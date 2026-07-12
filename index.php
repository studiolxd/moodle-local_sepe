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

/**
 * Admin page: SEPE centre data, received training actions and course mapping.
 *
 * @package    local_sepe
 * @copyright  2026 Studio LXD <hello@studiolxd.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('local_sepe_manage');

$context = context_system::instance();
require_capability('local/sepe:manage', $context);

$acciones = new \local_sepe\local\accion_repository();
$centros = new \local_sepe\local\centro_repository();
$logs = new \local_sepe\local\log_repository();

$action = optional_param('action', '', PARAM_ALPHA);
if ($action !== '' && confirm_sesskey()) {
    if ($action === 'map') {
        $accionid = required_param('accionid', PARAM_INT);
        $courseid = required_param('courseid', PARAM_INT);
        if ($courseid > 0 && !$DB->record_exists('course', ['id' => $courseid])) {
            throw new moodle_exception('errornocourse', 'local_sepe');
        }
        $acciones->set_course($accionid, $courseid > 0 ? $courseid : null);
        redirect(
            new moodle_url('/local/sepe/index.php'),
            get_string('mapsaved', 'local_sepe'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
    if ($action === 'reset') {
        $acciones->delete_all();
        $centros->reset();
        redirect(
            new moodle_url('/local/sepe/index.php'),
            get_string('resetdone', 'local_sepe'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
}

$canviewlogs = has_capability('local/sepe:viewlogs', $context);

$courseoptions = [];
foreach (get_courses('all', 'c.sortorder ASC', 'c.id, c.fullname') as $course) {
    if ((int)$course->id === SITEID) {
        continue;
    }
    $courseoptions[(int)$course->id] = format_string($course->fullname);
}

$page = new \local_sepe\output\acciones_page(
    $centros->get(),
    $acciones->list_all(),
    $canviewlogs ? $logs->latest(20) : [],
    $courseoptions,
    $canviewlogs
);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('managepage', 'local_sepe'));
echo $OUTPUT->render_from_template('local_sepe/acciones_page', $page->export_for_template($OUTPUT));
echo $OUTPUT->footer();
