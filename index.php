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
$pageurl = new moodle_url('/local/sepe/index.php');

$acciones = new \local_sepe\local\accion_repository();
$centros = new \local_sepe\local\centro_repository();
$logs = new \local_sepe\local\log_repository();

$action = optional_param('action', '', PARAM_ALPHA);
if ($action !== '') {
    require_sesskey();

    if ($action === 'map') {
        $accion = $acciones->get_by_id(required_param('accionid', PARAM_INT));
        // An empty autocomplete selection submits no value: unmap.
        $courseid = optional_param('courseid', 0, PARAM_INT);
        if ($courseid > 0 && ($courseid === (int)SITEID || !$DB->record_exists('course', ['id' => $courseid]))) {
            throw new moodle_exception('errornocourse', 'local_sepe');
        }
        $acciones->set_course($accion->id, $courseid > 0 ? $courseid : null);
        \local_sepe\event\accion_mapped::create([
            'context' => $context,
            'objectid' => $accion->id,
            'other' => [
                'origen_accion' => $accion->origen_accion,
                'codigo_accion' => $accion->codigo_accion,
                'courseid' => $courseid > 0 ? $courseid : null,
            ],
        ])->trigger();
        redirect($pageurl, get_string('mapsaved', 'local_sepe'), null, \core\output\notification::NOTIFY_SUCCESS);
    }

    if ($action === 'reset') {
        (new \local_sepe\local\reset_service())->reset();
        redirect($pageurl, get_string('resetdone', 'local_sepe'), null, \core\output\notification::NOTIFY_SUCCESS);
    }
}

$canviewlogs = has_capability('local/sepe:viewlogs', $context);

$list = $acciones->list_all();

// Only the mapped courses are loaded; the selector searches the rest on demand.
$mappedcourses = [];
$mappedids = array_unique(array_filter(array_map(static fn($a) => (int)$a->courseid, $list)));
if ($mappedids) {
    $mappedcourses = $DB->get_records_list('course', 'id', $mappedids, '', 'id, fullname');
}

$page = new \local_sepe\output\acciones_page(
    $centros->get(),
    $list,
    $canviewlogs ? $logs->latest(20) : [],
    array_map(static fn($c) => format_string($c->fullname, true, ['context' => $context]), $mappedcourses),
    $canviewlogs
);

foreach ($list as $accion) {
    $PAGE->requires->js_call_amd('core/form-autocomplete', 'enhance', [
        '#local-sepe-course-' . $accion->id,
        false,
        'core/form-course-selector',
        get_string('searchcourses'),
        false,
        true,
        get_string('notmapped', 'local_sepe'),
    ]);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('managepage', 'local_sepe'));
echo $OUTPUT->render_from_template('local_sepe/acciones_page', $page->export_for_template($OUTPUT));
echo $OUTPUT->footer();
