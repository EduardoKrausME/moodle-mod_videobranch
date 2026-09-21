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

namespace mod_videobranch\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Saves learner playback state.
 *
 * @package mod_videobranch
 * @copyright 2026 Eduardo Kraus
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class save_state extends external_api {
    /**
     * Parameters definition.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'videoid' => new external_value(PARAM_INT, 'Current video id'),
            'position' => new external_value(PARAM_FLOAT, 'Current position'),
            'segments' => new external_multiple_structure(new external_single_structure([
                'start' => new external_value(PARAM_FLOAT, 'Segment start'),
                'end' => new external_value(PARAM_FLOAT, 'Segment end'),
            ])),
        ]);
    }

    /**
     * Saves learner playback state.
     *
     * @param int $cmid Course module id.
     * @param int $videoid Video id.
     * @param float $position Current position.
     * @param array $segments Watched segments.
     * @return array
     */
    public static function execute(int $cmid, int $videoid, float $position, array $segments): array {
        global $DB, $USER;
        $params = self::validate_parameters(self::execute_parameters(), compact('cmid', 'videoid', 'position', 'segments'));
        $cm = get_coursemodule_from_id('videobranch', $params['cmid'], 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/videobranch:view', $context);
        $activity = $DB->get_record('videobranch', ['id' => $cm->instance], '*', MUST_EXIST);
        $normalised = [];
        foreach ($params['segments'] as $segment) {
            $normalised[] = [(float)$segment['start'], (float)$segment['end']];
        }
        $manager = new \mod_videobranch\attempt_manager($activity, $cm);
        $attempt = $manager->save_state($USER->id, $params['videoid'], $params['position'], $normalised);
        return ['success' => true, 'timemodified' => (int)$attempt->timemodified];
    }

    /**
     * Return definition.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether state was saved'),
            'timemodified' => new external_value(PARAM_INT, 'Modification timestamp'),
        ]);
    }
}
