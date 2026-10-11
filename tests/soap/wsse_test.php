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
 * Tests for the WS-Security UsernameToken authentication.
 *
 * @package    local_sepe
 * @copyright  2026 Studio LXD <hello@studiolxd.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_sepe\soap\wsse
 */
#[\PHPUnit\Framework\Attributes\CoversClass(wsse::class)]
final class wsse_test extends \advanced_testcase {
    /** @var string PasswordText type URI. */
    private const PASSWORDTEXT = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-username-token-profile-1.0#PasswordText';

    /** @var string PasswordDigest type URI. */
    private const PASSWORDDIGEST = 'http://docs.oasis-open.org/wss/2004/01/'
        . 'oasis-200401-wss-username-token-profile-1.0#PasswordDigest';

    /**
     * Builds a request envelope with the given security header.
     *
     * @param string $username wsse:Username content
     * @param string $password wsse:Password content
     * @param string|null $type wsse:Password Type attribute, null to omit
     * @param bool $withtoken whether to include the UsernameToken at all
     * @return \DOMDocument parsed envelope
     */
    private function envelope(
        string $username = 'SEPE',
        string $password = 'secret',
        ?string $type = self::PASSWORDTEXT,
        bool $withtoken = true
    ): \DOMDocument {
        $typeattr = $type === null ? '' : ' Type="' . $type . '"';
        $token = $withtoken
            ? '<wsse:Security xmlns:wsse="' . protocol::NS_WSSE . '">'
                . '<wsse:UsernameToken>'
                . '<wsse:Username>' . $username . '</wsse:Username>'
                . '<wsse:Password' . $typeattr . '>' . $password . '</wsse:Password>'
                . '</wsse:UsernameToken></wsse:Security>'
            : '';
        $xml = '<soapenv:Envelope xmlns:soapenv="' . protocol::NS_SOAPENV . '" xmlns:impl="' . protocol::NS_IMPL . '">'
            . '<soapenv:Header>' . $token . '</soapenv:Header>'
            . '<soapenv:Body><impl:obtenerDatosCentro/></soapenv:Body></soapenv:Envelope>';
        $doc = new \DOMDocument();
        $doc->loadXML($xml);
        return $doc;
    }

    public function test_valid_token(): void {
        $this->resetAfterTest();
        set_config('wspasswordhash', password_hash('secret', PASSWORD_DEFAULT), 'local_sepe');
        wsse::authenticate($this->envelope());
        // No exception means success.
        $this->assertTrue(true);
    }

    public function test_valid_token_without_type_attribute(): void {
        $this->resetAfterTest();
        set_config('wspasswordhash', password_hash('secret', PASSWORD_DEFAULT), 'local_sepe');
        wsse::authenticate($this->envelope('SEPE', 'secret', null));
        $this->assertTrue(true);
    }

    public function test_wrong_password(): void {
        $this->resetAfterTest();
        set_config('wspasswordhash', password_hash('secret', PASSWORD_DEFAULT), 'local_sepe');
        $this->expectException(auth_failed_exception::class);
        wsse::authenticate($this->envelope('SEPE', 'EPESSEPECESEPE'));
    }

    public function test_wrong_username(): void {
        $this->resetAfterTest();
        set_config('wspasswordhash', password_hash('secret', PASSWORD_DEFAULT), 'local_sepe');
        $this->expectException(auth_failed_exception::class);
        wsse::authenticate($this->envelope('ADMIN', 'secret'));
    }

    public function test_missing_token(): void {
        $this->resetAfterTest();
        set_config('wspasswordhash', password_hash('secret', PASSWORD_DEFAULT), 'local_sepe');
        $this->expectException(auth_failed_exception::class);
        wsse::authenticate($this->envelope('SEPE', 'secret', self::PASSWORDTEXT, false));
    }

    public function test_password_digest_rejected(): void {
        $this->resetAfterTest();
        set_config('wspasswordhash', password_hash('secret', PASSWORD_DEFAULT), 'local_sepe');
        $this->expectException(auth_failed_exception::class);
        wsse::authenticate($this->envelope('SEPE', 'secret', self::PASSWORDDIGEST));
    }

    public function test_unconfigured_password_rejects_everything(): void {
        $this->resetAfterTest();
        set_config('wspasswordhash', '', 'local_sepe');
        $this->expectException(auth_failed_exception::class);
        wsse::authenticate($this->envelope());
    }

    public function test_redact_masks_every_password_form(): void {
        $prefixed = '<wsse:Password Type="x#PasswordText">s3cret</wsse:Password>';
        $this->assertSame('<wsse:Password Type="x#PasswordText">***</wsse:Password>', wsse::redact($prefixed));

        $unprefixed = '<Password>s3cret</Password>';
        $this->assertSame('<Password>***</Password>', wsse::redact($unprefixed));

        $cdata = '<o:Password><![CDATA[a<b]]></o:Password>';
        $this->assertSame('<o:Password>***</o:Password>', wsse::redact($cdata));

        // A self-closing element has no content and must not swallow the rest.
        $selfclosing = '<wsse:Password/><x>keep</x><wsse:Password>s3cret</wsse:Password>';
        $this->assertSame('<wsse:Password/><x>keep</x><wsse:Password>***</wsse:Password>', wsse::redact($selfclosing));

        // Similar element names are left alone.
        $this->assertSame('<PasswordHint>hi</PasswordHint>', wsse::redact('<PasswordHint>hi</PasswordHint>'));
    }
}
