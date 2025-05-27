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

namespace local_edusupport;

use advanced_testcase;

/**
 * Test unit class of local_edusupport.
 *
 * @package local_edusupport
 * @category test
 * @copyright 2025 Wunderbyte GmbH <info@wunderbyte.at>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class observer_test extends advanced_testcase {
    /**
     * Setup the test environment.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
    }

    /**
     * Make sure the observer kicks in to delete all data related to a user when the user is deleted.
     * @param string $unitname
     * @param string $userid
     * @covers \local_edusupport\observer
     */
    public function test_delete_user(): void {

        global $DB;

        $user = $this->getDataGenerator()->create_user();

        $DB->insert_record('local_edusupport_supporters', [
            'courseid' => 4,
            'userid' => $user->id,
            'supportlevel' => 'test',
            'holidaymode' => 1,
        ]);

        $DB->insert_record('local_edusupport_subscr', [
            'issueid' => 4,
            'userid' => $user->id,
            'discussionid' => 8,
        ]);

        $this->assertTrue(
            $DB->record_exists('local_edusupport_supporters', ['userid' => $user->id]),
            "User {$user->id} should exist."
        );

        $this->assertTrue(
            $DB->record_exists('local_edusupport_subscr', ['userid' => $user->id]),
            "User {$user->id} should exist"
        );

        user_delete_user($user);

        $this->assertFalse(
            $DB->record_exists('local_edusupport_supporters', ['userid' => $user->id]),
            "User {$user->id} should no longer be a supporter."
        );

        $this->assertFalse(
            $DB->record_exists('local_edusupport_subscr', ['userid' => $user->id]),
            "User {$user->id} should no longer be subscribed to any discussions."
        );
    }
}
