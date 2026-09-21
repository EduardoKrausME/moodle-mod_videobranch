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
 * Provides branch configuration to learner and editor views.
 *
 * @package mod_videobranch
 * @copyright 2026 Eduardo Kraus
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class branch_manager {
    /** @var \stdClass */
    private $activity;
    /** @var \cm_info|\stdClass */
    private $cm;
    /** @var \context_module */
    private $context;

    /**
     * Constructor.
     *
     * @param \stdClass $activity Activity.
     * @param \cm_info|\stdClass $cm Course module.
     * @param \context_module $context Module context.
     */
    public function __construct(\stdClass $activity, $cm, \context_module $context) {
        $this->activity = $activity;
        $this->cm = $cm;
        $this->context = $context;
    }

    /**
     * Returns player configuration for one learner.
     *
     * @param int $userid User id.
     * @return array
     */
    public function get_player_config(int $userid): array {
        global $DB;
        $videos = $DB->get_records('videobranch_videos',
            ['videobranchid' => $this->activity->id], 'isstart DESC, sortorder ASC, id ASC');
        $nodes = $DB->get_records('videobranch_nodes',
            ['videobranchid' => $this->activity->id], 'videoid ASC, triggersecond ASC, sortorder ASC, id ASC');
        $nodeids = array_keys($nodes);
        $options = [];
        if ($nodeids) {
            [$insql, $params] = $DB->get_in_or_equal($nodeids, SQL_PARAMS_NAMED, 'node');
            $options = $DB->get_records_select('videobranch_options',
                "nodeid {$insql}", $params, 'nodeid ASC, sortorder ASC, id ASC');
        }
        $optionsbynode = [];
        foreach ($options as $option) {
            $optionsbynode[$option->nodeid][] = [
                'id' => (int)$option->id,
                'label' => format_string($option->label),
            ];
        }
        $clientnodes = [];
        foreach ($nodes as $node) {
            $clientnodes[] = [
                'id' => (int)$node->id,
                'videoid' => (int)$node->videoid,
                'name' => format_string($node->name),
                'question' => format_string($node->question),
                'second' => (float)$node->triggersecond,
                'options' => $optionsbynode[$node->id] ?? [],
            ];
        }
        $clientvideos = [];
        foreach ($videos as $video) {
            $clientvideos[] = $this->video_client_data($video);
        }
        $attemptmanager = new attempt_manager($this->activity, $this->cm);
        $attempt = $attemptmanager->get_or_create($userid);
        $ending = null;
        if (!empty($attempt->endingid)) {
            $endingrecord = $DB->get_record('videobranch_endings', ['id' => $attempt->endingid]);
            if ($endingrecord) {
                $ending = [
                    'id' => (int)$endingrecord->id,
                    'name' => format_string($endingrecord->name),
                    'message' => format_text($endingrecord->message, FORMAT_HTML, ['context' => $this->context]),
                ];
            }
        }
        return [
            'cmid' => (int)$this->cm->id,
            'activityid' => (int)$this->activity->id,
            'resumeplayback' => (int)$this->activity->resumeplayback,
            'allowseek' => (bool)$this->activity->allowseek,
            'allowback' => (bool)$this->activity->allowback,
            'showpath' => (bool)$this->activity->showpath,
            'videos' => array_values($clientvideos),
            'nodes' => array_values($clientnodes),
            'attempt' => [
                'id' => (int)$attempt->id,
                'videoid' => (int)$attempt->currentvideoid,
                'position' => (float)$attempt->currentposition,
                'watched' => json_decode((string)$attempt->watchedjson, true) ?: [],
                'path' => $attemptmanager->active_path($attempt->id),
                'completed' => (bool)$attempt->completed,
                'ending' => $ending,
            ],
        ];
    }

    /**
     * Returns editor data with destination descriptions.
     *
     * @return array
     */
    public function get_manage_data(): array {
        global $DB;
        $videos = $DB->get_records('videobranch_videos',
            ['videobranchid' => $this->activity->id], 'isstart DESC, sortorder ASC, id ASC');
        $nodes = $DB->get_records('videobranch_nodes',
            ['videobranchid' => $this->activity->id], 'videoid ASC, triggersecond ASC, sortorder ASC, id ASC');
        $endings = $DB->get_records('videobranch_endings',
            ['videobranchid' => $this->activity->id], 'sortorder ASC, id ASC');
        $nodeids = array_keys($nodes);
        $options = [];
        if ($nodeids) {
            [$insql, $params] = $DB->get_in_or_equal($nodeids, SQL_PARAMS_NAMED, 'node');
            $options = $DB->get_records_select('videobranch_options',
                "nodeid {$insql}", $params, 'nodeid ASC, sortorder ASC, id ASC');
        }
        $videomap = [];
        foreach ($videos as $video) {
            $videomap[$video->id] = $video;
        }
        $nodemap = $nodes;
        $endingmap = $endings;
        $clientvideos = [];
        foreach ($videos as $video) {
            $clientvideos[] = [
                'id' => (int)$video->id,
                'name' => format_string($video->name),
                'source' => get_string('source_' . $video->sourcetype, 'videobranch'),
                'isstart' => (bool)$video->isstart,
                'editurl' => (string)new \moodle_url('/mod/videobranch/video.php',
                    ['id' => $this->cm->id, 'videoid' => $video->id]),
                'deleteurl' => $this->delete_url('video', $video->id),
            ];
        }
        $optionsbynode = [];
        foreach ($options as $option) {
            $optionsbynode[$option->nodeid][] = [
                'id' => (int)$option->id,
                'label' => format_string($option->label),
                'target' => $this->describe_target($option, $videomap, $nodemap, $endingmap),
                'editurl' => (string)new \moodle_url('/mod/videobranch/option.php',
                    ['id' => $this->cm->id, 'optionid' => $option->id, 'nodeid' => $option->nodeid]),
                'deleteurl' => $this->delete_url('option', $option->id),
            ];
        }
        $clientnodes = [];
        foreach ($nodes as $node) {
            $clientnodes[] = [
                'id' => (int)$node->id,
                'name' => format_string($node->name),
                'question' => format_string($node->question),
                'video' => isset($videomap[$node->videoid]) ? format_string($videomap[$node->videoid]->name) : '-',
                'time' => self::format_time((float)$node->triggersecond),
                'options' => $optionsbynode[$node->id] ?? [],
                'hasoptions' => !empty($optionsbynode[$node->id]),
                'optionaddurl' => (string)new \moodle_url('/mod/videobranch/option.php',
                    ['id' => $this->cm->id, 'nodeid' => $node->id]),
                'editurl' => (string)new \moodle_url('/mod/videobranch/decision.php',
                    ['id' => $this->cm->id, 'nodeid' => $node->id]),
                'deleteurl' => $this->delete_url('node', $node->id),
            ];
        }
        $clientendings = [];
        foreach ($endings as $ending) {
            $clientendings[] = [
                'id' => (int)$ending->id,
                'name' => format_string($ending->name),
                'editurl' => (string)new \moodle_url('/mod/videobranch/ending.php',
                    ['id' => $this->cm->id, 'endingid' => $ending->id]),
                'deleteurl' => $this->delete_url('ending', $ending->id),
            ];
        }
        return [
            'videos' => $clientvideos,
            'hasvideos' => (bool)$clientvideos,
            'nodes' => $clientnodes,
            'hasnodes' => (bool)$clientnodes,
            'endings' => $clientendings,
            'hasendings' => (bool)$clientendings,
        ];
    }

    /**
     * Deletes an editor entity after verifying activity ownership.
     *
     * @param string $type Entity type.
     * @param int $id Entity id.
     * @return void
     */
    public function delete_entity(string $type, int $id): void {
        global $DB;
        if ($type === 'option') {
            $sql = '
                SELECT o.*
                  FROM {videobranch_options} o
                  JOIN {videobranch_nodes} n ON n.id = o.nodeid
                 WHERE o.id = :id
                   AND n.videobranchid = :activityid';
            $option = $DB->get_record_sql($sql,
                ['id' => $id, 'activityid' => $this->activity->id], MUST_EXIST);
            if ($DB->record_exists('videobranch_choices', ['optionid' => $option->id])) {
                throw new \moodle_exception('cannotdeletereferenced', 'mod_videobranch');
            }
            $DB->delete_records('videobranch_options', ['id' => $option->id]);
            return;
        }
        if ($type === 'node') {
            $node = $DB->get_record('videobranch_nodes', ['id' => $id, 'videobranchid' => $this->activity->id], '*', MUST_EXIST);
            if ($DB->record_exists('videobranch_options', ['targetnodeid' => $node->id]) ||
                $DB->record_exists('videobranch_choices', ['nodeid' => $node->id])) {
                throw new \moodle_exception('cannotdeletereferenced', 'mod_videobranch');
            }
            $optionids = $DB->get_fieldset_select('videobranch_options', 'id', 'nodeid = :nodeid', ['nodeid' => $node->id]);
            if ($optionids) {
                [$insql, $params] = $DB->get_in_or_equal($optionids, SQL_PARAMS_NAMED, 'option');
                if ($DB->record_exists_select('videobranch_choices', "optionid {$insql}", $params)) {
                    throw new \moodle_exception('cannotdeletereferenced', 'mod_videobranch');
                }
            }
            $DB->delete_records('videobranch_options', ['nodeid' => $node->id]);
            $DB->delete_records('videobranch_nodes', ['id' => $node->id]);
            return;
        }
        if ($type === 'ending') {
            $ending = $DB->get_record('videobranch_endings',
                ['id' => $id, 'videobranchid' => $this->activity->id], '*', MUST_EXIST);
            if ($DB->record_exists('videobranch_options', ['targetendingid' => $ending->id]) ||
                $DB->record_exists('videobranch_attempts', ['endingid' => $ending->id])) {
                throw new \moodle_exception('cannotdeletereferenced', 'mod_videobranch');
            }
            $DB->delete_records('videobranch_endings', ['id' => $ending->id]);
            return;
        }
        if ($type === 'video') {
            $video = $DB->get_record('videobranch_videos', ['id' => $id, 'videobranchid' => $this->activity->id], '*', MUST_EXIST);
            if ($DB->record_exists('videobranch_nodes', ['videoid' => $video->id]) ||
                $DB->record_exists('videobranch_options', ['targetvideoid' => $video->id]) ||
                $DB->record_exists('videobranch_attempts', ['currentvideoid' => $video->id]) ||
                $DB->record_exists('videobranch_choices', ['fromvideoid' => $video->id])) {
                throw new \moodle_exception('cannotdeletereferenced', 'mod_videobranch');
            }
            get_file_storage()->delete_area_files($this->context->id, 'mod_videobranch', 'video', $video->id);
            $DB->delete_records('videobranch_videos', ['id' => $video->id]);
            return;
        }
        throw new \moodle_exception('invalidentity', 'mod_videobranch');
    }

    /**
     * Converts video record into safe client configuration.
     *
     * @param \stdClass $video Video record.
     * @return array
     */
    private function video_client_data(\stdClass $video): array {
        $url = '';
        if ($video->sourcetype === 'upload') {
            $files = get_file_storage()->get_area_files($this->context->id, 'mod_videobranch', 'video', $video->id,
                'itemid, filepath, filename', false);
            $file = reset($files);
            if ($file) {
                $url = \moodle_url::make_pluginfile_url($this->context->id, 'mod_videobranch', 'video', $video->id,
                    $file->get_filepath(), $file->get_filename())->out(false);
            }
        } else {
            $url = clean_param((string)$video->sourceurl, PARAM_URL);
        }
        return [
            'id' => (int)$video->id,
            'name' => format_string($video->name),
            'type' => $video->sourcetype,
            'url' => $url,
            'isstart' => (bool)$video->isstart,
        ];
    }

    /**
     * Human-readable target description for editor.
     *
     * @param \stdClass $option Option record.
     * @param array $videos Videos map.
     * @param array $nodes Nodes map.
     * @param array $endings Endings map.
     * @return string
     */
    private function describe_target(\stdClass $option, array $videos, array $nodes, array $endings): string {
        if ($option->targettype === 'end') {
            return get_string('target_end_desc', 'videobranch',
                isset($endings[$option->targetendingid]) ? $endings[$option->targetendingid]->name : '-');
        }
        if ($option->targettype === 'node') {
            return get_string('target_node_desc', 'videobranch',
                isset($nodes[$option->targetnodeid]) ? $nodes[$option->targetnodeid]->name : '-');
        }
        $video = isset($videos[$option->targetvideoid]) ? $videos[$option->targetvideoid]->name : '-';
        return get_string('target_video_time_desc', 'videobranch', (object)[
            'video' => $video,
            'time' => self::format_time((float)$option->targetsecond),
        ]);
    }

    /**
     * Returns deletion URL.
     *
     * @param string $type Entity type.
     * @param int $id Entity id.
     * @return string
     */
    private function delete_url(string $type, int $id): string {
        return (string)new \moodle_url('/mod/videobranch/delete.php', [
            'id' => $this->cm->id,
            'type' => $type,
            'itemid' => $id,
            'sesskey' => sesskey(),
        ]);
    }

    /**
     * Formats seconds as MM:SS or HH:MM:SS.
     *
     * @param float $seconds Seconds.
     * @return string
     */
    public static function format_time(float $seconds): string {
        $seconds = max(0, (int)round($seconds));
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $remaining = $seconds % 60;
        return $hours > 0 ? sprintf('%02d:%02d:%02d', $hours, $minutes, $remaining) : sprintf('%02d:%02d', $minutes, $remaining);
    }
}
