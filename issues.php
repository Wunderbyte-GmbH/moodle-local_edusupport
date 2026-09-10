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
 * Overview of all support issues for the support team.
 *
 * @package    local_edusupport
 * @copyright  2020 Center for Learningmanagement (www.lernmanagement.at)
 * @author     Robert Schrenk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

$context = \context_system::instance();
$PAGE->set_context($context);
require_login();
$PAGE->set_url(new moodle_url('/local/edusupport/issues.php'));
$PAGE->requires->css('/local/edusupport/style/edusupport.css');
$title = get_string('issues', 'local_edusupport');
$PAGE->set_title($title);
$PAGE->set_heading($title);

$issupportteam = \local_edusupport\lib::is_second_level();

// Handle all actions before any output is sent, so we can redirect afterwards (post/redirect/get).
// Without the redirect a reload or the back button would trigger the same action again, which for
// close and reopen means another status post in the discussion and another round of notifications.
if ($issupportteam) {
    $assign = optional_param('assign', 0, PARAM_INT); // Discussion id we want to assign to.
    $unassign = optional_param('unassign', 0, PARAM_INT); // Discussion id we want to unassign from.
    $take = optional_param('take', 0, PARAM_INT); // Discussion id we want to take over ourselves.
    $reopen = optional_param('reopen', 0, PARAM_INT); // Discussion id we want to reopen.
    $close = optional_param('close', 0, PARAM_INT); // Discussion id we want to close.
    $prio = optional_param('prio', 0, PARAM_INT); // Discussion id we want to set the priority for.
    $lvl = optional_param('lvl', 0, PARAM_INT); // The priority level to set.

    if (
        !empty($assign) || !empty($unassign) || !empty($take)
        || !empty($reopen) || !empty($close) || !empty($prio)
    ) {
        require_sesskey();

        // Only act on discussions that actually are registered as an issue.
        $isissue = function ($discussionid) use ($DB) {
            return !empty($discussionid)
                && $DB->record_exists('local_edusupport_issues', ['discussionid' => $discussionid]);
        };

        if ($isissue($assign)) {
            \local_edusupport\lib::subscription_add($assign);
        }
        if ($isissue($unassign)) {
            \local_edusupport\lib::subscription_remove($unassign);
        }
        if ($isissue($take)) {
            \local_edusupport\lib::set_current_supporter($take, $USER->id);
            \local_edusupport\lib::subscription_add($take);
        }
        if ($isissue($reopen)) {
            \local_edusupport\lib::reopen_issue($reopen);
        }
        if ($isissue($close)) {
            \local_edusupport\lib::close_issue($close);
        }
        if (!empty($lvl) && $isissue($prio)) {
            \local_edusupport\lib::set_prioritylvl($prio, $lvl);
        }

        redirect($PAGE->url);
    }

    // Holiday mode is handled here as well, so its form can redirect just like every other action.
    if (get_config('local_edusupport', 'holidaymodeenabled')) {
        // Holiday mode decides whether escalation reaches somebody, so it belongs to the
        // platform team row. A course assignment carries no holiday of its own, and with the
        // unique key that row is unambiguous.
        $supporterconditions = [
            'userid' => $USER->id,
            'courseid' => \local_edusupport\lib::SYSTEM_COURSE_ID,
        ];
        $supporter = $DB->get_record('local_edusupport_supporters', $supporterconditions);
        if (!empty($supporter->id)) {
            $holidaymodeform = new \local_edusupport\holidaymode_form();
            $holidaymodeend = optional_param('holidaymodeend', 0, PARAM_INT);
            if (!empty($holidaymodeend)) {
                require_sesskey();
                $DB->set_field('local_edusupport_supporters', 'holidaymode', 0, $supporterconditions);
                redirect($PAGE->url);
            } else if ($holidaymodedata = $holidaymodeform->get_data()) {
                // The date_time_selector hands us a timestamp, and get_data() has checked the sesskey.
                $DB->set_field(
                    'local_edusupport_supporters',
                    'holidaymode',
                    (int) $holidaymodedata->holidaymode,
                    $supporterconditions
                );
                redirect($PAGE->url);
            } else if (!empty($supporter->holidaymode) && $supporter->holidaymode < time()) {
                // Expired holiday mode - invalidate it.
                $supporter->holidaymode = 0;
                $DB->set_field('local_edusupport_supporters', 'holidaymode', 0, $supporterconditions);
            }
        }
    }
}

echo $OUTPUT->header();

if (!$issupportteam) {
    echo $OUTPUT->render_from_template('local_edusupport/alert', [
        'content' => get_string('missing_permission', 'local_edusupport'),
        'type' => 'danger',
        'url' => new moodle_url('/my'),
    ]);
} else {
    $issues = $DB->get_records('local_edusupport_issues', [], 'priority,id,discussionid,status');

    $params = [
        'current' => [], // Issues the user is responsible for.
        'assigned' => [], // Issues the user receives notifications for.
        'other' => [], // All other issues.
        'wwwroot' => $CFG->wwwroot,
        'count' => [],
    ];
    $hasprio = get_config('local_edusupport', 'prioritylvl');
    $params['count']['current'] = 0;
    $params['count']['closed'] = 0;
    $params['count']['assigned'] = 0;
    $params['count']['other'] = 0;
    $params['userlinks'] = get_config('local_edusupport', 'userlinks');
    $params['hasprio'] = $hasprio;
    $params['sesskey'] = sesskey();
    foreach (array_reverse($issues) as $issue) {
        // Collect certain data about this issue.
        $discussion = $DB->get_record('forum_discussions', ['id' => $issue->discussionid]);
        $issue->name = $discussion->name;
        $issue->userid = $discussion->userid;
        $postinguser = $DB->get_record('user', ['id' => $discussion->userid]);
        $issue->userfullname = \fullname($postinguser);
        $sql = "SELECT id,modified,userid FROM {forum_posts} WHERE discussion=? ORDER BY modified DESC LIMIT 1 OFFSET 0";
        $lastpost = $DB->get_record_sql($sql, [$issue->discussionid]);
        $issue->lastmodified = $issue->timemodified;
        $issue->lastpostuserid = $lastpost->userid;
        $lastuser = $DB->get_record('user', ['id' => $issue->lastpostuserid]);
        $issue->lastpostuserfullname = fullname($lastuser);
        $assigned = $DB->get_record(
            'local_edusupport_subscr',
            ['discussionid' => $issue->discussionid, 'userid' => $USER->id]
        );
        $issue->prio = "";
        $issue->priolow = "";
        $issue->priomid = "";
        $issue->priohigh = "";
        if (isset($issue->accountmanager)) {
            $accountmanager = $DB->get_record('user', ['id' => $issue->accountmanager]);
            $issue->accountmanagerfn = \fullname($accountmanager);
        }

        // Now get the current supporter.
        if (!empty($issue->currentsupporter)) {
            $supportuser = $DB->get_record('user', ['id' => $issue->currentsupporter]);
            $issue->currentsupportername = \fullname($supportuser);
            $issue->currentsupporterid = $issue->currentsupporter;
        } else {
            $issue->currentsupportername = get_string('label:2ndlevel', 'local_edusupport');
        }

        $issue->state = \local_edusupport\lib::status_to_template($issue->status);

        if ($hasprio) {
            if ($issue->priority <= 1) {
                $issue->priolow = "active";
                $issue->priomid = "";
                $issue->priohigh = "";
            }
            if ($issue->priority > 1) {
                $issue->priolow = "";
                $issue->priomid = "active";
                $issue->priohigh = "";
            }
            if ($issue->priority > 2) {
                $issue->priolow = "";
                $issue->priomid = "";
                $issue->priohigh = "active";
            }
        }
        // Now separate between current, assigned and other issues.
        if ($issue->currentsupporter == $USER->id && $issue->priority > 0) {
            $params['current'][] = $issue;
            $params['count']['current'] = $params['count']['current'] + 1;
        } else if (!empty($assigned->id)) {
            $params['assigned'][] = $issue;
            $params['count']['assigned'] = $params['count']['assigned'] + 1;
        } else if ($issue->status != ISSUE_STATUS_CLOSED) {
            $params['other'][] = $issue;
            $params['count']['other'] = $params['count']['other'] + 1;
        } else if ($issue->status == ISSUE_STATUS_CLOSED) {
            $params['closed'][] = $issue;
            $params['count']['closed'] = $params['count']['closed'] + 1;
        }
    }

    // Holiday mode was already handled before any output was sent, this only renders it.
    if (!empty($supporter->id) && !empty($holidaymodeform)) {
        $supporter->holidayform = $holidaymodeform->render();
        $supporter->wwwroot = $CFG->wwwroot;
        $supporter->uniqid = uniqid();
        $supporter->sesskey = sesskey();
        echo $OUTPUT->render_from_template('local_edusupport/holidaymode', $supporter);
    }
    $params['accountmanagerenabled'] = !empty(get_config('local_edusupport', 'accountmanagers'));

    echo $OUTPUT->render_from_template('local_edusupport/issues', $params);
}

echo $OUTPUT->footer();
