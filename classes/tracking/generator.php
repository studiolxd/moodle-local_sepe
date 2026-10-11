<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace local_sepe\tracking;

/**
 * Generates the ACCION_FORMATIVA tracking XML from live Moodle data.
 *
 * For actions mapped to a Moodle course, the administrative shell of the
 * XML received from SEPE is kept (identifiers, description, specialities,
 * tutors) and the tracking sections are regenerated from the course:
 * USO and SEGUIMIENTO_EVALUACION from the standard log store, gradebook
 * and completion (see usage_calculator), and PARTICIPANTES from the
 * course enrolments, with their identity document taken from the
 * configured user field and their results from course completion and the
 * course grade.
 *
 * Participants are the users enrolled in the course (active or not) who
 * hold one of the site "graded roles" ($CFG->gradebookroles) in the
 * course context. Every query receives them as a subquery rather than an
 * IN list, so the cost does not grow with one query per participant and
 * large courses stay below the parameter limits of the database.
 *
 * The generated XML is cached in local_sepe_accion (see tracking_xml()):
 * obtenerAccion serves it while it is younger than CACHE_TTL, and the
 * refresh_tracking_data task regenerates it daily.
 *
 * @package    local_sepe
 * @copyright  2026 Studio LXD <hello@studiolxd.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class generator {
    /** @var int Age after which the cached tracking XML is regenerated, in seconds. */
    public const CACHE_TTL = DAYSECS;

    /** @var int Maximum age SEPE accepts for tracking data; older cache is never served. */
    public const MAX_STALE = 7 * DAYSECS;

    /**
     * Tracking XML of a mapped action, served from the cache when fresh.
     *
     * @param \stdClass $accion local_sepe_accion record with a non-null courseid
     * @return string|null ACCION_FORMATIVA XML fragment, or null when there is
     *     no usable data (callers fall back to rawxml)
     */
    public static function tracking_xml(\stdClass $accion): ?string {
        $cached = $accion->trackingxml ?? null;
        if ($cached !== null && time() - (int)$accion->trackinggenerated < self::CACHE_TTL) {
            return $cached;
        }
        return self::refresh($accion);
    }

    /**
     * Regenerates and caches the tracking XML of a mapped action.
     *
     * When regeneration fails, the last cached XML is still served while
     * it is within the 7-day freshness SEPE requires.
     *
     * @param \stdClass $accion local_sepe_accion record with a non-null courseid
     * @return string|null ACCION_FORMATIVA XML fragment, or null when there is
     *     no usable data (callers fall back to rawxml)
     */
    public static function refresh(\stdClass $accion): ?string {
        $now = time();
        $cached = $accion->trackingxml ?? null;
        $age = $cached !== null ? $now - (int)$accion->trackinggenerated : null;

        $xml = self::for_accion($accion);
        if ($xml !== null) {
            (new \local_sepe\local\accion_repository())->store_tracking((int)$accion->id, $xml, $now);
            return $xml;
        }

        if ($cached !== null && $age < self::MAX_STALE) {
            return $cached;
        }
        return null;
    }

    /**
     * Builds the tracking XML of a mapped action, bypassing the cache.
     *
     * @param \stdClass $accion local_sepe_accion record with a non-null courseid
     * @return string|null ACCION_FORMATIVA XML fragment, or null when the
     *     course data cannot be generated (callers fall back to rawxml)
     */
    public static function for_accion(\stdClass $accion): ?string {
        try {
            return (new self())->build($accion);
        } catch (\Throwable $e) {
            debugging('local_sepe: tracking generation failed for action '
                . $accion->codigo_accion . ': ' . $e->getMessage(), DEBUG_DEVELOPER);
            return null;
        }
    }

    /**
     * Builds the ACCION_FORMATIVA fragment.
     *
     * @param \stdClass $accion local_sepe_accion record
     * @return string XML fragment
     */
    private function build(\stdClass $accion): string {
        global $CFG, $DB;
        require_once($CFG->libdir . '/gradelib.php');

        $course = get_course($accion->courseid);
        $context = \context_course::instance($course->id);
        [$usersql, $userparams] = $this->students_sql($context);
        $userids = array_map('intval', $DB->get_fieldset_sql("$usersql ORDER BY id ASC", $userparams));

        $doc = new \DOMDocument('1.0', 'UTF-8');
        $doc->loadXML($accion->rawxml, LIBXML_NONET);
        $root = $doc->documentElement;

        [$from, $to] = $this->period($accion);
        $calculator = new usage_calculator($course->id, $usersql, $userparams, $from, $to);
        $uso = $calculator->uso();
        $seguimiento = $calculator->seguimiento_evaluacion();

        // Refresh the USO block of every speciality with the course figures.
        foreach ($this->children($root, 'ESPECIALIDADES_ACCION') as $especialidades) {
            foreach ($this->children($especialidades, 'ESPECIALIDAD') as $especialidad) {
                foreach ($this->children($especialidad, 'USO') as $old) {
                    $especialidad->replaceChild($this->build_uso($doc, $uso, $seguimiento), $old);
                }
            }
        }

        // Replace PARTICIPANTES with the live course enrolments.
        $participantes = $this->build_participantes($doc, $accion, $course, $userids, $usersql, $userparams);
        foreach ($this->children($root, 'PARTICIPANTES') as $old) {
            $root->replaceChild($participantes, $old);
            $participantes = null;
            break;
        }
        if ($participantes !== null) {
            $root->appendChild($participantes);
        }

        return $doc->saveXML($root);
    }

    /**
     * SQL selecting the ids of the participants of a course.
     *
     * Participants are users enrolled in the course (including suspended
     * or expired enrolments, which are reported as drop-outs) holding one
     * of the site graded roles in the course context.
     *
     * @param \context_course $context course context
     * @return array{0: string, 1: array} SQL returning an "id" column, and its named params
     */
    private function students_sql(\context_course $context): array {
        global $CFG, $DB;

        $roleids = array_filter(array_map('intval', explode(',', (string)($CFG->gradebookroles ?? ''))));
        if ($roleids === []) {
            return ['SELECT id FROM {user} WHERE 1 = 0', []];
        }
        [$enrolledsql, $params] = get_enrolled_sql($context, '', 0, false);
        [$rolesql, $roleparams] = $DB->get_in_or_equal($roleids, SQL_PARAMS_NAMED, 'sepegradedrole');
        $params += $roleparams;
        $params['sepestudentctx'] = $context->id;

        $sql = "SELECT id
                  FROM ($enrolledsql) sepeenrolled
                 WHERE EXISTS (
                           SELECT 1
                             FROM {role_assignments} ra
                            WHERE ra.userid = sepeenrolled.id
                                  AND ra.contextid = :sepestudentctx
                                  AND ra.roleid $rolesql
                       )";
        return [$sql, $params];
    }

    /**
     * Period of the action as timestamps, from FECHA_INICIO 00:00 to FECHA_FIN 23:59:59.
     *
     * @param \stdClass $accion local_sepe_accion record
     * @return array{0: int|null, 1: int|null} start and end, null when a date cannot be parsed
     */
    private function period(\stdClass $accion): array {
        $tz = \core_date::get_server_timezone_object();
        $start = \DateTime::createFromFormat('!d/m/Y', (string)$accion->fecha_inicio, $tz);
        $end = \DateTime::createFromFormat('!d/m/Y', (string)$accion->fecha_fin, $tz);
        return [
            $start !== false ? $start->getTimestamp() : null,
            $end !== false ? $end->getTimestamp() + DAYSECS - 1 : null,
        ];
    }

    /**
     * Builds a USO element with the computed figures.
     *
     * @param \DOMDocument $doc owner document
     * @param array $uso per-band usage from usage_calculator::uso()
     * @param array $seguimiento figures from usage_calculator::seguimiento_evaluacion()
     * @return \DOMElement USO element
     */
    private function build_uso(\DOMDocument $doc, array $uso, array $seguimiento): \DOMElement {
        $el = $doc->createElement('USO');

        $bands = ['manana' => 'HORARIO_MANANA', 'tarde' => 'HORARIO_TARDE', 'noche' => 'HORARIO_NOCHE'];
        foreach ($bands as $key => $name) {
            $band = $doc->createElement($name);
            $this->text($band, 'NUM_PARTICIPANTES', (string)$uso[$key]['participantes']);
            $this->text($band, 'NUMERO_ACCESOS', (string)$uso[$key]['accesos']);
            $this->text($band, 'DURACION_TOTAL', (string)$uso[$key]['duracion']);
            $el->appendChild($band);
        }

        $eval = $doc->createElement('SEGUIMIENTO_EVALUACION');
        $this->text($eval, 'NUM_PARTICIPANTES', (string)$seguimiento['participantes']);
        $this->text($eval, 'NUMERO_ACTIVIDADES_APRENDIZAJE', (string)$seguimiento['aprendizaje']);
        $this->text($eval, 'NUMERO_INTENTOS', (string)$seguimiento['intentos']);
        $this->text($eval, 'NUMERO_ACTIVIDADES_EVALUACION', (string)$seguimiento['evaluacion']);
        $el->appendChild($eval);

        return $el;
    }

    /**
     * Builds the PARTICIPANTES element from the course enrolments.
     *
     * Students without a parseable identity document in the configured
     * field are skipped (and reported through debugging), because the
     * SEPE identifier is mandatory for every participant.
     *
     * @param \DOMDocument $doc owner document
     * @param \stdClass $accion local_sepe_accion record
     * @param \stdClass $course course record
     * @param int[] $userids participant ids, in report order
     * @param string $usersql SQL returning the participant ids
     * @param array $userparams named params of $usersql
     * @return \DOMElement PARTICIPANTES element
     */
    private function build_participantes(
        \DOMDocument $doc,
        \stdClass $accion,
        \stdClass $course,
        array $userids,
        string $usersql,
        array $userparams
    ): \DOMElement {
        $el = $doc->createElement('PARTICIPANTES');
        if ($userids === []) {
            return $el;
        }

        $especialidades = $this->accion_especialidades($doc);
        $nifs = nif::raw_values($usersql, $userparams);
        $enrolments = $this->enrolment_periods($course->id, $usersql, $userparams);
        $courseitem = \grade_item::fetch_course_item($course->id);
        $grades = $this->final_grades($courseitem, $usersql, $userparams);
        $completions = $this->completions($course->id, $usersql, $userparams);

        foreach ($userids as $userid) {
            $id = nif::parse($nifs[$userid] ?? '');
            if ($id === null) {
                debugging(
                    "local_sepe: student {$userid} has no valid NIF/NIE, skipped from SEPE tracking",
                    DEBUG_DEVELOPER
                );
                continue;
            }

            $participante = $doc->createElement('PARTICIPANTE');

            $idel = $doc->createElement('ID_PARTICIPANTE');
            $this->text($idel, 'TIPO_DOCUMENTO', $id['tipo_documento']);
            $this->text($idel, 'NUM_DOCUMENTO', $id['num_documento']);
            $this->text($idel, 'LETRA_NIF', $id['letra_nif']);
            $participante->appendChild($idel);

            // 01 = meets the normative access requirements (data model B12.1.2).
            $this->text($participante, 'INDICADOR_COMPETENCIAS_CLAVE', '01');
            $participante->appendChild($doc->createElement('CONTRATO_FORMACION'));

            $espsel = $doc->createElement('ESPECIALIDADES_PARTICIPANTE');
            $enrolment = $enrolments[$userid] ?? null;
            $resultados = $this->resultados(
                $accion,
                $courseitem,
                $grades[$userid] ?? null,
                isset($completions[$userid]),
                $enrolment
            );
            foreach ($especialidades as $especialidad) {
                $espsel->appendChild(
                    $this->build_especialidad_participante($doc, $especialidad, $enrolment, $resultados)
                );
            }
            $participante->appendChild($espsel);

            $el->appendChild($participante);
        }

        return $el;
    }

    /**
     * Builds one ESPECIALIDAD element of ESPECIALIDADES_PARTICIPANTE.
     *
     * @param \DOMDocument $doc owner document
     * @param array $especialidad triplet with origen, area and codigo
     * @param \stdClass|null $enrolment enrolment period of the participant
     * @param array $resultados computed results (resultado, calificacion, puntuacion)
     * @return \DOMElement ESPECIALIDAD element
     */
    private function build_especialidad_participante(
        \DOMDocument $doc,
        array $especialidad,
        ?\stdClass $enrolment,
        array $resultados
    ): \DOMElement {
        $el = $doc->createElement('ESPECIALIDAD');

        $id = $doc->createElement('ID_ESPECIALIDAD');
        $this->text($id, 'ORIGEN_ESPECIALIDAD', $especialidad['origen']);
        $this->text($id, 'AREA_PROFESIONAL', $especialidad['area']);
        $this->text($id, 'CODIGO_ESPECIALIDAD', $especialidad['codigo']);
        $el->appendChild($id);

        if ($enrolment !== null && (int)$enrolment->timestart > 0) {
            $this->text($el, 'FECHA_ALTA', $this->fecha((int)$enrolment->timestart));
        }
        if ($enrolment !== null && (int)$enrolment->timeend > 0 && (int)$enrolment->timeend < time()) {
            $this->text($el, 'FECHA_BAJA', $this->fecha((int)$enrolment->timeend));
        }

        $el->appendChild($doc->createElement('TUTORIAS_PRESENCIALES'));
        $el->appendChild($doc->createElement('EVALUACION_FINAL'));

        $res = $doc->createElement('RESULTADOS');
        $this->text($res, 'RESULTADO_FINAL', $resultados['resultado']);
        if ($resultados['calificacion'] !== null) {
            $this->text($res, 'CALIFICACION_FINAL', (string)$resultados['calificacion']);
        }
        if ($resultados['puntuacion'] !== null) {
            $this->text($res, 'PUNTUACION_FINAL', (string)$resultados['puntuacion']);
        }
        $el->appendChild($res);

        return $el;
    }

    /**
     * Computes the SEPE results of one participant.
     *
     * RESULTADO_FINAL codes (data model B12.1.4.6.1): 0 started,
     * 2 dropped out, 3 finished with positive evaluation, 4 finished with
     * negative evaluation, 5 finished without evaluation. Grades are
     * reported on the 5-10 scale multiplied by 100, as the model requires.
     *
     * @param \stdClass $accion local_sepe_accion record
     * @param \grade_item|false $courseitem course grade item
     * @param string|null $finalgrade course final grade of the participant, null if ungraded
     * @param bool $completed whether the participant completed the course
     * @param \stdClass|null $enrolment enrolment period of the participant
     * @return array{resultado: string, calificacion: int|null, puntuacion: int|null}
     */
    private function resultados(
        \stdClass $accion,
        $courseitem,
        ?string $finalgrade,
        bool $completed,
        ?\stdClass $enrolment
    ): array {
        $grade10 = null;
        if ($courseitem && $finalgrade !== null && (float)$courseitem->grademax > 0) {
            $grade10 = (float)$finalgrade / (float)$courseitem->grademax * 10;
        }

        if ($completed) {
            $resultado = ($grade10 !== null && $grade10 < 5) ? '4' : '3';
        } else if (
            $enrolment !== null
                && ((int)$enrolment->status === ENROL_USER_SUSPENDED
                    || ((int)$enrolment->timeend > 0 && (int)$enrolment->timeend < time()))
        ) {
            $resultado = '2';
        } else if ($this->fecha_fin_passed($accion->fecha_fin)) {
            $resultado = '5';
        } else {
            $resultado = '0';
        }

        $calificacion = null;
        $puntuacion = null;
        if ($grade10 !== null && ($resultado === '3' || $resultado === '4')) {
            $calificacion = (int)round($grade10 * 100);
            if ($resultado === '3') {
                $puntuacion = max(500, $calificacion);
            }
        }

        return ['resultado' => $resultado, 'calificacion' => $calificacion, 'puntuacion' => $puntuacion];
    }

    /**
     * Speciality identifier triplets declared in the action XML.
     *
     * Falls back to ID_ESPECIALIDAD_PRINCIPAL when the action declares no
     * specialities, so every participant reports at least one.
     *
     * @param \DOMDocument $doc parsed rawxml document
     * @return array<int, array{origen: string, area: string, codigo: string}>
     */
    private function accion_especialidades(\DOMDocument $doc): array {
        $result = [];
        $root = $doc->documentElement;
        foreach ($this->children($root, 'ESPECIALIDADES_ACCION') as $container) {
            foreach ($this->children($container, 'ESPECIALIDAD') as $especialidad) {
                foreach ($this->children($especialidad, 'ID_ESPECIALIDAD') as $id) {
                    $result[] = $this->triplet($id);
                }
            }
        }
        if ($result === []) {
            foreach ($this->children($root, 'ID_ESPECIALIDAD_PRINCIPAL') as $id) {
                $result[] = $this->triplet($id);
            }
        }
        return $result;
    }

    /**
     * Reads an ID_ESPECIALIDAD triplet.
     *
     * @param \DOMElement $id ID_ESPECIALIDAD(_PRINCIPAL) element
     * @return array{origen: string, area: string, codigo: string}
     */
    private function triplet(\DOMElement $id): array {
        $get = function (string $name) use ($id): string {
            foreach ($this->children($id, $name) as $child) {
                return trim($child->textContent);
            }
            return '';
        };
        return [
            'origen' => $get('ORIGEN_ESPECIALIDAD'),
            'area' => $get('AREA_PROFESIONAL'),
            'codigo' => $get('CODIGO_ESPECIALIDAD'),
        ];
    }

    /**
     * Enrolment periods of the participants in a course.
     *
     * Users enrolled through several methods report the earliest start
     * and, when every method has an end date, the latest end.
     *
     * @param int $courseid course id
     * @param string $usersql SQL returning the participant ids
     * @param array $userparams named params of $usersql
     * @return array<int, \stdClass> objects with timestart, timeend and status, keyed by user id
     */
    private function enrolment_periods(int $courseid, string $usersql, array $userparams): array {
        global $DB;

        $params = $userparams + ['sepeenrolcourse' => $courseid];

        $rows = $DB->get_records_sql(
            "SELECT ue.userid, MIN(ue.timestart) AS timestart, MAX(ue.timeend) AS timeend,
                    MIN(ue.status) AS status, MIN(ue.timecreated) AS timecreated
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid
              WHERE e.courseid = :sepeenrolcourse AND ue.userid IN ($usersql)
           GROUP BY ue.userid",
            $params
        );

        foreach ($rows as $row) {
            if ((int)$row->timestart === 0) {
                $row->timestart = (int)$row->timecreated;
            }
        }
        return $rows;
    }

    /**
     * Course final grades of the participants.
     *
     * @param \grade_item|false $courseitem course grade item
     * @param string $usersql SQL returning the participant ids
     * @param array $userparams named params of $usersql
     * @return array<int, string|null> final grade keyed by user id
     */
    private function final_grades($courseitem, string $usersql, array $userparams): array {
        global $DB;
        if (!$courseitem) {
            return [];
        }
        return $DB->get_records_sql_menu(
            "SELECT userid, finalgrade
               FROM {grade_grades}
              WHERE itemid = :sepegradeitem AND userid IN ($usersql)",
            $userparams + ['sepegradeitem' => $courseitem->id]
        );
    }

    /**
     * Participants who completed the course.
     *
     * @param int $courseid course id
     * @param string $usersql SQL returning the participant ids
     * @param array $userparams named params of $usersql
     * @return array<int, string> completion time keyed by user id
     */
    private function completions(int $courseid, string $usersql, array $userparams): array {
        global $DB;
        return $DB->get_records_sql_menu(
            "SELECT userid, timecompleted
               FROM {course_completions}
              WHERE course = :sepecompletioncourse AND timecompleted > 0 AND userid IN ($usersql)",
            $userparams + ['sepecompletioncourse' => $courseid]
        );
    }

    /**
     * Whether the action end date (dd/mm/yyyy) is in the past.
     *
     * @param string $fechafin FECHA_FIN as stored, dd/mm/yyyy
     * @return bool
     */
    private function fecha_fin_passed(string $fechafin): bool {
        $date = \DateTime::createFromFormat('d/m/Y', $fechafin, \core_date::get_server_timezone_object());
        return $date !== false && $date->getTimestamp() < time() - DAYSECS;
    }

    /**
     * Formats a timestamp as dd/mm/yyyy in the server timezone.
     *
     * @param int $timestamp epoch seconds
     * @return string formatted date
     */
    private function fecha(int $timestamp): string {
        $date = new \DateTime('@' . $timestamp);
        $date->setTimezone(\core_date::get_server_timezone_object());
        return $date->format('d/m/Y');
    }

    /**
     * Appends an unqualified text element.
     *
     * @param \DOMElement $parent parent element
     * @param string $name element local name
     * @param string $value text content, safely escaped by DOM
     */
    private function text(\DOMElement $parent, string $name, string $value): void {
        $el = $parent->ownerDocument->createElement($name);
        $el->textContent = $value;
        $parent->appendChild($el);
    }

    /**
     * Direct children of an element with the given local name.
     *
     * @param \DOMElement $parent parent element
     * @param string $name local name to match
     * @return \DOMElement[]
     */
    private function children(\DOMElement $parent, string $name): array {
        $result = [];
        foreach ($parent->childNodes as $child) {
            if ($child instanceof \DOMElement && $child->localName === $name) {
                $result[] = $child;
            }
        }
        return $result;
    }
}
