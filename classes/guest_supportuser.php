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
 * Create a dummy user who is going to post forum messages instead of the Moodle guest user.
 * This user is needed when site visitors who do not have a login yet are using the support button to submit a support request.
 *
 * @package     local_edusupport
 * @author      Davvid Bogner
 * @copyright   2022 Wunderbyte GmbH
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_edusupport;

use stdClass;

/**
 * Class guest_supportuser: Creates a dummy user for anonymous support requests.
 *
 * @author      David Bogner
 * @copyright   2022 Wunderbyte GmbH
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class guest_supportuser {
    /**
     * @var stdClass
     */
    protected $user;

    /**
     * Checks if dummy user has already been created, if not it will be created and is then available through
     * get_support_guest_user
     * @throws \dml_exception
     */
    public function __construct() {
        global $CFG;
        $user = new stdClass();
        $user->username = "edusupport_guest_ticket";
        $user->firstname = "Guest";
        $user->email = 'edusupport@example.com';
        $user->lastname = "Ticket";
        $user->mnethostid = $CFG->mnet_localhost_id;
        $this->user = $user;
        $this->check_guestuser_config();
    }

    /**
     * Get the user who is replacing the guest user in order to be able to post to forum
     *
     * @return stdClass
     */
    public function get_support_guestuser(): stdClass {
        return $this->user;
    }

    /**
     * Check if user id is already in config.
     *
     * @return void
     * @throws \dml_exception
     */
    protected function check_guestuser_config(): void {
        $userid = get_config('local_edusupport', 'guestuserid');
        if ($this->guestuser_exists() && (int) $this->user->id === (int) $userid) {
            return;
        }
        $this->create_guestuser_if_inextistant();
    }

    /**
     * If the dummy user does not yet exist, create it with core function.
     *
     * @return void
     * @throws \moodle_exception
     */
    public function create_guestuser_if_inextistant(): void {
        global $CFG;
        if (!$this->guestuser_exists()) {
            require_once($CFG->dirroot . '/user/lib.php');
            $this->user->id = user_create_user($this->user, false, true);
        }
        set_config('guestuserid', $this->user->id, 'local_edusupport');
    }

    /**
     * Checks if dummy account for guest users already exists.
     * Will set user->id and return true if user exists, false if not
     *
     * @return int
     */
    protected function guestuser_exists(): bool {
        global $DB;
        $user = $DB->get_record('user', ['email' => $this->user->email,
            'mnethostid' => $this->user->mnethostid, 'deleted' => 0]);
        if ($user) {
            $this->user = $user;
            return true;
        } else {
            return false;
        }
    }
}
