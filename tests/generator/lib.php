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
 * Data generator for local_edusupport.
 *
 * @package    local_edusupport
 * @category   test
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Data generator for local_edusupport.
 *
 * @package    local_edusupport
 * @category   test
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_edusupport_generator extends component_generator_base {
    /**
     * Turn an existing forum into a support forum.
     *
     * @param array|stdClass|null $record needs a forumid, may carry a central flag.
     * @return stdClass the local_edusupport record.
     */
    public function create_supportforum($record = null): stdClass {
        global $DB;

        $record = (object) (array) $record;
        if (empty($record->forumid)) {
            throw new coding_exception('A support forum needs a forumid.');
        }

        $supportforum = \local_edusupport\lib::supportforum_enable($record->forumid);
        if (empty($supportforum->id)) {
            throw new coding_exception('Forum ' . $record->forumid . ' could not be made a support forum.');
        }
        if (!empty($record->central)) {
            set_config('centralforum', $record->forumid, 'local_edusupport');
        }

        return $DB->get_record('local_edusupport', ['id' => $supportforum->id], '*', MUST_EXIST);
    }

    /**
     * Add a user to the support team.
     *
     * Without a courseid the user joins the platform wide team, which is the second level.
     * Pass a real courseid to make somebody first level support of that course.
     *
     * @param array|stdClass|null $record needs a userid.
     * @return stdClass the local_edusupport_supporters record.
     */
    public function create_supporter($record = null): stdClass {
        global $DB;

        $record = (object) (array) $record;
        if (empty($record->userid)) {
            throw new coding_exception('A supporter needs a userid.');
        }

        $supporter = (object) [
            'courseid' => $record->courseid ?? \local_edusupport\lib::SYSTEM_COURSE_ID,
            'userid' => $record->userid,
            'supportlevel' => $record->supportlevel ?? '',
            'holidaymode' => $record->holidaymode ?? 0,
            'autoassign' => $record->autoassign ?? 1,
        ];
        $supporter->id = $DB->insert_record('local_edusupport_supporters', $supporter);

        // Supporters hold a role in every support forum, so bring those assignments up to date.
        \local_edusupport\lib::supportforum_rolecheck();

        return $supporter;
    }

    /**
     * Create a support issue together with the forum discussion behind it.
     *
     * @param array|stdClass|null $record needs a forumid, may carry userid, subject,
     *                                    description, status, priority and currentsupporter.
     * @return stdClass the local_edusupport_issues record, with a discussionid.
     */
    public function create_issue($record = null): stdClass {
        global $DB, $USER;

        $record = (object) (array) $record;
        if (empty($record->forumid)) {
            throw new coding_exception('An issue needs a forumid.');
        }

        $forum = $DB->get_record('forum', ['id' => $record->forumid], '*', MUST_EXIST);
        $discussion = $this->datagenerator->get_plugin_generator('mod_forum')->create_discussion([
            'course' => $forum->course,
            'forum' => $forum->id,
            'userid' => $record->userid ?? $USER->id,
            'name' => $record->subject ?? 'Test issue',
            'message' => $record->description ?? 'Test issue description',
        ]);

        $issue = \local_edusupport\lib::get_issue($discussion->id, true);
        $issue->currentsupporter = $record->currentsupporter ?? 0;
        $issue->priority = $record->priority ?? 1;
        $issue->status = $record->status ?? ISSUE_STATUS_NOTSTARTED;
        $issue->timemodified = time();
        $DB->update_record('local_edusupport_issues', $issue);

        return $DB->get_record('local_edusupport_issues', ['id' => $issue->id], '*', MUST_EXIST);
    }
}
