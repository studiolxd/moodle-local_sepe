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
 * Persistence of training actions and their nested entities.
 *
 * @package    local_sepe
 * @copyright  2026 Studio LXD <hello@studiolxd.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class accion_repository {

    /**
     * Whether an action with this identifier exists.
     *
     * @param string $origen ORIGEN_ACCION
     * @param string $codigo CODIGO_ACCION
     * @return bool
     */
    public function exists(string $origen, string $codigo): bool {
        global $DB;
        return $DB->record_exists('local_sepe_accion', [
            'origen_accion' => $origen,
            'codigo_accion' => $codigo,
        ]);
    }

    /**
     * Fetches an action by its protocol identifier.
     *
     * @param string $origen ORIGEN_ACCION
     * @param string $codigo CODIGO_ACCION
     * @return \stdClass|null the record including rawxml, or null
     */
    public function get(string $origen, string $codigo): ?\stdClass {
        global $DB;
        $record = $DB->get_record('local_sepe_accion', [
            'origen_accion' => $origen,
            'codigo_accion' => $codigo,
        ]);
        return $record ?: null;
    }

    /**
     * Identifiers of every stored action, in insertion order.
     *
     * @return \stdClass[] objects with origen_accion and codigo_accion
     */
    public function list_ids(): array {
        global $DB;
        return array_values($DB->get_records('local_sepe_accion', [], 'id ASC', 'id, origen_accion, codigo_accion'));
    }

    /**
     * Every stored action with its mapped course, for the admin page.
     *
     * @return \stdClass[]
     */
    public function list_all(): array {
        global $DB;
        return array_values($DB->get_records('local_sepe_accion', [], 'id ASC'));
    }

    /**
     * Persists a new action with its specialities, tutors and participants.
     *
     * Callers must ensure the identifier is free (see exists()) and are
     * expected to wrap the call in a delegated transaction.
     *
     * @param array $parsed normalized action data from the request parser
     * @return \stdClass the stored action record
     */
    public function create(array $parsed): \stdClass {
        global $DB;

        $now = time();
        $record = (object)[
            'origen_accion' => $parsed['origen_accion'],
            'codigo_accion' => $parsed['codigo_accion'],
            'situacion' => $parsed['situacion'],
            'origen_especialidad' => $parsed['origen_especialidad'],
            'area_profesional' => $parsed['area_profesional'],
            'codigo_especialidad' => $parsed['codigo_especialidad'],
            'duracion' => $parsed['duracion'],
            'fecha_inicio' => $parsed['fecha_inicio'],
            'fecha_fin' => $parsed['fecha_fin'],
            'itinerario_completo' => $parsed['itinerario_completo'],
            'tipo_financiacion' => $parsed['tipo_financiacion'],
            'numero_asistentes' => $parsed['numero_asistentes'],
            'denominacion' => $parsed['denominacion'],
            'informacion_general' => $parsed['informacion_general'],
            'horarios' => $parsed['horarios'],
            'requisitos' => $parsed['requisitos'],
            'contacto' => $parsed['contacto'],
            'rawxml' => $parsed['rawxml'],
            'courseid' => null,
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        $record->id = $DB->insert_record('local_sepe_accion', $record);

        foreach ($parsed['especialidades'] as $especialidad) {
            $tutores = $especialidad['tutores'];
            unset($especialidad['tutores']);
            $especialidad['accionid'] = $record->id;
            $especialidadid = $DB->insert_record('local_sepe_especialidad', (object)$especialidad);

            foreach ($tutores as $tutor) {
                $tutor['accionid'] = $record->id;
                $tutor['especialidadid'] = $especialidadid;
                $DB->insert_record('local_sepe_tutor', (object)$tutor);
            }
        }

        foreach ($parsed['participantes'] as $participante) {
            $participante['accionid'] = $record->id;
            $DB->insert_record('local_sepe_participante', (object)$participante);
        }

        return $record;
    }

    /**
     * Deletes an action and its nested entities.
     *
     * @param string $origen ORIGEN_ACCION
     * @param string $codigo CODIGO_ACCION
     * @return \stdClass|null the deleted record, or null if it did not exist
     */
    public function delete(string $origen, string $codigo): ?\stdClass {
        global $DB;

        $record = $this->get($origen, $codigo);
        if ($record === null) {
            return null;
        }
        $this->delete_children($record->id);
        $DB->delete_records('local_sepe_accion', ['id' => $record->id]);
        return $record;
    }

    /**
     * Deletes every action and nested entity.
     */
    public function delete_all(): void {
        global $DB;
        $DB->delete_records('local_sepe_tutor');
        $DB->delete_records('local_sepe_participante');
        $DB->delete_records('local_sepe_especialidad');
        $DB->delete_records('local_sepe_accion');
    }

    /**
     * Maps an action to a Moodle course (or unmaps it with null).
     *
     * @param int $accionid action record id
     * @param int|null $courseid course id, or null to unmap
     */
    public function set_course(int $accionid, ?int $courseid): void {
        global $DB;
        $DB->update_record('local_sepe_accion', (object)[
            'id' => $accionid,
            'courseid' => $courseid,
            'timemodified' => time(),
        ]);
    }

    /**
     * Deletes the nested entities of an action.
     *
     * @param int $accionid action record id
     */
    private function delete_children(int $accionid): void {
        global $DB;
        $DB->delete_records('local_sepe_tutor', ['accionid' => $accionid]);
        $DB->delete_records('local_sepe_participante', ['accionid' => $accionid]);
        $DB->delete_records('local_sepe_especialidad', ['accionid' => $accionid]);
    }
}
