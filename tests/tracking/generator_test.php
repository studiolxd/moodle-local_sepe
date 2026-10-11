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

#[\PHPUnit\Framework\Attributes\CoversClass(generator::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(usage_calculator::class)]
/**
 * Tests for the Moodle-backed tracking data generator.
 *
 * @package    local_sepe
 * @copyright  2026 Studio LXD <hello@studiolxd.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_sepe\tracking\generator
 * @covers     \local_sepe\tracking\usage_calculator
 */
final class generator_test extends \advanced_testcase {
    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        // Usage figures come from the standard log store, disabled by default in PHPUnit.
        set_config('enabled_stores', 'logstore_standard', 'tool_log');
    }

    /**
     * Creates the fixture action mapped to a fresh course.
     *
     * The fixture dates (2014) are moved to June 2026, where the test
     * sessions happen, since only activity within the action is counted.
     *
     * @param int $courseid course to map the action to
     * @return \stdClass local_sepe_accion record
     */
    private function create_mapped_accion(int $courseid): \stdClass {
        global $DB;
        $raw = file_get_contents(__DIR__ . '/../fixtures/request_crearAccion_crearAccion_2014280010.xml');
        $doc = new \DOMDocument();
        $doc->loadXML($raw);
        $parser = new request_parser();
        $parsed = $parser->parse_accion_formativa($parser->operation($doc));

        $repository = new accion_repository();
        $record = $repository->create($parsed);
        $repository->set_course($record->id, $courseid);
        $DB->update_record('local_sepe_accion', (object)[
            'id' => $record->id,
            'fecha_inicio' => '01/06/2026',
            'fecha_fin' => '30/06/2026',
        ]);
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
        // Activity outside the action period (01/06/2026-30/06/2026) is ignored.
        $this->insert_session(
            $course,
            $completed->id,
            make_timestamp(2026, 5, 20, 9, 0, 0),
            make_timestamp(2026, 5, 20, 12, 0, 0)
        );
        $this->insert_session(
            $course,
            $completed->id,
            make_timestamp(2026, 7, 1, 9, 0, 0),
            make_timestamp(2026, 7, 1, 12, 0, 0)
        );

        // Final course grade 8.5/10 and completion for the first student.
        $courseitem = \grade_item::fetch_course_item($course->id);
        $courseitem->update_final_grade($completed->id, 85, 'test');
        $completion = new \completion_completion(['course' => $course->id, 'userid' => $completed->id]);
        $completion->mark_enrolled();
        $completion->mark_complete();

        $accion = $this->create_mapped_accion($course->id);
        $xml = generator::for_accion($accion);
        // The student without NIF is skipped and reported through debugging.
        $this->assertDebuggingCalled();
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
        // (30/06/2026) has passed, so the other student reports 5.
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

    public function test_participants_are_enrolled_users_with_graded_roles(): void {
        global $CFG, $DB;
        $this->resetAfterTest();
        set_config('niffield', 'idnumber', 'local_sepe');

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $context = \context_course::instance($course->id);

        // A custom student role counts once it is one of the graded roles.
        $customrole = $generator->create_role(['shortname' => 'alumnosepe', 'archetype' => 'student']);
        set_config('gradebookroles', $CFG->gradebookroles . ',' . $customrole);

        $student = $generator->create_user(['idnumber' => '12345678Z']);
        $custom = $generator->create_user(['idnumber' => 'X1234567L']);
        $suspended = $generator->create_user(['idnumber' => '87654321X']);
        $notenrolled = $generator->create_user(['idnumber' => '11111111H']);
        $generator->enrol_user($student->id, $course->id, 'student');
        $generator->enrol_user($custom->id, $course->id, 'alumnosepe');
        $generator->enrol_user($suspended->id, $course->id, 'student', 'manual', 0, 0, ENROL_USER_SUSPENDED);
        // A role assignment without an enrolment does not make a participant.
        role_assign($DB->get_field('role', 'id', ['shortname' => 'student']), $notenrolled->id, $context->id);

        $xml = generator::for_accion($this->create_mapped_accion($course->id));
        $this->assertNotNull($xml);
        $doc = new \DOMDocument();
        $doc->loadXML($xml);
        $results = $this->results_by_document($doc);

        $this->assertEqualsCanonicalizing(['0012345678', '00X1234567', '0087654321'], array_keys($results));
        // The suspended enrolment is reported as a drop-out.
        $this->assertSame('2', $results['0087654321']['resultado']);
    }

    public function test_nif_from_custom_profile_field(): void {
        $this->resetAfterTest();
        set_config('niffield', 'profile_nif', 'local_sepe');

        $generator = $this->getDataGenerator();
        $generator->create_custom_profile_field(['datatype' => 'text', 'shortname' => 'nif', 'name' => 'NIF']);
        $course = $generator->create_course();
        $withnif = $generator->create_user(['idnumber' => 'ignored', 'profile_field_nif' => '12345678-Z']);
        $withoutnif = $generator->create_user(['idnumber' => '87654321X']);
        $generator->enrol_user($withnif->id, $course->id, 'student');
        $generator->enrol_user($withoutnif->id, $course->id, 'student');

        $xml = generator::for_accion($this->create_mapped_accion($course->id));
        // The student without a value in the profile field is skipped.
        $this->assertDebuggingCalled();
        $this->assertNotNull($xml);
        $doc = new \DOMDocument();
        $doc->loadXML($xml);
        $this->assertSame(['0012345678'], array_keys($this->results_by_document($doc)));
    }

    public function test_unknown_nif_field_reports_no_participants(): void {
        $this->resetAfterTest();
        set_config('niffield', 'nosuchcolumn', 'local_sepe');

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $student = $generator->create_user(['idnumber' => '12345678Z']);
        $generator->enrol_user($student->id, $course->id, 'student');

        $xml = generator::for_accion($this->create_mapped_accion($course->id));
        $this->assertNotNull($xml);
        $this->assertDebuggingCalledCount(2);
        $this->assertStringNotContainsString('<PARTICIPANTE>', $xml);
    }

    public function test_tracking_xml_is_cached(): void {
        global $DB;
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $accion = $this->create_mapped_accion($course->id);

        // First request generates and stores the XML.
        $this->assertNull($accion->trackingxml);
        $xml = generator::tracking_xml($accion);
        $this->assertNotNull($xml);
        $stored = $DB->get_record('local_sepe_accion', ['id' => $accion->id]);
        $this->assertSame($xml, $stored->trackingxml);
        $this->assertGreaterThan(0, (int)$stored->trackinggenerated);

        // A fresh cache is served as is.
        $stored->trackingxml = '<ACCION_FORMATIVA>cached</ACCION_FORMATIVA>';
        $this->assertSame($stored->trackingxml, generator::tracking_xml($stored));

        // An expired cache is regenerated...
        $stored->trackinggenerated = time() - generator::CACHE_TTL - 1;
        $this->assertSame($xml, generator::tracking_xml($stored));

        // ...and so is a fresh one on a forced refresh.
        $stored->trackinggenerated = time();
        $this->assertSame($xml, generator::refresh($stored));

        // Remapping the action discards the cache.
        (new accion_repository())->set_course($accion->id, $course->id);
        $this->assertNull($DB->get_field('local_sepe_accion', 'trackingxml', ['id' => $accion->id]));
    }

    public function test_tracking_xml_serves_recent_cache_when_generation_fails(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $accion = $this->create_mapped_accion($course->id);
        $accion->courseid = -1;

        // Within the 7 days SEPE accepts, the last good XML is served.
        $accion->trackingxml = '<ACCION_FORMATIVA>cached</ACCION_FORMATIVA>';
        $accion->trackinggenerated = time() - generator::CACHE_TTL - 1;
        $this->assertSame($accion->trackingxml, generator::tracking_xml($accion));
        $this->assertDebuggingCalled();

        // Older than that, nothing is served (callers fall back to rawxml).
        $accion->trackinggenerated = time() - generator::MAX_STALE - 1;
        $this->assertNull(generator::tracking_xml($accion));
        $this->assertDebuggingCalled();
    }

    public function test_returns_null_when_course_is_missing(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $accion = $this->create_mapped_accion($course->id);
        delete_course($course->id, false);

        // The observer unmaps the action...
        $this->assertNull((new accion_repository())->get('20', '2014280010')->courseid);

        // ...and a stale record still fails gracefully.
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
