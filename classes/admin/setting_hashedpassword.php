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

namespace local_sepe\admin;

/**
 * Password setting stored as a one-way hash.
 *
 * The SEPE web service password only ever needs to be verified against
 * incoming WS-Security tokens, never sent anywhere, so only a
 * password_hash() digest is persisted. The stored hash is never shown
 * back in the form; submitting an empty value keeps the current one.
 *
 * @package    local_sepe
 * @copyright  2026 Studio LXD <hello@studiolxd.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class setting_hashedpassword extends \admin_setting_configpasswordunmask {
    #[\Override]
    public function get_setting() {
        $current = $this->config_read($this->name);
        if ($current === null) {
            return null;
        }
        // Never expose the stored hash to the form.
        return '';
    }

    #[\Override]
    public function write_setting($data) {
        $data = (string)$data;
        if ($data === '') {
            // Keep the existing hash. Persist an empty marker on first save
            // so the admin tree stops flagging the setting as new.
            if ($this->config_read($this->name) === null) {
                return $this->config_write($this->name, '') ? '' : get_string('errorsetting', 'admin');
            }
            return '';
        }
        $hash = password_hash($data, PASSWORD_DEFAULT);
        return $this->config_write($this->name, $hash) ? '' : get_string('errorsetting', 'admin');
    }
}
