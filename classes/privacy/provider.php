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

namespace mod_videobranch\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;

/**
 * Privacy provider for Branching Video.
 *
 * @package mod_videobranch
 * @copyright 2026 Eduardo Kraus
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider {

    /**
     * Describes stored personal data.
     *
     * @param collection $collection Metadata collection.
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('videobranch_attempts', [
            'userid' => 'privacy:metadata:attempts:userid',
            'currentvideoid' => 'privacy:metadata:attempts:currentvideoid',
            'currentposition' => 'privacy:metadata:attempts:currentposition',
            'watchedjson' => 'privacy:metadata:attempts:watchedjson',
            'pathjson' => 'privacy:metadata:attempts:pathjson',
            'endingid' => 'privacy:metadata:attempts:endingid',
            'completed' => 'privacy:metadata:attempts:completed',
            'timemodified' => 'privacy:metadata:attempts:timemodified',
        ], 'privacy:metadata:attempts');
        $collection->add_database_table('videobranch_choices', [
            'nodeid' => 'privacy:metadata:choices:nodeid',
            'optionid' => 'privacy:metadata:choices:optionid',
            'active' => 'privacy:metadata:choices:active',
            'fromposition' => 'privacy:metadata:choices:fromposition',
            'timecreated' => 'privacy:metadata:choices:timecreated',
        ], 'privacy:metadata:choices');
        return $collection;
    }

    /**
     * Gets contexts containing user data.
     *
     * @param int $userid User id.
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $sql = 'SELECT ctx.id
                  FROM {context} ctx
                  JOIN {course_modules} cm ON cm.id = ctx.instanceid AND ctx.contextlevel = :contextlevel
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {videobranch} vb ON vb.id = cm.instance
                  JOIN {videobranch_attempts} a ON a.videobranchid = vb.id
                 WHERE a.userid = :userid';
        $params = ['contextlevel' => CONTEXT_MODULE, 'modname' => 'videobranch', 'userid' => $userid];
        $contextlist = new contextlist();
        $contextlist->add_from_sql($sql, $params);
        return $contextlist;
    }

    /**
     * Adds users with data in a context.
     *
     * @param userlist $userlist User list.
     * @return void
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof \context_module) {
            return;
        }
        $sql = 'SELECT a.userid
                  FROM {videobranch_attempts} a
                  JOIN {course_modules} cm ON cm.instance = a.videobranchid
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                 WHERE cm.id = :cmid';
        $userlist->add_from_sql('userid', $sql, ['modname' => 'videobranch', 'cmid' => $context->instanceid]);
    }

    /**
     * Exports user data.
     *
     * @param approved_contextlist $contextlist Approved contexts.
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_module) {
                continue;
            }
            $cm = get_coursemodule_from_id('videobranch', $context->instanceid);
            if (!$cm) {
                continue;
            }
            $attempt = $DB->get_record('videobranch_attempts', ['videobranchid' => $cm->instance, 'userid' => $userid]);
            if (!$attempt) {
                continue;
            }
            $choices = $DB->get_records('videobranch_choices', ['attemptid' => $attempt->id], 'timecreated ASC');
            $data = (object)[
                'currentposition' => $attempt->currentposition,
                'watched' => json_decode((string)$attempt->watchedjson, true),
                'path' => json_decode((string)$attempt->pathjson, true),
                'completed' => transform::yesno($attempt->completed),
                'timecreated' => transform::datetime($attempt->timecreated),
                'timemodified' => transform::datetime($attempt->timemodified),
                'choices' => array_values($choices),
            ];
            \core_privacy\local\request\writer::with_context($context)->export_data([], $data);
        }
    }

    /**
     * Deletes all user data in a module context.
     *
     * @param \context $context Context.
     * @return void
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;
        if (!$context instanceof \context_module) {
            return;
        }
        $cm = get_coursemodule_from_id('videobranch', $context->instanceid);
        if (!$cm) {
            return;
        }
        $attemptids = $DB->get_fieldset_select('videobranch_attempts', 'id', 'videobranchid = :id', ['id' => $cm->instance]);
        self::delete_attempt_ids($attemptids);
        $DB->delete_records('videobranch_attempts', ['videobranchid' => $cm->instance]);
    }

    /**
     * Deletes one user's data in approved contexts.
     *
     * @param approved_contextlist $contextlist Approved contexts.
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_module) {
                continue;
            }
            $cm = get_coursemodule_from_id('videobranch', $context->instanceid);
            if (!$cm) {
                continue;
            }
            $attemptids = $DB->get_fieldset_select('videobranch_attempts', 'id',
                'videobranchid = :id AND userid = :userid', ['id' => $cm->instance, 'userid' => $userid]);
            self::delete_attempt_ids($attemptids);
            $DB->delete_records('videobranch_attempts', ['videobranchid' => $cm->instance, 'userid' => $userid]);
        }
    }

    /**
     * Deletes approved users in a context.
     *
     * @param approved_userlist $userlist Approved user list.
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;
        $context = $userlist->get_context();
        if (!$context instanceof \context_module || !$userlist->get_userids()) {
            return;
        }
        $cm = get_coursemodule_from_id('videobranch', $context->instanceid);
        if (!$cm) {
            return;
        }
        [$usersql, $params] = $DB->get_in_or_equal($userlist->get_userids(), SQL_PARAMS_NAMED, 'user');
        $params['activityid'] = $cm->instance;
        $attemptids = $DB->get_fieldset_select('videobranch_attempts', 'id',
            "videobranchid = :activityid AND userid {$usersql}", $params);
        self::delete_attempt_ids($attemptids);
        $DB->delete_records_select('videobranch_attempts', "videobranchid = :activityid AND userid {$usersql}", $params);
    }

    /**
     * Deletes choices for a list of attempt ids.
     *
     * @param array $attemptids Attempt ids.
     * @return void
     */
    private static function delete_attempt_ids(array $attemptids): void {
        global $DB;
        if (!$attemptids) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($attemptids, SQL_PARAMS_NAMED, 'attempt');
        $DB->delete_records_select('videobranch_choices', "attemptid {$insql}", $params);
    }
}
