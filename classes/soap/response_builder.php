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
 * Builds SOAP response envelopes for the SEPE tracking service.
 *
 * Responses are built with DOM instead of PHP's SoapServer because the
 * reference implementation SEPE validates against (Axis2 ADB) expects
 * shapes SoapServer cannot produce: xsi:nil="true" on elements the WSDL
 * does not declare nillable (error responses, empty action lists). The
 * output mirrors the official SoapUI mock envelopes shipped with the
 * SEPE self-evaluation kit: qualified operation and payload wrappers,
 * unqualified children.
 *
 * @package    local_sepe
 * @copyright  2026 Studio LXD <hello@studiolxd.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class response_builder {

    /** @var string XMLNS namespace used to declare prefixes. */
    private const NS_XMLNS = 'http://www.w3.org/2000/xmlns/';

    /**
     * Response to crearCentro / obtenerDatosCentro (RESPUESTA_DATOS_CENTRO).
     *
     * @param string $operation 'crearCentro' or 'obtenerDatosCentro'
     * @param int $codigo protocol return code
     * @param string|null $etiqueta offending tag for return code 2, null otherwise
     * @param \stdClass|null $centro centre record to echo, null renders xsi:nil
     * @return string response envelope XML
     */
    public function respuesta_datos_centro(string $operation, int $codigo, ?string $etiqueta, ?\stdClass $centro): string {
        [$doc, $wrapper] = $this->wrapper($operation, 'RESPUESTA_DATOS_CENTRO', $codigo, $etiqueta, true);

        $datos = $doc->createElementNS(protocol::NS_ENTSAL, 'p465:DATOS_IDENTIFICATIVOS');
        $wrapper->appendChild($datos);
        if ($centro === null) {
            $this->nil($datos);
        } else {
            $idcentro = $doc->createElement('ID_CENTRO');
            $datos->appendChild($idcentro);
            $this->text($idcentro, 'ORIGEN_CENTRO', $centro->origen_centro);
            $this->text($idcentro, 'CODIGO_CENTRO', $centro->codigo_centro);
            $this->text($datos, 'NOMBRE_CENTRO', $centro->nombre_centro);
            $this->text($datos, 'URL_PLATAFORMA', $centro->url_plataforma);
            $this->text($datos, 'URL_SEGUIMIENTO', $centro->url_seguimiento);
            $this->text($datos, 'TELEFONO', $centro->telefono);
            $this->text($datos, 'EMAIL', $centro->email);
        }

        return $doc->saveXML();
    }

    /**
     * Response to crearAccion / obtenerAccion (RESPUESTA_OBT_ACCION).
     *
     * @param string $operation 'crearAccion' or 'obtenerAccion'
     * @param int $codigo protocol return code
     * @param string|null $etiqueta offending tag for return code 2, null otherwise
     * @param string|null $accionxml ACCION_FORMATIVA XML fragment to echo, null renders xsi:nil
     * @return string response envelope XML
     */
    public function respuesta_obt_accion(string $operation, int $codigo, ?string $etiqueta, ?string $accionxml): string {
        [$doc, $wrapper] = $this->wrapper($operation, 'RESPUESTA_OBT_ACCION', $codigo, $etiqueta, true);

        $accion = $doc->createElementNS(protocol::NS_ENTSAL, 'p465:ACCION_FORMATIVA');
        $wrapper->appendChild($accion);
        if ($accionxml === null) {
            $this->nil($accion);
        } else {
            $fragment = new \DOMDocument('1.0', 'UTF-8');
            $fragment->loadXML($accionxml, LIBXML_NONET);
            foreach ($fragment->documentElement->childNodes as $child) {
                $accion->appendChild($doc->importNode($child, true));
            }
        }

        return $doc->saveXML();
    }

    /**
     * Response to obtenerListaAcciones (RESPUESTA_OBT_LISTA_ACCIONES).
     *
     * An empty list is rendered as a single nilled ID_ACCION element,
     * matching the official mock and data model examples.
     *
     * @param int $codigo protocol return code
     * @param string|null $etiqueta offending tag for return code 2, null otherwise
     * @param array $ids list of objects with origen_accion and codigo_accion
     * @return string response envelope XML
     */
    public function respuesta_lista_acciones(int $codigo, ?string $etiqueta, array $ids): string {
        [$doc, $wrapper] = $this->wrapper('obtenerListaAcciones', 'RESPUESTA_OBT_LISTA_ACCIONES', $codigo, $etiqueta, true);

        if ($ids === []) {
            $idaccion = $doc->createElementNS(protocol::NS_ENTSAL, 'p465:ID_ACCION');
            $wrapper->appendChild($idaccion);
            $this->nil($idaccion);
        } else {
            foreach ($ids as $id) {
                $idaccion = $doc->createElementNS(protocol::NS_ENTSAL, 'p465:ID_ACCION');
                $wrapper->appendChild($idaccion);
                $this->text($idaccion, 'ORIGEN_ACCION', $id->origen_accion);
                $this->text($idaccion, 'CODIGO_ACCION', $id->codigo_accion);
            }
        }

        return $doc->saveXML();
    }

    /**
     * Response to eliminarAccion (RESPUESTA_ELIMINAR_ACCION).
     *
     * @param int $codigo protocol return code
     * @param string|null $etiqueta offending tag for return code 2, null otherwise
     * @return string response envelope XML
     */
    public function respuesta_eliminar_accion(int $codigo, ?string $etiqueta): string {
        [$doc] = $this->wrapper('eliminarAccion', 'RESPUESTA_ELIMINAR_ACCION', $codigo, $etiqueta, false);
        return $doc->saveXML();
    }

    /**
     * SOAP 1.1 fault envelope.
     *
     * @param string $faultcode local fault code, e.g. 'Client' or 'FailedAuthentication'
     * @param string $faultstring human readable fault description
     * @param bool $wssecode when true the fault code is qualified with the wsse namespace
     * @return string fault envelope XML
     */
    public function fault(string $faultcode, string $faultstring, bool $wssecode = false): string {
        [$doc, $body] = $this->envelope();

        $fault = $doc->createElementNS(protocol::NS_SOAPENV, 'soapenv:Fault');
        $body->appendChild($fault);

        $code = $doc->createElement('faultcode');
        if ($wssecode) {
            $code->setAttributeNS(self::NS_XMLNS, 'xmlns:wsse', protocol::NS_WSSE);
            $code->textContent = 'wsse:' . $faultcode;
        } else {
            $code->textContent = 'soapenv:' . $faultcode;
        }
        $fault->appendChild($code);

        $string = $doc->createElement('faultstring');
        $string->textContent = $faultstring;
        $fault->appendChild($string);

        return $doc->saveXML();
    }

    /**
     * Creates the envelope with header and body.
     *
     * @return array{0: \DOMDocument, 1: \DOMElement} document and body element
     */
    private function envelope(): array {
        $doc = new \DOMDocument('1.0', 'UTF-8');
        $env = $doc->createElementNS(protocol::NS_SOAPENV, 'soapenv:Envelope');
        $env->setAttributeNS(self::NS_XMLNS, 'xmlns:soapenc', protocol::NS_SOAPENC);
        $env->setAttributeNS(self::NS_XMLNS, 'xmlns:xsd', protocol::NS_XSD);
        $env->setAttributeNS(self::NS_XMLNS, 'xmlns:xsi', protocol::NS_XSI);
        $doc->appendChild($env);

        $env->appendChild($doc->createElementNS(protocol::NS_SOAPENV, 'soapenv:Header'));
        $body = $doc->createElementNS(protocol::NS_SOAPENV, 'soapenv:Body');
        $env->appendChild($body);

        return [$doc, $body];
    }

    /**
     * Creates the operation response and payload wrapper with the common
     * CODIGO_RETORNO and ETIQUETA_ERROR children.
     *
     * @param string $operation operation name, suffixed with 'Response' for the wrapper
     * @param string $respuesta payload wrapper local name, e.g. 'RESPUESTA_DATOS_CENTRO'
     * @param int $codigo protocol return code
     * @param string|null $etiqueta offending tag for return code 2, null renders xsi:nil
     * @param bool $declareentsal whether to declare the entsal (p465) prefix on the wrapper
     * @return array{0: \DOMDocument, 1: \DOMElement} document and payload wrapper element
     */
    private function wrapper(string $operation, string $respuesta, int $codigo, ?string $etiqueta, bool $declareentsal): array {
        [$doc, $body] = $this->envelope();

        $response = $doc->createElementNS(protocol::NS_IMPL, 'p867:' . $operation . 'Response');
        $body->appendChild($response);

        $wrapper = $doc->createElementNS(protocol::NS_SALIDA, 'p148:' . $respuesta);
        if ($declareentsal) {
            $wrapper->setAttributeNS(self::NS_XMLNS, 'xmlns:p465', protocol::NS_ENTSAL);
        }
        $response->appendChild($wrapper);

        $this->text($wrapper, 'CODIGO_RETORNO', (string)$codigo);
        $error = $doc->createElement('ETIQUETA_ERROR');
        $wrapper->appendChild($error);
        if ($etiqueta === null) {
            $this->nil($error);
        } else {
            $error->textContent = $etiqueta;
        }

        return [$doc, $wrapper];
    }

    /**
     * Appends an unqualified text element.
     *
     * @param \DOMElement $parent parent element
     * @param string $name element local name
     * @param string $value text content, safely escaped by DOM
     */
    private function text(\DOMElement $parent, string $name, string $value): void {
        $el = $parent->ownerDocument->createElement($name);
        $el->textContent = $value;
        $parent->appendChild($el);
    }

    /**
     * Marks an element as nil.
     *
     * @param \DOMElement $el element to nil
     */
    private function nil(\DOMElement $el): void {
        $el->setAttributeNS(protocol::NS_XSI, 'xsi:nil', 'true');
    }
}
