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
$string['edusupport:addinstance'] = 'eduSupport Block hinzufügen';
$string['edusupport:manage'] = 'eduSupport Manage Capability';
$string['edusupport:myaddinstance'] = 'eduSupport Block hinzufügen';
$string['archive'] = 'Archiv';
$string['assigned'] = 'Zugeordnet';
$string['auto2ndlvl'] = 'Automatische Weiterleitung zum 2nd Level Support';
$string['auto2ndlvl:description'] = 'Automatische Weiterleitung zum 2nd Level Support nach Anlegen eines neuen Tickets';
$string['autocreate_orggroup'] = 'Automatisch Gruppen für eduvidual-Organisationen anlegen';
$string['autocreate_usergroup'] = 'Automatisch private Gruppen für Nutzer/innen anlegen';
$string['be_more_accurate'] = 'Bitte beschreiben Sie das Problem genauer!';
$string['edusupport:canforward2ndlevel'] = 'Kann Probleme an das Plattform Support Team melden';
$string['changes_saved_successfully'] = 'Änderungen erfolgreich gespeichert.';
$string['changes_saved_fail'] = 'Änderungen konnten nicht gespeichert werden.';
$string['contactphone'] = 'Telefon';
$string['contactphone_missing'] = 'Bitte geben Sie Ihre Telefonnummer für Rückfragen an!';
$string['continue'] = 'Weiter';
$string['coursecategorydeletion'] = 'Sie versuchen einen Kursbereich zu löschen, der Supportforen enthält. Bitte stellen Sie sicher, dass Sie zuvor die Supportforen deaktivieren!';
$string['courseconfig'] = 'Kurskonfiguration';
$string['create_issue'] = 'Support kontaktieren';
$string['create_issue_error_title'] = 'Fehler';
$string['create_issue_error_description'] = 'Die Anfrage konnte nicht gespeichert werden!';
$string['create_issue_success_title'] = 'Erfolg';
$string['create_issue_success_description'] = 'Ihre Anfrage wurde gespeichert. Wir kümmern uns darum so rasch wie möglich!';
$string['create_issue_success_description_mail'] = 'Ihre Anfrage wurde per e-Mail gesendet. Wir kümmern uns darum so rasch wie möglich!';
$string['create_issue_success_goto'] = 'Anfrage öffnen';
$string['create_issue_success_responsibles'] = 'Ansprechperson für diese Ticket ist/sind:';
$string['create_issue_success_close'] = 'Schließen';
$string['cron:reminder:title'] = 'Support Erinnerung';
$string['cron:reminder:intro'] = 'Dies ist eine freundlicher Erinnerung an jene offenen Tickets, die Ihnen als Supporter zugeteilt wurden!';
$string['cron:deleteexpiredissues:title'] = 'Lösche alte Tickets';
$string['dedicatedsupporter'] = 'Zugewiesen';
$string['dedicatedsupporter:not_successfully_set'] = 'Konnte bevorzugte/n Supportmitarbeiter/in nicht auswählen.';
$string['dedicatedsupporter:successfully_set'] = 'Erfolgreich eine/n bevorzugte/n Supportmitarbeiter/in ausgewählt.';
$string['description'] = 'Beschreiben Sie das Problem und posten Sie den Link zur Seite oder zum Kurs wo das Problem auftrat';
$string['description_missing'] = 'Bitte geben Sie eine detaillierte Beschreibung an!';
$string['goto_tutorials'] = 'Hilfe & Anleitungen';
$string['goto_targetforum'] = 'Supportforum';
$string['edusupport:manage'] = 'Verwalten';
$string['email_to_xyz'] = 'Sende e-Mail an {$a->email}';
$string['enableprepage'] = "enable Prepage";
$string['enableprepage:description'] = "enables a site before form";
$string['prepage'] = "prepage content";
$string['prepage:description'] = "content displayed before form e.g. faq";
$string['extralinks'] = 'Extralinks';
$string['extralinks:description'] = 'Wenn Sie hier Links eintragen, dann wird der "Hilfe"-Button zu einem Menü. Dieses wird den "Hilfe"-Button als ersten Menüeintrag anzeigen, und alle hier eingetragenen Links als zusätzliche Hilfeanlaufstellen. Geben Sie die Links zeilenweise in folgendem Format an: Linkname|URL|faicon|Target';
$string['faqlink'] = 'FAQ-Link';
$string['faqlink:description'] = 'Addresse zum FAQ';
$string['faqread'] = 'Bitte bestätigen, dass Sie die FAQ gelesen haben!';
$string['faqread:description'] = 'Ich bestätige hiermit die <a href="{$a}">FAQ</a> gelesen zu haben';
$string['header'] = 'Hilfe in &nbsp;<i>{$a}</i>&nbsp; anfordern';
$string['holidaymodeenabled'] = "Urlaubsmodus aktivieren";
$string['holidaymodeenabled:description'] = "Urlaubsmodus: Supporter bekommen bis zu einem bestimmten Datum keine Tickets.";
$string['holidaymode'] = 'Urlaubsmodus';
$string['holidaymode_is_on'] = 'Urlaubsmodus ist an';
$string['holidaymode_is_on_descr'] = 'Bei aktiviertem Urlaubsmodus werden Ihnen keine neuen Tickets zugewiesen.';
$string['holidaymode_end'] = 'Beende Urlaubsmodus';
$string['notasigned'] = 'Es wurde noch kein Support-User zugewiesen';
$string['issue'] = 'Ticket';
$string['issue:countcurrent'] = 'offene Tickets';
$string['issue:countassigned'] = 'verfolgte Tickets';
$string['issue:countother'] = 'andere Tickets';
$string['issue:countclosed'] = 'geschlossene Tickets';
$string['issue:assigned'] = 'Sie wurden folgendem Ticket zugewiesen:';
$string['issue_assign'] = 'Zuordnen';
$string['issuereceived'] = '<p>Danke, dass Sie sich gemeldet haben. Ihre Supportanfrage ist eingelangt.</p>
<p>Sie werden in Kürze eine Antwort auf Ihre Frage erhalten. Bitte haben Sie Verständnis dafür, dass die Lösung einiger Probleme mehr Zeit beansprucht und es einige Tage dauern kann, bis wir Ihnen eine Lösung anbieten können.</p>
<p>Sie erhalten diese E-Mail, weil Sie das Team über eine Support-Anfrage um Hilfe gebeten haben. Sie finden alle Ihre Anfragen im <a href="{$a->wwwroot}/mod/forum/view.php?id={$a->cmid}">Support-Forum</a> auf {$a ->Sitename}.';
$string['issue_assign_nextlevel'] = 'Dieses Ticket dem Plattform-Support zuweisen';
$string['issue_assign_nextlevel:error'] = 'Entschuldigung, das Ticket konnte nicht dem Plattform Support Team zugewiesen werden.';
$string['issue_assign_nextlevel:post'] = '<p>Wir freuen uns, Ihnen mitteilen zu können, dass Ihre Support-Anfrage an das {$a->sitename} Support-Team weitergeleitet wurde!</p>
 <p>Sie werden in Kürze eine Antwort auf Ihre Frage erhalten. Bitte haben Sie Verständnis dafür, dass die Beantwortung mancher Fragen länger dauert und es einige Tage dauern kann, bis wir Ihnen eine Lösung anbieten können.</p>
 Sie erhalten diese E-Mail, weil Sie das Team von {$a->sitename} über eine Support-Anfrage um Hilfe gebeten haben. Sie finden alle Ihre Anfragen unter {$a->supportforumname} auf {$a->sitename}.
 <p>Wir wünschen Ihnen eine tolle Lernerfahrung!</p>
 <p>Ihr {$a->sitename} Team </p>';
$string['issue_assigned:subject'] = 'Supportanfrage zugeordnet';
$string['issuereceived:subject'] = 'Ihre Supportanfrage wird bearbeitet';
$string['issue_close'] = 'Anfrage schließen';
$string['issue_closed:subject'] = 'Anfrage wurde geschlossen';
$string['issue_closed:post'] = 'Dieses Ticket wurde von <a href="{$a->wwwroot}/user/view.php?id={$a->fromuserid}">{$a->fromuserfullname}</a> geschlossen. Falls Sie weitere Unterstützung benötigen, fordern Sie bitte wieder das Plattform Support Team an!';
$string['issue_responsibles:post'] = '
    <p>
        Die Verantwortung für dieses Ticket liegt bei: {$a->responsibles}!
    </p>
    <p>
        Die Manager/innen der Schule können das Problem an den {$a->sitename}-Support weiterleiten, indem sie die Schaltfläche "Dieses Ticket dem {$a->sitename}-Support zuweisen" anklicken (für Manager/innen rechts oben sichtbar).
    </p>
';
$string['issue_responsibles:subject'] = 'Supportanfrage zugeordnet';
$string['issue_revoke'] = 'Ticket vom höheren Supportlevel zurücknehmen';
$string['issue_revoke:error'] = 'Entschuldigung, dieses Ticket konnte vom höheren Supportlevel nicht zurückgeholt werden!';
$string['issue_revoke:post'] = '<a href="{$a->wwwroot}/user/view.php?id={$a->fromuserid}">{$a->fromuserfullname}</a> hat dieses Ticket vom höheren Supportlevel zurückgenommen';
$string['issue_revoke:subject'] = 'Ticket storniert';
$string['issue_close'] = 'Anfrage schließen';
$string['issue_closed:subject'] = 'Anfrage wurde geschlossen';
$string['issue_closed:post'] = 'Dieses Ticket wurde von <a href="{$a->wwwroot}/user/view.php?id={$a->fromuserid}">{$a->fromuserfullname}</a> geschlossen. Falls Sie weitere Unterstützung benötigen, fordern Sie bitte wieder das Plattform Support Team an!';
$string['issues'] = 'Anfragen';
$string['issues:assigned'] = 'Abonniert';
$string['issues:assigned:none'] = 'Es sind keine weiteren Anfragen abonniert worden.';
$string['issues:closed'] = 'Geschlossen';
$string['issues:current'] = 'Meine Verantwortung';
$string['issues:current:none'] = 'Gönn dir ne Pause - es ist alles erledigt!';
$string['issues:other'] = 'Andere Anfragen';
$string['issues:other:none'] = 'Super, auf diesem Planeten gibt es keine Probleme mehr, oder doch?';
$string['issues:openmine'] = '{$a} für mich';
$string['issues:opennosupporter'] = '{$a} nicht zugeordnet';
$string['issues:openall'] = '{$a} gesamt offen';
$string['label:2ndlevel'] = 'Plattform Support Team';
$string['missing_permission'] = 'Fehlende Erlaubnis!';
$string['missing_targetforum'] = 'Das Zielforum fehlt und muss konfiguriert werden!';
$string['missing_targetforum_exists'] = 'Das konfigurierte Zielforum existiert nicht. Die fehlerhafte Konfiguration muss behoben werden!';
$string['no_such_issue'] = 'Dies ist kein offenes Ticket! Sie können die <a href="{$a->todiscussionurl}"><u>Diskussion direkt im Forum</u></a> aufrufen oder zurück zur <a href="{$a->toissuesurl}"><u>Übersicht der offenen Tickets</u></a> wechseln.';
$string['only_you'] = 'Nur Sie und unser Team';
$string['phonefield'] = 'Telefonfeld verbergen';
$string['phonefield:description'] = 'Telefonfeld verbergen';
$string['postmailinfolink'] = 'Dies ist die Kopie einer Nachricht, die in {$a->coursename} gepostet wurde.

Klicken Sie hier, um zu antworten: {$a->replylink}';
$string['postto2ndlevel'] = 'Plattform Support Team';
$string['postto2ndlevel:description'] = 'Direkt an den {$a->sitename}-Support weiterleiten!';
$string['privacy:metadata'] = 'Dieses Plugin speichert keine personenbezogenen Daten, da die Informationen in einem Forum abgelegt werden.';
$string['priority'] = 'setze Priorität';
$string['prioritylvl'] = 'Prioritäten erlauben';
$string['prioritylvl:description'] = 'ermöglicht es in der Taskliste Prioritäten zu setzen';
$string['prioritylvl:low'] = 'niedrige Priorität';
$string['prioritylvl:mid'] = 'mittlere Priorität';
$string['prioritylvl:high'] = 'hohe Priorität';
$string['predefined_subjects'] = 'Definieren Sie hier vorgegebene Support-Betreffe';
$string['predefined_subjects:description'] = 'Beim Einreichen einer Support-Anfrage können Sie eine Liste von Betreffen voreinstellen, aus der Sie anstelle eines Texteingabefeldes auswählen können. Lassen Sie das Feld leer, wenn Sie die Texteingabe verwenden möchten. Ein Betreff pro Zeile, wenn Sie vordefinierte Betreffe angeben möchten.';
$string['predefined_subjects_prefix'] = 'Aktviere Betreff Prefix';
$string['predefined_subjects_prefix:description'] = 'Aktviere Betreff Prefix (Prefix kann in den Sprachpaketen geändert werden  subject_prefix e.g. Other:)';
$string['subject_prefix'] = 'Supportanfrage zu folgendem Thema: ';
$string['relativeurlsupportarea'] = 'Relative URL zum Supportbereich';
$string['select_subject'] = 'Bitte wählen Sie einen Betreff für die Anfrage';
$string['screenshot'] = 'Screenshot anhängen';
$string['screenshot:description'] = 'Ein Screenshot kann bei der Problembehebung helfen!';
$string['screenshot:generateinfo'] = 'Zur Generierung des Screenshots wird das Formular kurz unsichtbar, wird aber danach gleich wieder angezeigt!';
$string['screenshot:upload:failed'] = 'Vorbereitung der Datei fehlgeschlagen!';
$string['screenshot:upload:successful'] = 'Datei erfolgreich für Übertragung vorbereitet!';
$string['select_isselected'] = 'Derzeit ausgewählt';
$string['select_unavailable'] = 'Nicht verfügbar';
$string['send'] = 'Senden';
$string['subject'] = 'Betreff';
$string['subject_missing'] = 'Bitte geben Sie einen stichwortartigen Titel an, der das Problem beschreibt!';
$string['support_area'] = 'Hilfe & Anleitungen';
$string['supportcourse'] = 'Supportkurs';
$string['supporters'] = 'Supportmitarbeiter/innen';
$string['supporters:choose'] = 'Supportmitarbeiter/innen wählen';
$string['supportforum:choose'] = 'Foren für eduSupport auswählen';
$string['supporters:description'] = 'Alle Nutzer/innen des Kurses, die zumindest als "non-editing Teacher" eingestuft sind, stehen als Supportmitarbeiter/innen zur Verfügung. Geben Sie ein beliebiges Supportlevel (wird immer alphabetisch sortiert) an, um jemanden als Mitarbeiter/in einzusetzen!';
$string['supportforum:central:disable'] = 'deaktivieren';
$string['supportforum:central:enable'] = 'aktivieren';
$string['supportforum:disable'] = 'deaktivieren';
$string['supportforum:enable'] = 'aktivieren';
$string['supportlevel'] = 'Supportlevel';
$string['targetforum'] = 'Supportforum';
$string['targetforum:description'] = 'Bitte wählen Sie jenes Forum, welches im Kurs für Supportanfragen genutzt werden soll. In diesem Forum wird zwangszweise der Gruppenmodus aktiviert bevor die erste Supportanfrage erstellt wird. Das Plugin wird außerdem für jede/n Nutzer/in automatisch eine private Gruppe anlegen.';
$string['targetforum:core:description'] = 'Alle Nutzer/innen werden automatisch in das systemweite Supportforum eingeschrieben, sobald sie eine Supportanfrage erstellen. Außerdem besteht die Möglichkeit automatische Gruppen anzulegen, um die Supportanfragen voneinander zu trennen.';
$string['to_group'] = 'An';
$string['toggle'] = 'Kurssupportforum';
$string['toggle:central'] = 'Zentrales Supportforum';
$string['tooltiptext'] = 'Kontakt- und Supportformular';
$string['guestmodeenabled'] = 'Gastmodus aktiv';
$string['guestmodeenabled:description'] = 'Gäste können auch Supporttickets anlegen und werden dann per Mail benachrichtigt';
$string['guestmail'] = 'Ihre E-Mail Adresse';
$string['sendmsgonset2ndlvl'] = 'Sende Nachricht an Nutzer/in wenn ein Ticket einem Support User zugewiesen wird';
$string['sendmsgonset2ndlvl:description'] = 'Sende eine E-Mail an den/die Nutzer/in wenn ein Support-User zugewiesen wird oder geändert wird.';
$string['sendoriginalrequest'] = 'Die ursprüngliche Supportanfrage an den/die Benutzer/in senden';
$string['sendoriginalrequest:description'] = 'Den Forumsbeitrag der Supportanfrage an den Benutzer, der um Unterstützung gebeten hat senden';
$string['sendsupporterassignments'] = 'Senden von Support-Benutzerzuweisungen an den Benutzer';
$string['sendsupporterassignments:description'] = 'Benachrichtigen Sie den Benutzer per E-Mail, wenn ein Support-User der Anfrage zugewiesen wurde. Jedes Mal, wenn ein neuer Benutzer zugewiesen wird, wird eine E-Mail gesendet';
$string['sendissueclosed'] = 'Eine E-Mail an den User schicken, wenn der Support-Ticketstatus auf "Erledigt" gestetzt wurde';
$string['sendissueclosed:description'] = 'Zusätztlich zum Eintrag im Support-Forum eine E-Mail an den/die Benutzer/in schicken, wenn das Support-Ticket auf Status "Erledigt" gesetzt wurde';
$string['sendrequestreceived'] = 'Senden einer E-Mail zur Bestätigung, dass die Supportanfrage eingegangen ist';
$string['sendrequestreceived:description'] = 'Benachrichtigen Sie den/die Benutzer/in ausschließlich per E-Mail (und nicht im Support-Forum), dass die Anfrage eingegangen ist. Dies dient nur der Information des Benutzers und ist für die Dokumentation des Verlaufs der Anfrage im Support-Forum nicht relevant.';
$string['trackhost'] = 'Hostnamen angeben';
$string['trackhost:description'] = 'Große Moodle-Sites nutzen möglicherweise eine Architektur mit mehreren Webhosts. Schalten Sie diese Option ein, damit der Hostname des aktiven Webhosts bei Problemen erfasst wird.';
$string['userid'] = 'UserID';
$string['userlinks'] = 'Userlinks';
$string['userlinks:description'] = 'zeige Userlinks in Taskliste';
$string['your_issues'] = 'Ihre Anfragen';
$string['webhost'] = 'Host';
$string['weburl'] = 'URL';
$string['back'] = 'Zurück';
$string['continue'] = 'Weiter';
$string['firstlvlgroupmode'] = '1st level Support Gruppen Modus';
$string['firstlvlgroupmode:description'] = 'Aktiviert den Gruppenmodus, so dass Nicht-Lehrer (andere Rollen) auf der Grundlage eines benutzerdefinierten Feldes verbunden werden und im Kursforum antworten können (stellen Sie sicher, dass Sie der Rolle "canforward2ndlevel" das Recht geben. Aktivieren Sie auch den Gruppenmodus im Kurs und aktivieren Sie getrennte Gruppen.';
$string['customfieldname'] = 'Profilfeldname für den Gruppenmodus';
$string['customfieldname:description'] = 'Profilfeldname für den Gruppenmodus';
$string['rolename'] = 'Rollenname';
$string['rolename:description'] = 'Rollenname für den 1st Level Support (z.B. teacher statt editingtecher oder eine eigens erstellte Rolle)';

/* Status */
$string['status:notstarted'] = 'Noch nicht gestartet';
$string['status:awaitinguserreply'] = 'Erwarte Antwort des/der Benutzer:in';
$string['status:ongoing'] = 'In Bearbeitung';
$string['status:awaitingsupportaction'] = 'Erwarte Bearbeitung durch Supporter:in';
$string['status:closed'] = 'Abgeschlossen';

$string['status'] = 'Status';
$string['changestatus'] = 'Status ändern';
$string['startedby'] = 'Gestartet von';
$string['invalidmail'] = 'Bitte tragen Sie eine richtige E-Mail Adresse ein.';

$string['firstlvlgroupmode'] = '1st level Support Gruppen Modus';
$string['firstlvlgroupmode:description'] = 'Aktiviert den Gruppenmodus, so dass Nicht-Lehrer (andere Rollen) auf der Grundlage eines benutzerdefinierten Feldes verbunden werden und im Kursforum antworten können (stellen Sie sicher, dass Sie der Rolle "canforward2ndlevel" das Recht geben. Aktivieren Sie auch den Gruppenmodus im Kurs und aktivieren Sie getrennte Gruppen.';
$string['customfieldname'] = 'Profilfeldname für den Gruppenmodus';
$string['customfieldname:description'] = 'Profilfeldname für den Gruppenmodus';
$string['rolename'] = 'Rollenname';
$string['rolename:description'] = 'Rollenname für den 1st Level Support (z.B. teacher statt editingtecher oder eine eigens erstellte Rolle)';


/* Events */
$string['supportadded'] = "Supportuser hinzugefgügt";
$string['supportdeleted'] = "Supportuser gelöscht";
$string['supportchanged'] = "Supportuser geändert";

/* Privacy API */
$string['privacy:metadata:edusupport:subscr'] = 'Alle beobachteten Tickets';
$string['privacy:metadata:edusupport:issues'] = 'Tickets des Supporters';
$string['privacy:metadata:edusupport:fieldid'] = 'Id';
$string['privacy:metadata:edusupport:issueid'] = 'Ticket Id';
$string['privacy:metadata:edusupport:discussionid'] = 'Forum Diskussions Id ';
$string['privacy:metadata:edusupport:userid'] = 'User Id';
$string['privacy:metadata:edusupport:supporters'] = 'Alle Supporter';
$string['privacy:metadata:edusupport:supportlvl'] = 'Supportlevel';
$string['privacy:metadata:edusupport:courseid'] = 'Kurs Id mit dem Supportforum';
$string['privacy:metadata:edusupport:currentsupporter'] = 'User Id des supportenden Users';
$string['privacy:metadata:edusupport:priority'] = 'Ticketstatus';

/* Accountmanager */
$string['possiblemanagers'] = 'Mögliche Manager';
$string['none'] = 'nichts ausgewählt';
$string['accountmanagers'] = 'Account Manager';
$string['accountmanager'] = 'Dein Account Manager';
$string['capstocheck'] = 'Rechte die geprüft werden';
$string['setaccountmanager'] = 'Setze Account Manager';

$string['furtherquestions'] = 'Da Sie eine Supportanfrage als Gastbenutzer gestellt haben, können Sie nicht antworten oder weitere Kommentare zu dieser Anfrage abgeben. Wenn Sie weitere Unterstützung wünschen, registrieren Sie sich bitte unter {$a->sitename}.';

$string['timebeforereminder'] = 'Zeit bevor Erinnerung gesendet wird';
