<?php
define("IN_MYBB", 1);
require("global.php");
error_reporting(-1);
ini_set('display_errors', 1);

global $db, $mybb, $lang;
echo (
  '<style type="text/css">
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

  echo "<h1>1. Schritt Datenbanktabelle umbennen</h1>";
  echo "<p>Jules Plugin darf nicht deinstalliert sein und meins noch nicht installiert!</p>";
  echo '<form action="" method="post">';
  echo '<input type="submit" name="database_rename" value="Datenbanktabelle umbennen">';
  echo '</form>';

  if (isset($_POST['database_rename'])) {

    $old_table_name = TABLE_PREFIX . "inplayquotes";
    $new_table_name = TABLE_PREFIX . "inplayquotes_jule";

    $query = "RENAME TABLE $old_table_name TO $new_table_name";

    if ($db->table_exists("ingamequotes_likes")) {

        $old_table_name_katja = TABLE_PREFIX . "ingamequotes_likes";
        $new_table_name_katja = TABLE_PREFIX . "ingamequotes_likes_jule";

        $db->write_query($query = "RENAME TABLE $old_table_name_katja TO $new_table_name_katja");
    }

    // Führe das Update aus
    if ($db->write_query($query)) {
        echo "Die Tabelle wurde erfolgreich umbenannt. Jules Plugin kann deinstalliert werden und dann meins installiert werden.";
    } else {
        echo "Fehler beim Umbenennen der Tabelle.";
    }


  }

  echo '<div style="width:100%; background-color: rgb(121 123 123 / 50%); display: flex; position:fixed; bottom:0;right:0; height:50px; justify-content: center; align-items:center; gap:20px;">
<div> <a href="https://github.com/little-evil-genius/inplayzitate" target="_blank">Github Rep</a></div>
<div> <b>Kontakt:</b> little.evil.genius (Discord)</div>
<div> <b>Support:</b>  <a href="LINK">SG Thread</a> oder per Discord</div>
</div>';
} else {
  echo "<h1>Kein Zugriff</h1>";
}