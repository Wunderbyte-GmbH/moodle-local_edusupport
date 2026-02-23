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
 * Hook for before_standard_head_html_generation
 *
 * @package     local_edusupport
 * @author      Jacob Viertel
 * @copyright   2026 Wunderbyte GmbH
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_edusupport;

/**
 * Class before_standard_head_html_generation
 *
 * @author      Jacob Viertel
 * @copyright   2026 Wunderbyte GmbH
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {
    /**
     * Execute function for before_standard_head_html_generation.
     * @return void
     *
     */
    public static function before_standard_head_html_generation(
        \core\hook\output\before_standard_head_html_generation $hook,
    ): void {
        global $DB, $PAGE, $SITE;

        if ($PAGE->pagetype === 'mod-forum-discuss') {
            $d = optional_param('d', 0, PARAM_INT);
            $discussion = $DB->get_record('forum_discussions', ['id' => $d]);
            $coursecontext = \context_course::instance($discussion->course);

            if (
                has_capability('local/edusupport:canforward2ndlevel', $coursecontext)
                && \local_edusupport\lib::is_supportforum($discussion->forum)
            ) {
                $sql = "SELECT id FROM {local_edusupport_subscr} WHERE discussionid=? LIMIT 1 OFFSET 0";
                $chk = $DB->get_record_sql($sql, [$discussion->id]);

                $PAGE->requires->js_call_amd(
                    'local_edusupport/main',
                    'injectForwardButton',
                    [$d, !empty($chk->id), $SITE->fullname]
                );
            }
            if (\local_edusupport\lib::is_supportforum($discussion->forum)) {
                $PAGE->requires->js_call_amd('local_edusupport/main', 'injectTest');
            }
        }

        if ($PAGE->pagetype === 'course-management') {
            $categoryid = optional_param('categoryid', 0, PARAM_INT);
            $action = optional_param('action', '', PARAM_ALPHANUM);
            if ($action == 'deletecategory') {
                $coursecatcontext = \context_coursecat::instance($categoryid);
                $sql = "SELECT * FROM {context}
                        WHERE contextlevel=?
                        AND (path LIKE ? OR path LIKE ?)";
                $subcategories = $DB->get_records_sql($sql, [CONTEXT_COURSECAT,
                    $coursecatcontext->path, $coursecatcontext->path . '/%']);

                foreach ($subcategories as $subcategory) {
                    $chkforforum = $DB->get_record('local_edusupport', ['categoryid' => $subcategory->instanceid]);
                    if (!empty($chkforforum->id)) {
                        redirect(new \moodle_url('/local/edusupport/error.php', [
                            'error' => 'coursecategorydeletion',
                            'categoryid' => $categoryid,
                        ]));
                    }
                }
            }

            $coursecat = \core_course_category::get($categoryid, MUST_EXIST, true);
            if (empty($coursecat->__get('visible'))) {
                $coursecat->update(['visible' => 1]);
            }

            $supportforums = $DB->get_records('local_edusupport', ['categoryid' => $categoryid]);
            foreach ($supportforums as $supportforum) {
                $course = $DB->get_record('course', ['id' => $supportforum->id]);
                if (!empty($course->id) && $course->category != $categoryid) {
                    $DB->set_field('local_edusupport', 'categoryid', $categoryid, ['courseid' => $course->id]);
                }
            }
        }
    }
}
