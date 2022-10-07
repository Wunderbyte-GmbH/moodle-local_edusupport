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
 * @copyright  2018 Digital Education Society (http://www.dibig.at)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
*/

defined('MOODLE_INTERNAL') || die;

if ($hassiteconfig) {
    $settings = new admin_settingpage( 'local_edusupport_settings', get_string('pluginname', 'local_edusupport'));
    $ADMIN->add('localplugins', $settings);

    // Possibly we changed the menu, therefore we delete the cache. We should find a better place for this.
    $cache = cache::make('local_edusupport', 'supportmenu');
    $cache->delete('rendered');

    $settings->add(
        new admin_setting_configtextarea(
            'local_edusupport/extralinks',
            get_string('extralinks', 'local_edusupport'),
            get_string('extralinks:description', 'local_edusupport'),
            '',
            PARAM_TEXT
        )
    );
    $settings->add(
        new admin_setting_configcheckbox(
            'local_edusupport/trackhost',
            get_string('trackhost', 'local_edusupport'),
            get_string('trackhost:description', 'local_edusupport'),
            1
        )
    );

    // FAQ read.
    $settings->add(
        new admin_setting_configcheckbox(
            'local_edusupport/faqread',
            get_string('faqread', 'local_edusupport'),
            '',
            1
        )
    );

    // FAQ Link.
    $settings->add(
        new admin_setting_configtext(
            'local_edusupport/faqlink',
            get_string('faqlink', 'local_edusupport'),
            get_string('faqlink:description', 'local_edusupport'),
            ''
        )
    );

    // Disable User Profile Links.
    $settings->add(
        new admin_setting_configcheckbox(
            'local_edusupport/userlinks',
            get_string('userlinks', 'local_edusupport'),
            get_string('userlinks:description', 'local_edusupport'),
            1
        )
    );

    // Priority LVL.
    $settings->add(
        new admin_setting_configcheckbox(
            'local_edusupport/prioritylvl',
            get_string('prioritylvl', 'local_edusupport'),
            get_string('prioritylvl:description', 'local_edusupport'),
            1
        )
    );

    // Disable Telephone Link.
    $settings->add(
        new admin_setting_configcheckbox(
            'local_edusupport/phonefield',
            get_string('phonefield', 'local_edusupport'),
            get_string('phonefield:description', 'local_edusupport'),
            1
        )
    );

    // Delete threshhold.
    $settings->add(
        new admin_setting_configduration(
            'local_edusupport/deletethreshhold',
            get_string('deletethreshhold', 'local_edusupport'),
            get_string('deletethreshhold:description', 'local_edusupport'),
            4 * WEEKSECS)
    );

    $settings->add(
        new admin_setting_configcheckbox(
            'local_edusupport/auto2ndlvl',
            get_string('auto2ndlvl', 'local_edusupport'),
            get_string('auto2ndlvl:description', 'local_edusupport'),
            0
        )
    );

    $settings->add(
        new admin_setting_configtextarea(
            'local_edusupport/predefined_subjects',
            get_string('predefined_subjects', 'local_edusupport'),
            get_string('predefined_subjects:description', 'local_edusupport'),
            '',
            PARAM_TEXT
        )
    );

    $settings->add(
        new admin_setting_configcheckbox(
            'local_edusupport/predefined_subjects_prefix',
            get_string('predefined_subjects_prefix', 'local_edusupport'),
            get_string('predefined_subjects_prefix:description', 'local_edusupport'),
            0
        )
    );

    // Prepage before form
    $settings->add(
        new admin_setting_configcheckbox(
            'local_edusupport/enableprepage',
            get_string('enableprepage', 'local_edusupport'),
            get_string('enableprepage:description', 'local_edusupport'),
            0
        )
    );

    $settings->add(
        new admin_setting_configtextarea(
            'local_edusupport/prepage',
            get_string('prepage', 'local_edusupport'),
            get_string('prepage:description', 'local_edusupport'),
            '',
            PARAM_RAW
        )
    );

    // Prepage before form
    $settings->add(
        new admin_setting_configcheckbox(
            'local_edusupport/firstlvlgroupmode',
            get_string('firstlvlgroupmode', 'local_edusupport'),
            get_string('firstlvlgroupmode:description', 'local_edusupport'),
            0
        )
    );

    $settings->add(
        new admin_setting_configtext(
            'local_edusupport/customfieldname',
            get_string('customfieldname', 'local_edusupport'),
            get_string('customfieldname:description', 'local_edusupport'),
            ''
        )
    );

    $settings->add(
        new admin_setting_configtext(
            'local_edusupport/rolename',
            get_string('rolename', 'local_edusupport'),
            get_string('rolename:description', 'local_edusupport'),
            ''
        )
    );

    $settings->add(
        new admin_setting_configcheckbox(
            'local_edusupport/holidaymodeenabled',
            get_string('holidaymodeenabled', 'local_edusupport'),
            get_string('holidaymodeenabled:description', 'local_edusupport'),
            0
        )
    );
    $settings->add(
        new admin_setting_configcheckbox(
            'local_edusupport/guestmodeenabled',
            get_string('guestmodeenabled', 'local_edusupport'),
            get_string('guestmodeenabled:description', 'local_edusupport'),
            0
        )
    );

    $options = [
        0 => get_string('inactive'),
        60 => "1 " . get_string('minute'),
        600 => "10 " . get_string('minutes'),
        3600 => "60 " . get_string('minutes')
    ];

    $settings->add(
        new admin_setting_configselect(
            'local_edusupport/spamprotectionthreshold',
            get_string('spamprotection:threshold', 'local_edusupport'),
            get_string('spamprotection:threshold:description', 'local_edusupport'),
            600,
            $options)
    );

    $settings->add(
        new admin_setting_configselect(
            'local_edusupport/spamprotectionlimit',
            get_string('spamprotection:limit', 'local_edusupport'),
            get_string('spamprotection:limit:description', 'local_edusupport'),
            5,
            [ 1 => 1, 2 => 2, 5 => 5, 10 => 10, 20 => 20])
    );

    $settings->add(
        new admin_setting_configcheckbox(
            'local_edusupport/sendreminders',
             get_string('cron:reminder:title', 'local_edusupport'),
              '',
               0)
    );

    $settings->add(new admin_setting_configduration('local_edusupport/timebeforereminder',
    get_string('timebeforereminder', 'local_edusupport'), '', 2, 86400));

    $actions = array(
        (object) array('name' => 'supporters', 'href' => 'choosesupporters.php'),
        (object) array('name' => 'setaccountmanager', 'href' => 'accountmanager.php'),
    );
    $links = "<div class=\"grid-eq-3\">";
    foreach($actions AS $action) {
        $links .= '<a class="btn btn-secondary" href="' . $CFG->wwwroot . '/local/edusupport/' . $action->href . '">' .
                        '<i class="fa fa-users"></i> ' .
                        get_string($action->name, 'local_edusupport') .
                  '</a>';
    }
    $links .= "</div>";
    $settings->add(new admin_setting_heading('local_edusupport_actions', get_string('settings'), $links));
}
