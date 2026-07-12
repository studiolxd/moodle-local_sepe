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

namespace local_sepe\tracking;

/**
 * Tests for the NIF/NIE parser.
 *
 * @package    local_sepe
 * @copyright  2026 Studio LXD <hello@studiolxd.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(nif::class)]
final class nif_test extends \basic_testcase {
    public function test_dni(): void {
        $this->assertSame(
            ['tipo_documento' => 'D', 'num_documento' => '0012345678', 'letra_nif' => 'Z'],
            nif::parse('12345678Z')
        );
    }

    public function test_dni_short_number_is_zero_padded(): void {
        $this->assertSame(
            ['tipo_documento' => 'D', 'num_documento' => '0000000066', 'letra_nif' => 'R'],
            nif::parse('66R')
        );
    }

    public function test_dni_lowercase_and_separators(): void {
        $this->assertSame(
            ['tipo_documento' => 'D', 'num_documento' => '0012345678', 'letra_nif' => 'Z'],
            nif::parse('12.345.678-z')
        );
    }

    public function test_nie(): void {
        $this->assertSame(
            ['tipo_documento' => 'E', 'num_documento' => '00X1234567', 'letra_nif' => 'L'],
            nif::parse('X1234567L')
        );
    }

    public function test_invalid_values(): void {
        $this->assertNull(nif::parse(''));
        $this->assertNull(nif::parse('not a nif'));
        $this->assertNull(nif::parse('123456789012Z'));
        $this->assertNull(nif::parse('X123L'));
    }
}
