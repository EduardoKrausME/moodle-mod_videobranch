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
 * Adds or edits a decision node.
 *
 * @package mod_videobranch
 * @copyright 2026 Eduardo Kraus
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once("{$CFG->libdir}/formslib.php");

$id = required_param('id', PARAM_INT);
$nodeid = optional_param('nodeid', 0, PARAM_INT);
$cm = get_coursemodule_from_id('videobranch', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$activity = $DB->get_record('videobranch', ['id' => $cm->instance], '*', MUST_EXIST);
require_course_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/videobranch:manage', $context);

$videos = $DB->get_records_menu('videobranch_videos',
    ['videobranchid' => $activity->id],
    'isstart DESC, sortorder ASC, id ASC', 'id,name');
if (!$videos) {
    redirect(new moodle_url('/mod/videobranch/video.php', ['id' => $cm->id]),
        get_string('addvideofirst', 'videobranch'), null, \core\output\notification::NOTIFY_WARNING);
}
$node = $nodeid ? $DB->get_record('videobranch_nodes', ['id' => $nodeid, 'videobranchid' => $activity->id], '*', MUST_EXIST) : null;

$PAGE->set_url('/mod/videobranch/decision.php', ['id' => $cm->id, 'nodeid' => $nodeid]);
$PAGE->set_title(get_string($node ? 'editdecision' : 'adddecision', 'videobranch'));
$PAGE->set_heading($course->fullname);
$form = new \mod_videobranch\form\decision_form(null, ['videos' => $videos]);
if ($form->is_cancelled()) {
    redirect(new moodle_url('/mod/videobranch/manage.php', ['id' => $cm->id]));
}
if ($data = $form->get_data()) {
    if (!$DB->record_exists('videobranch_videos', ['id' => $data->videoid, 'videobranchid' => $activity->id])) {
        throw new moodle_exception('invalidvideo', 'mod_videobranch');
    }
    $record = (object)[
        'videobranchid' => $activity->id,
        'videoid' => $data->videoid,
        'name' => $data->name,
        'question' => $data->question,
        'triggersecond' => max(0, (float)$data->triggersecond),
        'sortorder' => (int)$data->sortorder,
        'timemodified' => time(),
    ];
    if ($node) {
        $record->id = $node->id;
        $DB->update_record('videobranch_nodes', $record);
    } else {
        $record->timecreated = time();
        $DB->insert_record('videobranch_nodes', $record);
    }
    redirect(new moodle_url('/mod/videobranch/manage.php', ['id' => $cm->id]), get_string('saved', 'videobranch'));
}
$form->set_data($node ? (array)$node + ['id' => $cm->id, 'nodeid' => $node->id] : ['id' => $cm->id, 'nodeid' => 0]);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string($node ? 'editdecision' : 'adddecision', 'videobranch'));
$form->display();
echo $OUTPUT->footer();
