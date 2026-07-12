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
 * Deletes the centre and every training action received from SEPE.
 *
 * The SEPE self-evaluation kit creates a test centre and test actions;
 * a second run against a service still holding them fails with duplicate
 * errors, so run this script between validator runs.
 *
 * Usage: php local/sepe/cli/reset_validation_data.php [--run]
 *
 * @package    local_sepe
 * @copyright  2026 Studio LXD <hello@studiolxd.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options, $unrecognized] = cli_get_params(
    ['run' => false, 'help' => false],
    ['h' => 'help']
);

if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognized));
}

if ($options['help'] || !$options['run']) {
    cli_writeln('Deletes the SEPE centre registration and every training action');
    cli_writeln('received through the tracking web service (tutors, participants');
    cli_writeln('and specialities included). Meant to be used between runs of the');
    cli_writeln('SEPE self-evaluation kit.');
    cli_writeln('');
    cli_writeln('Options:');
    cli_writeln('  --run   Actually delete the data.');
    cli_writeln('  -h, --help  Print this help.');
    exit(0);
}

$acciones = new \local_sepe\local\accion_repository();
$centros = new \local_sepe\local\centro_repository();

$count = count($acciones->list_ids());
$acciones->delete_all();
$centros->reset();

cli_writeln("Deleted centre registration and {$count} training action(s).");
exit(0);
