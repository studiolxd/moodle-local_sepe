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
 * Constants of the SEPE teletraining tracking SOAP protocol.
 *
 * Namespaces and return codes as defined by the official WSDL
 * (ProveedorCentroTFWS, June 2014) and the data model document
 * (November 2019).
 *
 * @package    local_sepe
 * @copyright  2026 Studio LXD <hello@studiolxd.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class protocol {
    /** @var string SOAP 1.1 envelope namespace. */
    public const NS_SOAPENV = 'http://schemas.xmlsoap.org/soap/envelope/';

    /** @var string SOAP 1.1 encoding namespace (declared on responses for Axis2 compatibility). */
    public const NS_SOAPENC = 'http://schemas.xmlsoap.org/soap/encoding/';

    /** @var string Namespace of the service operations. */
    public const NS_IMPL = 'http://impl.ws.application.proveedorcentro.meyss.spee.es';

    /** @var string Namespace of the input/output payload elements. */
    public const NS_ENTSAL = 'http://entsal.bean.domain.common.proveedorcentro.meyss.spee.es';

    /** @var string Namespace of the response wrapper elements. */
    public const NS_SALIDA = 'http://salida.bean.domain.common.proveedorcentro.meyss.spee.es';

    /** @var string WS-Security extensions namespace (UsernameToken Profile 1.0). */
    public const NS_WSSE = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd';

    /** @var string XML Schema instance namespace. */
    public const NS_XSI = 'http://www.w3.org/2001/XMLSchema-instance';

    /** @var string XML Schema namespace. */
    public const NS_XSD = 'http://www.w3.org/2001/XMLSchema';

    /** @var string PasswordText token type suffix accepted in the wsse Password Type attribute. */
    public const PASSWORDTEXT_SUFFIX = '#PasswordText';

    /** @var string Fixed WS-Security username used by SEPE. */
    public const USERNAME = 'SEPE';

    /** @var int Largest request body accepted, in bytes. Real crearAccion requests are well below 1 MB. */
    public const MAX_REQUEST_BYTES = 5 * 1024 * 1024;

    /** @var int Bytes of the request kept in the log when the request was not authenticated. */
    public const UNAUTHENTICATED_LOG_BYTES = 4096;

    /** @var int Return code: operation completed successfully. */
    public const RET_OK = 0;

    /** @var int Return code: unexpected error. */
    public const RET_UNEXPECTED = -1;

    /** @var int Return code: service unavailable. */
    public const RET_UNAVAILABLE = -2;

    /**
     * Return code: operation-specific error.
     *
     * Centre already has actions (crearCentro), action already exists
     * (crearAccion) or action does not exist (obtenerAccion, eliminarAccion).
     *
     * @var int
     */
    public const RET_SPECIFIC = 1;

    /** @var int Return code: invalid parameter; ETIQUETA_ERROR carries the offending tag name. */
    public const RET_PARAM = 2;

    /** @var string[] Operations defined by the WSDL. */
    public const OPERATIONS = [
        'crearCentro',
        'obtenerDatosCentro',
        'crearAccion',
        'obtenerAccion',
        'obtenerListaAcciones',
        'eliminarAccion',
    ];

    /**
     * This is a constants holder, never instantiated.
     */
    private function __construct() {
    }
}
