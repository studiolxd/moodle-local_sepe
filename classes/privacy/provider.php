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
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for local_sepe.
 *
 * The plugin stores personal data of two kinds: identity documents of
 * tutors and participants received from SEPE (third parties that may or
 * may not exist as Moodle users), and raw SOAP payloads in the request
 * log. Records linked to a Moodle user through the userid mapping are
 * exported and deleted through this provider; unlinked third-party data
 * is retained under the legal obligations of the FPE regulations (Orden
 * TMS/369/2019), as documented in the plugin README.
 *
 * @package    local_sepe
 * @copyright  2026 Studio LXD <hello@studiolxd.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    #[\Override]
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_sepe_tutor', [
            'tipo_documento' => 'privacy:metadata:local_sepe_tutor:tipo_documento',
            'num_documento' => 'privacy:metadata:local_sepe_tutor:num_documento',
            'letra_nif' => 'privacy:metadata:local_sepe_tutor:letra_nif',
            'acreditacion' => 'privacy:metadata:local_sepe_tutor:acreditacion',
            'userid' => 'privacy:metadata:local_sepe_tutor:userid',
        ], 'privacy:metadata:local_sepe_tutor');

        $collection->add_database_table('local_sepe_participante', [
            'tipo_documento' => 'privacy:metadata:local_sepe_participante:tipo_documento',
            'num_documento' => 'privacy:metadata:local_sepe_participante:num_documento',
            'letra_nif' => 'privacy:metadata:local_sepe_participante:letra_nif',
            'userid' => 'privacy:metadata:local_sepe_participante:userid',
        ], 'privacy:metadata:local_sepe_participante');

        $collection->add_database_table('local_sepe_accion', [
            'rawxml' => 'privacy:metadata:local_sepe_accion:rawxml',
        ], 'privacy:metadata:local_sepe_accion');

        $collection->add_database_table('local_sepe_wslog', [
            'ip' => 'privacy:metadata:local_sepe_wslog:ip',
            'requestxml' => 'privacy:metadata:local_sepe_wslog:requestxml',
            'responsexml' => 'privacy:metadata:local_sepe_wslog:responsexml',
        ], 'privacy:metadata:local_sepe_wslog');

        return $collection;
    }

    #[\Override]
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $sql = "SELECT c.id
                  FROM {context} c
                 WHERE c.contextlevel = :contextlevel
                   AND (EXISTS (SELECT 1 FROM {local_sepe_tutor} t WHERE t.userid = :userid1)
                        OR EXISTS (SELECT 1 FROM {local_sepe_participante} p WHERE p.userid = :userid2))";
        $contextlist->add_from_sql($sql, [
            'contextlevel' => CONTEXT_SYSTEM,
            'userid1' => $userid,
            'userid2' => $userid,
        ]);
        return $contextlist;
    }

    #[\Override]
    public static function get_users_in_context(userlist $userlist) {
        if (!$userlist->get_context() instanceof \context_system) {
            return;
        }
        $userlist->add_from_sql('userid', 'SELECT userid FROM {local_sepe_tutor} WHERE userid IS NOT NULL', []);
        $userlist->add_from_sql('userid', 'SELECT userid FROM {local_sepe_participante} WHERE userid IS NOT NULL', []);
    }

    #[\Override]
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_system) {
                continue;
            }
            $subcontext = [get_string('pluginname', 'local_sepe')];

            $tutores = $DB->get_records('local_sepe_tutor', ['userid' => $userid]);
            if ($tutores) {
                writer::with_context($context)->export_data(
                    array_merge($subcontext, ['tutores']),
                    (object)['records' => array_values(array_map(
                        static fn(\stdClass $t): array => [
                            'tipo_documento' => $t->tipo_documento,
                            'num_documento' => $t->num_documento,
                            'letra_nif' => $t->letra_nif,
                            'acreditacion' => $t->acreditacion,
                        ],
                        $tutores
                    ))]
                );
            }

            $participantes = $DB->get_records('local_sepe_participante', ['userid' => $userid]);
            if ($participantes) {
                writer::with_context($context)->export_data(
                    array_merge($subcontext, ['participantes']),
                    (object)['records' => array_values(array_map(
                        static fn(\stdClass $p): array => [
                            'tipo_documento' => $p->tipo_documento,
                            'num_documento' => $p->num_documento,
                            'letra_nif' => $p->letra_nif,
                        ],
                        $participantes
                    ))]
                );
            }
        }
    }

    #[\Override]
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;
        if (!$context instanceof \context_system) {
            return;
        }
        // Unlink every mapped user; the underlying records are kept under
        // the FPE legal retention obligation.
        $DB->set_field_select('local_sepe_tutor', 'userid', null, 'userid IS NOT NULL');
        $DB->set_field_select('local_sepe_participante', 'userid', null, 'userid IS NOT NULL');
    }

    #[\Override]
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_system) {
                continue;
            }
            $DB->set_field('local_sepe_tutor', 'userid', null, ['userid' => $userid]);
            $DB->set_field('local_sepe_participante', 'userid', null, ['userid' => $userid]);
        }
    }

    #[\Override]
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;
        if (!$userlist->get_context() instanceof \context_system) {
            return;
        }
        $userids = $userlist->get_userids();
        if ($userids === []) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($userids);
        $DB->set_field_select('local_sepe_tutor', 'userid', null, "userid $insql", $params);
        $DB->set_field_select('local_sepe_participante', 'userid', null, "userid $insql", $params);
    }
}
