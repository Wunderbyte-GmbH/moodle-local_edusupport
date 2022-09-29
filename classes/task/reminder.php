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
 * @package    local_edusupport
 * @copyright  2018 Digital Education Society (http://www.dibig.at)
 * @author     Robert Schrenk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_edusupport\task;

defined('MOODLE_INTERNAL') || die;

class reminder extends \core\task\adhoc_task {

    public function get_name() {
        // Shown in admin screens.
        return get_string('cron:reminder:title', 'local_edusupport');
    }

    public function execute($debug=false) {

        $taskdata = $this->get_custom_data();
        if (!get_config('local_edusupport', 'sendreminders')) {
            return;
        }
        global $DB;
        $timebeforeminder = time() - (get_config('local_edusupport', 'timebeforeminder'));
        $status1 = 4;
        $status2 = 1;

        $sql = "SELECT discussionid,currentsupporter
                    FROM {local_edusupport_issues}
                    WHERE priority > 0
                        AND currentsupporter > 0
                        AND (status = ? OR status = ?)
                        AND timemodified < ?
                        AND id = ?
                    ORDER BY currentsupporter ASC";
        $issues = $DB->get_records_sql($sql, array($status1, $status2, $timebeforeminder, $taskdata->issueid));
        if (!empty($issues)) {
            $currentsupporter = new \stdClass();
            $reminders = array();
            foreach ($issues as $issue) {
                if (!empty($currentsupporter->id) && $issue->currentsupporter != $currentsupporter->id) {
                    $this->send($currentsupporter, $reminders, $debug);
                    $reminders = array();
                    $currentsupporter = $issue->currentsupporter;
                }
                $currentsupporter = $DB->get_record('user', array('id' => $issue->currentsupporter));
                $discussion = $DB->get_record('forum_discussions', array('id' => $issue->discussionid));
                if (!empty($discussion->firstpost)) {
                    $post = $DB->get_record('forum_posts', array('id' => $discussion->firstpost));
                    $user = $DB->get_record('user', array('id' => $discussion->userid));
                    $discussion->message = $post->message;
                    $discussion->userfullname = \fullname($user);
                    $discussion->useremail = $user->email;
                    $reminders[] = $discussion;
                }
            }
            $this->send($currentsupporter, $reminders, $debug);
            if ($taskdata->sendagain) {
                $task = new reminder();
                $taskdata->sendagain = false;
                $task->set_custom_data($taskdata);
                $timebeforeminder = time() + 2 * (get_config('local_edusupport', 'timebeforeminder'));
                $task->set_next_run_time($timebeforeminder);
                 // Now queue the task or reschedule it if it already exists (with matching data).
                \core\task\manager::queue_adhoc_task($task);
            }
            return true;
        }
        return true;

    }
    private function send($supporter, $reminders = array(), $debug=false) {
        global $CFG, $OUTPUT;
        if (!empty($supporter->id) && $supporter->id > 0 && count($reminders) > 0) {
            $subject = $this->get_name();
            $mailhtml = $OUTPUT->render_from_template('local_edusupport/reminder_discussions', array('discussions' => $reminders, 'wwwroot' => $CFG->wwwroot));
            $mailtext = html_to_text($mailhtml);

            if ($debug) {
                echo "# Mail to " . $supporter->email;
                print_r($mailhtml);
            }

            $fromuser = \core_user::get_support_user();
            \email_to_user($supporter, $fromuser, $subject, $mailtext, $mailhtml, "", true);
        }
    }
}
