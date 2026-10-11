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

#[\PHPUnit\Framework\Attributes\CoversClass(server::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(request_parser::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(response_builder::class)]
/**
 * End-to-end tests of the SOAP server.
 *
 * test_validator_sequence() replays the exact request sequence of the
 * official SEPE self-evaluation kit (ProvCentTFVal), using the request
 * envelopes shipped in its SoapUI example project as fixtures.
 *
 * @package    local_sepe
 * @copyright  2026 Studio LXD <hello@studiolxd.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_sepe\soap\server
 * @covers     \local_sepe\soap\request_parser
 * @covers     \local_sepe\soap\response_builder
 */
final class server_test extends \advanced_testcase {
    /** @var string Password configured for the service in these tests. */
    private const PASSWORD = 'kit-password';

    /**
     * Configures the service like a centre about to run the validator.
     */
    private function configure_service(): void {
        set_config('wspasswordhash', password_hash(self::PASSWORD, PASSWORD_DEFAULT), 'local_sepe');
        set_config('centroorigen', '20', 'local_sepe');
        set_config('centrocodigo', '9999999999', 'local_sepe');
        set_config('centronombre', 'Centro de teleformación número 00000001', 'local_sepe');
        set_config('centrourlplataforma', 'https://sede.sepe.gob.es/portalSede/flows/inicio', 'local_sepe');
        set_config('centrourlseguimiento', 'https://example.com/local/sepe/endpoint.php', 'local_sepe');
        set_config('centrotelefono', '915859999', 'local_sepe');
        set_config('centroemail', 'bajel@sepe.es', 'local_sepe');
    }

    /**
     * Sends a fixture request through the server.
     *
     * @param string $fixture fixture file name under tests/fixtures
     * @param string $password WS-Security password to authenticate with
     * @return http_result server response
     */
    private function handle(string $fixture, string $password = self::PASSWORD): http_result {
        $raw = file_get_contents(__DIR__ . '/../fixtures/' . $fixture);
        return (new server())->handle($this->authenticated($raw, $password), '127.0.0.1');
    }

    /**
     * Injects a WS-Security header into a SoapUI fixture envelope.
     *
     * @param string $raw fixture envelope with an empty header
     * @param string $password WS-Security password
     * @return string envelope with UsernameToken
     */
    private function authenticated(string $raw, string $password): string {
        $header = '<soapenv:Header>'
            . '<wsse:Security xmlns:wsse="' . protocol::NS_WSSE . '"><wsse:UsernameToken>'
            . '<wsse:Username>SEPE</wsse:Username>'
            . '<wsse:Password Type="http://docs.oasis-open.org/wss/2004/01/'
            . 'oasis-200401-wss-username-token-profile-1.0#PasswordText">' . $password . '</wsse:Password>'
            . '</wsse:UsernameToken></wsse:Security></soapenv:Header>';
        return str_replace('<soapenv:Header/>', $header, $raw);
    }

    /**
     * Extracts the protocol return code from a response body.
     *
     * @param string $body response envelope
     * @return int CODIGO_RETORNO value
     */
    private function returncode(string $body): int {
        $doc = new \DOMDocument();
        $doc->loadXML($body);
        $codes = $doc->getElementsByTagName('CODIGO_RETORNO');
        $this->assertGreaterThan(0, $codes->length, 'Response has no CODIGO_RETORNO: ' . $body);
        return (int)$codes->item(0)->textContent;
    }

    /**
     * Namespace-agnostic canonical form of the first element with the given
     * local name (C14N would drag the in-scope namespace declarations of the
     * envelope into the comparison).
     *
     * @param string $xml XML document
     * @param string $localname element to canonicalize
     * @return string normalized representation of the element subtree
     */
    private function canonical_children(string $xml, string $localname): string {
        $doc = new \DOMDocument();
        $doc->loadXML($xml);
        $elements = $doc->getElementsByTagName($localname);
        $this->assertGreaterThan(0, $elements->length);
        return $this->normalize($elements->item(0));
    }

    /**
     * Recursively serializes an element by local name and trimmed text.
     *
     * @param \DOMElement $element element to serialize
     * @return string normalized representation
     */
    private function normalize(\DOMElement $element): string {
        $inner = '';
        $haschildelements = false;
        foreach ($element->childNodes as $child) {
            if ($child instanceof \DOMElement) {
                $haschildelements = true;
                $inner .= $this->normalize($child);
            }
        }
        if (!$haschildelements) {
            $inner = trim($element->textContent);
        }
        return '<' . $element->localName . '>' . $inner . '</' . $element->localName . '>';
    }

    public function test_validator_sequence(): void {
        $this->resetAfterTest();
        $this->configure_service();

        // 1. Wrong credentials must be rejected with a wsse fault.
        $result = $this->handle('request_obtenerDatosCentro_obtenerDatosCentro.xml', 'EPESSEPECESEPE');
        $this->assertSame(500, $result->status);
        $this->assertStringContainsString('FailedAuthentication', $result->body);

        // 2. obtenerDatosCentro answers with the configured centre data.
        $result = $this->handle('request_obtenerDatosCentro_obtenerDatosCentro.xml');
        $this->assertSame(200, $result->status);
        $this->assertSame(0, $this->returncode($result->body));
        $this->assertStringContainsString('Centro de teleformación número 00000001', $result->body);

        // 3. crearCentro registers the centre and echoes it.
        $result = $this->handle('request_crearCentro_crearCentro.xml');
        $this->assertSame(0, $this->returncode($result->body));
        $this->assertStringContainsString('<CODIGO_CENTRO>9999999999</CODIGO_CENTRO>', $result->body);

        // 4. obtenerDatosCentro now returns the registered centre.
        $result = $this->handle('request_obtenerDatosCentro_obtenerDatosCentro.xml');
        $this->assertSame(0, $this->returncode($result->body));
        $this->assertStringContainsString('<ORIGEN_CENTRO>20</ORIGEN_CENTRO>', $result->body);

        // 5. The action list is empty: a single nilled ID_ACCION.
        $result = $this->handle('request_obtenerListaAcciones_obtenerListaAcciones.xml');
        $this->assertSame(0, $this->returncode($result->body));
        $this->assertMatchesRegularExpression('/<p465:ID_ACCION[^>]*nil="true"/', $result->body);

        // 6. The five test actions are created.
        foreach (['2014280010', '2014280011', '2014280012', '2014280013', '2014280014'] as $codigo) {
            $result = $this->handle("request_crearAccion_crearAccion_{$codigo}.xml");
            $this->assertSame(0, $this->returncode($result->body), "crearAccion {$codigo}");
        }

        // 7. The list now contains all of them.
        $result = $this->handle('request_obtenerListaAcciones_obtenerListaAcciones.xml');
        $this->assertSame(0, $this->returncode($result->body));
        $this->assertSame(5, substr_count($result->body, '<p465:ID_ACCION>'));

        // 8. A duplicate crearAccion answers 1 with a nilled action.
        $result = $this->handle('request_crearAccion_crearAccion_2014280010.xml');
        $this->assertSame(1, $this->returncode($result->body));
        $this->assertMatchesRegularExpression('/<p465:ACCION_FORMATIVA[^>]*nil="true"/', $result->body);

        // 9. obtenerAccion echoes exactly what crearAccion received.
        $result = $this->handle('request_obtenerAccion_obtenerAccion.xml');
        $this->assertSame(0, $this->returncode($result->body));
        $request = file_get_contents(__DIR__ . '/../fixtures/request_crearAccion_crearAccion_2014280014.xml');
        $this->assertSame(
            $this->canonical_children($request, 'ACCION_FORMATIVA'),
            $this->canonical_children($result->body, 'ACCION_FORMATIVA')
        );

        // 10. eliminarAccion deletes once and answers 1 afterwards.
        $result = $this->handle('request_eliminarAccion_eliminarAccion.xml');
        $this->assertSame(0, $this->returncode($result->body));
        $result = $this->handle('request_eliminarAccion_eliminarAccion.xml');
        $this->assertSame(1, $this->returncode($result->body));
    }

    public function test_obtener_accion_unknown_returns_1(): void {
        $this->resetAfterTest();
        $this->configure_service();
        $result = $this->handle('request_obtenerAccion_obtenerAccion.xml');
        $this->assertSame(1, $this->returncode($result->body));
    }

    public function test_missing_required_tag_returns_2_with_etiqueta(): void {
        $this->resetAfterTest();
        $this->configure_service();

        $raw = file_get_contents(__DIR__ . '/../fixtures/request_crearCentro_crearCentro.xml');
        $raw = preg_replace('/<TELEFONO>.*?<\/TELEFONO>/s', '', $raw);
        $result = (new server())->handle($this->authenticated($raw, self::PASSWORD), '127.0.0.1');
        $this->assertSame(2, $this->returncode($result->body));
        $this->assertStringContainsString('<ETIQUETA_ERROR>TELEFONO</ETIQUETA_ERROR>', $result->body);
    }

    public function test_malformed_xml_returns_client_fault(): void {
        $this->resetAfterTest();
        $this->configure_service();
        $result = (new server())->handle('this is not xml', '127.0.0.1');
        $this->assertSame(500, $result->status);
        $this->assertStringContainsString('soapenv:Client', $result->body);
    }

    public function test_doctype_is_rejected(): void {
        $this->resetAfterTest();
        $this->configure_service();
        $raw = '<?xml version="1.0"?><!DOCTYPE foo [<!ENTITY bar "baz">]><foo>&bar;</foo>';
        $result = (new server())->handle($raw, '127.0.0.1');
        $this->assertSame(500, $result->status);
        $this->assertStringContainsString('soapenv:Client', $result->body);
    }

    public function test_service_disabled_returns_minus_2(): void {
        $this->resetAfterTest();
        $this->configure_service();
        set_config('servicedisabled', 1, 'local_sepe');
        $result = $this->handle('request_obtenerDatosCentro_obtenerDatosCentro.xml');
        $this->assertSame(200, $result->status);
        $this->assertSame(-2, $this->returncode($result->body));
    }

    public function test_requests_are_logged(): void {
        global $DB;
        $this->resetAfterTest();
        $this->configure_service();

        $this->handle('request_obtenerDatosCentro_obtenerDatosCentro.xml');
        $logs = $DB->get_records('local_sepe_wslog');
        $this->assertCount(1, $logs);
        $log = reset($logs);
        $this->assertSame('obtenerDatosCentro', $log->operation);
        $this->assertSame('0', (string)$log->returncode);
        $this->assertSame('127.0.0.1', $log->ip);
    }

    public function test_logged_request_never_contains_the_password(): void {
        global $DB;
        $this->resetAfterTest();
        $this->configure_service();

        $this->handle('request_obtenerDatosCentro_obtenerDatosCentro.xml');
        $this->handle('request_obtenerDatosCentro_obtenerDatosCentro.xml', 'wrong-password');

        $logs = $DB->get_records('local_sepe_wslog', null, 'id ASC');
        $this->assertCount(2, $logs);
        foreach ($logs as $log) {
            $this->assertStringNotContainsString(self::PASSWORD, $log->requestxml);
            $this->assertStringNotContainsString('wrong-password', $log->requestxml);
            $this->assertStringContainsString('***</wsse:Password>', $log->requestxml);
        }
    }

    public function test_unauthenticated_requests_are_logged_truncated(): void {
        global $DB;
        $this->resetAfterTest();
        $this->configure_service();

        $raw = file_get_contents(__DIR__ . '/../fixtures/request_crearAccion_crearAccion_2014280010.xml');
        $raw = str_replace('</soapenv:Body>', str_repeat('<!-- padding -->', 1000) . '</soapenv:Body>', $raw);
        $result = (new server())->handle($this->authenticated($raw, 'wrong-password'), '127.0.0.1');
        $this->assertSame(500, $result->status);

        $log = $DB->get_record('local_sepe_wslog', []);
        $this->assertSame('auth', $log->operation);
        $this->assertSame(protocol::UNAUTHENTICATED_LOG_BYTES, \core_text::strlen($log->requestxml));
        $this->assertSame(0, $DB->count_records('local_sepe_accion'));
    }

    public function test_oversized_request_is_rejected(): void {
        global $DB;
        $this->resetAfterTest();
        $this->configure_service();

        $raw = file_get_contents(__DIR__ . '/../fixtures/request_obtenerDatosCentro_obtenerDatosCentro.xml');
        $raw = str_replace('</soapenv:Body>', str_repeat(' ', protocol::MAX_REQUEST_BYTES) . '</soapenv:Body>', $raw);
        $result = (new server())->handle($this->authenticated($raw, self::PASSWORD), '127.0.0.1');

        $this->assertSame(500, $result->status);
        $this->assertStringContainsString('Request too large', $result->body);
        $log = $DB->get_record('local_sepe_wslog', []);
        $this->assertSame('fault', $log->operation);
        $this->assertLessThanOrEqual(protocol::UNAUTHENTICATED_LOG_BYTES, strlen($log->requestxml));
    }
}
