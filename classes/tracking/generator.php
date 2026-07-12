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
 * XML received from SEPE is kept and the tracking sections (participants,
 * usage times, evaluation results) are regenerated from the course:
 * enrolments, the standard log store and the gradebook.
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
        // Implemented together with the course mapping UI; until an action
        // is mapped this is never reached.
        return null;
    }
}
