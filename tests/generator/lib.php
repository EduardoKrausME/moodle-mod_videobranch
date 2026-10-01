<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Branching Video test generator.
 *
 * @package mod_videobranch
 * @category test
 * @copyright 2026 Eduardo Kraus
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


/**
 * Test data generator for mod_videobranch.
 *
 * @package    mod_videobranch
 * @category   test
 * @copyright  2026 Eduardo Kraus
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_videobranch_generator extends testing_module_generator {
    /**
     * Creates a Branching Video activity.
     *
     * @param object|array|null $record Activity data.
     * @param array|null $options Course module options.
     * @return stdClass
     */
    public function create_instance($record = null, ?array $options = null) {
        $record = (object)(array)$record;
        $record->name = $record->name ?? 'Branching Video test';
        $record->resumeplayback = $record->resumeplayback ?? 1;
        $record->allowseek = $record->allowseek ?? 1;
        $record->allowback = $record->allowback ?? 0;
        $record->showpath = $record->showpath ?? 1;
        $record->completionending = $record->completionending ?? 1;
        return parent::create_instance($record, (array)$options);
    }
}
