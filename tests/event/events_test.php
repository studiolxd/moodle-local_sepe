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

namespace local_sepe\event;

/**
 * Tests for the events of local_sepe.
 *
 * @package    local_sepe
 * @copyright  2026 Studio LXD <hello@studiolxd.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(accion_created::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(accion_deleted::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(accion_mapped::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(centro_updated::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(data_reset::class)]
final class events_test extends \advanced_testcase {
    /**
     * Events with valid data, keyed by class.
     *
     * @return array<string, array{0: class-string, 1: array}>
     */
    public static function events_provider(): array {
        $accion = ['origen_accion' => '20', 'codigo_accion' => '2026000001'];
        return [
            'accion_created' => [accion_created::class, ['objectid' => 1, 'other' => $accion]],
            'accion_deleted' => [accion_deleted::class, ['objectid' => 1, 'other' => $accion]],
            'accion_mapped' => [accion_mapped::class, ['objectid' => 1, 'other' => $accion + ['courseid' => 2]]],
            'centro_updated' => [
                centro_updated::class,
                ['objectid' => 1, 'other' => ['origen_centro' => '20', 'codigo_centro' => '9999999999']],
            ],
            'data_reset' => [data_reset::class, ['other' => ['acciones' => 3]]],
        ];
    }

    /**
     * Events trigger, describe themselves and declare their restore mapping.
     *
     * @param string $class event class
     * @param array $data event data without context
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('events_provider')]
    public function test_event(string $class, array $data): void {
        $this->resetAfterTest();
        $sink = $this->redirectEvents();
        $class::create($data + ['context' => \context_system::instance()])->trigger();
        $events = $sink->get_events();
        $sink->close();

        $this->assertCount(1, $events);
        $this->assertInstanceOf($class, $events[0]);
        $this->assertNotEmpty($events[0]->get_description());
        $this->assertNotEmpty($class::get_name());
        $this->assertFalse($class::get_other_mapping());
    }

    /**
     * Events refuse to be created without their mandatory other data.
     *
     * @param string $class event class
     * @param array $data event data without context
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('events_provider')]
    public function test_event_requires_other(string $class, array $data): void {
        $this->expectException(\coding_exception::class);
        $class::create(['other' => []] + $data + ['context' => \context_system::instance()]);
    }
}
