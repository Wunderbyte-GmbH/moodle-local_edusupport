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
 * Install time setup for local_edusupport.
 *
 * @package    local_edusupport
 * @copyright  2020 Center for Learning Management (https://www.lernmanagement.at)
 * @author     Robert Schrenk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Set up the role and capabilities the support team needs.
 *
 * @return void
 */
function xmldb_local_edusupport_install() {
    global $DB, $CFG;

    $role = $DB->get_record('role', ['shortname' => 'local_edusupport']);
    if (empty($role->id)) {
        $sql = "SELECT MAX(sortorder)+1 AS id FROM {role}";
        $max = $DB->get_record_sql($sql, []);

        $role = (object) [
            'name' => 'eduSupport Team',
            'shortname' => 'local_edusupport',
            'description' => 'This role was automatically created by the local_edusupport Plugin',
            'sortorder' => $max->id,
            'archetype' => '',
        ];
        $role->id = $DB->insert_record('role', $role);
    }

    set_config('supportteamrole', $role->id, 'local_edusupport');

    // Skip the dummy guest user on test sites: it becomes part of the site snapshot and
    // pollutes user-table fixtures (e.g. record-count assertions in behat suites of other
    // plugins). It is created on demand by guest_supportuser wherever it is needed.
    if (!defined('BEHAT_SITE_RUNNING') && !defined('BEHAT_UTIL') && !defined('PHPUNIT_TEST')) {
        $guestuser = new local_edusupport\guest_supportuser();
        $guestuser->create_guestuser_if_inextistant();
    }

    // Ensure, that this role is assigned in the required context levels.
    $chk = $DB->get_record('role_context_levels', ['roleid' => $role->id, 'contextlevel' => CONTEXT_MODULE]);
    if (empty($chk->id)) {
        $DB->insert_record('role_context_levels', ['roleid' => $role->id, 'contextlevel' => CONTEXT_MODULE]);
    }

    // Ensure, that this role has the required capabilities.
    $ctx = \context_system::instance();
    $caps = [
        'forumreport/summary:view',
        'mod/forum:addnews',
        'mod/forum:addquestion',
        'mod/forum:allowforcesubscribe',
        'mod/forum:canoverridecutoff',
        'mod/forum:canoverridediscussionlock',
        'mod/forum:canposttomygroups',
        'mod/forum:createattachment',
        'mod/forum:deleteownpost',
        'mod/forum:exportdiscussion',
        'mod/forum:exportforum',
        'mod/forum:exportownpost',
        'mod/forum:exportpost',
        'mod/forum:grade',
        'mod/forum:managesubscriptions',
        'mod/forum:postprivatereply',
        'mod/forum:postwithoutthrottling',
        'mod/forum:rate',
        'mod/forum:readprivatereplies',
        'mod/forum:replynews',
        'mod/forum:replypost',
        'mod/forum:viewallratings',
        'mod/forum:viewanyrating',
        'mod/forum:viewdiscussion',
        'mod/forum:viewhiddentimedposts',
        'mod/forum:viewqandawithoutposting',
        'mod/forum:viewrating',
        'mod/forum:viewsubscribers',
        'moodle/course:view',
        'moodle/course:viewhiddencourses',
        'moodle/course:viewhiddensections',
        'moodle/course:viewhiddenuserfields',
        'moodle/course:viewparticipants',
        'moodle/site:accessallgroups',
        'moodle/user:readuserposts',
    ];
    foreach ($caps as $cap) {
        $params = [
            'contextid' => $ctx->id,
            'roleid' => $role->id,
            'capability' => $cap,
            'permission' => 1,
        ];
        $chk = $DB->get_record('role_capabilities', $params);
        if (empty($chk->id)) {
            $DB->insert_record('role_capabilities', $params + ['timemodified' => time(), 'modifierid' => 2]);
        }
    }
}
