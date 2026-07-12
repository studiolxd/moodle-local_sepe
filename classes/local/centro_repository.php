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
 * Persistence of the centre identification data.
 *
 * The protocol works with a single centre: the one row in
 * local_sepe_centro, written by SEPE through crearCentro. Until that
 * happens, obtenerDatosCentro falls back to the plugin settings.
 *
 * @package    local_sepe
 * @copyright  2026 Studio LXD <hello@studiolxd.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class centro_repository {
    /**
     * The centre row registered by SEPE, if any.
     *
     * @return \stdClass|null
     */
    public function get(): ?\stdClass {
        global $DB;
        $records = $DB->get_records('local_sepe_centro', [], 'id ASC', '*', 0, 1);
        return $records ? reset($records) : null;
    }

    /**
     * The centre row, or a record built from the plugin settings.
     *
     * @return \stdClass|null null when neither exists (service must answer -2)
     */
    public function get_or_defaults(): ?\stdClass {
        $centro = $this->get();
        if ($centro !== null) {
            return $centro;
        }

        $config = get_config('local_sepe');
        $origen = trim((string)($config->centroorigen ?? ''));
        $codigo = trim((string)($config->centrocodigo ?? ''));
        $nombre = trim((string)($config->centronombre ?? ''));
        if ($origen === '' || $codigo === '' || $nombre === '') {
            return null;
        }

        return (object)[
            'origen_centro' => $origen,
            'codigo_centro' => $codigo,
            'nombre_centro' => $nombre,
            'url_plataforma' => trim((string)($config->centrourlplataforma ?? '')),
            'url_seguimiento' => trim((string)($config->centrourlseguimiento ?? '')),
            'telefono' => trim((string)($config->centrotelefono ?? '')),
            'email' => trim((string)($config->centroemail ?? '')),
        ];
    }

    /**
     * Creates or replaces the centre row with the data received from SEPE.
     *
     * @param array $datos normalized centre data from the request parser
     * @return \stdClass the stored record
     */
    public function upsert(array $datos): \stdClass {
        global $DB;

        $now = time();
        $existing = $this->get();
        if ($existing !== null) {
            $record = (object)array_merge((array)$existing, $datos);
            $record->timemodified = $now;
            $DB->update_record('local_sepe_centro', $record);
            return $record;
        }

        $record = (object)$datos;
        $record->timecreated = $now;
        $record->timemodified = $now;
        $record->id = $DB->insert_record('local_sepe_centro', $record);
        return $record;
    }

    /**
     * Whether any training action is stored.
     *
     * Used by crearCentro: SEPE specifies return code 1 when the centre
     * already has actions.
     *
     * @return bool
     */
    public function has_acciones(): bool {
        global $DB;
        return $DB->count_records('local_sepe_accion') > 0;
    }

    /**
     * Deletes the centre registration.
     */
    public function reset(): void {
        global $DB;
        $DB->delete_records('local_sepe_centro');
    }
}
