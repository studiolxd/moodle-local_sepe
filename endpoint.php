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
 * SOAP endpoint of the SEPE teletraining tracking service.
 *
 * Consumed machine-to-machine by SEPE, which authenticates every request
 * with a WS-Security UsernameToken header validated in
 * \local_sepe\soap\wsse — hence no require_login()/session here.
 * GET ?wsdl serves the service contract; POST handles SOAP calls.
 *
 * @package    local_sepe
 * @copyright  2026 Studio LXD <hello@studiolxd.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:disable moodle.Files.RequireLogin.Missing -- machine-to-machine SOAP endpoint with WS-Security authentication.

define('NO_MOODLE_COOKIES', true);
define('NO_DEBUG_DISPLAY', true);

// Load Moodle config. __DIR__ resolves symlinks, so the standard
// '../../config.php' breaks when this plugin dir is symlinked into
// Moodle for development. Resolve config.php via the web document root
// (the Moodle web root in both the legacy and 5.x `public/` layouts);
// fall back to the relative path for CLI / non-symlinked installs.
require(
    (!empty($_SERVER['DOCUMENT_ROOT'])
        && is_file(rtrim($_SERVER['DOCUMENT_ROOT'], '/') . '/config.php'))
        ? rtrim($_SERVER['DOCUMENT_ROOT'], '/') . '/config.php'
        : __DIR__ . '/../../config.php'
);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['wsdl'])) {
        $wsdl = file_get_contents(__DIR__ . '/wsdl/ProveedorCentroTFWS.wsdl');
        $endpoint = $CFG->wwwroot . '/local/sepe/endpoint.php';
        $wsdl = str_replace('@@ENDPOINTURL@@', $endpoint, $wsdl);
        @header('Content-Type: text/xml; charset=UTF-8');
        @header('Cache-Control: public, max-age=3600');
        echo $wsdl;
        exit;
    }
    http_response_code(405);
    @header('Allow: POST');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    @header('Allow: POST');
    exit;
}

// Read at most one byte over the limit: the server rejects anything larger
// without the whole body ever being held in memory.
$rawxml = (string)file_get_contents('php://input', false, null, 0, \local_sepe\soap\protocol::MAX_REQUEST_BYTES + 1);
$server = new \local_sepe\soap\server();
$result = $server->handle($rawxml, getremoteaddr());

http_response_code($result->status);
@header('Content-Type: text/xml; charset=UTF-8');
echo $result->body;
