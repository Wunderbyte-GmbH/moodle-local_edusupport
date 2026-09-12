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
 * Ad hoc task that sends one mail belonging to a support request.
 *
 * @package    local_edusupport
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_edusupport\task;

/**
 * Ad hoc task that sends one mail belonging to a support request.
 *
 * Filing a ticket used to hand the mail to the mail server while the browser was waiting for the
 * web service to answer. A slow or unreachable relay therefore froze the support form for as long
 * as the connection took to fail. The mails are queued here instead, so the person filing the
 * request gets their confirmation straight away and cron does the talking to the mail server.
 *
 * @package    local_edusupport
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class send_mail extends \core\task\adhoc_task {
    /**
     * Once the retries of a mail have been pushed this far apart it is dropped rather than
     * kept in the queue forever. A mail server that has been refusing a message for a day
     * is not going to accept it on the next attempt either.
     */
    private const GIVE_UP_DELAY = DAYSECS;

    /**
     * Queue a mail for sending by cron.
     *
     * The address is taken from the recipient as they are handed over and kept, rather than read
     * from the account when the task runs: a reply to a guest ticket goes to the address the guest
     * left behind, which is not the address of the account the ticket was filed under.
     *
     * @param \stdClass $recipient the user to send to. May be one of the pseudo users, e.g. the site support contact.
     * @param \stdClass $fromuser the user the mail is sent on behalf of.
     * @param string $subject
     * @param string $messagetext the plain text body.
     * @param string $messagehtml the html body.
     * @param string $attachmentpath absolute path of a file to attach, which the task deletes once it is sent.
     * @param string $attachmentname the filename the attachment is sent under.
     * @return void
     */
    public static function queue(
        \stdClass $recipient,
        \stdClass $fromuser,
        string $subject,
        string $messagetext,
        string $messagehtml,
        string $attachmentpath = '',
        string $attachmentname = ''
    ): void {
        $task = new self();
        $task->set_custom_data((object) [
            'recipientid' => $recipient->id,
            'recipientemail' => $recipient->email ?? '',
            'fromuserid' => $fromuser->id,
            'subject' => $subject,
            'messagetext' => $messagetext,
            'messagehtml' => $messagehtml,
            'attachmentpath' => $attachmentpath,
            'attachmentname' => $attachmentname,
        ]);
        \core\task\manager::queue_adhoc_task($task);
    }

    /**
     * Get the name shown for this task in the admin screens.
     *
     * @return string
     */
    public function get_name() {
        return get_string('cron:sendmail:title', 'local_edusupport');
    }

    /**
     * Send the mail this task was queued for.
     *
     * @return void
     */
    public function execute() {
        $data = $this->get_custom_data();

        $recipient = self::get_user($data->recipientid);
        $fromuser = self::get_user($data->fromuserid);
        if (empty($recipient) || empty($fromuser)) {
            // One of the two is gone, so there is nobody left to send this to or on behalf of.
            mtrace('local_edusupport: dropping a mail, sender or recipient no longer exists.');
            self::remove_attachment($data->attachmentpath);
            return;
        }

        if (!empty($data->recipientemail)) {
            $recipient->email = $data->recipientemail;
        }

        $sent = \email_to_user(
            $recipient,
            $fromuser,
            $data->subject,
            $data->messagetext,
            $data->messagehtml,
            $data->attachmentpath,
            $data->attachmentname
        );

        if (!$sent) {
            if ($this->get_fail_delay() >= self::GIVE_UP_DELAY) {
                // Retried for a day already. Give up, so the queue does not fill up with it.
                mtrace('local_edusupport: giving up on the mail "' . $data->subject . '" to ' . $recipient->email);
                self::remove_attachment($data->attachmentpath);
                return;
            }
            // Let the task fail, so cron retries it with a growing delay.
            throw new \moodle_exception('error:mailnotsent', 'local_edusupport', '', $recipient->email);
        }

        self::remove_attachment($data->attachmentpath);
    }

    /**
     * Resolve the user a stored id stands for.
     *
     * Beside real users this covers the pseudo users, whose negative ids are not in the user table.
     *
     * @param int $userid
     * @return \stdClass|null the user, or null if they do not exist anymore.
     */
    private static function get_user(int $userid): ?\stdClass {
        global $DB;

        if ($userid == \core_user::SUPPORT_USER) {
            return \core_user::get_support_user();
        }
        if ($userid == \core_user::NOREPLY_USER) {
            return \core_user::get_noreply_user();
        }

        $user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0]);

        return $user ?: null;
    }

    /**
     * Delete the temporary file an attachment was sent from.
     *
     * @param string $attachmentpath
     * @return void
     */
    private static function remove_attachment(string $attachmentpath): void {
        if (!empty($attachmentpath) && file_exists($attachmentpath)) {
            unlink($attachmentpath);
        }
    }
}
