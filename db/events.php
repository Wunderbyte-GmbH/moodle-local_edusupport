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
 * @copyright  2020 Center for Learningmanagement (https://www.lernmanagement.at)
 * @author     Robert Schrenk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

$observers = [];

// We should have separate functions for different event types for cleaner code and better readability!

$events = [
    "\\mod_forum\\event\\discussion_created",
    "\\mod_forum\\event\\discussion_deleted",
    "\\mod_forum\\event\\post_created",
    "\\core\\event\\user_deleted",
    "\\local\\edusupport\add_supportuser",
];

foreach ($events as $event) {
    $observers[] = [
            'eventname' => $event,
            'callback' => '\local_edusupport\observer::event',
        ];
}

// For new events, we do it separately.

$observers[] = [
    'eventname' => '\local_edusupport\event\supportuser_added',
    'callback' => '\local_edusupport\observer::supportuser_added',
];

$observers[] = [
    'eventname' => '\local_edusupport\event\supportuser_changed',
    'callback' => '\local_edusupport\observer::supportuser_changed',
];

$observers[] = [
    'eventname' => '\local_edusupport\event\supportuser_deleted',
    'callback' => '\local_edusupport\observer::supportuser_deleted',
];

$observers[] = [
    'eventname' => '\core\event\user_deleted',
    'callback' => '\local_edusupport\observer::user_deleted',
];
