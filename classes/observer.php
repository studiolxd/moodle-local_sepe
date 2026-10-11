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

namespace local_sepe;

/**
 * Event observers of local_sepe.
 *
 * @package    local_sepe
 * @copyright  2026 Studio LXD <hello@studiolxd.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class observer {
    /**
     * Unmaps the training actions of a deleted course.
     *
     * Without this, the actions would keep pointing at a missing course
     * and silently answer obtenerAccion with the XML received on creation.
     *
     * @param \core\event\course_deleted $event the event
     */
    public static function course_deleted(\core\event\course_deleted $event): void {
        (new local\accion_repository())->unmap_course((int)$event->objectid);
    }
}
