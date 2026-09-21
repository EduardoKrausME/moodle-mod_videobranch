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
 * Records one branching choice.
 *
 * @package mod_videobranch
 * @copyright 2026 Eduardo Kraus
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class choose_option extends external_api {
    /**
     * Parameters definition.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'nodeid' => new external_value(PARAM_INT, 'Decision node id'),
            'optionid' => new external_value(PARAM_INT, 'Option id'),
            'videoid' => new external_value(PARAM_INT, 'Current video id'),
            'position' => new external_value(PARAM_FLOAT, 'Current video position'),
        ]);
    }

    /**
     * Records the option and resolves destination.
     *
     * @param int $cmid Course module id.
     * @param int $nodeid Decision id.
     * @param int $optionid Option id.
     * @param int $videoid Current video id.
     * @param float $position Current position.
     * @return array
     */
    public static function execute(int $cmid, int $nodeid, int $optionid, int $videoid, float $position): array {
        global $DB, $USER;
        $params = self::validate_parameters(self::execute_parameters(),
            compact('cmid', 'nodeid', 'optionid', 'videoid', 'position'));
        $cm = get_coursemodule_from_id('videobranch', $params['cmid'], 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/videobranch:view', $context);
        $activity = $DB->get_record('videobranch', ['id' => $cm->instance], '*', MUST_EXIST);
        return (new \mod_videobranch\attempt_manager($activity, $cm))->choose(
            $USER->id, $params['nodeid'], $params['optionid'], $params['videoid'], $params['position']
        );
    }

    /**
     * Path entry structure.
     *
     * @return external_single_structure
     */
    private static function path_structure(): external_single_structure {
        return new external_single_structure([
            'choiceid' => new external_value(PARAM_INT, 'Choice record id'),
            'sequence' => new external_value(PARAM_INT, 'Sequence'),
            'nodeid' => new external_value(PARAM_INT, 'Decision id'),
            'nodename' => new external_value(PARAM_TEXT, 'Decision name'),
            'question' => new external_value(PARAM_TEXT, 'Decision question'),
            'optionid' => new external_value(PARAM_INT, 'Option id'),
            'optionlabel' => new external_value(PARAM_TEXT, 'Option label'),
            'videoid' => new external_value(PARAM_INT, 'Video id'),
            'second' => new external_value(PARAM_FLOAT, 'Decision time'),
            'timecreated' => new external_value(PARAM_INT, 'Timestamp'),
        ]);
    }

    /**
     * Return definition.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'type' => new external_value(PARAM_ALPHA, 'Destination type'),
            'videoid' => new external_value(PARAM_INT, 'Destination video id'),
            'second' => new external_value(PARAM_FLOAT, 'Destination time'),
            'nodeid' => new external_value(PARAM_INT, 'Destination decision id'),
            'endingid' => new external_value(PARAM_INT, 'Ending id'),
            'endingname' => new external_value(PARAM_TEXT, 'Ending name'),
            'message' => new external_value(PARAM_RAW, 'Ending message'),
            'completed' => new external_value(PARAM_BOOL, 'Completion state'),
            'path' => new external_multiple_structure(self::path_structure()),
        ]);
    }
}
