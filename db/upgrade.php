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
    global $DB, $CFG;
    $dbman = $DB->get_manager();

    if ($oldversion < 2022082400) {

        // Define field status to be added to local_edusupport_issues.
        $table = new xmldb_table('local_edusupport_issues');
        $field = new xmldb_field('status', XMLDB_TYPE_INTEGER, '1', null, null, null, null, 'opened');

        // Conditionally launch add field status.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Rename field priority on table local_edusupport_issues to NEWNAMEGOESHERE.
        $table = new xmldb_table('local_edusupport_issues');
        $field = new xmldb_field('opened', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1', 'currentsupporter');

        // Launch rename field priority.
        $dbman->rename_field($table, $field, 'priority');

        // Edusupport savepoint reached.
        upgrade_plugin_savepoint(true, 2022082400, 'local', 'edusupport');
    }

    if ($oldversion < 2022090501) {
        $table = new xmldb_table('local_edusupport_supporters');
        $field = new xmldb_field('holidaymode', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'supportlevel');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2022090501, 'local', 'edusupport');
    }

    if ($oldversion < 2022090701) {
        $table = new xmldb_table('local_edusupport_issues');
        $field = new xmldb_field('accountmanager', XMLDB_TYPE_INTEGER, '1', null, null, null, null, 'status');

        // Conditionally launch add field status.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2022090701, 'local', 'edusupport');
    }

    if ($oldversion < 2022091202) {
        require_once($CFG->dirroot.'/user/lib.php');
        $user = new stdClass();
        $user->username = "edusupport_guest_ticket";
        $user->firstname = "Guest";
        $user->lastname = "Ticket";
        $user->email = 'edusupport@example.com';
        $user->mnethostid = $CFG->mnet_localhost_id;
        if (!$DB->record_exists('user', array('email' => $user->email))) {
            $guestuserid = user_create_user($user, false, true);
            set_config('guestuserid', $guestuserid, 'local_edusupport');
        }
        upgrade_plugin_savepoint(true, 2022091202, 'local', 'edusupport');
    }


    if ($oldversion < 2022091500) {
        $table = new xmldb_table('local_edusupport_issues');
        $field = new xmldb_field('timecreated', XMLDB_TYPE_INTEGER, '10', true, null, null, time(), 'accountmanager');

        // Conditionally launch add field status.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $table = new xmldb_table('local_edusupport_issues');
        $field = new xmldb_field('timemodified', XMLDB_TYPE_INTEGER, '10', true, null, null, time(), 'timecreated');

        // Conditionally launch add field status.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Rename field
        upgrade_plugin_savepoint(true, 2022091500, 'local', 'edusupport');
    }

    if ($oldversion < 2022092004) {
        $records = $DB->get_records('local_edusupport_issues', ['status' => NULL]);
        if(!empty($records)) {
            foreach ($records as $record) {
                $record->status = 1;
                $DB->update_record('local_edusupport_issues', $record, true);
            }
        }
        upgrade_plugin_savepoint(true, 2022092004, 'local', 'edusupport');
    }
    if ($oldversion < 2022092005) {
        $table = new xmldb_table('local_edusupport_issues');
        $field = new xmldb_field('status', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, 1, 'priority');
        // Conditionally launch change field status.
        if ($dbman->field_exists($table, $field)) {
            $dbman->change_field_notnull($table, $field);
            $dbman->change_field_default($table, $field);
        }
        // Rename field
        upgrade_plugin_savepoint(true, 2022092005, 'local', 'edusupport');
    }
    if ($oldversion < 2022092008) {

        // Changing type of field archiveid on table local_edusupport to int.
        $table = new xmldb_table('local_edusupport_issues');
        $field = new xmldb_field('accountmanager', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'status');

        // Launch change of type for field archiveid.
        $dbman->change_field_type($table, $field);

        // Edusupport savepoint reached.
        upgrade_plugin_savepoint(true, 2022092008, 'local', 'edusupport');
    }



    return true;
}
