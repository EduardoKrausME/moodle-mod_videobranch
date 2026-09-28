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
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

/**
 * Tests for Branching Video.
 *
 * @package    mod_videobranch
 * @copyright  2026 Eduardo Kraus
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_videobranch;

/**
 * Tests learner path persistence and validation.
 *
 * @package mod_videobranch
 * @category test
 * @copyright 2026 Eduardo Kraus
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_videobranch\attempt_manager
 * @covers \mod_videobranch\branch_manager
 */
final class attempt_manager_test extends \advanced_testcase {
    /**
     * Teacher preview must not create learner data.
     */
    public function test_preview_does_not_create_attempt(): void {
        global $DB;

        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $activity = $this->getDataGenerator()->create_module('videobranch', ['course' => $course->id]);
        $graph = $this->create_graph($activity);
        $cm = get_coursemodule_from_instance('videobranch', $activity->id, $course->id, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);

        $manager = new branch_manager($activity, $cm, $context);
        $config = $manager->get_player_config($student->id, true);

        $this->assertTrue($config['preview']);
        $this->assertEquals($graph->video1, $config['attempt']['videoid']);
        $this->assertEquals(0, $DB->count_records('videobranch_attempts', [
            'videobranchid' => $activity->id,
            'userid' => $student->id,
        ]));

        $manager->get_player_config($student->id, false);
        $this->assertEquals(1, $DB->count_records('videobranch_attempts', [
            'videobranchid' => $activity->id,
            'userid' => $student->id,
        ]));
    }

    /**
     * The client cannot skip an unresolved decision and complete the activity directly.
     */
    public function test_cannot_skip_unresolved_decision(): void {
        global $DB;

        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $activity = $this->getDataGenerator()->create_module('videobranch', [
            'course' => $course->id,
            'allowseek' => 1,
        ]);
        $graph = $this->create_graph($activity);
        $cm = get_coursemodule_from_instance('videobranch', $activity->id, $course->id, false, MUST_EXIST);
        $manager = new attempt_manager($activity, $cm);

        $attempt = $manager->save_state($student->id, $graph->video1, 100.0, [[0.0, 100.0]]);
        $this->assertEqualsWithDelta(10.0, (float)$attempt->currentposition, 0.001);

        try {
            $manager->choose($student->id, $graph->node2, $graph->option2, $graph->video1, 20.0);
            $this->fail('A later decision must not be reachable before the first one is resolved.');
        } catch (\moodle_exception $exception) {
            $this->assertEquals('invalidplaybackstate', $exception->errorcode);
        }

        $destination = $manager->choose(
            $student->id,
            $graph->node1,
            $graph->option1,
            $graph->video1,
            10.0
        );
        $this->assertEquals('node', $destination['type']);
        $this->assertEquals($graph->node2, $destination['nodeid']);

        $attempt = $manager->save_state($student->id, $graph->video1, 20.0, [[19.75, 20.0]]);
        $this->assertEqualsWithDelta(20.0, (float)$attempt->currentposition, 0.001);

        $destination = $manager->choose(
            $student->id,
            $graph->node2,
            $graph->option2,
            $graph->video1,
            20.0
        );
        $this->assertTrue($destination['completed']);

        $attempt = $DB->get_record('videobranch_attempts', [
            'videobranchid' => $activity->id,
            'userid' => $student->id,
        ], '*', MUST_EXIST);
        $this->assertEquals(1, $attempt->completed);
        $this->assertEquals($graph->ending, $attempt->endingid);
    }

    /**
     * Resume-from-start resets the persisted active path for incomplete attempts.
     */
    public function test_resume_from_start_resets_persisted_path(): void {
        global $DB;

        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $activity = $this->getDataGenerator()->create_module('videobranch', [
            'course' => $course->id,
            'resumeplayback' => 0,
            'allowback' => 1,
        ]);
        $graph = $this->create_graph($activity);
        $cm = get_coursemodule_from_instance('videobranch', $activity->id, $course->id, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        $attemptmanager = new attempt_manager($activity, $cm);

        $attemptmanager->save_state($student->id, $graph->video1, 10.0, [[0.0, 10.0]]);
        $attemptmanager->choose($student->id, $graph->node1, $graph->option1, $graph->video1, 10.0);

        $this->assertEquals(1, $DB->count_records('videobranch_choices', [
            'attemptid' => $DB->get_field('videobranch_attempts', 'id', [
                'videobranchid' => $activity->id,
                'userid' => $student->id,
            ]),
            'active' => 1,
        ]));

        $config = (new branch_manager($activity, $cm, $context))->get_player_config($student->id, false);

        $this->assertEquals($graph->video1, $config['attempt']['videoid']);
        $this->assertEquals(0.0, $config['attempt']['position']);
        $this->assertSame([], $config['attempt']['path']);
        $this->assertEquals(0, $DB->count_records('videobranch_choices', [
            'attemptid' => $config['attempt']['id'],
            'active' => 1,
        ]));
    }

    /**
     * Playback state cannot be switched to an arbitrary video supplied by the client.
     */
    public function test_save_state_rejects_arbitrary_video_switch(): void {
        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $activity = $this->getDataGenerator()->create_module('videobranch', ['course' => $course->id]);
        $graph = $this->create_graph($activity);
        $cm = get_coursemodule_from_instance('videobranch', $activity->id, $course->id, false, MUST_EXIST);
        $manager = new attempt_manager($activity, $cm);

        $manager->get_or_create($student->id);

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('invalidplaybackstate', 'mod_videobranch'));
        $manager->save_state($student->id, $graph->video2, 1.0, [[0.0, 1.0]]);
    }

    /**
     * Creates a small graph with two sequential decisions and one ending.
     *
     * @param \stdClass $activity Activity record.
     * @return \stdClass
     */
    private function create_graph(\stdClass $activity): \stdClass {
        global $DB;

        $now = time();
        $video1 = $DB->insert_record('videobranch_videos', (object)[
            'videobranchid' => $activity->id,
            'name' => 'Start video',
            'sourcetype' => 'url',
            'sourceurl' => 'https://example.com/start.mp4',
            'isstart' => 1,
            'sortorder' => 0,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $video2 = $DB->insert_record('videobranch_videos', (object)[
            'videobranchid' => $activity->id,
            'name' => 'Other video',
            'sourcetype' => 'url',
            'sourceurl' => 'https://example.com/other.mp4',
            'isstart' => 0,
            'sortorder' => 1,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $node1 = $DB->insert_record('videobranch_nodes', (object)[
            'videobranchid' => $activity->id,
            'videoid' => $video1,
            'name' => 'Decision one',
            'question' => 'First?',
            'triggersecond' => 10.0,
            'sortorder' => 0,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $node2 = $DB->insert_record('videobranch_nodes', (object)[
            'videobranchid' => $activity->id,
            'videoid' => $video1,
            'name' => 'Decision two',
            'question' => 'Second?',
            'triggersecond' => 20.0,
            'sortorder' => 1,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $ending = $DB->insert_record('videobranch_endings', (object)[
            'videobranchid' => $activity->id,
            'name' => 'Finished',
            'message' => '<p>Done</p>',
            'sortorder' => 0,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $option1 = $DB->insert_record('videobranch_options', (object)[
            'nodeid' => $node1,
            'label' => 'Continue',
            'targettype' => 'node',
            'targetvideoid' => null,
            'targetsecond' => null,
            'targetnodeid' => $node2,
            'targetendingid' => null,
            'sortorder' => 0,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $option2 = $DB->insert_record('videobranch_options', (object)[
            'nodeid' => $node2,
            'label' => 'Finish',
            'targettype' => 'end',
            'targetvideoid' => null,
            'targetsecond' => null,
            'targetnodeid' => null,
            'targetendingid' => $ending,
            'sortorder' => 0,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        return (object)compact('video1', 'video2', 'node1', 'node2', 'ending', 'option1', 'option2');
    }
}
