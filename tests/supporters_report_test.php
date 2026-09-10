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
 * Tests for the platform wide overview of support users.
 *
 * @package    local_edusupport
 * @category   test
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_edusupport;

use advanced_testcase;
use context_system;
use core_reportbuilder\system_report_factory;
use core_reportbuilder\table\system_report_table;
use local_edusupport\reportbuilder\local\systemreports\supporters;

/**
 * Tests for the platform wide overview of support users.
 *
 * @package    local_edusupport
 * @category   test
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_edusupport\reportbuilder\local\systemreports\supporters
 * @covers     \local_edusupport\reportbuilder\local\entities\supporter
 */
final class supporters_report_test extends advanced_testcase {
    /**
     * Set up a course, a platform supporter and a course supporter.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        $this->setAdminUser();
    }

    /**
     * Read the report back as rendered rows.
     *
     * There is no shortcut for this on the report itself, so it goes through the table the
     * report is built on, the same way the web service exporter does.
     *
     * @return array of arrays, each holding the rendered cells of one row.
     */
    private function report_rows(): array {
        $report = system_report_factory::create(supporters::class, context_system::instance());

        $table = system_report_table::create($report->get_report_persistent()->get('id'), $report->get_parameters());
        $table->guess_base_url();
        $table->setup();
        $table->query_db(100, false);

        $columns = $report->get_active_columns_by_alias();
        $rows = [];
        foreach ($table->rawdata as $record) {
            $rows[] = array_values(array_intersect_key($table->format_row($record), $columns));
        }
        $table->close_recordset();

        return $rows;
    }

    /**
     * Both levels show up, each described as what it is.
     */
    public function test_both_levels_are_listed(): void {
        $generator = $this->getDataGenerator()->get_plugin_generator('local_edusupport');
        $course = $this->getDataGenerator()->create_course(['fullname' => 'Willow School']);

        $platform = $this->getDataGenerator()->create_user(['lastname' => 'Platform']);
        $local = $this->getDataGenerator()->create_user(['lastname' => 'Local']);
        $generator->create_supporter(['userid' => $platform->id]);
        $generator->create_supporter(['userid' => $local->id, 'courseid' => $course->id]);

        $rendered = implode(' ', array_map(fn($row) => implode(' ', $row), $this->report_rows()));

        $this->assertStringContainsString(get_string('level:first', 'local_edusupport'), $rendered);
        $this->assertStringContainsString(get_string('level:second', 'local_edusupport'), $rendered);
        $this->assertStringContainsString('Willow School', $rendered);
        $this->assertStringContainsString(get_string('scope:platform', 'local_edusupport'), $rendered);
    }

    /**
     * The platform team is not shown as if it supported the site course.
     *
     * The id standing for the platform team is also the id of the site course, so joining the
     * course table naively would name and link the front page on every second level row.
     */
    public function test_the_platform_team_is_not_shown_as_the_site_course(): void {
        global $SITE;

        $platform = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->get_plugin_generator('local_edusupport')
            ->create_supporter(['userid' => $platform->id]);

        $rendered = implode(' ', array_map(fn($row) => implode(' ', $row), $this->report_rows()));

        $this->assertStringContainsString(get_string('scope:platform', 'local_edusupport'), $rendered);
        $this->assertStringNotContainsString('/course/view.php?id=' . $SITE->id, $rendered);
    }

    /**
     * A deleted user drops out of the overview.
     */
    public function test_a_deleted_user_is_not_listed(): void {
        $gone = $this->getDataGenerator()->create_user(['lastname' => 'Vanished']);
        $this->getDataGenerator()->get_plugin_generator('local_edusupport')
            ->create_supporter(['userid' => $gone->id]);

        $this->assertCount(1, $this->report_rows());

        delete_user($gone);

        $this->assertCount(0, $this->report_rows());
    }
}
