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
* @copyright  2019 Digital Education Society (http://www.dibig.at)
* @author     Robert Schrenk
* @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
*/

defined('MOODLE_INTERNAL') || die;

function xmldb_local_edusupport_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();
    if ($oldversion < 2022082300) {
        $user = new stdClass();
        $user->username = "edusupport_guest_ticket";
        $user->firstname = "Guest";
        $user->lastname = "Ticket";
        $user->email = 'edusupport@example.com';
        $guestuserid = user_create_user($user, false, false);
        set_config('guestuserid', $guestuserid, 'local_edusupport');
        upgrade_plugin_savepoint(true, 2022082300, 'local', 'edusupport');
    }

    if ($oldversion < 2022081800) {
        $table = new xmldb_table('local_edusupport_issues');
        $field = new xmldb_field('status', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, null,
                'opened');

        // Launch rename field text.
        $dbman->rename_field($table, $field, 'status');

        upgrade_plugin_savepoint(true, 2022081800, 'local', 'edusupport');
    }
  
    if ($oldversion < 2022032500) {
        $table = new xmldb_table('local_edusupport_supporters');
        $field = new xmldb_field('holidaymode', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'supportlevel');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2022032500, 'local', 'edusupport');
    }

    return true;
}
