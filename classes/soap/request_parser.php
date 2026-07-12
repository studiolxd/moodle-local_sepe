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

namespace local_sepe\soap;

/**
 * Parses SEPE tracking service request payloads.
 *
 * Elements are matched by local name only: the payload schema is
 * elementFormDefault="unqualified" but real clients differ in how they
 * qualify the payload root, so prefixes are ignored throughout. Values
 * are stored verbatim — SEPE's own test data violates the XSD length
 * facets, so no length validation is applied, only presence and basic
 * numeric checks that map to protocol return code 2.
 *
 * @package    local_sepe
 * @copyright  2026 Studio LXD <hello@studiolxd.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class request_parser {

    /**
     * Returns the operation element, the first element child of the body.
     *
     * @param \DOMDocument $doc parsed request envelope
     * @return \DOMElement operation element
     * @throws client_fault_exception when the body is missing or the operation is unknown
     */
    public function operation(\DOMDocument $doc): \DOMElement {
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('soapenv', protocol::NS_SOAPENV);
        $bodies = $xpath->query('/soapenv:Envelope/soapenv:Body');
        if ($bodies === false || $bodies->length === 0) {
            throw new client_fault_exception('Missing SOAP body');
        }
        foreach ($bodies->item(0)->childNodes as $child) {
            if (!($child instanceof \DOMElement)) {
                continue;
            }
            if ($child->namespaceURI !== protocol::NS_IMPL
                    || !in_array($child->localName, protocol::OPERATIONS, true)) {
                throw new client_fault_exception('Unknown operation: ' . $child->localName);
            }
            return $child;
        }
        throw new client_fault_exception('Empty SOAP body');
    }

    /**
     * Parses the DATOS_IDENTIFICATIVOS payload of crearCentro.
     *
     * @param \DOMElement $operation operation element
     * @return array normalized centre data keyed by local_sepe_centro column
     * @throws parameter_exception when a required tag is missing
     */
    public function parse_datos_identificativos(\DOMElement $operation): array {
        $datos = $this->require_child($operation, 'DATOS_IDENTIFICATIVOS');
        $idcentro = $this->require_child($datos, 'ID_CENTRO');

        return [
            'origen_centro' => $this->require_text($idcentro, 'ORIGEN_CENTRO'),
            'codigo_centro' => $this->require_text($idcentro, 'CODIGO_CENTRO'),
            'nombre_centro' => $this->require_text($datos, 'NOMBRE_CENTRO'),
            'url_plataforma' => $this->require_text($datos, 'URL_PLATAFORMA'),
            'url_seguimiento' => $this->require_text($datos, 'URL_SEGUIMIENTO'),
            'telefono' => $this->require_text($datos, 'TELEFONO'),
            'email' => $this->require_text($datos, 'EMAIL'),
        ];
    }

    /**
     * Parses the ID_ACCION payload of obtenerAccion / eliminarAccion.
     *
     * @param \DOMElement $operation operation element
     * @return array{origen_accion: string, codigo_accion: string}
     * @throws parameter_exception when a required tag is missing
     */
    public function parse_id_accion(\DOMElement $operation): array {
        $id = $this->require_child($operation, 'ID_ACCION');
        return [
            'origen_accion' => $this->require_text($id, 'ORIGEN_ACCION'),
            'codigo_accion' => $this->require_text($id, 'CODIGO_ACCION'),
        ];
    }

    /**
     * Parses the ACCION_FORMATIVA payload of crearAccion.
     *
     * Returns the normalized header fields plus nested especialidades
     * (with their tutors) and participantes, and the verbatim XML of the
     * whole ACCION_FORMATIVA element under 'rawxml', which is what gets
     * echoed back on obtenerAccion.
     *
     * @param \DOMElement $operation operation element
     * @return array normalized action data
     * @throws parameter_exception when a required tag is missing or invalid
     */
    public function parse_accion_formativa(\DOMElement $operation): array {
        $accion = $this->require_child($operation, 'ACCION_FORMATIVA');
        $id = $this->require_child($accion, 'ID_ACCION');
        $principal = $this->require_child($accion, 'ID_ESPECIALIDAD_PRINCIPAL');
        $descripcion = $this->require_child($accion, 'DESCRIPCION_ACCION');

        $parsed = [
            'origen_accion' => $this->require_text($id, 'ORIGEN_ACCION'),
            'codigo_accion' => $this->require_text($id, 'CODIGO_ACCION'),
            'situacion' => $this->require_text($accion, 'SITUACION'),
            'origen_especialidad' => $this->require_text($principal, 'ORIGEN_ESPECIALIDAD'),
            'area_profesional' => $this->require_text($principal, 'AREA_PROFESIONAL'),
            'codigo_especialidad' => $this->require_text($principal, 'CODIGO_ESPECIALIDAD'),
            'duracion' => $this->require_int($accion, 'DURACION'),
            'fecha_inicio' => $this->require_text($accion, 'FECHA_INICIO'),
            'fecha_fin' => $this->require_text($accion, 'FECHA_FIN'),
            'itinerario_completo' => $this->require_text($accion, 'IND_ITINERARIO_COMPLETO'),
            'tipo_financiacion' => $this->require_text($accion, 'TIPO_FINANCIACION'),
            'numero_asistentes' => $this->require_int($accion, 'NUMERO_ASISTENTES'),
            'denominacion' => $this->require_text($descripcion, 'DENOMINACION_ACCION'),
            'informacion_general' => $this->optional_text($descripcion, 'INFORMACION_GENERAL'),
            'horarios' => $this->optional_text($descripcion, 'HORARIOS'),
            'requisitos' => $this->optional_text($descripcion, 'REQUISITOS'),
            'contacto' => $this->optional_text($descripcion, 'CONTACTO_ACCION'),
            'especialidades' => [],
            'participantes' => [],
            'rawxml' => $accion->ownerDocument->saveXML($accion),
        ];

        $especialidades = $this->child($accion, 'ESPECIALIDADES_ACCION');
        if ($especialidades !== null) {
            $sortorder = 0;
            foreach ($this->children($especialidades, 'ESPECIALIDAD') as $especialidad) {
                $parsed['especialidades'][] = $this->parse_especialidad($especialidad, $sortorder++);
            }
        }

        $participantes = $this->child($accion, 'PARTICIPANTES');
        if ($participantes !== null) {
            foreach ($this->children($participantes, 'PARTICIPANTE') as $participante) {
                $idp = $this->child($participante, 'ID_PARTICIPANTE');
                if ($idp === null) {
                    continue;
                }
                $parsed['participantes'][] = [
                    'tipo_documento' => $this->optional_text($idp, 'TIPO_DOCUMENTO') ?? '',
                    'num_documento' => $this->optional_text($idp, 'NUM_DOCUMENTO') ?? '',
                    'letra_nif' => $this->optional_text($idp, 'LETRA_NIF') ?? '',
                    'competencias_clave' => $this->optional_text($participante, 'INDICADOR_COMPETENCIAS_CLAVE'),
                ];
            }
        }

        return $parsed;
    }

    /**
     * Parses one ESPECIALIDAD element with its tutors.
     *
     * Nested detail is parsed leniently: the verbatim rawxml of the whole
     * action is the source of truth for echoes, these columns only feed
     * the admin UI and the Moodle-backed tracking generator.
     *
     * @param \DOMElement $especialidad ESPECIALIDAD element
     * @param int $sortorder position within ESPECIALIDADES_ACCION
     * @return array normalized speciality data with nested 'tutores'
     */
    private function parse_especialidad(\DOMElement $especialidad, int $sortorder): array {
        $id = $this->child($especialidad, 'ID_ESPECIALIDAD');
        $centro = $this->child($especialidad, 'CENTRO_IMPARTICION');
        $duracion = $this->child($especialidad, 'DATOS_DURACION');

        $result = [
            'origen_especialidad' => $id ? ($this->optional_text($id, 'ORIGEN_ESPECIALIDAD') ?? '') : '',
            'area_profesional' => $id ? ($this->optional_text($id, 'AREA_PROFESIONAL') ?? '') : '',
            'codigo_especialidad' => $id ? ($this->optional_text($id, 'CODIGO_ESPECIALIDAD') ?? '') : '',
            'centro_origen' => $centro ? $this->optional_text($centro, 'ORIGEN_CENTRO') : null,
            'centro_codigo' => $centro ? $this->optional_text($centro, 'CODIGO_CENTRO') : null,
            'fecha_inicio' => $this->optional_text($especialidad, 'FECHA_INICIO'),
            'fecha_fin' => $this->optional_text($especialidad, 'FECHA_FIN'),
            'modalidad' => $this->optional_text($especialidad, 'MODALIDAD_IMPARTICION'),
            'horas_presencial' => $duracion ? $this->optional_int($duracion, 'HORAS_PRESENCIAL') : null,
            'horas_teleformacion' => $duracion ? $this->optional_int($duracion, 'HORAS_TELEFORMACION') : null,
            'sortorder' => $sortorder,
            'tutores' => [],
        ];

        $tutores = $this->child($especialidad, 'TUTORES_FORMADORES');
        if ($tutores !== null) {
            foreach ($this->children($tutores, 'TUTOR_FORMADOR') as $tutor) {
                $idt = $this->child($tutor, 'ID_TUTOR');
                if ($idt === null) {
                    continue;
                }
                $result['tutores'][] = [
                    'tipo_documento' => $this->optional_text($idt, 'TIPO_DOCUMENTO') ?? '',
                    'num_documento' => $this->optional_text($idt, 'NUM_DOCUMENTO') ?? '',
                    'letra_nif' => $this->optional_text($idt, 'LETRA_NIF') ?? '',
                    'acreditacion' => $this->optional_text($tutor, 'ACREDITACION_TUTOR'),
                    'experiencia_profesional' => $this->optional_int($tutor, 'EXPERIENCIA_PROFESIONAL'),
                    'competencia_docente' => $this->optional_text($tutor, 'COMPETENCIA_DOCENTE'),
                    'experiencia_teleformacion' => $this->optional_int($tutor, 'EXPERIENCIA_MODALIDAD_TELEFORMACION'),
                    'formacion_teleformacion' => $this->optional_text($tutor, 'FORMACION_MODALIDAD_TELEFORMACION'),
                ];
            }
        }

        return $result;
    }

    /**
     * First direct child element with the given local name.
     *
     * @param \DOMElement $parent parent element
     * @param string $name local name to match, prefix-insensitive
     * @return \DOMElement|null the child, or null if absent
     */
    private function child(\DOMElement $parent, string $name): ?\DOMElement {
        foreach ($parent->childNodes as $child) {
            if ($child instanceof \DOMElement && $child->localName === $name) {
                return $child;
            }
        }
        return null;
    }

    /**
     * All direct child elements with the given local name.
     *
     * @param \DOMElement $parent parent element
     * @param string $name local name to match, prefix-insensitive
     * @return \DOMElement[] matching children in document order
     */
    private function children(\DOMElement $parent, string $name): array {
        $result = [];
        foreach ($parent->childNodes as $child) {
            if ($child instanceof \DOMElement && $child->localName === $name) {
                $result[] = $child;
            }
        }
        return $result;
    }

    /**
     * Required direct child element.
     *
     * @param \DOMElement $parent parent element
     * @param string $name local name of the required child
     * @return \DOMElement the child
     * @throws parameter_exception when the child is missing
     */
    private function require_child(\DOMElement $parent, string $name): \DOMElement {
        $child = $this->child($parent, $name);
        if ($child === null) {
            throw new parameter_exception($name);
        }
        return $child;
    }

    /**
     * Text content of a required direct child element.
     *
     * @param \DOMElement $parent parent element
     * @param string $name local name of the required child
     * @return string trimmed text content
     * @throws parameter_exception when the child is missing
     */
    private function require_text(\DOMElement $parent, string $name): string {
        return trim($this->require_child($parent, $name)->textContent);
    }

    /**
     * Integer content of a required direct child element.
     *
     * @param \DOMElement $parent parent element
     * @param string $name local name of the required child
     * @return int parsed integer
     * @throws parameter_exception when the child is missing or not an integer
     */
    private function require_int(\DOMElement $parent, string $name): int {
        $text = $this->require_text($parent, $name);
        if (!preg_match('/^-?\d+$/', $text)) {
            throw new parameter_exception($name);
        }
        return (int)$text;
    }

    /**
     * Text content of an optional direct child element.
     *
     * @param \DOMElement $parent parent element
     * @param string $name local name of the child
     * @return string|null trimmed text content, or null if the child is absent
     */
    private function optional_text(\DOMElement $parent, string $name): ?string {
        $child = $this->child($parent, $name);
        return $child === null ? null : trim($child->textContent);
    }

    /**
     * Integer content of an optional direct child element.
     *
     * @param \DOMElement $parent parent element
     * @param string $name local name of the child
     * @return int|null parsed integer, or null if absent or not numeric
     */
    private function optional_int(\DOMElement $parent, string $name): ?int {
        $text = $this->optional_text($parent, $name);
        if ($text === null || !preg_match('/^-?\d+$/', $text)) {
            return null;
        }
        return (int)$text;
    }
}
