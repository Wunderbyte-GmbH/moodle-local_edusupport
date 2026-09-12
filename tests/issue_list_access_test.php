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
 * Tests for who is let into the list of support issues.
 *
 * @package    local_edusupport
 * @category   test
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_edusupport;

use advanced_testcase;

/**
 * Tests for who is let into the list of support issues.
 *
 * The navigation offers issues.php through a button, and the page decides for itself who may
 * look at it. Those two used to disagree about site admins, who were shown the button and then
 * refused by the page unless they had also been added to the platform team.
 *
 * @package    local_edusupport
 * @category   test
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_edusupport\lib::can_view_issues
 */
final class issue_list_access_test extends advanced_testcase {
    /**
     * Reset between tests.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
    }

    /**
     * A site admin is let in without being a member of the support team.
     */
    public function test_a_site_admin_may_view_the_issue_list(): void {
        global $DB;

        $this->setAdminUser();

        $this->assertFalse(
            $DB->record_exists('local_edusupport_supporters', ['courseid' => lib::SYSTEM_COURSE_ID]),
            'The point of this test is an admin who is not on the support team.'
        );
        $this->assertTrue(lib::can_view_issues());
    }

    /**
     * The platform team is let in, which is what the page was written for.
     */
    public function test_the_platform_team_may_view_the_issue_list(): void {
        $supporter = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->get_plugin_generator('local_edusupport')
            ->create_supporter(['userid' => $supporter->id]);

        $this->assertTrue(lib::can_view_issues($supporter->id));
    }

    /**
     * Supporting one course is not enough: the list covers the whole site.
     */
    public function test_first_level_support_alone_does_not_open_the_issue_list(): void {
        $generator = $this->getDataGenerator();

        $course = $generator->create_course();
        $coursesupporter = $generator->create_user();
        $generator->enrol_user($coursesupporter->id, $course->id, 'editingteacher');
        lib::assign_first_level($course->id, [$coursesupporter->id]);

        $this->assertTrue(lib::is_first_level($coursesupporter->id, $course->id));
        $this->assertFalse(lib::can_view_issues($coursesupporter->id));
    }

    /**
     * Somebody unrelated stays out.
     */
    public function test_an_ordinary_user_may_not_view_the_issue_list(): void {
        $user = $this->getDataGenerator()->create_user();

        $this->assertFalse(lib::can_view_issues($user->id));
    }

    /**
     * The button in the navigation is offered to exactly the people the page lets in.
     *
     * Asking the two separately is what let them drift apart, so this pins them together.
     */
    public function test_the_navigation_offers_the_issue_list_to_whoever_may_see_it(): void {
        $generator = $this->getDataGenerator();

        $admin = get_admin();
        $supporter = $generator->create_user();
        $generator->get_plugin_generator('local_edusupport')->create_supporter(['userid' => $supporter->id]);
        $outsider = $generator->create_user();

        foreach ([$admin, $supporter, $outsider] as $user) {
            $this->setUser($user);
            // The menu is cached per user, and the cache outlives setUser().
            \cache_helper::purge_by_event('setbacksupportmenu');

            $offered = strpos(lib::get_supportmenu(), '/local/edusupport/issues.php') !== false;
            $this->assertSame(
                lib::can_view_issues($user->id),
                $offered,
                'The button and the page disagree about user ' . $user->id
            );
        }
    }
}
