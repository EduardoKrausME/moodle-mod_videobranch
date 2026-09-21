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

namespace mod_videobranch\completion;

use core_completion\activity_custom_completion;

/**
 * Custom completion based on reaching a valid ending.
 *
 * @package mod_videobranch
 * @copyright 2026 Eduardo Kraus
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class custom_completion extends activity_custom_completion {
    /**
     * Returns completion state for the ending rule.
     *
     * @param string $rule Rule name.
     * @return int
     */
    public function get_state(string $rule): int {
        global $DB;
        $this->validate_rule($rule);
        $activity = $DB->get_record('videobranch', ['id' => $this->cm->instance], 'id,completionending', MUST_EXIST);
        if (empty($activity->completionending)) {
            return COMPLETION_INCOMPLETE;
        }
        return $DB->record_exists('videobranch_attempts', [
            'videobranchid' => $activity->id,
            'userid' => $this->userid,
            'completed' => 1,
        ]) ? COMPLETION_COMPLETE : COMPLETION_INCOMPLETE;
    }

    /**
     * Lists available custom completion rules.
     *
     * @return array
     */
    public static function get_defined_custom_rules(): array {
        return ['completionending'];
    }

    /**
     * Describes active custom completion rules.
     *
     * @return array
     */
    public function get_custom_rule_descriptions(): array {
        return ['completionending' => get_string('completionending', 'videobranch')];
    }

    /**
     * Sort order for rules.
     *
     * @return array
     */
    public function get_sort_order(): array {
        return ['completionview', 'completionending'];
    }
}
