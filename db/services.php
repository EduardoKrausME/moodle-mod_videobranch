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
 * External functions for Branching Video.
 *
 * @package mod_videobranch
 * @copyright 2026 Eduardo Kraus
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'mod_videobranch_save_state' => [
        'classname' => 'mod_videobranch\\external\\save_state',
        'methodname' => 'execute',
        'description' => 'Save the current video position and watched segments.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'mod/videobranch:view',
    ],
    'mod_videobranch_choose_option' => [
        'classname' => 'mod_videobranch\\external\\choose_option',
        'methodname' => 'execute',
        'description' => 'Record a decision and return its next destination.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'mod/videobranch:view',
    ],
    'mod_videobranch_rewind_choice' => [
        'classname' => 'mod_videobranch\\external\\rewind_choice',
        'methodname' => 'execute',
        'description' => 'Rewind an active path to an earlier decision when changing choices is allowed.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'mod/videobranch:view',
    ],
];
