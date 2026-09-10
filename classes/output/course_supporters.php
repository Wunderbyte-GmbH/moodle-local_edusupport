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
 * The people who support a single course.
 *
 * @package    local_edusupport
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_edusupport\output;

use local_edusupport\lib;
use renderable;
use renderer_base;
use templatable;

/**
 * The people who support a single course.
 *
 * The page and the reply of the assignment form render the same list, so it lives here
 * rather than in either of them.
 *
 * @package    local_edusupport
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_supporters implements renderable, templatable {
    /** @var int the course the supporters belong to. */
    private $courseid;

    /**
     * Constructor.
     *
     * @param int $courseid
     */
    public function __construct(int $courseid) {
        $this->courseid = $courseid;
    }

    /**
     * Export the supporters of the course for the template.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        global $DB;

        $rows = lib::get_first_level($this->courseid);
        $supporters = [];
        if (!empty($rows)) {
            $userids = array_column($rows, 'userid');
            [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
            $users = $DB->get_records_select('user', "id $insql", $inparams, 'lastname ASC, firstname ASC');
            foreach ($users as $user) {
                $supporters[] = [
                    'userid' => $user->id,
                    'fullname' => fullname($user),
                    'email' => $user->email,
                    'profileurl' => (new \moodle_url('/user/view.php', [
                        'id' => $user->id,
                        'course' => $this->courseid,
                    ]))->out(false),
                ];
            }
        }

        return [
            'courseid' => $this->courseid,
            'supporters' => $supporters,
            'hassupporters' => !empty($supporters),
            'issupportcourse' => $DB->record_exists('local_edusupport', ['courseid' => $this->courseid]),
        ];
    }
}
