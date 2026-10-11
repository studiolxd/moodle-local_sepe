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

namespace local_sepe\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Tests for the privacy provider.
 *
 * @package    local_sepe
 * @copyright  2026 Studio LXD <hello@studiolxd.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(provider::class)]
final class provider_test extends \core_privacy\tests\provider_testcase {
    /**
     * Creates an action mapped to a course with a cached tracking XML.
     *
     * @param int $courseid mapped course
     * @param string $codigo CODIGO_ACCION
     * @return int action record id
     */
    private function create_cached_accion(int $courseid, string $codigo = '2026000001'): int {
        global $DB;
        return $DB->insert_record('local_sepe_accion', (object)[
            'origen_accion' => '20',
            'codigo_accion' => $codigo,
            'situacion' => '50',
            'origen_especialidad' => '20',
            'area_profesional' => 'SSCE',
            'codigo_especialidad' => 'SSCE0110',
            'duracion' => 380,
            'fecha_inicio' => '01/09/2026',
            'fecha_fin' => '20/12/2026',
            'itinerario_completo' => 'SI',
            'tipo_financiacion' => 'PR',
            'numero_asistentes' => 60,
            'denominacion' => 'Docencia de la formación profesional para el empleo',
            'rawxml' => '<ACCION_FORMATIVA/>',
            'courseid' => $courseid,
            'trackingxml' => '<ACCION_FORMATIVA><PARTICIPANTES/></ACCION_FORMATIVA>',
            'trackinggenerated' => time(),
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
    }

    /**
     * Creates a course with a cached action and an enrolled student.
     *
     * @return array{0: \stdClass, 1: int} the student and the action id
     */
    private function create_student_with_cached_accion(): array {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $user = $generator->create_user();
        $generator->enrol_user($user->id, $course->id, 'student');
        return [$user, $this->create_cached_accion($course->id)];
    }

    public function test_get_metadata(): void {
        $collection = provider::get_metadata(new collection('local_sepe'));
        $this->assertCount(5, $collection->get_collection());
    }

    public function test_get_contexts_for_userid(): void {
        $this->resetAfterTest();
        [$user] = $this->create_student_with_cached_accion();
        $other = $this->getDataGenerator()->create_user();

        $contextlist = provider::get_contexts_for_userid($user->id);
        $this->assertCount(1, $contextlist);
        $this->assertSame(\context_system::instance()->id, $contextlist->current()->id);
        $this->assertCount(0, provider::get_contexts_for_userid($other->id));
    }

    public function test_no_context_without_cached_tracking(): void {
        global $DB;
        $this->resetAfterTest();
        [$user, $accionid] = $this->create_student_with_cached_accion();
        $DB->set_field('local_sepe_accion', 'trackingxml', null, ['id' => $accionid]);
        $DB->set_field('local_sepe_accion', 'trackinggenerated', null, ['id' => $accionid]);

        $this->assertCount(0, provider::get_contexts_for_userid($user->id));
    }

    public function test_get_users_in_context(): void {
        $this->resetAfterTest();
        [$user] = $this->create_student_with_cached_accion();
        $this->getDataGenerator()->create_user();

        $userlist = new userlist(\context_system::instance(), 'local_sepe');
        provider::get_users_in_context($userlist);
        $this->assertEquals([$user->id], $userlist->get_userids());
    }

    public function test_export_user_data(): void {
        $this->resetAfterTest();
        [$user] = $this->create_student_with_cached_accion();

        $context = \context_system::instance();
        provider::export_user_data(new approved_contextlist($user, 'local_sepe', [$context->id]));
        $writer = writer::with_context($context);
        $this->assertTrue($writer->has_any_data());
        $data = $writer->get_data([get_string('pluginname', 'local_sepe'), '20/2026000001']);
        $this->assertSame('20/2026000001', $data->codigo_accion);
    }

    public function test_delete_data_for_user_discards_affected_cache(): void {
        global $DB;
        $this->resetAfterTest();
        [$user, $accionid] = $this->create_student_with_cached_accion();
        $othercourse = $this->getDataGenerator()->create_course();
        $otherid = $this->create_cached_accion($othercourse->id, '2026000002');

        $context = \context_system::instance();
        provider::delete_data_for_user(new approved_contextlist($user, 'local_sepe', [$context->id]));

        $this->assertNull($DB->get_field('local_sepe_accion', 'trackingxml', ['id' => $accionid]));
        $this->assertNotNull($DB->get_field('local_sepe_accion', 'trackingxml', ['id' => $otherid]));
        // The action itself is kept (FPE legal retention).
        $this->assertSame(2, $DB->count_records('local_sepe_accion'));
    }

    public function test_delete_data_for_users(): void {
        global $DB;
        $this->resetAfterTest();
        [$user, $accionid] = $this->create_student_with_cached_accion();

        $userlist = new approved_userlist(\context_system::instance(), 'local_sepe', [$user->id]);
        provider::delete_data_for_users($userlist);
        $this->assertNull($DB->get_field('local_sepe_accion', 'trackinggenerated', ['id' => $accionid]));
    }

    public function test_delete_data_for_all_users_in_context(): void {
        global $DB;
        $this->resetAfterTest();
        $this->create_student_with_cached_accion();

        provider::delete_data_for_all_users_in_context(\context_system::instance());
        $this->assertSame(0, $DB->count_records_select('local_sepe_accion', 'trackinggenerated IS NOT NULL'));
    }
}
