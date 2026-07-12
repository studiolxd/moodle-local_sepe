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

namespace local_sepe\task;

/**
 * Scheduled task that regenerates the tracking XML of mapped actions.
 *
 * SEPE requires tracking data to be at most 7 days old; running daily
 * keeps the generated XML warm and surfaces generation errors early
 * instead of at request time.
 *
 * @package    local_sepe
 * @copyright  2026 Studio LXD <hello@studiolxd.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class refresh_tracking_data extends \core\task\scheduled_task {
    #[\Override]
    public function get_name() {
        return get_string('taskrefreshtracking', 'local_sepe');
    }

    #[\Override]
    public function execute() {
        global $DB;

        $mapped = $DB->get_records_select('local_sepe_accion', 'courseid IS NOT NULL');
        foreach ($mapped as $accion) {
            $xml = \local_sepe\tracking\generator::for_accion($accion);
            if ($xml === null) {
                mtrace("local_sepe: could not generate tracking data for action {$accion->codigo_accion}.");
                continue;
            }
            mtrace("local_sepe: refreshed tracking data for action {$accion->codigo_accion}.");
        }
        if (!$mapped) {
            mtrace('local_sepe: no actions mapped to courses, nothing to refresh.');
        }
    }
}
