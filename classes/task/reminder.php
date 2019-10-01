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
 * @package    block_edusupport
 * @copyright  2018 Digital Education Society (http://www.dibig.at)
 * @author     Robert Schrenk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_edusupport\task;

defined('MOODLE_INTERNAL') || die;

class reminder extends \core\task\scheduled_task {
    public function get_name() {
        // Shown in admin screens.
        return get_string('cron:reminder:title', 'block_edusupport');
    }

    public function execute() {
        $sendreminders = get_config('block_edusupport', 'sendreminders');
        if (empty($sendreminders) || $sendreminders == 0) return;

        global $DB;
        $sql = "SELECT discussionid,currentsupporter
                    FROM {block_edusupport_issues}
                    WHERE opened=1
                        AND currentsupporter>0
                    ORDER BY currentsupporter ASC";
        $issues = $DB->get_records_sql($sql, array());
        $currentsupporter = new \stdClass();

        foreach ($issues AS $issue) {
            if (!empty($currentsupporter->id) && $issue->currentsupporter != $currentsupporter->id) {
                $this->send($currentsupporter, $reminders);
                $reminders = array();
                $currentsupporter = $issue->currentsupporter;
            }
            $currentsupporter = $DB->get_record('user', array('id' => $issue->currentsupporter));
            $discussion = $DB->get_record('forum_discussions', array('id' => $issue->discussionid));
            if (!empty($discussion->firstpost)) {
                $post = $DB->get_record('forum_posts', array('id' => $discussion->firstpost));
                $reminders[] = $discussion;
            }
        }
        $this->send($currentsupporter, $reminders);
    }
    private function send($supporter, $reminders = array()) {
        global $CFG, $OUTPUT;
        if (!empty($supporter->id) && $supporter->id > 0 && count($reminders) > 0) {
            $subject = $this->get_name();
            $mailhtml =  $OUTPUT->render_from_template('block_edusupport/reminder_discussions', array('discussions' => $reminders, 'wwwroot' => $CFG->wwwroot));
            $mailtext = html_to_text($mailhtml);

            $fromuser = \core_user::get_support_user();
            \email_to_user($supporter, $fromuser, $subject, $mailtext, $mailhtml, "", true);
        }
    }
}