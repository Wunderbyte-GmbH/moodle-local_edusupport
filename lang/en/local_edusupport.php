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
 * @package   local_edusupport
 * @copyright 2018 Digital Education Society (http://www.dibig.at)
 * @author    Robert Schrenk
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'eduSupport';
$string['edusupport:addinstance'] = 'Add eduSupport block';
$string['edusupport:myaddinstance'] = 'Add eduSupport block';

$string['archive'] = 'Archive';
$string['assigned'] = 'Assigned';
$string['auto2ndlvl'] = 'Auto forward 2nd';
$string['auto2ndlvl:description'] = 'Automatically forward all tickets to 2nd level support';
$string['autocreate_orggroup'] = 'Automatically create groups for eduvidual-Organizations';
$string['autocreate_usergroup'] = 'Automatically create a private group for user';
$string['be_more_accurate'] = 'Please be more accurate when describing your problem!';
$string['edusupport:canforward2ndlevel'] = 'Can forward issues to platform support team';
$string['cachedef_supportmenu'] = 'Cache for the supportmenu';
$string['changes_saved_successfully'] = 'Changes saved successfully.';
$string['changes_saved_fail'] = 'Changes could not be saved.';
$string['contactphone'] = 'Telephone';
$string['contactphone_missing'] = 'Please enter your telephone number';
$string['continue'] = 'continue';
$string['coursecategorydeletion'] = 'You are trying to remove a category, that contains supportforums. Please ensure, that you disable the support forums first!';
$string['courseconfig'] = 'Course config';
$string['create_issue'] = 'Contact support';
$string['create_issue_error_title'] = 'Error';
$string['create_issue_error_description'] = 'Your issue could not be stored!';
$string['create_issue_mail_success_description'] = 'Your issue has been stored. We will help you as soon as possible!';
$string['create_issue_success_title'] = 'Success';
$string['create_issue_success_description'] = 'Your issue has been stored. We will help you as soon as possible!';
$string['create_issue_success_description_mail'] = 'Your issue has been sent by mail. We will help you as soon as possible!';
$string['create_issue_success_goto'] = 'View issue';
$string['create_issue_success_responsibles'] = 'Contact person for this ticket is/are:';
$string['create_issue_success_close'] = 'close';
$string['cron:reminder:title'] = 'A user is waiting for your support';
$string['cron:reminder:intro'] = 'This is a friendly reminder about an open issue, that is assigned to you as assigned supporter!';
$string['cron:deleteexpiredissues:title'] = 'delete expired issues';
$string['dedicatedsupporter'] = 'Dedicated';
$string['dedicatedsupporter:not_successfully_set'] = 'Dedicated supporter could not be set';
$string['dedicatedsupporter:successfully_set'] = 'Successfully set dedicated supporter';
$string['description'] = 'Describe the problem encountered including the link to the page/course where the problem occured';
$string['description_missing'] = 'A detailed description of the problem is missing';
$string['deletethreshhold'] = 'Delete closed issues after';
$string['deletethreshhold:description'] = 'Set the threshhold for the deletion of closed issues in the issues view. This only affects the issues page, but not the forum posts. 0 means to keep closed issues forever (not yet recommended)';
$string['goto_tutorials'] = 'Documents & Tutorials';
$string['goto_targetforum'] = 'Supportforum';
$string['edusupport:manage'] = 'Manage';
$string['email_to_xyz'] = 'Send mail to {$a->email}';
$string['enableprepage'] = "Enable Prepage";
$string['enableprepage:description'] = "Enables a site before form";
$string['prepage'] = "Prepage content";
$string['prepage:description'] = "Content displayed before form e.g. faq";
$string['extralinks'] = 'Extralinks';
$string['extralinks:description'] = 'If you enter links here, the "help"-Button will be a menu instead of button. It will include the "help"-Button as first element, and all extra links as additional links. Enter links line by line in the following form: linkname|url|faicon|target';
$string['faqlink'] = 'FAQ-link';
$string['faqlink:description'] = 'link to FAQ';
$string['faqread'] = 'Please confirm that you have read the FAQ';
$string['faqread:description'] = 'I confirm, that I have read the <a href="{$a}" target="_blank">FAQ</a> prior to posting my question.';
$string['guestmail'] = 'Your e-mail';

$string['status'] = 'Status';
$string['changestatus'] = 'Change status';
$string['startedby'] = 'Started by';

$string['header'] = 'Request for help in &nbsp;<i>{$a}</i>';
$string['holidaymodeenabled'] = "Activate holidaymode";
$string['holidaymodeenabled:description'] = "Holidaymode: Supportuser don't get tickets till a set date.";
$string['holidaymode'] = 'Holidaymode';
$string['holidaymode_is_on'] = 'Holidaymode is on';
$string['holidaymode_is_on_descr'] = 'As long as you are on holidays, no new issues will be assigned to you.';
$string['holidaymode_end'] = 'End holidaymode';
$string['notasigned'] = 'No support user has been assigned yet';
$string['issue'] = 'Issue';
$string['issue:countcurrent'] = 'Open issues';
$string['issue:countassigned'] = 'Subscribed issues';
$string['issue:countother'] = 'Other issues';
$string['issue:countclosed'] = 'Closed issues';
$string['issue:assigned'] = 'You have been assigned to this issue:';
$string['issue_assign'] = 'Assign issue';
$string['issue_assign_nextlevel'] = 'Forward to the platform-support team';
$string['issue_assign_nextlevel:error'] = 'Sorry, this issue could not be forwarded to the platform support team';
$string['issuereceived'] = '<p>Thank you for reaching out, your support request has been received.</p>

<p>You will receive an answer to your question shortly. Please understand that some issues take longer to resolve and it might take a few days before we can provide you with a solution.</p>
<p>You are receiving this email because you asked the team for help via a support request. You can find all your requests in the <a href="{$a->wwwroot}/mod/forum/view.php?id={$a->cmid}">support forum</a> on {$a->sitename}. </p>
<p>We wish you a great learning experience!</p>
<p>Your {$a->sitename} support team</p>';
$string['issue_assign_nextlevel:post'] = '<p>We are happy to inform you that your support request has been assigned to the {$a->sitename} support team!</p>

<p>You will receive an answer to your question shortly. Please understand that some issues take longer to resolve and it might take a few days before we can provide you with a solution.</p>
<p>You are receiving this email because you asked the support team for help via a support request. You can find all your request under {$a->supportforumname} on {$a->sitename}.</p>
<p>We wish you a great learning experience!</p>

<p>Your {$a->sitename} team</p>';
// phpcs:ignore Squiz.PHP.CommentedOutCode.Found
/* $string['issue_assign_nextlevel:post'] = '<a href="{$a->wwwroot}/user/view.php?id={$a->fromuserid}">{$a->fromuserfullname}</a> forwarded this issue to the platform support team'; */
$string['issue_assigned:subject'] = 'Your support request has been assigned';
$string['issue_close'] = 'Close issue';
$string['issue_closed:subject'] = 'Issue closed';
$string['issue_closed:post'] = 'This issue closed was closed by <a href="{$a->wwwroot}/user/view.php?id={$a->fromuserid}">{$a->fromuserfullname}</a>. If you need further assistance please forward this issue again to the platform support team.';
$string['issue_responsibles:post'] = '<p>We are happy to inform you that your support request has been assigned to {$a->responsibles} from the {$a->sitename} support team!</p>

   <p>You will receive an answer to your question shortly. Please understand that some issues take longer to resolve and it might take a few days before we can provide you with a solution.</p>

   <p>You are receiving this email because you asked the atingi team for help via a support request. You can find all your request under {$a->supportforumname} on {$a->sitename}.</p>

   <p>We wish you a great learning experience!</p>

   <p>Your {$a->sitename} team</p>
';
$string['issue_responsibles:subject'] = 'Your support request has been assigned';
$string['issuereceived:subject'] = 'Your support request has been received';
$string['issue_revoke'] = 'Revoke this issue from higher support level';
$string['issue_revoke:error'] = 'Sorry, this issue could not be revoked from the higher support levels';
$string['issue_revoke:post'] = '<a href="{$a->wwwroot}/user/view.php?id={$a->fromuserid}">{$a->fromuserfullname}</a> revoked this issue from the higher support level';
$string['issue_revoke:subject'] = 'Supportissue revoked';
$string['issue_close'] = 'Close issue';
$string['issue_closed:subject'] = 'Issue closed';
$string['issue_closed:post'] = 'This issue closed was closed by <a href="{$a->wwwroot}/user/view.php?id={$a->fromuserid}">{$a->fromuserfullname}</a>. If you need further assistance please forward this issue again to the platform support team.';
$string['issues'] = 'Issues';
$string['issues:assigned'] = 'Subscribed';
$string['issues:assigned:none'] = 'Currently you do not have any issue subscriptions';
$string['issues:closed'] = 'Closed issues';

$string['issues:current'] = 'My issues';
$string['issues:current:none'] = 'Seems you deserve a break - no issue left for you!';
$string['issues:other'] = 'Other issues';
$string['issues:other:none'] = 'Great, there seem to be no more problems on that planet!';
$string['issues:openmine'] = '{$a} for me';
$string['issues:opennosupporter'] = '{$a} unassigned';
$string['issues:openall'] = '{$a} total open';
$string['label:2ndlevel'] = 'Platform support team';
$string['missing_permission'] = 'Missing required permission';
$string['missing_targetforum'] = 'Missing target forum, must be configured!';
$string['missing_targetforum_exists'] = 'The configured target forum does not exist. Wrong configuration!';
$string['no_such_issue'] = 'This is not an open issue! You can navigate to the <a href="{$a->todiscussionurl}"><u>discussion page</u></a> or go <a href="{$a->toissuesurl}"><u>back to the issues overview</u></a>.';
$string['only_you'] = 'Only you and our team';
$string['phonefield'] = 'disable phone field';
$string['phonefield:description'] = 'Deactivate phone field in the form for creating issues';
$string['postmailinfolink'] = 'This is a copy of a message posted in {$a->coursename}.

To reply click on this link: {$a->replylink}';
$string['postto2ndlevel'] = 'Submit to platform support team';
$string['postto2ndlevel:description'] = 'Directly forward to the {$a->sitename}-Support!';
$string['predefined_subjects'] = 'Define predefined subjects here';
$string['predefined_subjects:description'] = 'When submitting a support request you can define a list of subjects to choose from instead of a text input field. Leave empty if you want to use text input. One subject per line if you want to provide predefined subjects';
$string['predefined_subjects_prefix'] = 'Enable prefix';
$string['predefined_subjects_prefix:description'] = 'Enable prefix (name can be changed in language customisation subject_prefix e.g. Other:)';
$string['subject_prefix'] = 'Support request with following topic: ';
$string['privacy:metadata'] = 'This plugin does not store any personal data as it uses a forum as target.';
$string['priority'] = 'set priority';
$string['prioritylvl'] = 'enable priorities';
$string['prioritylvl:description'] = 'If enabled you can select priorities in the issues list';
$string['prioritylvl:low'] = 'low priority';
$string['prioritylvl:mid'] = 'mid priority';
$string['prioritylvl:high'] = 'high priority';
$string['relativeurlsupportarea'] = 'Relative URL to Supportarea';
$string['select_subject'] = 'Please select a subject';
$string['screenshot'] = 'Post screenshot';
$string['screenshot:description'] = 'A screenshot may help to solve the problem.';
$string['screenshot:generateinfo'] = 'To generate the screenshot the form will be hidden, and reappears afterwards.';
$string['screenshot:upload:failed'] = 'Preparation of file failed!';
$string['screenshot:upload:successful'] = 'File has been successfully prepared for uploading!';
$string['select_isselected'] = 'Currently selected';
$string['select_unavailable'] = 'Unavailable';
$string['send'] = 'Send';
$string['sendmsgonset2ndlvl'] = 'Send a message to user when 2nd level support user is assigned';
$string['sendmsgonset2ndlvl:description'] = 'Send a email to the user when whenever a support user is assigend or changed';
$string['sendoriginalrequest'] = 'Send the original support request to user';
$string['sendoriginalrequest:description'] = 'Send the forum post of the support request to the user who requested support';
$string['sendsupporterassignments'] = 'Send support user assignments to the user';
$string['sendsupporterassignments:description'] = 'Notify the user via emails when a support user has been assigned to the request. Everytime someone new is assigned an email is sent';
$string['sendissueclosed'] = 'Send e-mail when issue is closed';
$string['sendissueclosed:description'] = 'Notify the user via emails when the issue is closed. In any case, the "issue is closed message" will be posted in the support forum';
$string['sendrequestreceived'] = 'Send e-mail notification that the request has been received';
$string['sendrequestreceived:description'] = 'An e-mail is sent to the user submitting a support request. The e-mail confirms the receipt of the request but is not part of the ticket specific thread in the support forum';
$string['spamprotection:exception'] = 'Sorry, maximum amount of issues exceeded. Try again in a few minutes.';
$string['spamprotection:limit'] = 'Spamprotection > limit';
$string['spamprotection:limit:description'] = 'The maximum amount of created issues within time range.';
$string['spamprotection:threshold'] = 'Spamprotection > minutes';
$string['spamprotection:threshold:description'] = 'The time range that is used to protect from spam.';
$string['subject'] = 'Subject';
$string['subject_missing'] = 'Missing subject';
$string['support_area'] = 'Helpdesk & Tutorials';
$string['supportcourse'] = 'Supportcourse';
$string['supporters'] = 'Supporters';
$string['supporters:choose'] = 'Choose supporters';
$string['supporters:description'] = 'All users of the course, that are enrolled at least as "non-editing teacher" can be configured as supporter. Just enter anything as supportlevel to activate somebody as supporter!';
$string['supportforum:choose'] = 'Choose forums for eduSupport';
$string['supportforum:central:disable'] = 'disable';
$string['supportforum:central:enable'] = 'enable';
$string['supportforum:disable'] = 'disable';
$string['supportforum:enable'] = 'enable';
$string['supportlevel'] = 'Supportlevel';
$string['targetforum'] = 'Supportforum';
$string['targetforum:description'] = 'Please select the forum that should be used as target for support issues within this course. This forum will be forced to have some group mode enabled. The Plugin will create an individual group for every single user.';
$string['targetforum:core:description'] = 'All users will be automatically enrolled to the systemwide supportforum as soon as they create a support issue. Furthermore groups can be created and managed automatically to seperate support issues.';
$string['to_group'] = 'To';
$string['toggle'] = 'Course Supportforum';
$string['toggle:central'] = 'Central Supportforum';
$string['trackhost'] = 'Track host';
$string['trackhost:description'] = 'Big moodle sites may use an architecture with multiple webhosts. If you enable this option, edusupport will add the hostname of the used webhost to the issue.';
$string['guestmodeenabled'] = 'Guestmode active';
$string['guestmodeenabled:description'] = 'Guests can now also post supporttickets. These tickets get answered by mail';
$string['userid'] = 'UserID';
$string['userlinks'] = 'enable userlinks';
$string['userlinks:description'] = 'show userlinks in issues list';
$string['your_issues'] = 'Your issues';
$string['webhost'] = 'Host';
$string['weburl'] = 'URL';

$string['allowguesttickets'] = 'Allow tickets from guest user.';
$string['allowguesttickets:description'] = 'Guest can post one ticket and gets updates via mail.';
$string['back'] = 'back';
$string['continue'] = 'continue';
$string['firstlvlgroupmode'] = '1st level support group modus';
$string['firstlvlgroupmode:description'] = 'Enables group mode so that non teachers (other roles) get connected based on a customfield and can answer in the courseforum (make sure to give the role "canforward2ndlevel" right. Also enable group mode in course and set forum to seperate groups.';
$string['customfieldname'] = 'customfieldname for group mode';
$string['customfieldname:description'] = 'customfieldname for group mode';
$string['rolename'] = 'rolename';
$string['rolename:description'] = 'rolename for the supporters (e.g. teacher instead of editingtecher or customrole)';

$string['invalidmail'] = 'Please enter a vaild email address';

/* State */

$string['status:notstarted'] = 'Not yet started';
$string['status:awaitinguserreply'] = 'Awaiting user reply';
$string['status:ongoing'] = 'Ongoing';
$string['status:closed'] = 'Closed';
$string['status:awaitingsupportaction'] = 'Awaiting support action';
$string['status'] = 'Status';

/* Events */
$string['supportadded'] = "Supportuser added";
$string['supportdeleted'] = "Supportuser deleted";
$string['supportchanged'] = "Supportuser changed";

/* PrivaCY API */
$string['privacy:metadata:edusupport:subscr'] = 'All subscribed issues';
$string['privacy:metadata:edusupport:issues'] = 'Issues of supporters';
$string['privacy:metadata:edusupport:fieldid'] = 'Id';
$string['privacy:metadata:edusupport:issueid'] = 'Issue Id';
$string['privacy:metadata:edusupport:discussionid'] = 'Forum discussion Id ';
$string['privacy:metadata:edusupport:userid'] = 'User Id';
$string['privacy:metadata:edusupport:supporters'] = 'All defined supporters';
$string['privacy:metadata:edusupport:supportlvl'] = 'Supportlevel';
$string['privacy:metadata:edusupport:courseid'] = 'Course Id with supportforum';
$string['privacy:metadata:edusupport:currentsupporter'] = 'User Id of the assigned user';
$string['privacy:metadata:edusupport:status'] = 'Status of issue';


/* Accountmanager */
$string['possiblemanagers'] = 'Possible managers';
$string['none'] = 'none chosen';
$string['accountmanagers'] = 'Account managers';
$string['accountmanager'] = 'Your Account managers';
$string['capstocheck'] = 'Capabilties that are checked';
$string['setaccountmanager'] = 'Set Account managers';
$string['accountmanagertitle'] = 'Account manager';

/* Guestticket */
$string['furtherquestions'] = 'As you have posted a support request as guest user, you can not reply or post further comments for that issue. If you want to have further support please register on {$a->sitename}.';

$string['timebeforereminder'] = 'Time between last statusupdate and reminder';
