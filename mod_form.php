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
 * Activity settings form.
 *
 * @package   mod_videobranch
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/course/moodleform_mod.php');

/**
 * Branching Video activity settings form.
 */
class mod_videobranch_mod_form extends moodleform_mod {
    /**
     * Defines activity settings.
     *
     * @return void
     */
    public function definition(): void {
        $mform = $this->_form;

        $mform->addElement('header', 'general', get_string('general', 'form'));
        $mform->addElement('text', 'name', get_string('videobranchname', 'videobranch'), ['size' => 64]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $this->standard_intro_elements();

        $mform->addElement('header', 'playbacksettings', get_string('playbacksettings', 'videobranch'));
        $mform->addElement('select', 'resumeplayback', get_string('resumeplayback', 'videobranch'), [
            1 => get_string('resumeautomatic', 'videobranch'),
            2 => get_string('resumeask', 'videobranch'),
            0 => get_string('resumefromstart', 'videobranch'),
        ]);
        $mform->setDefault('resumeplayback', 1);
        $mform->addElement('selectyesno', 'allowseek', get_string('allowseek', 'videobranch'));
        $mform->setDefault('allowseek', 1);
        $mform->addElement('selectyesno', 'allowback', get_string('allowback', 'videobranch'));
        $mform->setDefault('allowback', 0);
        $mform->addElement('selectyesno', 'showpath', get_string('showpath', 'videobranch'));
        $mform->setDefault('showpath', 1);

        $mform->addElement('static', 'managehint', '', get_string('managehint', 'videobranch'));

        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    /**
     * Adds custom completion rule.
     *
     * @return array
     */
    public function add_completion_rules(): array {
        $mform = $this->_form;
        $field = 'completionending_videobranch';
        $mform->addElement('advcheckbox', $field, get_string('completionending', 'videobranch'));
        $mform->setDefault($field, 1);
        return [$field];
    }

    /**
     * Reports whether the custom completion rule is enabled.
     *
     * @param stdClass $data Submitted form data.
     * @return bool
     */
    public function completion_rule_enabled($data): bool {
        if (is_array($data)) {
            return !empty($data['completionending_videobranch']);
        }
        return !empty($data->completionending_videobranch);
    }

    /**
     * Prepares custom completion field.
     *
     * @param array $defaultvalues Default values.
     * @return void
     */
    public function data_preprocessing(&$defaultvalues): void {
        if (array_key_exists('completionending', $defaultvalues)) {
            $defaultvalues['completionending_videobranch'] = $defaultvalues['completionending'];
        }
    }

    /**
     * Returns form data with Moodle completion suffix removed.
     *
     * @return stdClass|null
     */
    public function get_data() {
        $data = parent::get_data();
        if (!$data) {
            return $data;
        }
        if (property_exists($data, 'completionending_videobranch')) {
            $data->completionending = (int)$data->completionending_videobranch;
            unset($data->completionending_videobranch);
        }
        return $data;
    }
}
