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
 * Every support user of the platform and where they support.
 *
 * @package    local_edusupport
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_edusupport\reportbuilder\local\systemreports;

use context_system;
use core_reportbuilder\local\entities\course;
use core_reportbuilder\local\entities\user;
use core_reportbuilder\system_report;
use local_edusupport\reportbuilder\local\entities\supporter;

/**
 * Every support user of the platform and where they support.
 *
 * @package    local_edusupport
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class supporters extends system_report {
    /**
     * Put the report together.
     *
     * @return void
     */
    protected function initialise(): void {
        $entity = new supporter();
        $alias = $entity->get_table_alias('local_edusupport_supporters');

        $this->set_main_table('local_edusupport_supporters', $alias);
        $this->add_entity($entity);

        $userentity = new user();
        $useralias = $userentity->get_table_alias('user');
        $this->add_entity($userentity->add_join(
            "JOIN {user} {$useralias} ON {$useralias}.id = {$alias}.userid"
        ));

        // The course is joined for its filters and extra columns. The scope column of the
        // supporter entity renders the course itself, because the id standing for the
        // platform team is also the id of the site course.
        $courseentity = new course();
        $coursealias = $courseentity->get_table_alias('course');
        $this->add_entity($courseentity->add_join(
            "LEFT JOIN {course} {$coursealias} ON {$coursealias}.id = {$alias}.courseid"
        ));

        $this->add_base_condition_simple("{$useralias}.deleted", 0);

        $this->add_columns_from_entities([
            'supporter:level',
            'user:fullnamewithlink',
            'user:email',
            'supporter:scope',
            'supporter:supportlevel',
            'supporter:autoassign',
            'supporter:holidaymode',
        ]);

        $this->add_filters_from_entities([
            'supporter:level',
            'user:fullname',
            'course:fullname',
            'supporter:supportlevel',
            'supporter:autoassign',
        ]);

        $this->set_initial_sort_column('supporter:level', SORT_ASC);
        $this->set_downloadable(true, get_string('supporters', 'local_edusupport'));
    }

    /**
     * Who may look at this report.
     *
     * @return bool
     */
    protected function can_view(): bool {
        return has_capability('moodle/site:config', context_system::instance());
    }
}
