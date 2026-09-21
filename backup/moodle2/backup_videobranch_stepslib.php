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
 * Backup structure for Branching Video.
 *
 * @package mod_videobranch
 * @copyright 2026 Eduardo Kraus
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_videobranch_activity_structure_step extends backup_activity_structure_step {
    /**
     * Defines backup structure.
     *
     * @return backup_nested_element
     */
    protected function define_structure() {
        $userinfo = $this->get_setting_value('userinfo');
        $activity = new backup_nested_element('videobranch', ['id'], [
            'name', 'intro', 'introformat', 'resumeplayback', 'allowseek', 'allowback', 'showpath',
            'completionending', 'timecreated', 'timemodified',
        ]);
        $videos = new backup_nested_element('videos');
        $video = new backup_nested_element('video', ['id'], [
            'name', 'sourcetype', 'sourceurl', 'isstart', 'sortorder', 'timecreated', 'timemodified',
        ]);
        $nodes = new backup_nested_element('nodes');
        $node = new backup_nested_element('node', ['id'], [
            'videoid', 'name', 'question', 'triggersecond', 'sortorder', 'timecreated', 'timemodified',
        ]);
        $options = new backup_nested_element('options');
        $option = new backup_nested_element('option', ['id'], [
            'label', 'targettype', 'targetvideoid', 'targetsecond', 'targetnodeid', 'targetendingid',
            'sortorder', 'timecreated', 'timemodified',
        ]);
        $endings = new backup_nested_element('endings');
        $ending = new backup_nested_element('ending', ['id'], [
            'name', 'message', 'sortorder', 'timecreated', 'timemodified',
        ]);
        $attempts = new backup_nested_element('attempts');
        $attempt = new backup_nested_element('attempt', ['id'], [
            'userid', 'currentvideoid', 'currentposition', 'watchedjson', 'pathjson', 'endingid', 'completed',
            'timecreated', 'timemodified', 'timecompleted',
        ]);
        $choices = new backup_nested_element('choices');
        $choice = new backup_nested_element('choice', ['id'], [
            'nodeid', 'optionid', 'sequence', 'active', 'fromvideoid', 'fromposition', 'timecreated',
        ]);

        $activity->add_child($videos);
        $videos->add_child($video);
        $activity->add_child($nodes);
        $nodes->add_child($node);
        $node->add_child($options);
        $options->add_child($option);
        $activity->add_child($endings);
        $endings->add_child($ending);
        $activity->add_child($attempts);
        $attempts->add_child($attempt);
        $attempt->add_child($choices);
        $choices->add_child($choice);

        $activity->set_source_table('videobranch', ['id' => backup::VAR_ACTIVITYID]);
        $video->set_source_table('videobranch_videos', ['videobranchid' => backup::VAR_PARENTID]);
        $node->set_source_table('videobranch_nodes', ['videobranchid' => backup::VAR_PARENTID]);
        $option->set_source_table('videobranch_options', ['nodeid' => backup::VAR_PARENTID]);
        $ending->set_source_table('videobranch_endings', ['videobranchid' => backup::VAR_PARENTID]);
        if ($userinfo) {
            $attempt->set_source_table('videobranch_attempts', ['videobranchid' => backup::VAR_PARENTID]);
            $choice->set_source_table('videobranch_choices', ['attemptid' => backup::VAR_PARENTID]);
        }

        $attempt->annotate_ids('user', 'userid');
        $activity->annotate_files('mod_videobranch', 'intro', null);
        $video->annotate_files('mod_videobranch', 'video', 'id');

        return $this->prepare_activity_structure($activity);
    }
}
