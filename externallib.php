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

use local_edusupport\guest_supportuser;

defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir . "/externallib.php");
require_once($CFG->dirroot . '/local/edusupport/classes/lib.php');


class local_edusupport_external extends external_api {
    public static function close_issue_parameters() {
        return new external_function_parameters([
            'discussionid' => new external_value(PARAM_INT, 'discussionid'),
        ]);
    }
    public static function close_issue($discussionid) {
        global $CFG;
        $params = self::validate_parameters(self::close_issue_parameters(), ['discussionid' => $discussionid]);
        return \local_edusupport\lib::close_issue($params['discussionid']);
    }
    public static function close_issue_returns() {
        return new external_value(PARAM_RAW, 'Returns 1 if successful, or error message.');
    }
    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function create_issue_parameters() {
        return new external_function_parameters([
            'subject' => new external_value(PARAM_TEXT, 'subject of this issue'),
            'description' => new external_value(PARAM_RAW, 'default for whole package otherwise channel name'),
                // We use PARAM_RAW here, as the editor can send HTML.
            'forum_group' => new external_value(PARAM_TEXT, 'Forum-ID and Group-ID to post to in format forumid_groupid.'),
            'postto2ndlevel' => new external_value(PARAM_INT, '1st level supporters can directly call the 2nd level support'),
            'image' => new external_value(PARAM_RAW, 'base64 encoded image as data url or empty string'),
            'screenshotname' => new external_value(PARAM_TEXT, 'the filename to use'),
            'url' => new external_value(PARAM_TEXT, 'URL where the error happened'),
                // We use PARAM_TEXT, as any input by the user is valid.
            'contactphone' => new external_value(PARAM_TEXT, 'Contactphone'),
                // We use PARAM_TEXT, was the user can enter any contact information.
            'guestmail' => new external_value(PARAM_EMAIL, 'Guestmail', VALUE_OPTIONAL, null, NULL_ALLOWED),
            'accountmanager' => new external_value(PARAM_INT, 'Accountmanager', VALUE_OPTIONAL, null, NULL_ALLOWED),
        ]);
    }

    /**
     * Create an issue in the targetforum.
     *
     * @return array $reply of created issue
     */
    public static function create_issue(
        $subject,
        $description,
        $forumgroup,
        $postto2ndlevel,
        $image,
        $screenshotname,
        $url,
        $contactphone,
        $guestmail,
        $accountmanager = null
    ): array {
        global $CFG, $DB, $OUTPUT, $PAGE, $USER, $SITE;

        $protecttime = get_config('local_edusupport', 'spamprotectionthreshold');
        $protectamount = get_config('local_edusupport', 'spamprotectionlimit');

        $cache = \cache::make('local_edusupport', 'spamprotect');
        $timeoffset = time() - $protecttime;
        $log = $cache->get('log');
        if (!empty($log)) {
            for ($a = 0; $a < count($log); $a++) {
                if ($log[$a] < $timeoffset) {
                    $log[$a] = '';
                }
            }
            $log = array_filter($log);
            $log = array_values($log);
            $cache->set('log', $log);
            if ($protectamount <= count($log)) {
                throw new \moodle_exception('spamprotection:exception', 'local_edusupport');
            }
        }
        $log[] = time();
        $cache->set('log', $log);

        $subjectprefixenabled = get_config('local_edusupport', 'predefined_subjects_prefix');
        $guestmodeenabled = false;
        $guestmode = get_config('local_edusupport', 'guestmodeenabled');
        if ($guestmode && isset($guestmail) && (isguestuser() || !isloggedin())) {
            $guestuser = new guest_supportuser();
            $user = $guestuser->get_support_guestuser();
            $guestmodeenabled = true;
        } else {
            $user = $USER;
        }

        $params = self::validate_parameters(
            self::create_issue_parameters(),
            ['subject' => $subject, 'description' => $description, 'forum_group' => $forumgroup,
                        'postto2ndlevel' => $postto2ndlevel, 'image' => $image, 'screenshotname' => $screenshotname, 'url' => $url,
            'contactphone' => $contactphone,
            'guestmail' => $guestmail,
            'accountmanager' => $accountmanager]
        );
        $reply = [
                'discussionid' => 0,
                'responsibles' => [],
        ];
        if (!empty(get_config('local_edusupport', 'trackhost'))) {
            $params['webhost'] = gethostname();
        }
        $params['description'] = nl2br($params['description']);

        $tmp = explode('_', $forumgroup);
        $forumid = 0;
        $groupid = 0;
        if (count($tmp) == 2) {
            $forumid = $tmp[0];
            $groupid = $tmp[1];
        }

        $PAGE->set_context(\context_system::instance());

        if ($forumgroup == 'mail' || empty($forumid)) {
            // Fallback and send by mail!
            $subject = $params['subject'];
            $params['includeemail'] = $user->email;
            $messagehtml = $OUTPUT->render_from_template("local_edusupport/issue_template", $params);
            $messagetext = html_to_text($messagehtml);

            $supportuser = \core_user::get_support_user();
            $recipients = [$supportuser];
            $reply['responsibles'][] = [
                    'userid' => $supportuser->id,
                    'name' => \fullname($supportuser),
                    'email' => $supportuser->email,
            ];
            $fromuser = $user;

            if (!empty($params['image'])) {
                $filename = $params['screenshotname'];
                // Write image to a temporary file.
                $x = explode(",", $params['image']);
                $filepath = $CFG->tempdir . '/edusupport-' . md5($user->id . date("Y-m-d H:i:s"));
                file_put_contents($filepath, base64_decode($x[1]));
                \core\antivirus\manager::scan_file($filepath, $filename, true);
                foreach ($recipients as $recipient) {
                    email_to_user($recipient, $fromuser, $subject, $messagetext, $messagehtml, $filepath, $filename);
                }
                if (file_exists($filepath)) {
                    unlink($filepath);
                }
            } else {
                foreach ($recipients as $recipient) {
                    email_to_user($recipient, $fromuser, $subject, $messagetext, $messagehtml);
                }
            }
            $reply['discussionid'] = -999;
            return $reply;
        } else {
            $potentialtargets = \local_edusupport\lib::get_potentialtargets();
            if (\local_edusupport\lib::is_supportforum($forumid) && !empty($potentialtargets[$forumid]->id)) {
                $canpostto2ndlevel = $potentialtargets[$forumid]->postto2ndlevel;
                // Mainly copied from mod/forum/externallib.php > add_discussion().
                $warnings = [];

                // Request and permission validation.
                $forum = $DB->get_record('forum', ['id' => $forumid], '*', MUST_EXIST);
                [$course, $cm] = get_course_and_cm_from_instance($forum, 'forum');

                $coursesupporters = \local_edusupport\lib::get_course_supporters($forum);
                foreach ($coursesupporters as $coursesupporter) {
                    $reply['responsibles'][] = [
                            'userid' => $coursesupporter->id,
                            'name' => \fullname($coursesupporter),
                            'email' => $coursesupporter->email,
                    ];
                }

                $context = context_module::instance($cm->id);
                // phpcs:ignore Squiz.PHP.CommentedOutCode.Found
                /* self::validate_context($context); */

                // Validate options.
                $options = [
                        'discussionsubscribe' => true,
                        'discussionpinned' => false,
                        'inlineattachmentsid' => 0,
                        'attachmentsid' => null,
                ];

                // Create group for user id firstlvlgroupmode is active.
                if (
                    get_config('local_edusupport', 'firstlvlgroupmode') &&
                        $cfn = get_config('local_edusupport', 'customfieldname')
                ) {
                    require_once("$CFG->dirroot/group/lib.php");
                    $groupname = fullname($user) . ' (' . $user->id . '-coursesupport)';
                    $group = $DB->get_record('groups', ['courseid' => $forum->course, 'name' => $groupname]);
                    if (empty($group->id)) {
                        // Create a group for this user.
                        $group = (object) [
                                'courseid' => $forum->course,
                                'name' => $groupname,
                                'description' => '',
                                'descriptionformat' => 1,
                                'timecreated' => time(),
                                'timemodified' => time(),
                        ];
                        $group->id = groups_create_group($group, false);
                    }
                    if (!empty($group->id)) {
                        // Find support users.
                        $groupusers = \local_edusupport\lib::get_support_user_by_matching_customfield($forum->course, $cfn);
                        groups_add_member($group, $user);
                        if ($groupusers) {
                            $responsibles = [];
                            foreach ($groupusers as $user) {
                                groups_add_member($group, $user->userid);
                                $responsibles[] =
                                    "<a href='{$CFG->wwwroot}/user/profile.php?id={$user->userid}' target='_blank'>" .
                                        "{$user->firstname} {$user->lastname}</a>";
                            }
                        } else {
                            $postto2ndlevel = true;
                        }
                    }
                }
                // Normalize group.
                if (!groups_get_activity_groupmode($cm)) {
                    // Groups not supported, force to -1.
                    $groupid = -1;
                } else {
                    // Check if we receive the default or and empty value for groupid,
                    // in this case, get the group for the user in the activity.
                    if (empty($groupid)) {
                        $groupid = groups_get_activity_group($cm);
                    }
                }

                // phpcs:ignore Squiz.PHP.CommentedOutCode.Found
                /* if (!forum_user_can_post_discussion($forum, $groupid, -1, $cm, $context)) {
                    throw new moodle_exception('cannotcreatediscussion', 'forum');
                }*/

                $thresholdwarning = forum_check_throttling($forum, $cm);
                forum_check_blocking_threshold($thresholdwarning);

                $message = $OUTPUT->render_from_template("local_edusupport/issue_template", $params);

                // Create the discussion.
                $discussion = new stdClass();
                $discussion->course = $course->id;
                $discussion->forum = $forum->id;
                $discussion->message = $message;
                $discussion->messageformat = FORMAT_HTML;   // Force formatting for now.
                $discussion->messagetrust = trusttext_trusted($context);
                $discussion->itemid = 0;
                // phpcs:ignore Squiz.PHP.CommentedOutCode.Found
                /* $options['inlineattachmentsid']; */
                $discussion->groupid = $groupid;
                $discussion->mailnow = 1;
                if ($guestmodeenabled) {
                    $discussion->subject = '[Guestticket: ' . $params['guestmail'] . '] ' . $params['subject'];
                    $discussion->name = $discussion->subject;
                } else {
                    $discussion->subject = $params['subject'];
                    $discussion->name = $discussion->subject;
                }
                if ($subjectprefixenabled) {
                    $discussion->subject = get_string('subject_prefix', 'local_edusupport') . " " . $discussion->subject;
                    $discussion->name = $discussion->subject;
                }

                $discussion->timestart = 0;
                $discussion->timeend = 0;
                $discussion->timelocked = 0;
                $discussion->attachment = 0;

                if (has_capability('mod/forum:pindiscussions', $context) && $options['discussionpinned']) {
                    $discussion->pinned = FORUM_DISCUSSION_PINNED;
                } else {
                    $discussion->pinned = FORUM_DISCUSSION_UNPINNED;
                }

                if ($discussionid = forum_add_discussion($discussion, null, null, $user->id)) {
                    $discussion->id = $discussionid;

                    if (!empty($params['image'])) {
                        $filename = $params['screenshotname'];

                        $x = explode(",", $params['image']);
                        // Write the file to a temp target.
                        $filepath = $CFG->tempdir . '/edusupport-' . md5($user->id . date("Y-m-d H:i:s"));
                        file_put_contents($filepath, base64_decode($x[1]));

                        $fs = get_file_storage();
                        // Scan for viruses.
                        \core\antivirus\manager::scan_file($filepath, $filename, true);

                        $fr = new stdClass();
                        $fr->component = 'mod_forum';
                        $fr->contextid = $context->id;
                        $fr->userid = $user->id;
                        $fr->filearea = 'attachment';
                        $fr->filename = $filename;
                        $fr->filepath = '/';
                        $fr->itemid = $discussion->firstpost;
                        $fr->license = $CFG->sitedefaultlicense;
                        $fr->author = fullname($user);
                        $fr->source = serialize((object) ['source' => $filename]);

                        $fs->create_file_from_pathname($fr, $filepath);
                        $DB->set_field('forum_posts', 'attachment', 1, ['id' => $discussion->firstpost]);
                    }

                    // Trigger events and completion.

                    $evparams = [
                            'context' => $context,
                            'objectid' => $discussion->id,
                            'other' => [
                                    'forumid' => $forum->id,
                            ],
                    ];
                    // Send email to user if configured.
                    $a = new stdClass();
                    $a->wwwroot = $CFG->wwwroot;
                    $a->cmid = $cm->id;
                    $a->sitename = $SITE->fullname;
                    $subject = get_string('issuereceived:subject', 'local_edusupport');
                    $mailhtml = get_string('issuereceived', 'local_edusupport', $a);
                    $mailtext = format_text($mailhtml, FORMAT_PLAIN);
                    if (get_config('local_edusupport', 'sendrequestreceived', $a)) {
                        \email_to_user($user, $user, $subject, $mailtext, $mailhtml, "", true);
                    }
                    $event = \mod_forum\event\discussion_created::create($evparams);
                    $event->add_record_snapshot('forum_discussions', $discussion);
                    $event->trigger();

                    $completion = new completion_info($course);
                    if (
                        $completion->is_enabled($cm) &&
                            ($forum->completiondiscussions || $forum->completionposts)
                    ) {
                        $completion->update_state($cm, COMPLETION_COMPLETE);
                    }

                    // Set the forum post as already mailed if the original request should not be sent to user.
                    $sendemail = get_config('local_edusupport', 'sendoriginalrequest');
                    if (!$sendemail) {
                        $discussion = $DB->get_record('forum_discussions', ['id' => $discussionid]);
                        $DB->set_field('forum_posts', 'mailed', 1, ['id' => $discussion->firstpost]);
                    }

                    $settings = new stdClass();
                    $settings->discussionsubscribe = $options['discussionsubscribe'];
                    forum_post_subscription($settings, $forum, $discussion);
                    $keyvaluepair = null;
                    if (isset($params['accountmanager'])) {
                        $keyvaluepair = new stdClass();
                        $keyvaluepair->key = 'accountmanager';
                        $keyvaluepair->value = $params['accountmanager'];
                    }
                    if ($postto2ndlevel && get_config('local_edusupport', 'firstlvlgroupmode')) {
                        \local_edusupport\lib::set_2nd_level($discussion->id, $keyvaluepair);
                    } else if ($canpostto2ndlevel && !empty($postto2ndlevel)) {
                        \local_edusupport\lib::set_2nd_level($discussion->id, $keyvaluepair);
                    } else if (get_config('local_edusupport', 'auto2ndlvl')) {
                        \local_edusupport\lib::set_2nd_level($discussion->id, $keyvaluepair);
                    } else {
                        // Post answer containing the reponsibles.
                        $managers = array_values(\local_edusupport\lib::get_course_supporters($forum));

                        if (!get_config('local_edusupport', 'firstlvlgroupmode')) {
                            $responsibles = [];
                            foreach ($managers as $manager) {
                                $responsibles[] =
                                        "<a href='{$CFG->wwwroot}/user/profile.php?id={$manager->id}' target='_blank'>" .
                                            "{$manager->firstname} {$manager->lastname}</a>";
                            }
                        }
                        $forum = $DB->get_record('forum', ['id' => $discussion->forum]);
                        \local_edusupport\lib::create_post(
                            $discussion->id,
                            get_string(
                                'issue_responsibles:post',
                                'local_edusupport',
                                [
                                        'responsibles' => implode(', ', $responsibles),
                                        'sitename' => $SITE->fullname,
                                        'supportforumname' => $forum->name,
                                    ]
                            ),
                            get_string('issue_responsibles:subject', 'local_edusupport'),
                            get_config('local_edusupport', 'sendsupporterassignments')
                        );
                    }

                    $reply['discussionid'] = $discussionid;
                    return $reply;
                } else {
                    throw new moodle_exception('couldnotadd', 'forum');
                }
                $reply['discussionid'] = -2;
                return $reply;
            } else {
                $reply['discussionid'] = -1;
                return $reply;
            }
        }
    }

    /**
     * Return definition.
     * @return external_single_structure
     */
    public static function create_issue_returns() {
        return new \external_single_structure(
            [
                'discussionid' => new \external_value(
                    PARAM_INT,
                    'Returns the discussion id of the created issue, -999 when mail was sent, or -1 on error'
                ),
                'responsibles' => new \external_multiple_structure(
                    new \external_single_structure(
                        [
                            'userid' => new \external_value(PARAM_INT, 'UserID of person or entity'),
                            'name' => new \external_value(PARAM_TEXT, 'Name of person or entity'),
                            'email' => new \external_value(PARAM_EMAIL, 'e-Mail of person or entity'),
                        ]
                    )
                ),
            ]
        );
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function create_form_parameters() {
        return new external_function_parameters([
            'url' => new external_value(PARAM_TEXT, 'subject of this issue'),
            'image' => new external_value(PARAM_RAW, 'base64 encoded image or empty'),
            'forumid' => new external_value(PARAM_INT, 'forumid the form is for'),
        ]);
    }

    /**
     * Create the form for submitting support requests. The form will be displayed in a modal.
     *
     * @param $url
     * @param $image
     * @param $forumid
     * @return string
     */
    public static function create_form($url, $image, $forumid): string {
        global $CFG, $PAGE, $USER, $OUTPUT;

        $params = self::validate_parameters(
            self::create_form_parameters(),
            ['url' => $url, 'image' => $image, 'forumid' => $forumid]
        );

        $PAGE->set_context(context_system::instance());

        \local_edusupport\lib::before_popup();

        require_once($CFG->dirroot . '/local/edusupport/classes/issue_create_form.php');
        $params['contactphone'] = $USER->phone1;
        $form = new \issue_create_form(null, null, 'post', '_self', ['id' => 'local_edusupport_create_form'], true);
        $form->set_data((object) $params);
        $prepageenabled = get_config('local_edusupport', 'enableprepage');
        $prepage = get_config('local_edusupport', 'prepage');
        if ($prepageenabled && $prepage) {
            $templatedata['prepage'] = format_text($prepage, FORMAT_HTML);
            $templatedata['form'] = $form->render();
            $output = $OUTPUT->render_from_template('local_edusupport/prepageenabled', $templatedata);
        } else {
            $output = $form->render();
        }
        return $output;
    }
    /**
     * Return definition.
     * @return external_value
     */
    public static function create_form_returns() {
        return new external_value(PARAM_RAW, 'Returns the form as html');
    }

    public static function get_potentialsupporters_parameters() {
        return new external_function_parameters(
            [
                'discussionid' => new external_value(PARAM_INT, 'discussionid'),
            ]
        );
    }

    /**
     * @param int $discussionid
     * @return false|string
     * @throws coding_exception
     * @throws dml_exception
     * @throws invalid_parameter_exception
     */
    public static function get_potentialsupporters(int $discussionid) {
        global $DB, $USER;
        $params = self::validate_parameters(self::get_potentialsupporters_parameters(), ['discussionid' => $discussionid]);
        $reply['supporters'] = [];

        $discussion = $DB->get_record('forum_discussions', ['id' => $params['discussionid']]);
        $sql = "SELECT s.userid,u.firstname,u.lastname,s.supportlevel
                    FROM {user} u, {local_edusupport_supporters} s
                    WHERE u.id=s.userid
                        AND (s.courseid=1 OR s.courseid=?)
                    ORDER BY u.lastname ASC,u.firstname ASC";
        $supporters = $DB->get_records_sql($sql, [$discussion->course]);
        foreach ($supporters as $supporter) {
            if (empty($supporter->supportlevel)) {
                $supporter->supportlevel = get_string('label:2ndlevel', 'local_edusupport');
            }
            if (!isset($reply['supporters'][$supporter->supportlevel])) {
                $reply['supporters'][$supporter->supportlevel] = [];
            }
            // TODO: $issue does not exist!! - @David.
            if (empty($issue->currentsupporter) && $supporter->userid == $USER->id) {
                $supporter->selected = true;
            } else if ($issue->currentsupporter == $supporter->userid) {
                $supporter->selected = true;
            }
            $reply['supporters'][$supporter->supportlevel][] = $supporter;
        }

        return json_encode($reply, JSON_NUMERIC_CHECK);
    }

    /**
     * @return external_value
     */
    public static function get_potentialsupporters_returns() {
        return new external_value(PARAM_RAW, 'Returns a json encoded array containing potential supporters.');
    }

    public static function set_archive_parameters() {
        return new external_function_parameters([
            'forumid' => new external_value(PARAM_INT, 'ForumID of archive'),
        ]);
    }

    /**
     * @param int $forumid
     * @return int
     * @throws dml_exception
     * @throws invalid_parameter_exception
     */
    public static function set_archive(int $forumid): int {
        global $DB;

        $params = self::validate_parameters(self::set_archive_parameters(), ['forumid' => $forumid]);

        $forum = $DB->get_record('forum', ['id' => $params['forumid']]);
        if (empty($forum->id)) {
            return -1;
        }

        if (\local_edusupport\lib::can_config_course($forum->course)) {
            $entry = $DB->get_record('local_edusupport', ['courseid' => $forum->course]);
            if (!empty($entry->courseid)) {
                $entry->forumid = !empty($entry->forumid) ? $entry->forumid : 0;
                $entry->archiveid = $forum->id;
                $DB->update_record('local_edusupport', $entry);
            } else {
                $entry = (object) ['courseid' => $forum->course, 'forumid' => $forum->id, 'archiveid' => 0];
                $DB->insert_record('local_edusupport', $entry);
            }
            return 1;
        }
        return 0;
    }

    /**
     * @return external_value
     */
    public static function set_archive_returns() {
        return new external_value(PARAM_INT, 'Returns 1 if successful');
    }

    public static function set_currentsupporter_parameters() {
        return new external_function_parameters(
            [
                'discussionid' => new external_value(PARAM_INT, 'discussionid'),
                'supporterid' => new external_value(PARAM_INT, 'supporterid (userid)'),
            ]
        );
    }
    public static function set_currentsupporter($discussionid, $supporterid) {
        global $CFG, $DB, $USER;
        $params = self::validate_parameters(
            self::set_currentsupporter_parameters(),
            ['discussionid' => $discussionid, 'supporterid' => $supporterid]
        );
        return \local_edusupport\lib::set_current_supporter($params['discussionid'], $params['supporterid']);
    }
    public static function set_currentsupporter_returns() {
        return new external_value(PARAM_RAW, 'Returns 1 if successful.');
    }


    public static function set_default_parameters() {
        return new external_function_parameters([
            'forumid' => new external_value(PARAM_INT, 'ForumID of new systemwide forum'),
            'asglobal' => new external_value(PARAM_BOOL, 'Whether this should be used as global target forum or not'),
        ]);
    }
    public static function set_default($forumid, $asglobal) {
        global $CFG, $DB, $PAGE;

        $params = self::validate_parameters(self::set_default_parameters(), ['forumid' => $forumid, 'asglobal' => $asglobal]);

        $forum = $DB->get_record('forum', ['id' => $params['forumid']]);
        if (empty($forum->id)) {
            return -1;
        }
        if ($params['asglobal']) {
            if (\local_edusupport\lib::can_config_global()) {
                set_config('targetforum', $forum->id, 'local_edusupport');
            } else {
                return -2;
            }
        } else if ($forum->id == get_config('local_edusupport', 'targetforum')) {
            set_config('targetforum', 0, 'local_edusupport');
        }

        if (\local_edusupport\lib::can_config_course($forum->course)) {
            $entry = $DB->get_record('local_edusupport', ['courseid' => $forum->course]);
            if (!empty($entry->forumid)) {
                $entry->forumid = $forum->id;
                $DB->update_record('local_edusupport', $entry);
            } else {
                $entry = (object) ['courseid' => $forum->course, 'forumid' => $forum->id];
                $DB->insert_record('local_edusupport', $entry);
            }
            return 1;
        }
        return 0;
    }
    public static function set_default_returns() {
        return new external_value(PARAM_INT, 'Returns 1 if successful');
    }

    public static function set_supporter_parameters() {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'CourseID'),
            'userid' => new external_value(PARAM_INT, 'UserID'),
            'supportlevel' => new external_value(PARAM_TEXT, 'Supportlevel to set'),
        ]);
    }

    /**
     * @param int $courseid
     * @param int $userid
     * @param string $supportlevel
     * @return int
     * @throws dml_exception
     * @throws invalid_parameter_exception
     */
    public static function set_supporter(int $courseid, int $userid, string $supportlevel) {
        global $DB;

        $params = self::validate_parameters(
            self::set_supporter_parameters(),
            ['courseid' => $courseid, 'userid' => $userid, 'supportlevel' => $supportlevel]
        );

        if (\local_edusupport\lib::can_config_course($params['courseid'])) {
            if (empty($params['supportlevel'])) {
                if (
                    $DB->delete_records(
                        'local_edusupport_supporters',
                        ['courseid' => $params['courseid'], 'userid' => $params['userid']]
                    )
                ) {
                    $event = \local_edusupport\event\supportuser_deleted::create(
                        [
                            'objectid' => $params['courseid'],
                            'relateduserid' => $params['userid'],
                            'other' => ['supportuserid' => $params['userid'], 'supportlevel' => $params['supportlevel']],
                        ]
                    );
                    $event->trigger();
                }
            } else {
                $entry = $DB->get_record(
                    'local_edusupport_supporters',
                    ['courseid' => $params['courseid'], 'userid' => $params['userid']]
                );
                if (!empty($entry->supportlevel)) {
                    $entry->supportlevel = $params['supportlevel'];
                    if ($DB->update_record('local_edusupport_supporters', $entry)) {
                        $event = \local_edusupport\event\supportuser_changed::create(
                            [
                                'objectid' => $params['courseid'],
                                'relateduserid' => $params['userid'],
                                'other' => ['supportuserid' => $params['userid'], 'supportlevel' => $params['supportlevel']],
                            ]
                        );
                        $event->trigger();
                    }
                } else {
                    if ($DB->insert_record('local_edusupport_supporters', (object) $params)) {
                        $event = \local_edusupport\event\supportuser_added::create(
                            [
                                'objectid' => $params['courseid'],
                                'relateduserid' => $params['userid'],
                                'other' => ['supportuserid' => $params['userid'], 'supportlevel' => $params['supportlevel']],
                            ]
                        );
                        $event->trigger();
                    }
                }
            }
            return 1;
        }
        return 0;
    }

    /**
     * @return external_value
     */
    public static function set_supporter_returns() {
        return new external_value(PARAM_INT, 'Returns 1 if successful');
    }

    /**
     * @return external_function_parameters
     */
    public static function set_status_parameters() {
        return new external_function_parameters([
            'status' => new external_value(PARAM_INT, 'status'),
            'issueid' => new external_value(PARAM_INT, 'issueid'),
        ]);
    }

    /**
     * @param int $status
     * @param int $issueid
     * @return int
     * @throws coding_exception
     * @throws invalid_parameter_exception
     * @throws moodle_exception
     * @throws require_login_exception
     */
    public static function set_status(int $status, int $issueid) {
        global $USER;
        require_login();
        $params = self::validate_parameters(self::set_status_parameters(), ['status' => $status, 'issueid' => $issueid]);
        if (\local_edusupport\lib::is_supportteam($USER->id) || \is_siteadmin()) {
            \local_edusupport\lib::set_status($params['status'], $params['issueid']);
            return 1;
        }
        return 0;
    }

    /**
     * @return external_value
     */
    public static function set_status_returns() {
        return new external_value(PARAM_INT, 'Returns 1 if successful');
    }
}
