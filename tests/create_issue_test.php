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
 * Tests for creating a support issue through the external function.
 *
 * @package    local_edusupport
 * @category   test
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_edusupport;

use advanced_testcase;
use local_edusupport_external;
use moodle_exception;
use stdClass;

/**
 * Tests for creating a support issue through the external function.
 *
 * local_edusupport_external still builds on lib/externallib.php, the deprecated compatibility
 * shim, which refuses to be loaded outside an isolated process. Hence the annotation below and
 * the require inside setUp() rather than at the top of this file.
 *
 * @package    local_edusupport
 * @category   test
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_edusupport_external::create_issue
 * @runTestsInSeparateProcesses
 */
final class create_issue_test extends advanced_testcase {
    /** @var stdClass the course holding the support forum. */
    private $course;

    /** @var stdClass the support forum. */
    private $forum;

    /** @var stdClass the user asking for support. */
    private $student;

    /**
     * Set up a support forum with a student who may post into it.
     */
    protected function setUp(): void {
        global $CFG;

        parent::setUp();
        require_once($CFG->dirroot . '/local/edusupport/externallib.php');

        $this->resetAfterTest(true);

        // Without an explicit limit the spam check compares against an empty setting.
        set_config('spamprotectionthreshold', 60, 'local_edusupport');
        set_config('spamprotectionlimit', 100, 'local_edusupport');

        $this->setAdminUser();
        $datagenerator = $this->getDataGenerator();

        $this->course = $datagenerator->create_course();
        $this->forum = $datagenerator->create_module('forum', ['course' => $this->course->id]);
        $datagenerator->get_plugin_generator('local_edusupport')
            ->create_supportforum(['forumid' => $this->forum->id]);

        $this->student = $datagenerator->create_user();
        $datagenerator->enrol_user($this->student->id, $this->course->id, 'student');
    }

    /**
     * Call create_issue with the defaults a ticket form would send.
     *
     * @param string $subject
     * @param string $forumgroup
     * @return array the reply of the external function.
     */
    private function create_issue(string $subject, string $forumgroup = ''): array {
        if ($forumgroup === '') {
            $forumgroup = $this->forum->id . '_0';
        }
        return local_edusupport_external::create_issue(
            $subject,
            'Beschreibung des Problems',
            $forumgroup,
            0,
            '',
            '',
            'https://example.com/course/view.php?id=' . $this->course->id,
            '',
            null,
            null
        );
    }

    /**
     * A ticket ends up as a discussion in the support forum.
     *
     * It does not yet become a tracked issue: local_edusupport_issues is only written by
     * set_2nd_level(), so with first level support alone the ticket lives in the forum only
     * and does not appear on issues.php. See the escalation test below.
     */
    public function test_create_issue_creates_a_discussion(): void {
        global $DB;

        $this->setUser($this->student);
        $reply = $this->create_issue('Drucker geht nicht');

        $this->assertGreaterThan(0, $reply['discussionid']);

        $discussion = $DB->get_record('forum_discussions', ['id' => $reply['discussionid']], '*', MUST_EXIST);
        $this->assertEquals($this->forum->id, $discussion->forum);
        $this->assertSame('Drucker geht nicht', $discussion->name);
        $this->assertEquals($this->student->id, $discussion->userid);

        $this->assertFalse($DB->record_exists('local_edusupport_issues', ['discussionid' => $reply['discussionid']]));
    }

    /**
     * With automatic escalation the ticket becomes a tracked issue.
     */
    public function test_create_issue_registers_a_tracked_issue_when_escalated(): void {
        global $DB;

        set_config('auto2ndlvl', 1, 'local_edusupport');

        $datagenerator = $this->getDataGenerator();
        $supporter = $datagenerator->create_user();
        $datagenerator->enrol_user($supporter->id, $this->course->id, 'teacher');
        $datagenerator->get_plugin_generator('local_edusupport')
            ->create_supporter(['userid' => $supporter->id]);

        $this->setUser($this->student);
        $reply = $this->create_issue('Drucker geht nicht');

        $this->assertGreaterThan(0, $reply['discussionid']);
        $this->assertTrue($DB->record_exists('local_edusupport_issues', ['discussionid' => $reply['discussionid']]));
    }

    /**
     * The reported contacts are everyone who may edit the course.
     *
     * This pins current behaviour: get_course_supporters() asks for moodle/course:update and
     * ignores the plugin's own supporter registry. Anyone with editing rights in the support
     * course - a manager, an integration account - is published to the person filing a ticket,
     * name and email address included. If the lookup is ever narrowed to the registry, this
     * test is meant to fail and force a deliberate decision.
     */
    public function test_create_issue_reports_course_editors_as_responsible(): void {
        $datagenerator = $this->getDataGenerator();

        $editor = $datagenerator->create_user();
        $datagenerator->enrol_user($editor->id, $this->course->id, 'editingteacher');

        // A registered supporter without editing rights in the course.
        $supporter = $datagenerator->create_user();
        $datagenerator->enrol_user($supporter->id, $this->course->id, 'student');
        $datagenerator->get_plugin_generator('local_edusupport')
            ->create_supporter(['userid' => $supporter->id]);

        $this->setUser($this->student);
        $reply = $this->create_issue('Drucker geht nicht');

        $responsibleids = array_column($reply['responsibles'], 'userid');
        $this->assertContains((int) $editor->id, array_map('intval', $responsibleids));
        $this->assertNotContains((int) $supporter->id, array_map('intval', $responsibleids));
    }

    /**
     * Without a target forum the request is sent to the site support address instead.
     */
    public function test_create_issue_falls_back_to_mail(): void {
        $sink = $this->redirectEmails();

        $this->setUser($this->student);
        $reply = $this->create_issue('Drucker geht nicht', 'mail');

        $this->assertEquals(-999, $reply['discussionid']);
        $this->assertNotEmpty($reply['responsibles']);
        $this->assertGreaterThan(0, $sink->count());
        $sink->close();
    }

    /**
     * Too many tickets in a row are refused.
     */
    public function test_spam_protection_blocks_a_burst_of_issues(): void {
        set_config('spamprotectionlimit', 1, 'local_edusupport');

        $this->setUser($this->student);
        $this->create_issue('Erstes Ticket');

        $this->expectException(moodle_exception::class);
        $this->create_issue('Zweites Ticket');
    }
}
