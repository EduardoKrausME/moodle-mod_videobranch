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
 * Restore structure for Branching Video.
 *
 * @package mod_videobranch
 * @copyright 2026 Eduardo Kraus
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_videobranch_activity_structure_step extends restore_activity_structure_step {
    /** @var array Options waiting until all target mappings exist. */
    private array $pendingoptions = [];
    /** @var array Choices waiting until option mappings exist. */
    private array $pendingchoices = [];

    /**
     * Defines restore paths.
     *
     * @return restore_path_element[]
     */
    protected function define_structure() {
        $paths = [
            new restore_path_element('videobranch', '/activity/videobranch'),
            new restore_path_element('videobranch_video', '/activity/videobranch/videos/video'),
            new restore_path_element('videobranch_node', '/activity/videobranch/nodes/node'),
            new restore_path_element('videobranch_option', '/activity/videobranch/nodes/node/options/option'),
            new restore_path_element('videobranch_ending', '/activity/videobranch/endings/ending'),
        ];
        if ($this->get_setting_value('userinfo')) {
            $paths[] = new restore_path_element('videobranch_attempt', '/activity/videobranch/attempts/attempt');
            $paths[] = new restore_path_element('videobranch_choice', '/activity/videobranch/attempts/attempt/choices/choice');
        }
        return $this->prepare_activity_structure($paths);
    }

    /**
     * process_videobranch
     *
     * @param $data
     * @return void
     * @throws dml_exception
     */
    protected function process_videobranch($data): void {
        global $DB;
        $data = (object)$data;
        $data->course = $this->get_courseid();
        $newid = $DB->insert_record('videobranch', $data);
        $this->apply_activity_instance($newid);
    }

    /**
     * process_videobranch_video
     *
     * @param $data
     * @return void
     * @throws dml_exception
     */
    protected function process_videobranch_video($data): void {
        global $DB;
        $data = (object)$data;
        $oldid = $data->id;
        $data->videobranchid = $this->get_new_parentid('videobranch');
        $newid = $DB->insert_record('videobranch_videos', $data);
        $this->set_mapping('videobranch_video', $oldid, $newid, true);
    }

    /**
     * process_videobranch_node
     *
     * @param $data
     * @return void
     * @throws dml_exception
     */
    protected function process_videobranch_node($data): void {
        global $DB;
        $data = (object)$data;
        $oldid = $data->id;
        $data->videobranchid = $this->get_new_parentid('videobranch');
        $data->videoid = $this->get_mappingid('videobranch_video', $data->videoid);
        $newid = $DB->insert_record('videobranch_nodes', $data);
        $this->set_mapping('videobranch_node', $oldid, $newid);
    }

    /**
     * process_videobranch_option
     *
     * @param $data
     * @return void
     */
    protected function process_videobranch_option($data): void {
        $data = (object)$data;
        $data->nodeid = $this->get_new_parentid('videobranch_node');
        $this->pendingoptions[] = $data;
    }

    /**
     * process_videobranch_ending
     *
     * @param $data
     * @return void
     * @throws dml_exception
     */
    protected function process_videobranch_ending($data): void {
        global $DB;
        $data = (object)$data;
        $oldid = $data->id;
        $data->videobranchid = $this->get_new_parentid('videobranch');
        $newid = $DB->insert_record('videobranch_endings', $data);
        $this->set_mapping('videobranch_ending', $oldid, $newid);
    }

    /**
     * process_videobranch_attempt
     *
     * @param $data
     * @return void
     * @throws dml_exception
     */
    protected function process_videobranch_attempt($data): void {
        global $DB;
        $data = (object)$data;
        $oldid = $data->id;
        $data->videobranchid = $this->get_new_parentid('videobranch');
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->currentvideoid = $data->currentvideoid ? $this->get_mappingid('videobranch_video', $data->currentvideoid) : null;
        $data->endingid = $data->endingid ? $this->get_mappingid('videobranch_ending', $data->endingid) : null;
        $data->watchedjson = '{}';
        $data->pathjson = '[]';
        if (!$data->userid) {
            return;
        }
        $newid = $DB->insert_record('videobranch_attempts', $data);
        $this->set_mapping('videobranch_attempt', $oldid, $newid);
    }

    /**
     * process_videobranch_choice
     *
     * @param $data
     * @return void
     */
    protected function process_videobranch_choice($data): void {
        $data = (object)$data;
        $data->attemptid = $this->get_new_parentid('videobranch_attempt');
        $this->pendingchoices[] = $data;
    }

    /**
     * Restores files after records are mapped.
     *
     * @return void
     */
    protected function after_execute(): void {
        global $DB;
        foreach ($this->pendingoptions as $data) {
            $oldid = $data->id;
            if (!empty($data->targetvideoid)) {
                $data->targetvideoid = $this->get_mappingid('videobranch_video', $data->targetvideoid);
            }
            if (!empty($data->targetnodeid)) {
                $data->targetnodeid = $this->get_mappingid('videobranch_node', $data->targetnodeid);
            }
            if (!empty($data->targetendingid)) {
                $data->targetendingid = $this->get_mappingid('videobranch_ending', $data->targetendingid);
            }
            $newid = $DB->insert_record('videobranch_options', $data);
            $this->set_mapping('videobranch_option', $oldid, $newid);
        }
        foreach ($this->pendingchoices as $data) {
            $data->nodeid = $this->get_mappingid('videobranch_node', $data->nodeid);
            $data->optionid = $this->get_mappingid('videobranch_option', $data->optionid);
            $data->fromvideoid = $data->fromvideoid ? $this->get_mappingid('videobranch_video', $data->fromvideoid) : null;
            if ($data->attemptid && $data->nodeid && $data->optionid) {
                $DB->insert_record('videobranch_choices', $data);
            }
        }
        $this->add_related_files('mod_videobranch', 'intro', null);
        $this->add_related_files('mod_videobranch', 'video', 'videobranch_video');
    }
}
