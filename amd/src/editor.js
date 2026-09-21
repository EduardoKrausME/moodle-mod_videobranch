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
 * editor.js
 *
 * @package   mod_videobranch
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Branch editor interactions.
 *
 * @module mod_videobranch/editor
 */
define(['core/notification'], function (Notification) {
    const init = () => {
        document.addEventListener('click', (event) => {
            const link = event.target.closest('[data-action="delete"]');
            if (!link) {
                return;
            }
            event.preventDefault();
            Notification.confirm(
                M.util.get_string('confirmdelete', 'videobranch'),
                M.util.get_string('confirmdeletebody', 'videobranch'),
                M.util.get_string('delete', 'moodle'),
                M.util.get_string('cancel', 'moodle'),
                () => {
                    window.location.href = link.href;
                }
            );
        });
    };
    return {init: init};
});
