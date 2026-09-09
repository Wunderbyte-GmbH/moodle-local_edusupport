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
 * Tests for the marker that flags a discussion as a closed issue.
 *
 * @package    local_edusupport
 * @category   test
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_edusupport;

use advanced_testcase;

/**
 * Tests for the marker that flags a discussion as a closed issue.
 *
 * @package    local_edusupport
 * @category   test
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_edusupport\lib::add_closed_prefix
 * @covers     \local_edusupport\lib::strip_closed_prefix
 * @covers     \local_edusupport\lib::migrate_legacy_closed_prefixes
 */
final class closed_prefix_test extends advanced_testcase {
    /**
     * A plain name gets the marker.
     */
    public function test_add_marks_a_plain_name(): void {
        $this->assertSame(
            lib::CLOSED_PREFIX . 'Allgemeine Anfrage',
            lib::add_closed_prefix('Allgemeine Anfrage')
        );
    }

    /**
     * A name carrying the marker of an earlier version is converted, not stacked.
     */
    public function test_add_replaces_a_legacy_marker(): void {
        $this->assertSame(
            lib::CLOSED_PREFIX . 'Allgemeine Anfrage',
            lib::add_closed_prefix('[Closed] Allgemeine Anfrage')
        );
    }

    /**
     * Closing an already closed issue must not add a second marker.
     */
    public function test_add_is_idempotent(): void {
        $name = 'Allgemeine Anfrage';
        for ($i = 0; $i < 3; $i++) {
            $name = lib::add_closed_prefix($name);
        }
        $this->assertSame(lib::CLOSED_PREFIX . 'Allgemeine Anfrage', $name);
    }

    /**
     * Stripping removes the current marker as well as the ones of earlier versions.
     */
    public function test_strip_removes_both_markers(): void {
        $this->assertSame(
            'Allgemeine Anfrage',
            lib::strip_closed_prefix(lib::CLOSED_PREFIX . 'Allgemeine Anfrage')
        );
        $this->assertSame(
            'Allgemeine Anfrage',
            lib::strip_closed_prefix('[Closed] Allgemeine Anfrage')
        );
        $this->assertSame('Allgemeine Anfrage', lib::strip_closed_prefix('Allgemeine Anfrage'));
    }

    /**
     * A marker that is not at the start of the name is part of the subject and stays.
     */
    public function test_a_marker_inside_the_name_is_left_alone(): void {
        $name = 'Was bedeutet [Closed] im Betreff?';
        $this->assertSame($name, lib::strip_closed_prefix($name));
        $this->assertSame(lib::CLOSED_PREFIX . $name, lib::add_closed_prefix($name));
    }

    /**
     * The upgrade rewrites discussion names that still carry a legacy marker.
     */
    public function test_migrate_legacy_closed_prefixes(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $forum = $this->getDataGenerator()->create_module('forum', ['course' => $course->id]);
        $generator = $this->getDataGenerator()->get_plugin_generator('local_edusupport');
        $generator->create_supportforum(['forumid' => $forum->id]);

        $legacy = $generator->create_issue(['forumid' => $forum->id, 'subject' => '[Closed] Altes Ticket']);
        $open = $generator->create_issue(['forumid' => $forum->id, 'subject' => 'Offenes Ticket']);
        $inside = $generator->create_issue(['forumid' => $forum->id, 'subject' => 'Frage zu [Closed] Tickets']);

        // A discussion in the same forum that is not registered as an issue must stay untouched.
        $unrelated = $this->getDataGenerator()->get_plugin_generator('mod_forum')->create_discussion([
            'course' => $course->id,
            'forum' => $forum->id,
            'userid' => get_admin()->id,
            'name' => '[Closed] Kein Ticket',
        ]);

        $this->assertSame(1, lib::migrate_legacy_closed_prefixes());

        $this->assertSame(
            lib::CLOSED_PREFIX . 'Altes Ticket',
            $DB->get_field('forum_discussions', 'name', ['id' => $legacy->discussionid])
        );
        $this->assertSame(
            'Offenes Ticket',
            $DB->get_field('forum_discussions', 'name', ['id' => $open->discussionid])
        );
        $this->assertSame(
            'Frage zu [Closed] Tickets',
            $DB->get_field('forum_discussions', 'name', ['id' => $inside->discussionid])
        );
        $this->assertSame(
            '[Closed] Kein Ticket',
            $DB->get_field('forum_discussions', 'name', ['id' => $unrelated->id])
        );

        // Running it again changes nothing.
        $this->assertSame(0, lib::migrate_legacy_closed_prefixes());
    }
}
