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
 * Tests for reacting to a support issue: assignment, subscription and priority.
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
 * Tests for reacting to a support issue: assignment, subscription and priority.
 *
 * @package    local_edusupport
 * @category   test
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_edusupport\lib::set_current_supporter
 * @covers     \local_edusupport\lib::set_prioritylvl
 * @covers     \local_edusupport\lib::set_2nd_level
 * @covers     \local_edusupport\lib::subscription_add
 * @covers     \local_edusupport\lib::subscription_remove
 */
final class supporter_assignment_test extends advanced_testcase {
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

        set_config('sendmsgonset2ndlvl', 0, 'local_edusupport');
        set_config('sendsupporterassignments', 0, 'local_edusupport');

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
     * Create an issue in the support forum.
     *
     * @return stdClass the issue record.
     */
    private function create_issue(): stdClass {
        return $this->generator->create_issue([
            'forumid' => $this->forum->id,
            'userid' => $this->student->id,
            'subject' => 'Drucker geht nicht',
        ]);
    }

    /**
     * Taking an issue sets the current supporter and subscribes them.
     */
    public function test_set_current_supporter_assigns_and_subscribes(): void {
        global $DB;

        $issue = $this->create_issue();

        $this->setUser($this->supporter);
        $this->assertTrue(lib::set_current_supporter($issue->discussionid, $this->supporter->id));

        $this->assertEquals(
            $this->supporter->id,
            $DB->get_field('local_edusupport_issues', 'currentsupporter', ['discussionid' => $issue->discussionid])
        );
        $this->assertTrue($DB->record_exists('local_edusupport_subscr', [
            'discussionid' => $issue->discussionid,
            'userid' => $this->supporter->id,
        ]));
    }

    /**
     * An issue cannot be handed to someone outside the support team, and says so.
     */
    public function test_set_current_supporter_refuses_an_outsider(): void {
        global $DB;

        $issue = $this->create_issue();
        $outsider = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($outsider->id, $this->course->id, 'student');

        $this->setUser($this->supporter);
        $this->assertFalse(lib::set_current_supporter($issue->discussionid, $outsider->id));

        $this->assertEquals(
            0,
            $DB->get_field('local_edusupport_issues', 'currentsupporter', ['discussionid' => $issue->discussionid])
        );
        $this->assertFalse($DB->record_exists('local_edusupport_subscr', [
            'discussionid' => $issue->discussionid,
            'userid' => $outsider->id,
        ]));
    }

    /**
     * Every way an assignment can be refused names its own reason.
     */
    public function test_validate_supporter_assignment_reports_the_reason(): void {
        $issue = $this->create_issue();
        $outsider = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($outsider->id, $this->course->id, 'student');

        $this->setUser($this->supporter);
        $this->assertNull(lib::validate_supporter_assignment($issue->discussionid, $this->supporter->id));
        $this->assertSame(
            'error:targetnotasupporter',
            lib::validate_supporter_assignment($issue->discussionid, $outsider->id)
        );
        $this->assertSame(
            'error:unknowndiscussion',
            lib::validate_supporter_assignment(0, $this->supporter->id)
        );

        $plainforum = $this->getDataGenerator()->create_module('forum', ['course' => $this->course->id]);
        $plaindiscussion = $this->getDataGenerator()->get_plugin_generator('mod_forum')->create_discussion([
            'course' => $this->course->id,
            'forum' => $plainforum->id,
            'userid' => $this->student->id,
            'name' => 'Kein Ticket',
        ]);
        $this->assertSame(
            'error:notasupportforum',
            lib::validate_supporter_assignment($plaindiscussion->id, $this->supporter->id)
        );

        // Someone outside the support team may not hand over anything at all.
        $this->setUser($this->student);
        $this->assertSame(
            'error:notasupporter',
            lib::validate_supporter_assignment($issue->discussionid, $this->supporter->id)
        );
    }

    /**
     * Every refusal reason has a language string in English and German.
     */
    public function test_every_refusal_reason_has_a_string(): void {
        $reasons = [
            'error:notasupportforum',
            'error:notasupporter',
            'error:targetnotasupporter',
            'error:unknowndiscussion',
        ];
        foreach ($reasons as $reason) {
            foreach (['en', 'de'] as $language) {
                $this->assertNotEmpty(
                    get_string_manager()->get_string($reason, 'local_edusupport', null, $language),
                    "Missing {$language} string for {$reason}."
                );
            }
        }
    }

    /**
     * The priority of an issue can be raised and lowered.
     */
    public function test_set_prioritylvl(): void {
        global $DB;

        $issue = $this->create_issue();

        $this->setUser($this->supporter);
        $this->assertTrue(lib::set_prioritylvl($issue->discussionid, 3));
        $this->assertEquals(
            3,
            $DB->get_field('local_edusupport_issues', 'priority', ['discussionid' => $issue->discussionid])
        );

        lib::set_prioritylvl($issue->discussionid, 1);
        $this->assertEquals(
            1,
            $DB->get_field('local_edusupport_issues', 'priority', ['discussionid' => $issue->discussionid])
        );
    }

    /**
     * Subscribing twice leaves a single subscription, unsubscribing removes it.
     */
    public function test_subscriptions_are_added_once_and_removed(): void {
        global $DB;

        $issue = $this->create_issue();

        $this->setUser($this->supporter);
        lib::subscription_add($issue->discussionid, $this->supporter->id);
        lib::subscription_add($issue->discussionid, $this->supporter->id);

        $this->assertSame(1, $DB->count_records('local_edusupport_subscr', [
            'discussionid' => $issue->discussionid,
            'userid' => $this->supporter->id,
        ]));

        lib::subscription_remove($issue->discussionid, $this->supporter->id);

        $this->assertSame(0, $DB->count_records('local_edusupport_subscr', [
            'discussionid' => $issue->discussionid,
            'userid' => $this->supporter->id,
        ]));
    }

    /**
     * Escalating to 2nd level picks a supporter and subscribes them.
     */
    public function test_set_2nd_level_assigns_a_supporter(): void {
        global $DB;

        $issue = $this->create_issue();

        $this->setUser($this->supporter);
        $this->assertTrue(lib::set_2nd_level($issue->discussionid));

        $this->assertEquals(
            $this->supporter->id,
            $DB->get_field('local_edusupport_issues', 'currentsupporter', ['discussionid' => $issue->discussionid])
        );
        $this->assertTrue($DB->record_exists('local_edusupport_subscr', [
            'discussionid' => $issue->discussionid,
            'userid' => $this->supporter->id,
        ]));
    }

    /**
     * A supporter on holiday is passed over while someone else is available.
     */
    public function test_set_2nd_level_skips_a_supporter_on_holiday(): void {
        global $DB;

        set_config('holidaymodeenabled', 1, 'local_edusupport');

        $away = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($away->id, $this->course->id, 'teacher');
        $this->generator->create_supporter(['userid' => $away->id, 'holidaymode' => time() + DAYSECS]);

        $issue = $this->create_issue();

        $this->setUser($this->supporter);
        lib::set_2nd_level($issue->discussionid);

        $this->assertEquals(
            $this->supporter->id,
            $DB->get_field('local_edusupport_issues', 'currentsupporter', ['discussionid' => $issue->discussionid])
        );
    }

    /**
     * When the whole team is away the issue is still assigned rather than left unowned.
     */
    public function test_set_2nd_level_falls_back_when_everyone_is_on_holiday(): void {
        global $DB;

        set_config('holidaymodeenabled', 1, 'local_edusupport');
        $DB->set_field('local_edusupport_supporters', 'holidaymode', time() + DAYSECS, [
            'userid' => $this->supporter->id,
        ]);

        $issue = $this->create_issue();

        $this->setUser($this->supporter);
        lib::set_2nd_level($issue->discussionid);

        $this->assertEquals(
            $this->supporter->id,
            $DB->get_field('local_edusupport_issues', 'currentsupporter', ['discussionid' => $issue->discussionid])
        );
    }
}
