<?php
define("IN_MYBB", 1);
require("global.php");

global $db, $mybb, $lang;
echo (
  '<meta charset="UTF-8">
  <style type="text/css">
body {
  background-color: #efefef;
  text-align: center;
  margin: 40px 100px;
  font-family: Verdana;
}
fieldset {
  width: 50%;
  margin: auto;
  margin-bottom: 20px;
}
legend {
  font-weight: bold;
}
</style>'
);

if ($mybb->usergroup['canmodcp'] == 1 AND !$db->table_exists("inplayquotes_reactions_settings")) {
  echo "<h1>Update Script für die Übertragung der Inplayzitate</h1>";
  echo "<p>Ehemalige Inplayzitate von dem Plugin Inplayquotes 2.0 oder 3.0 von Sparksfly mit einem Klick übertragen</p>";

  echo "<h1>Datenbanktabelle der Inplayzitate sichern</h1>";
  echo "<p>Jules Plugin darf nicht deinstalliert sein und meins noch nicht installiert!</p>";
  echo '<form action="" method="post">';
  echo '<input type="submit" name="database_copy" value="Datenbanktabelle sichern">';
  echo '</form>';

  if (isset($_POST['database_copy'])) {
    
    $db->query("CREATE TABLE ".TABLE_PREFIX."inplayquotes_jule(
        `qid` int(11) NOT NULL AUTO_INCREMENT,
        `uid` int(11) NOT NULL,
        `tid` int(11) NOT NULL,
        `pid` int(11) NOT NULL,
        `timestamp` int(21) NOT NULL,
        `quote` varchar(500) COLLATE utf8_general_ci NOT NULL,
        PRIMARY KEY (`qid`),
        KEY `qid` (`qid`)
    ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci AUTO_INCREMENT=1 ");

    $result = $db->query("SELECT * FROM ".TABLE_PREFIX."inplayquotes");
    while($row = $db->fetch_array($result)) {
        $quote = $db->escape_string($row['quote']);
        $db->query("INSERT INTO ".TABLE_PREFIX."inplayquotes_jule (uid, tid, pid, timestamp, quote)
                    VALUES ('".$row['uid']."', '".$row['tid']."', '".$row['pid']."', '".$row['timestamp']."', '$quote')");
    }

    echo "Datenbanktabelle erfolgreich gesichert und übertragen!";
}

  echo '<div style="width:100%; background-color: rgb(121 123 123 / 50%); display: flex; position:fixed; bottom:0;right:0; height:50px; justify-content: center; align-items:center; gap:20px;">
<div> <a href="https://github.com/little-evil-genius/inplayzitate" target="_blank">Github Rep</a></div>
<div> <b>Kontakt:</b> little.evil.genius (Discord)</div>
<div> <b>Support:</b>  <a href="https://storming-gates.de/showthread.php?tid=1039057">SG Thread</a> oder per Discord</div>
</div>';
} else {
  echo "<h1>Kein Zugriff</h1>";
}
