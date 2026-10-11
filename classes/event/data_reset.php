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
 * Event fired when the centre and every training action are deleted.
 *
 * Triggered by the reset button of the admin page and by the
 * reset_validation_data CLI script.
 *
 * @package    local_sepe
 * @copyright  2026 Studio LXD <hello@studiolxd.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class data_reset extends \core\event\base {
    #[\Override]
    protected function init() {
        $this->data['crud'] = 'd';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    #[\Override]
    public static function get_name() {
        return get_string('eventdatareset', 'local_sepe');
    }

    #[\Override]
    public function get_description() {
        return "The user with id '{$this->userid}' deleted the SEPE centre registration "
            . "and {$this->other['acciones']} training action(s).";
    }

    #[\Override]
    protected function validate_data() {
        parent::validate_data();
        if (!isset($this->other['acciones'])) {
            throw new \coding_exception("The 'acciones' value must be set in other.");
        }
    }

    #[\Override]
    public static function get_other_mapping() {
        return false;
    }
}
