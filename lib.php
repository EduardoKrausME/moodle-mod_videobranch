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
 * Core callbacks for Branching Video.
 *
 * @package   mod_videobranch
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Declares Moodle features supported by the module.
 *
 * @param string $feature Feature constant.
 * @return bool|string|null
 */
function videobranch_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_ARCHETYPE:
            return MOD_ARCHETYPE_RESOURCE;
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        case FEATURE_COMPLETION_HAS_RULES:
            return true;
        case FEATURE_GROUPS:
            return false;
        case FEATURE_GROUPINGS:
            return false;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_MOD_PURPOSE:
            return MOD_PURPOSE_CONTENT;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        default:
            return null;
    }
}

/**
 * Adds a Branching Video instance.
 *
 * @param stdClass $data Activity data.
 * @param mod_videobranch_mod_form|null $mform Form instance.
 * @return int New instance id.
 */
function videobranch_add_instance(stdClass $data, ?mod_videobranch_mod_form $mform = null): int {
    global $DB;
    $data->timecreated = time();
    $data->timemodified = time();
    if (!isset($data->completionending)) {
        $data->completionending = 1;
    }
    return $DB->insert_record('videobranch', $data);
}

/**
 * Updates a Branching Video instance.
 *
 * @param stdClass $data Activity data.
 * @param mod_videobranch_mod_form|null $mform Form instance.
 * @return bool
 */
function videobranch_update_instance(stdClass $data, ?mod_videobranch_mod_form $mform = null): bool {
    global $DB;
    $data->id = $data->instance;
    $data->timemodified = time();
    return $DB->update_record('videobranch', $data);
}

/**
 * Deletes a Branching Video instance and all dependent records.
 *
 * @param int $id Activity instance id.
 * @return bool
 */
function videobranch_delete_instance(int $id): bool {
    global $DB;
    $activity = $DB->get_record('videobranch', ['id' => $id]);
    if (!$activity) {
        return false;
    }

    $cm = get_coursemodule_from_instance('videobranch', $id, $activity->course, false, IGNORE_MISSING);
    $context = $cm ? context_module::instance($cm->id) : null;

    $transaction = $DB->start_delegated_transaction();
    $attemptids = $DB->get_fieldset_select('videobranch_attempts', 'id', 'videobranchid = :id', ['id' => $id]);
    if ($attemptids) {
        [$insql, $params] = $DB->get_in_or_equal($attemptids, SQL_PARAMS_NAMED, 'attempt');
        $DB->delete_records_select('videobranch_choices', "attemptid {$insql}", $params);
    }
    $nodeids = $DB->get_fieldset_select('videobranch_nodes', 'id', 'videobranchid = :id', ['id' => $id]);
    if ($nodeids) {
        [$insql, $params] = $DB->get_in_or_equal($nodeids, SQL_PARAMS_NAMED, 'node');
        $DB->delete_records_select('videobranch_options', "nodeid {$insql}", $params);
    }
    $DB->delete_records('videobranch_attempts', ['videobranchid' => $id]);
    $DB->delete_records('videobranch_nodes', ['videobranchid' => $id]);
    $DB->delete_records('videobranch_endings', ['videobranchid' => $id]);
    $DB->delete_records('videobranch_videos', ['videobranchid' => $id]);
    $DB->delete_records('videobranch', ['id' => $id]);
    $transaction->allow_commit();

    if ($context) {
        get_file_storage()->delete_area_files($context->id, 'mod_videobranch');
    }
    return true;
}

/**
 * Returns course-module information cached by Moodle.
 *
 * @param stdClass $coursemodule Course module record.
 * @return cached_cm_info|null
 */
function videobranch_get_coursemodule_info($coursemodule): ?cached_cm_info {
    global $DB;
    $activity = $DB->get_record('videobranch', ['id' => $coursemodule->instance], 'id,name,intro,introformat');
    if (!$activity) {
        return null;
    }
    $info = new cached_cm_info();
    $info->name = $activity->name;
    if ($coursemodule->showdescription) {
        $info->content = format_module_intro('videobranch', $activity, $coursemodule->id, false);
    }
    return $info;
}

/**
 * Serves uploaded branching video files.
 *
 * @param stdClass $course Course record.
 * @param stdClass $cm Course module record.
 * @param context $context Context.
 * @param string $filearea File area.
 * @param array $args Path arguments.
 * @param bool $forcedownload Force download.
 * @param array $options File options.
 * @return bool
 */
function mod_videobranch_pluginfile($course, $cm, $context, string $filearea, array $args,
                                    bool $forcedownload, array $options = []): bool {
    global $DB;
    if ($context->contextlevel !== CONTEXT_MODULE || $filearea !== 'video') {
        return false;
    }
    require_login($course, true, $cm);
    require_capability('mod/videobranch:view', $context);
    $itemid = (int)array_shift($args);
    $video = $DB->get_record('videobranch_videos', ['id' => $itemid], 'id,videobranchid', MUST_EXIST);
    if ((int)$video->videobranchid !== (int)$cm->instance) {
        return false;
    }
    $filename = array_pop($args);
    $filepath = '/' . ($args ? implode('/', $args) . '/' : '');
    $file = get_file_storage()->get_file($context->id, 'mod_videobranch', 'video', $itemid, $filepath, $filename);
    if (!$file || $file->is_directory()) {
        return false;
    }
    send_stored_file($file, 0, 0, $forcedownload, $options);
}

/**
 * Returns file areas exposed by the module.
 *
 * @param stdClass $course Course record.
 * @param stdClass $cm Course module record.
 * @param context $context Context.
 * @return array
 */
function videobranch_get_file_areas($course, $cm, $context): array {
    return ['video' => get_string('videofile', 'videobranch')];
}

/**
 * Extends activity navigation with management and report links.
 *
 * @param settings_navigation $settingsnav Settings navigation.
 * @param navigation_node $node Activity node.
 * @return void
 */
function videobranch_extend_settings_navigation(settings_navigation $settingsnav, navigation_node $node): void {
    global $PAGE;
    if (!$PAGE->cm) {
        return;
    }
    $context = context_module::instance($PAGE->cm->id);
    if (has_capability('mod/videobranch:manage', $context)) {
        $node->add(get_string('managepaths', 'videobranch'),
            new moodle_url('/mod/videobranch/manage.php', ['id' => $PAGE->cm->id]));
    }
    if (has_capability('mod/videobranch:viewreport', $context)) {
        $node->add(get_string('report', 'videobranch'),
            new moodle_url('/mod/videobranch/report/report.php', ['id' => $PAGE->cm->id]));
    }
}
