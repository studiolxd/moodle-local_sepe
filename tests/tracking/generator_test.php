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

use local_sepe\local\accion_repository;
use local_sepe\soap\request_parser;

/**
 * Tests for the Moodle-backed tracking data generator.
 *
 * @package    local_sepe
 * @copyright  2026 Studio LXD <hello@studiolxd.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(generator::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(usage_calculator::class)]
final class generator_test extends \advanced_testcase {
    /**
     * Creates the fixture action mapped to a fresh course.
     *
     * @param int $courseid course to map the action to
     * @return \stdClass local_sepe_accion record
     */
    private function create_mapped_accion(int $courseid): \stdClass {
        $raw = file_get_contents(__DIR__ . '/../fixtures/request_crearAccion_crearAccion_2014280010.xml');
        $doc = new \DOMDocument();
        $doc->loadXML($raw);
        $parser = new request_parser();
        $parsed = $parser->parse_accion_formativa($parser->operation($doc));

        $repository = new accion_repository();
        $record = $repository->create($parsed);
        $repository->set_course($record->id, $courseid);
        return $repository->get('20', '2014280010');
    }

    /**
     * Inserts a fake session in the standard log store.
     *
     * @param \stdClass $course course
     * @param int $userid user
     * @param int $start first event timestamp
     * @param int $end last event timestamp
     */
    private function insert_session(\stdClass $course, int $userid, int $start, int $end): void {
        global $DB;
        $context = \context_course::instance($course->id);
        for ($time = $start; $time <= $end; $time += MINSECS * 30) {
            $DB->insert_record('logstore_standard_log', (object)[
                'eventname' => '\\core\\event\\course_viewed',
                'component' => 'core',
                'action' => 'viewed',
                'target' => 'course',
                'crud' => 'r',
                'edulevel' => 2,
                'contextid' => $context->id,
                'contextlevel' => CONTEXT_COURSE,
                'contextinstanceid' => $course->id,
                'userid' => $userid,
                'courseid' => $course->id,
                'anonymous' => 0,
                'other' => 'N;',
                'timecreated' => min($time, $end),
                'origin' => 'web',
                'ip' => '127.0.0.1',
            ]);
        }
    }

    public function test_generates_tracking_from_course_data(): void {
        global $CFG;
        require_once($CFG->libdir . '/gradelib.php');

        $this->resetAfterTest();
        set_config('niffield', 'idnumber', 'local_sepe');

        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['enablecompletion' => 1]);
        $completed = $generator->create_user(['idnumber' => '12345678Z']);
        $inprogress = $generator->create_user(['idnumber' => 'X1234567L']);
        $withoutnif = $generator->create_user();
        $teacher = $generator->create_user(['idnumber' => '99999999R']);

        $enrolstart = make_timestamp(2026, 6, 1, 9, 0, 0);
        $generator->enrol_user($completed->id, $course->id, 'student', 'manual', $enrolstart);
        $generator->enrol_user($inprogress->id, $course->id, 'student', 'manual', $enrolstart);
        $generator->enrol_user($withoutnif->id, $course->id, 'student', 'manual', $enrolstart);
        $generator->enrol_user($teacher->id, $course->id, 'editingteacher');

        // Morning session of two hours and an evening session of an hour
        // and a half (which the SEPE rule rounds down to one hour).
        $this->insert_session(
            $course,
            $completed->id,
            make_timestamp(2026, 6, 2, 8, 0, 0),
            make_timestamp(2026, 6, 2, 10, 0, 0)
        );
        $this->insert_session(
            $course,
            $inprogress->id,
            make_timestamp(2026, 6, 2, 16, 0, 0),
            make_timestamp(2026, 6, 2, 17, 30, 0)
        );

        // Final course grade 8.5/10 and completion for the first student.
        $courseitem = \grade_item::fetch_course_item($course->id);
        $courseitem->update_final_grade($completed->id, 85, 'test');
        $completion = new \completion_completion(['course' => $course->id, 'userid' => $completed->id]);
        $completion->mark_enrolled();
        $completion->mark_complete();

        $accion = $this->create_mapped_accion($course->id);
        $xml = generator::for_accion($accion);
        $this->assertNotNull($xml);

        $doc = new \DOMDocument();
        $doc->loadXML($xml);

        // The teacher and the student without NIF are not reported.
        $participantes = $doc->getElementsByTagName('PARTICIPANTE');
        $this->assertSame(2, $participantes->length);
        $this->assertStringContainsString('<NUM_DOCUMENTO>0012345678</NUM_DOCUMENTO>', $xml);
        $this->assertStringContainsString('<NUM_DOCUMENTO>00X1234567</NUM_DOCUMENTO>', $xml);
        $this->assertStringNotContainsString('<NUM_DOCUMENTO>0099999999</NUM_DOCUMENTO>', $xml);

        // Results: completed with 8.5 → 3/850/850; the action end date
        // (14/12/2014) has passed, so the other student reports 5.
        $results = $this->results_by_document($doc);
        $this->assertSame(
            ['resultado' => '3', 'calificacion' => '850', 'puntuacion' => '850'],
            $results['0012345678']
        );
        $this->assertSame(
            ['resultado' => '5', 'calificacion' => null, 'puntuacion' => null],
            $results['00X1234567']
        );

        // Enrolment date is reported as FECHA_ALTA.
        $this->assertStringContainsString('<FECHA_ALTA>01/06/2026</FECHA_ALTA>', $xml);

        // USO: one two-hour morning session, one evening session rounded
        // down to one hour, nothing at night.
        $uso = $doc->getElementsByTagName('USO')->item(0);
        $this->assertNotNull($uso);
        $this->assert_uso($uso, 'HORARIO_MANANA', 1, 1, 2);
        $this->assert_uso($uso, 'HORARIO_TARDE', 1, 1, 1);
        $this->assert_uso($uso, 'HORARIO_NOCHE', 0, 0, 0);

        // The generated document validates against the (corrected) SEPE XSD.
        $this->assert_validates_against_xsd($xml);
    }

    public function test_returns_null_when_course_is_missing(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $accion = $this->create_mapped_accion($course->id);
        delete_course($course->id, false);

        $this->assertNull(generator::for_accion($accion));
        $this->assertDebuggingCalled();
    }

    /**
     * Collects RESULTADOS per participant document number.
     *
     * @param \DOMDocument $doc generated document
     * @return array<string, array{resultado: string|null, calificacion: string|null, puntuacion: string|null}>
     */
    private function results_by_document(\DOMDocument $doc): array {
        $results = [];
        foreach ($doc->getElementsByTagName('PARTICIPANTE') as $participante) {
            $document = $participante->getElementsByTagName('NUM_DOCUMENTO')->item(0)->textContent;
            $resultados = $participante->getElementsByTagName('RESULTADOS')->item(0);
            $get = static function (string $name) use ($resultados): ?string {
                $el = $resultados->getElementsByTagName($name)->item(0);
                return $el?->textContent;
            };
            $results[$document] = [
                'resultado' => $get('RESULTADO_FINAL'),
                'calificacion' => $get('CALIFICACION_FINAL'),
                'puntuacion' => $get('PUNTUACION_FINAL'),
            ];
        }
        return $results;
    }

    /**
     * Asserts the figures of one USO band.
     *
     * @param \DOMElement $uso USO element
     * @param string $band band element name
     * @param int $participantes expected NUM_PARTICIPANTES
     * @param int $accesos expected NUMERO_ACCESOS
     * @param int $duracion expected DURACION_TOTAL
     */
    private function assert_uso(\DOMElement $uso, string $band, int $participantes, int $accesos, int $duracion): void {
        $el = $uso->getElementsByTagName($band)->item(0);
        $this->assertNotNull($el, "USO has no {$band}");
        $this->assertSame(
            (string)$participantes,
            $el->getElementsByTagName('NUM_PARTICIPANTES')->item(0)->textContent,
            "{$band} participants"
        );
        $this->assertSame(
            (string)$accesos,
            $el->getElementsByTagName('NUMERO_ACCESOS')->item(0)->textContent,
            "{$band} accesses"
        );
        $this->assertSame(
            (string)$duracion,
            $el->getElementsByTagName('DURACION_TOTAL')->item(0)->textContent,
            "{$band} duration"
        );
    }

    /**
     * Validates a generated ACCION_FORMATIVA against the embedded XSD,
     * wrapped in the schema root element.
     *
     * @param string $accionxml generated ACCION_FORMATIVA fragment
     */
    private function assert_validates_against_xsd(string $accionxml): void {
        $wrapper = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<ACCIONES_INICIADAS_DEL_CENTRO>'
            . '<DATOS_IDENTIFICATIVOS>'
            . '<ID_CENTRO><ORIGEN_CENTRO>20</ORIGEN_CENTRO><CODIGO_CENTRO>9999999999</CODIGO_CENTRO></ID_CENTRO>'
            . '<NOMBRE_CENTRO>Centro de teleformación número 00000001</NOMBRE_CENTRO>'
            . '<URL_PLATAFORMA>https://sede.sepe.gob.es/portalSede/flows/inicio</URL_PLATAFORMA>'
            . '<URL_SEGUIMIENTO>https://example.com/local/sepe/endpoint.php</URL_SEGUIMIENTO>'
            . '<TELEFONO>915859999</TELEFONO>'
            . '<EMAIL>bajel@sepe.es</EMAIL>'
            . '</DATOS_IDENTIFICATIVOS>'
            . '<ACCIONES_FORMATIVAS>' . $accionxml . '</ACCIONES_FORMATIVAS>'
            . '</ACCIONES_INICIADAS_DEL_CENTRO>';

        $doc = new \DOMDocument();
        $doc->loadXML($wrapper);
        libxml_use_internal_errors(true);
        $valid = $doc->schemaValidate(__DIR__ . '/../../xsd/ACCIONES_INICIADAS_DEL_CENTRO_V11.xsd');
        $errors = array_map(static fn($e) => trim($e->message), libxml_get_errors());
        libxml_clear_errors();
        libxml_use_internal_errors(false);
        $this->assertTrue($valid, 'Generated XML does not validate: ' . implode('; ', $errors));
    }
}
