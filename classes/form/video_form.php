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

namespace mod_videobranch\form;

/**
 * Video editor form.
 *
 * @package mod_videobranch
 * @copyright 2026 Eduardo Kraus
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class video_form extends \moodleform {
    /**
     * Defines fields.
     *
     * @return void
     */
    public function definition(): void {
        $mform = $this->_form;
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'videoid', 0);
        $mform->setType('videoid', PARAM_INT);
        $mform->addElement('text', 'name', get_string('videoname', 'videobranch'), ['size' => 60]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addElement('select', 'sourcetype', get_string('sourcetype', 'videobranch'), [
            'upload' => get_string('source_upload', 'videobranch'),
            'url' => get_string('source_url', 'videobranch'),
            'youtube' => get_string('source_youtube', 'videobranch'),
            'vimeo' => get_string('source_vimeo', 'videobranch'),
        ]);
        $mform->addElement('url', 'sourceurl', get_string('sourceurl', 'videobranch'), ['size' => 70], ['usefilepicker' => false]);
        $mform->hideIf('sourceurl', 'sourcetype', 'eq', 'upload');
        $mform->addElement('filemanager', 'videofile', get_string('videofile', 'videobranch'), null, [
            'subdirs' => 0,
            'maxfiles' => 1,
            'accepted_types' => ['video'],
        ]);
        $mform->hideIf('videofile', 'sourcetype', 'neq', 'upload');
        $mform->addElement('advcheckbox', 'isstart', get_string('startvideo', 'videobranch'));
        $mform->addElement('text', 'sortorder', get_string('sortorder', 'videobranch'), ['size' => 6]);
        $mform->setType('sortorder', PARAM_INT);
        $mform->setDefault('sortorder', 0);
        $this->add_action_buttons(true, get_string('savechanges'));
    }

    /**
     * Validates form values.
     *
     * @param array $data Values.
     * @param array $files Files.
     * @return array
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        if (($data['sourcetype'] ?? '') !== 'upload' && empty($data['sourceurl'])) {
            $errors['sourceurl'] = get_string('required');
        }
        if (($data['sourcetype'] ?? '') === 'upload') {
            if (empty($data['videofile'])) {
                $errors['videofile'] = get_string('required');
            } else {
                $info = file_get_draft_area_info((int)$data['videofile']);
                if (empty($info['filecount'])) {
                    $errors['videofile'] = get_string('required');
                }
            }
        }
        return $errors;
    }
}
