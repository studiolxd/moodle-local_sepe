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

namespace local_sepe\tracking;

/**
 * Parses Spanish identity documents into the SEPE identifier triplet.
 *
 * The SEPE data model identifies people by TIPO_DOCUMENTO ('D' for DNI,
 * 'E' for NIE), a 10-character NUM_DOCUMENTO and the check letter
 * (LETRA_NIF) separately. Numbers are left-padded with zeros to 10
 * characters, following the convention of SEPE's own test data.
 *
 * @package    local_sepe
 * @copyright  2026 Studio LXD <hello@studiolxd.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class nif {
    /**
     * Parses a raw NIF/NIE string.
     *
     * @param string $raw value of the configured user field, e.g. '12345678Z' or 'X1234567L'
     * @return array{tipo_documento: string, num_documento: string, letra_nif: string}|null
     *     null when the value is not a well-formed DNI or NIE
     */
    public static function parse(string $raw): ?array {
        $value = strtoupper(preg_replace('/[\s\-.]/', '', $raw));

        if (preg_match('/^(\d{1,8})([A-Z])$/', $value, $matches)) {
            return [
                'tipo_documento' => 'D',
                'num_documento' => str_pad($matches[1], 10, '0', STR_PAD_LEFT),
                'letra_nif' => $matches[2],
            ];
        }

        if (preg_match('/^([XYZ])(\d{7})([A-Z])$/', $value, $matches)) {
            return [
                'tipo_documento' => 'E',
                'num_documento' => str_pad($matches[1] . $matches[2], 10, '0', STR_PAD_LEFT),
                'letra_nif' => $matches[3],
            ];
        }

        return null;
    }

    /**
     * Reads the configured NIF field of a set of users in a single query.
     *
     * The plugin setting 'niffield' selects either the standard idnumber
     * field, another column of the user table, or a custom profile field
     * ('profile_<shortname>').
     *
     * @param string $usersql SQL returning an "id" column with the user ids
     * @param array $params named params of $usersql
     * @return array<int, string> raw field value keyed by user id; users
     *     without a value may be missing
     */
    public static function raw_values(string $usersql, array $params): array {
        global $DB;

        $field = trim((string)get_config('local_sepe', 'niffield'));
        if ($field === '') {
            $field = 'idnumber';
        }

        if (str_starts_with($field, 'profile_')) {
            $params['sepeniffield'] = substr($field, strlen('profile_'));
            return $DB->get_records_sql_menu(
                "SELECT d.userid, d.data
                   FROM {user_info_data} d
                   JOIN {user_info_field} f ON f.id = d.fieldid
                  WHERE f.shortname = :sepeniffield AND d.userid IN ($usersql)",
                $params
            );
        }

        // The column name goes into the SQL: only accept real user columns.
        if (!array_key_exists($field, $DB->get_columns('user'))) {
            debugging("local_sepe: the NIF user field '{$field}' does not exist", DEBUG_DEVELOPER);
            return [];
        }
        return $DB->get_records_sql_menu(
            "SELECT u.id, u.{$field}
               FROM {user} u
              WHERE u.id IN ($usersql)",
            $params
        );
    }
}
