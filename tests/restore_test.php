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
use backup;
use backup_controller;
use backup_setting;
use PHPUnit\Framework\Attributes\CoversNothing;
use restore_controller;
use restore_dbops;

/**
 * Tests Branching Video backup and restore.
 *
 * @coversNothing
 */
 * @package mod_videobranch
 * @category test
 * @copyright 2026 Eduardo Kraus
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversNothing]
final class restore_test extends advanced_testcase {
    /**
     * Loads backup and restore APIs.
     */
    public static function setUpBeforeClass(): void {
        global $CFG;

        parent::setUpBeforeClass();
        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
    }

    /**
     * Watched segments and the active path survive restore with remapped ids.
     */
    public function test_restore_preserves_watched_segments_and_rebuilds_path(): void {
        global $CFG, $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();
        $CFG->backup_file_logger_level = backup::LOG_NONE;

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
            'watchedjson' => json_encode([(string)$videoid => [[0.0, 5.0]]]),
            'pathjson' => json_encode([['stale' => true]]),
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

        $backup = new backup_controller(
            backup::TYPE_1COURSE,
            $course->id,
            backup::FORMAT_MOODLE,
            backup::INTERACTIVE_NO,
            backup::MODE_IMPORT,
            get_admin()->id
        );
        $backup->get_plan()->get_setting('users')->set_status(backup_setting::NOT_LOCKED);
        $backup->get_plan()->get_setting('users')->set_value(true);
        $backupid = $backup->get_backupid();
        $backup->execute_plan();
        $backup->destroy();

        $newcourseid = restore_dbops::create_new_course(
            $course->fullname,
            $course->shortname . '_restored',
            $course->category
        );
        $restore = new restore_controller(
            $backupid,
            $newcourseid,
            backup::INTERACTIVE_NO,
            backup::MODE_GENERAL,
            get_admin()->id,
            backup::TARGET_NEW_COURSE
        );
        $restore->get_plan()->get_setting('users')->set_status(backup_setting::NOT_LOCKED);
        $restore->get_plan()->get_setting('users')->set_value(true);
        $this->assertTrue($restore->execute_precheck());
        $restore->execute_plan();
        $restore->destroy();

        $restoredactivity = $DB->get_record('videobranch', ['course' => $newcourseid], '*', MUST_EXIST);
        $restoredvideo = $DB->get_record('videobranch_videos', [
            'videobranchid' => $restoredactivity->id,
        ], '*', MUST_EXIST);
        $restorednode = $DB->get_record('videobranch_nodes', [
            'videobranchid' => $restoredactivity->id,
        ], '*', MUST_EXIST);
        $restoredoption = $DB->get_record('videobranch_options', [
            'nodeid' => $restorednode->id,
        ], '*', MUST_EXIST);
        $restoredattempt = $DB->get_record('videobranch_attempts', [
            'videobranchid' => $restoredactivity->id,
            'userid' => $student->id,
        ], '*', MUST_EXIST);

        $watched = json_decode((string)$restoredattempt->watchedjson, true);
        $this->assertArrayHasKey((string)$restoredvideo->id, $watched);
        $this->assertEquals([[0.0, 5.0]], $watched[(string)$restoredvideo->id]);

        $path = json_decode((string)$restoredattempt->pathjson, true);
        $this->assertCount(1, $path);
        $this->assertEquals($restorednode->id, $path[0]['nodeid']);
        $this->assertEquals($restoredoption->id, $path[0]['optionid']);
        $this->assertEquals($restoredvideo->id, $path[0]['videoid']);
        $this->assertArrayNotHasKey('stale', $path[0]);
    }
}
