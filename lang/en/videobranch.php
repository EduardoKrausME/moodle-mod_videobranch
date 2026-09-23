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
 * English language strings for Branching Video.
 *
 * @package mod_videobranch
 * @copyright 2026 Eduardo Kraus
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

$string['abandonments'] = 'Where learners stopped';
$string['activechoice'] = 'Current path';
$string['adddecision'] = 'Add decision';
$string['addending'] = 'Add ending';
$string['addoption'] = 'Add alternative';
$string['addvideo'] = 'Add video';
$string['addvideofirst'] = 'Add at least one video before creating a decision.';
$string['allowback'] = 'Allow learners to go back and change a decision';
$string['allowseek'] = 'Allow free seeking';
$string['alternative'] = 'Alternative';
$string['alternativecounts'] = 'Alternatives selected';
$string['attemptlocktimeout'] = 'The learner attempt could not be locked for update. Please try again.';
$string['backnotallowed'] = 'Changing an earlier decision is not allowed in this activity.';
$string['backtoreport'] = 'Back to report';
$string['cannotdeletereferenced'] = 'This item cannot be deleted because it is used by another path.';
$string['changedecision'] = 'Change from here';
$string['chooseoption'] = 'Choose an alternative';
$string['completed'] = 'Completed';
$string['completedpaths'] = 'Completed paths';
$string['completionending'] = 'Learner must reach a valid ending';
$string['completionrules'] = '';
$string['confirmdelete'] = 'Delete item';
$string['confirmdeletebody'] = 'Delete this item? This action cannot be undone.';
$string['decision'] = 'Decision';
$string['decisionhistory'] = 'Decision history';
$string['decisionname'] = 'Decision name';
$string['decisionrequired'] = 'Choose an alternative to continue.';
$string['decisions'] = 'Decisions';
$string['decisionwithoutoptions'] = 'This decision has no alternatives yet.';
$string['deleted'] = 'Item deleted.';
$string['editdecision'] = 'Edit decision';
$string['editending'] = 'Edit ending';
$string['editoption'] = 'Edit alternative';
$string['editvideo'] = 'Edit video';
$string['endingdistribution'] = 'Ending distribution';
$string['endingmessage'] = 'Message shown at the ending';
$string['endingname'] = 'Ending name';
$string['endingreached'] = 'Ending reached';
$string['endings'] = 'Valid endings';
$string['errormaxfiles'] = 'Only one file can be uploaded.';
$string['final'] = 'Ending';
$string['inprogress'] = 'In progress';
$string['invaliddecision'] = 'The requested decision is not valid for this activity.';
$string['invalidending'] = 'The requested ending is not valid for this activity.';
$string['invalidentity'] = 'Invalid Branching Video entity.';
$string['invalidvideo'] = 'The requested video is not valid for this activity.';
$string['lastactivity'] = 'Last activity';
$string['lastpoint'] = 'Last point';
$string['location'] = 'Video and time';
$string['managehint'] = 'After saving the activity, use “Manage paths” to add videos, decision points, alternatives and endings.';
$string['managepaths'] = 'Manage paths';
$string['managepaths_help'] = 'Build the experience by adding videos, placing decisions at timecodes, connecting each alternative to its destination, and defining valid endings. Paths may jump to another time, another video, another decision, or an ending.';
$string['modulename'] = 'Branching Video';
$string['modulenameplural'] = 'Branching Videos';
$string['mostusedpaths'] = 'Most-used paths';
$string['noabandonments'] = 'No incomplete attempts were found.';
$string['nodata'] = 'No data is available yet.';
$string['nodecisionsconfigured'] = 'No decisions have been configured yet.';
$string['noendingsconfigured'] = 'No valid endings have been configured yet.';
$string['nopathyet'] = 'No decision has been taken yet.';
$string['notstarted'] = 'Not started';
$string['novideosconfigured'] = 'No videos have been configured yet.';
$string['optionfor'] = 'Alternative for decision: {$a}';
$string['optionlabel'] = 'Alternative text';
$string['participant'] = 'Participant';
$string['participants'] = 'Participants with an attempt';
$string['pathavailablebelow'] = 'Your path is shown beside the video.';
$string['pathheading'] = 'Path taken';
$string['playbacksettings'] = 'Playback and navigation';
$string['player'] = 'Video player';
$string['pluginadministration'] = 'Branching Video administration';
$string['pluginname'] = 'Branching Video';
$string['privacy:metadata:attempts'] = 'Stores each learner’s current Branching Video path and resume state.';
$string['privacy:metadata:attempts:completed'] = 'Whether the learner reached a valid ending.';
$string['privacy:metadata:attempts:currentposition'] = 'The last playback position.';
$string['privacy:metadata:attempts:currentvideoid'] = 'The video where the learner last stopped.';
$string['privacy:metadata:attempts:endingid'] = 'The ending reached by the learner.';
$string['privacy:metadata:attempts:pathjson'] = 'The learner’s currently active path.';
$string['privacy:metadata:attempts:timemodified'] = 'The time the attempt was last updated.';
$string['privacy:metadata:attempts:userid'] = 'The learner who owns the attempt.';
$string['privacy:metadata:attempts:watchedjson'] = 'The watched video segments used for resume and tracking.';
$string['privacy:metadata:choices'] = 'Stores the learner’s decision history.';
$string['privacy:metadata:choices:active'] = 'Whether this choice remains in the current path.';
$string['privacy:metadata:choices:fromposition'] = 'The video position where the choice was made.';
$string['privacy:metadata:choices:nodeid'] = 'The decision that was presented.';
$string['privacy:metadata:choices:optionid'] = 'The selected alternative.';
$string['privacy:metadata:choices:timecreated'] = 'The time the choice was made.';
$string['question'] = 'Question shown to the learner';
$string['report'] = 'Report';
$string['resumeask'] = 'Ask before resuming';
$string['resumeautomatic'] = 'Resume automatically';
$string['resumefromstart'] = 'Always start from the beginning';
$string['resumeno'] = 'Start again';
$string['resumeplayback'] = 'Resume playback';
$string['resumequestion'] = 'Continue from where you stopped?';
$string['resumeyes'] = 'Continue';
$string['saved'] = 'Changes saved.';
$string['savingerror'] = 'The playback position could not be saved.';
$string['showpath'] = 'Show the path taken to the learner';
$string['sortorder'] = 'Sort order';
$string['source_upload'] = 'Uploaded video';
$string['source_url'] = 'Direct media URL';
$string['source_vimeo'] = 'Vimeo';
$string['source_youtube'] = 'YouTube';
$string['sourcetype'] = 'Video source';
$string['sourceurl'] = 'Video URL';
$string['startvideo'] = 'Start video';
$string['state'] = 'State';
$string['status'] = 'Status';
$string['studentpaths'] = 'Learner paths';
$string['students'] = 'Learners';
$string['supersededchoice'] = 'Changed later';
$string['target_end'] = 'A valid ending';
$string['target_end_desc'] = 'Ending: {$a}';
$string['target_node'] = 'Another decision';
$string['target_node_desc'] = 'Decision: {$a}';
$string['target_time'] = 'Another time in a video';
$string['target_video'] = 'Another video';
$string['target_video_time_desc'] = '{$a->video} at {$a->time}';
$string['targetending'] = 'Destination ending';
$string['targetnode'] = 'Destination decision';
$string['targetsecond'] = 'Destination second';
$string['targettype'] = 'Destination';
$string['targetvideo'] = 'Destination video';
$string['triggersecond'] = 'Pause at second';
$string['userreport'] = 'Learner report';
$string['video'] = 'Video';
$string['videobranch:addinstance'] = 'Add a new Branching Video activity';
$string['videobranch:manage'] = 'Manage videos, decisions, options and endings';
$string['videobranch:view'] = 'View and participate in Branching Video';
$string['videobranch:viewreport'] = 'View Branching Video reports';
$string['videobranchname'] = 'Activity name';
$string['videofile'] = 'Video file';
$string['videoname'] = 'Video name';
$string['videos'] = 'Videos';
$string['viewactivity'] = 'View activity';
