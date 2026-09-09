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
 * Tests for closing and reopening a support issue.
 *
 * @package    local_edusupport
 * @category   test
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_edusupport;

use advanced_testcase;
use stdClass;

/**
 * Tests for closing and reopening a support issue.
 *
 * @package    local_edusupport
 * @category   test
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_edusupport\lib::close_issue
 * @covers     \local_edusupport\lib::reopen_issue
 */
final class close_issue_test extends advanced_testcase {
    /** @var stdClass the course holding the support forum. */
    private $course;

    /** @var stdClass the support forum. */
    private $forum;

    /** @var stdClass a member of the global support team. */
    private $supporter;

    /** @var stdClass the user asking for support. */
    private $student;

    /** @var \local_edusupport_generator the plugin data generator. */
    private $generator;

    /**
     * Set up a support forum with a supporter and a student.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        $this->preventResetByRollback();
        $this->redirectMessages();

        set_config('sendissueclosed', 0, 'local_edusupport');

        $this->setAdminUser();
        $datagenerator = $this->getDataGenerator();
        $this->generator = $datagenerator->get_plugin_generator('local_edusupport');

        $this->course = $datagenerator->create_course();
        $this->forum = $datagenerator->create_module('forum', ['course' => $this->course->id]);
        $this->generator->create_supportforum(['forumid' => $this->forum->id]);

        $this->supporter = $datagenerator->create_user();
        $datagenerator->enrol_user($this->supporter->id, $this->course->id, 'teacher');
        $this->generator->create_supporter(['userid' => $this->supporter->id]);

        $this->student = $datagenerator->create_user();
        $datagenerator->enrol_user($this->student->id, $this->course->id, 'student');
    }

    /**
     * Closing marks the discussion, sets the status and drops the priority.
     */
    public function test_close_marks_the_discussion_and_sets_the_status(): void {
        global $DB;

        $issue = $this->generator->create_issue([
            'forumid' => $this->forum->id,
            'userid' => $this->student->id,
            'subject' => 'Drucker geht nicht',
        ]);

        $this->setUser($this->supporter);
        $this->assertTrue(lib::close_issue($issue->discussionid));

        $this->assertSame(
            lib::CLOSED_PREFIX . 'Drucker geht nicht',
            $DB->get_field('forum_discussions', 'name', ['id' => $issue->discussionid])
        );

        $closed = $DB->get_record('local_edusupport_issues', ['discussionid' => $issue->discussionid]);
        $this->assertEquals(ISSUE_STATUS_CLOSED, $closed->status);
        $this->assertEquals(0, $closed->priority);
    }

    /**
     * Closing unsubscribes everyone from the issue.
     */
    public function test_close_removes_the_subscriptions(): void {
        global $DB;

        $issue = $this->generator->create_issue([
            'forumid' => $this->forum->id,
            'userid' => $this->student->id,
        ]);

        $this->setUser($this->supporter);
        lib::subscription_add($issue->discussionid, $this->supporter->id);
        $this->assertTrue($DB->record_exists('local_edusupport_subscr', ['discussionid' => $issue->discussionid]));

        lib::close_issue($issue->discussionid);

        $this->assertFalse($DB->record_exists('local_edusupport_subscr', ['discussionid' => $issue->discussionid]));
    }

    /**
     * Closing posts a note into the discussion.
     */
    public function test_close_posts_a_note_into_the_discussion(): void {
        global $DB;

        $issue = $this->generator->create_issue([
            'forumid' => $this->forum->id,
            'userid' => $this->student->id,
        ]);
        $before = $DB->count_records('forum_posts', ['discussion' => $issue->discussionid]);

        $this->setUser($this->supporter);
        lib::close_issue($issue->discussionid);

        $this->assertSame(
            $before + 1,
            $DB->count_records('forum_posts', ['discussion' => $issue->discussionid])
        );
    }

    /**
     * Closing an issue twice must not stack markers.
     */
    public function test_closing_twice_keeps_one_marker(): void {
        global $DB;

        $issue = $this->generator->create_issue([
            'forumid' => $this->forum->id,
            'userid' => $this->student->id,
            'subject' => 'Drucker geht nicht',
        ]);

        $this->setUser($this->supporter);
        lib::close_issue($issue->discussionid);
        lib::close_issue($issue->discussionid);

        $this->assertSame(
            lib::CLOSED_PREFIX . 'Drucker geht nicht',
            $DB->get_field('forum_discussions', 'name', ['id' => $issue->discussionid])
        );
    }

    /**
     * Someone outside the support team cannot close an issue.
     */
    public function test_close_is_refused_for_a_non_supporter(): void {
        global $DB;

        $issue = $this->generator->create_issue([
            'forumid' => $this->forum->id,
            'userid' => $this->student->id,
            'subject' => 'Drucker geht nicht',
        ]);

        $this->setUser($this->student);
        $this->assertFalse(lib::close_issue($issue->discussionid));

        $this->assertSame(
            'Drucker geht nicht',
            $DB->get_field('forum_discussions', 'name', ['id' => $issue->discussionid])
        );
    }

    /**
     * A discussion in a forum that is not a support forum is not touched.
     */
    public function test_close_is_refused_outside_a_support_forum(): void {
        $plainforum = $this->getDataGenerator()->create_module('forum', ['course' => $this->course->id]);
        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_forum')->create_discussion([
            'course' => $this->course->id,
            'forum' => $plainforum->id,
            'userid' => $this->student->id,
            'name' => 'Kein Ticket',
        ]);

        $this->setUser($this->supporter);
        $this->assertFalse(lib::close_issue($discussion->id));
    }

    /**
     * Reopening removes the marker and puts the issue back into the queue.
     */
    public function test_reopen_removes_the_marker(): void {
        global $DB;

        $issue = $this->generator->create_issue([
            'forumid' => $this->forum->id,
            'userid' => $this->student->id,
            'subject' => 'Drucker geht nicht',
        ]);

        $this->setUser($this->supporter);
        lib::close_issue($issue->discussionid);
        $this->assertTrue(lib::reopen_issue($issue->discussionid));

        $this->assertSame(
            'Drucker geht nicht',
            $DB->get_field('forum_discussions', 'name', ['id' => $issue->discussionid])
        );

        $reopened = $DB->get_record('local_edusupport_issues', ['discussionid' => $issue->discussionid]);
        $this->assertEquals(ISSUE_STATUS_AWAITING_SUPPORT_ACTION, $reopened->status);
        $this->assertEquals(1, $reopened->priority);
    }

    /**
     * An issue closed before the marker changed can still be reopened cleanly.
     */
    public function test_reopen_removes_a_legacy_marker(): void {
        global $DB;

        $issue = $this->generator->create_issue([
            'forumid' => $this->forum->id,
            'userid' => $this->student->id,
            'subject' => '[Closed] Altes Ticket',
        ]);

        $this->setUser($this->supporter);
        lib::reopen_issue($issue->discussionid);

        $this->assertSame(
            'Altes Ticket',
            $DB->get_field('forum_discussions', 'name', ['id' => $issue->discussionid])
        );
    }
}
