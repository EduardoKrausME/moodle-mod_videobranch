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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Tests for Branching Video.
 *
 * @package    mod_videobranch
 * @copyright  2026 Eduardo Kraus
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_videobranch\privacy;

use core_privacy\local\metadata\collection;

/**
 * Tests the Branching Video privacy provider.
 *
 * @package mod_videobranch
 * @category test
 * @copyright 2026 Eduardo Kraus
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_videobranch\privacy\provider
 */
final class provider_test extends \core_privacy\tests\provider_testcase {
    /**
     * All learner state fields are declared as privacy metadata.
     */
    public function test_get_metadata(): void {
        $collection = provider::get_metadata(new collection('mod_videobranch'));
        $items = $collection->get_collection();
        $tables = [];
        foreach ($items as $item) {
            $tables[$item->get_name()] = $item;
        }

        $this->assertArrayHasKey('videobranch_attempts', $tables);
        $attemptfields = $tables['videobranch_attempts']->get_privacy_fields();
        foreach ([
            'videobranchid', 'userid', 'currentvideoid', 'currentposition', 'watchedjson', 'pathjson',
            'endingid', 'completed', 'timecreated', 'timemodified', 'timecompleted',
        ] as $field) {
            $this->assertArrayHasKey($field, $attemptfields);
        }

        $this->assertArrayHasKey('videobranch_choices', $tables);
        $choicefields = $tables['videobranch_choices']->get_privacy_fields();
        foreach ([
            'attemptid', 'nodeid', 'optionid', 'sequence', 'active',
            'fromvideoid', 'fromposition', 'timecreated',
        ] as $field) {
            $this->assertArrayHasKey($field, $choicefields);
        }
    }
}
