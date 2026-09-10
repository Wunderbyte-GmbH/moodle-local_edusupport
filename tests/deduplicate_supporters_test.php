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
 * Tests for the cleanup that precedes the unique key on the supporter table.
 *
 * @package    local_edusupport
 * @category   test
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_edusupport;

use advanced_testcase;
use xmldb_index;
use xmldb_table;

/**
 * Tests for the cleanup that precedes the unique key on the supporter table.
 *
 * @package    local_edusupport
 * @category   test
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_edusupport\lib::deduplicate_supporters
 */
final class deduplicate_supporters_test extends advanced_testcase {
    /**
     * Reproduce the state the cleanup actually runs in.
     *
     * On a fresh install the unique key exists from the start, so duplicates cannot arise at
     * all. deduplicate_supporters() only ever runs during the upgrade of a site that predates
     * the key, and that is the situation these tests need: the key has to be gone before the
     * rows can be written.
     */
    protected function setUp(): void {
        global $DB;

        parent::setUp();
        $this->resetAfterTest(true);

        $dbman = $DB->get_manager();
        $table = new xmldb_table('local_edusupport_supporters');
        foreach (
            [
            new xmldb_index('courseid-userid', XMLDB_INDEX_UNIQUE, ['courseid', 'userid']),
            new xmldb_index('userid', XMLDB_INDEX_NOTUNIQUE, ['userid']),
            ] as $index
        ) {
            if ($dbman->index_exists($table, $index)) {
                $dbman->drop_index($table, $index);
            }
        }
    }

    /**
     * Prove that the cleanup leaves data the unique key can live with.
     *
     * @return void
     */
    private function assert_unique_key_can_be_added(): void {
        global $DB;

        $dbman = $DB->get_manager();
        $table = new xmldb_table('local_edusupport_supporters');
        $index = new xmldb_index('courseid-userid', XMLDB_INDEX_UNIQUE, ['courseid', 'userid']);
        $dbman->add_index($table, $index);
        $this->assertTrue($dbman->index_exists($table, $index));
    }

    /**
     * Write a supporter row straight to the database, bypassing every check.
     *
     * @param int $courseid
     * @param int $userid
     * @param string $supportlevel
     * @param int $holidaymode
     * @return int the id of the new row.
     */
    private function raw_row(int $courseid, int $userid, string $supportlevel = '', int $holidaymode = 0): int {
        global $DB;

        return $DB->insert_record('local_edusupport_supporters', (object) [
            'courseid' => $courseid,
            'userid' => $userid,
            'supportlevel' => $supportlevel,
            'holidaymode' => $holidaymode,
        ]);
    }

    /**
     * Duplicates are merged, and a row naming a speciality is the one that survives.
     */
    public function test_duplicates_are_merged_and_the_labelled_row_wins(): void {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $this->raw_row(lib::SYSTEM_COURSE_ID, $user->id);
        $labelled = $this->raw_row(lib::SYSTEM_COURSE_ID, $user->id, 'technical');
        $this->raw_row(lib::SYSTEM_COURSE_ID, $user->id);

        $this->assertSame(2, lib::deduplicate_supporters());

        $rows = $DB->get_records('local_edusupport_supporters', ['userid' => $user->id]);
        $this->assertCount(1, $rows);
        $survivor = reset($rows);
        $this->assertEquals($labelled, $survivor->id);
        $this->assertSame('technical', $survivor->supportlevel);

        $this->assert_unique_key_can_be_added();
    }

    /**
     * An ongoing holiday must not disappear just because its row loses the merge.
     */
    public function test_the_longest_holidaymode_survives(): void {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $until = time() + WEEKSECS;
        $this->raw_row(lib::SYSTEM_COURSE_ID, $user->id, 'technical', 0);
        $this->raw_row(lib::SYSTEM_COURSE_ID, $user->id, '', $until);

        lib::deduplicate_supporters();

        $rows = $DB->get_records('local_edusupport_supporters', ['userid' => $user->id]);
        $this->assertCount(1, $rows);
        $survivor = reset($rows);
        $this->assertSame('technical', $survivor->supportlevel);
        $this->assertEquals($until, $survivor->holidaymode);
    }

    /**
     * Rows pointing at users or courses that are gone are removed.
     */
    public function test_orphaned_rows_are_removed(): void {
        global $DB;

        $keep = $this->getDataGenerator()->create_user();
        $deleted = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();

        $this->raw_row(lib::SYSTEM_COURSE_ID, $keep->id);
        $this->raw_row($course->id, $keep->id);
        $this->raw_row(lib::SYSTEM_COURSE_ID, $deleted->id);
        $this->raw_row(lib::SYSTEM_COURSE_ID, -1);
        $this->raw_row(-1, $keep->id);

        delete_user($deleted);

        lib::deduplicate_supporters();

        $remaining = $DB->get_records('local_edusupport_supporters');
        $this->assertCount(2, $remaining);
        foreach ($remaining as $row) {
            $this->assertEquals($keep->id, $row->userid);
        }
    }

    /**
     * A courseid of zero was never a course, it always meant the platform wide team.
     */
    public function test_courseid_zero_becomes_the_platform_team(): void {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $this->raw_row(0, $user->id, 'technical');

        lib::deduplicate_supporters();

        $rows = $DB->get_records('local_edusupport_supporters', ['userid' => $user->id]);
        $this->assertCount(1, $rows);
        $this->assertEquals(lib::SYSTEM_COURSE_ID, reset($rows)->courseid);
    }

    /**
     * A zero row and a platform row for the same user collapse into one.
     */
    public function test_a_zero_row_merges_with_the_platform_row(): void {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $this->raw_row(0, $user->id, 'technical');
        $this->raw_row(lib::SYSTEM_COURSE_ID, $user->id);

        $this->assertSame(1, lib::deduplicate_supporters());

        $rows = $DB->get_records('local_edusupport_supporters', ['userid' => $user->id]);
        $this->assertCount(1, $rows);
        $this->assertSame('technical', reset($rows)->supportlevel);

        $this->assert_unique_key_can_be_added();
    }

    /**
     * Running the cleanup on healthy data changes nothing.
     */
    public function test_clean_data_is_left_alone(): void {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $this->raw_row(lib::SYSTEM_COURSE_ID, $user->id);
        $this->raw_row($course->id, $user->id);

        $this->assertSame(0, lib::deduplicate_supporters());
        $this->assertSame(2, $DB->count_records('local_edusupport_supporters'));
    }
}
