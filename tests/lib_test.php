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
 * Tests for Branching Video.
 *
 * @package    mod_videobranch
 * @copyright  2026 Eduardo Kraus
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videobranch;

use advanced_testcase;
use PHPUnit\Framework\Attributes\CoversFunction;

defined('MOODLE_INTERNAL') || die;
global $CFG;

require_once($CFG->dirroot . '/mod/videobranch/lib.php');

/**
 * Tests core module callbacks.
 *
 * @package mod_videobranch
 * @category test
 * @copyright 2026 Eduardo Kraus
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversFunction('videobranch_get_coursemodule_info')]
#[CoversFunction('videobranch_reset_userdata')]
final class lib_test extends advanced_testcase {
    /**
     * Custom completion rules are exposed through cached course module information.
     */
    public function test_get_coursemodule_info_exposes_completion_rule(): void {
        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => COMPLETION_ENABLED]);
        $activity = $this->getDataGenerator()->get_plugin_generator('mod_videobranch')->create_instance(
            ['course' => $course->id, 'completionending' => 1],
            ['completion' => COMPLETION_TRACKING_AUTOMATIC]
        );
        $cm = get_coursemodule_from_instance('videobranch', $activity->id, $course->id, false, MUST_EXIST);

        $info = videobranch_get_coursemodule_info($cm);

        $this->assertArrayHasKey('customcompletionrules', $info->customdata);
        $this->assertEquals(1, $info->customdata['customcompletionrules']['completionending']);
    }

    /**
     * Course reset removes attempts and dependent choices.
     */
    public function test_reset_userdata_removes_attempts_and_choices(): void {
        global $DB;

        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $activity = $this->getDataGenerator()->create_module('videobranch', ['course' => $course->id]);
        $now = time();

        $videoid = $DB->insert_record('videobranch_videos', (object)[
            'videobranchid' => $activity->id,
            'name' => 'Video',
            'sourcetype' => 'url',
            'sourceurl' => 'https://example.com/video.mp4',
            'isstart' => 1,
            'sortorder' => 0,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $nodeid = $DB->insert_record('videobranch_nodes', (object)[
            'videobranchid' => $activity->id,
            'videoid' => $videoid,
            'name' => 'Decision',
            'question' => 'Continue?',
            'triggersecond' => 5.0,
            'sortorder' => 0,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $endingid = $DB->insert_record('videobranch_endings', (object)[
            'videobranchid' => $activity->id,
            'name' => 'End',
            'message' => '',
            'sortorder' => 0,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $optionid = $DB->insert_record('videobranch_options', (object)[
            'nodeid' => $nodeid,
            'label' => 'Finish',
            'targettype' => 'end',
            'targetvideoid' => null,
            'targetsecond' => null,
            'targetnodeid' => null,
            'targetendingid' => $endingid,
            'sortorder' => 0,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $attemptid = $DB->insert_record('videobranch_attempts', (object)[
            'videobranchid' => $activity->id,
            'userid' => $student->id,
            'currentvideoid' => $videoid,
            'currentposition' => 5.0,
            'watchedjson' => '{}',
            'pathjson' => '[]',
            'endingid' => null,
            'completed' => 0,
            'timecreated' => $now,
            'timemodified' => $now,
            'timecompleted' => null,
        ]);
        $DB->insert_record('videobranch_choices', (object)[
            'attemptid' => $attemptid,
            'nodeid' => $nodeid,
            'optionid' => $optionid,
            'sequence' => 1,
            'active' => 1,
            'fromvideoid' => $videoid,
            'fromposition' => 5.0,
            'timecreated' => $now,
        ]);

        $status = videobranch_reset_userdata((object)[
            'courseid' => $course->id,
            'reset_videobranch' => 1,
        ]);

        $this->assertCount(1, $status);
        $this->assertEquals(0, $DB->count_records('videobranch_attempts', ['videobranchid' => $activity->id]));
        $this->assertEquals(0, $DB->count_records('videobranch_choices', ['attemptid' => $attemptid]));
    }
}
