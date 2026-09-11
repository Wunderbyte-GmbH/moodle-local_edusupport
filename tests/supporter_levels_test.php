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
 * Tests for the separation of first and second level support.
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
 * Tests for the separation of first and second level support.
 *
 * @package    local_edusupport
 * @category   test
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_edusupport\lib::get_first_level
 * @covers     \local_edusupport\lib::get_second_level
 * @covers     \local_edusupport\lib::is_first_level
 * @covers     \local_edusupport\lib::is_second_level
 * @covers     \local_edusupport\lib::get_assignable_users
 * @covers     \local_edusupport\lib::is_supportteam
 * @covers     \local_edusupport\lib::get_course_supporters
 * @covers     \local_edusupport\lib::can_assign_first_level
 * @covers     \local_edusupport\lib::assign_first_level
 * @covers     \local_edusupport\event\supportuser_deleted
 * @covers     \local_edusupport\lib::supportforum_rolecheck
 * @covers     \local_edusupport\lib::set_2nd_level
 * @covers     \local_edusupport\lib::subscription_add
 * @covers     \local_edusupport\lib::validate_supporter_assignment
 */
final class supporter_levels_test extends advanced_testcase {
    /** @var stdClass a course with a support forum. */
    private $course;

    /** @var \local_edusupport_generator the plugin data generator. */
    private $generator;

    /**
     * Set up a course.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $this->generator = $this->getDataGenerator()->get_plugin_generator('local_edusupport');
        $this->course = $this->getDataGenerator()->create_course();
    }

    /**
     * Create a user with a role in the course.
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
     * The platform team is not reported as first level of any course.
     */
    public function test_get_first_level_ignores_the_platform_team(): void {
        $platform = $this->getDataGenerator()->create_user();
        $this->generator->create_supporter(['userid' => $platform->id]);

        $this->assertSame([], lib::get_first_level($this->course->id));
        $this->assertCount(1, lib::get_second_level());
    }

    /**
     * A course assignment is not reported as platform team.
     */
    public function test_get_second_level_ignores_course_assignments(): void {
        $local = $this->user_with_role('editingteacher');
        $this->generator->create_supporter(['userid' => $local->id, 'courseid' => $this->course->id]);

        $this->assertSame([], lib::get_second_level());
        $this->assertCount(1, lib::get_first_level($this->course->id));
    }

    /**
     * Asking for the first level of the sentinel id yields nothing, it is not a course.
     */
    public function test_the_sentinel_is_never_a_course(): void {
        $platform = $this->getDataGenerator()->create_user();
        $this->generator->create_supporter(['userid' => $platform->id]);

        $this->assertSame([], lib::get_first_level(lib::SYSTEM_COURSE_ID));
        $this->assertFalse(lib::is_first_level($platform->id, lib::SYSTEM_COURSE_ID));
    }

    /**
     * The two predicates answer for their own level only.
     */
    public function test_the_predicates_stay_on_their_level(): void {
        $platform = $this->getDataGenerator()->create_user();
        $local = $this->user_with_role('editingteacher');
        $this->generator->create_supporter(['userid' => $platform->id]);
        $this->generator->create_supporter(['userid' => $local->id, 'courseid' => $this->course->id]);

        $this->assertTrue(lib::is_second_level($platform->id));
        $this->assertFalse(lib::is_first_level($platform->id, $this->course->id));

        $this->assertTrue(lib::is_first_level($local->id, $this->course->id));
        $this->assertFalse(lib::is_second_level($local->id));
    }

    /**
     * A supporter on holiday is left out when only the available ones are wanted.
     */
    public function test_get_second_level_can_skip_a_holiday(): void {
        $available = $this->getDataGenerator()->create_user();
        $away = $this->getDataGenerator()->create_user();
        $this->generator->create_supporter(['userid' => $available->id]);
        $this->generator->create_supporter(['userid' => $away->id, 'holidaymode' => time() + DAYSECS]);

        $this->assertCount(2, lib::get_second_level());

        $onduty = lib::get_second_level(false, true);
        $this->assertCount(1, $onduty);
        $this->assertEquals($available->id, reset($onduty)->userid);
    }

    /**
     * The eligibility rule needs both capabilities, not either of them.
     *
     * This is the trap worth pinning: handing a list of capabilities to get_enrolled_users()
     * means "one of these is enough". A student has mod/forum:startdiscussion but not
     * moodle/course:viewhiddenactivities, so an OR would let every student through.
     */
    public function test_assignable_users_need_both_capabilities(): void {
        $editingteacher = $this->user_with_role('editingteacher');
        $teacher = $this->user_with_role('teacher');
        $student = $this->user_with_role('student');

        $assignable = lib::get_assignable_users($this->course->id);

        $this->assertArrayHasKey($editingteacher->id, $assignable);
        $this->assertArrayHasKey($teacher->id, $assignable);
        $this->assertArrayNotHasKey($student->id, $assignable);
    }

    /**
     * Somebody who may see hidden activities but not post is not eligible either.
     */
    public function test_a_reader_without_posting_rights_is_not_assignable(): void {
        global $DB;

        $reader = $this->user_with_role('teacher');
        $context = \context_course::instance($this->course->id);
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'teacher'], MUST_EXIST);
        role_change_permission($roleid, $context, 'mod/forum:startdiscussion', CAP_PROHIBIT);

        $this->assertArrayNotHasKey($reader->id, lib::get_assignable_users($this->course->id));
    }

    /**
     * A manager assigned site wide but not enrolled is not eligible.
     *
     * This is the case that started the whole rework: such accounts used to appear as support
     * contacts because the capability was all that was asked for.
     */
    public function test_an_unenrolled_manager_is_not_assignable(): void {
        global $DB;

        $manager = $this->getDataGenerator()->create_user();
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST);
        role_assign($roleid, $manager->id, \context_system::instance()->id);

        $this->assertArrayNotHasKey($manager->id, lib::get_assignable_users($this->course->id));
    }

    /**
     * The search narrows the list down.
     */
    public function test_assignable_users_can_be_searched(): void {
        $found = $this->getDataGenerator()->create_user(['lastname' => 'Zimmermann']);
        $other = $this->getDataGenerator()->create_user(['lastname' => 'Ackermann']);
        foreach ([$found, $other] as $user) {
            $this->getDataGenerator()->enrol_user($user->id, $this->course->id, 'editingteacher');
        }

        $result = lib::get_assignable_users($this->course->id, 'Zimmer');

        $this->assertArrayHasKey($found->id, $result);
        $this->assertArrayNotHasKey($other->id, $result);
    }

    /**
     * Escalation only reaches those marked assignable.
     *
     * Which of the platform team escalation may pick used to be encoded in the free text
     * support level, where an empty string meant yes. On a real site that field held labels
     * people had typed, so almost nobody was ever picked automatically.
     */
    public function test_only_assignable_supporters_are_offered_to_escalation(): void {
        $byhandonly = $this->getDataGenerator()->create_user();
        $assignable = $this->getDataGenerator()->create_user();
        $this->generator->create_supporter([
            'userid' => $byhandonly->id,
            'supportlevel' => 'database',
            'autoassign' => 0,
        ]);
        $this->generator->create_supporter([
            'userid' => $assignable->id,
            'supportlevel' => 'anything at all',
        ]);

        $this->assertCount(2, lib::get_second_level());

        $offered = lib::get_second_level(true);
        $this->assertCount(1, $offered);
        $this->assertEquals($assignable->id, reset($offered)->userid);
    }

    /**
     * A label in the support level says nothing about the level or about assignability.
     */
    public function test_the_support_level_is_only_a_label(): void {
        $labelled = $this->getDataGenerator()->create_user();
        $this->generator->create_supporter(['userid' => $labelled->id, 'supportlevel' => '1st level']);

        $this->assertTrue(lib::is_second_level($labelled->id));
        $this->assertCount(1, lib::get_second_level(true));
    }

    /**
     * A ticket is never handed to somebody who only supports a course.
     */
    public function test_a_course_supporter_cannot_be_handed_a_ticket(): void {
        $platform = $this->getDataGenerator()->create_user();
        $local = $this->user_with_role('editingteacher');
        $this->generator->create_supporter(['userid' => $platform->id]);
        $this->generator->create_supporter(['userid' => $local->id, 'courseid' => $this->course->id]);

        $forum = $this->getDataGenerator()->create_module('forum', ['course' => $this->course->id]);
        $this->generator->create_supportforum(['forumid' => $forum->id]);
        $issue = $this->generator->create_issue(['forumid' => $forum->id]);

        $this->setUser($platform);
        $this->assertNull(lib::validate_supporter_assignment($issue->discussionid, $platform->id));
        $this->assertSame(
            'error:targetnotasupporter',
            lib::validate_supporter_assignment($issue->discussionid, $local->id)
        );
    }

    /**
     * Only the platform team follows tickets, first level works in the forum.
     */
    public function test_a_course_supporter_is_not_subscribed_to_tickets(): void {
        global $DB;

        $local = $this->user_with_role('editingteacher');
        $this->generator->create_supporter(['userid' => $local->id, 'courseid' => $this->course->id]);

        $forum = $this->getDataGenerator()->create_module('forum', ['course' => $this->course->id]);
        $this->generator->create_supportforum(['forumid' => $forum->id]);
        $issue = $this->generator->create_issue(['forumid' => $forum->id]);

        lib::subscription_add($issue->discussionid, $local->id);

        $this->assertFalse($DB->record_exists('local_edusupport_subscr', [
            'discussionid' => $issue->discussionid,
            'userid' => $local->id,
        ]));
    }

    /**
     * Escalation never lands on a course supporter.
     */
    public function test_escalation_stays_within_the_platform_team(): void {
        global $DB;

        $platform = $this->getDataGenerator()->create_user();
        $local = $this->user_with_role('editingteacher');
        $this->generator->create_supporter(['userid' => $platform->id]);
        $this->generator->create_supporter(['userid' => $local->id, 'courseid' => $this->course->id]);

        $forum = $this->getDataGenerator()->create_module('forum', ['course' => $this->course->id]);
        $this->generator->create_supportforum(['forumid' => $forum->id]);
        $issue = $this->generator->create_issue(['forumid' => $forum->id]);

        $this->setUser($platform);
        lib::set_2nd_level($issue->discussionid);

        $this->assertEquals(
            $platform->id,
            $DB->get_field('local_edusupport_issues', 'currentsupporter', ['discussionid' => $issue->discussionid])
        );
    }

    /**
     * Both levels keep their role in the support forum.
     *
     * The role check has to mirror its own assignment query, otherwise every run would hand
     * the role out and take it away again.
     */
    public function test_both_levels_hold_the_forum_role(): void {
        global $DB;

        $platform = $this->getDataGenerator()->create_user();
        $local = $this->user_with_role('editingteacher');

        $forum = $this->getDataGenerator()->create_module('forum', ['course' => $this->course->id]);
        $this->generator->create_supportforum(['forumid' => $forum->id]);
        $this->generator->create_supporter(['userid' => $platform->id]);
        $this->generator->create_supporter(['userid' => $local->id, 'courseid' => $this->course->id]);

        $cm = get_coursemodule_from_instance('forum', $forum->id);
        $context = \context_module::instance($cm->id);
        $roleid = get_config('local_edusupport', 'supportteamrole');

        foreach ([$platform->id, $local->id] as $userid) {
            $this->assertTrue(
                $DB->record_exists('role_assignments', [
                    'roleid' => $roleid,
                    'userid' => $userid,
                    'contextid' => $context->id,
                ]),
                "User $userid should hold the support role in the forum."
            );
        }

        // A second run must not take away what the first one handed out.
        lib::supportforum_rolecheck($forum->id);
        foreach ([$platform->id, $local->id] as $userid) {
            $this->assertTrue($DB->record_exists('role_assignments', [
                'roleid' => $roleid,
                'userid' => $userid,
                'contextid' => $context->id,
            ]));
        }
    }

    /**
     * Assigning replaces the whole set and refuses whoever is not eligible.
     */
    public function test_assign_first_level_replaces_the_set(): void {
        $keep = $this->user_with_role('editingteacher');
        $added = $this->user_with_role('teacher');
        $dropped = $this->user_with_role('editingteacher');
        $student = $this->user_with_role('student');

        lib::assign_first_level($this->course->id, [$keep->id, $dropped->id]);
        $this->assertCount(2, lib::get_first_level($this->course->id));

        $result = lib::assign_first_level($this->course->id, [$keep->id, $added->id, $student->id]);

        $this->assertEquals([$added->id], $result['added']);
        $this->assertEquals([$dropped->id], $result['removed']);
        $this->assertEquals([$student->id], $result['refused']);

        $assigned = array_column(lib::get_first_level($this->course->id), 'userid');
        sort($assigned);
        $expected = [$keep->id, $added->id];
        sort($expected);
        $this->assertEquals($expected, $assigned);
    }

    /**
     * Taking somebody off the first level is logged as a deletion.
     *
     * The event used to report itself as a creation, so log filters for deletions missed it.
     */
    public function test_removing_a_supporter_is_logged_as_a_deletion(): void {
        $supporter = $this->user_with_role('editingteacher');
        lib::assign_first_level($this->course->id, [$supporter->id]);

        $sink = $this->redirectEvents();
        lib::assign_first_level($this->course->id, []);
        $events = array_values(array_filter(
            $sink->get_events(),
            fn($event) => $event instanceof event\supportuser_deleted
        ));
        $sink->close();

        $this->assertCount(1, $events);
        $this->assertSame('d', $events[0]->crud);
        $this->assertEquals($supporter->id, $events[0]->relateduserid);
    }

    /**
     * The platform team is maintained elsewhere and cannot be written through this door.
     */
    public function test_assign_first_level_refuses_the_sentinel(): void {
        $user = $this->getDataGenerator()->create_user();

        $result = lib::assign_first_level(lib::SYSTEM_COURSE_ID, [$user->id]);

        $this->assertSame([], $result['added']);
        $this->assertFalse(lib::is_second_level($user->id));
    }

    /**
     * Assigned supporters are the ones reported as responsible for a request.
     */
    public function test_get_course_supporters_reads_the_assignment(): void {
        $assigned = $this->user_with_role('editingteacher');
        $unassigned = $this->user_with_role('editingteacher');

        $forum = $this->getDataGenerator()->create_module('forum', ['course' => $this->course->id]);
        $this->generator->create_supportforum(['forumid' => $forum->id]);
        lib::assign_first_level($this->course->id, [$assigned->id]);

        $supporters = lib::get_course_supporters($forum);

        $this->assertArrayHasKey($assigned->id, $supporters);
        $this->assertArrayNotHasKey($unassigned->id, $supporters);
    }

    /**
     * Without an assignment nobody is reported, which is what makes a request escalate.
     */
    public function test_get_course_supporters_is_empty_without_an_assignment(): void {
        $this->user_with_role('editingteacher');
        $forum = $this->getDataGenerator()->create_module('forum', ['course' => $this->course->id]);
        $this->generator->create_supportforum(['forumid' => $forum->id]);

        $this->assertSame([], lib::get_course_supporters($forum));
    }

    /**
     * Assigning gives the person the support role in the forum of that course.
     */
    public function test_assigning_grants_the_forum_role(): void {
        global $DB;

        $supporter = $this->user_with_role('editingteacher');
        $forum = $this->getDataGenerator()->create_module('forum', ['course' => $this->course->id]);
        $this->generator->create_supportforum(['forumid' => $forum->id]);

        lib::assign_first_level($this->course->id, [$supporter->id]);

        $cm = get_coursemodule_from_instance('forum', $forum->id);
        $context = \context_module::instance($cm->id);
        $roleid = get_config('local_edusupport', 'supportteamrole');
        $conditions = ['roleid' => $roleid, 'userid' => $supporter->id, 'contextid' => $context->id];

        $this->assertTrue($DB->record_exists('role_assignments', $conditions));

        // Taking the assignment away takes the role with it.
        lib::assign_first_level($this->course->id, []);
        $this->assertFalse($DB->record_exists('role_assignments', $conditions));
    }

    /**
     * Who may assign follows the capability, not a hard coded role.
     */
    public function test_can_assign_first_level_follows_the_capability(): void {
        $editingteacher = $this->user_with_role('editingteacher');
        $teacher = $this->user_with_role('teacher');
        $student = $this->user_with_role('student');

        $this->assertTrue(lib::can_assign_first_level($this->course->id, $editingteacher->id));
        $this->assertFalse(lib::can_assign_first_level($this->course->id, $teacher->id));
        $this->assertFalse(lib::can_assign_first_level($this->course->id, $student->id));
    }

    /**
     * The old entry point keeps answering exactly as it did.
     */
    public function test_is_supportteam_still_answers_as_before(): void {
        $platform = $this->getDataGenerator()->create_user();
        $local = $this->user_with_role('editingteacher');
        $this->generator->create_supporter(['userid' => $platform->id]);
        $this->generator->create_supporter(['userid' => $local->id, 'courseid' => $this->course->id]);

        // Without a course the question has always been about the platform team.
        $this->assertTrue(lib::is_supportteam($platform->id));
        $this->assertFalse(lib::is_supportteam($local->id));

        // With a course, and by default, either level answers yes.
        $this->assertTrue(lib::is_supportteam($platform->id, $this->course->id));
        $this->assertTrue(lib::is_supportteam($local->id, $this->course->id));

        // Asking for the course alone leaves the platform team out.
        $this->assertFalse(lib::is_supportteam($platform->id, $this->course->id, false));
        $this->assertTrue(lib::is_supportteam($local->id, $this->course->id, false));
    }
}
