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
 * Adds or edits a decision option.
 *
 * @package mod_videobranch
 * @copyright 2026 Eduardo Kraus
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once("{$CFG->libdir}/formslib.php");

$id = required_param('id', PARAM_INT);
$nodeid = required_param('nodeid', PARAM_INT);
$optionid = optional_param('optionid', 0, PARAM_INT);
$cm = get_coursemodule_from_id('videobranch', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$activity = $DB->get_record('videobranch', ['id' => $cm->instance], '*', MUST_EXIST);
require_course_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/videobranch:manage', $context);
$node = $DB->get_record('videobranch_nodes',
    ['id' => $nodeid, 'videobranchid' => $activity->id], '*', MUST_EXIST);
$option = $optionid ? $DB->get_record('videobranch_options',
    ['id' => $optionid, 'nodeid' => $node->id], '*', MUST_EXIST) : null;
$videos = $DB->get_records_menu('videobranch_videos',
    ['videobranchid' => $activity->id], 'isstart DESC, sortorder ASC, id ASC', 'id,name');
$nodes = $DB->get_records_menu('videobranch_nodes',
    ['videobranchid' => $activity->id], 'name ASC', 'id,name');
$endings = $DB->get_records_menu('videobranch_endings',
    ['videobranchid' => $activity->id], 'sortorder ASC, id ASC', 'id,name');

$PAGE->set_url('/mod/videobranch/option.php', ['id' => $cm->id, 'nodeid' => $nodeid, 'optionid' => $optionid]);
$PAGE->set_title(get_string($option ? 'editoption' : 'addoption', 'videobranch'));
$PAGE->set_heading($course->fullname);
$form = new \mod_videobranch\form\option_form(null, [
    'nodeid' => $node->id,
    'videos' => ['' => get_string('choose')] + $videos,
    'nodes' => ['' => get_string('choose')] + $nodes,
    'endings' => ['' => get_string('choose')] + $endings,
]);
if ($form->is_cancelled()) {
    redirect(new moodle_url('/mod/videobranch/manage.php', ['id' => $cm->id]));
}
if ($data = $form->get_data()) {
    $targetvideoid = in_array($data->targettype, ['time', 'video'], true) ? (int)$data->targetvideoid : null;
    $targetnodeid = $data->targettype === 'node' ? (int)$data->targetnodeid : null;
    $targetendingid = $data->targettype === 'end' ? (int)$data->targetendingid : null;
    if ($targetvideoid && !$DB->record_exists('videobranch_videos', ['id' => $targetvideoid, 'videobranchid' => $activity->id])) {
        throw new moodle_exception('invalidvideo', 'mod_videobranch');
    }
    if ($targetnodeid && !$DB->record_exists('videobranch_nodes',
            ['id' => $targetnodeid, 'videobranchid' => $activity->id])) {
        throw new moodle_exception('invaliddecision', 'mod_videobranch');
    }
    if ($targetendingid && !$DB->record_exists('videobranch_endings',
            ['id' => $targetendingid, 'videobranchid' => $activity->id])) {
        throw new moodle_exception('invalidending', 'mod_videobranch');
    }
    $record = (object)[
        'nodeid' => $node->id,
        'label' => $data->label,
        'targettype' => $data->targettype,
        'targetvideoid' => $targetvideoid,
        'targetsecond' => in_array($data->targettype, ['time', 'video'], true) ? max(0, (float)$data->targetsecond) : null,
        'targetnodeid' => $targetnodeid,
        'targetendingid' => $targetendingid,
        'sortorder' => (int)$data->sortorder,
        'timemodified' => time(),
    ];
    if ($option) {
        $record->id = $option->id;
        $DB->update_record('videobranch_options', $record);
    } else {
        $record->timecreated = time();
        $DB->insert_record('videobranch_options', $record);
    }
    redirect(new moodle_url('/mod/videobranch/manage.php', ['id' => $cm->id]), get_string('saved', 'videobranch'));
}
$form->set_data($option ? (array)$option + ['id' => $cm->id, 'nodeid' => $node->id, 'optionid' => $option->id] : [
    'id' => $cm->id,
    'nodeid' => $node->id,
    'optionid' => 0,
    'targettype' => 'time',
    'targetvideoid' => $node->videoid,
]);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string($option ? 'editoption' : 'addoption', 'videobranch'));
echo $OUTPUT->notification(get_string('optionfor', 'videobranch', format_string($node->name)), 'info');
$form->display();
echo $OUTPUT->footer();
