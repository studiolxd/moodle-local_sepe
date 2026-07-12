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
     * Creates an action with one tutor and one participant mapped to a user.
     *
     * @param int $userid user to map both records to
     * @return int action record id
     */
    private function create_linked_data(int $userid): int {
        global $DB;
        $accionid = $DB->insert_record('local_sepe_accion', (object)[
            'origen_accion' => '20',
            'codigo_accion' => '2026000001',
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
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
        $DB->insert_record('local_sepe_tutor', (object)[
            'accionid' => $accionid,
            'tipo_documento' => 'D',
            'num_documento' => '0012345678',
            'letra_nif' => 'Z',
            'acreditacion' => 'Licenciado',
            'userid' => $userid,
        ]);
        $DB->insert_record('local_sepe_participante', (object)[
            'accionid' => $accionid,
            'tipo_documento' => 'D',
            'num_documento' => '0087654321',
            'letra_nif' => 'X',
            'userid' => $userid,
        ]);
        return $accionid;
    }

    public function test_get_metadata(): void {
        $collection = provider::get_metadata(new collection('local_sepe'));
        $this->assertCount(4, $collection->get_collection());
    }

    public function test_get_contexts_for_userid(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->create_linked_data($user->id);

        $contextlist = provider::get_contexts_for_userid($user->id);
        $this->assertCount(1, $contextlist);
        $this->assertSame(\context_system::instance()->id, $contextlist->current()->id);
    }

    public function test_get_users_in_context(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $this->create_linked_data($user->id);

        $userlist = new userlist(\context_system::instance(), 'local_sepe');
        provider::get_users_in_context($userlist);
        $this->assertEqualsCanonicalizing([$user->id], $userlist->get_userids());
        $this->assertNotContains($other->id, $userlist->get_userids());
    }

    public function test_export_user_data(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->create_linked_data($user->id);

        $context = \context_system::instance();
        $contextlist = new approved_contextlist($user, 'local_sepe', [$context->id]);
        provider::export_user_data($contextlist);

        $writer = writer::with_context($context);
        $this->assertTrue($writer->has_any_data());
        $tutores = $writer->get_data([get_string('pluginname', 'local_sepe'), 'tutores']);
        $this->assertSame('0012345678', $tutores->records[0]['num_documento']);
    }

    public function test_delete_data_for_user_unlinks_records(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->create_linked_data($user->id);

        $context = \context_system::instance();
        provider::delete_data_for_user(new approved_contextlist($user, 'local_sepe', [$context->id]));

        // Records survive (FPE legal retention) but the user link is gone.
        $this->assertSame(1, $DB->count_records('local_sepe_tutor'));
        $this->assertSame(0, $DB->count_records_select('local_sepe_tutor', 'userid IS NOT NULL'));
        $this->assertSame(0, $DB->count_records_select('local_sepe_participante', 'userid IS NOT NULL'));
    }

    public function test_delete_data_for_users(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->create_linked_data($user->id);

        $userlist = new approved_userlist(\context_system::instance(), 'local_sepe', [$user->id]);
        provider::delete_data_for_users($userlist);
        $this->assertSame(0, $DB->count_records_select('local_sepe_tutor', 'userid IS NOT NULL'));
    }

    public function test_delete_data_for_all_users_in_context(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->create_linked_data($user->id);

        provider::delete_data_for_all_users_in_context(\context_system::instance());
        $this->assertSame(0, $DB->count_records_select('local_sepe_participante', 'userid IS NOT NULL'));
    }
}
