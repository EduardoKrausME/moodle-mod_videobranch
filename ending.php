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
 * Adds or edits an ending.
 *
 * @package mod_videobranch
 * @copyright 2026 Eduardo Kraus
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once("{$CFG->libdir}/formslib.php");

$id = required_param('id', PARAM_INT);
$endingid = optional_param('endingid', 0, PARAM_INT);
$cm = get_coursemodule_from_id('videobranch', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$activity = $DB->get_record('videobranch', ['id' => $cm->instance], '*', MUST_EXIST);
require_course_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/videobranch:manage', $context);
$ending = $endingid ? $DB->get_record('videobranch_endings',
    ['id' => $endingid, 'videobranchid' => $activity->id], '*', MUST_EXIST) : null;

$PAGE->set_url('/mod/videobranch/ending.php', ['id' => $cm->id, 'endingid' => $endingid]);
$PAGE->set_title(get_string($ending ? 'editending' : 'addending', 'videobranch'));
$PAGE->set_heading($course->fullname);
$form = new \mod_videobranch\form\ending_form();
if ($form->is_cancelled()) {
    redirect(new moodle_url('/mod/videobranch/manage.php', ['id' => $cm->id]));
}
if ($data = $form->get_data()) {
    $record = (object)[
        'videobranchid' => $activity->id,
        'name' => $data->name,
        'message' => $data->message_editor['text'],
        'sortorder' => (int)$data->sortorder,
        'timemodified' => time(),
    ];
    if ($ending) {
        $record->id = $ending->id;
        $DB->update_record('videobranch_endings', $record);
    } else {
        $record->timecreated = time();
        $DB->insert_record('videobranch_endings', $record);
    }
    redirect(new moodle_url('/mod/videobranch/manage.php', ['id' => $cm->id]), get_string('saved', 'videobranch'));
}
$defaults = ['id' => $cm->id, 'endingid' => $endingid];
if ($ending) {
    $defaults += (array)$ending;
    $defaults['message_editor'] = ['text' => $ending->message, 'format' => FORMAT_HTML];
}
$form->set_data($defaults);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string($ending ? 'editending' : 'addending', 'videobranch'));
$form->display();
echo $OUTPUT->footer();
