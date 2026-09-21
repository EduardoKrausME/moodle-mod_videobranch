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
 * Decision editor form.
 *
 * @package mod_videobranch
 * @copyright 2026 Eduardo Kraus
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class decision_form extends \moodleform {
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
        $mform->addElement('hidden', 'nodeid', 0);
        $mform->setType('nodeid', PARAM_INT);
        $mform->addElement('text', 'name', get_string('decisionname', 'videobranch'), ['size' => 60]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addElement('textarea', 'question', get_string('question', 'videobranch'), ['rows' => 4, 'cols' => 70]);
        $mform->setType('question', PARAM_TEXT);
        $mform->addRule('question', null, 'required', null, 'client');
        $mform->addElement('select', 'videoid', get_string('video', 'videobranch'), $custom['videos']);
        $mform->addElement('text', 'triggersecond', get_string('triggersecond', 'videobranch'), ['size' => 10]);
        $mform->setType('triggersecond', PARAM_FLOAT);
        $mform->addRule('triggersecond', null, 'required', null, 'client');
        $mform->addElement('text', 'sortorder', get_string('sortorder', 'videobranch'), ['size' => 6]);
        $mform->setType('sortorder', PARAM_INT);
        $mform->setDefault('sortorder', 0);
        $this->add_action_buttons(true, get_string('savechanges'));
    }
}
