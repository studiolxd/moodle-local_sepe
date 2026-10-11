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
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use local_sepe\local\accion_repository;

/**
 * Privacy provider for local_sepe.
 *
 * The plugin stores personal data of three kinds:
 *
 * - identity documents of tutors and participants received from SEPE
 *   (third parties that may or may not exist as Moodle users, never linked
 *   to a Moodle account), retained under the legal obligations of the FPE
 *   regulations (Orden TMS/369/2019), as documented in the plugin README;
 * - raw SOAP payloads in the request log, with configurable retention;
 * - the cached tracking XML of actions mapped to a course, which embeds the
 *   identity document, enrolment dates, results and usage of the course
 *   students, and is sent to SEPE.
 *
 * Only the cached tracking XML relates to Moodle users. Their requests are
 * served by discarding the cache of the affected actions; it is generated
 * again from the course (as the FPE regulations require) on the next
 * request or task run.
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
        ], 'privacy:metadata:local_sepe_tutor');

        $collection->add_database_table('local_sepe_participante', [
            'tipo_documento' => 'privacy:metadata:local_sepe_participante:tipo_documento',
            'num_documento' => 'privacy:metadata:local_sepe_participante:num_documento',
            'letra_nif' => 'privacy:metadata:local_sepe_participante:letra_nif',
        ], 'privacy:metadata:local_sepe_participante');

        $collection->add_database_table('local_sepe_accion', [
            'rawxml' => 'privacy:metadata:local_sepe_accion:rawxml',
            'trackingxml' => 'privacy:metadata:local_sepe_accion:trackingxml',
            'trackinggenerated' => 'privacy:metadata:local_sepe_accion:trackinggenerated',
        ], 'privacy:metadata:local_sepe_accion');

        $collection->add_database_table('local_sepe_wslog', [
            'ip' => 'privacy:metadata:local_sepe_wslog:ip',
            'requestxml' => 'privacy:metadata:local_sepe_wslog:requestxml',
            'responsexml' => 'privacy:metadata:local_sepe_wslog:responsexml',
        ], 'privacy:metadata:local_sepe_wslog');

        $collection->add_external_location_link('sepe', [
            'nif' => 'privacy:metadata:sepe:nif',
            'enrolment' => 'privacy:metadata:sepe:enrolment',
            'results' => 'privacy:metadata:sepe:results',
            'usage' => 'privacy:metadata:sepe:usage',
        ], 'privacy:metadata:sepe');

        return $collection;
    }

    /**
     * SQL fragment joining cached actions with the enrolments of their course.
     *
     * @return string FROM clause with aliases a (action), e (enrol) and ue (user_enrolments)
     */
    private static function cached_enrolments_sql(): string {
        return "{local_sepe_accion} a
                JOIN {enrol} e ON e.courseid = a.courseid
                JOIN {user_enrolments} ue ON ue.enrolid = e.id";
    }

    /**
     * Ids of the actions whose cached tracking XML may include some users.
     *
     * @param int[] $userids user ids
     * @return int[] action ids
     */
    private static function cached_actions_for_users(array $userids): array {
        global $DB;
        if ($userids === []) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        return array_map('intval', $DB->get_fieldset_sql(
            "SELECT DISTINCT a.id
               FROM " . self::cached_enrolments_sql() . "
              WHERE a.trackinggenerated IS NOT NULL AND ue.userid $insql",
            $params
        ));
    }

    #[\Override]
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $sql = "SELECT c.id
                  FROM {context} c
                 WHERE c.contextlevel = :contextlevel
                       AND EXISTS (
                           SELECT 1
                             FROM " . self::cached_enrolments_sql() . "
                            WHERE a.trackinggenerated IS NOT NULL AND ue.userid = :userid
                       )";
        $contextlist->add_from_sql($sql, ['contextlevel' => CONTEXT_SYSTEM, 'userid' => $userid]);
        return $contextlist;
    }

    #[\Override]
    public static function get_users_in_context(userlist $userlist) {
        if (!$userlist->get_context() instanceof \context_system) {
            return;
        }
        $userlist->add_from_sql(
            'userid',
            "SELECT ue.userid
               FROM " . self::cached_enrolments_sql() . "
              WHERE a.trackinggenerated IS NOT NULL",
            []
        );
    }

    #[\Override]
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_system) {
                continue;
            }
            $ids = self::cached_actions_for_users([$userid]);
            if ($ids === []) {
                continue;
            }
            $acciones = $DB->get_records_list(
                'local_sepe_accion',
                'id',
                $ids,
                'id ASC',
                'id, origen_accion, codigo_accion, denominacion, courseid, trackinggenerated'
            );
            foreach ($acciones as $accion) {
                $codigo = $accion->origen_accion . '/' . $accion->codigo_accion;
                writer::with_context($context)->export_data(
                    [get_string('pluginname', 'local_sepe'), $codigo],
                    (object)[
                        'codigo_accion' => $codigo,
                        'denominacion' => $accion->denominacion,
                        'courseid' => $accion->courseid,
                        'trackinggenerated' => transform::datetime($accion->trackinggenerated),
                        'description' => get_string('privacy:export:trackingxml', 'local_sepe'),
                    ]
                );
            }
        }
    }

    #[\Override]
    public static function delete_data_for_all_users_in_context(\context $context) {
        if (!$context instanceof \context_system) {
            return;
        }
        (new accion_repository())->clear_tracking();
    }

    #[\Override]
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_system) {
                continue;
            }
            (new accion_repository())->clear_tracking(self::cached_actions_for_users([$userid]));
        }
    }

    #[\Override]
    public static function delete_data_for_users(approved_userlist $userlist) {
        if (!$userlist->get_context() instanceof \context_system) {
            return;
        }
        (new accion_repository())->clear_tracking(self::cached_actions_for_users($userlist->get_userids()));
    }
}
