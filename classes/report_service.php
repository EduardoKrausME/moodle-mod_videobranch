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
 * Builds aggregate and learner reports.
 *
 * @package mod_videobranch
 * @copyright 2026 Eduardo Kraus
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_service {
    /** @var \stdClass */
    private $activity;
    /** @var \cm_info|\stdClass */
    private $cm;

    /**
     * Constructor.
     *
     * @param \stdClass $activity Activity.
     * @param \cm_info|\stdClass $cm Course module.
     */
    public function __construct(\stdClass $activity, $cm) {
        $this->activity = $activity;
        $this->cm = $cm;
    }

    /**
     * Returns aggregate report data.
     *
     * @return array
     */
    public function aggregate(): array {
        global $DB;
        $attempts = $DB->get_records('videobranch_attempts', ['videobranchid' => $this->activity->id], 'timemodified DESC');
        $users = [];
        if ($attempts) {
            $userrecords = $DB->get_records_list('user', 'id',
                array_values(array_unique(array_map(static fn($a) => $a->userid, $attempts))), '',
                'id,firstname,lastname,email');
            foreach ($attempts as $attempt) {
                $user = $userrecords[$attempt->userid] ?? null;
                if (!$user) {
                    continue;
                }
                $ending = $attempt->endingid ? $DB->get_field('videobranch_endings', 'name',
                    ['id' => $attempt->endingid]) : '';
                $video = $attempt->currentvideoid ? $DB->get_field('videobranch_videos', 'name',
                    ['id' => $attempt->currentvideoid]) : '';
                $users[] = [
                    'userid' => (int)$user->id,
                    'fullname' => fullname($user),
                    'completed' => (bool)$attempt->completed,
                    'status' => get_string($attempt->completed ? 'completed' : 'inprogress', 'videobranch'),
                    'ending' => $ending ? format_string($ending) : '-',
                    'lastpoint' => $video ? format_string($video) . ' — ' .
                        branch_manager::format_time((float)$attempt->currentposition) : '-',
                    'timemodified' => userdate($attempt->timemodified),
                    'url' => (string)new \moodle_url('/mod/videobranch/report/user.php',
                        ['id' => $this->cm->id, 'userid' => $user->id]),
                ];
            }
        }

        $sql = 'SELECT o.id, o.label, n.id AS nodeid, n.name AS nodename, COUNT(DISTINCT a.userid) AS users
                  FROM {videobranch_options} o
                  JOIN {videobranch_nodes} n ON n.id = o.nodeid
                  LEFT JOIN {videobranch_choices} c ON c.optionid = o.id AND c.active = 1
                  LEFT JOIN {videobranch_attempts} a ON a.id = c.attemptid
                 WHERE n.videobranchid = :activityid
              GROUP BY o.id, o.label, n.id, n.name
              ORDER BY n.name, o.sortorder, o.id';
        $optionstats = [];
        foreach ($DB->get_records_sql($sql, ['activityid' => $this->activity->id]) as $row) {
            $optionstats[] = [
                'decision' => format_string($row->nodename),
                'option' => format_string($row->label),
                'users' => (int)$row->users,
            ];
        }

        $endings = [];
        $sql = 'SELECT e.id, e.name, COUNT(a.id) AS users
                  FROM {videobranch_endings} e
             LEFT JOIN {videobranch_attempts} a ON a.endingid=e.id AND a.completed=1
                 WHERE e.videobranchid=:activityid
              GROUP BY e.id,e.name
              ORDER BY users DESC,e.sortorder,e.id';
        foreach ($DB->get_records_sql($sql, ['activityid' => $this->activity->id]) as $row) {
            $endings[] = ['name' => format_string($row->name), 'users' => (int)$row->users];
        }

        $pathcounts = [];
        $attemptmanager = new attempt_manager($this->activity, $this->cm);
        foreach ($attempts as $attempt) {
            $path = $attemptmanager->active_path((int)$attempt->id);
            if (!$path) {
                continue;
            }
            $labels = [];
            foreach ($path as $entry) {
                $labels[] = ($entry['nodename'] ??
                        ('#' . ($entry['nodeid'] ?? '?'))) . ': ' . ($entry['optionlabel'] ??
                        ('#' . ($entry['optionid'] ?? '?')));
            }
            $signature = implode(' → ', $labels);
            $pathcounts[$signature] = ($pathcounts[$signature] ?? 0) + 1;
        }
        arsort($pathcounts);
        $paths = [];
        foreach ($pathcounts as $path => $count) {
            $paths[] = ['path' => $path, 'users' => $count];
        }

        $abandonments = array_values(array_filter($users, static fn(array $user): bool => !$user['completed']));
        return [
            'users' => $users,
            'hasusers' => (bool)$users,
            'optionstats' => $optionstats,
            'hasoptionstats' => (bool)$optionstats,
            'endings' => $endings,
            'hasendings' => (bool)$endings,
            'paths' => $paths,
            'haspaths' => (bool)$paths,
            'abandonments' => $abandonments,
            'hasabandonments' => (bool)$abandonments,
            'totalattempts' => count($attempts),
            'totalcompleted' => count(array_filter($attempts, static fn($a) => !empty($a->completed))),
        ];
    }

    /**
     * Returns one learner report including inactive historical choices.
     *
     * @param int $userid User id.
     * @return array
     */
    public function user(int $userid): array {
        global $DB;
        $user = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
        $attempt = $DB->get_record('videobranch_attempts', ['videobranchid' => $this->activity->id, 'userid' => $userid]);
        $choices = [];
        if ($attempt) {
            $sql = 'SELECT c.*, n.name AS nodename, n.question, o.label AS optionlabel, v.name AS videoname
                      FROM {videobranch_choices} c
                      JOIN {videobranch_nodes} n ON n.id=c.nodeid
                      JOIN {videobranch_options} o ON o.id=c.optionid
                 LEFT JOIN {videobranch_videos} v ON v.id=c.fromvideoid
                     WHERE c.attemptid=:attemptid
                  ORDER BY c.timecreated ASC,c.id ASC';
            foreach ($DB->get_records_sql($sql, ['attemptid' => $attempt->id]) as $choice) {
                $choices[] = [
                    'decision' => format_string($choice->nodename),
                    'question' => format_string($choice->question),
                    'option' => format_string($choice->optionlabel),
                    'active' => (bool)$choice->active,
                    'state' => get_string($choice->active ? 'activechoice' : 'supersededchoice', 'videobranch'),
                    'video' => $choice->videoname ? format_string($choice->videoname) : '-',
                    'position' => branch_manager::format_time((float)$choice->fromposition),
                    'time' => userdate($choice->timecreated),
                ];
            }
        }
        $ending = $attempt && $attempt->endingid ? $DB->get_field('videobranch_endings', 'name', ['id' => $attempt->endingid]) : '';
        return [
            'fullname' => fullname($user),
            'hasattempt' => (bool)$attempt,
            'completed' => $attempt ? (bool)$attempt->completed : false,
            'status' => $attempt ?
                get_string($attempt->completed ? 'completed' : 'inprogress', 'videobranch') :
                get_string('notstarted', 'videobranch'),
            'ending' => $ending ? format_string($ending) : '-',
            'choices' => $choices,
            'haschoices' => (bool)$choices,
        ];
    }
}
