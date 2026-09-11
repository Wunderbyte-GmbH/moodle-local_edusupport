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
 * Tests for the privacy provider.
 *
 * @package    local_edusupport
 * @category   test
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_edusupport\privacy;

use context_system;
use context_user;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use core_privacy\tests\provider_testcase;
use stdClass;

/**
 * Tests for the privacy provider.
 *
 * @package    local_edusupport
 * @category   test
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_edusupport\privacy\provider
 */
final class provider_test extends provider_testcase {
    /** @var stdClass somebody who shows up in every place the plugin records people. */
    private stdClass $supporter;

    /** @var stdClass somebody in the same places, whose data has to stay. */
    private stdClass $colleague;

    /** @var stdClass issue handled by the supporter. */
    private stdClass $issue;

    /** @var stdClass issue handled by the colleague, with the supporter as account manager. */
    private stdClass $otherissue;

    /** @var stdClass the support forum, naming the supporter as dedicated supporter. */
    private stdClass $supportforum;

    /**
     * Put two people into every place the plugin records people.
     */
    protected function setUp(): void {
        global $DB;

        parent::setUp();
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $plugingenerator = $generator->get_plugin_generator('local_edusupport');

        $course = $generator->create_course();
        $forum = $generator->create_module('forum', ['course' => $course->id]);
        $this->supportforum = $plugingenerator->create_supportforum(['forumid' => $forum->id]);

        $this->supporter = $generator->create_user();
        $this->colleague = $generator->create_user();
        foreach ([$this->supporter, $this->colleague] as $user) {
            $plugingenerator->create_supporter(['userid' => $user->id]);
        }
        $plugingenerator->create_supporter(['userid' => $this->supporter->id, 'courseid' => $course->id]);

        $this->issue = $plugingenerator->create_issue([
            'forumid' => $forum->id,
            'currentsupporter' => $this->supporter->id,
        ]);
        $this->otherissue = $plugingenerator->create_issue([
            'forumid' => $forum->id,
            'currentsupporter' => $this->colleague->id,
        ]);
        $DB->set_field('local_edusupport_issues', 'accountmanager', $this->supporter->id, ['id' => $this->otherissue->id]);

        foreach ([$this->supporter, $this->colleague] as $user) {
            $subscription = [
                'issueid' => $this->issue->id,
                'discussionid' => $this->issue->discussionid,
                'userid' => $user->id,
            ];
            if (!$DB->record_exists('local_edusupport_subscr', $subscription)) {
                $DB->insert_record('local_edusupport_subscr', $subscription);
            }
        }

        $DB->set_field('local_edusupport', 'dedicatedsupporter', $this->supporter->id, ['id' => $this->supportforum->id]);
        set_config('accountmanagers', $this->supporter->id . ',' . $this->colleague->id, 'local_edusupport');
    }

    /**
     * Everything lives in the person's own context, and people without data have none.
     */
    public function test_get_contexts_for_userid(): void {
        $contextids = provider::get_contexts_for_userid($this->supporter->id)->get_contextids();
        $this->assertEquals([context_user::instance($this->supporter->id)->id], $contextids);

        $stranger = $this->getDataGenerator()->create_user();
        $this->assertCount(0, provider::get_contexts_for_userid($stranger->id));
    }

    /**
     * Being named on an issue or a forum is enough to have data here.
     *
     * Neither leaves a row keyed by user id behind, so they are easy to overlook.
     */
    public function test_a_reference_alone_counts_as_data(): void {
        global $DB;

        $manager = $this->getDataGenerator()->create_user();
        $dedicated = $this->getDataGenerator()->create_user();
        $DB->set_field('local_edusupport_issues', 'accountmanager', $manager->id, ['id' => $this->issue->id]);
        $DB->set_field('local_edusupport', 'dedicatedsupporter', $dedicated->id, ['id' => $this->supportforum->id]);

        $this->assertCount(1, provider::get_contexts_for_userid($manager->id));
        $this->assertCount(1, provider::get_contexts_for_userid($dedicated->id));
    }

    /**
     * The person owning a user context is its only user; other contexts have nobody.
     */
    public function test_get_users_in_context(): void {
        $userlist = new userlist(context_user::instance($this->supporter->id), 'local_edusupport');
        provider::get_users_in_context($userlist);
        $this->assertEquals([$this->supporter->id], $userlist->get_userids());

        $userlist = new userlist(context_system::instance(), 'local_edusupport');
        provider::get_users_in_context($userlist);
        $this->assertCount(0, $userlist);
    }

    /**
     * The export holds each kind of data once per entry, and nothing else.
     */
    public function test_export_user_data(): void {
        $this->export_all_data_for_user($this->supporter->id, 'local_edusupport');
        $writer = writer::with_context(context_user::instance($this->supporter->id));
        $root = get_string('pluginname', 'local_edusupport');

        $supporter = $writer->get_data([$root, get_string('privacy:export:supporter', 'local_edusupport')]);
        $this->assertEqualsCanonicalizing(
            [get_string('level:first', 'local_edusupport'), get_string('level:second', 'local_edusupport')],
            array_column($supporter->entries, 'level')
        );

        $issues = $writer->get_data([$root, get_string('privacy:export:issues', 'local_edusupport')]);
        $this->assertEqualsCanonicalizing(
            [$this->issue->id, $this->otherissue->id],
            array_column($issues->entries, 'issueid')
        );

        $subscriptions = $writer->get_data([$root, get_string('privacy:export:subscriptions', 'local_edusupport')]);
        $this->assertNotContains(null, $subscriptions->entries);
        $this->assertContains($this->issue->id, array_column($subscriptions->entries, 'issueid'));

        $forums = $writer->get_data([$root, get_string('privacy:export:dedicated', 'local_edusupport')]);
        $this->assertEquals([$this->supportforum->forumid], array_column($forums->entries, 'forumid'));
    }

    /**
     * Somebody else's export only shows what concerns them.
     */
    public function test_export_leaves_out_other_people(): void {
        $this->export_all_data_for_user($this->colleague->id, 'local_edusupport');
        $writer = writer::with_context(context_user::instance($this->colleague->id));
        $root = get_string('pluginname', 'local_edusupport');

        $issues = $writer->get_data([$root, get_string('privacy:export:issues', 'local_edusupport')]);
        $this->assertEquals([$this->otherissue->id], array_column($issues->entries, 'issueid'));
        $this->assertEmpty($writer->get_data([$root, get_string('privacy:export:dedicated', 'local_edusupport')]));
    }

    /**
     * The three ways the privacy API asks for a deletion.
     *
     * @return array
     */
    public static function deletion_paths(): array {
        return [
            'one user in their contexts' => ['user'],
            'several users in one context' => ['users'],
            'everybody in one context' => ['context'],
        ];
    }

    /**
     * Every way of deleting forgets the person completely and leaves everybody else alone.
     *
     * Only one of the three used to take the person off their issues, and it did so by
     * writing -1 where the rest of the plugin reads 0 as "nobody".
     *
     * @dataProvider deletion_paths
     * @param string $path
     */
    public function test_deletion_forgets_the_person_and_nobody_else(string $path): void {
        global $DB;

        $context = context_user::instance($this->supporter->id);
        switch ($path) {
            case 'user':
                provider::delete_data_for_user(new approved_contextlist($this->supporter, 'local_edusupport', [$context->id]));
                break;
            case 'users':
                provider::delete_data_for_users(new approved_userlist($context, 'local_edusupport', [$this->supporter->id]));
                break;
            default:
                provider::delete_data_for_all_users_in_context($context);
        }

        $this->assertCount(0, provider::get_contexts_for_userid($this->supporter->id));
        $this->assertEquals(0, $DB->get_field('local_edusupport_issues', 'currentsupporter', ['id' => $this->issue->id]));
        $this->assertEquals(0, $DB->get_field('local_edusupport_issues', 'accountmanager', ['id' => $this->otherissue->id]));
        $this->assertEquals(0, $DB->get_field('local_edusupport', 'dedicatedsupporter', ['id' => $this->supportforum->id]));
        $this->assertSame((string) $this->colleague->id, get_config('local_edusupport', 'accountmanagers'));

        $this->assertTrue($DB->record_exists('local_edusupport_supporters', ['userid' => $this->colleague->id]));
        $this->assertTrue($DB->record_exists('local_edusupport_subscr', ['userid' => $this->colleague->id]));
        $this->assertEquals(
            $this->colleague->id,
            $DB->get_field('local_edusupport_issues', 'currentsupporter', ['id' => $this->otherissue->id])
        );
    }

    /**
     * Requests for other contexts or other people do not touch the person.
     */
    public function test_deletion_outside_the_person_does_nothing(): void {
        provider::delete_data_for_all_users_in_context(context_system::instance());
        provider::delete_data_for_users(new approved_userlist(
            context_user::instance($this->supporter->id),
            'local_edusupport',
            [$this->colleague->id]
        ));

        $this->assertCount(1, provider::get_contexts_for_userid($this->supporter->id));
        $this->assertCount(1, provider::get_contexts_for_userid($this->colleague->id));
    }
}
