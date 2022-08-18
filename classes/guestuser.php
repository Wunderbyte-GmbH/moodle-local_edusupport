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
 * guestuser
 *
 * @package     local_edusupport
 * @author      Thomas Winkler
 * @copyright   2022 Wunderbyte GmbH
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_edusupport;

use core_user;
use moodle_exception;
use stdClass;

defined('MOODLE_INTERNAL') || die;

/**
 * Class guestuser
 * @author      Thomas Winkler
 * @copyright   2022 Wunderbyte GmbH
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class guestuser {

    public $userid;

    /**
     * guest user
     *
     */
    public function __construct(string $mail = null) {
        global $DB;
        if ($DB->record_exists('user', ['email' => $mail])) {
            throw new moodle_exception('you have already an account');
        }
        $this->userid = $this->create_temp_user($mail);
    }

    /**
     *
     * This is to create a new entity in the database
     *
     * @param string $mail
     *
     */
    public function create_temp_user(string $mail) {
        strtolower($mail);
        $user = new stdClass;
        $string = str_replace(' ', '-', $mail);
        $string = preg_replace('/[^A-Za-z0-9\-]/', '', $string);
        $user->username = 'support'.time().$string;
        $user->firstname = "Support";
        $user->lastname = time();
        $user->email = $mail;
        $user->description = "local_edusupport";
        return \core_useruser_create_user($user, false, false);
    }

    public function delete_temp_user(string $mail) {
        global $DB;
        $user = find_user($mail);
        user_delete_user($user);
    }
}
