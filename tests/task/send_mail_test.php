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
 * Tests for the ad hoc task that sends the mails of a support request.
 *
 * @package    local_edusupport
 * @category   test
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_edusupport\task;

use advanced_testcase;
use core_user;
use core\task\manager;
use moodle_exception;

/**
 * Tests for the ad hoc task that sends the mails of a support request.
 *
 * @package    local_edusupport
 * @category   test
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_edusupport\task\send_mail
 */
final class send_mail_test extends advanced_testcase {
    /** @var \stdClass the user a mail is addressed to. */
    private $recipient;

    /** @var \stdClass the user a mail is sent on behalf of. */
    private $sender;

    /**
     * Two users to send between.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);

        $this->recipient = $this->getDataGenerator()->create_user(['email' => 'supportteam@example.com']);
        $this->sender = $this->getDataGenerator()->create_user(['email' => 'ratlos@example.com']);
    }

    /**
     * Queueing hands the mail to cron instead of sending it there and then.
     */
    public function test_queue_does_not_talk_to_the_mail_server(): void {
        $sink = $this->redirectEmails();

        send_mail::queue($this->recipient, $this->sender, 'Drucker geht nicht', 'Nichts geht mehr', '<p>Nichts geht mehr</p>');

        $this->assertSame(0, $sink->count());
        $this->assertCount(1, manager::get_adhoc_tasks(send_mail::class));
        $sink->close();
    }

    /**
     * What was queued is what cron sends.
     */
    public function test_the_queued_mail_is_sent_when_the_task_runs(): void {
        $sink = $this->redirectEmails();

        send_mail::queue($this->recipient, $this->sender, 'Drucker geht nicht', 'Nichts geht mehr', '<p>Nichts geht mehr</p>');
        $this->runAdhocTasks(send_mail::class);

        $messages = $sink->get_messages();
        $this->assertCount(1, $messages);
        $this->assertSame('Drucker geht nicht', $messages[0]->subject);
        $this->assertSame('supportteam@example.com', $messages[0]->to);
        $sink->close();
    }

    /**
     * The site support contact has no row in the user table, so the task resolves it when it runs.
     *
     * This is the recipient of every request filed on a site that has no support forum set up.
     */
    public function test_the_site_support_contact_is_resolved_when_the_task_runs(): void {
        global $CFG;

        $CFG->supportemail = 'hilfe@example.com';
        $sink = $this->redirectEmails();

        $supportuser = core_user::get_support_user();
        $this->assertEquals(core_user::SUPPORT_USER, $supportuser->id);

        send_mail::queue($supportuser, $this->sender, 'Drucker geht nicht', 'Nichts geht mehr', '<p>Nichts geht mehr</p>');
        $this->runAdhocTasks(send_mail::class);

        $messages = $sink->get_messages();
        $this->assertCount(1, $messages);
        $this->assertSame('hilfe@example.com', $messages[0]->to);
        $sink->close();
    }

    /**
     * The screenshot survives until the task runs and is cleaned up afterwards.
     */
    public function test_an_attachment_is_kept_until_it_has_been_sent(): void {
        global $CFG;

        $sink = $this->redirectEmails();

        $filepath = $CFG->tempdir . '/edusupport-' . random_string();
        file_put_contents($filepath, 'not really a png');

        send_mail::queue(
            $this->recipient,
            $this->sender,
            'Drucker geht nicht',
            'Nichts geht mehr',
            '<p>Nichts geht mehr</p>',
            $filepath,
            'screenshot.png'
        );

        $this->assertFileExists($filepath, 'The queued mail still needs its attachment.');

        $this->runAdhocTasks(send_mail::class);

        $this->assertSame(1, $sink->count());
        $this->assertFileDoesNotExist($filepath, 'The temporary file has to go once the mail is out.');
        $sink->close();
    }

    /**
     * A mail that cannot go out fails the task, so that cron retries it.
     */
    public function test_a_mail_that_cannot_be_sent_fails_the_task(): void {
        global $DB;

        $sink = $this->redirectEmails();
        // An address phpmailer refuses, which is what email_to_user() reports back as a failure.
        $DB->set_field('user', 'email', 'keine-adresse', ['id' => $this->recipient->id]);
        $this->recipient = $DB->get_record('user', ['id' => $this->recipient->id]);

        send_mail::queue($this->recipient, $this->sender, 'Drucker geht nicht', 'Nichts geht mehr', '<p>Nichts geht mehr</p>');

        $tasks = manager::get_adhoc_tasks(send_mail::class);
        $task = reset($tasks);

        try {
            $task->execute();
            $this->fail('The task has to fail, otherwise nobody ever retries the mail.');
        } catch (moodle_exception $e) {
            $this->assertSame('error:mailnotsent', $e->errorcode);
        }

        $this->assertDebuggingCalled();
        $this->assertSame(0, $sink->count());
        $sink->close();
    }

    /**
     * After a day of retries the mail is dropped rather than kept in the queue forever.
     */
    public function test_a_mail_is_given_up_on_once_the_retries_are_a_day_apart(): void {
        global $CFG, $DB;

        $this->expectOutputRegex('/giving up on the mail/');

        $sink = $this->redirectEmails();
        $DB->set_field('user', 'email', 'keine-adresse', ['id' => $this->recipient->id]);
        $this->recipient = $DB->get_record('user', ['id' => $this->recipient->id]);

        $filepath = $CFG->tempdir . '/edusupport-' . random_string();
        file_put_contents($filepath, 'not really a png');

        send_mail::queue(
            $this->recipient,
            $this->sender,
            'Drucker geht nicht',
            'Nichts geht mehr',
            '<p>Nichts geht mehr</p>',
            $filepath,
            'screenshot.png'
        );

        $tasks = manager::get_adhoc_tasks(send_mail::class);
        $task = reset($tasks);
        $task->set_fail_delay(DAYSECS);

        // No exception this time: the task completes and the mail is dropped.
        $task->execute();

        $this->assertDebuggingCalled();
        $this->assertSame(0, $sink->count());
        $this->assertFileDoesNotExist($filepath, 'A dropped mail must not leave its attachment behind.');
        $sink->close();
    }

    /**
     * A recipient who was deleted in the meantime drops the mail instead of failing forever.
     */
    public function test_a_mail_to_a_deleted_user_is_dropped(): void {
        $this->expectOutputRegex('/no longer exists/');

        $sink = $this->redirectEmails();

        send_mail::queue($this->recipient, $this->sender, 'Drucker geht nicht', 'Nichts geht mehr', '<p>Nichts geht mehr</p>');
        delete_user($this->recipient);

        $this->runAdhocTasks(send_mail::class);

        $this->assertSame(0, $sink->count());
        $sink->close();
    }
}
