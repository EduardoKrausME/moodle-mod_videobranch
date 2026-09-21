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
 * Decision option editor form.
 *
 * @package mod_videobranch
 * @copyright 2026 Eduardo Kraus
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class option_form extends \moodleform {
    /**
     * Defines fields.
     *
     * @return void
     */
    public function definition(): void {
        $mform = $this->_form;
        $custom = $this->_customdata;
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'optionid', 0);
        $mform->setType('optionid', PARAM_INT);
        $mform->addElement('hidden', 'nodeid', $custom['nodeid']);
        $mform->setType('nodeid', PARAM_INT);
        $mform->addElement('text', 'label', get_string('optionlabel', 'videobranch'), ['size' => 70]);
        $mform->setType('label', PARAM_TEXT);
        $mform->addRule('label', null, 'required', null, 'client');
        $mform->addElement('select', 'targettype', get_string('targettype', 'videobranch'), [
            'time' => get_string('target_time', 'videobranch'),
            'video' => get_string('target_video', 'videobranch'),
            'node' => get_string('target_node', 'videobranch'),
            'end' => get_string('target_end', 'videobranch'),
        ]);
        $mform->addElement('select', 'targetvideoid', get_string('targetvideo', 'videobranch'), $custom['videos']);
        $mform->hideIf('targetvideoid', 'targettype', 'in', ['node', 'end']);
        $mform->addElement('text', 'targetsecond', get_string('targetsecond', 'videobranch'), ['size' => 10]);
        $mform->setType('targetsecond', PARAM_FLOAT);
        $mform->hideIf('targetsecond', 'targettype', 'in', ['node', 'end']);
        $mform->addElement('select', 'targetnodeid', get_string('targetnode', 'videobranch'), $custom['nodes']);
        $mform->hideIf('targetnodeid', 'targettype', 'neq', 'node');
        $mform->addElement('select', 'targetendingid', get_string('targetending', 'videobranch'), $custom['endings']);
        $mform->hideIf('targetendingid', 'targettype', 'neq', 'end');
        $mform->addElement('text', 'sortorder', get_string('sortorder', 'videobranch'), ['size' => 6]);
        $mform->setType('sortorder', PARAM_INT);
        $mform->setDefault('sortorder', 0);
        $this->add_action_buttons(true, get_string('savechanges'));
    }

    /**
     * Validates target fields.
     *
     * @param array $data Values.
     * @param array $files Files.
     * @return array
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        if (($data['targettype'] ?? '') === 'node' && empty($data['targetnodeid'])) {
            $errors['targetnodeid'] = get_string('required');
        }
        if (($data['targettype'] ?? '') === 'end' && empty($data['targetendingid'])) {
            $errors['targetendingid'] = get_string('required');
        }
        if (in_array(($data['targettype'] ?? ''), ['time', 'video'], true) && empty($data['targetvideoid'])) {
            $errors['targetvideoid'] = get_string('required');
        }
        return $errors;
    }
}
