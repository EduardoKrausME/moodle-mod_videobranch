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
 * Learner view for Branching Video.
 *
 * @package mod_videobranch
 * @copyright 2026 Eduardo Kraus
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$id = required_param('id', PARAM_INT);
$cm = get_coursemodule_from_id('videobranch', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$activity = $DB->get_record('videobranch', ['id' => $cm->instance], '*', MUST_EXIST);
require_course_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/videobranch:view', $context);

$PAGE->set_url('/mod/videobranch/view.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($activity->name));
$PAGE->set_heading($course->fullname);
$PAGE->set_context($context);

$event = \mod_videobranch\event\course_module_viewed::create([
    'objectid' => $activity->id,
    'context' => $context,
]);
$event->add_record_snapshot('course_modules', $cm);
$event->add_record_snapshot('videobranch', $activity);
$event->trigger();
$completion = new completion_info($course);
$completion->set_module_viewed($cm);

$engine = new \mod_videobranch\branch_manager($activity, $cm, $context);
$config = $engine->get_player_config($USER->id);

if (empty($config['videos'])) {
    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('novideosconfigured', 'videobranch'), 'warning');
    if (has_capability('mod/videobranch:manage', $context)) {
        echo $OUTPUT->single_button(new moodle_url('/mod/videobranch/manage.php', ['id' => $cm->id]),
            get_string('managepaths', 'videobranch'));
    }
    echo $OUTPUT->footer();
    exit;
}

$templatedata = [
    'name' => format_string($activity->name),
    'intro' => format_module_intro('videobranch', $activity, $cm->id),
    'hasintro' => trim((string)$activity->intro) !== '',
    'configjson' => json_encode($config, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT),
    'canmanage' => has_capability('mod/videobranch:manage', $context),
    'manageurl' => (string)new moodle_url('/mod/videobranch/manage.php', ['id' => $cm->id]),
    'canreport' => has_capability('mod/videobranch:viewreport', $context),
    'reporturl' => (string)new moodle_url('/mod/videobranch/report/report.php', ['id' => $cm->id]),
];

$PAGE->requires->strings_for_js([
    'chooseoption', 'resumequestion', 'resumeyes', 'resumeno', 'decisionrequired', 'pathheading',
    'endingreached', 'savingerror', 'invalidvideo', 'backnotallowed',
], 'videobranch');
$PAGE->requires->js_call_amd('mod_videobranch/player', 'init');

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('mod_videobranch/view', $templatedata);
echo $OUTPUT->footer();
