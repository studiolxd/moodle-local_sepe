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

require(__DIR__ . '/../../config.php');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['wsdl'])) {
        $wsdl = file_get_contents(__DIR__ . '/wsdl/ProveedorCentroTFWS.wsdl');
        $endpoint = $CFG->wwwroot . '/local/sepe/endpoint.php';
        $wsdl = str_replace('@@ENDPOINTURL@@', $endpoint, $wsdl);
        @header('Content-Type: text/xml; charset=UTF-8');
        echo $wsdl;
        exit;
    }
    @header('HTTP/1.1 405 Method Not Allowed');
    @header('Allow: POST');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    @header('HTTP/1.1 405 Method Not Allowed');
    @header('Allow: POST');
    exit;
}

$rawxml = file_get_contents('php://input');
$server = new \local_sepe\soap\server();
$result = $server->handle($rawxml, getremoteaddr());

@header('HTTP/1.1 ' . ($result->status === 500 ? '500 Internal Server Error' : '200 OK'));
@header('Content-Type: text/xml; charset=UTF-8');
echo $result->body;
