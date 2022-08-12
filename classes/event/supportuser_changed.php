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
 * The mod_forum discussion created event.
 *
 * @package    mod_forum
 * @copyright  2014 Dan Poltawski <dan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_edusupport\event;

defined('MOODLE_INTERNAL') || die();

/**
 * The mod_forum discussion created event class.
 *
 * @property-read array $other {
 *      Extra information about the event.
 *
 *      - int forumid: The id of the forum the discussion is in.
 * }
 *
 * @package    local_edusupport
 * @copyright  2022 Thomas Winkler Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class supportuser_changed extends \core\event\base {
    /**
     * Init method.
     *
     * @return void
     */
    protected function init() {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'local_edusupport_supporters';
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '$this->userid' has changed supporterid '" . $this->other['oldsupportuserid'] . "' to '" . $this->other['newsupportuserid'] .
         ".' Supportlevel: '" . $this->other['oldsupportlevel'] . "' to '"  . $this->other['newsupportlevel'] . "'";
    }

    /**
     * Return localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('supportchanged', 'local_edusupport');
    }


    /**
     * Return the legacy event log data.
     *
     * @return array|null
     */
    protected function get_legacy_logdata() {

        return null;
    }

    /**
     * Custom validation.
     *
     * @throws \coding_exception
     * @return void
     */
    protected function validate_data() {
        parent::validate_data();

        if (!isset($this->other['oldsupportuserid'])) {
            throw new \coding_exception('The \'oldsupportuserid\' value must be set in other.');
        }
        if (!isset($this->other['newsupportuserid'])) {
            throw new \coding_exception('The \'newsupportuserid\' value must be set in other.');
        }
        if (!isset($this->other['oldsupportlevel'])) {
            throw new \coding_exception('The \'oldsupportlevel\' value must be set in other.');
        }
        if (!isset($this->other['newsupportlevel'])) {
            throw new \coding_exception('The \'oldsupportlevel\' value must be set in other.');
        }
    }

    public static function get_objectid_mapping() {
        return \core\event\base::NOT_MAPPED;
    }

    public static function get_other_mapping() {
        $othermapped = array();
        $othermapped['someid'] = \core\event\base::NOT_MAPPED;
        return $othermapped;
    }
}
