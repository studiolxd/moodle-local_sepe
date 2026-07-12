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

namespace local_sepe\output;

/**
 * Admin page: centre data, received actions and web service log.
 *
 * @package    local_sepe
 * @copyright  2026 Studio LXD <hello@studiolxd.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class acciones_page implements \renderable, \templatable {
    /**
     * Constructor.
     *
     * @param \stdClass|null $centro centre registered by SEPE, null when not yet registered
     * @param \stdClass[] $acciones stored actions
     * @param \stdClass[] $logs latest log entries
     * @param array<int, string> $courses course id => fullname options for mapping
     * @param bool $canviewlogs whether the viewer may see the log section
     */
    public function __construct(
        /** @var \stdClass|null Centre row. */
        private readonly ?\stdClass $centro,
        /** @var \stdClass[] Stored actions. */
        private readonly array $acciones,
        /** @var \stdClass[] Latest log entries. */
        private readonly array $logs,
        /** @var array<int, string> Mappable courses. */
        private readonly array $courses,
        /** @var bool Whether logs are visible. */
        private readonly bool $canviewlogs,
    ) {
    }

    #[\Override]
    public function export_for_template(\renderer_base $output) {
        $pageurl = new \moodle_url('/local/sepe/index.php');

        $acciones = [];
        foreach ($this->acciones as $accion) {
            $options = [];
            foreach ($this->courses as $courseid => $fullname) {
                $options[] = [
                    'id' => $courseid,
                    'name' => $fullname,
                    'selected' => (int)$accion->courseid === $courseid,
                ];
            }
            $acciones[] = [
                'id' => $accion->id,
                'codigo' => $accion->origen_accion . '/' . $accion->codigo_accion,
                'denominacion' => $accion->denominacion,
                'situacion' => $accion->situacion,
                'especialidad' => $accion->codigo_especialidad,
                'fechas' => $accion->fecha_inicio . ' — ' . $accion->fecha_fin,
                'mapped' => !empty($accion->courseid),
                'options' => $options,
            ];
        }

        $logs = [];
        foreach ($this->logs as $log) {
            $logs[] = [
                'time' => userdate($log->timecreated, get_string('strftimedatetimeshort', 'langconfig')),
                'operation' => $log->operation,
                'ip' => $log->ip,
                'returncode' => $log->returncode ?? '—',
                'errormsg' => $log->errormsg,
            ];
        }

        return (object)[
            'centro' => $this->centro ? [
                'codigo' => $this->centro->origen_centro . '/' . $this->centro->codigo_centro,
                'nombre' => $this->centro->nombre_centro,
                'urlseguimiento' => $this->centro->url_seguimiento,
            ] : null,
            'hasacciones' => $acciones !== [],
            'acciones' => $acciones,
            'canviewlogs' => $this->canviewlogs,
            'haslogs' => $logs !== [],
            'logs' => $logs,
            'actionurl' => $pageurl->out(false),
            'sesskey' => sesskey(),
        ];
    }
}
