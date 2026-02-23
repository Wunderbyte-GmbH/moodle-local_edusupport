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
 * Library for the edusupport plugin.
 *
 * @package    local_edusupport
 * @copyright  2020 Center for Learningmanagement (www.lernmanagement.at)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Extend navigation.
 *
 * @param navigation_node $navigation The navigation node to extend
 */
function local_edusupport_extend_navigation($navigation) {
    if (\local_edusupport\lib::is_supportteam()) {
        $nodehome = $navigation->get('home');
        if (empty($nodehome)) {
            $nodehome = $navigation;
        }
        $label = get_string('issues', 'local_edusupport');
        $link = new moodle_url('/local/edusupport/issues.php', []);
        $icon = new pix_icon('docs', '', '');
        $nodecreatecourse = $nodehome->add($label, $link, navigation_node::NODETYPE_LEAF, $label, 'edusupportissues', $icon);
        $nodecreatecourse->showinflatnavigation = true;
    }
}

/**
 * Extend course navigation with edusupport nodes.
 *
 * @param navigation_node $parentnode The navigation node to add items to
 * @param stdClass $course The course object
 * @param context $context The context object
 * @return void
 */
function local_edusupport_extend_navigation_course($parentnode, $course, $context) {
    // If we allow support users on course level, we can remove the next line.
    if (!is_siteadmin()) {
        return;
    }
    // phpcs:ignore Squiz.PHP.CommentedOutCode.Found
    /* $coursecontext = \context_course::instance($course->id);
    if (!has_capability('local/edusupport:canforward2ndlevel', $coursecontext)) return; */

    // We want to add these new nodes after the Edit settings node, and before the
    // Locally assigned roles node. Of course, both of those are controlled by capabilities.
    $keys = $parentnode->get_children_key_list();

    $beforekey = null;
    $i = array_search('modedit', $keys);
    if (($i === false) && array_key_exists(0, $keys)) {
        $beforekey = $keys[0];
    } else if (array_key_exists($i + 1, $keys)) {
        $beforekey = $keys[$i + 1];
    }

    if (is_siteadmin()) {
        $url = '/local/edusupport/chooseforum.php';
        $node = navigation_node::create(
            get_string('supportforum:choose', 'local_edusupport'),
            new moodle_url($url, ['courseid' => $course->id]),
            navigation_node::TYPE_SETTING,
            null,
            'advancedsettings',
            new pix_icon('i/marker', 'eduSupport')
        );
        $parentnode->add_node($node, $beforekey);
    }
    // phpcs:ignore Squiz.PHP.CommentedOutCode.Found
    /*
    // This is prepared for later use, if we allow support users on course level.
    $url = '/local/edusupport/choosesupporters.php';
    $node = navigation_node::create(get_string('supporters:choose', 'local_edusupport'),
        new moodle_url($url, array('courseid' => $course->id)),
        navigation_node::TYPE_SETTING, null, 'advancedsettings',
        new pix_icon('t/eye', ''));
    $parentnode->add_node($node, $beforekey);
    */
}

/**
 * Serves the forum attachments. Implements needed access control ;-)
 *
 * @package  local_edusupport --> we fake downloads for mod_forum.
 * @category files
 * @param stdClass $course course object
 * @param stdClass $cm course module object
 * @param stdClass $context context object
 * @param string $filearea file area
 * @param array $args extra arguments
 * @param bool $forcedownload whether or not force download
 * @param array $options additional options affecting the file serving
 * @return bool false if file not found, does not return if found - justsend the file
 */
function local_edusupport_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    global $CFG, $DB, $USER;
    require_once($CFG->dirroot . '/local/edusupport/classes/lib.php');
    require_once($CFG->dirroot . '/mod/forum/lib.php');

    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }

    // Instead of requiring course login we check if the current user is support user of this discussion!
    // phpcs:ignore Squiz.PHP.CommentedOutCode.Found
    /* require_course_login($course, true, $cm); */
    if (!\local_edusupport\lib::is_supportteam($USER->id, $course->id)) {
        return false;
    }

    $postid = (int)array_shift($args);
    if (!$post = $DB->get_record('forum_posts', ['id' => $postid])) {
        return false;
    }

    $areas = \forum_get_file_areas($course, $cm, $context);

    // Filearea must contain a real area.
    if (!isset($areas[$filearea])) {
        return false;
    }

    if (!$discussion = $DB->get_record('forum_discussions', ['id' => $post->discussion])) {
        return false;
    }

    if (!\local_edusupport\lib::is_supportforum($discussion->forum)) {
        return false;
    }

    $fs = \get_file_storage();
    $relativepath = implode('/', $args);
    $fullpath = "/$context->id/mod_forum/$filearea/$postid/$relativepath";
    if (!$file = $fs->get_file_by_hash(sha1($fullpath)) || $file->is_directory()) {
        return false;
    }

    // We skip this check, we already checked, that we belong to the supportteam and have access.
    // Make sure groups allow this user to see this file.
    // phpcs:ignore Squiz.PHP.CommentedOutCode.Found
    /*
    if ($discussion->groupid > 0) {
        $groupmode = \groups_get_activity_groupmode($cm, $course);
        if ($groupmode == SEPARATEGROUPS) {
            if (!\groups_is_member($discussion->groupid) and !has_capability('moodle/site:accessallgroups', $context)) {
                return false;
            }
        }
    }
    */

    // We skip this check, we already checked, that we belong to the supportteam and have access.
    // Make sure we're allowed to see it...
    // phpcs:ignore Squiz.PHP.CommentedOutCode.Found
    /*
    if (!forum_user_can_see_post($forum, $discussion, $post, NULL, $cm)) {
        return false;
    }
    */

    // Finally send the file.
    send_stored_file($file, 0, 0, true, $options); // Download MUST be forced - security!
    return true;
}

/**
 * If a course category was deleted we remove all contained support forums.
 * @param category the course category.
 */
function local_edusupport_pre_course_category_delete($category) {
    global $DB;
    $courses = $DB->get_records('course', ['category' => $category->id]);
    foreach ($courses as $course) {
        local_edusupport_pre_course_delete($course);
    }
}

/**
 * If a course was deleted we remove all contained support forums.
 * @param course the course.
 */
function local_edusupport_pre_course_delete($course) {
    global $DB;
    $supportforums = $DB->get_records('local_edusupport', ['courseid' => $course->id]);
    foreach ($supportforums as $supportforum) {
        \local_edusupport\lib::supportforum_disable($supportforum->id);
    }
}
/**
 * If a forum was deleted we remove it as support forum.
 * @param cm the course module.
 */
function local_edusupport_pre_course_module_delete($cm) {
    global $DB;
    $forumtype = $DB->get_record('modules', ['name' => 'forum']);
    if (!empty($forumtype->id) && !empty($cm->module) && $cm->module == $forumtype->id) {
        \local_edusupport\lib::supportforum_disable($cm->instance);
    }
}

/**
 * Renders the popup.
 *
 * @param renderer_base $renderer
 * @return string The HTML
 */
function local_edusupport_render_navbar_output(\renderer_base $renderer) {
    $guestmode = get_config('local_edusupport', 'guestmodeenabled');
    // Early bail out conditions.
    if (!isloggedin()  && !$guestmode) {
        return '';
    }
    if (isguestuser() && !$guestmode) {
        return '';
    }
    return  \local_edusupport\lib::get_supportmenu();
}
