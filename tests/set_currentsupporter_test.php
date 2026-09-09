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
 * Tests for handing an issue to a supporter through the external function.
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
 * Tests for handing an issue to a supporter through the external function.
 *
 * local_edusupport_external still builds on lib/externallib.php, the deprecated compatibility
 * shim, which refuses to be loaded outside an isolated process. Hence the annotation below and
 * the require inside setUp() rather than at the top of this file.
 *
 * @package    local_edusupport
 * @category   test
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_edusupport_external::set_currentsupporter
 * @runTestsInSeparateProcesses
 */
final class set_currentsupporter_test extends advanced_testcase {
    /** @var stdClass the course holding the support forum. */
    private $course;

    /** @var stdClass the support forum. */
    private $forum;

    /** @var stdClass a member of the global support team. */
    private $supporter;

    /** @var stdClass the user asking for support. */
    private $student;

    /** @var stdClass the issue to hand over. */
    private $issue;

    /**
     * Set up a support forum holding one issue.
     */
    protected function setUp(): void {
        global $CFG;

        parent::setUp();
        require_once($CFG->dirroot . '/local/edusupport/externallib.php');

        $this->resetAfterTest(true);
        $this->preventResetByRollback();
        $this->redirectMessages();

        set_config('sendmsgonset2ndlvl', 0, 'local_edusupport');
        set_config('sendsupporterassignments', 0, 'local_edusupport');

        $this->setAdminUser();
        $datagenerator = $this->getDataGenerator();
        $generator = $datagenerator->get_plugin_generator('local_edusupport');

        $this->course = $datagenerator->create_course();
        $this->forum = $datagenerator->create_module('forum', ['course' => $this->course->id]);
        $generator->create_supportforum(['forumid' => $this->forum->id]);

        $this->supporter = $datagenerator->create_user();
        $datagenerator->enrol_user($this->supporter->id, $this->course->id, 'teacher');
        $generator->create_supporter(['userid' => $this->supporter->id]);

        $this->student = $datagenerator->create_user();
        $datagenerator->enrol_user($this->student->id, $this->course->id, 'student');

        $this->issue = $generator->create_issue([
            'forumid' => $this->forum->id,
            'userid' => $this->student->id,
            'subject' => 'Drucker geht nicht',
        ]);
    }

    /**
     * A permitted hand over reports success and actually assigns the supporter.
     */
    public function test_a_permitted_handover_reports_success(): void {
        global $DB;

        $this->setUser($this->supporter);
        $result = local_edusupport_external::set_currentsupporter(
            $this->issue->discussionid,
            $this->supporter->id
        );

        $this->assertEquals(1, $result);
        $this->assertEquals(
            $this->supporter->id,
            $DB->get_field('local_edusupport_issues', 'currentsupporter', [
                'discussionid' => $this->issue->discussionid,
            ])
        );
    }

    /**
     * Handing an issue to someone outside the support team fails loudly.
     *
     * The refusal used to be reported as success: set_current_supporter() returned -3, which
     * PHP coerced to true on its bool return type, and the caller reloaded the page as if the
     * assignment had worked.
     */
    public function test_a_refused_handover_raises_the_reason(): void {
        global $DB;

        $outsider = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($outsider->id, $this->course->id, 'student');

        $this->setUser($this->supporter);

        try {
            local_edusupport_external::set_currentsupporter($this->issue->discussionid, $outsider->id);
            $this->fail('Handing the issue to a non supporter should have raised an exception.');
        } catch (moodle_exception $e) {
            $this->assertSame('error:targetnotasupporter', $e->errorcode);
            $this->assertSame('local_edusupport', $e->module);
        }

        $this->assertEquals(
            0,
            $DB->get_field('local_edusupport_issues', 'currentsupporter', [
                'discussionid' => $this->issue->discussionid,
            ])
        );
    }

    /**
     * Someone outside the support team cannot hand over an issue at all.
     */
    public function test_an_outsider_cannot_hand_over_an_issue(): void {
        $this->setUser($this->student);

        try {
            local_edusupport_external::set_currentsupporter($this->issue->discussionid, $this->supporter->id);
            $this->fail('A non supporter should not be able to hand over an issue.');
        } catch (moodle_exception $e) {
            $this->assertSame('error:notasupporter', $e->errorcode);
        }
    }
}
