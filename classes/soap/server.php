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

use local_sepe\local\accion_repository;
use local_sepe\local\centro_repository;
use local_sepe\local\log_repository;

/**
 * SOAP server for the SEPE teletraining tracking service.
 *
 * Pure string-in/result-out orchestration: parses and authenticates the
 * envelope, dispatches to the operation handlers and always answers with
 * either a protocol response (HTTP 200 with CODIGO_RETORNO) or a SOAP
 * fault (HTTP 500) when the request cannot be understood at all —
 * matching the behaviour of the Axis2/WSS4J reference stack the SEPE
 * validator was written against.
 *
 * @package    local_sepe
 * @copyright  2026 Studio LXD <hello@studiolxd.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class server {
    /** @var response_builder Response envelope builder. */
    private response_builder $builder;

    /** @var request_parser Request payload parser. */
    private request_parser $parser;

    /** @var centro_repository Centre persistence. */
    private centro_repository $centros;

    /** @var accion_repository Action persistence. */
    private accion_repository $acciones;

    /** @var log_repository Request log persistence. */
    private log_repository $logs;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->builder = new response_builder();
        $this->parser = new request_parser();
        $this->centros = new centro_repository();
        $this->acciones = new accion_repository();
        $this->logs = new log_repository();
    }

    /**
     * Handles a raw SOAP request.
     *
     * @param string $rawxml raw request body
     * @param string $ip remote address, for the log
     * @return http_result response body and HTTP status
     */
    public function handle(string $rawxml, string $ip): http_result {
        $log = (object)[
            'operation' => '',
            'ip' => $ip,
            'returncode' => null,
            'requestxml' => null,
            'responsexml' => null,
            'errormsg' => null,
        ];
        $authenticated = false;

        try {
            if (strlen($rawxml) > protocol::MAX_REQUEST_BYTES) {
                throw new client_fault_exception('Request too large');
            }
            $doc = $this->parse_document($rawxml);
            wsse::authenticate($doc);
            $authenticated = true;
            $operation = $this->parser->operation($doc);
            $log->operation = $operation->localName;
            $body = $this->dispatch($operation->localName, $operation, $log);
            $log->responsexml = $body;
            return new http_result(200, $body);
        } catch (auth_failed_exception $e) {
            $log->operation = $log->operation !== '' ? $log->operation : 'auth';
            $log->errormsg = $e->getMessage();
            $body = $this->builder->fault(
                'FailedAuthentication',
                'The security token could not be authenticated or authorized',
                true
            );
            $log->responsexml = $body;
            return new http_result(500, $body);
        } catch (client_fault_exception $e) {
            $log->operation = $log->operation !== '' ? $log->operation : 'fault';
            $log->errormsg = $e->getMessage();
            $body = $this->builder->fault('Client', $e->getMessage());
            $log->responsexml = $body;
            return new http_result(500, $body);
        } finally {
            // The password is never logged, and unauthenticated requests
            // only keep a prefix, so anonymous traffic cannot fill the log.
            $requestxml = wsse::redact($rawxml);
            if (!$authenticated) {
                $requestxml = \core_text::substr($requestxml, 0, protocol::UNAUTHENTICATED_LOG_BYTES);
            }
            $log->requestxml = $requestxml;
            $this->logs->insert($log);
        }
    }

    /**
     * Parses the raw request into a DOM document.
     *
     * @param string $rawxml raw request body
     * @return \DOMDocument parsed envelope
     * @throws client_fault_exception when the XML is empty, malformed or carries a DOCTYPE
     */
    private function parse_document(string $rawxml): \DOMDocument {
        if (trim($rawxml) === '') {
            throw new client_fault_exception('Empty request');
        }
        // A DOCTYPE has no place in a SOAP message; rejecting it up front
        // rules out XXE and entity expansion attacks.
        if (stripos($rawxml, '<!DOCTYPE') !== false) {
            throw new client_fault_exception('DOCTYPE is not allowed');
        }

        $doc = new \DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $loaded = $doc->loadXML($rawxml, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded) {
            throw new client_fault_exception('Malformed XML');
        }
        return $doc;
    }

    /**
     * Runs an operation, converting business errors into protocol return codes.
     *
     * @param string $name operation name
     * @param \DOMElement $operation operation element
     * @param \stdClass $log log record being built
     * @return string response envelope XML
     */
    private function dispatch(string $name, \DOMElement $operation, \stdClass $log): string {
        if (get_config('local_sepe', 'servicedisabled')) {
            $log->returncode = protocol::RET_UNAVAILABLE;
            return $this->respond($name, protocol::RET_UNAVAILABLE, null);
        }

        try {
            $result = match ($name) {
                'crearCentro' => $this->crear_centro($operation, $log),
                'obtenerDatosCentro' => $this->obtener_datos_centro($log),
                'crearAccion' => $this->crear_accion($operation, $log),
                'obtenerAccion' => $this->obtener_accion($operation, $log),
                'obtenerListaAcciones' => $this->obtener_lista_acciones($log),
                'eliminarAccion' => $this->eliminar_accion($operation, $log),
            };
        } catch (parameter_exception $e) {
            $log->returncode = protocol::RET_PARAM;
            $log->errormsg = $e->getMessage();
            return $this->respond($name, protocol::RET_PARAM, $e->etiqueta);
        } catch (\Throwable $e) {
            $log->returncode = protocol::RET_UNEXPECTED;
            $log->errormsg = $e->getMessage();
            return $this->respond($name, protocol::RET_UNEXPECTED, null);
        }
        return $result;
    }

    /**
     * Builds the empty (nil payload) response matching the operation type.
     *
     * @param string $operation operation name
     * @param int $codigo protocol return code
     * @param string|null $etiqueta offending tag for return code 2
     * @return string response envelope XML
     */
    private function respond(string $operation, int $codigo, ?string $etiqueta): string {
        return match ($operation) {
            'crearCentro', 'obtenerDatosCentro' => $this->builder->respuesta_datos_centro($operation, $codigo, $etiqueta, null),
            'crearAccion', 'obtenerAccion' => $this->builder->respuesta_obt_accion($operation, $codigo, $etiqueta, null),
            'obtenerListaAcciones' => $this->builder->respuesta_lista_acciones($codigo, $etiqueta, []),
            'eliminarAccion' => $this->builder->respuesta_eliminar_accion($codigo, $etiqueta),
        };
    }

    /**
     * crearCentro: SEPE registers the centre and its assigned code.
     *
     * @param \DOMElement $operation operation element
     * @param \stdClass $log log record being built
     * @return string response envelope XML
     */
    private function crear_centro(\DOMElement $operation, \stdClass $log): string {
        global $DB;

        $datos = $this->parser->parse_datos_identificativos($operation);

        if ($this->centros->has_acciones()) {
            // Protocol: a centre that already tracks actions cannot be re-created.
            $log->returncode = protocol::RET_SPECIFIC;
            return $this->respond('crearCentro', protocol::RET_SPECIFIC, null);
        }

        $transaction = $DB->start_delegated_transaction();
        $record = $this->centros->upsert($datos);
        \local_sepe\event\centro_updated::create([
            'context' => \context_system::instance(),
            'objectid' => $record->id,
            'other' => [
                'origen_centro' => $record->origen_centro,
                'codigo_centro' => $record->codigo_centro,
            ],
        ])->trigger();
        $transaction->allow_commit();

        $log->returncode = protocol::RET_OK;
        return $this->builder->respuesta_datos_centro('crearCentro', protocol::RET_OK, null, $record);
    }

    /**
     * obtenerDatosCentro: returns the registered centre or the configured defaults.
     *
     * @param \stdClass $log log record being built
     * @return string response envelope XML
     */
    private function obtener_datos_centro(\stdClass $log): string {
        $centro = $this->centros->get_or_defaults();
        if ($centro === null) {
            $log->returncode = protocol::RET_UNAVAILABLE;
            return $this->respond('obtenerDatosCentro', protocol::RET_UNAVAILABLE, null);
        }
        $log->returncode = protocol::RET_OK;
        return $this->builder->respuesta_datos_centro('obtenerDatosCentro', protocol::RET_OK, null, $centro);
    }

    /**
     * crearAccion: SEPE notifies a training action and its assigned code.
     *
     * @param \DOMElement $operation operation element
     * @param \stdClass $log log record being built
     * @return string response envelope XML
     */
    private function crear_accion(\DOMElement $operation, \stdClass $log): string {
        global $DB;

        $parsed = $this->parser->parse_accion_formativa($operation);

        if ($this->acciones->exists($parsed['origen_accion'], $parsed['codigo_accion'])) {
            $log->returncode = protocol::RET_SPECIFIC;
            return $this->respond('crearAccion', protocol::RET_SPECIFIC, null);
        }

        $transaction = $DB->start_delegated_transaction();
        $record = $this->acciones->create($parsed);
        \local_sepe\event\accion_created::create([
            'context' => \context_system::instance(),
            'objectid' => $record->id,
            'other' => [
                'origen_accion' => $record->origen_accion,
                'codigo_accion' => $record->codigo_accion,
            ],
        ])->trigger();
        $transaction->allow_commit();

        $log->returncode = protocol::RET_OK;
        return $this->builder->respuesta_obt_accion('crearAccion', protocol::RET_OK, null, $parsed['rawxml']);
    }

    /**
     * obtenerAccion: returns the tracking data of an action.
     *
     * Actions mapped to a Moodle course answer with tracking data generated
     * from the course (cached up to a day, see generator::tracking_xml());
     * unmapped actions (including the validator's test data) echo the
     * verbatim XML received on creation.
     *
     * @param \DOMElement $operation operation element
     * @param \stdClass $log log record being built
     * @return string response envelope XML
     */
    private function obtener_accion(\DOMElement $operation, \stdClass $log): string {
        $id = $this->parser->parse_id_accion($operation);
        $accion = $this->acciones->get($id['origen_accion'], $id['codigo_accion']);
        if ($accion === null) {
            $log->returncode = protocol::RET_SPECIFIC;
            return $this->respond('obtenerAccion', protocol::RET_SPECIFIC, null);
        }

        $xml = $accion->rawxml;
        if (!empty($accion->courseid)) {
            $xml = \local_sepe\tracking\generator::tracking_xml($accion) ?? $xml;
        }

        $log->returncode = protocol::RET_OK;
        return $this->builder->respuesta_obt_accion('obtenerAccion', protocol::RET_OK, null, $xml);
    }

    /**
     * obtenerListaAcciones: identifiers of every tracked action.
     *
     * @param \stdClass $log log record being built
     * @return string response envelope XML
     */
    private function obtener_lista_acciones(\stdClass $log): string {
        $ids = $this->acciones->list_ids();
        $log->returncode = protocol::RET_OK;
        return $this->builder->respuesta_lista_acciones(protocol::RET_OK, null, $ids);
    }

    /**
     * eliminarAccion: SEPE removes a training action.
     *
     * @param \DOMElement $operation operation element
     * @param \stdClass $log log record being built
     * @return string response envelope XML
     */
    private function eliminar_accion(\DOMElement $operation, \stdClass $log): string {
        global $DB;

        $id = $this->parser->parse_id_accion($operation);

        $transaction = $DB->start_delegated_transaction();
        $deleted = $this->acciones->delete($id['origen_accion'], $id['codigo_accion']);
        if ($deleted !== null) {
            \local_sepe\event\accion_deleted::create([
                'context' => \context_system::instance(),
                'objectid' => $deleted->id,
                'other' => [
                    'origen_accion' => $deleted->origen_accion,
                    'codigo_accion' => $deleted->codigo_accion,
                ],
            ])->trigger();
        }
        $transaction->allow_commit();

        if ($deleted === null) {
            $log->returncode = protocol::RET_SPECIFIC;
            return $this->respond('eliminarAccion', protocol::RET_SPECIFIC, null);
        }
        $log->returncode = protocol::RET_OK;
        return $this->builder->respuesta_eliminar_accion(protocol::RET_OK, null);
    }
}
