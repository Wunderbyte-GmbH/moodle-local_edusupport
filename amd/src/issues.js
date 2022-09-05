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

/*
 * @package    local_edusupport
 * @copyright  Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';

var alltr = Array.from(document.querySelectorAll('tr.issue'));
var checked = {};

export const init = () => {

    var allCheckboxes = document.querySelectorAll('#issuefilter input[type=checkbox]');
   

    getChecked('statefilter');


    Array.prototype.forEach.call(allCheckboxes, function (el) {
    el.addEventListener('change', toggleCheckbox);
    });
    document.querySelectorAll('.changeStatusSelect').forEach(function(status) {
        status.addEventListener('change', function() {
           setStatus(status.value, status.dataset.issueid);
        });
   });
}

export const setStatus = (status, issueid) => {
        console.log(status);
        Ajax.call([{
        methodname: "local_edusupport_set_status",
        args: { status: status, 
                issueid: issueid,
        },
            done: function(data) {
                location.reload();
            },
            fail: function(ex) {
                // eslint-disable-next-line no-console
                console.log("ex:" + ex);
            },
        }]);
};


// not exactly vanilla as there is one lodash function



export const toggleCheckbox = (e)  => {
  getChecked(e.target.name);
  setVisibility();
}

export const getChecked = (name)  => {
  checked[name] = Array.from(document.querySelectorAll('input[name=' + name + ']:checked')).map(function (el) {
    return el.value;
  });
}

export const setVisibility = () => {
  alltr.map(function (el) {
    var statefilter = checked.statefilter.length ? 
(Array.from(el.classList).filter(value => checked.statefilter.includes(value))).length : true;
    if (statefilter) {
      el.style.display = 'table-row';
    } else {
      el.style.display = 'none';
    }
  });
}
