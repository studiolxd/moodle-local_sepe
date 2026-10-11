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

namespace local_sepe\event;

/**
 * Event fired when SEPE creates a training action through the web service.
 *
 * @package    local_sepe
 * @copyright  2026 Studio LXD <hello@studiolxd.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class accion_created extends \core\event\base {
    #[\Override]
    protected function init() {
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'local_sepe_accion';
    }

    #[\Override]
    public static function get_name() {
        return get_string('eventaccioncreated', 'local_sepe');
    }

    #[\Override]
    public function get_description() {
        return "SEPE created the training action '{$this->other['codigo_accion']}' "
            . "(origin '{$this->other['origen_accion']}') through the tracking web service.";
    }

    #[\Override]
    protected function validate_data() {
        parent::validate_data();
        if (!isset($this->other['origen_accion'])) {
            throw new \coding_exception("The 'origen_accion' value must be set in other.");
        }
        if (!isset($this->other['codigo_accion'])) {
            throw new \coding_exception("The 'codigo_accion' value must be set in other.");
        }
    }

    #[\Override]
    public static function get_objectid_mapping() {
        return ['db' => 'local_sepe_accion', 'restore' => \core\event\base::NOT_MAPPED];
    }

    #[\Override]
    public static function get_other_mapping() {
        return false;
    }
}
