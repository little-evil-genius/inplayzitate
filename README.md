# Inplayzitate
Das Zitat-Plugin ermöglicht es den Usern, Zitate aus einem bestimmten Bereich des Forums einzureichen. Alle Zitate werden auf einer Übersichtsseite angezeigt, wobei ein zufälliges Zitat auf der Indexseite erscheint. User können je nach Einstellung Reaktionen für die einzelnen Zitate vergeben. Die Reaktionen können im Administrationsbereich (ACP) konfiguriert werden.<br>
<br>
Die Übersichtsseite bietet verschiedene Filtermöglichkeiten und kann auch in mehrere Seiten unterteilt werden. In den Einstellungen des Plugins kann festgelegt werden, ob das zufällige Zitat auf der Indexseite zwischen den Foren angezeigt werden soll. Die Anzeige auf dem Index kann pro Design individuell entschieden werden, es muss nur die entsprechende Variable eingesetzt werden. (Das Forum bleibt dennoch gleich.)<br>
<br>
Die angezeigte Grafik der zitierten Charaktere kann entweder der Avatar, eine Grafik aus dem Upload-System oder ein Link aus einem Profilfeld/Steckbrieffeld sein.<br>
Zusätzlich zum Szenentitel und Postdatum können auch nach Wunsch alle weiteren Informationen zur Szene, aus der das Zitat stammt, im Template ausgegeben werden. Es besteht auch die Möglichkeit, alle Profilfelder/Steckbriefe des zitierten Charakters anzuzeigen.
<br>
<br>
Die Idee zu den Reaktionen auf die Inplayzitate wurde von dem Plugin <a href="https://github.com/MattRogowski/MyReactions">MyReactions</a> von <a href="https://matt.rogow.ski/">Matt Rogowski</a> inspiriert. Die mitgelieferten Bilder für die Reaktionen stammen auch aus diesem Plugin.

# Vorrausetzung
- Das ACP Modul <a href="https://github.com/little-evil-genius/rpgstuff_modul" target="_blank">RPG Stuff</a> <b>muss</b> vorhanden sein.
- Der <a href="https://doylecc.altervista.org/bb/downloads.php?dlid=26&cat=2" target="_blank">Accountswitcher</a> von doylecc <b>muss</b> installiert sein.
  
# Datenbank-Änderungen
hinzugefügte Tabelle:
- PRÄFIX_inplayquotes
- PRÄFIX_inplayquotes_reactions
- PRÄFIX_inplayquotes_reactions_settings

# Neue Sprachdateien
- deutsch_du/admin/inplayquotes.lang.php
- deutsch_du/inplayquotes.lang.php

# Einstellungen
- Erlaubte Gruppen
- Zitate Bereich
- ausgeschlossene Foren
- Benachrichtigungssystem
- Gästeberechtigung
- Zitate pro Seite
- Filteroptionen
- Grafiktyp
- Identifikator von dem Upload-Element
- FID von dem Profilfeld
- Identifikator von dem Steckbrieffeld
- Standard-Grafik
- Gäste-Ansicht
- Profilfeldsystem
- Overview: Reaktionen auf Zitate
- Vergabe der Reaktionen
- Spielername
- Szeneninformation ausgeben
- Listen PHP
- Listen Menü
- zufälliges Zitat im Profil
- Zitat löschen
- Anzeige auf dem Index<br>
<br>
<b>HINWEIS:</b><br>
Das Plugin ist kompatibel mit den klassischen Profilfeldern von MyBB und/oder dem <a href="https://github.com/katjalennartz/application_ucp">Steckbrief-Plugin von Risuena</a>.<br>
Auch ist das Plugin mit verschiedenen Inplaytrackern/Szenentrackern kompatibel: mit dem <a href="https://github.com/its-sparks-fly/Inplaytracker-2.0">Inplaytracker 2.0 von sparks fly</a>, dem Nachfolger <a href="https://github.com/ItsSparksFly/mybb-inplaytracker">Inplaytracker 3.0 von sparks fly</a>, dem <a href="https://github.com/little-evil-genius/Inplayszenen-Manager">Inplayszenen-Manager von mir (little.evil.genius)</a>, dem <a href="https://storming-gates.de/showthread.php?tid=1023729&pid=537783#pid537783">Szenentracker von Risuena</a>, dem <a href="https://github.com/Ales12/inplaytracker">Inplaytracker 1.0 von Ales</a> und dem Nachfolger <a href="https://github.com/Ales12/inplaytracker-2.0">Inplaytracker 2.0 von Ales</a>.<br>
Genauso kann auch das Listen-Menü angezeigt werden, wenn man das <a href="https://github.com/ItsSparksFly/mybb-lists">Automatische Listen-Plugin von sparks fly</a> verwendet. Beides muss nur vorher eingestellt werden.

# Neue Template-Gruppe innerhalb der Design-Templates
- Inplayzitate

# Neues Templates (nicht global!)
- inplayquotes_index
- inplayquotes_index_bit
- inplayquotes_index_bit_none
- inplayquotes_memberprofile
- inplayquotes_memberprofile_bit
- inplayquotes_memberprofile_bit_none
- inplayquotes_overview
- inplayquotes_overview_bit
- inplayquotes_overview_filter
- inplayquotes_overview_filter_bit
- inplayquotes_postbit
- inplayquotes_postbit_popup
- inplayquotes_reactions
- inplayquotes_reactions_add
- inplayquotes_reactions_add_popup
- inplayquotes_reactions_add_popup_image
- inplayquotes_reactions_reacted
- inplayquotes_reactions_reacted_image
- inplayquotes_reactions_stored<br><br>
<b>HINWEIS:</b><br>
Alle Templates wurden größtenteils ohne Tabellen-Struktur gecodet. Das Layout wurde auf ein MyBB Default Design angepasst.

# Neue Variable
- postbit_classic & postbit: {$post['button_inplayquotes']}
- index: {$inplayquotes_index}
- member_profile: {$inplayquotes_memberprofile}
- forumbit_depth1_cat oder forumbit_depth2_cat: {$forum['inplayquotes_index']}

# Neues CSS - inplayquotes.css
Es wird automatisch in jedes bestehende und neue Design hinzugefügt. Man sollte es einfach einmal abspeichern - auch im Default. Nach einem MyBB Upgrade fehlt der Stylesheets im Masterstyle? Im ACP Modul "RPG Erweiterungen" befindet sich der Menüpunkt "Stylesheets überprüfen" und kann von hinterlegten Plugins den Stylesheet wieder hinzufügen.
<blockquote>
  
        .inplayquotes_popup {
            background: #ffffff;
            width: 100%;
            margin: auto auto;
            border: 1px solid #ccc;
            padding: 1px;
            -moz-border-radius: 7px;
            -webkit-border-radius: 7px;
            border-radius: 7px;
        }
        
        .inplayquotes_popup-headline {
            background: #0066a2 url(../../../images/thead.png) top left repeat-x;
            color: #ffffff;
            border-bottom: 1px solid #263c30;
            padding: 8px;
            -moz-border-radius-topleft: 6px;
            -moz-border-radius-topright: 6px;
            -webkit-border-top-left-radius: 6px;
            -webkit-border-top-right-radius: 6px;
            border-top-left-radius: 6px;
            border-top-right-radius: 6px;
        }
        
        .inplayquotes_popup-quoteInfo {
            background: #f5f5f5;
            border: 1px solid;
            border-color: #fff #ddd #ddd #fff;
            padding: 5px 0;
        }
        
        .inplayquotes_popup-textarea {
            background: #f5f5f5;
            border: 1px solid;
            border-color: #fff #ddd #ddd #fff;
            text-align: center;
            padding: 5px 0;
        }
        
        .inplayquotes_popup-button {
            border-top: 1px solid #fff;
            padding: 6px;
            background: #ddd;
            color: #666;
            -moz-border-radius-bottomleft: 6px;
            -webkit-border-bottom-left-radius: 6px;
            border-bottom-left-radius: 6px;
            -moz-border-radius-bottomright: 6px;
            -webkit-border-bottom-right-radius: 6px;
            border-bottom-right-radius: 6px;
            border-bottom: 0;
            text-align: center;
        }
        
        #inplayquotes_overview {
            box-sizing: border-box;
            background: #fff;
            border: 1px solid #ccc;
            padding: 1px;
            -moz-border-radius: 7px;
            -webkit-border-radius: 7px;
            border-radius: 7px;
        }
        
        .inplayquotes-headline {
            height: 50px;
            width: 100%;
            font-size: 30px;
            display: flex;
            justify-content: center;
            align-items: center;
            font-weight: bold;
            text-transform: uppercase;
            background: #0066a2 url(../../../images/thead.png) top left repeat-x;
            color: #ffffff;
            -moz-border-radius-topleft: 6px;
            -moz-border-radius-topright: 6px;
            -webkit-border-top-left-radius: 6px;
            -webkit-border-top-right-radius: 6px;
            border-top-left-radius: 6px;
            border-top-right-radius: 6px;
        }
        
        .inplayquotes-filter {
            background: #f5f5f5;
        }
        
        .inplayquotes-filter-headline {
            background: #0f0f0f url(../../../images/tcat.png) repeat-x;
            color: #fff;
            border-top: 1px solid #444;
            border-bottom: 1px solid #000;
            padding: 6px;
            font-size: 12px;
        }
        
        .inplayquotes-filteroptions {
            display: flex;
            justify-content: space-around;
            width: 90%;
            margin: 10px auto;
            gap: 5px;
        }
        
        .inplayquotes_overview_filter_bit {
            width: 100%;
            text-align: center;
        }
        
        .inplayquotes-filter-bit-headline {
            padding: 6px;
            background: #ddd;
            color: #666;
        }
        
        .inplayquotes-filter-bit-dropbox {
            margin: 5px;
        }
        
        .inplayquotes-body {
            background: #f5f5f5;
            padding: 20px 40px;
            text-align: justify;
            line-height: 180%;
            -moz-border-radius-bottomright: 6px;
            -webkit-border-bottom-right-radius: 6px;
            border-bottom-right-radius: 6px;
            -moz-border-radius-bottomleft: 6px;
            -webkit-border-bottom-left-radius: 6px;
            border-bottom-left-radius: 6px;
        }
        
        .inplayquotes_overview_bit {
            width: 100%;
            display: flex;
            margin: 20px 0;
            flex-wrap: nowrap;
            align-items: center;
        }
        
        .inplayquotes_overview_bit:nth-child(even) {
            flex-direction: row-reverse;
        }
        
        .inplayquotes_overview_bit_avatar {
            width: 10%;
            text-align: center;
        }
        
        .inplayquotes_overview_bit_avatar img {
            border-radius: 100%;
            border: 2px solid #0071bd;
            width: 100px;
        }
        
        .inplayquotes_overview_bit_container {
            width: 90%;
        }
        
        .inplayquotes_overview_bit_quote {
            width: 95%;
            margin: auto;
            font-size: 15px;
            text-align: justify;
            margin-bottom: 10px;
        }
        
        .inplayquotes_overview_bit_footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .inplayquotes_overview_bit_reaction a:link,
        .inplayquotes_overview_bit_reaction a:active,
        .inplayquotes_overview_bit_reaction a:visited,
        .inplayquotes_overview_bit_reaction a:hover{
            background:#ddd;
            border-radius: 0;
            color: #666;
            font-size: 9px;
            text-transform: uppercase;
            padding: 7px 5px;
        } 
        
        .inplayquotes_popup-quotepreview {
            background: #f5f5f5;
            border: 1px solid;
            border-color: #fff #ddd #ddd #fff;
            padding: 5px 0;
        }
        
        .inplayquotes_popup-subline {
            background: #0f0f0f url(../../../images/tcat.png) repeat-x;
            color: #fff;
            border-top: 1px solid #444;
            border-bottom: 1px solid #000;
            padding: 6px;
            font-size: 12px;
        }
        
        .inplayquotes_popup-reactions {
            background: #f5f5f5;
            border: 1px solid;
            border-color: #fff #ddd #ddd #fff;
            text-align: center;
            padding: 5px 0;
            -moz-border-radius-bottomright: 6px;
            -webkit-border-bottom-right-radius: 6px;
            border-bottom-right-radius: 6px;
            -moz-border-radius-bottomleft: 6px;
            -webkit-border-bottom-left-radius: 6px;
            border-bottom-left-radius: 6px;
        }
        
        .inplayquotes_popup-reactions img {
            width: 24px;
            height: 24px;
            padding: 5px;
            cursor: pointer;
        }
        
        .inplayquotes_overview_bit_reaction_bit {
            display: flex; 
            gap: 5px;
        }
        
        .inplayquotes_overview_bit_reaction-reacted {
            margin-top: 5px;
        }
        
        .inplayquotes_overview_bit_reaction-reacted img {
            width: 16px;
            height: 16px;
        }
        
        .inplayquotes_overview_bit_reaction-reacted a:link,
        .inplayquotes_overview_bit_reaction-reacted a:active,
        .inplayquotes_overview_bit_reaction-reacted a:visited,
        .inplayquotes_overview_bit_reaction-reacted a:hover{
                background: none;
                color: #0072BC;
                font-size: 10px;
                text-transform: none;
                padding: 0;
        }
        
        .inplayquotes_overview_bit_reaction_images {
            background: #ddd;
            border-radius: 0;
            color: #666;
            font-size: 9px;
            text-transform: uppercase;
            padding: 0 5px;
            display: flex;
            flex-wrap: nowrap;
            align-items: center;
            justify-content: center;
            gap: 5px;
        }
        
        .inplayquotes_overview_bit_reaction_images img {
            width: 16px;
            height: 16px;
        }
        
        .inplayquotes_overview_bit_reactions_delete a:link, 
        .inplayquotes_overview_bit_reactions_delete a:active, 
        .inplayquotes_overview_bit_reactions_delete a:visited, 
        .inplayquotes_overview_bit_reactions_deletea:hover {
            background: none;
            border-radius: 0;
            color: #0072BC;
            font-size: 9px;
            text-transform: uppercase;
            padding: 0;
        }
        
        .inplayquotes_overview_bit_user {
            text-align: right;
            line-height: 15px;
        }
        
        .inplayquotes_overview_bit_user b {
            text-transform: uppercase;
        }
        
        .inplayquotes_overview_bit_user span {
            font-style: italic;
            font-size: 11px;
        }
        
        .inplayquotes_index {
            background: #fff;
            width: 100%;
            margin: auto auto;
            border: 1px solid #ccc;
            padding: 1px;
            -moz-border-radius: 7px;
            -webkit-border-radius: 7px;
            border-radius: 7px;
        }
        
        .inplayquotes_index-headline {
            background: #0066a2 url(../../../images/thead.png) top left repeat-x;
            color: #ffffff;
            border-bottom: 1px solid #263c30;
            padding: 8px;
            -moz-border-radius-topleft: 6px;
            -moz-border-radius-topright: 6px;
            -webkit-border-top-left-radius: 6px;
            -webkit-border-top-right-radius: 6px;
            border-top-left-radius: 6px;
            border-top-right-radius: 6px;
        }
        
        .inplayquotes_index-allquotes {
            border-top: 1px solid #fff;
            padding: 6px;
            background: #ddd;
            color: #666;
            text-align: right;
            -moz-border-radius-bottomright: 6px;
            -webkit-border-bottom-right-radius: 6px;
            border-bottom-right-radius: 6px;
            -moz-border-radius-bottomleft: 6px;
            -webkit-border-bottom-left-radius: 6px;
            border-bottom-left-radius: 6px;
        }
        
        .inplayquotes_index_bit {
            background: #f5f5f5;
            border: 1px solid;
            border-color: #fff #ddd #ddd #fff;
            padding: 5px 10px;
            display: flex;
            flex-wrap: nowrap;
            align-items: center;
        }
        
        .inplayquotes_index_bit_avatar {
            width: 10%;
            text-align: center;
        }
        
        .inplayquotes_index_bit_avatar img {
            border-radius: 100%;
            border: 2px solid #0071bd;
            width: 100px;
        }
        
        .inplayquotes_index_bit_container {
            width: 90%;
        }
        
        .inplayquotes_index_bit_quote {
            width: 95%;
            margin: auto;
            font-size: 15px;
            text-align: justify;
            margin-bottom: 10px;
        }
        
        .inplayquotes_index_bit_footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .inplayquotes_index_bit_user {
            text-align: right;
            line-height: 15px;
        }
        
        .inplayquotes_index_bit_user b {
            text-transform: uppercase;
        }
        
        .inplayquotes_index_bit_user span {
            font-style: italic;
            font-size: 11px;
        }
        
        .inplayquotes_memberprofile {
            background: #fff;
            margin: auto auto;
            border: 1px solid #ccc;
            padding: 1px;
            -moz-border-radius: 7px;
            -webkit-border-radius: 7px;
            border-radius: 7px;
        }
        
        .inplayquotes_memberprofile-headline {
            background: #0066a2 url(../../../images/thead.png) top left repeat-x;
            color: #ffffff;
            border-bottom: 1px solid #263c30;
            padding: 8px;
            -moz-border-radius-topleft: 6px;
            -moz-border-radius-topright: 6px;
            -webkit-border-top-left-radius: 6px;
            -webkit-border-top-right-radius: 6px;
            border-top-left-radius: 6px;
            border-top-right-radius: 6px;
        }
        
        .inplayquotes_memberprofile-allquotes {
            border-top: 1px solid #fff;
            padding: 6px;
            background: #ddd;
            color: #666;
            text-align: right;
            -moz-border-radius-bottomright: 6px;
            -webkit-border-bottom-right-radius: 6px;
            border-bottom-right-radius: 6px;
            -moz-border-radius-bottomleft: 6px;
            -webkit-border-bottom-left-radius: 6px;
            border-bottom-left-radius: 6px;
        }
        .inplayquotes_memberprofile_bit {
            background: #f5f5f5;
            border: 1px solid;
            border-color: #fff #ddd #ddd #fff;
            padding: 5px 10px;
        }
        
        .inplayquotes_memberprofile_bit_quote {
            width: 95%;
            margin: auto;
            font-size: 15px;
            text-align: justify;
            margin-bottom: 10px;
        }
        
        .inplayquotes_memberprofile_bit_footer {
            text-align: right;
            line-height: 15px;
        }
        
        .inplayquotes_memberprofile_bit_footer span {
            text-transform: uppercase;
            font-style: italic;
            font-size: 11px;
        }
</blockquote>

# Benutzergruppen-Berechtigungen setzen
Damit alle Admin-Accounts Zugriff auf die Verwaltung der Reaktionsmöglichkeiten haben im ACP, müssen unter dem Reiter Benutzer & Gruppen » Administrator-Berechtigungen » Benutzergruppen-Berechtigungen die Berechtigungen einmal angepasst werden. Die Berechtigungen für die Inplayzitate befinden sich im Tab 'RPG Erweiterungen'.

# Account löschen
Damit die Löschung der Zitate in der Datenbank richtig funktioniert, wenn eingestellt, müssen Accounts über das Popup "Optionen" im ACP gelöscht werden. Im ACP werden alle Accounts unter dem Reiter Benutzer & Gruppen > Benutzer aufgelistet. Und bei jedem Account befindet sich rechts ein Optionen-Button. Wenn man diesen drückt, erscheint eine Auswahl von verschiedenen Möglichkeiten.

# Links
<b>ACP</b><br>
index.php?module=config-inplayquotes<br>
<br>
<b>Übersicht der Zitate</b><br>
misc.php?action=inplayquotes

# extra Variablen
Es gibt die Möglichkeit in den Templates "inplayquotes_index_bit" und "inplayquotes_overview_bit" die Charakternamen verschieden darzustellen:
- normaler Name: {$charactername}
- normaler Name als Link: {$charactername_link}
- mit Gruppenfarbe: {$charactername_formated}
- mit Gruppenfarben als Link: {$charactername_formated_link}
- Vorname: {$charactername_first} & Nachname: {$charactername_last}

# Szenen- & Charakterinformationen
Es gibt die Möglichkeit in den Templates "inplayquotes_index_bit", "inplayquotes_overview_bit" und "inplayquotes_memberprofile_bit" weitere Informationen über die Szene oder den Account/Charakter selbst:<br>
<br>
<b>Szeneninformationen</b><br>
Mit der Variable {$scenefield['XX']} können die verschiedenen Informationen aus den DB Tabellen der Tracker ausgegeben werden. Ich habe mich an die aktuellen (07.05.2024) Github Versionen und deren Tabellenstruktur gehalten. Solltet ihr etwas verändert haben, müsst ihr in die PHP und das entsprechende Array $scene_fields für das genutzte Trackersystem anpassen.<br>
Entsprechende Umformungen wie das die Namen der Charaktere in der Szene angezeigt werden, sind in der PHP Datei vorhanden.
<br>
<b>Inplaytracker 2.0 von sparks fly</b>
Mögliche Felder (das XX durch den Namen vom Feld ersetzen:<br>
$scene_fields = ["partners", "ipdate", "iport", "ipdaytime", "openscene", "postorder"];<br>
<br>
<b>Inplaytracker 3.0 von sparks fly</b>
Mögliche Felder (das XX durch den Namen vom Feld ersetzen:<br>
$scene_fields = ["location", "date", "shortdesc", "openscene", "partners"];<br>
<br>
<b>Szenentracker von risuena</b>
Mögliche Felder (das XX durch den Namen vom Feld ersetzen:<br>
$scene_fields = ["scenetracker_date", "scenetracker_place", "scenetracker_user", "scenetracker_trigger"];<br>
<br>
<b>Inplaytracker 1.0 von Ales</b><br>
Mögliche Felder (das XX durch den Namen vom Feld ersetzen:<br>
$scene_fields = ["spieler", "date", "ort", "ip_time"];<br>
<br>
<b>Inplaytracker 2.0 von Ales</b><br>
Mögliche Felder (das XX durch den Namen vom Feld ersetzen:<br>
$scene_fields = ["charas", "date", "time", "place"];<br>
<br>
<b>Inplaytracker von little.evil.genius</b><br>
Mögliche Felder (das XX durch den Namen vom Feld ersetzen:<br>
$scene_fields = ["partners", "partners_username", "date", "trigger_warning", "scenetype", "postorder"];<br>
Zusätzlich können die individuellen Felder mit dem Identifikator für die Felder jeweils ausgegeben werden.<br>
<br>
<br>
<b>Charakterinformationen</b><br>
Mit den Variablen {$characterfield['identifikator']} oder {$characterfield['fidX']} können alle Informationen zu dem Account/Charakter aus den DB Tabellen userfields (Profilfelder) und application_ucp_userfields (Steckbrieffelder) ausgegeben werden. 

# Inplayzitate von sparks fly 2.0 oder 3.0 übertragen
1. Backup machen (DB und Webspace Dateien) 
2. Nur das Update Script hochladen (Hauptverzeichnis vom Forum - kein Unterordner, wie inc!)
3. LINK/update_inplayquotes.php aufrufen und Button drücken
4. im ACP das Inplayzitate Plugin deinstallieren
5. Dateien vom alten Inplayzitate Plugin auf dem Webspace entfernen
6. die Dateien von diesem Plugin hochladen
7. im ACP installieren

# Demo
<img src="https://stormborn.at/plugins/inplayquotes_index.png">
<img src="https://stormborn.at/plugins/inplayquotes_overview2.png">
<img src="https://stormborn.at/plugins/inplayquotes_reaction.png">
