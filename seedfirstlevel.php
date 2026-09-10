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
 * Fill the first level of every support course from the eligibility rule.
 *
 * @package    local_edusupport
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->libdir . '/adminlib.php');

$apply = optional_param('apply', 0, PARAM_BOOL);

admin_externalpage_setup('local_edusupport_seedfirstlevel');

$url = new moodle_url('/local/edusupport/seedfirstlevel.php');

if ($apply) {
    require_sesskey();
    $applied = \local_edusupport\lib::seed_first_level_from_capabilities(false);
    $added = array_sum(array_column($applied, 'toadd'));
    redirect(
        $url,
        get_string('seedfirstlevel:done', 'local_edusupport', $added),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

$report = \local_edusupport\lib::seed_first_level_from_capabilities(true);
$pending = array_sum(array_column($report, 'toadd'));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('seedfirstlevel', 'local_edusupport'));
echo html_writer::tag('p', get_string('seedfirstlevel:description', 'local_edusupport'));

if (empty($report)) {
    echo $OUTPUT->notification(get_string('seedfirstlevel:nocourses', 'local_edusupport'), 'info');
} else {
    $table = new html_table();
    $table->head = [
        get_string('course'),
        get_string('seedfirstlevel:assigned', 'local_edusupport'),
        get_string('seedfirstlevel:eligible', 'local_edusupport'),
        get_string('seedfirstlevel:toadd', 'local_edusupport'),
    ];
    $table->attributes['class'] = 'generaltable';
    foreach ($report as $row) {
        $names = empty($row['names']) ? '-' : implode(', ', $row['names']);
        $table->data[] = [
            html_writer::link(new moodle_url('/course/view.php', ['id' => $row['courseid']]), $row['coursename']),
            $row['assigned'],
            $row['eligible'],
            $row['toadd'] . ($row['toadd'] ? ' (' . $names . ')' : ''),
        ];
    }
    echo html_writer::table($table);

    if ($pending) {
        echo $OUTPUT->single_button(
            new moodle_url($url, ['apply' => 1, 'sesskey' => sesskey()]),
            get_string('seedfirstlevel:apply', 'local_edusupport', $pending),
            'post'
        );
    } else {
        echo $OUTPUT->notification(get_string('seedfirstlevel:nothingtodo', 'local_edusupport'), 'info');
    }
}

echo $OUTPUT->footer();
