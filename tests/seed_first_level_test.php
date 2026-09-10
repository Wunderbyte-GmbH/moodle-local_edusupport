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
 * Tests for filling the first level of support courses from the eligibility rule.
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
 * Tests for filling the first level of support courses from the eligibility rule.
 *
 * @package    local_edusupport
 * @category   test
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_edusupport\lib::seed_first_level_from_capabilities
 */
final class seed_first_level_test extends advanced_testcase {
    /** @var stdClass a course holding a support forum. */
    private $course;

    /** @var \local_edusupport_generator the plugin data generator. */
    private $generator;

    /**
     * Set up a support course.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $this->generator = $this->getDataGenerator()->get_plugin_generator('local_edusupport');
        $this->course = $this->getDataGenerator()->create_course();
        $forum = $this->getDataGenerator()->create_module('forum', ['course' => $this->course->id]);
        $this->generator->create_supportforum(['forumid' => $forum->id]);
    }

    /**
     * Enrol a new user in the support course.
     *
     * @param string $role the shortname of the role to enrol with.
     * @return stdClass the user.
     */
    private function user_with_role(string $role): stdClass {
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $this->course->id, $role);
        return $user;
    }

    /**
     * A dry run reports what would happen and writes nothing.
     */
    public function test_a_dry_run_writes_nothing(): void {
        $teacher = $this->user_with_role('editingteacher');
        $this->user_with_role('student');

        $report = lib::seed_first_level_from_capabilities(true);

        $this->assertArrayHasKey($this->course->id, $report);
        $this->assertSame(1, $report[$this->course->id]['eligible']);
        $this->assertSame(0, $report[$this->course->id]['assigned']);
        $this->assertSame(1, $report[$this->course->id]['toadd']);
        $this->assertSame([fullname($teacher)], $report[$this->course->id]['names']);

        $this->assertSame([], lib::get_first_level($this->course->id));
    }

    /**
     * Applying assigns the eligible people, and only those.
     */
    public function test_applying_assigns_the_eligible_people(): void {
        $teacher = $this->user_with_role('editingteacher');
        $assistant = $this->user_with_role('teacher');
        $student = $this->user_with_role('student');

        lib::seed_first_level_from_capabilities(false);

        $assigned = array_column(lib::get_first_level($this->course->id), 'userid');
        sort($assigned);
        $expected = [$teacher->id, $assistant->id];
        sort($expected);

        $this->assertEquals($expected, $assigned);
        $this->assertNotContains($student->id, $assigned);
    }

    /**
     * A second run has nothing left to do.
     */
    public function test_running_twice_changes_nothing(): void {
        $this->user_with_role('editingteacher');

        lib::seed_first_level_from_capabilities(false);
        $before = lib::get_first_level($this->course->id);

        $report = lib::seed_first_level_from_capabilities(true);
        $this->assertSame(0, $report[$this->course->id]['toadd']);

        lib::seed_first_level_from_capabilities(false);
        $this->assertEquals(array_keys($before), array_keys(lib::get_first_level($this->course->id)));
    }

    /**
     * An assignment somebody made on purpose is never taken away.
     *
     * This is what separates the tool from a migration: it only ever adds. Somebody who was
     * assigned by hand and has since lost the rights that would make them eligible stays.
     */
    public function test_an_existing_assignment_survives(): void {
        global $DB;

        $chosen = $this->user_with_role('editingteacher');
        lib::assign_first_level($this->course->id, [$chosen->id]);

        // Take the rights away, so the eligibility rule would no longer find this person.
        $context = \context_course::instance($this->course->id);
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
        role_change_permission($roleid, $context, 'moodle/course:viewhiddenactivities', CAP_PROHIBIT);
        $this->assertArrayNotHasKey($chosen->id, lib::get_assignable_users($this->course->id));

        lib::seed_first_level_from_capabilities(false);

        $assigned = array_column(lib::get_first_level($this->course->id), 'userid');
        $this->assertEquals([$chosen->id], $assigned);
    }

    /**
     * Courses without a support forum are left alone.
     */
    public function test_courses_without_a_support_forum_are_skipped(): void {
        $other = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $other->id, 'editingteacher');

        $report = lib::seed_first_level_from_capabilities(true);

        $this->assertArrayNotHasKey($other->id, $report);
    }
}
