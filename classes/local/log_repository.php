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

namespace local_sepe\local;

/**
 * Persistence of the web service request log.
 *
 * @package    local_sepe
 * @copyright  2026 Studio LXD <hello@studiolxd.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class log_repository {

    /**
     * Logs a request. Best-effort: a logging failure never breaks the response.
     *
     * @param \stdClass $log record with operation, ip, returncode, requestxml, responsexml, errormsg
     */
    public function insert(\stdClass $log): void {
        global $DB;
        try {
            $log->timecreated = time();
            $DB->insert_record('local_sepe_wslog', $log);
        } catch (\Throwable $e) {
            debugging('local_sepe: could not log web service request: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Latest log entries, newest first.
     *
     * @param int $limit maximum number of entries
     * @return \stdClass[]
     */
    public function latest(int $limit = 20): array {
        global $DB;
        return array_values($DB->get_records('local_sepe_wslog', [], 'timecreated DESC, id DESC', '*', 0, $limit));
    }

    /**
     * Deletes entries older than the given number of days.
     *
     * @param int $days retention in days; 0 keeps everything
     * @return int number of deleted entries
     */
    public function cleanup(int $days): int {
        global $DB;
        if ($days <= 0) {
            return 0;
        }
        $cutoff = time() - ($days * DAYSECS);
        $count = $DB->count_records_select('local_sepe_wslog', 'timecreated < ?', [$cutoff]);
        if ($count > 0) {
            $DB->delete_records_select('local_sepe_wslog', 'timecreated < ?', [$cutoff]);
        }
        return $count;
    }
}
