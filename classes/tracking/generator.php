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
 * @package    local_sepe
 * @copyright  2026 Studio LXD <hello@studiolxd.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class generator {
    /**
     * Builds the tracking XML of a mapped action.
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
        global $CFG;
        require_once($CFG->libdir . '/gradelib.php');
        require_once($CFG->dirroot . '/user/profile/lib.php');

        $course = get_course($accion->courseid);
        $context = \context_course::instance($course->id);
        $students = $this->students($context);
        $userids = array_keys($students);

        $doc = new \DOMDocument('1.0', 'UTF-8');
        $doc->loadXML($accion->rawxml, LIBXML_NONET);
        $root = $doc->documentElement;

        $calculator = new usage_calculator($course->id, $userids);
        $uso = $calculator->uso();
        $seguimiento = $calculator->seguimiento_evaluacion();

        // Refresh the USO block of every speciality with the course figures.
        foreach ($this->find($root, 'ESPECIALIDADES_ACCION') as $especialidades) {
            foreach ($this->children($especialidades, 'ESPECIALIDAD') as $especialidad) {
                foreach ($this->children($especialidad, 'USO') as $old) {
                    $especialidad->replaceChild($this->build_uso($doc, $uso, $seguimiento), $old);
                }
            }
        }

        // Replace PARTICIPANTES with the live course enrolments.
        $participantes = $this->build_participantes($doc, $accion, $course, $students);
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
     * Enrolled users holding a student-archetype role in the course.
     *
     * @param \context_course $context course context
     * @return array<int, \stdClass> user records keyed by id
     */
    private function students(\context_course $context): array {
        $studentroles = array_keys(get_archetype_roles('student'));
        if ($studentroles === []) {
            return [];
        }
        $users = get_role_users($studentroles, $context, false, 'u.*', 'u.id ASC');
        return $users;
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
     * @param \stdClass[] $students student records keyed by id
     * @return \DOMElement PARTICIPANTES element
     */
    private function build_participantes(\DOMDocument $doc, \stdClass $accion, \stdClass $course, array $students): \DOMElement {
        $el = $doc->createElement('PARTICIPANTES');
        $especialidades = $this->accion_especialidades($doc);
        $enrolments = $this->enrolment_periods($course->id, array_keys($students));
        $courseitem = \grade_item::fetch_course_item($course->id);

        foreach ($students as $student) {
            $id = nif::parse(nif::raw_value($student));
            if ($id === null) {
                debugging(
                    "local_sepe: student {$student->id} has no valid NIF/NIE, skipped from SEPE tracking",
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
            $enrolment = $enrolments[$student->id] ?? null;
            $resultados = $this->resultados($accion, $student->id, $courseitem, $enrolment);
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
     * @param int $userid participant user id
     * @param \grade_item|false $courseitem course grade item
     * @param \stdClass|null $enrolment enrolment period of the participant
     * @return array{resultado: string, calificacion: int|null, puntuacion: int|null}
     */
    private function resultados(\stdClass $accion, int $userid, $courseitem, ?\stdClass $enrolment): array {
        global $DB;

        $grade10 = null;
        if ($courseitem) {
            $grade = new \grade_grade(['itemid' => $courseitem->id, 'userid' => $userid]);
            if ($grade->finalgrade !== null && (float)$courseitem->grademax > 0) {
                $grade10 = (float)$grade->finalgrade / (float)$courseitem->grademax * 10;
            }
        }

        $completed = $DB->get_field(
            'course_completions',
            'timecompleted',
            ['course' => $accion->courseid, 'userid' => $userid]
        );

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
        foreach ($this->find($root, 'ESPECIALIDADES_ACCION') as $container) {
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
     * @param int[] $userids participant user ids
     * @return array<int, \stdClass> objects with timestart, timeend and status, keyed by user id
     */
    private function enrolment_periods(int $courseid, array $userids): array {
        global $DB;

        if ($userids === []) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $params['courseid'] = $courseid;

        $rows = $DB->get_records_sql(
            "SELECT ue.userid, MIN(ue.timestart) AS timestart, MAX(ue.timeend) AS timeend,
                    MIN(ue.status) AS status, MIN(ue.timecreated) AS timecreated
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid
              WHERE e.courseid = :courseid AND ue.userid $insql
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

    /**
     * Alias of children() used where the intent is a lookup.
     *
     * @param \DOMElement $parent parent element
     * @param string $name local name to match
     * @return \DOMElement[]
     */
    private function find(\DOMElement $parent, string $name): array {
        return $this->children($parent, $name);
    }
}
