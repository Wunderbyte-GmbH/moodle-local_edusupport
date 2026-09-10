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
 * Form to assign the first level support of a course.
 *
 * @package    local_edusupport
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_edusupport\form;

use context;
use context_course;
use core_form\dynamic_form;
use local_edusupport\lib;
use local_edusupport\output\course_supporters;
use moodle_url;

/**
 * Form to assign the first level support of a course.
 *
 * Runs over core_form_dynamic_form, so assigning somebody neither reloads the page nor
 * sends the whole course page over the wire again.
 *
 * @package    local_edusupport
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_supporters_form extends dynamic_form {
    /**
     * Get the course this form is for.
     *
     * @return int
     */
    private function get_courseid(): int {
        return (int) $this->optional_param('courseid', 0, PARAM_INT);
    }

    /**
     * Define the form.
     *
     * @return void
     */
    protected function definition() {
        $mform = $this->_form;
        $courseid = $this->get_courseid();

        $mform->addElement('hidden', 'courseid', $courseid);
        $mform->setType('courseid', PARAM_INT);

        // The candidates are the teaching staff of one course, so the list is short enough to
        // ship with the form. That saves a search web service and keeps the capability filter
        // where it cannot be argued with, on the server.
        $options = [];
        foreach (lib::get_assignable_users($courseid) as $user) {
            $options[$user->id] = fullname($user);
        }

        $mform->addElement(
            'autocomplete',
            'supporters',
            get_string('coursesupporters:assign', 'local_edusupport'),
            $options,
            ['multiple' => true, 'noselectionstring' => get_string('coursesupporters:none', 'local_edusupport')]
        );
        $mform->addHelpButton('supporters', 'coursesupporters:assign', 'local_edusupport');
    }

    /**
     * The context the submission is checked against.
     *
     * @return context
     */
    protected function get_context_for_dynamic_submission(): context {
        return context_course::instance($this->get_courseid());
    }

    /**
     * Refuse anybody who may not assign support for this course.
     *
     * @return void
     */
    protected function check_access_for_dynamic_submission(): void {
        require_capability('local/edusupport:assignsupporters', $this->get_context_for_dynamic_submission());
    }

    /**
     * Preset the form with the people who support the course today.
     *
     * @return void
     */
    public function set_data_for_dynamic_submission(): void {
        $courseid = $this->get_courseid();
        $this->set_data([
            'courseid' => $courseid,
            'supporters' => array_values(array_map(
                fn($row) => $row->userid,
                lib::get_first_level($courseid)
            )),
        ]);
    }

    /**
     * Store the assignment and hand back the rendered list.
     *
     * @return array with the list markup for the caller to put in place.
     */
    public function process_dynamic_submission(): array {
        global $PAGE;

        $data = $this->get_data();
        $courseid = (int) $data->courseid;

        lib::assign_first_level($courseid, $data->supporters ?? []);

        $output = $PAGE->get_renderer('core');

        return [
            'listhtml' => $output->render_from_template(
                'local_edusupport/course_supporters',
                (new course_supporters($courseid))->export_for_template($output)
            ),
        ];
    }

    /**
     * The page this form belongs to.
     *
     * @return moodle_url
     */
    protected function get_page_url_for_dynamic_submission(): moodle_url {
        return new moodle_url('/local/edusupport/coursesupporters.php', ['courseid' => $this->get_courseid()]);
    }
}
