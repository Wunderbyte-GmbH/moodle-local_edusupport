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
 * Configuration of the support forums of a course.
 *
 * @module     local_edusupport/courseconfig
 * @copyright  Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'core/ajax', 'core/notification'], function($, AJAX, NOTIFICATION) {

    /**
     * Flash an element green or red to report the outcome of a call.
     *
     * @param {string} selector the element to flash.
     * @param {boolean} success whether the call succeeded.
     * @returns {void}
     */
    var flash = function(selector, success) {
        var colour = success ? 'rgba(0, 255, 0, 0.2)' : 'rgba(255, 0, 0, 0.2)';
        $(selector).css('background-color', colour);
        setTimeout(function() {
            $(selector).css('background-color', '');
        }, 500);
    };

    return {
        /**
         * Mark a forum of the course as the archive of the support forum.
         *
         * @param {string} uniqid identifier of the rendered list.
         * @param {number} forumid the forum to use as archive.
         * @returns {void}
         */
        setArchive: function(uniqid, forumid) {
            AJAX.call([{
                methodname: 'local_edusupport_set_archive',
                args: {forumid: forumid},
                done: function(result) {
                    flash('#' + uniqid + '-forum-' + forumid, result == '1');
                    top.location.reload();
                },
                fail: NOTIFICATION.exception
            }]);
        },

        /**
         * Mark a forum as the support forum of the course, or of the whole site.
         *
         * @param {string} uniqid identifier of the rendered list.
         * @param {number} forumid the forum to use.
         * @param {number} [asglobal] 1 to use it site wide, 0 for this course only.
         * @returns {void}
         */
        setDefault: function(uniqid, forumid, asglobal) {
            if (typeof asglobal === 'undefined') {
                asglobal = 0;
            }
            AJAX.call([{
                methodname: 'local_edusupport_set_default',
                args: {forumid: forumid, asglobal: asglobal},
                done: function(result) {
                    flash('#' + uniqid + '-forum-' + forumid, result == '1');
                    top.location.reload();
                },
                fail: NOTIFICATION.exception
            }]);
        },

        /**
         * Set the support level a user of this course is registered with.
         *
         * @param {string} uniqid identifier of the rendered list.
         * @param {number} courseid the course the user supports.
         * @param {number} userid the user to register.
         * @param {string} supportlevel the level to register them with.
         * @returns {void}
         */
        setSupporter: function(uniqid, courseid, userid, supportlevel) {
            AJAX.call([{
                methodname: 'local_edusupport_set_supporter',
                args: {courseid: courseid, userid: userid, supportlevel: supportlevel},
                done: function(result) {
                    flash('#' + uniqid + '-setsupporter-' + userid, result == '1');
                },
                fail: NOTIFICATION.exception
            }]);
        },
    };
});
