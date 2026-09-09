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
 * Global configuration of the support forums.
 *
 * @module     local_edusupport/globalconfig
 * @copyright  Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['core/notification', 'core/str'], function(Notification, Str) {
    return {
        /**
         * Kept so the button in the globalconfig template still resolves.
         *
         * Setting the default support forum is done from the course configuration page
         * these days, see local_edusupport/courseconfig.
         *
         * @returns {void}
         */
        setDefault: function() {
            Str.get_string('notused', 'local_edusupport')
                .then(function(message) {
                    return Notification.alert('', message);
                })
                .catch(Notification.exception);
        },
    };
});
