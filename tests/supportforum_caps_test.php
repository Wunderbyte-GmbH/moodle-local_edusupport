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
 * Tests for the protection a support forum and its course get.
 *
 * @package    local_edusupport
 * @category   test
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_edusupport;

use advanced_testcase;
use context_course;
use context_module;
use stdClass;

/**
 * Tests for the protection a support forum and its course get.
 *
 * @package    local_edusupport
 * @category   test
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_edusupport\lib::supportforum_managecaps
 */
final class supportforum_caps_test extends advanced_testcase {
    /** @var string[] each protected capability with where it is prohibited. */
    private const PROTECTED = [
        'moodle/course:activityvisibility' => 'module',
        'moodle/course:changecategory' => 'course',
        'moodle/course:changefullname' => 'course',
        'moodle/course:changeidnumber' => 'course',
        'moodle/course:changeshortname' => 'course',
        'moodle/course:delete' => 'course',
        'moodle/course:enrolconfig' => 'course',
        'moodle/course:manageactivities' => 'module',
        'moodle/course:reset' => 'course',
        'moodle/course:visibility' => 'course',
        'moodle/restore:configure' => 'course',
        'moodle/restore:restorecourse' => 'course',
        'moodle/restore:restoresection' => 'course',
        'moodle/restore:viewautomatedfilearea' => 'course',
    ];

    /** @var stdClass the forum being protected. */
    private stdClass $forum;

    /** @var context_module */
    private context_module $modcontext;

    /** @var context_course */
    private context_course $coursecontext;

    /**
     * Create a course with a forum.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $this->forum = $this->getDataGenerator()->create_module('forum', ['course' => $course->id]);
        $this->modcontext = context_module::instance($this->forum->cmid);
        $this->coursecontext = context_course::instance($course->id);
    }

    /**
     * What a role is prohibited from in the forum and its course.
     *
     * @param int $roleid
     * @return string[] capability => 'module' or 'course', sorted by capability.
     */
    private function prohibited(int $roleid): array {
        global $DB;

        [$insql, $params] = $DB->get_in_or_equal([$this->modcontext->id, $this->coursecontext->id], SQL_PARAMS_NAMED);
        $records = $DB->get_records_select(
            'role_capabilities',
            "roleid = :roleid AND permission = :permission AND contextid $insql",
            $params + ['roleid' => $roleid, 'permission' => CAP_PROHIBIT]
        );

        $result = [];
        foreach ($records as $record) {
            $result[$record->capability] = $record->contextid == $this->modcontext->id ? 'module' : 'course';
        }
        ksort($result);
        return $result;
    }

    /**
     * The protection goes to the role every logged in person has.
     */
    public function test_protection_goes_to_the_default_user_role(): void {
        global $CFG;

        $this->assertTrue(lib::supportforum_managecaps($this->forum->id, true));

        $this->assertSame(self::PROTECTED, $this->prohibited($CFG->defaultuserroleid));
    }

    /**
     * A site with another default role gets that role protected, not whatever has id 7.
     */
    public function test_a_different_default_role_is_respected(): void {
        global $CFG;

        $previous = (int) $CFG->defaultuserroleid;
        $everyone = $this->getDataGenerator()->create_role(['shortname' => 'everyone']);
        set_config('defaultuserroleid', $everyone);

        lib::supportforum_managecaps($this->forum->id, true);

        $this->assertSame(self::PROTECTED, $this->prohibited($everyone));
        $this->assertSame([], $this->prohibited($previous));
    }

    /**
     * Turning the support forum off again removes every prohibition it set.
     */
    public function test_disabling_lifts_the_protection(): void {
        global $CFG;

        lib::supportforum_managecaps($this->forum->id, true);
        lib::supportforum_managecaps($this->forum->id, false);

        $this->assertSame([], $this->prohibited($CFG->defaultuserroleid));
    }

    /**
     * Without a default role there is nobody to protect against, and nothing is written.
     */
    public function test_nothing_happens_without_a_default_role(): void {
        global $DB;

        set_config('defaultuserroleid', 0);

        $this->assertFalse(lib::supportforum_managecaps($this->forum->id, true));
        $this->assertFalse($DB->record_exists_select(
            'role_capabilities',
            'contextid IN (:module, :course)',
            ['module' => $this->modcontext->id, 'course' => $this->coursecontext->id]
        ));
    }
}
