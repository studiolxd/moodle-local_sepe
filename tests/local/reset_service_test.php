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

namespace local_sepe\local;

/**
 * Tests for the validation data reset.
 *
 * @package    local_sepe
 * @copyright  2026 Studio LXD <hello@studiolxd.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(reset_service::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_sepe\event\data_reset::class)]
final class reset_service_test extends \advanced_testcase {
    public function test_reset_deletes_everything_and_triggers_event(): void {
        global $DB;
        $this->resetAfterTest();

        $raw = file_get_contents(__DIR__ . '/../fixtures/request_crearAccion_crearAccion_2014280010.xml');
        $doc = new \DOMDocument();
        $doc->loadXML($raw);
        $parser = new \local_sepe\soap\request_parser();
        (new accion_repository())->create($parser->parse_accion_formativa($parser->operation($doc)));
        (new centro_repository())->upsert([
            'origen_centro' => '20',
            'codigo_centro' => '9999999999',
            'nombre_centro' => 'Centro',
            'url_plataforma' => 'https://example.com',
            'url_seguimiento' => 'https://example.com/local/sepe/endpoint.php',
            'telefono' => '915859999',
            'email' => 'centro@example.com',
        ]);
        $this->assertGreaterThan(0, $DB->count_records('local_sepe_participante'));

        $sink = $this->redirectEvents();
        $count = (new reset_service())->reset();
        $events = $sink->get_events();
        $sink->close();

        $this->assertSame(1, $count);
        foreach (['centro', 'accion', 'especialidad', 'tutor', 'participante'] as $table) {
            $this->assertSame(0, $DB->count_records('local_sepe_' . $table), $table);
        }
        $this->assertCount(1, $events);
        $this->assertInstanceOf(\local_sepe\event\data_reset::class, $events[0]);
        $this->assertSame(1, $events[0]->other['acciones']);
    }
}
