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
 * Report builder entity for the supporter registry.
 *
 * @package    local_edusupport
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_edusupport\reportbuilder\local\entities;

use core_reportbuilder\local\entities\base;
use core_reportbuilder\local\filters\boolean_select;
use core_reportbuilder\local\filters\select;
use core_reportbuilder\local\filters\text;
use core_reportbuilder\local\helpers\format;
use core_reportbuilder\local\report\column;
use core_reportbuilder\local\report\filter;
use lang_string;
use local_edusupport\lib;
use stdClass;

/**
 * Report builder entity for the supporter registry.
 *
 * One row is one person supporting either a single course or the whole platform.
 *
 * @package    local_edusupport
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class supporter extends base {
    /**
     * Database tables that this entity uses.
     *
     * @return string[]
     */
    protected function get_default_tables(): array {
        return [
            'local_edusupport_supporters',
        ];
    }

    /**
     * The default title for this entity.
     *
     * @return lang_string
     */
    protected function get_default_entity_title(): lang_string {
        return new lang_string('supporters', 'local_edusupport');
    }

    /**
     * Initialise the entity.
     *
     * @return base
     */
    public function initialise(): base {
        foreach ($this->get_all_columns() as $column) {
            $this->add_column($column);
        }
        foreach ($this->get_all_filters() as $filter) {
            $this->add_filter($filter);
        }

        return $this;
    }

    /**
     * The two levels a row can belong to.
     *
     * @return array of value => label.
     */
    public static function get_level_options(): array {
        return [
            0 => get_string('level:first', 'local_edusupport'),
            1 => get_string('level:second', 'local_edusupport'),
        ];
    }

    /**
     * All available columns.
     *
     * @return column[]
     */
    protected function get_all_columns(): array {
        $alias = $this->get_table_alias('local_edusupport_supporters');
        $columns = [];

        // Which level the row belongs to, derived from the course it points at.
        $columns[] = (new column(
            'level',
            new lang_string('level', 'local_edusupport'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$alias}.courseid", 'levelcourseid')
            ->set_is_sortable(true)
            ->add_callback(static function($value, stdClass $row): string {
                $options = self::get_level_options();
                return $row->levelcourseid == lib::SYSTEM_COURSE_ID ? $options[1] : $options[0];
            });

        // Where the person supports. The course cannot simply be linked here: the id standing
        // for the platform team is also the id of the site course, so every second level row
        // would point at the front page.
        $columns[] = (new column(
            'scope',
            new lang_string('scope', 'local_edusupport'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$alias}.courseid", 'scopecourseid')
            ->set_is_sortable(true)
            ->add_callback(static function($value, stdClass $row): string {
                if ($row->scopecourseid == lib::SYSTEM_COURSE_ID) {
                    return get_string('scope:platform', 'local_edusupport');
                }
                $course = get_course($row->scopecourseid);
                return \html_writer::link(
                    new \moodle_url('/course/view.php', ['id' => $course->id]),
                    format_string($course->fullname)
                );
            });

        // The free text label people fill in as they please.
        $columns[] = (new column(
            'supportlevel',
            new lang_string('supportlevel', 'local_edusupport'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$alias}.supportlevel")
            ->set_is_sortable(true);

        $columns[] = (new column(
            'autoassign',
            new lang_string('autoassign', 'local_edusupport'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_BOOLEAN)
            ->add_field("{$alias}.autoassign")
            ->set_is_sortable(true)
            ->add_callback([format::class, 'boolean_as_text']);

        $columns[] = (new column(
            'holidaymode',
            new lang_string('holidaymode', 'local_edusupport'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TIMESTAMP)
            ->add_field("{$alias}.holidaymode")
            ->set_is_sortable(true)
            ->add_callback(static function($value): string {
                return empty($value) ? '-' : userdate($value);
            });

        return $columns;
    }

    /**
     * All available filters.
     *
     * @return filter[]
     */
    protected function get_all_filters(): array {
        $alias = $this->get_table_alias('local_edusupport_supporters');
        $filters = [];

        $filters[] = (new filter(
            select::class,
            'level',
            new lang_string('level', 'local_edusupport'),
            $this->get_entity_name(),
            "CASE WHEN {$alias}.courseid = " . lib::SYSTEM_COURSE_ID . " THEN 1 ELSE 0 END"
        ))
            ->add_joins($this->get_joins())
            ->set_options_callback(static function(): array {
                return self::get_level_options();
            });

        $filters[] = (new filter(
            text::class,
            'supportlevel',
            new lang_string('supportlevel', 'local_edusupport'),
            $this->get_entity_name(),
            "{$alias}.supportlevel"
        ))
            ->add_joins($this->get_joins());

        $filters[] = (new filter(
            boolean_select::class,
            'autoassign',
            new lang_string('autoassign', 'local_edusupport'),
            $this->get_entity_name(),
            "{$alias}.autoassign"
        ))
            ->add_joins($this->get_joins());

        return $filters;
    }
}
