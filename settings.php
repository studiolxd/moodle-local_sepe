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
 * Admin settings for local_sepe.
 *
 * @package    local_sepe
 * @copyright  2026 Studio LXD <hello@studiolxd.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $ADMIN->add('localplugins', new admin_category('local_sepe_category', get_string('pluginname', 'local_sepe')));

    $settings = new admin_settingpage('local_sepe_settings', get_string('settingspage', 'local_sepe'));

    $endpointurl = new moodle_url('/local/sepe/endpoint.php');
    $settings->add(new admin_setting_heading(
        'local_sepe/serviceheading',
        get_string('serviceheading', 'local_sepe'),
        get_string('serviceheading_desc', 'local_sepe', $endpointurl->out(false))
    ));

    $settings->add(new \local_sepe\admin\setting_hashedpassword(
        'local_sepe/wspasswordhash',
        get_string('wspassword', 'local_sepe'),
        get_string('wspassword_desc', 'local_sepe'),
        ''
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_sepe/servicedisabled',
        get_string('servicedisabled', 'local_sepe'),
        get_string('servicedisabled_desc', 'local_sepe'),
        0
    ));

    $settings->add(new admin_setting_configtext(
        'local_sepe/logretentiondays',
        get_string('logretentiondays', 'local_sepe'),
        get_string('logretentiondays_desc', 'local_sepe'),
        90,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'local_sepe/niffield',
        get_string('niffield', 'local_sepe'),
        get_string('niffield_desc', 'local_sepe'),
        'idnumber'
    ));

    $settings->add(new admin_setting_heading(
        'local_sepe/centreheading',
        get_string('centreheading', 'local_sepe'),
        get_string('centreheading_desc', 'local_sepe')
    ));

    $settings->add(new admin_setting_configtext(
        'local_sepe/centroorigen',
        get_string('centroorigen', 'local_sepe'),
        get_string('centroorigen_desc', 'local_sepe'),
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'local_sepe/centrocodigo',
        get_string('centrocodigo', 'local_sepe'),
        get_string('centrocodigo_desc', 'local_sepe'),
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'local_sepe/centronombre',
        get_string('centronombre', 'local_sepe'),
        get_string('centronombre_desc', 'local_sepe'),
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'local_sepe/centrourlplataforma',
        get_string('centrourlplataforma', 'local_sepe'),
        get_string('centrourlplataforma_desc', 'local_sepe'),
        $CFG->wwwroot
    ));

    $settings->add(new admin_setting_configtext(
        'local_sepe/centrourlseguimiento',
        get_string('centrourlseguimiento', 'local_sepe'),
        get_string('centrourlseguimiento_desc', 'local_sepe'),
        $endpointurl->out(false)
    ));

    $settings->add(new admin_setting_configtext(
        'local_sepe/centrotelefono',
        get_string('centrotelefono', 'local_sepe'),
        get_string('centrotelefono_desc', 'local_sepe'),
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'local_sepe/centroemail',
        get_string('centroemail', 'local_sepe'),
        get_string('centroemail_desc', 'local_sepe'),
        ''
    ));

    $ADMIN->add('local_sepe_category', $settings);

    $ADMIN->add('local_sepe_category', new admin_externalpage(
        'local_sepe_manage',
        get_string('managepage', 'local_sepe'),
        new moodle_url('/local/sepe/index.php'),
        'local/sepe:manage'
    ));
}
