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
 * Behat data generator for local_edusupport.
 *
 * @package    local_edusupport
 * @category   test
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Behat data generator for local_edusupport.
 *
 * This only maps entity names to the methods of local_edusupport_generator, so that
 * the core step "the following ... exist:" can set up support forums, supporters and
 * issues. It defines no step of its own.
 *
 * @package    local_edusupport
 * @category   test
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_local_edusupport_generator extends behat_generator_base {
    /**
     * Get a list of the entities that can be created for local_edusupport.
     *
     * @return array entity name => information about how to generate.
     */
    protected function get_creatable_entities(): array {
        return [
            'supportforums' => [
                'singular' => 'supportforum',
                'datagenerator' => 'supportforum',
                'required' => ['forum'],
                'switchids' => ['forum' => 'forumid'],
            ],
            'supporters' => [
                'singular' => 'supporter',
                'datagenerator' => 'supporter',
                'required' => ['user'],
                'switchids' => ['user' => 'userid', 'course' => 'courseid'],
            ],
            'issues' => [
                'singular' => 'issue',
                'datagenerator' => 'issue',
                'required' => ['forum'],
                'switchids' => ['forum' => 'forumid', 'user' => 'userid', 'supporter' => 'currentsupporter'],
            ],
        ];
    }

    /**
     * Look up a forum by its name.
     *
     * @param string $name the forum name.
     * @return int the forum id.
     */
    protected function get_forum_id(string $name): int {
        global $DB;

        if (!$id = $DB->get_field('forum', 'id', ['name' => $name])) {
            throw new Exception('There is no forum called "' . $name . '".');
        }
        return $id;
    }

    /**
     * Look up a supporter by username, so an issue can be assigned to them.
     *
     * @param string $username the username.
     * @return int the user id.
     */
    protected function get_supporter_id(string $username): int {
        return $this->get_user_id($username);
    }
}
