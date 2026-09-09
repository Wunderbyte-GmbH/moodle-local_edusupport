define(
    ['jquery', 'core/ajax', 'core/notification', 'core/str', 'core/url', 'core/modal_save_cancel',
        'core/local/modal/alert', 'core/modal_events'],
    function($, AJAX, NOTIFICATION, STR, URL, SaveCancelModal, AlertModal, ModalEvents) {
    return {
        debug: 0,
        modal: undefined,
        screenshot: '',
        screenshotname: '',
        triggerSteps: 0,
        assignSupporter: function(discussionid) {
                // Show a selection of possible supporters.
                AJAX.call([{
                    methodname: 'local_edusupport_get_potentialsupporters',
                    args: {discussionid: discussionid},
                    done: function(result) {
                        try {
 result = JSON.parse(result);
} catch (e) { }
                        var supportlevels = Object.keys(result.supporters);
                        var body = '<input type="hidden" value="' + discussionid + '" />';
                        body += '<select>';
                        for (var a = 0; a < supportlevels.length; a++) {
                            body += '<optgroup label="' + supportlevels[a] + '">';
                            for (var b = 0; b < result.supporters[supportlevels[a]].length; b++) {
                                var supporter = result.supporters[supportlevels[a]][b];
                                var selected = supporter.selected ? ' selected="selected"' : '';
                                body += '<option value="' + supporter.userid + '"' + selected + '>'
                                    + supporter.firstname + ' ' + supporter.lastname + '</option>';
                            }
                            body += '</optgroup>';
                        }
                        body += '</select>';

                        // Console.log(result);
                        SaveCancelModal.create({
                            title: STR.get_string('select', 'core'),
                            body: body,
                            // Footer: 'footer',
                        }).then(function(modal) {
                            modal.show();
                            modal.getRoot().on(ModalEvents.save, function(e) {
                                e.preventDefault();
                                var discussionid = $(this).find('.modal-body input').val();
                                var supporterid = $(this).find('.modal-body select').val();
                                var data = {'discussionid': discussionid, 'supporterid': supporterid};
                                // Console.log('Store', this, e, data);
                                AJAX.call([{
                                    methodname: 'local_edusupport_set_currentsupporter',
                                    args: data,
                                    done: function(result) {
                                        if (result == 1) {
                                            top.location.reload();
                                        } else {
                                            alert('Error: ' + result);
                                        }
                                    },
                                    fail: NOTIFICATION.exception
                                }]);
                            });
                        });
                    },
                    fail: NOTIFICATION.exception
                }]);

        },
        /**
         * Checks if a particular support form has a screenshot. If not, it hides the modal and creates one.
         *
         * @param {object} c the checkbox that was clicked.
         * @returns {void}
         */
        checkHasScreenshot: function(c) {
            if ($(c).closest("form").find("#screenshot").attr('src') == '') {
                $(c).closest("form").find('#screenshot_ok').css("display", "block");

            } else {
                $(c).closest("form").find('#screenshot_ok').css("display", "none");
                $(c).closest("form").find("#screenshot").css("display", ($(c).is(":checked") ? "inline" : "none"));
                $(c).closest("form").find("#screenshot_new").css("display", ($(c).is(":checked") ? "block" : "none"));
            }
        },
        /**
         * Generate the screenshot now.
         *
         * @returns {void}
         */
        generateScreenshot: function() {
            var MAIN = this;
            MAIN.modal.hide();
            require(['local_edusupport/html2canvas'], function(h2c) {
                h2c(document.body).then(function(canvas) {
                    MAIN.canvas = canvas;
                    if (typeof MAIN.modal !== 'undefined') {
                        MAIN.prepareScreenshot();
                        MAIN.modal.show();
                    }
                });
            });
        },
        /**
         * Inject a help button in the upper right menu.
         */
        injectHelpButton: function() {
            AJAX.call([{
                methodname: 'local_edusupport_get_extralinks',
                args: {},
                done: function(result) {
                    $(result).insertBefore($('nav.fixed-top .nav .nav-item:last-child'));
                },
                fail: NOTIFICATION.exception
            }]);
        },
        /**
         * Scans the page for all discussion posts and adds a reply-button.
         *
         * @param {number} discussion the discussion to add the buttons to.
         * @returns {void}
         */
        injectReplyButtons: function(discussion) {
            STR.get_strings([
                    {'key': 'reply', component: 'forum'},
                ]).done(function(s) {
                    // Remove default reply links.
                    $('a[href*="issue.php?discussion=' + discussion + '&parent="]').remove();
                    $('a[href*="issue.php?discussion=' + discussion + '&delete="]').remove();
                    $('a[href*="post.php?prune="]').remove();
                    // Add our customized reply links.
                    $('.forum-post-container>.forumpost').each(function() {
                        var postid = $(this).attr('data-post-id');
                        if ($(this).find('.reply-' + postid).length == 0) {
                            $(this).find('.post-actions:first-child').append(
                                $('<a data-region="post-action" class="btn btn-link reply-' + postid + '"'
                                    + ' title="' + s[0] + '" aria-label="' + s[0] + '"'
                                    + ' role="menuitem" tabindex="-1">')
                                    .html(s[0])
                                    .attr('href', URL.relativeUrl('/local/edusupport/issue.php?discussion='
                                        + discussion + '&replyto=' + postid + '#mformforum'))
                            );
                        }
                    });
                }
            ).fail(NOTIFICATION.exception);
        },
        /**
         * Close an issue.
         *
         * @param {number} discussionid the issue to close.
         * @returns {void}
         */
        closeIssue: function(discussionid) {
            AJAX.call([{
                methodname: 'local_edusupport_close_issue',
                args: {discussionid: discussionid},
                done: function(result) {
                    if (result == 1) {
                        top.location.href = URL.relativeUrl('/local/edusupport/issues.php', {});
                    } else {
                        NOTIFICATION.exception(result);
                        // Alert('Error: ' + result);
                    }
                },
                fail: NOTIFICATION.exception
            }]);
        },
        /**
         * Colorize shown discussions.
         **/
        colorize: function() {
            var discussionids = [];
            $('table.forumheaderlist tr.discussion td.starter a').each(function() {
 var d = $(this).attr('href').split('?d='); discussionids[discussionids.length] = d[1];
});
            var data = {discussionids: discussionids};
            AJAX.call([{
                methodname: 'local_edusupport_colorize',
                args: data,
                done: function(result) {
                    try {
 result = JSON.parse(result);
} catch (e) {}
                    if (typeof result.styles !== 'undefined') {
                        var discussionids = Object.keys(result.styles);
                        for (var a = 0; a < discussionids.length; a++) {
                            var discussionid = discussionids[a];
                            var style = result.styles[discussionid];
                            $('table.forumheaderlist tr.discussion td.starter a[href$="d=' + discussionid + '"]')
                                .closest('tr').attr('style', style);
                        }
                    }
                },
                fail: NOTIFICATION.exception
            }]);
        },
        /**
         * Let's inject a button to call the 2nd level support.
         *
         * @param {number} discussionid the issue the button belongs to.
         * @param {boolean} isissue determines if this issue is already at higher support levels.
         * @returns {void}
         */
        injectForwardButton: function(discussionid, isissue) {
            if (this.debug) {
}
            if (typeof discussionid === 'undefined') {
 return;
}
            STR.get_strings([
                    {
                        'key': (typeof isissue !== 'undefined' && isissue) ? 'issue_revoke' : 'issue_assign_nextlevel',
                        component: 'local_edusupport'
                    },
                ]).done(function(s) {
                    $('#page-content div[role="main"] .discussionname').parent().prepend(
                        $('<a href="#">')
                                    .attr('onclick', "require(['local_edusupport/main'], function(MAIN) { "
                                        + "MAIN.injectForwardModal(" + discussionid + ", " + isissue + "); });"
                                        + " return false;")
                                    .attr('style', 'float: right')
                                    .addClass("btn btn-secondary")
                                    .html(s[0])
                    );
                }
            ).fail(NOTIFICATION.exception);
        },
        injectTest: function() {
            var discussionname = $(".discussionname");
            if (discussionname.text().substr(0, 2) == "! ") {
                discussionname.addClass("alert-warning");
            }
             if (discussionname.text().substr(0, 2) == "!!") {
                discussionname.addClass("alert-danger");
            }


        },
        injectForwardModal: function(discussionid, revoke) {
            STR.get_strings([
                    {'key': 'confirm', component: 'core'},
                    {
                        'key': (typeof revoke !== 'undefined' && revoke) ? 'issue_revoke' : 'issue_assign_nextlevel',
                        component: 'local_edusupport'
                    },
                ]).done(function(s) {
                    SaveCancelModal.create({
                        title: s[0],
                        body: s[1],
                    })
                    .then(function(modal) {
                        var root = modal.getRoot();
                        root.on(ModalEvents.save, function() {
                            top.location.href = URL.relativeUrl('/local/edusupport/forward_2nd_level.php',
                                {d: discussionid, revoke: revoke});
                        });
                        modal.show();
                    });
                }
            ).fail(NOTIFICATION.exception);
        },
        postBox: function(modal) {
            var MAIN = this;
            if (typeof MAIN.is_sending !== 'undefined' && MAIN.is_sending) {
                return;
            }
            var subject = $('#local_edusupport_create_form #id_subject').val();
            var contactphone = $('#local_edusupport_create_form #id_contactphone').val() || '';
            var description = $('#local_edusupport_create_form #id_description').val();
            var forum_group = $('#local_edusupport_create_form #id_forum_group').val();
            var postto2ndlevel = $('#local_edusupport_create_form #id_postto2ndlevel').prop('checked') ? 1 : 0;
            var post_screenshot = true; // $('#local_edusupport_create_form #id_postscreenshot').prop('checked') ? 1 : 0;
            var screenshot = MAIN.screenshot; // $('#local_edusupport_create_form img#screenshot').attr('src');
            var screenshotname = MAIN.screenshotname;
            var faqread = $('#local_edusupport_create_form #id_faqread').prop('checked') ? 1 : 0;
            var guestmailfield = $('#local_edusupport_create_form #id_guestmail');
            var guestmail = guestmailfield.length ? guestmailfield.val() : null;
            var accountmanagerfield = $('#local_edusupport_create_form #id_accountmanager');
            var accountmanager = accountmanagerfield.length ? accountmanagerfield.val() : null;
            var url = top.location.href;
            if (faqread == 0) {
                var editaPresent = STR.get_string('faqread', 'local_edusupport', {});
                $.when(editaPresent).done(function(localizedEditString) {
                    NOTIFICATION.alert('', localizedEditString);
                });
                return;
            }
            if (subject.length == 0) {
                var editaPresent = STR.get_string('select_subject', 'local_edusupport', {});
                $.when(editaPresent).done(function(localizedEditString) {
                    NOTIFICATION.alert('', localizedEditString);
                });
                return;
            }
            if (subject.length < 3 || description.length < 5) {
                var editaPresent = STR.get_string('be_more_accurate', 'local_edusupport', {});
                $.when(editaPresent).done(function(localizedEditString) {
                    NOTIFICATION.alert('', localizedEditString);
                });
                return;
            }

            var validregex = /^[a-zA-Z0-9.!#$%&'*+/=?^_`{|}~-]+@[a-zA-Z0-9-]+(?:\.[a-zA-Z0-9-]+)*$/;
            if (guestmailfield.length && !guestmailfield.val().match(validregex)) {
                var editaPresent = STR.get_string('invalidmail', 'local_edusupport', {});
                $.when(editaPresent).done(function(localizedEditString) {
                    NOTIFICATION.alert('', localizedEditString);
                });
                return;
            }

            MAIN.is_sending = true;

            var imagedataurl = (post_screenshot && typeof screenshot !== 'undefined') ? screenshot : '';
            AJAX.call([{
                methodname: 'local_edusupport_create_issue',
                args: {subject: subject, description: description, forum_group: forum_group,
                    postto2ndlevel: postto2ndlevel, image: imagedataurl, screenshotname: screenshotname,
                     url: url, contactphone: contactphone, guestmail: guestmail, accountmanager: accountmanager},
                done: function(result) {
                    // Result is the discussion id, -999 if sent by mail, or -1. If it is above 0 we
                    // show a confirm box that redirects to the post, on -1 we show an error.
                    modal.hide();

                    var responsibles = '';
                    if (typeof result.responsibles !== 'undefined') {
                        responsibles += '<ul class="edusupport_responsible">';
                        for (var i = 0; i < result.responsibles.length; i++) {
                            var r = result.responsibles[i];
                            if (typeof r.userid !== 'undefined' && r.userid > 0) {
                                responsibles += '<li><a href="'
                                    + URL.fileUrl('/user', 'view.php?id=' + r.userid)
                                    + '" target="_blank">' + r.name + '</a></li>';
                            } else if (typeof r.email !== 'undefined' && r.email != '') {
                                responsibles += '<li><a href="mailto:' + r.email + '">' + r.name + '</a></li>';
                            } else {
                                responsibles += '<li>' + r.name + '</li>';
                            }
                        }
                        responsibles += '</ul>';
                    }
                    if (typeof result.discussionid !== 'undefined' && parseInt(result.discussionid) == -999) {
                        // Confirmation, was sent by mail.
                        STR.get_strings([
                            {'key': 'create_issue_success_title', component: 'local_edusupport'},
                            {'key': 'create_issue_success_description_mail', component: 'local_edusupport'},
                            {'key': 'create_issue_success_responsibles', component: 'local_edusupport'},
                            {'key': 'create_issue_success_close', component: 'local_edusupport'},
                            ]).done(function(s) {
                                var desc = s[1];
                                if (responsibles != '') {
                                    desc = s[2] + responsibles;
                                }
                                NOTIFICATION.alert(s[0], desc, s[3]);
                            }
                        ).fail(NOTIFICATION.exception);
                    } else if (typeof result.discussionid !== 'undefined' && parseInt(result.discussionid) > 0) {
                        // Confirmation
                        STR.get_strings([
                            {'key': 'create_issue_success_title', component: 'local_edusupport'},
                            {'key': 'create_issue_success_description', component: 'local_edusupport'},
                            {'key': 'create_issue_success_responsibles', component: 'local_edusupport'},
                            {'key': 'create_issue_success_goto', component: 'local_edusupport'},
                            {'key': 'create_issue_success_close', component: 'local_edusupport'},
                            ]).done(function(s) {
                                var desc = s[1];
                                if (responsibles != '') {
                                    desc = s[2] + responsibles;
                                }
                                NOTIFICATION.confirm(s[0], desc, s[3], s[4], function() {
 top.location.href = URL.fileUrl('/mod/forum', 'discuss.php?d=' + result.discussionid);
});
                            }
                        ).fail(NOTIFICATION.exception);
                    } else {
                        STR.get_strings([
                                {'key': 'create_issue_error_title', component: 'local_edusupport'},
                                {'key': 'create_issue_error_description', component: 'local_edusupport'},
                            ]).done(function(s) {
                                NOTIFICATION.alert(s[0], s[1]);
                            }
                        ).fail(NOTIFICATION.exception);
                    }
                    MAIN.is_sending = false;
                },
                fail: NOTIFICATION.exception
            }]);
        },
        prepareBox: function() {
            var MAIN = this;
            var body = $(MAIN.modal.body);
            if (body.find('#id_forum_group>option').length <= 1) {
                body.find('#id_forum_group').parent().parent().css('display', 'none');
            }

            MAIN.modal.setLarge();

            MAIN.modal.getRoot().on(ModalEvents.save, function(e) {
                // Stop the default save button behaviour which is to close the modal.
                MAIN.postBox(MAIN.modal);
                e.preventDefault();
                // Do your form validation here.
            });
            var editaPresent = STR.get_string('create_issue', 'local_edusupport', {});
            $.when(editaPresent).done(function(localizedEditString) {
                MAIN.modal.setSaveButtonText(localizedEditString);
            });
            /* $('#id_postscreenshot').closest('div.fitem').css('display', 'none');
            $('#screenshot').closest('div').css('display', 'none');
*/
            MAIN.modal.show();
        },
        /**
         * Insert screenshot to form.
         *
         * @returns {void}
         */
        prepareScreenshot: function() {
            var MAIN = this;
            var dataurl = MAIN.canvas.toDataURL();
            var body = $(MAIN.modal.body);
            body.find('img#screenshot').attr('src', dataurl);
            $('#screenshot').closest('div').css('display', undefined);
            $('#id_postscreenshot').closest('div.fitem').css('display', undefined);
            MAIN.checkHasScreenshot($('#id_postscreenshot'));
            // Delete canvas - next time we want a new screenshot!
            delete (MAIN.canvas);
        },
        showBox: function(forumid) {
            if (typeof forumid === 'undefined') {
 forumid = 0;
}
            var MAIN = this;
            // @todo no functional requirement that screenshot works.
            // @todo screenshot creation parallel to modal?
            // @todo save modal in object for manipulation
            delete (MAIN.canvas);

            if (typeof MAIN.modal !== 'undefined') {
                MAIN.prepareBox(forumid);
            } else {
                MAIN.triggerSpinner(1);
                AJAX.call([{
                    methodname: 'local_edusupport_create_form',
                    args: {url: top.location.href, image: '', forumid: forumid},
                    done: function(result) {
                        MAIN.triggerSpinner(-1);
                        // Remove any previously created forms.
                        $('#local_edusupport_create_form').remove();
                        // Console.log(result);
                        SaveCancelModal.create({
                            // Title: 'create issue',
                            body: result,
                            large: 1,
                            // Footer: 'footer',
                        }).then(function(modal) {
                            MAIN.modal = modal;

                            MAIN.prepareBox();
                        });
                    },
                    fail: NOTIFICATION.exception
                }]);
            }
        },
        showSupporter: function(forumid) {
            if (typeof forumid === 'undefined') {
 forumid = 0;
}
            var MAIN = this;
            // @todo no functional requirement that screenshot works.
            // @todo screenshot creation parallel to modal?
            // @todo save modal in object for manipulation
            delete (MAIN.canvas);
            if (typeof MAIN.modal !== 'undefined') {
                MAIN.prepareBox(forumid);
            } else {
                MAIN.triggerSpinner(1);
                AJAX.call([{
                    methodname: 'local_edusupport_create_form',
                    args: {url: top.location.href, image: '', forumid: forumid},
                    done: function(result) {
                        MAIN.triggerSpinner(-1);
                        // Remove any previously created forms.
                        $('#local_edusupport_create_form').remove();
                        // Console.log(result);
                        SaveCancelModal.create({
                            // Title: 'create issue',
                            body: result,
                            large: 1,
                            // Footer: 'footer',
                        }).then(function(modal) {
                            MAIN.modal = modal;
                            MAIN.prepareBox();
                        });
                    },
                    fail: NOTIFICATION.exception
                }]);
            }
        },

        supportCourseMovedAlert: function(title, msg) {
            AlertModal.create({
                title: title,
                body: msg,
                // Footer: 'footer',
            }).then(function(modal) {
                modal.show();
            });
        },
        triggerSpinner: function(steps) {
            var MAIN = this;
            MAIN.triggerSteps += steps;
            if (MAIN.triggerSteps > 0) {
                if ($('body #edusupport-spinner').length == 0) {
                    $('body').append($('<div id="edusupport-spinner" class="spinner-grid show">'
                        + '<div></div><div></div><div></div><div></div></div>'));
                }
            } else {
                $('#edusupport-spinner').remove();
            }
        },
        uploadScreenshot: function() {
            var MAIN = this;
            $('#edusupport_screenshot input').addClass('disabled');
            $('#edusupport_screenshot div.alert').addClass('hidden');
            var file = document.querySelector('#edusupport_screenshot input[type="file"]').files[0];
            var reader = new FileReader();
            reader.readAsDataURL(file);
            if (typeof file.name !== 'undefined') {
                MAIN.screenshotname = file.name;
                reader.onload = function() {
                    $('#edusupport_screenshot div.alert-success').removeClass('hidden');
                    $('#edusupport_screenshot input').removeClass('disabled');
                    MAIN.screenshot = reader.result;
                };
                reader.onerror = function() {
                    $('#edusupport_screenshot div.alert-danger').removeClass('hidden');
                    $('#edusupport_screenshot input').removeClass('disabled');

                };
            }
        },
    };
});
