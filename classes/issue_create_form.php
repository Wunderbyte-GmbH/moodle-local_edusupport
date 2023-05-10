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
 * @package    block_edupublisher
 * @copyright  2018 Digital Education Society (http://www.dibig.at)
 * @copyright  2020 Center for Learningmanagement (www.lernmanagement.at)
 * @author     Robert Schrenk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_edusupport\accountmanager;
use local_wb_faq\wb_faq;

defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir . "/formslib.php");

class issue_create_form extends moodleform {
    public $maxbytes = 1024 * 1024;
    public $areamaxbytes = 10485760;
    public $maxfiles = 1;
    public $subdirs = 0;

    public function definition() {
        global $CFG, $COURSE, $SITE;

        $faqread = get_config('local_edusupport', 'faqread');
        $faqlink = get_config('local_edusupport', 'faqlink');
        $prioritylvl = get_config('local_edusupport', 'prioritylvl');
        $disablephonefield = get_config('local_edusupport', 'phonefield');
        $guestuserallowed = true; // Do we need 'guestuserallowed' from get_config?

        $editoroptions = array('subdirs' => 0, 'maxbytes' => 0, 'maxfiles' => 0,
                               'changeformat' => 0, 'context' => null, 'noclean' => 0,
                               'trusttext' => 0, 'enable_filemanagement' => false);

        $mform = $this->_form;

        $mform->addElement('hidden', 'id', 0);
        $mform->setType('id', PARAM_INT);

        // TODO: Obsolete forumid remove in the future.
        $mform->addElement('hidden', 'forumid', '');
        $mform->setType('forumid', PARAM_INT);

        $mform->addElement('hidden', 'url', '');
        $mform->setType('url', PARAM_TEXT);
        $mform->addElement('hidden', 'image', ''); // Base64 encoded image.
        $mform->setType('image', PARAM_RAW);

        $mform->addElement('header', 'header', get_string('header', 'local_edusupport', $COURSE->fullname));

        if ($faqread) {
            $mform->addElement('checkbox', 'faqread', '', get_string('faqread:description', 'local_edusupport', $faqlink));
            $mform->setType('faqread', PARAM_BOOL);
            $mform->addRule('faqread', get_string('subject_missing', 'local_edusupport'), 'required', true, 'server');
        } else {
            $mform->addElement('html', '<input type="checkbox" id="id_faqread" class="autochecked" ' .
                'style="display: none;" checked="checked" />');
        }

        $mform->addElement('html', '<div id="create_issue_input">');

        require_once($CFG->dirroot . '/local/edusupport/classes/lib.php');

        $potentialtargets = \local_edusupport\lib::get_potentialtargets();

        $hideifs = array('mail');

        // If there are not potentialtargets we don't care. We will send a mail to the Moodle default support contact.
        $options = array();
        foreach ($potentialtargets as $pt) {
            if (empty($pt->potentialgroups) || count($pt->potentialgroups) == 0) {
                $options[$pt->id . '_0'] = $pt->name;
                if (empty($pt->postto2ndlevel)) {
                    $hideifs[] = $pt->id . '_0';
                }
            } else {
                foreach ($pt->potentialgroups as $group) {
                    $options[$pt->id . '_' . $group->id] = $pt->name . ' > ' . $group->name;
                    if (empty($pt->postto2ndlevel)) {
                        $hideifs[] = $pt->id . '_' . $group->id;
                    }
                }
            }
        }
        if (count($potentialtargets) == 0) {
            $supportuser = \core_user::get_support_user();
            $options['mail'] = get_string('email_to_xyz', 'local_edusupport', (object) array('email' => $supportuser->email));
        }

        $hideifs = '["' . implode('","', $hideifs) . '"]';
        $postto2ndlevelhideshow = [
            'require([\'jquery\'], function($) {',
                'var val = $(\'#id_forum_group\').val();',
                '$(\'.edusupport_label\').addClass(\'hidden\');',
                '$(\'#edusupport_label_\' + val).removeClass(\'hidden\');',
                'var hide = (' . $hideifs . '.indexOf(val) > -1);',
                'var pt2 = $(\'#id_postto2ndlevel\');',
                '$(pt2).prop(\'checked\', false);',
                '$(pt2).closest(\'div.form-group\').css(\'display\', hide ? \'none\' : \'block\');',
            '});'
        ];
        $mform->addElement('select', 'forum_group', get_string('to_group', 'local_edusupport'), $options,
            array('onchange' => implode("", $postto2ndlevelhideshow)));
        $mform->setType('forum_group', PARAM_INT);

        if (class_exists('local_wb_faq\wb_faq')) {
            wb_faq::add_form_elements($mform);
        }

        if (!empty($usesubjects = get_config('local_edusupport', 'predefined_subjects'))) {
            $options = ['' => ''];
            $options += explode(PHP_EOL, $usesubjects);
            $options = array_combine($options, $options);
            $mform->addElement('select', 'subject', get_string('subject', 'local_edusupport'), $options,
                array('style' => 'width: 100%;'));
            $mform->setType('subject', PARAM_TEXT);
            $mform->addRule('subject', get_string('subject_missing', 'local_edusupport'), 'required', null, 'server');
        } else {
            $mform->addElement('text', 'subject', get_string('subject', 'local_edusupport'),
                array('style' => 'width: 100%;', 'type' => 'tel'));
            $mform->setType('subject', PARAM_TEXT);
            $mform->addRule('subject', get_string('subject_missing', 'local_edusupport'), 'required', null, 'server');
        }

        if (!$disablephonefield) {
            $mform->addElement('text', 'contactphone', get_string('contactphone', 'local_edusupport'),
                array('style' => 'width: 100%;'));
        } else {
            $mform->addElement('hidden', 'contactphone', '');
        }
        $mform->setType('contactphone', PARAM_TEXT);

        if ((isguestuser() || !isloggedin()) && $guestuserallowed) {
            $mform->addElement('text', 'guestmail', get_string('guestmail', 'local_edusupport'), array('style' => 'width: 100%;'));
            $mform->setType('guestmail', PARAM_EMAIL);
            $mform->addRule('guestmail', get_string('mail_missing', 'local_edusupport'), 'required', null, 'server');
        }

        // Accountmanager select.
        $am = new accountmanager;
        $am->prepare_accountmanager_for_form($mform);

        $mform->addElement('textarea', 'description', get_string('description', 'local_edusupport'),
            array('style' => 'width: 100%;', 'rows' => 10));
        $mform->setType('description', PARAM_RAW);
        $mform->addRule('description', get_string('description_missing', 'local_edusupport'), 'required', null, 'server');

        $mform->addElement('checkbox', 'postto2ndlevel', '', get_string('postto2ndlevel:description', 'local_edusupport',
            array('sitename' => $SITE->fullname)));
        $mform->setType('postto2ndlevel', PARAM_BOOL);
        $mform->setDefault('postto2ndlevel', 0);

        $fileupload = [
            '<div class="form-group row fitem">',
            ' <div class="col-md-3">' . get_string('screenshot', 'local_edusupport') . '</div>',
            ' <div class="col-md-9" id="edusupport_screenshot">',
            '  <input type="file" onchange="require([\'local_edusupport/main\'], function(M) { M.uploadScreenshot(); });" /><br />',
            '  <div class="alert alert-danger hidden">' . get_string('screenshot:upload:failed', 'local_edusupport') . '</div>',
            '  <div class="alert alert-success hidden">' . get_string('screenshot:upload:successful', 'local_edusupport') .
                '</div>',
            ' </div>',
            '</div>'
        ];
        $mform->addElement('html', implode("\n", $fileupload));
        $mform->addElement('html', '<script> setTimeout(function() { ' . implode('', $postto2ndlevelhideshow) .
            ' }, 100);</script>');

        $mform->addElement('html', '</div>');
    }

    // Custom validation should be added here.
    public function validation($data, $files) {
        $errors = array();
        return $errors;
    }

    public function return_priority_options() {
        return [
            "" => get_string('prioritylvl:low', 'local_edusupport'),
            "!" => get_string('prioritylvl:mid', 'local_edusupport'),
            "!!" => get_string('prioritylvl:high', 'local_edusupport'),
        ];
    }
}
