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

namespace local_sepe\local;

/**
 * Deletes the centre registration and every training action.
 *
 * Shared by the admin page and the reset_validation_data CLI script, so
 * both run the deletion atomically and leave the same audit trail.
 *
 * @package    local_sepe
 * @copyright  2026 Studio LXD <hello@studiolxd.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class reset_service {
    /**
     * Deletes all centre and action data in a single transaction.
     *
     * @return int number of training actions deleted
     */
    public function reset(): int {
        global $DB;

        $acciones = new accion_repository();
        $transaction = $DB->start_delegated_transaction();
        $count = $acciones->count();
        $acciones->delete_all();
        (new centro_repository())->reset();
        \local_sepe\event\data_reset::create([
            'context' => \context_system::instance(),
            'other' => ['acciones' => $count],
        ])->trigger();
        $transaction->allow_commit();

        return $count;
    }
}
