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
 * Assign the first level support of a course.
 *
 * @package    local_edusupport
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

$courseid = required_param('courseid', PARAM_INT);

$course = get_course($courseid);
$context = context_course::instance($courseid);

require_login($course);
require_capability('local/edusupport:assignsupporters', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/edusupport/coursesupporters.php', ['courseid' => $courseid]));
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('coursesupporters', 'local_edusupport'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_secondary_active_tab('local_edusupport_coursesupporters');
$PAGE->requires->js_call_amd('local_edusupport/coursesupporters', 'init', [$courseid]);

$output = $PAGE->get_renderer('core');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('coursesupporters', 'local_edusupport'));
echo html_writer::tag('p', get_string('coursesupporters:description', 'local_edusupport'));

echo $OUTPUT->render_from_template(
    'local_edusupport/course_supporters',
    (new \local_edusupport\output\course_supporters($courseid))->export_for_template($output)
);

echo $OUTPUT->render(new single_button(
    new moodle_url('#'),
    get_string('coursesupporters:assign', 'local_edusupport'),
    'get',
    single_button::BUTTON_PRIMARY,
    ['data-action' => 'assign-coursesupporters']
));

echo $OUTPUT->footer();
