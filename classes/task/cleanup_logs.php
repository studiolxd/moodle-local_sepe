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
 * Scheduled task that prunes old web service log entries.
 *
 * @package    local_sepe
 * @copyright  2026 Studio LXD <hello@studiolxd.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cleanup_logs extends \core\task\scheduled_task {

    #[\Override]
    public function get_name() {
        return get_string('taskcleanuplogs', 'local_sepe');
    }

    #[\Override]
    public function execute() {
        $days = (int)get_config('local_sepe', 'logretentiondays');
        $logs = new \local_sepe\local\log_repository();
        $deleted = $logs->cleanup($days);
        mtrace("local_sepe: deleted {$deleted} log entries older than {$days} days.");
    }
}
