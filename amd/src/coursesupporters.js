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
 * Assign the first level support of a course without leaving the page.
 *
 * @module     local_edusupport/coursesupporters
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import ModalForm from 'core_form/modalform';
import Notification from 'core/notification';
import Templates from 'core/templates';
import {getString} from 'core/str';

const SELECTORS = {
    trigger: '[data-action="assign-coursesupporters"]',
    list: '[data-region="coursesupporters"]',
};

/**
 * Wire the assign button up to the form.
 *
 * @param {number} courseid the course to assign support for.
 * @returns {void}
 */
export const init = (courseid) => {
    const trigger = document.querySelector(SELECTORS.trigger);
    if (!trigger) {
        return;
    }

    trigger.addEventListener('click', (event) => {
        event.preventDefault();

        const form = new ModalForm({
            formClass: 'local_edusupport\\form\\course_supporters_form',
            args: {courseid: courseid},
            modalConfig: {title: getString('coursesupporters:assign', 'local_edusupport')},
            saveButtonText: getString('savechanges'),
            returnFocus: trigger,
        });

        // Replacing just the list is the whole point of doing this over a web service: the
        // page stays where it is and only the part that changed comes back over the wire.
        form.addEventListener(form.events.FORM_SUBMITTED, (submitted) => {
            const list = document.querySelector(SELECTORS.list);
            if (!list || !submitted.detail || !submitted.detail.listhtml) {
                return;
            }
            Templates.replaceNode(list, submitted.detail.listhtml, '');
        });

        form.addEventListener(form.events.ERROR, Notification.exception);

        form.show();
    });
};
