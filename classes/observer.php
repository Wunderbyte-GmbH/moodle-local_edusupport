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
 * @package    local_edusupport
 * @copyright  2020 Center for Learningmangement (www.lernmanagement.at)
 * @author     Robert Schrenk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_edusupport;

use cache_helper;
use local_edusupport\event\supportuser_added;
use local_edusupport\event\supportuser_changed;
use local_edusupport\event\supportuser_deleted;

class observer {

    /**
     * Observer for the supportuser_added event
     *
     * @param supportuser_added $event
     */
    public static function supportuser_added(supportuser_added $event) {
        // When a support user gets added, changed or deleted, we need to purge the navbar menu cache.
        // The navbar only shows issues if a user is a support user (or an admin).
        cache_helper::purge_by_event('setbacksupportmenu');
    }

    /**
     * Observer for the supportuser_changed event
     *
     * @param supportuser_changed $event
     */
    public static function supportuser_changed(supportuser_changed $event) {
        // When a support user gets added, changed or deleted, we need to purge the navbar menu cache.
        // The navbar only shows issues if a user is a support user (or an admin).
        cache_helper::purge_by_event('setbacksupportmenu');
    }

    /**
     * Observer for the supportuser_deleted event
     *
     * @param supportuser_deleted $event
     */
    public static function supportuser_deleted(supportuser_deleted $event) {
        // When a support user gets added, changed or deleted, we need to purge the navbar menu cache.
        // The navbar only shows issues if a user is a support user (or an admin).
        cache_helper::purge_by_event('setbacksupportmenu');
    }

    public static function event($event) {

        // We should have separate functions for different event types for better readability!

        global $CFG, $DB, $OUTPUT;

        $entry = (object)$event->get_data();
        if ($entry->eventname == '\core\event\user_deleted') {
            $conditions = array('id' => $event->relateduserid);
            \local_edusupport\accountmanager::delete_account_manager($event->relateduserid);
            return $DB->delete_records('local_edusupport_supporters', $conditions);
        }
        if ($entry->eventname == '\mod_forum\event\discussion_deleted') {
            $discussionid = $entry->objectid;
            return \local_edusupport\lib::delete_issue($discussionid);
        } else {
            if (substr($entry->eventname, 0, strlen("\\mod_forum\\event\\post_")) == "\\mod_forum\\event\\post_") {
                $post = $DB->get_record("forum_posts", array("id" => $entry->objectid));
                $discussion = $DB->get_record("forum_discussions", array("id" => $post->discussion));
            } else {
                $discussion = $DB->get_record("forum_discussions", array("id" => $entry->objectid));
                $post = $DB->get_record("forum_posts", array("discussion" => $discussion->id, "parent" => 0));
            }

            $forum = $DB->get_record("forum", array("id" => $discussion->forum));
            $course = $DB->get_record("course", array("id" => $forum->course));
            $issue = $DB->get_record('local_edusupport_issues', array('discussionid' => $discussion->id));
            if (empty($issue->id)) {
                return;
            }
            \local_edusupport\lib::reopen_issue($discussion->id);
            $author = $DB->get_record('user', array('id' => $post->userid));

            // Having more than one user in the discussion means that someone has already answered.
            $morethanoneuser = $DB->get_record_sql(
                "SELECT COUNT(DISTINCT userid) AS count
                FROM {forum_posts}
                WHERE discussion = :discussion", ['discussion' => $discussion->id]);
            if ($morethanoneuser->count > 1) {
                if ($post->userid == $discussion->userid) {
                    // If the user posted, we are waiting for a support action.
                    \local_edusupport\lib::set_status(ISSUE_STATUS_AWAITING_SUPPORT_ACTION, $issue->id);
                } else {
                    // If the supporter posted, we are waiting for a user reply.
                    \local_edusupport\lib::set_status(ISSUE_STATUS_AWAITING_USER_REPLY, $issue->id);
                }
            } else {
                \local_edusupport\lib::set_status(ISSUE_STATUS_AWAITING_SUPPORT_ACTION, $issue->id);
            }
            // Enhance post data.
            $post->wwwroot = $CFG->wwwroot;
            $post->authorfullname = \fullname($author);
            $post->authorlink = $CFG->wwwroot . '/user/view.php?id=' . $author->id;
            $post->authorpicture = $OUTPUT->user_picture($author, array('size' => 40));
            $post->postdate = strftime('%d. %B %Y, %H:%m', $post->created);

            $post->coursename = $course->fullname;
            $post->forumname = $forum->name;
            $post->discussionname = $discussion->name;

            $post->issuelink = $CFG->wwwroot . '/local/edusupport/issue.php?d=' . $discussion->id;
            $post->replylink = $CFG->wwwroot . '/local/edusupport/issue.php?d=' . $discussion->id . '&replyto=' . $post->id;

            // Get all subscribers.
            $subscribers = $DB->get_records('local_edusupport_subscr', array('discussionid' => $discussion->id));
            $guestmode = get_config('local_edusupport', 'guestmodeenabled');

            // Write to Guestuser.
            if ($guestmode && strpos($discussion->name, 'Guestticket')) {
                preg_match('/(?<=Guestticket: )(.*)(?=\])/', $discussion->name, $matches);
                $mail = $matches[0];
                $guestuser = new guest_supportuser();
                $touser = $guestuser->get_support_guestuser();
                $touser->email = $mail;
                $post->furtherquestions = get_string('furtherquestions', 'local_edusupport', ['sitename' => $CFG->wwwroot]);

                $mailhtml = $OUTPUT->render_from_template('local_edusupport/post_mailhtml_guest', $post);
                $mailtext = $OUTPUT->render_from_template('local_edusupport/post_mailtext_guest', $post);
                $subject = $discussion->name;
                \email_to_user($touser, $author, $subject, $mailtext, $mailhtml, "", true);
            }
            foreach ($subscribers as $subscriber) {
                // We do not want to send to ourselves...
                if ($subscriber->userid == $author->id) {
                    continue;
                }

                $touser = $DB->get_record('user', array('id' => $subscriber->userid));

                // Send notification.
                $subject = $discussion->name;
                $mailhtml = $OUTPUT->render_from_template('local_edusupport/post_mailhtml', $post);
                $mailtext = $OUTPUT->render_from_template('local_edusupport/post_mailtext', $post);

                \email_to_user($touser, $author, $subject, $mailtext, $mailhtml, "", true);
            }
            return true;
        }
    }
}
