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

namespace mod_videobranch;

/**
 * Manages learner path state and decision history.
 *
 * @package mod_videobranch
 * @copyright 2026 Eduardo Kraus
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class attempt_manager {
    /** @var \stdClass */
    private $activity;
    /** @var \cm_info|\stdClass */
    private $cm;

    /**
     * Constructor.
     *
     * @param \stdClass $activity Activity record.
     * @param \cm_info|\stdClass $cm Course module.
     */
    public function __construct(\stdClass $activity, $cm) {
        $this->activity = $activity;
        $this->cm = $cm;
    }

    /**
     * Gets or creates one attempt per user and activity.
     *
     * @param int $userid User id.
     * @return \stdClass
     */
    public function get_or_create(int $userid): \stdClass {
        global $DB;
        $conditions = [
            'videobranchid' => $this->activity->id,
            'userid' => $userid,
        ];
        if ($attempt = $DB->get_record('videobranch_attempts', $conditions)) {
            return $attempt;
        }

        $factory = \core\lock\lock_config::get_lock_factory('mod_videobranch');
        $lock = $factory->get_lock('attempt:' . $this->activity->id . ':' . $userid, 10);
        if (!$lock) {
            throw new \moodle_exception('attemptlocktimeout', 'mod_videobranch');
        }
        try {
            if ($attempt = $DB->get_record('videobranch_attempts', $conditions)) {
                return $attempt;
            }
            $startvideo = $DB->get_record_sql(
                'SELECT * FROM {videobranch_videos} WHERE videobranchid = :id ORDER BY isstart DESC, sortorder ASC, id ASC',
                ['id' => $this->activity->id], IGNORE_MULTIPLE
            );
            $now = time();
            $record = (object)[
                'videobranchid' => $this->activity->id,
                'userid' => $userid,
                'currentvideoid' => $startvideo ? $startvideo->id : null,
                'currentposition' => 0,
                'watchedjson' => '{}',
                'pathjson' => '[]',
                'endingid' => null,
                'completed' => 0,
                'timecreated' => $now,
                'timemodified' => $now,
                'timecompleted' => null,
            ];
            $record->id = $DB->insert_record('videobranch_attempts', $record);
            return $record;
        } finally {
            $lock->release();
        }
    }

    /**
     * Saves resume state and watched segments.
     *
     * @param int $userid User id.
     * @param int $videoid Video id.
     * @param float $position Current position.
     * @param array $segments Watched segments for the current video.
     * @return \stdClass Updated attempt.
     */
    public function save_state(int $userid, int $videoid, float $position, array $segments): \stdClass {
        global $DB;
        $this->assert_video($videoid);
        $attempt = $this->get_or_create($userid);
        $transaction = $DB->start_delegated_transaction();
        $attempt = $DB->get_record_sql(
            'SELECT * FROM {videobranch_attempts} WHERE id = :id FOR UPDATE',
            ['id' => $attempt->id],
            MUST_EXIST
        );
        $watched = json_decode((string)$attempt->watchedjson, true);
        if (!is_array($watched)) {
            $watched = [];
        }
        $existing = isset($watched[(string)$videoid]) && is_array($watched[(string)$videoid])
            ? $watched[(string)$videoid]
            : [];
        $watched[(string)$videoid] = self::merge_segments(array_merge($existing, $segments));
        $attempt->currentvideoid = $videoid;
        $attempt->currentposition = max(0, $position);
        $attempt->watchedjson = json_encode($watched);
        $attempt->timemodified = time();
        $DB->update_record('videobranch_attempts', $attempt);
        $transaction->allow_commit();
        return $attempt;
    }

    /**
     * Records a selected option and returns destination details.
     *
     * @param int $userid User id.
     * @param int $nodeid Decision node id.
     * @param int $optionid Option id.
     * @param int $videoid Video from which selection was made.
     * @param float $position Position at selection.
     * @return array Destination.
     */
    public function choose(int $userid, int $nodeid, int $optionid, int $videoid, float $position): array {
        global $DB;
        $node = $DB->get_record('videobranch_nodes', ['id' => $nodeid, 'videobranchid' => $this->activity->id], '*', MUST_EXIST);
        $option = $DB->get_record('videobranch_options', ['id' => $optionid, 'nodeid' => $nodeid], '*', MUST_EXIST);
        if ((int)$node->videoid !== $videoid) {
            throw new \moodle_exception('invaliddecision', 'mod_videobranch');
        }
        $attempt = $this->get_or_create($userid);
        $transaction = $DB->start_delegated_transaction();
        $attempt = $DB->get_record_sql(
            'SELECT * FROM {videobranch_attempts} WHERE id = :id FOR UPDATE',
            ['id' => $attempt->id],
            MUST_EXIST
        );
        $existing = $DB->get_record('videobranch_choices', [
            'attemptid' => $attempt->id,
            'nodeid' => $nodeid,
            'active' => 1,
        ]);
        if ($existing) {
            if (empty($this->activity->allowback)) {
                throw new \moodle_exception('backnotallowed', 'mod_videobranch');
            }
            $this->deactivate_from_sequence($attempt->id, (int)$existing->sequence);
        }

        $maxsequence = (int)$DB->get_field_sql(
            'SELECT COALESCE(MAX(sequence), 0) FROM {videobranch_choices} WHERE attemptid = :attemptid AND active = 1',
            ['attemptid' => $attempt->id]
        );
        $choice = (object)[
            'attemptid' => $attempt->id,
            'nodeid' => $nodeid,
            'optionid' => $optionid,
            'sequence' => $maxsequence + 1,
            'active' => 1,
            'fromvideoid' => $videoid,
            'fromposition' => max(0, $position),
            'timecreated' => time(),
        ];
        $DB->insert_record('videobranch_choices', $choice);

        $path = $this->active_path($attempt->id);
        $attempt->pathjson = json_encode($path);
        $attempt->endingid = null;
        $attempt->completed = 0;
        $attempt->timecompleted = null;
        $destination = $this->resolve_destination($option);
        if ($destination['type'] === 'end') {
            $attempt->endingid = $destination['endingid'];
            $attempt->completed = 1;
            $attempt->timecompleted = time();
        } else {
            $attempt->currentvideoid = $destination['videoid'];
            $attempt->currentposition = $destination['second'];
        }
        $attempt->timemodified = time();
        $DB->update_record('videobranch_attempts', $attempt);
        $transaction->allow_commit();

        $this->update_completion($userid, !empty($attempt->completed));
        return $destination + ['path' => $path, 'completed' => (bool)$attempt->completed];
    }

    /**
     * Rewinds to a previous active choice.
     *
     * @param int $userid User id.
     * @param int $choiceid Choice id to rewind to.
     * @return array Rewind destination.
     */
    public function rewind(int $userid, int $choiceid): array {
        global $DB;
        if (empty($this->activity->allowback)) {
            throw new \moodle_exception('backnotallowed', 'mod_videobranch');
        }
        $attempt = $this->get_or_create($userid);
        $transaction = $DB->start_delegated_transaction();
        $attempt = $DB->get_record_sql(
            'SELECT * FROM {videobranch_attempts} WHERE id = :id FOR UPDATE',
            ['id' => $attempt->id],
            MUST_EXIST
        );
        $choice = $DB->get_record('videobranch_choices', [
            'id' => $choiceid,
            'attemptid' => $attempt->id,
            'active' => 1,
        ], '*', MUST_EXIST);
        $node = $DB->get_record('videobranch_nodes', ['id' => $choice->nodeid], '*', MUST_EXIST);
        $this->deactivate_from_sequence($attempt->id, (int)$choice->sequence);
        $attempt->currentvideoid = $node->videoid;
        $attempt->currentposition = max(0, (float)$node->triggersecond - 0.25);
        $attempt->endingid = null;
        $attempt->completed = 0;
        $attempt->timecompleted = null;
        $attempt->pathjson = json_encode($this->active_path($attempt->id));
        $attempt->timemodified = time();
        $DB->update_record('videobranch_attempts', $attempt);
        $transaction->allow_commit();
        $this->update_completion($userid, false);
        return [
            'videoid' => (int)$node->videoid,
            'second' => (float)$attempt->currentposition,
            'nodeid' => (int)$node->id,
            'path' => json_decode($attempt->pathjson, true) ?: [],
        ];
    }

    /**
     * Returns active path enriched with labels.
     *
     * @param int $attemptid Attempt id.
     * @return array
     */
    public function active_path(int $attemptid): array {
        global $DB;
        $sql = 'SELECT c.id AS choiceid, c.sequence, c.nodeid, c.optionid, c.timecreated,
                       n.name AS nodename, n.question, n.videoid, n.triggersecond, o.label AS optionlabel
                  FROM {videobranch_choices} c
                  JOIN {videobranch_nodes} n ON n.id = c.nodeid
                  JOIN {videobranch_options} o ON o.id = c.optionid
                 WHERE c.attemptid = :attemptid AND c.active = 1
              ORDER BY c.sequence ASC, c.id ASC';
        $records = $DB->get_records_sql($sql, ['attemptid' => $attemptid]);
        $path = [];
        foreach ($records as $record) {
            $path[] = [
                'choiceid' => (int)$record->choiceid,
                'sequence' => (int)$record->sequence,
                'nodeid' => (int)$record->nodeid,
                'nodename' => format_string($record->nodename),
                'question' => format_string($record->question),
                'optionid' => (int)$record->optionid,
                'optionlabel' => format_string($record->optionlabel),
                'videoid' => (int)$record->videoid,
                'second' => (float)$record->triggersecond,
                'timecreated' => (int)$record->timecreated,
            ];
        }
        return $path;
    }

    /**
     * Resolves option target to client destination.
     *
     * @param \stdClass $option Option record.
     * @return array
     */
    private function resolve_destination(\stdClass $option): array {
        global $DB;
        if ($option->targettype === 'end') {
            $ending = $DB->get_record('videobranch_endings', [
                'id' => $option->targetendingid,
                'videobranchid' => $this->activity->id,
            ], '*', MUST_EXIST);
            return [
                'type' => 'end',
                'endingid' => (int)$ending->id,
                'endingname' => format_string($ending->name),
                'message' => format_text($ending->message, FORMAT_HTML, ['context' => \context_module::instance($this->cm->id)]),
                'videoid' => 0,
                'second' => 0.0,
                'nodeid' => 0,
            ];
        }
        if ($option->targettype === 'node') {
            $node = $DB->get_record('videobranch_nodes', [
                'id' => $option->targetnodeid,
                'videobranchid' => $this->activity->id,
            ], '*', MUST_EXIST);
            return [
                'type' => 'node',
                'videoid' => (int)$node->videoid,
                'second' => max(0, (float)$node->triggersecond - 0.25),
                'nodeid' => (int)$node->id,
                'endingid' => 0,
                'endingname' => '',
                'message' => '',
            ];
        }
        $videoid = (int)$option->targetvideoid;
        $this->assert_video($videoid);
        return [
            'type' => $option->targettype === 'video' ? 'video' : 'time',
            'videoid' => $videoid,
            'second' => max(0, (float)$option->targetsecond),
            'nodeid' => 0,
            'endingid' => 0,
            'endingname' => '',
            'message' => '',
        ];
    }

    /**
     * Marks choices from a sequence onward inactive.
     *
     * @param int $attemptid Attempt id.
     * @param int $sequence Sequence threshold.
     * @return void
     */
    private function deactivate_from_sequence(int $attemptid, int $sequence): void {
        global $DB;
        $DB->set_field_select('videobranch_choices', 'active', 0,
            'attemptid = :attemptid AND sequence >= :sequence AND active = 1',
            ['attemptid' => $attemptid, 'sequence' => $sequence]);
    }

    /**
     * Synchronises the branch result with Moodle activity completion.
     *
     * @param int $userid User id.
     * @param bool $complete Completion state.
     * @return void
     */
    private function update_completion(int $userid, bool $complete): void {
        $completion = new \completion_info(get_course($this->activity->course));
        if ($completion->is_enabled($this->cm)) {
            $completion->update_state(
                $this->cm,
                $complete ? COMPLETION_COMPLETE : COMPLETION_INCOMPLETE,
                $userid
            );
        }
    }

    /**
     * Verifies video ownership.
     *
     * @param int $videoid Video id.
     * @return void
     */
    private function assert_video(int $videoid): void {
        global $DB;
        if (!$DB->record_exists('videobranch_videos', ['id' => $videoid, 'videobranchid' => $this->activity->id])) {
            throw new \moodle_exception('invalidvideo', 'mod_videobranch');
        }
    }

    /**
     * Merges and sanitises watched segments.
     *
     * @param array $segments Raw segments.
     * @return array
     */
    public static function merge_segments(array $segments): array {
        $clean = [];
        foreach ($segments as $segment) {
            if (!is_array($segment) || count($segment) < 2) {
                continue;
            }
            $start = max(0, (float)$segment[0]);
            $end = max($start, (float)$segment[1]);
            if ($end > $start) {
                $clean[] = [$start, $end];
            }
        }
        usort($clean, static fn(array $a, array $b): int => $a[0] <=> $b[0]);
        $merged = [];
        foreach ($clean as $segment) {
            if (!$merged || $segment[0] > $merged[count($merged) - 1][1] + 0.5) {
                $merged[] = $segment;
            } else {
                $merged[count($merged) - 1][1] = max($merged[count($merged) - 1][1], $segment[1]);
            }
        }
        return $merged;
    }
}
