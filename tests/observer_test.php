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

namespace local_edusupport;

use advanced_testcase;
use core\task\manager;
use local_edusupport\task\send_mail;

/**
 * Test unit class of local_edusupport.
 *
 * @package local_edusupport
 * @category test
 * @copyright 2025 Wunderbyte GmbH <info@wunderbyte.at>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class observer_test extends advanced_testcase {
    /**
     * Setup the test environment.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
    }

    /**
     * Make sure the observer kicks in to delete all data related to a user when the user is deleted.
     * @covers \local_edusupport\observer
     */
    public function test_delete_user(): void {

        global $DB;

        $user = $this->getDataGenerator()->create_user();

        $DB->insert_record('local_edusupport_supporters', [
            'courseid' => 4,
            'userid' => $user->id,
            'supportlevel' => 'test',
            'holidaymode' => 1,
        ]);

        $DB->insert_record('local_edusupport_subscr', [
            'issueid' => 4,
            'userid' => $user->id,
            'discussionid' => 8,
        ]);

        $this->assertTrue(
            $DB->record_exists('local_edusupport_supporters', ['userid' => $user->id]),
            "User {$user->id} should exist."
        );

        $this->assertTrue(
            $DB->record_exists('local_edusupport_subscr', ['userid' => $user->id]),
            "User {$user->id} should exist"
        );

        user_delete_user($user);

        $this->assertFalse(
            $DB->record_exists('local_edusupport_supporters', ['userid' => $user->id]),
            "User {$user->id} should no longer be a supporter."
        );

        $this->assertFalse(
            $DB->record_exists('local_edusupport_subscr', ['userid' => $user->id]),
            "User {$user->id} should no longer be subscribed to any discussions."
        );
    }

    /**
     * Deleting a user removes that user's rows and nobody else's.
     *
     * The observer used to delete supporter rows by their own id instead of by user id. Test
     * data never tripped over that, because phpunit gives every table its own id range, so a
     * row id never happens to equal a user id. The collision is built on purpose here.
     *
     * @covers \local_edusupport\observer::user_deleted
     */
    public function test_deleting_a_user_leaves_other_supporters_alone(): void {
        global $DB;

        $leaving = $this->getDataGenerator()->create_user();
        $staying = $this->getDataGenerator()->create_user();
        set_config('accountmanagers', $leaving->id . ',' . $staying->id, 'local_edusupport');

        $DB->insert_record('local_edusupport_supporters', (object) [
            'courseid' => lib::SYSTEM_COURSE_ID,
            'userid' => $leaving->id,
            'supportlevel' => '',
            'holidaymode' => 0,
            'autoassign' => 1,
        ]);

        // A row belonging to somebody else, whose own id equals the leaving user's id.
        $DB->import_record('local_edusupport_supporters', (object) [
            'id' => $leaving->id,
            'courseid' => lib::SYSTEM_COURSE_ID,
            'userid' => $staying->id,
            'supportlevel' => '',
            'holidaymode' => 0,
            'autoassign' => 1,
        ]);

        delete_user($leaving);

        $this->assertFalse($DB->record_exists('local_edusupport_supporters', ['userid' => $leaving->id]));
        $this->assertTrue(
            $DB->record_exists('local_edusupport_supporters', ['id' => $leaving->id, 'userid' => $staying->id]),
            'The row of an unrelated supporter must survive.'
        );
        $this->assertSame((string) $staying->id, get_config('local_edusupport', 'accountmanagers'));
    }

    /**
     * A deleted user no longer handles issues or a support forum.
     *
     * @covers \local_edusupport\observer::user_deleted
     */
    public function test_deleting_a_user_takes_them_off_issues_and_forums(): void {
        global $DB;

        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $plugingenerator = $generator->get_plugin_generator('local_edusupport');

        $course = $generator->create_course();
        $forum = $generator->create_module('forum', ['course' => $course->id]);
        $supportforum = $plugingenerator->create_supportforum(['forumid' => $forum->id]);

        $leaving = $generator->create_user();
        $plugingenerator->create_supporter(['userid' => $leaving->id]);
        $issue = $plugingenerator->create_issue(['forumid' => $forum->id, 'currentsupporter' => $leaving->id]);
        $DB->set_field('local_edusupport_issues', 'accountmanager', $leaving->id, ['id' => $issue->id]);
        $DB->set_field('local_edusupport', 'dedicatedsupporter', $leaving->id, ['id' => $supportforum->id]);

        delete_user($leaving);

        $issue = $DB->get_record('local_edusupport_issues', ['id' => $issue->id], '*', MUST_EXIST);
        $this->assertEquals(0, $issue->currentsupporter);
        $this->assertEquals(0, $issue->accountmanager);
        $this->assertEquals(0, $DB->get_field('local_edusupport', 'dedicatedsupporter', ['id' => $supportforum->id]));
    }

    /**
     * Set up a support forum holding one issue somebody is subscribed to.
     *
     * @param string $subject the name of the discussion, which carries the guest address if there is one.
     * @return array the discussion, the subscriber and the person replying.
     */
    private function create_issue_with_a_subscriber(string $subject = 'Drucker geht nicht'): array {
        global $DB;

        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $plugingenerator = $generator->get_plugin_generator('local_edusupport');

        $course = $generator->create_course();
        $forum = $generator->create_module('forum', ['course' => $course->id]);
        $plugingenerator->create_supportforum(['forumid' => $forum->id]);

        $asking = $generator->create_user(['email' => 'ratlos@example.com']);
        $supporter = $generator->create_user(['email' => 'supportteam@example.com']);
        $plugingenerator->create_supporter(['userid' => $supporter->id]);

        $issue = $plugingenerator->create_issue([
            'forumid' => $forum->id,
            'userid' => $asking->id,
            'subject' => $subject,
        ]);
        $discussion = $DB->get_record('forum_discussions', ['id' => $issue->discussionid], '*', MUST_EXIST);

        $DB->insert_record('local_edusupport_subscr', (object) [
            'issueid' => $issue->id,
            'userid' => $supporter->id,
            'discussionid' => $discussion->id,
        ]);

        return [$discussion, $supporter, $asking];
    }

    /**
     * Post a reply and let the observer see it.
     *
     * @param \stdClass $discussion
     * @param \stdClass $author
     * @return void
     */
    private function reply_to(\stdClass $discussion, \stdClass $author): void {
        global $DB;

        $forum = $DB->get_record('forum', ['id' => $discussion->forum], '*', MUST_EXIST);
        [, $cm] = get_course_and_cm_from_instance($forum, 'forum');

        $post = $this->getDataGenerator()->get_plugin_generator('mod_forum')->create_post([
            'discussion' => $discussion->id,
            'userid' => $author->id,
            'parent' => $discussion->firstpost,
            'message' => 'Haben Sie es schon neu gestartet?',
        ]);

        \mod_forum\event\post_created::create([
            'context' => \context_module::instance($cm->id),
            'objectid' => $post->id,
            'other' => [
                'discussionid' => $discussion->id,
                'forumid' => $forum->id,
                'forumtype' => $forum->type,
            ],
        ])->trigger();
    }

    /**
     * A reply hands its notifications to cron rather than to the mail server.
     *
     * Posting used to wait for every subscriber's mail to go out, which freezes the page for
     * as long as the mail server takes. See {@see \local_edusupport\task\send_mail}.
     *
     * @covers \local_edusupport\observer::event
     */
    public function test_a_reply_queues_its_notifications(): void {
        [$discussion, $supporter, $asking] = $this->create_issue_with_a_subscriber();

        $sink = $this->redirectEmails();
        $this->reply_to($discussion, $asking);

        $this->assertSame(0, $sink->count(), 'Nothing may be sent while the post is still being saved.');

        $tasks = manager::get_adhoc_tasks(send_mail::class);
        $this->assertCount(1, $tasks);

        $this->runAdhocTasks(send_mail::class);

        $messages = $sink->get_messages();
        $this->assertCount(1, $messages);
        $this->assertSame($supporter->email, $messages[0]->to);
        $sink->close();
    }

    /**
     * The author of the reply is not notified of their own post.
     *
     * @covers \local_edusupport\observer::event
     */
    public function test_a_reply_does_not_notify_its_own_author(): void {
        [$discussion, $supporter] = $this->create_issue_with_a_subscriber();

        $sink = $this->redirectEmails();
        $this->reply_to($discussion, $supporter);

        $this->assertCount(0, manager::get_adhoc_tasks(send_mail::class));
        $sink->close();
    }

    /**
     * A guest ticket is answered at the address the guest left, not at the shared account's own.
     *
     * Every guest ticket is filed under one shared account, so the address has to travel with
     * the queued mail. Reading it off the account when the task runs would send every reply to
     * the same placeholder address.
     *
     * @covers \local_edusupport\observer::event
     */
    public function test_a_reply_to_a_guest_ticket_keeps_the_address_the_guest_left(): void {
        set_config('guestmodeenabled', 1, 'local_edusupport');

        [$discussion, , $asking] = $this->create_issue_with_a_subscriber(
            '[Guestticket: fragende@example.com] Drucker geht nicht'
        );

        $sink = $this->redirectEmails();
        $this->reply_to($discussion, $asking);

        $this->runAdhocTasks(send_mail::class);

        $recipients = array_column($sink->get_messages(), 'to');
        $this->assertContains('fragende@example.com', $recipients);
        $this->assertNotContains('edusupport@example.com', $recipients, 'That is the shared guest account.');
        $sink->close();
    }
}
