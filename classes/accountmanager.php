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
 * accountmanager
 *
 * @package     local_edusupport
 * @author      Thomas Winkler
 * @copyright   2022 Wunderbyte GmbH
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_edusupport;

defined('MOODLE_INTERNAL') || die;


/**
 * Class accountmanager
 * @author      Thomas Winkler
 * @copyright   2022 Wunderbyte GmbH
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class accountmanager {

    public $userid;

    /**
     * empty constructor accountmanager
     *
     */
    public function __construct() {

    }

    /**
     *
     * This is to change mail before issue mail is sent
     * @param string $mail
     *
     */
    public function form_to_config_edusupport_accountmanager(array $accountmanagers, array $capstocheck) {
        $accountmanagerslist = implode(',', $accountmanagers);
        $capstocheck = implode(',', $capstocheck);
        set_config('accountmanagers', $accountmanagerslist, 'local_edusupport');
        set_config('capstocheck', $capstocheck, 'local_edusupport');
    }

    /**
     * returns all managers from site
     * @return array
     */
    public static function get_capabiltities_to_check() {
        return array(
            'moodle/course:manageactivities' => 'moodle/course:manageactivities',
            'moodle/course:viewhiddenactivities' => 'moodle/course:viewhiddenactivities',
            'moodle/category:manage' => 'moodle/category:manage',
            'enrol/category:config' => 'enrol/category:config',
        );
    }
    /**
     * returns all managers from site
     * @return array|bool
     */
    public static function get_all_category_managers_from_site() {
        global $DB;
        $roles = get_roles_with_capability('moodle/course:create', CAP_ALLOW);
        $sql = '
        SELECT
        DISTINCT u.username as username, u.id as userid, u.firstname, u.lastname
        FROM {role_assignments} ra
        JOIN {user} u ON u.id = ra.userid
        JOIN {role} r ON r.id = ra.roleid
        JOIN {context} ctx ON ctx.id = ra.contextid
        WHERE ';
        $i = 0;
        foreach($roles as $role) {
            if ($i == 0) {
                $sql .= "r.id = ". $role->id . " ";
            } else {
                $sql .= "OR r.id = " . $role->id . " ";
            }
            $i++;
        }
        $sql .= " UNION select u.username as username, u.id as userid, u.firstname, u.lastname
        FROM {user} u
        WHERE
        u.id IN (SELECT value FROM {config} WHERE name = 'siteadmins') ";
        $sql .= "ORDER BY username";

        $users = $DB->get_records_sql($sql);
        if (isset($users)) {
            foreach ($users as $user) {
                $name = $user->firstname . ' ' . $user->lastname;
                $id = $user->userid;
                $possibleusers[$id] = $name;
            }
            return $possibleusers;
        }
        return false;
    }


    /**
     * Checks if a user can choose an accountmanager
     * @return bool
     */
    public function can_choose_accountmanager() {
        global $DB, $USER;
        if (empty(get_config('local_edusupport', 'capstocheck'))) return false;
        $capability = explode(',', get_config('local_edusupport', 'capstocheck'));
        $sql = '
            SELECT  c.id as cid, ra.id, ra.userid, ra.contextid, ra.roleid, r.shortname, ra.component, ra.itemid, c.path
            FROM {role_assignments} ra
            JOIN {context} c ON ra.contextid = c.id
            JOIN {role} r ON ra.roleid = r.id
   		    WHERE ra.userid = ?
            ORDER BY contextlevel DESC, contextid ASC, r.sortorder ASC
        ';
        if ($DB->record_exists_sql($sql, array($USER->id))) {
            $records = $DB->get_records_sql($sql, array($USER->id));
            foreach ($records as $record) {
                $context = \context::instance_by_id($record->cid);
                if (has_any_capability($capability, $context)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Prepares the accountmanagers for the issue create form
     *
     */
    public function prepare_accountmanager_for_form(&$mform) {
        global $CFG;
        require_once($CFG->dirroot.'/user/lib.php');
        $users = \user_get_users_by_id(explode(',', get_config('local_edusupport', 'accountmanagers')));
        if (empty($users) || !$this->can_choose_accountmanager()) {
            return;
        }

        $options = array('0' => get_string('none', 'local_edusupport'));

        foreach ($users as $user) {
            $options[$user->id] = $user->firstname . ' ' . $user->lastname;
        }

        $mform->addElement('select', 'accountmanager', get_string('accountmanager', 'local_edusupport'), $options);
        $mform->setDefault('accountmanager', 0);

    }
    /**
     * Deletes account manager from setting
     *
     * @param int $userid
     * @return void
     */
    public static function delete_account_manager(int $userid) {
        if (!get_config('local_edusupport', 'accountmanagers')) {
            return;
        }
        $accountmanagers = explode(',', get_config('local_edusupport', 'accountmanagers'));
        if (in_array($userid, $accountmanagers)) {
            unset($accountmanagers[array_search($userid, $accountmanagers)]);
            $accountmanagerslist = implode(',', $accountmanagers);
            set_config('accountmanagers', $accountmanagerslist, 'local_edusupport');
        }
    }
}
