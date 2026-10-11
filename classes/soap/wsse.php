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
 * WS-Security UsernameToken Profile 1.0 authentication.
 *
 * SEPE authenticates with a fixed username ("SEPE") and a PasswordText
 * credential in the SOAP header. Only a password_hash() digest of the
 * expected password is stored, so verification is delegated to
 * password_verify(), which is timing-safe.
 *
 * @package    local_sepe
 * @copyright  2026 Studio LXD <hello@studiolxd.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class wsse {
    /**
     * Validates the WS-Security header of a request.
     *
     * @param \DOMDocument $doc parsed request envelope
     * @throws auth_failed_exception when the token is missing or invalid
     */
    public static function authenticate(\DOMDocument $doc): void {
        $token = self::extract_token($doc);
        if ($token === null) {
            throw new auth_failed_exception('Missing WS-Security UsernameToken');
        }
        if (
            $token['passwordtype'] !== null
                && !str_ends_with($token['passwordtype'], protocol::PASSWORDTEXT_SUFFIX)
        ) {
            throw new auth_failed_exception('Unsupported password type: ' . $token['passwordtype']);
        }
        $hash = (string)get_config('local_sepe', 'wspasswordhash');
        if ($hash === '') {
            throw new auth_failed_exception('No web service password configured');
        }
        // Evaluate both checks unconditionally so a wrong username costs
        // the same time as a wrong password.
        $userok = hash_equals(protocol::USERNAME, $token['username']);
        $passok = password_verify($token['password'], $hash);
        if (!$userok || !$passok) {
            throw new auth_failed_exception('Invalid credentials');
        }
    }

    /**
     * Masks the WS-Security password of a raw request before it is logged.
     *
     * Works on the raw text so it also covers requests that are not
     * well-formed XML.
     *
     * @param string $rawxml raw request body
     * @return string the request with the content of every Password element replaced
     */
    public static function redact(string $rawxml): string {
        $redacted = preg_replace(
            '#(<(?:[A-Za-z_][\w.-]*:)?Password\b[^>]*(?<!/)>).*?(</(?:[A-Za-z_][\w.-]*:)?Password\s*>)#s',
            '$1***$2',
            $rawxml
        );
        // On a PCRE failure, never fall back to the unredacted request.
        return $redacted ?? '[request not logged: password redaction failed]';
    }

    /**
     * Extracts the UsernameToken from the SOAP header, if present.
     *
     * @param \DOMDocument $doc parsed request envelope
     * @return array{username: string, password: string, passwordtype: string|null}|null
     */
    public static function extract_token(\DOMDocument $doc): ?array {
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('soapenv', protocol::NS_SOAPENV);
        $xpath->registerNamespace('wsse', protocol::NS_WSSE);

        $tokens = $xpath->query('/soapenv:Envelope/soapenv:Header/wsse:Security/wsse:UsernameToken');
        if ($tokens === false || $tokens->length === 0) {
            return null;
        }
        $token = $tokens->item(0);

        $usernames = $xpath->query('wsse:Username', $token);
        $passwords = $xpath->query('wsse:Password', $token);
        if ($usernames->length === 0 || $passwords->length === 0) {
            return null;
        }
        $password = $passwords->item(0);
        $type = null;
        if ($password instanceof \DOMElement && $password->hasAttribute('Type')) {
            $type = $password->getAttribute('Type');
        }

        return [
            'username' => $usernames->item(0)->textContent,
            'password' => $password->textContent,
            'passwordtype' => $type,
        ];
    }
}
