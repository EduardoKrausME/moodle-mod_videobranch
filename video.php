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
 * Adds or edits a video.
 *
 * @package mod_videobranch
 * @copyright 2026 Eduardo Kraus
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once("{$CFG->libdir}/formslib.php");

$id = required_param('id', PARAM_INT);
$videoid = optional_param('videoid', 0, PARAM_INT);
$cm = get_coursemodule_from_id('videobranch', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$activity = $DB->get_record('videobranch', ['id' => $cm->instance], '*', MUST_EXIST);
require_course_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/videobranch:manage', $context);

$video = null;
if ($videoid) {
    $video = $DB->get_record('videobranch_videos', ['id' => $videoid, 'videobranchid' => $activity->id], '*', MUST_EXIST);
}

$PAGE->set_url('/mod/videobranch/video.php', ['id' => $cm->id, 'videoid' => $videoid]);
$PAGE->set_title(get_string($video ? 'editvideo' : 'addvideo', 'videobranch'));
$PAGE->set_heading($course->fullname);

$form = new \mod_videobranch\form\video_form();
if ($form->is_cancelled()) {
    redirect(new moodle_url('/mod/videobranch/manage.php', ['id' => $cm->id]));
}
if ($data = $form->get_data()) {
    $now = time();
    $record = (object)[
        'videobranchid' => $activity->id,
        'name' => $data->name,
        'sourcetype' => $data->sourcetype,
        'sourceurl' => $data->sourcetype === 'upload' ? null : clean_param((string)$data->sourceurl, PARAM_URL),
        'isstart' => empty($data->isstart) ? 0 : 1,
        'sortorder' => (int)$data->sortorder,
        'timemodified' => $now,
    ];
    if ($video) {
        $record->id = $video->id;
        $DB->update_record('videobranch_videos', $record);
        $savedid = $video->id;
    } else {
        $record->timecreated = $now;
        if (!$DB->record_exists('videobranch_videos', ['videobranchid' => $activity->id])) {
            $record->isstart = 1;
        }
        $savedid = $DB->insert_record('videobranch_videos', $record);
    }
    if (!empty($record->isstart)) {
        $DB->set_field_select('videobranch_videos', 'isstart', 0,
            'videobranchid = :activityid AND id <> :videoid', ['activityid' => $activity->id, 'videoid' => $savedid]);
    }
    if ($data->sourcetype === 'upload') {
        file_save_draft_area_files($data->videofile, $context->id, 'mod_videobranch', 'video', $savedid, [
            'subdirs' => 0,
            'maxfiles' => 1,
            'accepted_types' => ['video'],
        ]);
    } else {
        get_file_storage()->delete_area_files($context->id, 'mod_videobranch', 'video', $savedid);
    }
    redirect(new moodle_url('/mod/videobranch/manage.php', ['id' => $cm->id]), get_string('saved', 'videobranch'));
}

$defaults = ['id' => $cm->id, 'videoid' => $videoid];
if ($video) {
    $defaults += (array)$video;
    $draftid = file_get_submitted_draft_itemid('videofile');
    file_prepare_draft_area($draftid, $context->id, 'mod_videobranch', 'video', $video->id, [
        'subdirs' => 0, 'maxfiles' => 1, 'accepted_types' => ['video'],
    ]);
    $defaults['videofile'] = $draftid;
}
$form->set_data($defaults);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string($video ? 'editvideo' : 'addvideo', 'videobranch'));
$form->display();
echo $OUTPUT->footer();
