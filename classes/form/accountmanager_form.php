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
 * File containing the form definition to post in the forum.
 * THIS IS A CLONE OF THE STANDARD FORM, THAT IS MODIFIED A LITTLE
 * FOR THIS PLUGIN.
 *
 * @package   local_edusuport
 * @copyright Thomas winkler
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


namespace local_edusupport\form;
require_once($CFG->libdir . '/formslib.php');
use moodleform;



defined('MOODLE_INTERNAL') || die();

/**
 * Class to add an accountmanger in edusupport plugin.
 *
 * @package   local_edusupport
 * @copyright Thomas Winkler
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class accountmanager_form extends moodleform {
    

    public function __construct() {
        parent::__construct();
    }
    /**
     * Form definition
     *
     * @return void
     */
    function definition() {
        global $CFG, $OUTPUT;

        $mform =& $this->_form;
        $possiblemanagers = \local_edusupport\accountmanager::get_all_category_managers_from_site();

        $options = [
            'multiple' => true,
            'noselectionstring' => get_string('none', 'local_edusupport'),
        ];
        $mform->addElement('autocomplete', 'possiblemanagers', get_string('possiblemanagers', 'local_edusupport'), $possiblemanagers, $options);
        $mform->setAdvanced('autocomplete', true);


        $capstocheck = \local_edusupport\accountmanager::get_capabiltities_to_check();

        $options = [
            'multiple' => true,
            'noselectionstring' => get_string('none', 'local_edusupport'),
        ];
        $mform->addElement('autocomplete', 'capstocheck', get_string('capstocheck', 'local_edusupport'), $capstocheck, $options);
        $mform->setAdvanced('autocomplete', true);

        $this->add_action_buttons();
        
    }

    /**
     * Form validation
     *
     * @param array $data data from the form.
     *
     * @return array of errors.
     */
    function validation($data, $files) {
        $errors = parent::validation($data, $files);
        return $errors;
    }


    public function set_data($defaults) {
        $currentaccountmanagers = explode(',', get_config('local_edusupport', 'accountmanagers'));
        $defaults->possiblemanagers = $currentaccountmanagers;
        $currentcaps = explode(',', get_config('local_edusupport', 'capstocheck'));
        $defaults->capstocheck = $currentcaps;
        return parent::set_data($defaults);
    }

}
