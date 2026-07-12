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
 * Thrown when a required payload tag is missing or invalid.
 *
 * Mapped to protocol return code 2 with ETIQUETA_ERROR set to the
 * offending tag name, as defined by the SEPE data model.
 *
 * @package    local_sepe
 * @copyright  2026 Studio LXD <hello@studiolxd.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class parameter_exception extends \Exception {
    /**
     * Constructor.
     *
     * @param string $etiqueta name of the offending tag, reported back in ETIQUETA_ERROR
     */
    public function __construct(
        /** @var string Name of the offending tag. */
        public readonly string $etiqueta,
    ) {
        parent::__construct('Invalid parameter: ' . $etiqueta);
    }
}
