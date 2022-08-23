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
use mod_bigbluebuttonbn\local\config;
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
       
    }

    /**
     *
     * This is to change mail before issue mail is sent 
     * @param string $mail
     *
     */
    public function change_user_mail(string $mail) {
        $user = \core_user::get_user(get_config('local_edusupport', 'guestuserid'));
        $user->mail = $mail;
        user_update_user($user, false);
    }

    public function reset_user_mail() {
        $user = \core_user::get_user(get_config('local_edusupport', 'guestuserid'));
        $user->mail = "edusupport@example.com";
        user_update_user($user, false);
    }
}
