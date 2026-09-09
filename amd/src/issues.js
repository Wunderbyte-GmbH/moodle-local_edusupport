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
 * Filtering and status handling on the issue overview.
 *
 * @module     local_edusupport/issues
 * @copyright  Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Notification from 'core/notification';

const alltr = Array.from(document.querySelectorAll('tr.issue'));
const checked = {};

/**
 * Wire up the state filter and the status selects.
 *
 * @returns {void}
 */
export const init = () => {
    const allCheckboxes = document.querySelectorAll('#issuefilter input[type=checkbox]');

    getChecked('statefilter');
    Array.prototype.forEach.call(allCheckboxes, function(el) {
        el.addEventListener('change', toggleCheckbox);
    });
    document.querySelectorAll('.changeStatusSelect').forEach(function(status) {
        status.addEventListener('change', function() {
            setStatus(status.value, status.dataset.issueid);
        });
    });
};

/**
 * Store a new status for an issue and reload the page.
 *
 * @param {string} status the status to set.
 * @param {number} issueid the issue to set it for.
 * @returns {void}
 */
export const setStatus = (status, issueid) => {
    Ajax.call([{
        methodname: 'local_edusupport_set_status',
        args: {
            status: status,
            issueid: issueid,
        },
        done: function() {
            location.reload();
        },
        fail: Notification.exception,
    }]);
};

/**
 * Remember which boxes of a filter are ticked and apply the filter.
 *
 * @param {Event} e the change event of the checkbox.
 * @returns {void}
 */
export const toggleCheckbox = (e) => {
    getChecked(e.target.name);
    setVisibility();
};

/**
 * Remember which boxes of a filter are ticked.
 *
 * @param {string} name the name of the checkbox group.
 * @returns {void}
 */
export const getChecked = (name) => {
    checked[name] = Array.from(document.querySelectorAll('input[name=' + name + ']:checked'))
        .map(function(el) {
            return el.value;
        });
};

/**
 * Show only the issues matching the current filter.
 *
 * @returns {void}
 */
export const setVisibility = () => {
    alltr.forEach(function(el) {
        const statefilter = checked.statefilter.length
            ? Array.from(el.classList).filter(value => checked.statefilter.includes(value)).length
            : true;
        el.style.display = statefilter ? 'table-row' : 'none';
    });
};
