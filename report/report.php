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
 * Aggregate Branching Video report.
 *
 * @package mod_videobranch
 * @copyright 2026 Eduardo Kraus
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

$id = required_param('id', PARAM_INT);
$cm = get_coursemodule_from_id('videobranch', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$activity = $DB->get_record('videobranch', ['id' => $cm->instance], '*', MUST_EXIST);
require_course_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/videobranch:viewreport', $context);

$PAGE->set_url('/mod/videobranch/report/report.php', ['id' => $cm->id]);
$PAGE->set_title(get_string('report', 'videobranch'));
$PAGE->set_heading($course->fullname);
$data = (new \mod_videobranch\report_service($activity, $cm))->aggregate();
$data['name'] = format_string($activity->name);
$data['manageurl'] = (string)new moodle_url('/mod/videobranch/manage.php', ['id' => $cm->id]);

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('mod_videobranch/report', $data);
echo $OUTPUT->footer();
