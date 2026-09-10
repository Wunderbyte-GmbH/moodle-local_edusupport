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
 * Core library of the eduSupport plugin.
 *
 * @package    local_edusupport
 * @copyright  2020 Center for Learningmanagement (www.lernmanagement.at)
 * @author     Robert Schrenk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_edusupport;

use context_system;
use core\message\message;
use local_edusupport\task\reminder;
use local_edusupport\guest_supportuser;
use mod_forum\event\post_created;
use moodle_url;
use stdClass;

defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/mod/forum/lib.php');

define("ISSUE_STATUS_NOTSTARTED", 1);
define("ISSUE_STATUS_AWAITING_USER_REPLY", 2);
define("ISSUE_STATUS_ONGOING", 3);
define("ISSUE_STATUS_AWAITING_SUPPORT_ACTION", 4);
define("ISSUE_STATUS_CLOSED", 5);

/**
 * Core library of the eduSupport plugin.
 *
 * @package    local_edusupport
 * @copyright  2020 Center for Learningmanagement (www.lernmanagement.at)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class lib {
    /** @var int Course id standing for the site wide support team rather than a single course. */
    const SYSTEM_COURSE_ID = 1;

    /** @var string Prefix that marks a discussion name as a closed issue. */
    const CLOSED_PREFIX = "🔒 ";

    /** @var array Prefixes used by earlier versions, still present in existing discussion names. */
    const CLOSED_PREFIX_LEGACY = ["[Closed] "];

    /**
     * Perform some actions before the popup is rendered.
     */
    public static function before_popup() {
        global $CFG, $DB, $USER;
        $guestmode = get_config('local_edusupport', 'guestmodeenabled');
        if ($guestmode && (isguestuser() || !isloggedin())) {
            $guestuser = new guest_supportuser();
            $user = $guestuser->get_support_guestuser();
        } else {
            $user = $USER;
        }
        $centralforum = get_config('local_edusupport', 'centralforum');
        if (!empty($centralforum) && self::is_supportforum($centralforum)) {
            $forum = $DB->get_record('forum', ['id' => $centralforum]);
            $coursectx = \context_course::instance($forum->course);
            if (!empty($coursectx->id)) {
                if (!is_enrolled($coursectx, $user, '', true)) {
                    // Enrol as student.
                    self::course_manual_enrolments([$forum->course], [$user->id], 5);
                }
                require_once("$CFG->dirroot/group/lib.php");
                $groupname = fullname($user) . ' (' . $user->id . ')';
                $group = $DB->get_record('groups', ['courseid' => $forum->course, 'name' => $groupname]);
                if (empty($group->id)) {
                    // Create a group for this user.
                    $group = (object) [
                        'courseid' => $forum->course,
                        'name' => $groupname,
                        'description' => '',
                        'descriptionformat' => 1,
                        'timecreated' => time(),
                        'timemodified' => time(),
                    ];
                    $group->id = groups_create_group($group, false);
                }
                if (!empty($group->id)) {
                    groups_add_member($group, $user);
                }
            }
        }
    }

    /**
     * Check whether the current user may configure the support forums of a course.
     *
     * @param int $courseid
     * @return bool
     */
    public static function can_config_course($courseid): bool {
        global $USER;
        if (self::can_config_global()) {
            return true;
        }
        $context = \context_course::instance($courseid);
        return is_enrolled($context, $USER, 'moodle/course:activityvisibility');
    }

    /**
     * Check whether the current user may configure the plugin site wide.
     *
     * @return bool
     */
    public static function can_config_global(): bool {
        return \is_siteadmin();
    }

    /**
     * Add the closed-marker to a discussion name.
     *
     * Any previously set marker - including the ones used by earlier versions - is removed first,
     * so the name never ends up carrying two prefixes.
     *
     * @param string $name the discussion name.
     * @return string the name including the closed-marker.
     */
    public static function add_closed_prefix(string $name): string {
        return self::CLOSED_PREFIX . self::strip_closed_prefix($name);
    }

    /**
     * Remove the closed-marker from a discussion name.
     *
     * Also removes the markers used by earlier versions, so issues that were closed before the
     * marker changed can still be reopened cleanly.
     *
     * @param string $name the discussion name.
     * @return string the name without any closed-marker.
     */
    public static function strip_closed_prefix(string $name): string {
        $prefixes = array_merge([self::CLOSED_PREFIX], self::CLOSED_PREFIX_LEGACY);
        foreach ($prefixes as $prefix) {
            if ($prefix !== '' && str_starts_with($name, $prefix)) {
                return substr($name, strlen($prefix));
            }
        }
        return $name;
    }

    /**
     * Rewrite discussion names that still carry the closed-marker of an earlier version.
     *
     * Only discussions that are registered as an issue are touched.
     *
     * @return int the number of discussions that were changed.
     */
    public static function migrate_legacy_closed_prefixes(): int {
        global $DB;
        $changed = 0;
        foreach (self::CLOSED_PREFIX_LEGACY as $legacyprefix) {
            $like = $DB->sql_like('fd.name', ':prefix');
            $sql = "SELECT fd.id, fd.name
                      FROM {forum_discussions} fd
                      JOIN {local_edusupport_issues} lei ON lei.discussionid = fd.id
                     WHERE $like";
            $params = ['prefix' => $DB->sql_like_escape($legacyprefix) . '%'];
            foreach ($DB->get_records_sql($sql, $params) as $discussion) {
                // The sql_like() match is case insensitive on most databases, so confirm it here.
                if (!str_starts_with($discussion->name, $legacyprefix)) {
                    continue;
                }
                $DB->set_field(
                    'forum_discussions',
                    'name',
                    self::add_closed_prefix($discussion->name),
                    ['id' => $discussion->id]
                );
                $changed++;
            }
        }
        return $changed;
    }

    /**
     * Close an issue.
     *
     * @param int discussionid.
     **/
    public static function close_issue($discussionid) {
        global $CFG, $DB, $USER;

        $discussion = $DB->get_record('forum_discussions', ['id' => $discussionid]);
        $issue = self::get_issue($discussionid);
        if (!self::is_supportforum($discussion->forum)) {
            return false;
        }
        // The ticket system is the platform team's tool.
        if (!self::is_second_level()) {
            return false;
        }

        // 2.) create a post that we closed that issue.
        self::create_post(
            $issue->discussionid,
            get_string(
                'issue_closed:post',
                'local_edusupport',
                [
                    'fromuserfullname' => \fullname($USER),
                    'fromuserid' => $USER->id,
                    'wwwroot' => $CFG->wwwroot,
                ]
            ),
            get_string('issue_closed:subject', 'local_edusupport'),
            get_config('local_edusupport', 'sendissueclosed')
        );

        // 3.) remove all supporters from the abo-list
        $DB->delete_records('local_edusupport_subscr', ['discussionid' => $discussionid]);

        $issue->priority = 0;
        $issue->discussionid = $discussionid;
        $issue->status = 5;
        $issue->timemodified = time();
        // 4.) remove issue-link from database
        $DB->update_record('local_edusupport_issues', $issue);
        // Mark post as closed.
        $discussion->name = self::add_closed_prefix($discussion->name);
        $discussion->modified = time();
        $DB->update_record('forum_discussions', $discussion);
        return true;
    }

    /**
     * Delete an issue of which the discussion has been deleted.
     *
     * @param discussionid.
     **/
    public static function delete_issue($discussionid) {
        global $CFG, $DB, $USER;

        $issue = $DB->get_record('local_edusupport_issues', ['discussionid' => $discussionid]);
        if (!empty($issue->id)) {
            // Remove all supporters from the abo-list.
            $DB->delete_records('local_edusupport_subscr', ['discussionid' => $discussionid]);
            // Delete issue.
            $DB->delete_records('local_edusupport_issues', ['discussionid' => $discussionid]);
        }
        return true;
    }

    /**
     * Get the helpbutton menu from cache or generate it.
     */
    public static function get_supportmenu() {
        global $CFG, $OUTPUT, $USER;
        $cache = \cache::make('local_edusupport', 'supportmenu');
        if (!empty($cache->get($USER->id))) {
            return $cache->get($USER->id);
        }

        $extralinksfromconfig = get_config('local_edusupport', 'extralinks');
        $extralinks = [];
        if (!empty($extralinksfromconfig)) {
            $extralinksfromconfig = explode("\n", $extralinksfromconfig);
            for ($a = 0; $a < count($extralinksfromconfig); $a++) {
                $tmp = explode('|', $extralinksfromconfig[$a]);
                $extralink = (object) ['id' => $a];
                if (!empty($tmp[0])) {
                    $extralink->name = $tmp[0];
                }
                if (!empty($tmp[1])) {
                    $extralink->url = $tmp[1];
                }
                if (!empty($tmp[2])) {
                    $extralink->faicon = $tmp[2];
                }
                if (!empty($tmp[3])) {
                    $extralink->target = trim($tmp[3]);
                }
                $extralinks[] = $extralink;
            }
        }

        $showissues = null;
        $issuesurl = null;
        // Check if the user is part of the support team or an admin.
        // We only show the "issues" navbar button starting from Moodle 4.0.
        if ($CFG->version >= 2022041900 && (is_siteadmin() || self::is_second_level())) {
            $showissues = true;
            $issuesurl = new moodle_url('/local/edusupport/issues.php');
        }

        $prepageenabled = get_config('local_edusupport', 'enableprepage');
        $nav = $OUTPUT->render_from_template(
            'local_edusupport/helpbutton',
            [
            'extralinks' => $extralinks,
            'hasextralinks' => count($extralinks) > 0,
            'prepageenabled' => $prepageenabled,
            'showissues' => $showissues,
            'issuesurl' => $issuesurl,
            ]
        );
        $cache->set($USER->id, $nav);
        return $nav;
    }

    /**
     * Close an issue.
     *
     * @param int discussionid
     **/
    public static function reopen_issue(int $discussionid): bool {
        global $DB;
        $discussion = $DB->get_record('forum_discussions', ['id' => $discussionid]);
        $issue = self::get_issue($discussionid);

        $issue->priority = 1;
        $issue->status = ISSUE_STATUS_AWAITING_SUPPORT_ACTION;
        $issue->discussionid = $discussionid;
        $issue->timemodified = time();

        // 4.) remove issue-link from database.
        $DB->update_record('local_edusupport_issues', $issue);

        // We also want to send reminders when we re-open an issue.
        self::send_reminder($issue->id);

        // Remove the closed-marker again.
        $discussion->name = self::strip_closed_prefix($discussion->name);
        $discussion->modified = time();
        $DB->update_record('forum_discussions', $discussion);
        return true;
    }

    /**
     * Enrols users to specific courses
     *
     * @param array $courseids containing courseids or a single courseid
     * @param array $userids containing userids or a single userid
     * @param int $roleid roleid to assign, or -1 if wants to unenrolroleid to assign, or -1 if wants to unenrol
     * @return bool
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public static function course_manual_enrolments(array $courseids, array $userids, int $roleid): bool {
        global $DB;
        if (!is_array($courseids)) {
            $courseids = [$courseids];
        }
        if (!is_array($userids)) {
            $userids = [$userids];
        }

        // Check manual enrolment plugin instance is enabled/exist.
        $enrol = enrol_get_plugin('manual');
        if (empty($enrol)) {
            throw new \moodle_exception('manualpluginnotinstalled', 'enrol_manual');
        }
        $failures = 0;
        $instances = [];
        foreach ($courseids as $courseid) {
            // Check if course exists.
            $course = $DB->get_record('course', ['id' => $courseid], '*', IGNORE_MISSING);
            if (empty($course->id)) {
                continue;
            }
            if (empty($instances[$courseid])) {
                $instances[$courseid] = self::get_enrol_instance($courseid);
            }

            foreach ($userids as $userid) {
                $user = $DB->get_record('user', ['id' => $userid]);
                if (empty($user->id)) {
                    continue;
                }
                if ($roleid == -1) {
                    $enrol->unenrol_user($instances[$courseid], $userid);
                } else {
                    $enrol->enrol_user($instances[$courseid], $userid, $roleid, time(), 0, ENROL_USER_ACTIVE);
                }
            }
        }
        return ($failures == 0);
    }

    /**
     * Answer to the original discussion post of a discussion
     *
     * @param int $discussionid
     * @param string $text as cpmtent
     * @param string $subject subject for post, if not given first 30 chars of text are used
     * @param int $sendemail 0 do not send, 1 send e-mail.
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public static function create_post(int $discussionid, string $text, string $subject = "", int $sendemail = 1): void {
        global $DB, $USER;

        $guestmode = get_config('local_edusupport', 'guestmodeenabled');
        if ($guestmode && (isguestuser() || !isloggedin())) {
            $guestuser = new guest_supportuser();
            $user = $guestuser->get_support_guestuser();
        } else {
            $user = $USER;
        }
        if (empty($subject)) {
            $subject = substr($text, 0, 30);
        }
        $discussion = $DB->get_record('forum_discussions', ['id' => $discussionid]);
        $post = $DB->get_record('forum_posts', ['discussion' => $discussionid, 'parent' => 0]);
        $post->parent = $post->id;
        unset($post->id);
        $post->userid = $user->id;
        $post->created = time();
        $post->modified = time();
        $post->mailed = ($sendemail) ? 0 : 1;
        $post->subject = $subject;
        $post->message = $text;
        $post->messageformat = 1;
        $post->id = $DB->insert_record('forum_posts', $post, 1);

        $forum = $DB->get_record('forum', ['id' => $discussion->forum]);

        $dbcontext = $DB->get_record('course_modules', ['course' => $discussion->course, 'instance' => $discussion->forum]);
        $context = \context_module::instance($dbcontext->id);
        $eventparams = [
            'context' => $context,
            'objectid' => $post->id,
            'other' => [
                'discussionid' => $discussion->id,
                'forumid' => $discussion->forum,
                'forumtype' => $forum->type,
            ],
        ];

        $event = post_created::create($eventparams);
        $event->add_record_snapshot('forum_posts', $post);
        $event->trigger();
    }

    /**
     * Clones an object to reveal private fields.
     *
     * @param array $object
     * @return array
     */
    public static function expose_properties(array $object = []): array {
        $object = (array) $object;
        $keys = array_keys($object);
        foreach ($keys as $key) {
            $xkey = explode("\0", $key);
            $xkey = $xkey[count($xkey) - 1];
            $object[$xkey] = $object[$key];
            unset($object[$key]);
            if (is_object($object[$xkey])) {
                $object[$xkey] = self::expose_properties($object[$xkey]);
            }
        }
        return $object;
    }

    /**
     * Checks for groupmode in a forum and lists available groups of this user.
     *
     * @return array of groups.
     **/
    public static function get_groups_for_user(int $forumid): array {
        // Store rating if we are permitted to.
        global $CFG, $DB, $USER;
        $guestmode = get_config('local_edusupport', 'guestmodeenabled');
        if ((empty($USER->id) || isguestuser()) && !$guestmode) {
            return [];
        }
        if ($guestmode && (!isloggedin() || isguestuser())) {
            $supportuser = new guest_supportuser();
            $user = $supportuser->get_support_guestuser();
        } else {
            $user = $USER;
        }

        $forum = $DB->get_record('forum', ['id' => $forumid]);
        $course = $DB->get_record('course', ['id' => $forum->course]);

        $cm = \get_coursemodule_from_instance('forum', $forumid);

        $groupmode = \groups_get_activity_groupmode($cm);
        // If we do not use groups in this forum, return without groups.
        if (empty($groupmode)) {
            return [];
        }

        // We do not use the function groups_get_user_groups, as it does not
        // return groups that don't have members!!
        // phpcs:ignore Squiz.PHP.CommentedOutCode.Found
        /* $groupsfromdb = \groups_get_user_groups($course->id); */
        $groupsfromdb = $DB->get_records('groups', ['courseid' => $course->id]);
        if (count($groupsfromdb) == 0) {
            return [];
        }

        require_once($CFG->dirroot . '/mod/forum/lib.php');

        $groups = [];
        foreach ($groupsfromdb as $k => $group) {
            $ismember = $DB->get_record('groups_members', ['groupid' => $group->id, 'userid' => $user->id]);
            if (!empty($ismember->id)) {
                // Only allow the group generated for the user.
                if ($group->name == fullname($user) . ' (' . $user->id . ')') {
                    $groups[$k] = $group;
                }
            }
        }
        return $groups;
    }

    /**
     * Get the issue for this discussionid.
     *
     * @param int $discussionid
     * @param bool $createifnotexist
     * @param $keyvaluepair
     * @return false|mixed|object|stdClass|void
     * @throws \dml_exception
     */
    public static function get_issue(int $discussionid, bool $createifnotexist = false, $keyvaluepair = null) {
        global $DB;
        if (empty($discussionid)) {
            return;
        }
        $issue = $DB->get_record('local_edusupport_issues', ['discussionid' => $discussionid]);
        if (empty($issue->id) && !empty($createifnotexist)) {
            $issue = (object) [
                'discussionid' => $discussionid,
                'currentsupporter' => 0,
                'created' => time(),
            ];
            if (isset($keyvaluepair)) {
                $issue->{$keyvaluepair->key} = $keyvaluepair->value;
            }
            $issue->timecreated = time();
            $issue->timemodified = time();
            $issue->id = $DB->insert_record('local_edusupport_issues', $issue);
        }
        return $issue;
    }

    /**
     * Get potential targets of a user.
     *
     * @param int $userid if empty will use current user.
     * @return array containing forums and their possible groups.
     */
    public static function get_potentialtargets(int $userid = 0): array {
        global $DB, $USER;
        $guestmode = get_config('local_edusupport', 'guestmodeenabled');
        if (empty($userid)) {
            if ($guestmode && (isguestuser() || !isloggedin())) {
                $guestuser = new guest_supportuser();
                $guestsupportuser = $guestuser->get_support_guestuser();
                $userid = $guestsupportuser->id;
            } else {
                $userid = $USER->id;
            }
        }

        $forums = [];
        $courseids = implode(',', array_keys(enrol_get_all_users_courses($userid)));
        if (strlen($courseids) > 0) {
            $sql = " SELECT f.id,f.name,f.course
                        FROM {local_edusupport} be, {forum} f, {course} c
                        WHERE f.course=c.id
                            AND be.forumid=f.id
                            AND c.id IN ($courseids)
                        ORDER BY c.fullname ASC, f.name ASC";
            $forumsfromdb = $DB->get_records_sql($sql, []);
            $delimiter = ' > ';
            foreach ($forumsfromdb as &$forum) {
                $course = $DB->get_record('course', ['id' => $forum->course], 'id,fullname');
                $coursecontext = \context_course::instance($forum->course);
                if (empty($coursecontext->id)) {
                    continue;
                }

                $fcm = get_coursemodule_from_instance('forum', $forum->id, 0, false, MUST_EXIST);
                $fctx = \context_module::instance($fcm->id);
                $modinfo = get_fast_modinfo($course);
                $cm = $modinfo->get_cm($fcm->id);

                if ($cm->uservisible && has_capability('mod/forum:startdiscussion', $fctx, $userid)) {
                    $forum->name = $course->fullname . $delimiter . $forum->name;
                    $forum->postto2ndlevel = has_capability('local/edusupport:canforward2ndlevel', $coursecontext, $userid);
                    $forum->potentialgroups = self::get_groups_for_user($forum->id);
                    $forums[$forum->id] = $forum;
                }
            }
        }
        return $forums;
    }

    /**
     * Get issues closed a month ago
     *
     * @return array containing discussionids of closed and expired issues.
     */
    public static function get_expiredissues() {
        global $DB;
        $time = get_config('local_edusupport', 'deletethreshhold');
        $expirationtime = time() - $time;
        if (!$time || $time == 0) {
            $expirationtime = 0;
        }

        $sql = "SELECT edu.id, edu.discussionid, edu.priority, f.id, f.timemodified
                FROM {local_edusupport_issues} edu
                JOIN {forum_discussions} f
                    ON edu.discussionid = f.id
                WHERE edu.priority = 0
                AND f.timemodified < {$expirationtime}";
        $records = $DB->get_records_sql($sql);
        return $records;
    }

    /**
     * Bring the supporter table into a shape that tolerates a unique key on (courseid, userid).
     *
     * Duplicates are possible in existing data because nothing ever enforced uniqueness and
     * because set_supporter() decided on an existing row by looking at supportlevel rather
     * than at the row id. Order matters here: rows have to be normalised and merged before
     * the index is added, or the upgrade dies half way through.
     *
     * @return int the number of rows that were removed.
     */
    public static function deduplicate_supporters(): int {
        global $DB;

        $transaction = $DB->start_delegated_transaction();

        // A courseid of 0 was never a course. It always meant the platform wide team.
        $DB->set_field('local_edusupport_supporters', 'courseid', self::SYSTEM_COURSE_ID, ['courseid' => 0]);

        $removed = 0;

        // Rows pointing at users or courses that no longer exist.
        $orphans = "SELECT s.id
                      FROM {local_edusupport_supporters} s
                 LEFT JOIN {user} u ON u.id = s.userid
                 LEFT JOIN {course} c ON c.id = s.courseid
                     WHERE u.id IS NULL
                        OR u.deleted = 1
                        OR (s.courseid <> :systemcourseid AND c.id IS NULL)";
        $orphanids = array_keys($DB->get_records_sql($orphans, ['systemcourseid' => self::SYSTEM_COURSE_ID]));
        if (!empty($orphanids)) {
            [$insql, $inparams] = $DB->get_in_or_equal($orphanids, SQL_PARAMS_NAMED);
            $DB->delete_records_select('local_edusupport_supporters', "id $insql", $inparams);
            $removed += count($orphanids);
        }

        // Merge what is left, one group of (courseid, userid) at a time.
        $groups = $DB->get_records_sql(
            "SELECT MIN(id) AS lowestid, courseid, userid, COUNT(*) AS duplicates
               FROM {local_edusupport_supporters}
           GROUP BY courseid, userid
             HAVING COUNT(*) > 1"
        );
        foreach ($groups as $group) {
            $rows = $DB->get_records(
                'local_edusupport_supporters',
                ['courseid' => $group->courseid, 'userid' => $group->userid],
                'id ASC'
            );

            // The support level is a free text label without meaning to the code, but keeping
            // a filled one loses less than an empty one. Among equals the oldest row wins.
            $winner = null;
            foreach ($rows as $row) {
                if ($winner === null || ($winner->supportlevel === '' && $row->supportlevel !== '')) {
                    $winner = $row;
                }
            }

            // An ongoing holiday must survive the merge, and so must being assignable -
            // losing either would quietly change who escalation can reach.
            $holidaymode = 0;
            $autoassign = 0;
            foreach ($rows as $row) {
                $holidaymode = max($holidaymode, (int) $row->holidaymode);
                $autoassign = max($autoassign, (int) $row->autoassign);
            }
            if ((int) $winner->holidaymode !== $holidaymode) {
                $DB->set_field('local_edusupport_supporters', 'holidaymode', $holidaymode, ['id' => $winner->id]);
            }
            if ((int) $winner->autoassign !== $autoassign) {
                $DB->set_field('local_edusupport_supporters', 'autoassign', $autoassign, ['id' => $winner->id]);
            }

            $DB->delete_records_select(
                'local_edusupport_supporters',
                'courseid = :courseid AND userid = :userid AND id <> :winner',
                ['courseid' => $group->courseid, 'userid' => $group->userid, 'winner' => $winner->id]
            );
            $removed += count($rows) - 1;
        }

        $transaction->allow_commit();

        return $removed;
    }

    /**
     * Get the platform wide support team, which is the second level.
     *
     * Note that supportlevel is a free text label people fill in as they please. It groups
     * the team in the assignment dialogue and carries no meaning for the code.
     *
     * @param bool $assignableonly only those escalation may pick automatically.
     * @param bool $availableonly leave out whoever is on holiday right now.
     * @return array of supporter records, keyed by row id.
     */
    public static function get_second_level(bool $assignableonly = false, bool $availableonly = false): array {
        global $DB;

        $wheres = ['courseid = :courseid'];
        $params = ['courseid' => self::SYSTEM_COURSE_ID];

        if ($assignableonly) {
            $wheres[] = 'autoassign = 1';
        }
        if ($availableonly) {
            $wheres[] = 'holidaymode < :now';
            $params['now'] = time();
        }

        return $DB->get_records_select('local_edusupport_supporters', implode(' AND ', $wheres), $params);
    }

    /**
     * Get the first level support of a single course.
     *
     * @param int $courseid
     * @return array of supporter records, keyed by row id.
     */
    public static function get_first_level(int $courseid): array {
        global $DB;

        if ($courseid == self::SYSTEM_COURSE_ID) {
            // That id is the sentinel for the platform team, it is never a course.
            return [];
        }

        return $DB->get_records('local_edusupport_supporters', ['courseid' => $courseid]);
    }

    /**
     * Check whether a user belongs to the platform wide support team.
     *
     * @param int $userid check a particular user, or the current one.
     * @return bool
     */
    public static function is_second_level(int $userid = 0): bool {
        global $DB, $USER;

        $userid = empty($userid) ? $USER->id : $userid;

        return $DB->record_exists('local_edusupport_supporters', [
            'userid' => $userid,
            'courseid' => self::SYSTEM_COURSE_ID,
        ]);
    }

    /**
     * Check whether a user is first level support of a particular course.
     *
     * @param int $userid
     * @param int $courseid
     * @return bool
     */
    public static function is_first_level(int $userid, int $courseid): bool {
        global $DB;

        if ($courseid == self::SYSTEM_COURSE_ID) {
            return false;
        }

        return $DB->record_exists('local_edusupport_supporters', [
            'userid' => $userid,
            'courseid' => $courseid,
        ]);
    }

    /**
     * Get the users of a course that may be made first level support.
     *
     * Eligible is whoever is enrolled and can both start a discussion in a forum and see
     * hidden activities. That pair is what separates teaching staff from students: a student
     * may start discussions too, but cannot see hidden activities.
     *
     * The two capabilities cannot be handed to one get_enrolled_users() call, because a list
     * of capabilities there means "one of these is enough" (see get_with_capability_join() in
     * lib/accesslib.php). The two sets are intersected in a single query instead.
     *
     * @param int $courseid
     * @param string $search optional name or identity search for an autocomplete.
     * @param int $limit optional maximum number of users to return.
     * @return array of user records, keyed by user id.
     */
    public static function get_assignable_users(int $courseid, string $search = '', int $limit = 0): array {
        global $DB;

        $context = \context_course::instance($courseid);
        [$postsql, $postparams] = get_enrolled_sql($context, 'mod/forum:startdiscussion', 0, true);
        [$staffsql, $staffparams] = get_enrolled_sql($context, 'moodle/course:viewhiddenactivities', 0, true);

        $wheres = ["u.id IN ($postsql)", "u.id IN ($staffsql)", 'u.deleted = 0'];
        $params = array_merge($postparams, $staffparams);

        if ($search !== '') {
            [$searchsql, $searchparams] = users_search_sql($search, 'u', USER_SEARCH_CONTAINS);
            $wheres[] = $searchsql;
            $params = array_merge($params, $searchparams);
        }

        $namefields = \core_user\fields::for_name()->get_sql('u')->selects;
        $sql = "SELECT u.id, u.email $namefields
                  FROM {user} u
                 WHERE " . implode(' AND ', $wheres) . "
              ORDER BY u.lastname ASC, u.firstname ASC";

        return $DB->get_records_sql($sql, $params, 0, $limit);
    }

    /**
     * Re-sync the support role in the forums of a single course.
     *
     * supportforum_rolecheck() without a forum walks every support forum on the site, which
     * on a platform with many of them is far too slow to sit inside a request. Assigning
     * somebody in one course only ever affects that course's forums.
     *
     * @param int $courseid
     * @return void
     */
    public static function rolecheck_course(int $courseid): void {
        global $DB;

        $forums = $DB->get_records('local_edusupport', ['courseid' => $courseid], '', 'id, forumid');
        foreach ($forums as $forum) {
            self::supportforum_rolecheck($forum->forumid);
        }
    }

    /**
     * Check whether somebody may assign the first level support of a course.
     *
     * @param int $courseid
     * @param int $userid check a particular user, or the current one.
     * @return bool
     */
    public static function can_assign_first_level(int $courseid, int $userid = 0): bool {
        global $USER;

        $userid = empty($userid) ? $USER->id : $userid;

        return has_capability(
            'local/edusupport:assignsupporters',
            \context_course::instance($courseid),
            $userid
        );
    }

    /**
     * Set the first level support of a course to exactly the given users.
     *
     * Anybody not on the list loses the assignment, anybody on it who is not eligible is
     * refused. The forum role is re-synced afterwards, for this course only.
     *
     * @param int $courseid
     * @param array $userids the users that should support this course from now on.
     * @return array with the keys added, removed and refused, each holding user ids.
     */
    public static function assign_first_level(int $courseid, array $userids): array {
        global $DB, $USER;

        $result = ['added' => [], 'removed' => [], 'refused' => []];
        if ($courseid == self::SYSTEM_COURSE_ID) {
            // That id is the platform team, which is maintained elsewhere.
            return $result;
        }

        $eligible = self::get_assignable_users($courseid);
        $current = [];
        foreach (self::get_first_level($courseid) as $row) {
            $current[$row->userid] = $row;
        }

        $wanted = [];
        foreach ($userids as $userid) {
            $userid = (int) $userid;
            if (!isset($eligible[$userid])) {
                $result['refused'][] = $userid;
                continue;
            }
            $wanted[$userid] = $userid;
        }

        $context = \context_course::instance($courseid);

        foreach ($wanted as $userid) {
            if (isset($current[$userid])) {
                continue;
            }
            $DB->insert_record('local_edusupport_supporters', (object) [
                'courseid' => $courseid,
                'userid' => $userid,
                'supportlevel' => '',
                'holidaymode' => 0,
                'autoassign' => 0,
            ]);
            $result['added'][] = $userid;
            event\supportuser_added::create([
                'objectid' => $courseid,
                'context' => $context,
                'relateduserid' => $userid,
                'other' => ['supportuserid' => $userid, 'supportlevel' => ''],
            ])->trigger();
        }

        foreach ($current as $userid => $row) {
            if (isset($wanted[$userid])) {
                continue;
            }
            $DB->delete_records('local_edusupport_supporters', ['id' => $row->id]);
            $result['removed'][] = $userid;
            event\supportuser_deleted::create([
                'objectid' => $courseid,
                'context' => $context,
                'relateduserid' => $userid,
                'other' => ['supportuserid' => $userid, 'supportlevel' => $row->supportlevel],
            ])->trigger();
        }

        if (!empty($result['added']) || !empty($result['removed'])) {
            self::rolecheck_course($courseid);
        }

        return $result;
    }

    /**
     * Checks if a user belongs to the support team.
     *
     * @deprecated since 2.8.0. The two levels are separate, so ask for the one you mean:
     *             is_second_level() for the platform team, is_first_level() for a course.
     *
     * @param int $userid check particular user, or current user
     * @param int $courseid check for particular course
     * @param bool $includeglobalteam if checking for particular course, also include global team.
     * @return bool
     */
    public static function is_supportteam($userid = 0, $courseid = 0, $includeglobalteam = true) {
        global $USER;

        $userid = empty($userid) ? $USER->id : $userid;

        if ($courseid > 0 && !$includeglobalteam) {
            return self::is_first_level($userid, $courseid);
        }
        if ($courseid > 0) {
            return self::is_second_level($userid) || self::is_first_level($userid, $courseid);
        }

        return self::is_second_level($userid);
    }

    /**
     * Checks if a given forum is used as support-forum.
     *
     * @param forumid.
     * @return true or false.
     */
    public static function is_supportforum($forumid) {
        global $DB;
        $chk = $DB->get_record('local_edusupport', ['forumid' => $forumid]);
        return !empty($chk->id);
    }

    /**
     * Get the first level support of a support forum.
     *
     * First level is assigned explicitly per course, which is what carries the per school
     * model: every school has its own support course, and the people named there answer the
     * requests filed in it. Only when they cannot help does an issue get escalated to the
     * second level, the platform wide team kept under the sentinel course id.
     *
     * An empty result means nobody was named, and requests escalate straight away.
     *
     * @param object $forum the support forum.
     * @return array of user records, keyed by user id.
     */
    public static function get_course_supporters($forum) {
        global $DB;

        $rows = self::get_first_level($forum->course);
        if (empty($rows)) {
            return [];
        }

        [$insql, $inparams] = $DB->get_in_or_equal(array_column($rows, 'userid'), SQL_PARAMS_NAMED);

        return $DB->get_records_select('user', "id $insql AND deleted = 0", $inparams);
    }

    /**
     * Get the enrol instance for manual enrolments of a course, or create one.
     *
     * @param courseid
     * @return object enrolinstance
     */
    private static function get_enrol_instance($courseid) {
        // Check manual enrolment plugin instance is enabled/exist.
        $enrol = enrol_get_plugin('manual');
        if (empty($enrol)) {
            throw new \moodle_exception('manualpluginnotinstalled', 'enrol_manual');
        }
        $instance = null;
        $enrolinstances = enrol_get_instances($courseid, false);
        foreach ($enrolinstances as $courseenrolinstance) {
            if ($courseenrolinstance->enrol == "manual") {
                return $courseenrolinstance;
            }
        }
        if (empty($instance)) {
            $course = get_course($courseid);
            $enrol->add_default_instance($course);
            return self::get_enrol_instance($courseid);
        }
    }

    /**
     * Similar to close_issue, but can be done by a trainer in the supportforum.
     *
     * @param discussionid.
     **/
    public static function revoke_issue($discussionid): bool {
        global $CFG, $DB, $USER;

        $discussion = $DB->get_record('forum_discussions', ['id' => $discussionid]);
        $issue = self::get_issue($discussionid);
        if (!self::is_supportforum($discussion->forum)) {
            return false;
        }
        // Check if the user taking the action has trainer permissions.
        $coursecontext = \context_course::instance($discussion->course);
        if (!has_capability('local/edusupport:canforward2ndlevel', $coursecontext)) {
            return false;
        }

        // 2.) create a post that we closed that issue.
        self::create_post(
            $issue->discussionid,
            get_string(
                'issue_revoke:post',
                'local_edusupport',
                [
                    'fromuserfullname' => \fullname($USER),
                    'fromuserid' => $USER->id,
                    'wwwroot' => $CFG->wwwroot,
                ]
            ),
            get_string('issue_revoke:subject', 'local_edusupport'),
            get_config('local_edusupport', 'sendissueclosed')
        );

        // 3.) remove all supporters from the abo-list
        $DB->delete_records('local_edusupport_subscr', ['discussionid' => $discussionid]);

        // 4.) remove issue-link from database
        $DB->delete_records('local_edusupport_issues', ['discussionid' => $discussionid]);

        return true;
    }

    /**
     * Send an issue to 2nd level support.
     *
     * @param int $discussionid
     * @return true or false.
     */
    public static function set_2nd_level(int $discussionid, $keyvaluepair = null): bool {
        global $CFG, $DB, $USER, $PAGE, $SITE;

        if (!isset($PAGE->context)) {
            $PAGE->set_context(context_system::instance());
        }

        $discussion = $DB->get_record('forum_discussions', ['id' => $discussionid]);
        $issue = self::get_issue($discussionid, true, $keyvaluepair);
        if (!self::is_supportforum($discussion->forum)) {
            return false;
        }
        // Todo: MDL-000000 Only subscribe 1 person and make it responsible!
        $supportforum = $DB->get_record('local_edusupport', ['forumid' => $discussion->forum]);

        $respectholidays = !empty(get_config('local_edusupport', 'holidaymodeenabled'));
        $supporters = self::get_second_level(true, $respectholidays);
        if (empty($supporters) && $respectholidays) {
            // Everybody is away, so fall back to the whole assignable team rather than
            // leaving the request without anybody responsible.
            $supporters = self::get_second_level(true, false);
        }

        // The dedicated supporter is stored as a user id, while the rows are keyed by their
        // own id, so the person has to be looked up rather than indexed.
        $dedicated = null;
        if (!empty($supportforum->dedicatedsupporter)) {
            foreach ($supporters as $supporter) {
                if ($supporter->userid == $supportforum->dedicatedsupporter) {
                    $dedicated = $supporter;
                    break;
                }
            }
        }
        if ($dedicated === null && !empty($supporters)) {
            // Choose one supporter randomly.
            $keys = array_keys($supporters);
            $dedicated = $supporters[$keys[array_rand($keys)]];
        }

        if (!empty($dedicated->userid)) {
            $DB->set_field(
                'local_edusupport_issues',
                'currentsupporter',
                $dedicated->userid,
                ['discussionid' => $discussion->id]
            );
            self::subscription_add($discussionid, $dedicated->userid);
        }
        $centralforumid = get_config('local_edusupport', 'centralforum');
        $forum = $DB->get_record('forum', ['id' => $discussion->forum]);

        if (!empty($dedicated->userid) && get_config('local_edusupport', 'sendmsgonset2ndlvl')) {
            $subject = get_string('issue_assigned:subject', 'local_edusupport');
            $messagebody = get_string('issue_assign_nextlevel:post', 'local_edusupport', (object) [
                'fromuserfullname' => fullname($USER),
                'fromuserid' => $USER->id,
                'wwwroot' => $CFG->wwwroot,
                'sitename' => $SITE->fullname,
                'supportforumname' => $forum->name,
            ]);
            // Post assignment message to forum.
            self::create_post(
                $issue->discussionid,
                $messagebody,
                $subject,
                // Only send mail if setting is turned on!
                get_config('local_edusupport', 'sendsupporterassignments')
            );
            // In any case, send e-mail to the dedicated supporter.
            $issueurl = (new moodle_url('/local/edusupport/issue.php?d=' . $discussion->id))->out(false);
            $posthtml = get_string('issue:assigned', 'local_edusupport') . " " . $discussion->name . " $issueurl";
            $postsubject = $discussion->name;
            $msg = new message();
            $touser = $DB->get_record('user', ['id' => $dedicated->userid]);
            $msg->userfrom = $USER;
            $msg->userto = $touser;
            $msg->subject = $postsubject;
            $msg->fullmessage = $posthtml;
            $msg->fullmessageformat = FORMAT_PLAIN;
            $msg->fullmessagehtml = $posthtml;
            $msg->smallmessage = $postsubject;
            $msg->contexturl = $issueurl; // A relevant URL for the notification.
            $msg->contexturlname = 'Issue'; // Link title explaining where users get to for the contexturl.
            $msg->name = 'edusupport_issue';
            $msg->component = 'local_edusupport';
            $msg->notification = 1;
            message_send($msg);
        } else {
            self::set_status(ISSUE_STATUS_AWAITING_SUPPORT_ACTION, $issue->id);
        }

        return true;
    }

    /**
     * Check whether the current user may hand an issue to a particular supporter.
     *
     * Returning the reason rather than just a boolean lets callers tell the user what went
     * wrong. set_current_supporter() uses this to decide whether to act at all.
     *
     * @param int $discussionid the discussion behind the issue.
     * @param int $userid the user the issue should be handed to.
     * @return string|null the language string identifier of the refusal, or null if allowed.
     */
    public static function validate_supporter_assignment(int $discussionid, int $userid): ?string {
        global $DB;

        $discussion = $DB->get_record('forum_discussions', ['id' => $discussionid]);
        if (empty($discussion->id)) {
            return 'error:unknowndiscussion';
        }
        if (!self::is_supportforum($discussion->forum)) {
            return 'error:notasupportforum';
        }
        // Handing a ticket over happens inside the platform team, on both ends: first
        // level works in the forum and never takes ownership of a ticket.
        if (!self::is_second_level()) {
            return 'error:notasupporter';
        }
        if (!self::is_second_level($userid)) {
            return 'error:targetnotasupporter';
        }
        return null;
    }

    /**
     * Used by 2nd-level support to assign an issue to a particular person from 3rd level.
     *
     * @param int $discussionid.
     * @param int $userid.
     * @return bool true when the issue was handed over, false when that was refused.
     */
    public static function set_current_supporter(int $discussionid, int $userid): bool {
        global $CFG, $DB, $USER, $PAGE, $SITE;

        if (!isset($PAGE->context)) {
            $PAGE->set_context(context_system::instance());
        }

        if (self::validate_supporter_assignment($discussionid, $userid) !== null) {
            return false;
        }

        $discussion = $DB->get_record('forum_discussions', ['id' => $discussionid]);
        $forum = $DB->get_record('forum', ['id' => $discussion->forum]);
        $issue = self::get_issue($discussionid);

        // Set currentsupporter and add to subscribed users.
        $DB->set_field('local_edusupport_issues', 'currentsupporter', $userid, ['discussionid' => $discussion->id]);
        self::subscription_add($discussionid, $userid);

        $supporter = $DB->get_record('local_edusupport_supporters', ['userid' => $userid]);
        if (empty($supporter->supportlevel)) {
            $supporter->supportlevel = get_string('label:2ndlevel', 'local_edusupport');
        }
        $touser = $DB->get_record('user', ['id' => $userid]);
        $subject = get_string('issue_assigned:subject', 'local_edusupport');
        $messagebody = get_string(
            'issue_assign_nextlevel:post',
            'local_edusupport',
            (object) [
                'fromuserfullname' => \fullname($USER),
                'fromuserid' => $USER->id,
                'touserfullname' => \fullname($touser),
                'touserid' => $userid,
                'tosupportlevel' => $supporter->supportlevel,
                'wwwroot' => $CFG->wwwroot,
                'sitename' => $SITE->fullname,
                'supportforumname' => $forum->name,
            ]
        );
        // Post assignment message to forum.
        self::create_post(
            $discussionid,
            $messagebody,
            $subject,
            // Only send mail if setting is turned on!
            get_config('local_edusupport', 'sendsupporterassignments')
        );

        // In any case, send e-mail to the assigned supporter.
        $issueurl = (new moodle_url('/local/edusupport/issue.php?d=' . $discussion->id))->out(false);
        $posthtml = get_string('issue:assigned', 'local_edusupport') . " " . $discussion->name . " $issueurl";
        $postsubject = $discussion->name;
        $msg = new message();
        $touser = $DB->get_record('user', ['id' => $userid]);
        $msg->userfrom = $USER;
        $msg->userto = $touser;
        $msg->subject = $postsubject;
        $msg->fullmessage = $posthtml;
        $msg->fullmessageformat = FORMAT_PLAIN;
        $msg->fullmessagehtml = $posthtml;
        $msg->smallmessage = $postsubject;
        $msg->contexturl = $issueurl; // A relevant URL for the notification.
        $msg->contexturlname = 'Issue'; // Link title explaining where users get to for the contexturl.
        $msg->name = 'edusupport_issue';
        $msg->component = 'local_edusupport';
        $msg->notification = 1;
        message_send($msg);
        return true;
    }

    /**
     * Set the priority level of an issue.
     *
     * @param int $discussionid
     * @param int $priority
     * @return bool
     * @throws \dml_exception
     */
    public static function set_prioritylvl($discussionid, $priority): bool {
        global $DB;

        $issue = self::get_issue($discussionid);
        $issue->priority = $priority;
        $issue->discussionid = $discussionid;
        $issue->timemodified = time();
        $DB->update_record('local_edusupport_issues', $issue);
        return true;
    }

    /**
     * Add support user to the list of assigned users.
     *
     * @param int $discussionid
     * @param int $userid
     * @return bool|int
     */
    public static function subscription_add($discussionid, $userid = 0) {
        global $DB, $USER;
        if (empty($userid)) {
            $userid = $USER->id;
        }
        if (!self::is_second_level($userid)) {
            // Only the platform team follows tickets; first level works in the forum.
            return;
        }
        $issue = self::get_issue($discussionid);
        $subscription = $DB->get_record('local_edusupport_subscr', ['discussionid' => $discussionid, 'userid' => $userid]);
        if (empty($subscription->id)) {
            $subscription = (object) [
                'issueid' => $issue->id,
                'discussionid' => $discussionid,
                'userid' => $userid,
            ];
            $subscription->id = $DB->insert_record('local_edusupport_subscr', $subscription);
        }
        return $subscription;
    }

    /**
     * Remove support user from the list of assigned users.
     *
     * @param int dicussionid
     * @param int userid
     */
    public static function subscription_remove($discussionid, $userid = 0) {
        global $DB, $USER;
        if (empty($userid)) {
            $userid = $USER->id;
        }
        $DB->delete_records('local_edusupport_subscr', ['discussionid' => $discussionid, 'userid' => $userid]);
    }

    /**
     * Removes a forum as potential supportforum.
     *
     * @param forumid.
     * @return true.
     */
    public static function supportforum_disable($forumid) {
        global $DB;
        $DB->delete_records('local_edusupport', ['forumid' => $forumid]);
        self::supportforum_managecaps($forumid, false);
        self::supportforum_rolecheck($forumid);
        $centralforum = get_config('local_edusupport', 'centralforum');
        if ($forumid == $centralforum) {
            self::supportforum_disablecentral();
        }
        // TODO MDL-000000 shall we check for orphaned discussions too?
    }

    /**
     * Removes a forum as central support-forum.
     **/
    public static function supportforum_disablecentral() {
        if (!is_siteadmin()) {
            return;
        }
        set_config('centralforum', 0, 'local_edusupport');
    }

    /**
     * Sets a forum as possible support-forum.
     *
     * @param forumid.
     * @return forum as object on success.
     **/
    public static function supportforum_enable($forumid) {
        global $DB, $USER;
        $forum = $DB->get_record('forum', ['id' => $forumid]);
        if (empty($forum->course)) {
            return false;
        }

        $supportforum = $DB->get_record('local_edusupport', ['forumid' => $forumid]);
        if (empty($supportforum->id)) {
            $course = $DB->get_record('course', ['id' => $forum->course]);
            $supportforum = (object) [
                'categoryid' => $course->category,
                'courseid' => $forum->course,
                'forumid' => $forum->id,
                'archiveid' => 0,
                'dedicatedsupporter' => 0,
            ];
            $supportforum->id = $DB->insert_record('local_edusupport', $supportforum);
        }

        self::supportforum_managecaps($forumid, true);
        self::supportforum_rolecheck($forumid);
        if (!empty($supportforum->id)) {
            return $supportforum;
        } else {
            return false;
        }
    }

    /**
     * Sets a forum as central support-forum.
     *
     * @param int $forumid.
     * @return bool|object forum as object on success.
     **/
    public static function supportforum_enablecentral(int $forumid) {
        global $DB;
        if (!is_siteadmin()) {
            return false;
        }
        $forum = $DB->get_record('forum', ['id' => $forumid]);
        if (empty($forum->course)) {
            return false;
        }

        $supportforum = $DB->get_record('local_edusupport', ['forumid' => $forumid]);
        if (!empty($supportforum->id)) {
            set_config('centralforum', $forum->id, 'local_edusupport');
            return $forum;
        }
        return false;
    }

    /**
     * Sets the capabilities for the context to prevent deletion.
     *
     * @param forumid.
     * @param trigger true if we enable the forum, false if we disable it.
     **/
    public static function supportforum_managecaps($forumid, $trigger) {
        global $DB, $USER;
        $forum = $DB->get_record('forum', ['id' => $forumid]);
        if (empty($forum->course)) {
            return false;
        }

        $cm = \get_coursemodule_from_instance('forum', $forumid, 0, false, MUST_EXIST);
        $ctxmod = \context_module::instance($cm->id);
        $ctxcourse = \context_course::instance($forum->course);

        $capabilities = [
            'moodle/course:activityvisibility',
            'moodle/course:changecategory',
            'moodle/course:changefullname',
            'moodle/course:changeidnumber',
            'moodle/course:changeshortname',
            'moodle/course:enrolconfig',
            'moodle/course:manageactivities',
            'moodle/course:delete',
            'moodle/course:reset',
            'moodle/course:visibility',
            'moodle/restore:configure',
            'moodle/restore:restorecourse',
            'moodle/restore:restoresection',
            'moodle/restore:viewautomatedfilearea',
        ];
        $roles = [
            7,
            7,
            7,
            7,
            7,
            7,
            7,
            7,
            7,
            7,
            7,
            7,
            7,
            7,
        ];
        $contexts = [
            $ctxmod,
            $ctxcourse,
            $ctxcourse,
            $ctxcourse,
            $ctxcourse,
            $ctxcourse,
            $ctxmod,
            $ctxcourse,
            $ctxcourse,
            $ctxcourse,
            $ctxcourse,
            $ctxcourse,
            $ctxcourse,
            $ctxcourse,
        ];
        $permission = ($trigger) ? CAP_PROHIBIT : CAP_INHERIT;
        for ($a = 0; $a < count($capabilities); $a++) {
            \role_change_permission($roles[$a], $contexts[$a], $capabilities[$a], $permission);
        }
    }

    /**
     * Checks for a forum, if all supportteam-members have the required role.
     *
     * @param int forumid.
     */
    public static function supportforum_rolecheck(int $forumid = 0) {
        global $DB;
        if (empty($forumid)) {
            // We have to re-sync all supportforums.
            $forums = $DB->get_records('local_edusupport', []);
            foreach ($forums as $forum) {
                self::supportforum_rolecheck($forum->forumid);
            }
        } else {
            $forum = $DB->get_record('forum', ['id' => $forumid], '*', IGNORE_MISSING);
            if (empty($forum->id)) {
                return;
            }
            $issupportforum = self::is_supportforum($forumid);

            $cm = \get_coursemodule_from_instance('forum', $forumid, $forum->course, false, MUST_EXIST);
            $ctx = \context_module::instance($cm->id);

            $roleid = get_config('local_edusupport', 'supportteamrole');

            // Get all users that currently have the supporter-role.
            $sql = "SELECT userid FROM {role_assignments} WHERE roleid=? AND contextid=?";
            $curmembers = array_keys($DB->get_records_sql($sql, [$roleid, $ctx->id]));
            foreach ($curmembers as $curmember) {
                $unassign = false;
                if (!$issupportforum) {
                    $unassign = true;
                } else {
                    // Must mirror the assignment query below, or every run would hand the
                    // role out and take it away again.
                    $issupporter = self::is_second_level($curmember)
                        || self::is_first_level($curmember, $forum->course);
                    $unassign = !$issupporter;
                }
                if ($unassign) {
                    role_unassign($roleid, $curmember, $ctx->id);
                }
            }

            if ($issupportforum) {
                // Assign all current supportteam users.
                $sql = "SELECT les.*
                        FROM {local_edusupport_supporters} les
                        JOIN {user} u
                        ON u.id = les.userid
                        WHERE (les.courseid=? OR les.courseid=?)
                        AND u.deleted != 1";
                $params = [self::SYSTEM_COURSE_ID, $forum->course];
                $members = $DB->get_records_sql($sql, $params);
                foreach ($members as $member) {
                    role_assign($roleid, $member->userid, $ctx->id);
                }
            }
        }
    }

    /**
     * Set the dedicated supporter for a particular forum.
     *
     * @param userid.
     **/
    public static function supportforum_setdedicatedsupporter($forumid, $userid) {
        if (!self::is_supportforum($forumid)) {
            return false;
        }
        global $DB;
        if ($userid == -1) {
            $DB->set_field('local_edusupport', 'dedicatedsupporter', -1, ['forumid' => $forumid]);
        } else {
            if (!self::is_second_level($userid)) {
                return false;
            }
            $DB->set_field('local_edusupport', 'dedicatedsupporter', $userid, ['forumid' => $forumid]);
        }
        return true;
    }

    /**
     * Find a support user that has the same customfieldvalue as a user (can be enabled in settings)
     *
     * @return array supportuserid|false
     **/
    public static function get_support_user_by_matching_customfield($courseid, $cfn) {
        global $DB, $USER;
        $userid = $USER->id;
        $customfieldname = get_config('local_edusupport', 'customfieldname');
        $sql = "SELECT uid.data, uif.id FROM {user_info_data} uid
        LEFT JOIN {user_info_field} uif
        on uid.fieldid = uif.id
        WHERE uif.name = :customfieldname AND uid.userid = :userid";
        $params = ['courseid' => $courseid, 'customfieldname' => $customfieldname, 'userid' => $userid];
        $customfielddata = $DB->get_record_sql($sql, $params);
        $role = get_config('local_edusupport', 'rolename');
        $params = ['courseid' => $courseid, 'fieldid' => $customfielddata->id, 'customfieldvalue' => $customfielddata->data,
            'role' => $role];
        $sql = "SELECT  uid.userid, u.firstname, u.lastname,  uid.data,  r.shortname from {course} ic
                        JOIN {context} con ON con.instanceid = ic.id
                        JOIN {role_assignments} ra ON con.id = ra.contextid AND con.contextlevel = 50
                        JOIN {role} r ON ra.roleid = r.id
                        JOIN {user} u ON u.id = ra.userid
                        LEFT JOIN {groups_members} gm ON u.id = gm.userid
                        LEFT JOIN {user_info_data} uid on u.id = uid.userid
                        WHERE ic.id = :courseid AND u.id > 0 AND uid.fieldid = :fieldid
                        AND uid.data = :customfieldvalue AND r.shortname = :role";
        $supportusers = $DB->get_records_sql($sql, $params);
        if ($supportusers) {
            return $supportusers;
        } else {
            return false;
        }
    }

    /**
     * Updates status of an issueid
     *
     * @param int $status
     * @param int $issueid
     *
     * @return void
     */
    public static function set_status(int $status, int $issueid) {
        global $DB;
        $issue = new stdClass();
        $issue->id = $issueid;
        $issue->status = $status;
        $issue->timemodified = time();

        $DB->update_record('local_edusupport_issues', $issue);

        if ($status == ISSUE_STATUS_AWAITING_SUPPORT_ACTION && get_config('local_edusupport', 'sendreminders')) {
            self::send_reminder($issueid);
        }
    }

    /**
     * Helper function to send reminder mails.
     * @param int $issueid
     */
    public static function send_reminder(int $issueid) {

        if (self::issue_already_has_reminder($issueid)) {
            // There already are reminders in the future, so we do not create another one.
            return;
        }

        $taskdata = [
            'issueid' => $issueid,
            // No second reminders anymore.
            // phpcs:ignore Squiz.PHP.CommentedOutCode.Found
            /* 'sendagain' => true, */
        ];
        $task = new reminder();
        $task->set_custom_data($taskdata);
        $timebeforereminder = time() + (get_config('local_edusupport', 'timebeforereminder'));
        $task->set_next_run_time($timebeforereminder);

        // Unfortunately, reschedule does not work because set_status is called at each loading of issues.php.
        // So we need to use the normal queue function.
        \core\task\manager::queue_adhoc_task($task);
    }

    /**
     * Helper function to check if there already is a reminder to be sent in the future.
     * @param int $issueid
     */
    public static function issue_already_has_reminder(int $issueid) {
        global $DB;

        $sql =
            "SELECT COUNT(*) AS cnt
            FROM {task_adhoc}
            WHERE component = 'local_edusupport'
            AND classname = '\\local_edusupport\\task\\reminder'
            AND customdata LIKE '{_issueid_:" . $issueid . "}%'";

        $record = $DB->get_record_sql($sql);
        $futurereminderscount = $record->cnt;

        if ($futurereminderscount > 0) {
            return true;
        }
        return false;
    }

    /**
     * Translates the status number to data used by the template. Returns associative array: ['status' => string, 'class' => string]
     *
     * @param int $status
     * @return array
     */
    public static function status_to_template(int $status): array {
        switch ($status) {
            case ISSUE_STATUS_NOTSTARTED:
                return ['status' => get_string('status:notstarted', 'local_edusupport'), 'class' => 'badge badge-danger',
                    'stateclass' => 'notstarted'];
                break;
            case ISSUE_STATUS_AWAITING_USER_REPLY:
                return ['status' => get_string('status:awaitinguserreply', 'local_edusupport'),
                    'class' => 'badge badge-brown',
                    'stateclass' => 'awaiting'];
                break;
            case ISSUE_STATUS_ONGOING:
                return ['status' => get_string('status:ongoing', 'local_edusupport'), 'class' => 'badge badge-success',
                    'stateclass' => 'ongoing'];
                break;
            case ISSUE_STATUS_AWAITING_SUPPORT_ACTION:
                return ['status' => get_string('status:awaitingsupportaction', 'local_edusupport'),
                    'class' => 'badge badge-orange',
                    'stateclass' => 'awaitingsupportaction'];
                break;
            case ISSUE_STATUS_CLOSED:
                return ['status' => get_string('status:closed', 'local_edusupport'), 'class' => 'badge badge-success',
                    'stateclass' => 'closed'];
                break;
        }
        return [];
    }
}
