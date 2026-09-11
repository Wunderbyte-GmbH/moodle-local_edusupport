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
 * Privacy provider for local_edusupport.
 *
 * @package    local_edusupport
 * @copyright  2018 Digital Education Society (http://www.dibig.at)
 * @author     Robert Schrenk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace local_edusupport\privacy;

use context;
use context_user;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use local_edusupport\accountmanager;
use local_edusupport\lib;

/**
 * Privacy provider for local_edusupport.
 *
 * Everything the plugin stores about a person concerns their part in the support: where they
 * support, which issues they handle or follow, and which support forums name them. None of it
 * belongs to a course, so all of it lives in the person's own user context. The requests
 * themselves are forum posts and are covered by mod_forum.
 *
 * @package    local_edusupport
 * @copyright  2020 Center for Learningmanagement (www.lernmanagement.at)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Describe the personal data local_edusupport stores.
     *
     * @param collection $collection the collection to add the descriptions to.
     * @return collection the collection with this plugin's descriptions added.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'local_edusupport_supporters',
            [
                'courseid' => 'privacy:metadata:edusupport:courseid',
                'userid' => 'privacy:metadata:edusupport:userid',
                'supportlevel' => 'privacy:metadata:edusupport:supportlevel',
                'holidaymode' => 'privacy:metadata:edusupport:holidaymode',
                'autoassign' => 'privacy:metadata:edusupport:autoassign',
            ],
            'privacy:metadata:edusupport:supporters'
        );

        $collection->add_database_table(
            'local_edusupport_subscr',
            [
                'issueid' => 'privacy:metadata:edusupport:issueid',
                'discussionid' => 'privacy:metadata:edusupport:discussionid',
                'userid' => 'privacy:metadata:edusupport:userid',
            ],
            'privacy:metadata:edusupport:subscr'
        );

        $collection->add_database_table(
            'local_edusupport_issues',
            [
                'discussionid' => 'privacy:metadata:edusupport:discussionid',
                'currentsupporter' => 'privacy:metadata:edusupport:currentsupporter',
                'accountmanager' => 'privacy:metadata:edusupport:accountmanager',
                'priority' => 'privacy:metadata:edusupport:priority',
                'status' => 'privacy:metadata:edusupport:status',
                'timecreated' => 'privacy:metadata:edusupport:timecreated',
                'timemodified' => 'privacy:metadata:edusupport:timemodified',
            ],
            'privacy:metadata:edusupport:issues'
        );

        $collection->add_database_table(
            'local_edusupport',
            [
                'forumid' => 'privacy:metadata:edusupport:forumid',
                'dedicatedsupporter' => 'privacy:metadata:edusupport:dedicatedsupporter',
            ],
            'privacy:metadata:edusupport:supportforums'
        );

        return $collection;
    }

    /**
     * Whether the plugin holds anything at all about a person.
     *
     * @param int $userid
     * @return bool
     */
    protected static function has_data(int $userid): bool {
        global $DB;

        return $DB->record_exists('local_edusupport_supporters', ['userid' => $userid])
            || $DB->record_exists('local_edusupport_subscr', ['userid' => $userid])
            || $DB->record_exists_select(
                'local_edusupport_issues',
                'currentsupporter = :supporter OR accountmanager = :manager',
                ['supporter' => $userid, 'manager' => $userid]
            )
            || $DB->record_exists('local_edusupport', ['dedicatedsupporter' => $userid]);
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param int $userid the userid.
     * @return contextlist the list of contexts containing user info for the user.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        if (self::has_data($userid)) {
            $contextlist->add_user_context($userid);
        }
        return $contextlist;
    }

    /**
     * Add the users who have data in the given context to the user list.
     *
     * @param userlist $userlist the list to add the users to.
     * @return void
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if ($context instanceof context_user && self::has_data($context->instanceid)) {
            $userlist->add_user($context->instanceid);
        }
    }

    /**
     * Export all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts to export information for.
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        $userid = (int) $contextlist->get_user()->id;
        $context = self::own_context($contextlist, $userid);
        if (!$context) {
            return;
        }

        $supporter = [];
        foreach ($DB->get_records('local_edusupport_supporters', ['userid' => $userid], 'id') as $row) {
            $level = $row->courseid == lib::SYSTEM_COURSE_ID ? 'level:second' : 'level:first';
            $supporter[] = [
                'courseid' => $row->courseid,
                'level' => get_string($level, 'local_edusupport'),
                'supportlevel' => $row->supportlevel,
                'holidaymode' => empty($row->holidaymode) ? null : transform::datetime($row->holidaymode),
                'autoassign' => transform::yesno($row->autoassign),
            ];
        }
        self::export_entries($context, 'privacy:export:supporter', $supporter);

        $subscriptions = [];
        foreach ($DB->get_records('local_edusupport_subscr', ['userid' => $userid], 'id') as $row) {
            $subscriptions[] = [
                'issueid' => $row->issueid,
                'discussionid' => $row->discussionid,
            ];
        }
        self::export_entries($context, 'privacy:export:subscriptions', $subscriptions);

        $issues = [];
        $rows = $DB->get_records_select(
            'local_edusupport_issues',
            'currentsupporter = :supporter OR accountmanager = :manager',
            ['supporter' => $userid, 'manager' => $userid],
            'id'
        );
        foreach ($rows as $row) {
            $issues[] = [
                'issueid' => $row->id,
                'discussionid' => $row->discussionid,
                'currentsupporter' => transform::yesno($row->currentsupporter == $userid),
                'accountmanager' => transform::yesno($row->accountmanager == $userid),
                'priority' => $row->priority,
                'status' => $row->status,
                'timecreated' => empty($row->timecreated) ? null : transform::datetime($row->timecreated),
                'timemodified' => empty($row->timemodified) ? null : transform::datetime($row->timemodified),
            ];
        }
        self::export_entries($context, 'privacy:export:issues', $issues);

        $forums = [];
        foreach ($DB->get_records('local_edusupport', ['dedicatedsupporter' => $userid], 'id') as $row) {
            $forums[] = [
                'forumid' => $row->forumid,
                'courseid' => $row->courseid,
            ];
        }
        self::export_entries($context, 'privacy:export:dedicated', $forums);
    }

    /**
     * The user's own context, if it is among the approved ones.
     *
     * @param approved_contextlist $contextlist
     * @param int $userid
     * @return context_user|null
     */
    protected static function own_context(approved_contextlist $contextlist, int $userid): ?context_user {
        foreach ($contextlist as $context) {
            if ($context instanceof context_user && $context->instanceid == $userid) {
                return $context;
            }
        }
        return null;
    }

    /**
     * Write one kind of data below the plugin's folder, unless there is none.
     *
     * @param context $context
     * @param string $identifier string naming the folder.
     * @param array $entries
     * @return void
     */
    protected static function export_entries(context $context, string $identifier, array $entries): void {
        if (!$entries) {
            return;
        }
        writer::with_context($context)->export_data(
            [get_string('pluginname', 'local_edusupport'), get_string($identifier, 'local_edusupport')],
            (object) ['entries' => $entries]
        );
    }

    /**
     * Delete all user data for this context.
     *
     * @param context $context The context to delete data for.
     * @return void
     */
    public static function delete_data_for_all_users_in_context(context $context) {
        if ($context instanceof context_user) {
            static::delete_user_data($context->instanceid);
        }
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param approved_userlist $userlist The approved context and user information to delete information for.
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        $context = $userlist->get_context();
        if ($context instanceof context_user && in_array($context->instanceid, $userlist->get_userids())) {
            static::delete_user_data($context->instanceid);
        }
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts and user information to delete information for.
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        $userid = (int) $contextlist->get_user()->id;
        if (self::own_context($contextlist, $userid)) {
            static::delete_user_data($userid);
        }
    }

    /**
     * Remove a person from the support system.
     *
     * Rows that only exist because of the person are deleted. Issues and support forums are
     * kept, because they belong to the people asking for help; only the reference to the person
     * is set back to 0, which everywhere in the plugin means "nobody". The observer for deleted
     * users calls this as well, so a deletion request and a deleted account end up the same.
     *
     * @param int $userid
     * @return void
     */
    public static function delete_user_data(int $userid): void {
        global $DB;

        $DB->delete_records('local_edusupport_supporters', ['userid' => $userid]);
        $DB->delete_records('local_edusupport_subscr', ['userid' => $userid]);
        $DB->set_field('local_edusupport_issues', 'currentsupporter', 0, ['currentsupporter' => $userid]);
        $DB->set_field('local_edusupport_issues', 'accountmanager', 0, ['accountmanager' => $userid]);
        $DB->set_field('local_edusupport', 'dedicatedsupporter', 0, ['dedicatedsupporter' => $userid]);
        accountmanager::delete_account_manager($userid);
    }
}
