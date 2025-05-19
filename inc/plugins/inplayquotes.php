<?php

// The idea for the reactions to the in-play quotes was inspired by the MyReactions plugin (https://github.com/MattRogowski/MyReactions) by Matt Rogowski. 
// The images provided for the reactions also come from this plugin.

// Direktzugriff auf die Datei aus Sicherheitsgründen sperren
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

// HOOKS
$plugins->add_hook("admin_config_settings_change", "inplayquotes_settings_change");
$plugins->add_hook("admin_settings_print_peekers", "inplayquotes_settings_peek");
$plugins->add_hook("admin_rpgstuff_action_handler", "inplayquotes_admin_rpgstuff_action_handler");
$plugins->add_hook("admin_rpgstuff_permissions", "inplayquotes_admin_rpgstuff_permissions");
$plugins->add_hook("admin_rpgstuff_menu", "inplayquotes_admin_rpgstuff_menu");
$plugins->add_hook("admin_load", "inplayquotes_admin_manage");
$plugins->add_hook('admin_rpgstuff_update_stylesheet', 'inplayquotes_admin_update_stylesheet');
$plugins->add_hook('admin_rpgstuff_update_plugin', 'inplayquotes_admin_update_plugin');
$plugins->add_hook("admin_user_users_delete_commit_end", "inplayquotes_user_delete");
$plugins->add_hook("postbit", "inplayquotes_postbit");
$plugins->add_hook("misc_start", "inplayquotes_misc");
$plugins->add_hook("global_intermediate", "inplayquotes_index");
$plugins->add_hook("member_profile_end", "inplayquotes_profile");
$plugins->add_hook("fetch_wol_activity_end", "inplayquotes_online_activity");
$plugins->add_hook("build_friendly_wol_location_end", "inplayquotes_online_location");
if(class_exists('MybbStuff_MyAlerts_AlertTypeManager')) {
    $plugins->add_hook("global_start", "inplayquotes_alerts");
}
$plugins->add_hook("build_forumbits_forum", "inplayquotes_forumbits");
 
// Die Informationen, die im Pluginmanager angezeigt werden
function inplayquotes_info(){
	return array(
		"name"		=> "Inplayzitate",
		"description"	=> "User können denkwürdige Inplaymomente als Zitat speichern",
		"website"	=> "https://github.com/little-evil-genius/inplayzitate",
		"author"	=> "little.evil.genius",
		"authorsite"	=> "https://storming-gates.de/member.php?action=profile&uid=1712",
		"version"	=> "1.1",
		"compatibility" => "18*"
	);
}
 
// Diese Funktion wird aufgerufen, wenn das Plugin installiert wird (optional).
function inplayquotes_install(){
    
    global $db, $lang;

    // SPRACHDATEI
    $lang->load("inplayquotes");

    // RPG Stuff Modul muss vorhanden sein
    if (!file_exists(MYBB_ADMIN_DIR."/modules/rpgstuff/module_meta.php")) {
		flash_message($lang->inplayquotes_error_rpgstuff, 'error');
		admin_redirect('index.php?module=config-plugins');
	}

    // Accountswitcher muss vorhanden sein
    if (!function_exists('accountswitcher_is_installed')) {
		flash_message($lang->inplayquotes_error_accountswitcher, 'error');
		admin_redirect('index.php?module=config-plugins');
	}

    // DATENBANKEN ERSTELLEN
    inplayquotes_database();

    // STANDARD REAKTIONEN
    $first_reactions = ["Scream", "Smirk", "Fire", "Laughing", "HeartEyes", "Heart"];
    foreach ($first_reactions as $reaction) {
        $standard_reactions = array(
            "name" => $db->escape_string($reaction),
            "image" => $db->escape_string("images/inplayquotes/".$reaction.".png"),
        );
        
        $db->insert_query("inplayquotes_reactions_settings", $standard_reactions);
    }

	// EINSTELLUNGEN HINZUFÜGEN
    $maxdisporder = $db->fetch_field($db->query("SELECT MAX(disporder) FROM ".TABLE_PREFIX."settinggroups"), "MAX(disporder)");
	$setting_group = array(
		'name'          => 'inplayquotes',
		'title'         => 'Inplayzitate',
		'description'   => 'Einstellungen für die Inplayzitate',
		'disporder'     => $maxdisporder+1,
		'isdefault'     => 0
	);
	$db->insert_query("settinggroups", $setting_group); 
    inplayquotes_settings();
	rebuild_settings();

    // TEMPLATES ERSTELLEN
    // Template Gruppe für jedes Design erstellen
    $templategroup = array(
        "prefix" => "inplayquotes",
        "title" => $db->escape_string("Inplayzitate"),
    );
    $db->insert_query("templategroups", $templategroup);
    inplayquotes_templates();

    // STYLESHEET HINZUFÜGEN
	require_once MYBB_ADMIN_DIR."inc/functions_themes.php";
    // Funktion
    $css = inplayquotes_stylesheet();
    $sid = $db->insert_query("themestylesheets", $css);
	$db->update_query("themestylesheets", array("cachefile" => "inplayquotes.css"), "sid = '".$sid."'", 1);

	$tids = $db->simple_select("themes", "tid");
	while($theme = $db->fetch_array($tids)) {
		update_theme_stylesheet_list($theme['tid']);
	}

    // Übertragung von den Daten von Jules Inplayzitaten
    if ($db->table_exists("inplayquotes_jule")) {
        // Führe den Datenübertrag aus
        $db->query("INSERT INTO `".TABLE_PREFIX."inplayquotes` (qid, uid, username, tid, pid, date, quote) SELECT qid,uid,'',tid,pid,timestamp,quote FROM `".TABLE_PREFIX."inplayquotes_jule`");
        
        // Username noch rausfischen
        $alluids_query = $db->query("SELECT uid FROM ".TABLE_PREFIX."inplayquotes GROUP BY uid");

        $all_uids = [];
        while($alluid = $db->fetch_array($alluids_query)) {
            $user = get_user($alluid['uid']);
            // Vorhanden -> daten aus der Users Tabelle
            if (!empty($user)) {
                $all_uids[$alluid['uid']] = $user['username'];
            } else {
                $all_uids[$alluid['uid']] = "Gast";
            }
        }

        foreach ($all_uids as $uid => $username) {
            $into_username = array(
                "username" => $db->escape_string($username),
            );
            $db->update_query("inplayquotes", $into_username, "uid='".$uid."'");
        }

        // alte Tabelle löschen
        $db->drop_table("inplayquotes_jule");
    }
}
 
// Funktion zur Überprüfung des Installationsstatus; liefert true zurürck, wenn Plugin installiert, sonst false (optional).
function inplayquotes_is_installed(){

    global $db, $cache, $mybb;
  
	if($db->table_exists("inplayquotes"))  {
		return true;
	}
	return false;
} 
 
// Diese Funktion wird aufgerufen, wenn das Plugin deinstalliert wird (optional).
function inplayquotes_uninstall(){
	
    global $db, $cache;

    //DATENBANK LÖSCHEN
    if($db->table_exists("inplayquotes"))
    {
        $db->drop_table("inplayquotes");
    }
    if($db->table_exists("inplayquotes_reactions"))
    {
        $db->drop_table("inplayquotes_reactions");
    }
    if($db->table_exists("inplayquotes_reactions_settings"))
    {
        $db->drop_table("inplayquotes_reactions_settings");
    }
    
    // EINSTELLUNGEN LÖSCHEN
    $db->delete_query('settings', "name LIKE 'inplayquotes%'");
    $db->delete_query('settinggroups', "name = 'inplayquotes'");

    rebuild_settings();

    // TEMPLATGRUPPE LÖSCHEN
    $db->delete_query("templategroups", "prefix = 'inplayquotes'");

    // TEMPLATES LÖSCHEN
    $db->delete_query("templates", "title LIKE 'inplayquotes%'");

}
 
// Diese Funktion wird aufgerufen, wenn das Plugin aktiviert wird.
function inplayquotes_activate(){

    global $db, $cache;

	inplayquotes_cache();

    include MYBB_ROOT."/inc/adminfunctions_templates.php";
    
    // VARIABLEN EINFÜGEN
	find_replace_templatesets("postbit_classic", "#".preg_quote('{$post[\'button_edit\']}')."#i", '{$post[\'button_inplayquotes\']}{$post[\'button_edit\']}');
	find_replace_templatesets("postbit", "#".preg_quote('{$post[\'button_edit\']}')."#i", '{$post[\'button_inplayquotes\']}{$post[\'button_edit\']}');
	find_replace_templatesets("index", "#".preg_quote('{$footer}')."#i", '{$inplayquotes_index}{$footer}');
	find_replace_templatesets("member_profile", "#".preg_quote('{$awaybit}')."#i", '{$awaybit}{$inplayquotes_memberprofile}');

    // MyALERTS STUFF
	if(class_exists('MybbStuff_MyAlerts_AlertTypeManager')) {
		$alertTypeManager = MybbStuff_MyAlerts_AlertTypeManager::getInstance();

		if (!$alertTypeManager) {
			$alertTypeManager = MybbStuff_MyAlerts_AlertTypeManager::createInstance($db, $cache);
		}

        // Alert fürs Zitat hinzugefügt
		$alertType = new MybbStuff_MyAlerts_Entity_AlertType();
		$alertType->setCode('inplayquotes_add_quote'); // The codename for your alert type. Can be any unique string.
		$alertType->setEnabled(true);
		$alertType->setCanBeUserDisabled(true);

		$alertTypeManager->add($alertType);

        // Alert fürs Reaktion hinzufügen
        $alertType = new MybbStuff_MyAlerts_Entity_AlertType();
		$alertType->setCode('inplayquotes_add_reaction'); // The codename for your alert type. Can be any unique string.
		$alertType->setEnabled(true);
		$alertType->setCanBeUserDisabled(true);

		$alertTypeManager->add($alertType);
    }

}
 
// Diese Funktion wird aufgerufen, wenn das Plugin deaktiviert wird.
function inplayquotes_deactivate(){
	global $db, $cache;

	include MYBB_ROOT."/inc/adminfunctions_templates.php";

    // VARIABLEN ENTFERNEN
	find_replace_templatesets("postbit_classic", "#".preg_quote('{$post[\'button_inplayquotes\']}')."#i", '', 0);
	find_replace_templatesets("postbit", "#".preg_quote('{$post[\'button_inplayquotes\']}')."#i", '', 0);
	find_replace_templatesets("index", "#".preg_quote('{$inplayquotes_index}')."#i", '', 0);
	find_replace_templatesets("member_profile", "#".preg_quote('{$inplayquotes_memberprofile}')."#i", '', 0);
	find_replace_templatesets("forumbit_depth1_cat", "#".preg_quote('{$forum[\'inplayquotes_index\']}')."#i", '', 0);
	find_replace_templatesets("forumbit_depth2_cat", "#".preg_quote('{$forum[\'inplayquotes_index\']}')."#i", '', 0);

    // MyALERT STUFF
    if (class_exists('MybbStuff_MyAlerts_AlertTypeManager')) {
		$alertTypeManager = MybbStuff_MyAlerts_AlertTypeManager::getInstance();

		if (!$alertTypeManager) {
			$alertTypeManager = MybbStuff_MyAlerts_AlertTypeManager::createInstance($db, $cache);
		}

		$alertTypeManager->deleteByCode('inplayquotes_add_quote');
        $alertTypeManager->deleteByCode('inplayquotes_add_reaction');
	}
}

#####################################
### THE BIG MAGIC - THE FUNCTIONS ###
#####################################

// ADMIN-CP PEEKER
function inplayquotes_settings_change(){
    
    global $db, $mybb, $inplayquotes_settings_peeker;

    $result = $db->simple_select('settinggroups', 'gid', "name='inplayquotes'", array("limit" => 1));
    $group = $db->fetch_array($result);
    $inplayquotes_settings_peeker = ($mybb->get_input('gid') == $group['gid']) && ($mybb->request_method != 'post');
}

function inplayquotes_settings_peek(&$peekers){

    global $mybb, $inplayquotes_settings_peeker;

    if ($inplayquotes_settings_peeker) {
        $peekers[] = 'new Peeker($(".setting_inplayquotes_reactions"), $("#row_setting_inplayquotes_reactions_option"),/1/,true)';
        $peekers[] = 'new Peeker($("#setting_inplayquotes_lists_type"), $("#row_setting_inplayquotes_lists_menu"), /^0/, false)';
        $peekers[] = 'new Peeker($("#setting_inplayquotes_overview_graphic"), $("#row_setting_inplayquotes_overview_graphic_uploadsystem"),/^1/,false)';
        $peekers[] = 'new Peeker($("#setting_inplayquotes_overview_graphic"), $("#row_setting_inplayquotes_overview_graphic_profilefield"),/^2/,false)';
        $peekers[] = 'new Peeker($("#setting_inplayquotes_overview_graphic"), $("#row_setting_inplayquotes_overview_graphic_characterfield"),/^3/,false)';
    }

	?>
    <script type="text/javascript">
        $(document).ready(function() {
            $('#setting_inplayquotes_playername').closest('tr').hide(); 

            $('#setting_inplayquotes_overview_filter_1, #setting_inplayquotes_overview_filter_2, #setting_inplayquotes_overview_filter_3, #setting_inplayquotes_reactions_option').change(function() {
                if ($('#setting_inplayquotes_overview_filter_1').prop('checked') || $('#setting_inplayquotes_reactions_option').val() == '0') {
                    $('#setting_inplayquotes_playername').closest('tr').show(); 
                } else {
                    $('#setting_inplayquotes_playername').closest('tr').hide(); 
                }
            });
        });
    </script>
    <?php 
}

// ADMIN-CP VERWALTUNG
// action handler fürs acp konfigurieren
function inplayquotes_admin_rpgstuff_action_handler(&$actions) {
	$actions['inplayquotes'] = array('active' => 'inplayquotes', 'file' => 'inplayquotes');
}

// Berechtigungen im ACP - Adminrechte
function inplayquotes_admin_rpgstuff_permissions(&$admin_permissions) {
	global $lang;
	
    $lang->load('inplayquotes');

	$admin_permissions['inplayquotes'] = $lang->inplayquotes_permission;

	return $admin_permissions;
}

// Menü einfügen
function inplayquotes_admin_rpgstuff_menu(&$sub_menu) {
	global $mybb, $lang;
	
    $lang->load('inplayquotes');

	$sub_menu[] = [
		"id" => "inplayquotes",
		"title" => $lang->inplayquotes_manage,
		"link" => "index.php?module=rpgstuff-inplayquotes"
	];
}

// Verwaltung im ACP
function inplayquotes_admin_manage() {

	global $mybb, $db, $lang, $page, $run_module, $action_file, $cache;

	$lang->load('inplayquotes');

    if ($page->active_action != 'inplayquotes') {
		return false;
	}

	// Add to page navigation
	$page->add_breadcrumb_item($lang->inplayquotes_manage, "index.php?module=rpgstuff-inplayquotes");

	if ($run_module == 'rpgstuff' && $action_file == 'inplayquotes') {

		// ÜBERSICHT
		if ($mybb->get_input('action') == "" || !$mybb->get_input('action')) {

			// Optionen im Header bilden
			$page->output_header($lang->inplayquotes_manage);

			// Übersichtsseite Button
			$sub_tabs['inplayquotes_overview'] = [
				"title" => $lang->inplayquotes_overview,
				"link" => "index.php?module=rpgstuff-inplayquotes",
				"description" => $lang->inplayquotes_overview_desc
			];
			// Hinzufüge Button
			$sub_tabs['inplayquotes_add'] = [
				"title" => $lang->inplayquotes_add,
				"link" => "index.php?module=rpgstuff-inplayquotes&amp;action=add",
				"description" => $lang->inplayquotes_add_desc
			];

			$page->output_nav_tabs($sub_tabs, 'inplayquotes_overview');
			
			$default_perpage = 10;
            $perpage = $mybb->get_input('perpage', MyBB::INPUT_INT);
            if(!$perpage){
                $perpage = $default_perpage;
            }

			// Page
            $reactions_count = $db->num_rows($db->simple_select("inplayquotes_reactions_settings", "rsid"));
            $pageview = $mybb->get_input('page', MyBB::INPUT_INT);
            if ($pageview && $pageview > 0) {
                $start = ($pageview - 1) * $perpage;
            } else {
                $start = 0;
                $pageview = 1;
            }
			
            $end = $start + $perpage;
            $lower = $start+1;
            $upper = $end;
            if($upper > $reactions_count) {
                $upper = $reactions_count;
            }

			// Show errors
			if (isset($errors)) {
				$page->output_inline_error($errors);
			}

			$form = new Form("index.php?module=rpgstuff-inplayquotes", "post", "", 1);
			$form_container = new FormContainer($lang->inplayquotes_overview);
			$form_container->output_row_header($lang->inplayquotes_overview_image, array("class" => "align_center", "width" => 1));
			$form_container->output_row_header($lang->inplayquotes_overview_name, array("width" => "35%"));
			$form_container->output_row_header($lang->inplayquotes_overview_option, array("class" => "align_center", "colspan" => 2));

            // Alle Reaktionen
			$query_elements = $db->query("SELECT * FROM ".TABLE_PREFIX."inplayquotes_reactions_settings
            ORDER BY name ASC
			LIMIT ".$start.", ".$perpage."
            ");

			while ($elements = $db->fetch_array($query_elements)) {

                $image = "<img src=\"../".$elements['image']."\" alt=\"\" width=\"32\" height=\"32\">";
                $form_container->output_cell($image, array("class" => "align_center", "width" => "20%"));
                $form_container->output_cell(htmlspecialchars_uni($elements['name']), array("width" => "60%"));
                $form_container->output_cell("<a href=\"index.php?module=rpgstuff-inplayquotes&amp;action=edit&amp;rsid=".$elements['rsid']."\">{$lang->inplayquotes_overview_option_edit}</a>", array("class" => "align_center", "width" => "10%"));
                $form_container->output_cell("<a href=\"index.php?module=rpgstuff-inplayquotes&amp;action=delete&amp;rsid=".$elements['rsid']."&amp;my_post_key={$mybb->post_code}\" onclick=\"return AdminCP.deleteConfirmation(this, '{$lang->inplayquotes_overview_option_delete_notice}')\">{$lang->inplayquotes_overview_option_delete}</a>", array("class" => "align_center", "width" => "10%"));
                $form_container->construct_row();
            }

			if($db->num_rows($query_elements) == 0){
                $form_container->output_cell($lang->inplayquotes_overview_no_elements, array("colspan" => 5));
                $form_container->construct_row();
			}

            $form_container->end();
            $form->end();
            // Multipage
            $search_url = htmlspecialchars_uni(
                "index.php?module=rpgstuff-inplayquotes&amp;".$mybb->get_input('perpage')
            );
            $multipage = multipage($reactions_count, $perpage, $pageview, $search_url);
            echo $multipage;
			$page->output_footer();
			exit;
        }

        // HINZUFÜGEN
        if ($mybb->get_input('action') == "add") {
    
            if ($mybb->request_method == "post") {
    
                // Check if required fields are not empty
                if (empty($mybb->get_input('name'))) {
                    $errors[] = $lang->inplayquotes_add_error_name;
                }
                if (empty($mybb->get_input('image'))) {
                    $errors[] = $lang->inplayquotes_add_error_image;
                }
    
                // No errors - insert
                if (empty($errors)) {
    
                    // Daten speichern
                    $new_reactions = array(
                        "name" => $db->escape_string($mybb->get_input('name')),
                        "image" => $db->escape_string($mybb->get_input('image')),
                    );                    
                    
                    $db->insert_query("inplayquotes_reactions_settings", $new_reactions);

                    inplayquotes_cache();

                    $mybb->input['module'] = $lang->inplayquotes_manage;
                    $mybb->input['action'] = $lang->inplayquotes_add_logadmin;
                    log_admin_action(htmlspecialchars_uni($mybb->input['name']));
    
                    flash_message($lang->inplayquotes_add_flash, 'success');
                    admin_redirect("index.php?module=rpgstuff-inplayquotes");
                }
            }
    
            $page->add_breadcrumb_item($lang->inplayquotes_add);
    
            // Editor scripts
            $page->extra_header .= '
            <link rel="stylesheet" href="../jscripts/sceditor/themes/mybb.css" type="text/css" media="all" />
            <script type="text/javascript" src="../jscripts/sceditor/jquery.sceditor.bbcode.min.js?ver=1832"></script>
            <script type="text/javascript" src="../jscripts/bbcodes_sceditor.js?ver=1832"></script>
            <script type="text/javascript" src="../jscripts/sceditor/plugins/undo.js?ver=1832"></script>
            <link href="./jscripts/codemirror/lib/codemirror.css?ver=1813" rel="stylesheet">
            <link href="./jscripts/codemirror/theme/mybb.css?ver=1813" rel="stylesheet">
            <script src="./jscripts/codemirror/lib/codemirror.js?ver=1813"></script>
            <script src="./jscripts/codemirror/mode/xml/xml.js?ver=1813"></script>
            <script src="./jscripts/codemirror/mode/javascript/javascript.js?ver=1813"></script>
            <script src="./jscripts/codemirror/mode/css/css.js?ver=1813"></script>
            <script src="./jscripts/codemirror/mode/htmlmixed/htmlmixed.js?ver=1813"></script>
            <link href="./jscripts/codemirror/addon/dialog/dialog-mybb.css?ver=1813" rel="stylesheet">
            <script src="./jscripts/codemirror/addon/dialog/dialog.js?ver=1813"></script>
            <script src="./jscripts/codemirror/addon/search/searchcursor.js?ver=1813"></script>
            <script src="./jscripts/codemirror/addon/search/search.js?ver=1821"></script>
            <script src="./jscripts/codemirror/addon/fold/foldcode.js?ver=1813"></script>
            <script src="./jscripts/codemirror/addon/fold/xml-fold.js?ver=1813"></script>
            <script src="./jscripts/codemirror/addon/fold/foldgutter.js?ver=1813"></script>
            <link href="./jscripts/codemirror/addon/fold/foldgutter.css?ver=1813" rel="stylesheet">
            ';
    
            // Build options header
            $page->output_header($lang->inplayquotes_manage." - ".$lang->inplayquotes_add);

			// Übersichtsseite Button
			$sub_tabs['inplayquotes_overview'] = [
				"title" => $lang->inplayquotes_overview,
				"link" => "index.php?module=rpgstuff-inplayquotes",
				"description" => $lang->inplayquotes_overview_desc
			];
			// Hinzufüge Button
			$sub_tabs['inplayquotes_add'] = [
				"title" => $lang->inplayquotes_add,
				"link" => "index.php?module=rpgstuff-inplayquotes&amp;action=add",
				"description" => $lang->inplayquotes_add_desc
			];
    
            $page->output_nav_tabs($sub_tabs, 'inplayquotes_add');
    
            // Show errors
            if (isset($errors)) {
                $page->output_inline_error($errors);
            } else {
                $mybb->input['image'] = 'images/inplayquotes/';
            }
    
            // Build the form
            $form = new Form("index.php?module=rpgstuff-inplayquotes&amp;action=add", "post", "", 1);
            $form_container = new FormContainer($lang->inplayquotes_add);
    
            // Name
            $form_container->output_row(
                $lang->inplayquotes_add_name,
                $form->generate_text_box('name', $mybb->get_input('name'))
            );
    
            // PHP Datei
            $form_container->output_row(
                $lang->inplayquotes_add_image,
                $lang->inplayquotes_add_image_desc,
                $form->generate_text_box('image', $mybb->get_input('image'))
            );

            $form_container->end();
            $buttons[] = $form->generate_submit_button($lang->inplayquotes_add_button);
            $form->output_submit_wrapper($buttons);
                
            $form->end();
            
            $page->output_footer();
            exit;
        }

        // BEARBEITEN
        if ($mybb->get_input('action') == "edit") {
            
            // Get the data
            $rsid = $mybb->get_input('rsid', MyBB::INPUT_INT);
            $element_query = $db->simple_select("inplayquotes_reactions_settings", "*", "rsid = '".$rsid."'");
            $element = $db->fetch_array($element_query);
    
            if ($mybb->request_method == "post") {
    
                // Check if required fields are not empty
                if (empty($mybb->get_input('name'))) {
                    $errors[] = $lang->inplayquotes_add_error_name;
                }
                if (empty($mybb->get_input('image'))) {
                    $errors[] = $lang->inplayquotes_add_error_image;
                }
    
                // No errors - insert
                if (empty($errors)) {
    
                    $rsid = $mybb->get_input('rsid', MyBB::INPUT_INT);
    
                    // Daten speichern
                    $edit_reaction = array(
                        "name" => $db->escape_string($mybb->get_input('name')),
                        "image" => $db->escape_string($mybb->get_input('image')),
                    );                    

                    $db->update_query("inplayquotes_reactions_settings", $edit_reaction, "rsid='".$rsid."'");

                    //inplayquotes_cache();

                    $mybb->input['module'] = $lang->inplayquotes_manage;
                    $mybb->input['action'] = $lang->inplayquotes_edit_logadmin;
                    log_admin_action(htmlspecialchars_uni($mybb->input['name']));
    
                    flash_message($lang->inplayquotes_edit_flash, 'success');
                    admin_redirect("index.php?module=rpgstuff-inplayquotes");
                }
            }
    
            $page->add_breadcrumb_item($lang->inplayquotes_edit);
    
            // Editor scripts
            $page->extra_header .= '
            <link rel="stylesheet" href="../jscripts/sceditor/themes/mybb.css" type="text/css" media="all" />
            <script type="text/javascript" src="../jscripts/sceditor/jquery.sceditor.bbcode.min.js?ver=1832"></script>
            <script type="text/javascript" src="../jscripts/bbcodes_sceditor.js?ver=1832"></script>
            <script type="text/javascript" src="../jscripts/sceditor/plugins/undo.js?ver=1832"></script>
            <link href="./jscripts/codemirror/lib/codemirror.css?ver=1813" rel="stylesheet">
            <link href="./jscripts/codemirror/theme/mybb.css?ver=1813" rel="stylesheet">
            <script src="./jscripts/codemirror/lib/codemirror.js?ver=1813"></script>
            <script src="./jscripts/codemirror/mode/xml/xml.js?ver=1813"></script>
            <script src="./jscripts/codemirror/mode/javascript/javascript.js?ver=1813"></script>
            <script src="./jscripts/codemirror/mode/css/css.js?ver=1813"></script>
            <script src="./jscripts/codemirror/mode/htmlmixed/htmlmixed.js?ver=1813"></script>
            <link href="./jscripts/codemirror/addon/dialog/dialog-mybb.css?ver=1813" rel="stylesheet">
            <script src="./jscripts/codemirror/addon/dialog/dialog.js?ver=1813"></script>
            <script src="./jscripts/codemirror/addon/search/searchcursor.js?ver=1813"></script>
            <script src="./jscripts/codemirror/addon/search/search.js?ver=1821"></script>
            <script src="./jscripts/codemirror/addon/fold/foldcode.js?ver=1813"></script>
            <script src="./jscripts/codemirror/addon/fold/xml-fold.js?ver=1813"></script>
            <script src="./jscripts/codemirror/addon/fold/foldgutter.js?ver=1813"></script>
            <link href="./jscripts/codemirror/addon/fold/foldgutter.css?ver=1813" rel="stylesheet">
            ';
    
            // Build options header
            $page->output_header($lang->inplayquotes_manage." - ".$lang->inplayquotes_edit);

			// Übersichtsseite Button
			$sub_tabs['inplayquotes_overview'] = [
				"title" => $lang->inplayquotes_overview,
				"link" => "index.php?module=rpgstuff-inplayquotes",
				"description" => $lang->inplayquotes_overview_desc
			];
			// Hinzufüge Button
			$sub_tabs['inplayquotes_edit'] = [
				"title" => $lang->inplayquotes_edit,
				"link" => "index.php?module=rpgstuff-inplayquotes&action=edit&rsid=".$rsid,
				"description" => $lang->inplayquotes_edit_desc
			];
    
            $page->output_nav_tabs($sub_tabs, 'inplayquotes_edit');
    
            // Show errors
            if (isset($errors)) {
                $page->output_inline_error($errors);
            }
    
            // Build the form
            $form = new Form("index.php?module=rpgstuff-inplayquotes&amp;action=edit&rsid=".$rsid, "post", "", 1);
            $form_container = new FormContainer($lang->inplayquotes_add);
            echo $form->generate_hidden_field('rsid', $rsid);
    
            // Name
            $form_container->output_row(
                $lang->inplayquotes_add_name,
                $form->generate_text_box('name', $element['name'])
            );
    
            // PHP Datei
            $form_container->output_row(
                $lang->inplayquotes_add_image,
                $lang->inplayquotes_add_image_desc,
                $form->generate_text_box('image', $element['image'])
            );

            $form_container->end();
            $buttons[] = $form->generate_submit_button($lang->inplayquotes_add_button);
            $form->output_submit_wrapper($buttons);
                
            $form->end();
            
            $page->output_footer();
            exit;
        }

        // LÖSCHEN
        if ($mybb->input['action'] == "delete") {

			// Get data
			$rsid = $mybb->get_input('rsid', MyBB::INPUT_INT);
			$query = $db->simple_select("inplayquotes_reactions_settings", "*", "rsid='".$rsid."'");
			$del_type = $db->fetch_array($query);

			// Error Handling
			if (empty($rsid)) {
				flash_message($lang->inplayquotes_delete_error_invalid, 'error');
				admin_redirect("index.php?module=rpgstuff-inplayquotes");
			}

			// Cancel button pressed?
			if (isset($mybb->input['no']) && $mybb->input['no']) {
				admin_redirect("index.php?module=rpgstuff-inplayquotes");
			}

			if ($mybb->request_method == "post") {

                // Reaktion aus der DB löschen
				$db->delete_query("inplayquotes_reactions_settings", "rsid = '".$rsid."'");
                // abgebene Reaktion löschen
				$db->delete_query("inplayquotes_reactions", "reaction = '".$rsid."'");

                inplayquotes_cache();

                $mybb->input['module'] = $lang->inplayquotes_manage;
                $mybb->input['action'] = $lang->inplayquotes_delete_logadmin;
                log_admin_action(htmlspecialchars_uni($del_type['name']));

                flash_message($lang->sprintf($lang->inplayquotes_delete_flash, $del_type['name']), 'success');
                admin_redirect("index.php?module=rpgstuff-inplayquotes");
			} else {
				$page->output_confirm_action(
					"index.php?module=rpgstuff-inplayquotes&amp;action=delete&amp;rsid=".$rsid,
					$lang->uploadsystem_manage_overview_delete_notice
				);
			}
			exit;
		}

    }

}

// Stylesheet zum Master Style hinzufügen
function inplayquotes_admin_update_stylesheet(&$table) {

    global $db, $mybb, $lang;
	
    $lang->load('rpgstuff_stylesheet_updates');

    require_once MYBB_ADMIN_DIR."inc/functions_themes.php";

    // HINZUFÜGEN
    if ($mybb->input['action'] == 'add_master' AND $mybb->get_input('plugin') == "inplayquotes") {

        $css = inplayquotes_stylesheet();
        
        $sid = $db->insert_query("themestylesheets", $css);
        $db->update_query("themestylesheets", array("cachefile" => "inplayquotes.css"), "sid = '".$sid."'", 1);
    
        $tids = $db->simple_select("themes", "tid");
        while($theme = $db->fetch_array($tids)) {
            update_theme_stylesheet_list($theme['tid']);
        } 

        flash_message($lang->stylesheets_flash, "success");
        admin_redirect("index.php?module=rpgstuff-stylesheet_updates");
    }

    // Zelle mit dem Namen des Themes
    $table->construct_cell("<b>".htmlspecialchars_uni("Inplayzitate")."</b>", array('width' => '70%'));

    // Ob im Master Style vorhanden
    $master_check = $db->fetch_field($db->query("SELECT tid FROM ".TABLE_PREFIX."themestylesheets 
    WHERE name = 'inplayquotes.css' 
    AND tid = 1
    "), "tid");
    
    if (!empty($master_check)) {
        $masterstyle = true;
    } else {
        $masterstyle = false;
    }

    if (!empty($masterstyle)) {
        $table->construct_cell($lang->stylesheets_masterstyle, array('class' => 'align_center'));
    } else {
        $table->construct_cell("<a href=\"index.php?module=rpgstuff-stylesheet_updates&action=add_master&plugin=inplayquotes\">".$lang->stylesheets_add."</a>", array('class' => 'align_center'));
    }
    
    $table->construct_row();
}

// Plugin Update
function inplayquotes_admin_update_plugin(&$table) {

    global $db, $mybb, $lang;
	
    $lang->load('rpgstuff_plugin_updates');

    // UPDATE
    if ($mybb->input['action'] == 'add_update' AND $mybb->get_input('plugin') == "inplayquotes") {

        // Einstellungen überprüfen => Type = update
        inplayquotes_settings('update');
        rebuild_settings();

        // Templates 
        inplayquotes_templates('update');

        // Stylesheet
        $update_data = inplayquotes_stylesheet_update();
        $update_stylesheet = $update_data['stylesheet'];
        $update_string = $update_data['update_string'];
        if (!empty($update_string)) {

            // Ob im Master Style die Überprüfung vorhanden ist
            $masterstylesheet = $db->fetch_field($db->query("SELECT stylesheet FROM ".TABLE_PREFIX."themestylesheets WHERE tid = 1 AND name = 'inplayquotes.css'"), "stylesheet");
            $masterstylesheet = (string)($masterstylesheet ?? '');
            $update_string = (string)($update_string ?? '');
            $pos = strpos($masterstylesheet, $update_string);
            if ($pos === false) { // nicht vorhanden 
            
                $theme_query = $db->simple_select('themes', 'tid, name');
                while ($theme = $db->fetch_array($theme_query)) {
        
                    $stylesheet_query = $db->simple_select("themestylesheets", "*", "name='".$db->escape_string('inplayquotes.css')."' AND tid = ".$theme['tid']);
                    $stylesheet = $db->fetch_array($stylesheet_query);
        
                    if ($stylesheet) {

                        require_once MYBB_ADMIN_DIR."inc/functions_themes.php";
        
                        $sid = $stylesheet['sid'];
            
                        $updated_stylesheet = array(
                            "cachefile" => $db->escape_string($stylesheet['name']),
                            "stylesheet" => $db->escape_string($stylesheet['stylesheet']."\n\n".$update_stylesheet),
                            "lastmodified" => TIME_NOW
                        );
            
                        $db->update_query("themestylesheets", $updated_stylesheet, "sid='".$sid."'");
            
                        if(!cache_stylesheet($theme['tid'], $stylesheet['name'], $updated_stylesheet['stylesheet'])) {
                            $db->update_query("themestylesheets", array('cachefile' => "css.php?stylesheet=".$sid), "sid='".$sid."'", 1);
                        }
            
                        update_theme_stylesheet_list($theme['tid']);
                    }
                }
            } 
        }

        // Datenbanktabellen & Felder
        inplayquotes_database();

        flash_message($lang->plugins_flash, "success");
        admin_redirect("index.php?module=rpgstuff-plugin_updates");
    }

    // Zelle mit dem Namen des Themes
    $table->construct_cell("<b>".htmlspecialchars_uni("Inplayzitate")."</b>", array('width' => '70%'));

    // Überprüfen, ob Update erledigt
    $update_check = inplayquotes_is_updated();

    if (!empty($update_check)) {
        $table->construct_cell($lang->plugins_actual, array('class' => 'align_center'));
    } else {
        $table->construct_cell("<a href=\"index.php?module=rpgstuff-plugin_updates&action=add_update&plugin=inplayquotes\">".$lang->plugins_update."</a>", array('class' => 'align_center'));
    }
    
    $table->construct_row();
}

// USER WIRD GELÖSCHT
function inplayquotes_user_delete(){

    global $db, $cache, $mybb, $user;

    // EINSTELLUNG
    $deletion_settings = $mybb->settings['inplayquotes_deletion'];
    
    // UID gelöschter Chara
    $deleteChara = (int)$user['uid'];

    if ($deletion_settings == 0) return;

    // Alle Zitate rausfinden
    $allqoutes_query = $db->query("SELECT * FROM ".TABLE_PREFIX."inplayquotes
    WHERE uid = ".$deleteChara."
    ");
    $qouteids = [];
    while($qoutelist = $db->fetch_array($allqoutes_query)) {
        $qouteids[] = $qoutelist['qid'];
    } 
    foreach ($qouteids as $qid) {
        // Alle Reaktion auf seine Zitate löschen
        $db->delete_query('inplayquotes_reactions', "qid = ".$qid."");
    }

    // alle Zitate löschen
    $db->delete_query('inplayquotes', "uid = ".$deleteChara."");
}

// POSTBIT - BUTTON
function inplayquotes_postbit(&$post){

	global $lang, $templates, $db, $mybb, $forum, $fid, $inplayquotes_popup, $pid;
    
    // SPRACHDATEI
	$lang->load('inplayquotes');
    
    // USER-ID
    $active_uid = $mybb->user['uid'];
    
    $pid = $post['pid'];

	// EINSTELLUNG
	$allowgroups = $mybb->settings['inplayquotes_allowgroups'];
	$quotesarea = $mybb->settings['inplayquotes_quotesarea'];
    $selectedforums = explode(",", $quotesarea);
	$excludedarea = $mybb->settings['inplayquotes_excludedarea'];
    $excludedforums = explode(",", $excludedarea);

	// Informationen aus der DB forums
    // hier den parentlist -> ich hänge hinten und vorne noch ein komma dran um so was wie 1 udn 100 abzufangen
	$parentlist = ",".$forum['parentlist'].",";

    if(!empty($quotesarea) AND is_member($allowgroups) AND !in_array($fid, $excludedforums)) {
        foreach($selectedforums as $selectedforum) {
            if(preg_match("/,{$selectedforum},/i", $parentlist) || $quotesarea == -1) {
                $thread = get_thread($post['tid']);
                $subject = "";
                $username = "";
                $quote_infos = "";
                $subject = $thread['subject'];
                $username = $post['username'];
                $quote_infos = $lang->sprintf($lang->inplayquotes_postbit_popup_infos, $subject, $username);
                eval("\$inplayquotes_popup = \"".$templates->get("inplayquotes_postbit_popup")."\";");
                eval("\$post['button_inplayquotes'] = \"" . $templates->get("inplayquotes_postbit") . "\";");
                return $post;
			} else {
                $post['button_inplayquotes'] = "";
            }
        }
    } else {
        $post['button_inplayquotes'] = "";
    }
}

// ÜBERSICHTS SEITE
function inplayquotes_misc() {

    global $db, $cache, $mybb, $lang, $templates, $theme, $header, $headerinclude, $footer, $parser, $code_html, $inplayquotes;
    
    // SPRACHDATEI
	$lang->load('inplayquotes');

    // DAS HTML UND CO ANGEZEIGT WIRD
    require_once MYBB_ROOT."inc/class_parser.php";
    $parser = new postParser;
    $code_html = array(
        "allow_html" => 1,
        "allow_mycode" => 1,
        "allow_smilies" => 1,
        "allow_imgcode" => 1,
        "filter_badwords" => 0,
        "nl2br" => 1,
        "allow_videocode" => 0
    );

    // Übersetzung der Monatsnamen ins Englische
    $month_translation = array(
        'Januar' => 'January',
        'Februar' => 'February',
        'März' => 'March',
        'April' => 'April',
        'Mai' => 'May',
        'Juni' => 'June',
        'Juli' => 'July',
        'August' => 'August',
        'September' => 'September',
        'Oktober' => 'October',
        'November' => 'November', 
        'Dezember' => 'December'
    );

	$mybb->input['action'] = $mybb->get_input('action');
    
    // USER-ID
    $active_uid = $mybb->user['uid'];
    // Accountswitcher
    $active_allcharas = inplayquotes_get_allchars($active_uid);
    $active_charastring = implode(",", array_keys($active_allcharas));
    $count_allcharas = count($active_allcharas);

    // DAMIT DIE PN SACHE FUNKTIONIERT
    require_once MYBB_ROOT."inc/datahandlers/pm.php";
    $pmhandler = new PMDataHandler();

    // EINSTELLUNGEN
	$allowgroups = $mybb->settings['inplayquotes_allowgroups'];
	$alertsystem = $mybb->settings['inplayquotes_user_alert'];
    $listsnav_setting = $mybb->settings['inplayquotes_lists']; 
    $liststype_setting = $mybb->settings['inplayquotes_lists_type']; 
	$listsmenu_setting = $mybb->settings['inplayquotes_lists_menu']; 
	$overview_guest = $mybb->settings['inplayquotes_overview_guest'];
	$overview_multipage = $mybb->settings['inplayquotes_overview_multipage'];
	$overview_filter = $mybb->settings['inplayquotes_overview_filter'];
	$graphic_type = $mybb->settings['inplayquotes_overview_graphic'];
	$graphic_uploadsystem = $mybb->settings['inplayquotes_overview_graphic_uploadsystem'];
	$graphic_profilefield = $mybb->settings['inplayquotes_overview_graphic_profilefield'];
	$graphic_characterfield = $mybb->settings['inplayquotes_overview_graphic_characterfield'];
	$graphic_defaultgraphic = $mybb->settings['inplayquotes_overview_graphic_defaultgraphic'];
	$graphic_guest = $mybb->settings['inplayquotes_overview_graphic_guest'];
    $reactions_setting = $mybb->settings['inplayquotes_reactions'];
    $reactions_option = $mybb->settings['inplayquotes_reactions_option'];
    $playername_setting = $mybb->settings['inplayquotes_playername'];

    $all_reactions_options = $db->num_rows($db->query("SELECT rsid FROM ".TABLE_PREFIX."inplayquotes_reactions_settings"));

    // ÜBERSICHT
	if($mybb->input['action'] == "inplayquotes"){

		// Listenmenü
		if($liststype_setting != 2){
            // Jules Plugin
            if ($liststype_setting == 1) {
                $lang->load("lists");
                $query_lists = $db->simple_select("lists", "*");
                $menu_bit = "";
                while($list = $db->fetch_array($query_lists)) {
                    eval("\$menu_bit .= \"".$templates->get("lists_menu_bit")."\";");
                }
                eval("\$lists_menu = \"".$templates->get("lists_menu")."\";");
            } else {
                eval("\$lists_menu = \"".$templates->get($listsmenu_setting)."\";");
            }
        } else {
            $lists_menu = "";
        }

        // NAVIGATION
		if(!empty($listsnav_setting)){
            add_breadcrumb($lang->inplayquotes_lists, $listsnav_setting);
            add_breadcrumb($lang->inplayquotes_overview, "misc.php?action=inplayquotes");
		} else{
            add_breadcrumb($lang->inplayquotes_overview, "misc.php?action=inplayquotes");
		}

        // Gäste ausschließen
		if($overview_guest == 0 AND $active_uid == 0) {
            redirect('index.php', $lang->inplayquotes_overview_error_guest);
		}

        // Filter
        if (!empty($overview_filter)) {

            $filter_options = explode(",", $overview_filter);

            $filter_bit = "";
            foreach ($filter_options as $filter_option) {
                $filteroptions = "";

                // nach Spieler
                if ($filter_option == "player") {
                    $filter_headline = $lang->inplayquotes_filter_headline_player;
                    $first_select = $lang->inplayquotes_filter_select_player;

                    $alluids_query = $db->query("SELECT uid FROM ".TABLE_PREFIX."inplayquotes GROUP BY uid");

                    $main_uids = [];
                    while($alluid = $db->fetch_array($alluids_query)) {
                        $user = get_user($alluid['uid']);
                        // Vorhanden -> daten aus der Users Tabelle
                        if (!empty($user)) {
                            if ($user['as_uid'] == 0) {
                                $main_uids[] = $alluid['uid'];
                            } else {
                                $main_uids[] = $user['as_uid'];
                            }
                        }
                    }
                    $all_main = array_unique($main_uids);

                    foreach ($all_main as $uid) {
                        // wenn Zahl => klassisches Profilfeld
                        if (is_numeric($playername_setting)) {
                            $playername = $db->fetch_field($db->simple_select("userfields", "fid".$playername_setting, "ufid = '".$uid."'"), "fid".$playername_setting);
                        } else {
                            $playerid = $db->fetch_field($db->simple_select("application_ucp_fields", "id", "fieldname = '".$playername_setting."'"), "id");
                            $playername = $db->fetch_field($db->simple_select("application_ucp_userfields", "value", "fieldid = '".$playerid."' AND uid = '".$uid."'"), "value");
                        }
                        
                        if ($mybb->get_input('filter_player') == $uid) {
                            $check_select = "selected";
                        } else {
                            $check_select = "";
                        }

                        $filteroptions .= "<option value=\"".$uid."\" ".$check_select.">".$playername."</option>";
                    }
                }
                // nach Charakter
                else if ($filter_option == "character") {
                    $filter_headline = $lang->inplayquotes_filter_headline_character;
                    $first_select = $lang->inplayquotes_filter_select_character;

                    $character_query = $db->query("SELECT DISTINCT COALESCE(u.username, iq.username) AS username, iq.uid AS uid 
                    FROM ".TABLE_PREFIX."inplayquotes iq
                    LEFT JOIN ".TABLE_PREFIX."users u ON iq.uid = u.uid
                    ORDER BY username ASC");
                    while($character = $db->fetch_array($character_query)) {
                        if ($mybb->get_input('filter_character') == $character['uid'] || $mybb->get_input('profile_direct') == $character['uid']) {
                            $check_select = "selected";
                        } else {
                            $check_select = "";
                        }
                        $user = get_user($character['uid']);
                        // Vorhanden -> daten aus der Users Tabelle
                        if (!empty($user)) {
                            $filteroptions .= "<option value=\"".$character['uid']."\" ".$check_select.">".$user['username']."</option>";
                        } else {
                            $filteroptions .= "<option value=\"".$character['uid']."\" ".$check_select.">".$character['username'].$lang->inplayquotes_filter_select_character_formerly."</option>";
                        }
                    }
                }
                // nach Postdatum
                else if ($filter_option == "timestamp") {
                    $filter_headline = $lang->inplayquotes_filter_headline_timestamp;
                    $first_select = $lang->inplayquotes_filter_select_timestamp;

                    $timestamp_query = $db->query("SET lc_time_names = 'de_DE';");
                    $timestamp_query = $db->query("SELECT DISTINCT DATE_FORMAT(from_unixtime(date), '%M %Y') AS postdate, MIN(date) AS min_date FROM ".TABLE_PREFIX."inplayquotes GROUP BY postdate ORDER by min_date ASC");
                    while($time = $db->fetch_array($timestamp_query)) {
                        if ($mybb->get_input('filter_timestamp') == $time['postdate']) {
                            $check_select = "selected";
                        } else {
                            $check_select = "";
                        }
                        $filteroptions .= "<option value=\"".$time['postdate']."\" ".$check_select.">".$time['postdate']."</option>";
                    } 
                }

                $filter_select = "<select name=\"filter_".$filter_option."\"><option value=\"%\">".$first_select."</option>".$filteroptions."</select>";

                eval("\$filter_bit .= \"".$templates->get("inplayquotes_overview_filter_bit")."\";");
            }

            eval("\$filter_option = \"".$templates->get("inplayquotes_overview_filter")."\";");
        } else {
            $filter_option = "";
        }

        // QUERY KRAM - Filter
        $player_filter = "%";
        $character_filter = "%";
        $timestamp_filter = "%";
        $filter_multipage = "";
        if($mybb->get_input('inplayquotes_search_filter')) {
            $player_filter = $mybb->get_input('filter_player');
            $character_filter = $mybb->get_input('filter_character');
            $timestamp_filter = $mybb->get_input('filter_timestamp');
            $filter_multipage = "&inplayquotes_search_filter=".$lang->inplayquotes_filter_button;
        }

        // SPIELER
        if ($player_filter != "%" AND !empty($player_filter)) {
            $allchars = inplayquotes_get_allchars_filter($player_filter);
            $playerids_string = implode(",", array_keys($allchars));
            $player_filter_sql = "AND uid IN (".$playerids_string.")";
            $player_multipage = "&filter_player=".$player_filter;
        } else {
            $player_filter_sql = "";
            $player_multipage = "";
        }

        // CHARAKTER
        if ($character_filter != "%" AND !empty($character_filter)) {
            $character_filter_sql = "AND uid = '".$character_filter."'";
            $character_multipage = "&filter_character=".$character_filter;
        } else {
            $character_filter_sql = "";
            $character_multipage = "";
        }

        // ZEITRAUM
        if ($timestamp_filter != "%" AND !empty($timestamp_filter)) {
            list($selected_month, $selected_year) = explode(" ", $timestamp_filter);
            $selected_month_en = $month_translation[$selected_month];
            $selected_date = date('Y-m-d', strtotime('1 '.$selected_month_en.' '.$selected_year));
            $timestamp_filter_sql = "AND MONTH(FROM_UNIXTIME(date)) = MONTH('".$selected_date."') 
            AND YEAR(FROM_UNIXTIME(date)) = YEAR('".$selected_date."')";
            $timestamp_multipage = "&filter_timestamp=".$timestamp_filter;
        } else {
            $timestamp_filter_sql = "";
            $timestamp_multipage = "";
        }

        // PROFIL DIREKT
        $profile_direct = $mybb->get_input('profile_direct');
        if($profile_direct) {
            $profile_multipage_sql = "AND uid = ".$profile_direct."";
            $profile_multipage = "&profile_direct=".$profile_direct;
        } else {
            $profile_multipage_sql = "";
            $profile_multipage = "";
        }

        // Multipage
        $allQuotes = $db->num_rows($db->query("SELECT qid FROM ".TABLE_PREFIX."inplayquotes
        WHERE quote != ''
        ".$player_filter_sql."
        ".$character_filter_sql."
        ".$timestamp_filter_sql."
        ".$profile_multipage_sql));
        if ($overview_multipage != 0) {
    
            $perpage = $overview_multipage;
            $input_page = $mybb->get_input('page', MyBB::INPUT_INT);
            if($input_page) {
                $start = ($input_page-1) *$perpage;
            }
            else {
                $start = 0;
                $input_page = 1;
            }
            $end = $start + $perpage;
            $lower = $start+1;
            $upper = $end;
            if($upper > $allQuotes) {
                $upper = $allQuotes;
            }
    
            $page_url = htmlspecialchars_uni("misc.php?action=inplayquotes".$player_multipage.$character_multipage.$timestamp_multipage.$filter_multipage.$profile_multipage);

            $multipage = multipage($allQuotes, $perpage, $input_page, $page_url);

            $multipage_sql = "LIMIT ".$start.", ".$perpage;
        } else {
            $multipage = "";
            $multipage_sql = "";
        }

        // ALERT DIREKT
        $alert_direct = $mybb->get_input('alert_direct');
        if($alert_direct) {
            $alert_direct_sql = "AND qid = ".$alert_direct."";
            $multipage = "<a href=\"misc.php?action=inplayquotes\">".$lang->inplayquotes_overview."</a>";
            $multipage_sql = "";
        } else {
            $alert_direct_sql = "";
        }

        $quotes_query = $db->query("SELECT * FROM ".TABLE_PREFIX."inplayquotes
        WHERE quote != ''
        ".$player_filter_sql."
        ".$character_filter_sql."
        ".$timestamp_filter_sql."
        ".$alert_direct_sql."
        ".$profile_multipage_sql."
		ORDER BY qid DESC
        ".$multipage_sql);

        $inplayquotes_bit = "";
		while($quote = $db->fetch_array($quotes_query)) {

            // Leer laufen lassen
            $qid = "";
            $uid = "";
            $charactername = "";
            $charactername_formated = "";
            $charactername_formated_link = "";
            $charactername_link = "";
            $charactername_fullname = "";
            $charactername_first = "";
            $charactername_last = "";
            $tid = "";
            $pid = "";
            $date = "";
            $inplayquote = "";
            $graphic_link = "";
            $scene_link = "";
            $postdate = "";

            // Mit Infos füllen
            $qid = $quote['qid'];
            $uid = $quote['uid'];
            $tid = $quote['tid'];
            $pid = $quote['pid'];
            $date = $quote['date'];
            $inplayquote = $parser->parse_message($quote['quote'], $code_html);

            // User vorhanden oder nicht
            $user = get_user($uid);
            // Vorhanden -> daten aus der Users Tabelle
            if (!empty($user)) {
                // CHARACTER NAME
                // ohne alles
                $charactername = $user['username'];
                // mit Gruppenfarbe
                $charactername_formated = format_name($charactername, $user['usergroup'], $user['displaygroup']);	
                $charactername_formated_link = build_profile_link(format_name($charactername, $user['usergroup'], $user['displaygroup']), $uid);	
                // Nur Link
                $charactername_link = build_profile_link($charactername, $uid);
                // Name gesplittet
                $charactername_fullname = explode(" ", $charactername);
                $charactername_first = array_shift($charactername_fullname);
                $charactername_last = implode(" ", $charactername_fullname);
                
                // CHARACTER GRAFIK
                // Gäste
                if ($active_uid == 0 AND $graphic_guest == 1) {
                    $graphic_link = $theme['imgdir']."/".$graphic_defaultgraphic;
                } else {
                    // Avatar
                    if ($graphic_type == 0) {
                        $chara_graphic = $user['avatar'];
                    } 
                    // Uploadsystem
                    else if ($graphic_type == 1) {
                        $path = $db->fetch_field($db->simple_select("uploadsystem", "path", "identification = '".$graphic_uploadsystem."'"), "path");                  
                        $value = $db->fetch_field($db->simple_select("uploadfiles", $graphic_uploadsystem, "ufid = '".$uid."'"), $graphic_uploadsystem);

                        if ($value != "") {
                            $chara_graphic = $path."/".$value;
                        } else {
                            $chara_graphic = "";
                        }
                    }
                    // Profilfelder
                    else if ($graphic_type == 2) {
                        $fid = "fid".$graphic_profilefield;
                        $chara_graphic = $db->fetch_field($db->simple_select("userfields", $fid, "ufid = '".$uid."'"), $fid);
                    }
                    // Steckifelder
                    else if ($graphic_type == 3) {	
                        $fieldid = $db->fetch_field($db->simple_select("application_ucp_fields", "id", "fieldname = '".$graphic_characterfield."'"), "id");                  
                        $chara_graphic = $db->fetch_field($db->simple_select("application_ucp_userfields", "value", "uid = '".$uid."' AND fieldid = '".$fieldid."'"), "value");
                    }
    
                    // wenn man kein Grafik hat => Default
                    if ($chara_graphic == "") {
                        // Dateinamen bauen
                        $graphic_link = $theme['imgdir']."/".$graphic_defaultgraphic;
                    } else {
                        // Dateinamen bauen
                        $graphic_link = $chara_graphic;
                    }
                }

            } 
            // Nicht vorhanden -> "Gast" = gespeicherter Name
            else {
                // CHARACTER NAME
                // ohne alles
                $charactername = $charactername_formated = $charactername_formated_link = $charactername_link = $quote['username'];
                // Name gesplittet
                $charactername_fullname = explode(" ", $charactername);
                $charactername_first = array_shift($charactername_fullname);
                $charactername_last = implode(" ", $charactername_fullname); 

                // CHARACTER GRAFIK -> immer Default
                $graphic_link = $theme['imgdir']."/".$graphic_defaultgraphic;
            }

            // Szenenlink
            $thread = get_thread($tid);
            $scene_link = "<a href=\"showthread.php?tid=".$tid."&amp;pid=".$pid."#pid".$pid."\">".$thread['subject']."</a>";

            // Postdatum
            $postdate = my_date('relative', $date);

            // Profilfelder
            $characterfield = inplayquotes_build_characterfield($uid);
            // Szeneninfos
            $scenefield = inplayquotes_build_scenefield($tid);

            // Reaktionen
            $pos = strpos(",".$active_charastring.",", ",".$uid.",");
            if ($reactions_setting == 1) {

                // Leer laufen lassen
                $quote_preview = "";
                $prev_quote = "";

                // Mit Infos füllen
                if(my_strlen($inplayquote) > 200) {
                    $prev_quote = my_substr($inplayquote, 0, 200)."...";
                } else {
                    $prev_quote = $inplayquote;
                }
                $quote_preview = $lang->sprintf($lang->inplayquotes_reactions_quote_preview, $charactername, $prev_quote);

                // Pro Charakter
                if ($reactions_option == 1) {
                    // Bishierige Reaktionen auf das Zitat
                    $query_reactionsUser = $db->query("SELECT reaction FROM ".TABLE_PREFIX."inplayquotes_reactions
                    WHERE qid = '".$qid."'
                    AND uid = '".$active_uid."'                       
                    ");    
                } 
                // pro Spieler
                else {
                    // Bishierige Reaktionen auf das Zitat
                    $query_reactionsUser = $db->query("SELECT reaction FROM ".TABLE_PREFIX."inplayquotes_reactions
                    WHERE qid = '".$qid."'
                    AND uid IN (".$active_charastring.")                          
                    ");   
                } 

                $reactionsUser_qids = "";
                while ($reactionsUser = $db->fetch_array($query_reactionsUser)){
                    $reactionsUser_qids .= $reactionsUser['reaction'].",";
                } 
                if(!empty($reactionsUser_qids)) {
                    // letztes Komma abschneiden 
                    $reactionsUser_string = substr($reactionsUser_qids, 0, -1);
                    $reaction_sql = "WHERE rsid NOT IN(".$reactionsUser_string.")";

                    // Pro Charakter
                    if ($reactions_option == 1) {
                        $allreacted_query = $db->query("SELECT r.reaction, rs.image, MAX(rid) AS max_rid FROM ".TABLE_PREFIX."inplayquotes_reactions r
                        LEFT JOIN ".TABLE_PREFIX."inplayquotes_reactions_settings rs ON rs.rsid = r.reaction
                        WHERE uid = '".$active_uid."' 
                        AND qid = '".$qid."' 
                        GROUP BY r.reaction
                        ");
                    }
                    // pro Spieler
                    else {
                        $allreacted_query = $db->query("SELECT r.reaction, rs.image  FROM ".TABLE_PREFIX."inplayquotes_reactions r
                        LEFT JOIN ".TABLE_PREFIX."inplayquotes_reactions_settings rs ON rs.rsid = r.reaction
                        WHERE uid IN (".$active_charastring.") 
                        AND qid = '".$qid."' 
                        GROUP BY r.reaction
                        ");
                    }

                    $reacted_images = "";
                    $reacted_reactions = "";
                    while($allreacted = $db->fetch_array($allreacted_query)) {
            
                        // Leer laufen lassen
                        $reaction = "";          
                        $image = "";

                        // Mit Infos füllen
                        $reaction = $allreacted['reaction'];         
                        $image = $allreacted['image'];

                        eval("\$reacted_images .= \"".$templates->get("inplayquotes_reactions_reacted_image")."\";");
                    }

                    eval("\$reacted_reactions = \"".$templates->get("inplayquotes_reactions_reacted")."\";");
                } else {
                    $reaction_sql = "";
                    $reacted_reactions = "";
                }

                // Bilder
                $allreactions_query = $db->query("SELECT * FROM ".TABLE_PREFIX."inplayquotes_reactions_settings
                ".$reaction_sql);
    
                $reactions_images = "";
                while($allreaction = $db->fetch_array($allreactions_query)) {
                    // Leer laufen lassen
                    $rsid = "";
                    $name = "";
                    $image = "";

                    // Mit Infos füllen
                    $rsid = $allreaction['rsid'];
                    $name = $allreaction['name'];
                    $image = $allreaction['image'];

                    eval("\$reactions_images .= \"".$templates->get("inplayquotes_reactions_add_popup_image")."\";");
                }

                eval("\$reactions_popup = \"".$templates->get("inplayquotes_reactions_add_popup")."\";");

                $check = $db->num_rows($db->query("SELECT * FROM ".TABLE_PREFIX."inplayquotes_reactions
                WHERE qid = '".$qid."'"));
                // Hat schon Reaktionen
                if ($check > 0) {

                    $reactions_query = $db->query("SELECT rs.*, GROUP_CONCAT(r.rid) AS rid_list FROM ".TABLE_PREFIX."inplayquotes_reactions r 
                    LEFT JOIN ".TABLE_PREFIX."inplayquotes_reactions_settings rs ON rs.rsid = r.reaction 
                    WHERE qid = '".$qid."' 
                    GROUP BY rs.rsid");
                    $stored_reactions = "";
                    while($reaction = $db->fetch_array($reactions_query)) {
                        // Leer laufen lassen
                        $rsid = "";
                        $name = "";
                        $image = "";
                        $rid = "";
                        $count = "";
                        $title = "";
    
                        // Mit Infos füllen
                        $rsid = $reaction['rsid'];
                        $name = $reaction['name'];
                        $image = $reaction['image'];

                        $query_quote = $db->query("SELECT reaction,uid,username FROM ".TABLE_PREFIX."inplayquotes_reactions
                        WHERE qid = '".$qid."'  
                        AND reaction = '".$rsid."'
                        ORDER BY username
                        ");

                        $count = $db->num_rows($query_quote);

                        // Namen von den Leuten
                        // Charakternamen
                        if ($reactions_option == 1) {
                            $all_usernames = "";
                            while ($alluser = $db->fetch_array($query_quote)){
                                $user = get_user($alluser['uid']);
                                // Vorhanden -> daten aus der Users Tabelle
                                if (!empty($user)) {
                                    $all_usernames .= $user['username'].", ";
                                } else {
                                    $all_usernames .= $alluser['username'].", ";
                                }
                            } 
                            // letztes Komma abschneiden 
                            $title = substr($all_usernames, 0, -2);
                        }
                        // Spielername
                        else {
                            $all_playername = "";
                            while ($alluser = $db->fetch_array($query_quote)){
                                $user = get_user($alluser['uid']);
                                // Vorhanden -> daten aus der Users Tabelle
                                if (!empty($user)) {
                                    // wenn Zahl => klassisches Profilfeld
                                    if (is_numeric($playername_setting)) {
                                        $playername = $db->fetch_field($db->simple_select("userfields", "fid".$playername_setting, "ufid = '".$alluser['uid']."'"), "fid".$playername_setting);
                                    } else {
                                        $playerid = $db->fetch_field($db->simple_select("application_ucp_fields", "id", "fieldname = '".$playername_setting."'"), "id");
                                        $playername = $db->fetch_field($db->simple_select("application_ucp_userfields", "value", "fieldid = '".$playerid."' AND uid = '".$alluser['uid']."'"), "value");
                                    }
                                } else {
                                    $playername = $alluser['username'].$lang->inplayquotes_filter_select_character_formerly;
                                }
                                $all_playername .= $playername.", ";
                            } 
                            // letztes Komma abschneiden 
                            $title = substr($all_playername, 0, -2);
                        }
    
                        eval("\$stored_reactions .= \"".$templates->get("inplayquotes_reactions_stored")."\";");
                    }

                    // Pro Charakter
                    if ($reactions_option == 1) {
                        $account_reactions = $db->num_rows($db->query("SELECT reaction FROM ".TABLE_PREFIX."inplayquotes_reactions
                        WHERE qid = '".$qid."'  
                        AND uid = '".$active_uid."'                
                        "));
    
                        if ($all_reactions_options != $account_reactions) {
                            $check_add = 1;
                        } else {
                            $check_add = 0;
                        }   
                    } 
                    // pro Spieler
                    else {
                        $player_reactions = $db->num_rows($db->query("SELECT reaction FROM ".TABLE_PREFIX."inplayquotes_reactions
                        WHERE qid = '".$qid."'  
                        AND uid IN (".$active_charastring.")                
                        "));
    
                        $account_reactions = $db->num_rows($db->query("SELECT reaction FROM ".TABLE_PREFIX."inplayquotes_reactions
                        WHERE qid = '".$qid."'  
                        AND uid = '".$active_uid."'                
                        "));
    
                        $all_reactions_math = $all_reactions_options*$count_allcharas;
    
                        if ($all_reactions_math != $player_reactions AND $all_reactions_options != $account_reactions) {
                            $check_add = 1;
                        } else {
                            $check_add = 0;
                        }    
                    } 

                    if ($pos === false AND $check_add == 1 AND $active_uid != 0) {
                        eval("\$reactions_add = \"".$templates->get("inplayquotes_reactions_add")."\";");
                    } else {
                        $reactions_add = "";
                    }

                    if ($mybb->usergroup['canmodcp'] == '1') {
                        $delete_reactions = "<span class=\"inplayquotes_overview_bit_reactions_delete\"><a href=\"misc.php?action=inplayquotes&amp;allreactions_delete=".$qid."\">".$lang->inplayquotes_allreactions_delete."</a></span>";
                    } else {
                        $delete_reactions = "";
                    }

                    eval("\$reactions = \"".$templates->get("inplayquotes_reactions")."\";");
                } else {
                    $reacted_reactions = "";

                    if ($pos === false AND $active_uid != 0) {
                        eval("\$reactions = \"".$templates->get("inplayquotes_reactions_add")."\";");
                    } else {
                        $reactions = "";
                    }
                }
            } else {
                $reactions = "";
            }

            // Lösch Link
            if(($mybb->usergroup['canmodcp'] == '1' OR $pos !== false) AND $active_uid != 0){
                $del_quote = "<a href=\"misc.php?action=inplayquotes&quote_delete=".$qid."\" onClick=\"return confirm('Möchtest du dieses Zitat wirklich löschen?');\">".$lang->inplayquotes_quote_delete."</a>";
            } else {
                $del_quote = "";
            }

            eval("\$inplayquotes_bit .= \"".$templates->get("inplayquotes_overview_bit")."\";");
		}

        // REAKTION LÖSCHEN
        $reactions_delete = $mybb->get_input('reactions_delete');
        $reactions_quote = $mybb->get_input('reactions_quote');
        if($reactions_delete) {

            if ($reactions_option == 1) {
                $query_del = $db->query("SELECT rid FROM ".TABLE_PREFIX."inplayquotes_reactions
                WHERE qid = '".$reactions_quote."'
                AND reaction = '".$reactions_delete."'
                AND uid = '".$active_uid."'                       
                ");   
            } else {
                $query_del = $db->query("SELECT rid FROM ".TABLE_PREFIX."inplayquotes_reactions
                WHERE qid = '".$reactions_quote."'
                AND reaction = '".$reactions_delete."'
                AND uid IN (".$active_charastring.")                          
                ");   
            }
            
            $del_rids = [];
            while ($del = $db->fetch_array($query_del)){
                $del_rids[] = $del['rid'];
            }

            foreach ($del_rids as $rid) {
                $db->delete_query("inplayquotes_reactions", "rid = '".$rid."'");
            }

            redirect("misc.php?action=inplayquotes", $lang->inplayquote_redirect_reactions_delete);
        }

        // REAKTION LÖSCHEN - TEAM 
        $allreactions_delete = $mybb->get_input('allreactions_delete');
        if($allreactions_delete) {
            $db->delete_query("inplayquotes_reactions", "qid = '".$allreactions_delete."'");
            redirect("misc.php?action=inplayquotes", $lang->inplayquote_redirect_allreactions_delete);
        }

        // ZITAT LÖSCHEN
        $quote_delete = $mybb->get_input('quote_delete');
        if($quote_delete) {
            $db->delete_query("inplayquotes", "qid = '".$quote_delete."'");
            $db->delete_query("inplayquotes_reactions", "qid = '".$quote_delete."'");
            redirect("misc.php?action=inplayquotes", $lang->inplayquote_redirect_quote_delete);
        }
        
		eval("\$page = \"".$templates->get("inplayquotes_overview")."\";");
        output_page($page);
        die();
    }

    // ZITAT HINZUFÜGEN
	if($mybb->input['action'] == "add_inplayquote"){

        $pid = $mybb->get_input('pid');

		if(!is_member($allowgroups)) {
            redirect('index.php', $lang->inplayquotes_add_inplayquote_error_allowgroups);
		}

        // AUTO ID
        $qid = inplayquotes_getNextId("inplayquotes");

        // Post und Thread Infos ziehen
        $post = get_post($pid);
        $thread = get_thread($post['tid']);
			
        $new_quote = array(
            "uid" => (int)$post['uid'],
            "username" => $db->escape_string($post['username']),
            "tid" => (int)$post['tid'],
            "pid" => $pid,
            "date" => (int)$post['dateline'],
            "quote" => $db->escape_string($mybb->get_input('inplayquote'))
        );

        $sendby = $mybb->get_input('sendby');

        // MyALERTS STUFF
        if(class_exists('MybbStuff_MyAlerts_AlertTypeManager') AND $alertsystem == 0) {
            $alertType = MybbStuff_MyAlerts_AlertTypeManager::getInstance()->getByCode('inplayquotes_add_quote');
            if ($alertType != NULL && $alertType->getEnabled()) {
                $alert = new MybbStuff_MyAlerts_Entity_Alert((int)$post['uid'], $alertType, (int)$sendby);
                $alert->setExtraDetails([
                    'username' => get_user($sendby)['username'],
                    'from' => $sendby,
                    'scene' => $thread['subject'],
                    'pid' => $pid,
                    'tid' => $post['tid'],
                    'qid' => $qid,
                ]);
                MybbStuff_MyAlerts_AlertManager::getInstance()->addAlert($alert);
            }
        } 
        // PN STUFF
        else {
            $pm_change = array(
                "subject" => $lang->inplayquotes_pm_add_quote_subject,
                "message" => $lang->sprintf($lang->inplayquotes_pm_add_quote_message, get_user($post['uid'])['username'], $post['tid'], $pid, $thread['subject'], $parser->parse_message($mybb->get_input('inplayquote'), $code_html)),
                "fromid" => $sendby,
                "toid" => $post['uid'],
                "icon" => "",
                "do" => "",
                "pmid" => "",
                "dateline" => TIME_NOW,
                "status" => 0,
                "ipaddress" => get_user($sendby)['lastip']
            );
            $pm_change['options'] = array(
                'signature' => '0',
                'savecopy' => '0',
                'disablesmilies' => '0',
                'readreceipt' => '0',
            );
            // $pmhandler->admin_override = true;
            $pmhandler->set_data($pm_change);
            if (!$pmhandler->validate_pm())
                return false;
            else {
                $pmhandler->insert_pm();
            }
        }
		
       $db->insert_query("inplayquotes", $new_quote);	

        redirect("showthread.php?tid=".$post['tid']."&pid=".$pid."#pid".$pid, $lang->inplayquote_redirect_add_inplayquote);	  
	}

    // REAKTION HINZUFÜGEN
    if($mybb->input['action'] == 'add_inplayquote_reaction') {

        $qid = (int)$mybb->get_input('qid');
        $reactionId = (int)$mybb->get_input('selected_reaction_id');
        $sendby = (int)$mybb->get_input('sendby');

        $new_reaction = array(
            'reaction' => (int)$reactionId,
            'qid' => (int)$qid,
            'uid' => (int)$sendby,
            'username' => get_user($sendby)['username']
        );

        $quote_user = $db->fetch_field($db->simple_select("inplayquotes", "uid", "qid = '".$qid."'"), "uid");
        $inplayquote = $db->fetch_field($db->simple_select("inplayquotes", "quote", "qid = '".$qid."'"), "quote");
        $reaction_img = $db->fetch_field($db->simple_select("inplayquotes_reactions_settings", "image", "rsid = '".$reactionId."'"), "image");

        // MyALERTS STUFF
        if(class_exists('MybbStuff_MyAlerts_AlertTypeManager') AND $alertsystem == 0) {
            $alertType = MybbStuff_MyAlerts_AlertTypeManager::getInstance()->getByCode('inplayquotes_add_reaction');
            if ($alertType != NULL && $alertType->getEnabled()) {
                $alert = new MybbStuff_MyAlerts_Entity_Alert((int)$quote_user, $alertType, (int)$sendby);
                $alert->setExtraDetails([
                    'username' => get_user($sendby)['username'],
                    'from' => $sendby,
                    'image' => $reaction_img,
                    'qid' => $qid,
                ]);
                MybbStuff_MyAlerts_AlertManager::getInstance()->addAlert($alert);
            }
        } 
        // PN STUFF
        else {
            $pm_change = array(
                "subject" => $lang->inplayquotes_pm_add_reaction_subject,
                "message" => $lang->sprintf($lang->inplayquotes_pm_add_reaction_message, get_user($quote_user)['username'], $reaction_img, $parser->parse_message($inplayquote, $code_html)),
                "fromid" => $sendby,
                "toid" => $quote_user,
                "icon" => "",
                "do" => "",
                "pmid" => "",
                "dateline" => TIME_NOW,
                "status" => 0,
                "ipaddress" => get_user($sendby)['lastip']
            );
            $pm_change['options'] = array(
                'signature' => '0',
                'savecopy' => '0',
                'disablesmilies' => '0',
                'readreceipt' => '0',
            );
            // $pmhandler->admin_override = true;
            $pmhandler->set_data($pm_change);
            if (!$pmhandler->validate_pm())
                return false;
            else {
                $pmhandler->insert_pm();
            }
        }
		
        $db->insert_query("inplayquotes_reactions", $new_reaction);	

        redirect("misc.php?action=inplayquotes", $lang->inplayquote_redirect_add_reaction);	  
    }
}

// INDEX ANZEIGE
function inplayquotes_index() {

    global $db, $cache, $mybb, $lang, $templates, $inplayquotes_index, $theme, $parser, $code_html;

    // EINSTELLUNGEN
	$graphic_type = $mybb->settings['inplayquotes_overview_graphic'];
	$graphic_uploadsystem = $mybb->settings['inplayquotes_overview_graphic_uploadsystem'];
	$graphic_profilefield = $mybb->settings['inplayquotes_overview_graphic_profilefield'];
	$graphic_characterfield = $mybb->settings['inplayquotes_overview_graphic_characterfield'];
	$graphic_defaultgraphic = $mybb->settings['inplayquotes_overview_graphic_defaultgraphic'];
	$graphic_guest = $mybb->settings['inplayquotes_overview_graphic_guest'];
    $reactions_setting = $mybb->settings['inplayquotes_reactions'];
    $reactions_option = $mybb->settings['inplayquotes_reactions_option'];
    $playername_setting = $mybb->settings['inplayquotes_playername'];

    $all_reactions_options = $db->num_rows($db->query("SELECT rsid FROM ".TABLE_PREFIX."inplayquotes_reactions_settings"));

    // DAS HTML UND CO ANGEZEIGT WIRD
    require_once MYBB_ROOT."inc/class_parser.php";
    $parser = new postParser;
    $code_html = array(
        "allow_html" => 1,
        "allow_mycode" => 1,
        "allow_smilies" => 1,
        "allow_imgcode" => 1,
        "filter_badwords" => 0,
        "nl2br" => 1,
        "allow_videocode" => 0
    );
    
    // SPRACHDATEI
	$lang->load('inplayquotes');
    
    // USER-ID
    $active_uid = $mybb->user['uid'];
    // Accountswitcher
    $active_allcharas = inplayquotes_get_allchars($active_uid);
    $active_charastring = implode(",", array_keys($active_allcharas));
    $count_allcharas = count($active_allcharas);

    $index_quote = $db->query("SELECT * FROM ".TABLE_PREFIX."inplayquotes	
	ORDER BY rand()
	LIMIT 1
    ");

    eval("\$inplayquote_bit = \"".$templates->get("inplayquotes_index_bit_none")."\";");
    while($quote = $db->fetch_array($index_quote)) {

        // Leer laufen lassen
        $qid = "";
        $uid = "";
        $charactername = "";
        $charactername_formated = "";
        $charactername_formated_link = "";
        $charactername_link = "";
        $charactername_fullname = "";
        $charactername_first = "";
        $charactername_last = "";
        $tid = "";
        $pid = "";
        $date = "";
        $inplayquote = "";
        $graphic_link = "";
        $scene_link = "";
        $postdate = "";

        // Mit Infos füllen
        $qid = $quote['qid'];
        $uid = $quote['uid'];
        $tid = $quote['tid'];
        $pid = $quote['pid'];
        $date = $quote['date'];
        $inplayquote = $parser->parse_message($quote['quote'], $code_html);

        // User vorhanden oder nicht
        $user = get_user($uid);
        // Vorhanden -> daten aus der Users Tabelle
        if (!empty($user)) {
            // CHARACTER NAME
            // ohne alles
            $charactername = $user['username'];
            // mit Gruppenfarbe
            $charactername_formated = format_name($charactername, $user['usergroup'], $user['displaygroup']);	
            $charactername_formated_link = build_profile_link(format_name($charactername, $user['usergroup'], $user['displaygroup']), $uid);	
            // Nur Link
            $charactername_link = build_profile_link($charactername, $uid);
            // Name gesplittet
            $charactername_fullname = explode(" ", $charactername);
            $charactername_first = array_shift($charactername_fullname);
            $charactername_last = implode(" ", $charactername_fullname);
            
            // CHARACTER GRAFIK
            // Gäste
            if ($active_uid == 0 AND $graphic_guest == 1) {
                $graphic_link = $theme['imgdir']."/".$graphic_defaultgraphic;
            } else {
                // Avatar
                if ($graphic_type == 0) {
                    $chara_graphic = $user['avatar'];
                } 
                // Uploadsystem
                else if ($graphic_type == 1) {
                    $path = $db->fetch_field($db->simple_select("uploadsystem", "path", "identification = '".$graphic_uploadsystem."'"), "path");                  
                    $value = $db->fetch_field($db->simple_select("uploadfiles", $graphic_uploadsystem, "ufid = '".$uid."'"), $graphic_uploadsystem);

                    if ($value != "") {
                        $chara_graphic = $path."/".$value;
                    } else {
                        $chara_graphic = "";
                    }
                }
                // Profilfelder
                else if ($graphic_type == 2) {
                    $fid = "fid".$graphic_profilefield;
                    $chara_graphic = $db->fetch_field($db->simple_select("userfields", $fid, "ufid = '".$uid."'"), $fid);
                }
                // Steckifelder
                else if ($graphic_type == 3) {	
                    $fieldid = $db->fetch_field($db->simple_select("application_ucp_fields", "id", "fieldname = '".$graphic_characterfield."'"), "id");                  
                    $chara_graphic = $db->fetch_field($db->simple_select("application_ucp_userfields", "value", "uid = '".$uid."' AND fieldid = '".$fieldid."'"), "value");
                }

                // wenn man kein Grafik hat => Default
                if ($chara_graphic == "") {
                    // Dateinamen bauen
                    $graphic_link = $theme['imgdir']."/".$graphic_defaultgraphic;
                } else {
                    // Dateinamen bauen
                    $graphic_link = $chara_graphic;
                }
            }

        } 
        // Nicht vorhanden -> "Gast" = gespeicherter Name
        else {
            // CHARACTER NAME
            // ohne alles
            $charactername = $charactername_formated = $charactername_formated_link = $charactername_link = $quote['username'];
            // Name gesplittet
            $charactername_fullname = explode(" ", $charactername);
            $charactername_first = array_shift($charactername_fullname);
            $charactername_last = implode(" ", $charactername_fullname); 

            // CHARACTER GRAFIK -> immer Default
            $graphic_link = $theme['imgdir']."/".$graphic_defaultgraphic;
        }

        // Szenenlink
        $thread = get_thread($tid);
        $scene_link = "<a href=\"showthread.php?tid=".$tid."&amp;pid=".$pid."#pid".$pid."\">".$thread['subject']."</a>";

        // Postdatum
        $postdate = my_date('relative', $date);

        // Profilfelder
        $characterfield = inplayquotes_build_characterfield($uid);
        // Szeneninfos
        $scenefield = inplayquotes_build_scenefield($tid);

        // Reaktionen
        $pos = strpos(",".$active_charastring.",", ",".$uid.",");
        if ($reactions_setting == 1) {

            // Leer laufen lassen
            $quote_preview = "";
            $prev_quote = "";

            // Mit Infos füllen
            if(my_strlen($inplayquote) > 200) {
                $prev_quote = my_substr($inplayquote, 0, 200)."...";
            } else {
                $prev_quote = $inplayquote;
            }
            $quote_preview = $lang->sprintf($lang->inplayquotes_reactions_quote_preview, $charactername, $prev_quote);

            // Pro Charakter
            if ($reactions_option == 1) {
                // Bishierige Reaktionen auf das Zitat
                $query_reactionsUser = $db->query("SELECT reaction FROM ".TABLE_PREFIX."inplayquotes_reactions
                WHERE qid = '".$qid."'
                AND uid = '".$active_uid."'                       
                ");    
            } 
            // pro Spieler
            else {
                // Bishierige Reaktionen auf das Zitat
                $query_reactionsUser = $db->query("SELECT reaction FROM ".TABLE_PREFIX."inplayquotes_reactions
                WHERE qid = '".$qid."'
                AND uid IN (".$active_charastring.")                          
                ");   
            } 

            $reactionsUser_qids = "";
            while ($reactionsUser = $db->fetch_array($query_reactionsUser)){
                $reactionsUser_qids .= $reactionsUser['reaction'].",";
            } 
            if(!empty($reactionsUser_qids)) {
                // letztes Komma abschneiden 
                $reactionsUser_string = substr($reactionsUser_qids, 0, -1);
                $reaction_sql = "WHERE rsid NOT IN(".$reactionsUser_string.")";

                // Pro Charakter
                if ($reactions_option == 1) {
                    $allreacted_query = $db->query("SELECT r.reaction, rs.image, MAX(rid) AS max_rid FROM ".TABLE_PREFIX."inplayquotes_reactions r
                    LEFT JOIN ".TABLE_PREFIX."inplayquotes_reactions_settings rs ON rs.rsid = r.reaction
                    WHERE uid = '".$active_uid."' 
                    AND qid = '".$qid."' 
                    GROUP BY r.reaction
                    ");
                }
                // pro Spieler
                else {
                    $allreacted_query = $db->query("SELECT r.reaction, rs.image  FROM ".TABLE_PREFIX."inplayquotes_reactions r
                    LEFT JOIN ".TABLE_PREFIX."inplayquotes_reactions_settings rs ON rs.rsid = r.reaction
                    WHERE uid IN (".$active_charastring.") 
                    AND qid = '".$qid."' 
                    GROUP BY r.reaction
                    ");
                }

                $reacted_images = "";
                $reacted_reactions = "";
                while($allreacted = $db->fetch_array($allreacted_query)) {
        
                    // Leer laufen lassen
                    $reaction = "";          
                    $image = "";

                    // Mit Infos füllen
                    $reaction = $allreacted['reaction'];         
                    $image = $allreacted['image'];

                    eval("\$reacted_images .= \"".$templates->get("inplayquotes_reactions_reacted_image")."\";");
                }

                eval("\$reacted_reactions = \"".$templates->get("inplayquotes_reactions_reacted")."\";");
            } else {
                $reaction_sql = "";
                $reacted_reactions = "";
            }

            // Bilder
            $allreactions_query = $db->query("SELECT * FROM ".TABLE_PREFIX."inplayquotes_reactions_settings
            ".$reaction_sql);

            $reactions_images = "";
            while($allreaction = $db->fetch_array($allreactions_query)) {
                // Leer laufen lassen
                $rsid = "";
                $name = "";
                $image = "";

                // Mit Infos füllen
                $rsid = $allreaction['rsid'];
                $name = $allreaction['name'];
                $image = $allreaction['image'];

                eval("\$reactions_images .= \"".$templates->get("inplayquotes_reactions_add_popup_image")."\";");
            }

            eval("\$reactions_popup = \"".$templates->get("inplayquotes_reactions_add_popup")."\";");

            $check = $db->num_rows($db->query("SELECT * FROM ".TABLE_PREFIX."inplayquotes_reactions
            WHERE qid = '".$qid."'"));
            // Hat schon Reaktionen
            if ($check > 0) {

                $reactions_query = $db->query("SELECT rs.*, GROUP_CONCAT(r.rid) AS rid_list FROM ".TABLE_PREFIX."inplayquotes_reactions r 
                LEFT JOIN ".TABLE_PREFIX."inplayquotes_reactions_settings rs ON rs.rsid = r.reaction 
                WHERE qid = '".$qid."' 
                GROUP BY rs.rsid");
                $stored_reactions = "";
                while($reaction = $db->fetch_array($reactions_query)) {
                    // Leer laufen lassen
                    $rsid = "";
                    $name = "";
                    $image = "";
                    $rid = "";
                    $count = "";
                    $title = "";

                    // Mit Infos füllen
                    $rsid = $reaction['rsid'];
                    $name = $reaction['name'];
                    $image = $reaction['image'];

                    $query_quote = $db->query("SELECT reaction,uid,username FROM ".TABLE_PREFIX."inplayquotes_reactions
                    WHERE qid = '".$qid."'  
                    AND reaction = '".$rsid."'
                    ORDER BY username
                    ");

                    $count = $db->num_rows($query_quote);

                    // Namen von den Leuten
                    // Charakternamen
                    if ($reactions_option == 1) {
                        $all_usernames = "";
                        while ($alluser = $db->fetch_array($query_quote)){
                            $user = get_user($alluser['uid']);
                            // Vorhanden -> daten aus der Users Tabelle
                            if (!empty($user)) {
                                $all_usernames .= $user['username'].", ";
                            } else {
                                $all_usernames .= $alluser['username'].", ";
                            }
                        } 
                        // letztes Komma abschneiden 
                        $title = substr($all_usernames, 0, -2);
                    }
                    // Spielername
                    else {
                        $all_playername = "";
                        while ($alluser = $db->fetch_array($query_quote)){
                            $user = get_user($alluser['uid']);
                            // Vorhanden -> daten aus der Users Tabelle
                            if (!empty($user)) {
                                // wenn Zahl => klassisches Profilfeld
                                if (is_numeric($playername_setting)) {
                                    $playername = $db->fetch_field($db->simple_select("userfields", "fid".$playername_setting, "ufid = '".$alluser['uid']."'"), "fid".$playername_setting);
                                } else {
                                    $playerid = $db->fetch_field($db->simple_select("application_ucp_fields", "id", "fieldname = '".$playername_setting."'"), "id");
                                    $playername = $db->fetch_field($db->simple_select("application_ucp_userfields", "value", "fieldid = '".$playerid."' AND uid = '".$alluser['uid']."'"), "value");
                                }
                            } else {
                                $playername = $alluser['username'].$lang->inplayquotes_filter_select_character_formerly;
                            }
                            $all_playername .= $playername.", ";
                        } 
                        // letztes Komma abschneiden 
                        $title = substr($all_playername, 0, -2);
                    }

                    eval("\$stored_reactions .= \"".$templates->get("inplayquotes_reactions_stored")."\";");
                }

                // Pro Charakter
                if ($reactions_option == 1) {
                    $account_reactions = $db->num_rows($db->query("SELECT reaction FROM ".TABLE_PREFIX."inplayquotes_reactions
                    WHERE qid = '".$qid."'  
                    AND uid = '".$active_uid."'                
                    "));

                    if ($all_reactions_options != $account_reactions) {
                        $check_add = 1;
                    } else {
                        $check_add = 0;
                    }   
                } 
                // pro Spieler
                else {
                    $player_reactions = $db->num_rows($db->query("SELECT reaction FROM ".TABLE_PREFIX."inplayquotes_reactions
                    WHERE qid = '".$qid."'  
                    AND uid IN (".$active_charastring.")                
                    "));

                    $account_reactions = $db->num_rows($db->query("SELECT reaction FROM ".TABLE_PREFIX."inplayquotes_reactions
                    WHERE qid = '".$qid."'  
                    AND uid = '".$active_uid."'                
                    "));

                    $all_reactions_math = $all_reactions_options*$count_allcharas;

                    if ($all_reactions_math != $player_reactions AND $all_reactions_options != $account_reactions) {
                        $check_add = 1;
                    } else {
                        $check_add = 0;
                    }    
                } 

                if ($pos === false AND $check_add == 1 AND $active_uid != 0) {
                    eval("\$reactions_add = \"".$templates->get("inplayquotes_reactions_add")."\";");
                } else {
                    $reactions_add = "";
                }

                if ($mybb->usergroup['canmodcp'] == '1') {
                    $delete_reactions = "<span class=\"inplayquotes_overview_bit_reactions_delete\"><a href=\"misc.php?action=inplayquotes&amp;allreactions_delete=".$qid."\">".$lang->inplayquotes_allreactions_delete."</a></span>";
                } else {
                    $delete_reactions = "";
                }

                eval("\$reactions = \"".$templates->get("inplayquotes_reactions")."\";");
            } else {
                $reacted_reactions = "";

                if ($pos === false AND $active_uid != 0) {
                    eval("\$reactions = \"".$templates->get("inplayquotes_reactions_add")."\";");
                } else {
                    $reactions = "";
                }
            }
        } else {
            $reactions = "";
        }

        // Lösch Link
        if(($mybb->usergroup['canmodcp'] == '1' OR $pos !== false) AND $active_uid != 0){
            $del_quote = "<a href=\"misc.php?action=inplayquotes&quote_delete=".$qid."\" onClick=\"return confirm('Möchtest du dieses Zitat wirklich löschen?');\">".$lang->inplayquotes_quote_delete."</a>";
        } else {
            $del_quote = "";
        }

        eval("\$inplayquote_bit = \"".$templates->get("inplayquotes_index_bit")."\";");
    }

    eval("\$inplayquotes_index = \"".$templates->get("inplayquotes_index")."\";");
}

// PROFIL
function inplayquotes_profile() {

	global $db, $mybb, $lang, $templates, $memprofile, $inplayquotes_memberprofile, $parser, $code_html, $inplayquotes_bit;

    // EINSTELLUNGEN
	$profile_setting = $mybb->settings['inplayquotes_profile'];
    $overview_filter = $mybb->settings['inplayquotes_overview_filter'];

    // DAS HTML UND CO ANGEZEIGT WIRD
    require_once MYBB_ROOT."inc/class_parser.php";
    $parser = new postParser;
    $code_html = array(
        "allow_html" => 1,
        "allow_mycode" => 1,
        "allow_smilies" => 1,
        "allow_imgcode" => 1,
        "filter_badwords" => 0,
        "nl2br" => 1,
        "allow_videocode" => 0
    );
    
    // SPRACHDATEI
	$lang->load('inplayquotes');

    if ($profile_setting == 0) return;

    $random_quote = $db->query("SELECT * FROM ".TABLE_PREFIX."inplayquotes	
    WHERE uid = '".$memprofile['uid']."'
	ORDER BY rand()
	LIMIT 1
    ");

    eval("\$inplayquote_bit = \"".$templates->get("inplayquotes_memberprofile_bit_none")."\";");
    $all_inplayquotes = "<a href=\"misc.php?action=inplayquotes\">".$lang->inplayquotes_overview."</a>";
    while($quote = $db->fetch_array($random_quote)) {

        // Leer laufen lassen
        $qid = "";
        $uid = "";
        $tid = "";
        $pid = "";
        $date = "";
        $inplayquote = "";

        // Mit Infos füllen
        $qid = $quote['qid'];
        $uid = $quote['uid'];
        $tid = $quote['tid'];
        $pid = $quote['pid'];
        $date = $quote['date'];
        $inplayquote = $parser->parse_message($quote['quote'], $code_html);

        // Szenenlink
        $thread = get_thread($tid);
        $scene_link = "<a href=\"showthread.php?tid=".$tid."&amp;pid=".$pid."#pid".$pid."\">".$thread['subject']."</a>";

        // Postdatum
        $postdate = my_date('relative', $date);

        // Profilfelder
        $characterfield = inplayquotes_build_characterfield($uid);
        // Szeneninfos
        $scenefield = inplayquotes_build_scenefield($tid);

        $all_inplayquotes = "<a href=\"misc.php?action=inplayquotes&profile_direct=".$memprofile['uid']."\">".$lang->sprintf($lang->inplayquotes_memberprofile_link, $memprofile['username'])."</a>";

        eval("\$inplayquote_bit = \"".$templates->get("inplayquotes_memberprofile_bit")."\";");
    }

    eval("\$inplayquotes_memberprofile = \"".$templates->get("inplayquotes_memberprofile")."\";");
}

// ONLINE LOCATION
function inplayquotes_online_activity($user_activity) {

	global $parameters, $user;

	$split_loc = explode(".php", $user_activity['location']);
	if(isset($user['location']) && $split_loc[0] == $user['location']) { 
		$filename = '';
	} else {
		$filename = my_substr($split_loc[0], -my_strpos(strrev($split_loc[0]), "/"));
	}

	switch ($filename) {
		case 'misc':
			if ($parameters['action'] == "inplayquotes") {
				$user_activity['activity'] = "inplayquotes";
			}
            break;
	}


	return $user_activity;
}
function inplayquotes_online_location($plugin_array) {

	global $mybb, $theme, $lang, $db;
    
    // SPRACHDATEI LADEN
    $lang->load("inplayquotes");

	if ($plugin_array['user_activity']['activity'] == "inplayquotes") {
		$plugin_array['location_name'] = $lang->inplayquotes_online_location;
	}

	return $plugin_array;
}

// MyALERTS STUFF
function inplayquotes_alerts() {

	global $mybb, $lang;
	$lang->load('inplayquotes');

    // ZITAT HINZUFÜGEN
    /**
	 * Alert formatter for my custom alert type.
	 */
	class MybbStuff_MyAlerts_Formatter_InplayquotesAddquoteFormatter extends MybbStuff_MyAlerts_Formatter_AbstractFormatter
	{
	    /**
	     * Format an alert into it's output string to be used in both the main alerts listing page and the popup.
	     *
	     * @param MybbStuff_MyAlerts_Entity_Alert $alert The alert to format.
	     *
	     * @return string The formatted alert string.
	     */
	    public function formatAlert(MybbStuff_MyAlerts_Entity_Alert $alert, array $outputAlert)
	    {
			global $db;
			$alertContent = $alert->getExtraDetails();
            $userid = $db->fetch_field($db->simple_select("users", "uid", "username = '{$alertContent['username']}'"), "uid");
            $user = get_user($userid);
            $alertContent['username'] = format_name($user['username'], $user['usergroup'], $user['displaygroup']);
	        return $this->lang->sprintf(
	            $this->lang->inplayquotes_alert_add_quote,
                $alertContent['username'],
                $alertContent['from'],
                $alertContent['scene'],
                $alertContent['pid'],
                $alertContent['tid'],
                $alertContent['qid']
	        );
	    }

	    /**
	     * Init function called before running formatAlert(). Used to load language files and initialize other required
	     * resources.
	     *
	     * @return void
	     */
	    public function init()
	    {
	        if (!$this->lang->inplayquotes) {
	            $this->lang->load('inplayquotes');
	        }
	    }

	    /**
	     * Build a link to an alert's content so that the system can redirect to it.
	     *
	     * @param MybbStuff_MyAlerts_Entity_Alert $alert The alert to build the link for.
	     *
	     * @return string The built alert, preferably an absolute link.
	     */
	    public function buildShowLink(MybbStuff_MyAlerts_Entity_Alert $alert)
	    {
            $alertContent = $alert->getExtraDetails();
            return $this->mybb->settings['bburl'] . '/misc.php?action=inplayquotes&alert_direct='.$alertContent['qid'];
	    }
	}

	if (class_exists('MybbStuff_MyAlerts_AlertFormatterManager')) {
		$formatterManager = MybbStuff_MyAlerts_AlertFormatterManager::getInstance();

		if (!$formatterManager) {
			$formatterManager = MybbStuff_MyAlerts_AlertFormatterManager::createInstance($mybb, $lang);
		}

		$formatterManager->registerFormatter(
				new MybbStuff_MyAlerts_Formatter_InplayquotesAddquoteFormatter($mybb, $lang, 'inplayquotes_add_quote')
		);
    }

    // REAKTION HINZUFÜGEN
    /**
	 * Alert formatter for my custom alert type.
	 */
	class MybbStuff_MyAlerts_Formatter_InplayquotesAddreactionFormatter extends MybbStuff_MyAlerts_Formatter_AbstractFormatter
	{
	    /**
	     * Format an alert into it's output string to be used in both the main alerts listing page and the popup.
	     *
	     * @param MybbStuff_MyAlerts_Entity_Alert $alert The alert to format.
	     *
	     * @return string The formatted alert string.
	     */
	    public function formatAlert(MybbStuff_MyAlerts_Entity_Alert $alert, array $outputAlert)
	    {
			global $db;
			$alertContent = $alert->getExtraDetails();
            $userid = $db->fetch_field($db->simple_select("users", "uid", "username = '{$alertContent['username']}'"), "uid");
            $user = get_user($userid);
            $alertContent['username'] = format_name($user['username'], $user['usergroup'], $user['displaygroup']);
	        return $this->lang->sprintf(
	            $this->lang->inplayquotes_alert_add_reaction,
                $alertContent['username'],
                $alertContent['from'],
                $alertContent['image'],
                $alertContent['qid']
	        );
	    }

	    /**
	     * Init function called before running formatAlert(). Used to load language files and initialize other required
	     * resources.
	     *
	     * @return void
	     */
	    public function init()
	    {
	        if (!$this->lang->inplayquotes) {
	            $this->lang->load('inplayquotes');
	        }
	    }

	    /**
	     * Build a link to an alert's content so that the system can redirect to it.
	     *
	     * @param MybbStuff_MyAlerts_Entity_Alert $alert The alert to build the link for.
	     *
	     * @return string The built alert, preferably an absolute link.
	     */
	    public function buildShowLink(MybbStuff_MyAlerts_Entity_Alert $alert)
	    {
            $alertContent = $alert->getExtraDetails();
            return $this->mybb->settings['bburl'] . '/misc.php?action=inplayquotes&alert_direct='.$alertContent['qid'];
	    }
	}

	if (class_exists('MybbStuff_MyAlerts_AlertFormatterManager')) {
		$formatterManager = MybbStuff_MyAlerts_AlertFormatterManager::getInstance();

		if (!$formatterManager) {
			$formatterManager = MybbStuff_MyAlerts_AlertFormatterManager::createInstance($mybb, $lang);
		}

		$formatterManager->registerFormatter(
				new MybbStuff_MyAlerts_Formatter_InplayquotesAddreactionFormatter($mybb, $lang, 'inplayquotes_add_reaction')
		);
    }
}

// CACHE
function inplayquotes_cache(){
	global $db, $cache;

	$iqreactions = array();

    $query = $db->simple_select("inplayquotes_reactions_settings", "*", "", array('order_by' => 'name', 'order_dir' => 'ASC'));
	while($iqreaction = $db->fetch_array($query)){
        $iqreactions[$iqreaction['rsid']] = $iqreaction;
	}
	$cache->update('inplayquotes_reactions_settings', $iqreactions);
}

// ACCOUNTSWITCHER HILFSFUNKTION - FILTER
function inplayquotes_get_allchars_filter($player_filter) {
	global $db;

	$charas = array();

	$get_all_users = $db->query("SELECT uid,username FROM " . TABLE_PREFIX . "users WHERE (as_uid = ".$player_filter.") OR (uid = ".$player_filter.") ORDER BY username");
	while ($users = $db->fetch_array($get_all_users)) {
	  $uid = $users['uid'];
	  $charas[$uid] = $users['username'];
	}
	return $charas;  
}

// ACCOUNTSWITCHER HILFSFUNKTION
function inplayquotes_get_allchars($uid) {
	global $db, $cache, $mybb, $lang, $templates, $theme, $header, $headerinclude, $footer;

	//für den fall nicht mit hauptaccount online
	if (isset(get_user($uid)['as_uid'])) {
        $as_uid = intval(get_user($uid)['as_uid']);
    } else {
        $as_uid = 0;
    }

	$charas = array();
	if ($as_uid == 0) {
	  // as_uid = 0 wenn hauptaccount oder keiner angehangen
	  $get_all_users = $db->query("SELECT uid,username FROM " . TABLE_PREFIX . "users WHERE (as_uid = '".$uid."') OR (uid = '".$uid."') ORDER BY username");
	} else if ($as_uid != 0) {
	  //id des users holen wo alle an gehangen sind 
	  $get_all_users = $db->query("SELECT uid,username FROM " . TABLE_PREFIX . "users WHERE (as_uid = '".$as_uid."') OR (uid = '".$uid."') OR (uid = '".$as_uid."') ORDER BY username");
	}
	while ($users = $db->fetch_array($get_all_users)) {
	  $uid = $users['uid'];
	  $charas[$uid] = $users['username'];
	}
	return $charas;  
}

// Variabel Bau Funktion - danke Katja <3
function inplayquotes_build_characterfield($uid){

    global $db, $mybb;
  
    // Rückgabe als Array, also einzelne Variablen die sich ansprechen lassen
    $array = array();

    // welches System
	$profilfeldsystem = $mybb->settings['inplayquotes_profilfeldsystem'];

    // klassische Profilfelder 0
    if ($profilfeldsystem == 0) {
        //erst einmal alle FIDs bekommen
        $allfid_query = $db->query("SELECT fid FROM ".TABLE_PREFIX."profilefields");
    
        $all_fid = [];
        while($allfid = $db->fetch_array($allfid_query)) {
            $all_fid[] = $allfid['fid'];
        }

        foreach ($all_fid as $fid) {
            // Inhalt vom Feld
            $fieldvalue = $db->fetch_field($db->simple_select("userfields", "fid".$fid, "ufid = '".$uid."'"), "fid".$fid);
  
            // {$characterfield['fidX']}  
            $arraylabel = "fid".$fid;
            $array[$arraylabel] = $fieldvalue;
        }

    }
    // Steckbriefplugin 1
    else if ($profilfeldsystem == 1){
        //erst einmal alle IDs und Identifikatoren bekommen
        $allfields_query = $db->query("SELECT fieldname, id FROM ".TABLE_PREFIX."application_ucp_fields");
    
        $all_fields = [];
        while($allfields = $db->fetch_array($allfields_query)) {
            $all_fields[$allfields['id']] = $allfields['fieldname'];
        }

        foreach ($all_fields as $fieldid => $fieldname) {
            // Inhalt vom Feld
            $fieldvalue = $db->fetch_field($db->simple_select("application_ucp_userfields", "value", "uid = '".$uid."' AND fieldid = '".$fieldid."'"), "value");
  
            // {$characterfield['identifikator']}  
            $arraylabel = $fieldname;
            $array[$arraylabel] = $fieldvalue;
        }

    }
    // beides 2
    else {
        //alle Profilfelder bekommen
        $allprofilefields_query = $db->query("SELECT fid FROM ".TABLE_PREFIX."profilefields");
    
        $all_profilefields = [];
        while($allprofilefields = $db->fetch_array($allprofilefields_query)) {
            $all_profilefields[] = "fid".$allprofilefields['fid'];
        }

        //alle Steckifelder bekommen
        $allapplicationfields_query = $db->query("SELECT id FROM ".TABLE_PREFIX."application_ucp_fields");
    
        $all_applicationfields = [];
        while($allapplicationfields = $db->fetch_array($allapplicationfields_query)) {
            $all_applicationfields[] = $allapplicationfields['id'];
        }

        // Zusammenführen
        $all_fields = array_merge($all_profilefields, $all_applicationfields);

        foreach ($all_fields as $fieldid) {

            // Überprüfen ob Profilfeld oder Steckifeld
            if(strpos($fieldid, 'fid') !== false) {
                $fieldvalue = $db->fetch_field($db->simple_select("userfields", $fieldid, "ufid = '".$uid."'"), $fieldid);
                $arraylabel = $fieldid;
            } else {
                $fieldvalue = $db->fetch_field($db->simple_select("application_ucp_userfields", "value", "uid = '".$uid."' AND fieldid = '".$fieldid."'"), "value");
                $arraylabel = $db->fetch_field($db->simple_select("application_ucp_fields", "fieldname", "id = '".$fieldid."'"), "fieldname");
            }

            // {$characterfield['identifikator']} OR {$characterfield['fidX']}  
            $array[$arraylabel] = $fieldvalue;
        }
    }
  
    return $array;
}

// Variabel Bau Funktion - danke Katja <3
function inplayquotes_build_scenefield($tid){

    global $db, $mybb, $lang;

    // welches System
	$inplaytrackersystem = $mybb->settings['inplayquotes_inplaytracker'];
    if ($inplaytrackersystem == 0) return;
  
    // Rückgabe als Array, also einzelne Variablen die sich ansprechen lassen
    $array = array();

    // Inplaytracker 2.0 von sparks fly 1
    if ($inplaytrackersystem == 0) {
        //erst einmal alle Szenenfelder bekommen
        $scene_fields = ["partners", "ipdate", "iport", "ipdaytime", "openscene", "postorder"];

        foreach ($scene_fields as $field) {

            // Inhalt vom Feld
            $fieldvalue = $db->fetch_field($db->simple_select("threads", $field, "tid = '".$tid."'"), $field);

            if ($field == "partners") {
                $partners = explode(",", $fieldvalue);
                $partnerusers = array();

                foreach ($partners as $partner) { 
                    $charakter = get_user($partner);
                    $taguser = build_profile_link($charakter['username'], $partner);
                    $partnerusers[] = $taguser;
                }

                $fieldvalue = implode(" &raquo; ", $partnerusers);
            }

            if ($field == "ipdate") {
                if($mybb->settings['inplaytracker_timeformat'] == "0") {
                    $fieldvalue = date("d.m.Y", $fieldvalue);
                }
                else {
                    $fieldvalue = $fieldvalue;
                }
            }

            if ($field == "openscene") {
                if ($fieldvalue == -1) {
                    $fieldvalue = "Private Szene";
                } else if ($fieldvalue == 0) {
                    $fieldvalue = "Nach Absprache";
                } else if ($fieldvalue == 1) {
                    $fieldvalue = "Öffentliche Szene";
                }
            }

            if ($field == "postorder") {
                if ($fieldvalue == 1) {
                    $fieldvalue = "Feste Postingreihenfolge";
                } else if ($fieldvalue == 0) {
                    $fieldvalue = "Keine Reihenfolge";
                }
            }

            // {$scenefield['fidX']}  
            $arraylabel = $field;
            $array[$arraylabel] = $fieldvalue;
        }
    }
    // Inplaytracker 3.0 von sparks fly 2
    else if ($inplaytrackersystem == 2){
        //erst einmal alle Szenenfelder bekommen
        $scene_fields = ["location", "date", "shortdesc", "openscene", "partners"];

        foreach ($scene_fields as $field) {

            if ($field == "partners") {
                // Inhalt vom Feld
                $scenes_partners = $db->simple_select("ipt_scenes_partners", "uid", "tid='".$tid."'");

                $partners = [];
                while ($partner = $db->fetch_array($scenes_partners)) {
                    $tagged_user = get_user($partner['uid']);
                    $partners[] = $tagged_user['username'];
                }
                $fieldvalue = implode(" &bull; ", $partners);
            } else {

                // Inhalt vom Feld
                $fieldvalue = $db->fetch_field($db->simple_select("ipt_scenes", $field, "tid = '".$tid."'"), $field);

                if ($field == "date") {
                    $fieldvalue = date("d.m.Y", $fieldvalue);
                }

                if ($field == "openscene") {
                    if ($fieldvalue == 0) {
                        $fieldvalue = "Private Szene";
                    } else if ($fieldvalue == 1) {
                        $fieldvalue = "Öffentliche Szene";
                    }
                }
            }

            // {$scenefield['fidX']}  
            $arraylabel = $field;
            $array[$arraylabel] = $fieldvalue;
        }

    }
    // Szenentracker von risuena 3
    else if ($inplaytrackersystem == 3) {
        //erst einmal alle Szenenfelder bekommen
        $scene_fields = ["scenetracker_date", "scenetracker_place", "scenetracker_user", "scenetracker_trigger"];

        foreach ($scene_fields as $field) {

            // Inhalt vom Feld
            $fieldvalue = $db->fetch_field($db->simple_select("threads", $field, "tid = '".$tid."'"), $field);

            if ($field == "scenetracker_date") {
                $fieldvalue = date('d.m.Y - H:i', strtotime($fieldvalue));
            }

            if ($field == "scenetracker_user") {
                $partners = explode(",", $fieldvalue);
                $fieldvalue = implode(" &bull; ", $partners);
            }

            // {$scenefield['fidX']}  
            $arraylabel = $field;
            $array[$arraylabel] = $fieldvalue;
        }
    }
    // Inplaytracker von little.evil.genius 4
    else if ($inplaytrackersystem == 4) {
        //erst einmal alle Szenenfelder bekommen
        $scene_fields = ["partners", "partners_username", "date", "trigger_warning", "scenetype", "postorder"];

        $fields_query = $db->query("SELECT identification FROM ".TABLE_PREFIX."inplayscenes_fields");
        while ($field = $db->fetch_array($fields_query)) {
            $scene_fields[] = $db->escape_string($field['identification']);
        }

        // Sprachdatei laden
        $lang->load('inplayscenes');

        $scenetype_setting = $mybb->settings['inplayscenes_scenetype'];
        $month_setting = $mybb->settings['inplayscenes_months'];
        $color_setting = $mybb->settings['inplayscenes_groupcolor'];
        $hide_setting = $mybb->settings['inplayscenes_hide'];
    
        $postorderoptions = [
            '1' => $lang->inplayscenes_postorder_fixed,
            '0' => $lang->inplayscenes_postorder_none
        ];
    
        if ($scenetype_setting == 1) {
            $sceneoptions = [
                '0' => $lang->inplayscenes_scenetype_private,
                '1' => $lang->inplayscenes_scenetype_agreed,
                '2' => $lang->inplayscenes_scenetype_open
            ];
            if ($hide_setting == 1) {
                array_push($sceneoptions, $lang->inplayscenes_scenetype_hide);
            }
        } else {
            if ($hide_setting == 1) {
                $sceneoptions = [
                    '0' => '',
                    '3' => $lang->inplayscenes_scenetype_hide
                ];
            }
        }

        $months = array(
            '01' => $lang->inplayscenes_jan,
            '02' => $lang->inplayscenes_feb,
            '03' => $lang->inplayscenes_mar,
            '04' => $lang->inplayscenes_apr,
            '05' => $lang->inplayscenes_mai,
            '06' => $lang->inplayscenes_jun,
            '07' => $lang->inplayscenes_jul,
            '08' => $lang->inplayscenes_aug,
            '09' => $lang->inplayscenes_sep,
            '10' => $lang->inplayscenes_okt,
            '11' => $lang->inplayscenes_nov,
            '12' => $lang->inplayscenes_dez
        );

        foreach ($scene_fields as $field) {

            // Inhalt vom Feld
            $fieldvalue = $db->fetch_field($db->simple_select("inplayscenes", $field, "tid = '".$tid."'"), $field);

            if ($field == "date") {
                list($year, $month, $day) = explode('-', $fieldvalue);
                if ($month_setting == 0) {
                    $fieldvalue = $day.".".$month.".".$year;
                } else {
                    $fieldvalue = $day.". ".$months[$month]." ".$year;
                }
            }

            if ($field == "partners" || $field == "partners_username") {

                if ($field == "partners") {
                    $partners_username = $db->fetch_field($db->simple_select("inplayscenes", 'partners_username', "tid = '".$tid."'"), 'partners_username');
                    $partners = $fieldvalue;
                } else if ($field == "partners_username") {
                    $partners_username = $fieldvalue;
                    $partners = $db->fetch_field($db->simple_select("inplayscenes", 'partners', "tid = '".$tid."'"), 'partners');
                }
            
                $usernames = explode(",", $partners_username);
                $uids = explode(",", $partners);
            
                $partners = [];
                foreach ($uids as $key => $uid) {
            
                    $tagged_user = get_user($uid);
                    if (!empty($tagged_user)) {
                        if ($color_setting == 1) {
                            $username = format_name($tagged_user['username'], $tagged_user['usergroup'], $tagged_user['displaygroup']);
                        } else {
                            $username = $tagged_user['username'];
                        }
                        $taguser = build_profile_link($username, $uid);
                    } else {
                        $taguser = $usernames[$key];
                    }
                    $partners[] = $taguser;
                }
                $fieldvalue = implode(" &raquo; ", $partners);
            }

            if ($field == "scenetype") {
                if ($scenetype_setting == 1) {
                    $fieldvalue = $sceneoptions[$fieldvalue];
                    
                    if ($hide_setting == 0 && $fieldvalue == 3) {
                        $fieldvalue = $sceneoptions[0];
                    }
                } else if ($hide_setting == 1) {
                    $fieldvalue = $sceneoptions[$fieldvalue];
                } else {
                    $fieldvalue = "";
                }
            }

            if ($field == "postorder") {
                $fieldvalue = $postorderoptions[$fieldvalue];
            }

            // {$scenefield['fidX']}  
            $arraylabel = $field;
            $array[$arraylabel] = $fieldvalue;
        }
    }
    // Inplaytracker von Ales 1.0 5
    else if ($inplaytrackersystem == 5) {
        //erst einmal alle Szenenfelder bekommen
        $scene_fields = ["spieler", "date", "ort", "ip_time"];

        foreach ($scene_fields as $field) {

            // Inhalt vom Feld
            $fieldvalue = $db->fetch_field($db->simple_select("threads", $field, "tid = '".$tid."'"), $field);

            if ($field == "date") {
                $fieldvalue = date("d.m.Y", strtotime($fieldvalue));
            }

            if ($field == "spieler") {
                $partners = explode(", ", $fieldvalue);
                $partnerusers = array();
                foreach ($partners as $partner) { 
                    $charakter = get_user($partner);
                    $taguser = build_profile_link($charakter['username'], $partner);
                    $partnerusers[] = $taguser;
                }

                $fieldvalue = implode(" &raquo; ", $partnerusers);
            }

            // {$scenefield['fidX']}  
            $arraylabel = $field;
            $array[$arraylabel] = $fieldvalue;
        }
    }
    // Inplaytracker von Ales 2.0 6
    else if ($inplaytrackersystem == 6) {
        //erst einmal alle Szenenfelder bekommen
        $scene_fields = ["charas", "date", "time", "place"];

        foreach ($scene_fields as $field) {

            // Inhalt vom Feld
            $fieldvalue = $db->fetch_field($db->simple_select("threads", $field, "tid = '".$tid."'"), $field);

            if ($field == "date") {
                $fieldvalue = date("d.m.Y", strtotime($fieldvalue));
            }

            if ($field == "charas") {
                $partners = explode(",", $fieldvalue);
                $partnerusers = array();
                foreach ($partners as $partner) { 
                    $chara_query = $db->simple_select("users", "*", "username ='".$db->escape_string($partner)."'");
                    $charaktername = $db->fetch_array($chara_query);

                    if (!empty($charaktername)) {
                        $username = format_name($charaktername['username'], $charaktername['usergroup'], $charaktername['displaygroup']);
                        $partnerusers[] = build_profile_link($username, $charaktername['uid']);
                    } else {
                        $partnerusers[]= $partner;
                    }
                }

                $fieldvalue = implode(" &raquo; ", $partnerusers);
            }

            // {$scenefield['fidX']}  
            $arraylabel = $field;
            $array[$arraylabel] = $fieldvalue;
        }
    }
  
    return $array;
}

// QID ERMITTELN
function inplayquotes_getNextId($tablename){
    global $db;
    $databasename = $db->fetch_field($db->write_query("SELECT DATABASE()"), "DATABASE()");
    $lastId = $db->fetch_field($db->write_query("SELECT AUTO_INCREMENT FROM information_schema.TABLES 
    WHERE TABLE_SCHEMA = '" . $databasename . "' AND TABLE_NAME = '" . TABLE_PREFIX . $tablename . "'"), "AUTO_INCREMENT");
    return $lastId;
}

// Zwischen den Foren
function inplayquotes_forumbits(&$forum) {

    global $db, $cache, $mybb, $lang, $templates, $theme, $parser, $code_html;

    if ($forum['fid'] != $mybb->settings['inplayquotes_indexarea']) {
        $forum['inplayquotes_index'] = "";
        return;    
    }

    // EINSTELLUNGEN
	$graphic_type = $mybb->settings['inplayquotes_overview_graphic'];
	$graphic_uploadsystem = $mybb->settings['inplayquotes_overview_graphic_uploadsystem'];
	$graphic_profilefield = $mybb->settings['inplayquotes_overview_graphic_profilefield'];
	$graphic_characterfield = $mybb->settings['inplayquotes_overview_graphic_characterfield'];
	$graphic_defaultgraphic = $mybb->settings['inplayquotes_overview_graphic_defaultgraphic'];
	$graphic_guest = $mybb->settings['inplayquotes_overview_graphic_guest'];
    $reactions_setting = $mybb->settings['inplayquotes_reactions'];
    $reactions_option = $mybb->settings['inplayquotes_reactions_option'];
    $playername_setting = $mybb->settings['inplayquotes_playername'];

    $all_reactions_options = $db->num_rows($db->query("SELECT rsid FROM ".TABLE_PREFIX."inplayquotes_reactions_settings"));

    // DAS HTML UND CO ANGEZEIGT WIRD
    require_once MYBB_ROOT."inc/class_parser.php";
    $parser = new postParser;
    $code_html = array(
        "allow_html" => 1,
        "allow_mycode" => 1,
        "allow_smilies" => 1,
        "allow_imgcode" => 1,
        "filter_badwords" => 0,
        "nl2br" => 1,
        "allow_videocode" => 0
    );
    
    // SPRACHDATEI
	$lang->load('inplayquotes');
    
    // USER-ID
    $active_uid = $mybb->user['uid'];
    // Accountswitcher
    $active_allcharas = inplayquotes_get_allchars($active_uid);
    $active_charastring = implode(",", array_keys($active_allcharas));
    $count_allcharas = count($active_allcharas);

    $index_quote = $db->query("SELECT * FROM ".TABLE_PREFIX."inplayquotes	
	ORDER BY rand()
	LIMIT 1
    ");

    eval("\$inplayquote_bit = \"".$templates->get("inplayquotes_index_bit_none")."\";");
    while($quote = $db->fetch_array($index_quote)) {

        // Leer laufen lassen
        $qid = "";
        $uid = "";
        $charactername = "";
        $charactername_formated = "";
        $charactername_formated_link = "";
        $charactername_link = "";
        $charactername_fullname = "";
        $charactername_first = "";
        $charactername_last = "";
        $tid = "";
        $pid = "";
        $date = "";
        $inplayquote = "";
        $graphic_link = "";
        $scene_link = "";
        $postdate = "";

        // Mit Infos füllen
        $qid = $quote['qid'];
        $uid = $quote['uid'];
        $tid = $quote['tid'];
        $pid = $quote['pid'];
        $date = $quote['date'];
        $inplayquote = $parser->parse_message($quote['quote'], $code_html);

        // User vorhanden oder nicht
        $user = get_user($uid);
        // Vorhanden -> daten aus der Users Tabelle
        if (!empty($user)) {
            // CHARACTER NAME
            // ohne alles
            $charactername = $user['username'];
            // mit Gruppenfarbe
            $charactername_formated = format_name($charactername, $user['usergroup'], $user['displaygroup']);	
            $charactername_formated_link = build_profile_link(format_name($charactername, $user['usergroup'], $user['displaygroup']), $uid);	
            // Nur Link
            $charactername_link = build_profile_link($charactername, $uid);
            // Name gesplittet
            $charactername_fullname = explode(" ", $charactername);
            $charactername_first = array_shift($charactername_fullname);
            $charactername_last = implode(" ", $charactername_fullname);
            
            // CHARACTER GRAFIK
            // Gäste
            if ($active_uid == 0 AND $graphic_guest == 1) {
                $graphic_link = $theme['imgdir']."/".$graphic_defaultgraphic;
            } else {
                // Avatar
                if ($graphic_type == 0) {
                    $chara_graphic = $user['avatar'];
                } 
                // Uploadsystem
                else if ($graphic_type == 1) {
                    $path = $db->fetch_field($db->simple_select("uploadsystem", "path", "identification = '".$graphic_uploadsystem."'"), "path");                  
                    $value = $db->fetch_field($db->simple_select("uploadfiles", $graphic_uploadsystem, "ufid = '".$uid."'"), $graphic_uploadsystem);

                    if ($value != "") {
                        $chara_graphic = $path."/".$value;
                    } else {
                        $chara_graphic = "";
                    }
                }
                // Profilfelder
                else if ($graphic_type == 2) {
                    $fid = "fid".$graphic_profilefield;
                    $chara_graphic = $db->fetch_field($db->simple_select("userfields", $fid, "ufid = '".$uid."'"), $fid);
                }
                // Steckifelder
                else if ($graphic_type == 3) {	
                    $fieldid = $db->fetch_field($db->simple_select("application_ucp_fields", "id", "fieldname = '".$graphic_characterfield."'"), "id");                  
                    $chara_graphic = $db->fetch_field($db->simple_select("application_ucp_userfields", "value", "uid = '".$uid."' AND fieldid = '".$fieldid."'"), "value");
                }

                // wenn man kein Grafik hat => Default
                if ($chara_graphic == "") {
                    // Dateinamen bauen
                    $graphic_link = $theme['imgdir']."/".$graphic_defaultgraphic;
                } else {
                    // Dateinamen bauen
                    $graphic_link = $chara_graphic;
                }
            }

        } 
        // Nicht vorhanden -> "Gast" = gespeicherter Name
        else {
            // CHARACTER NAME
            // ohne alles
            $charactername = $charactername_formated = $charactername_formated_link = $charactername_link = $quote['username'];
            // Name gesplittet
            $charactername_fullname = explode(" ", $charactername);
            $charactername_first = array_shift($charactername_fullname);
            $charactername_last = implode(" ", $charactername_fullname); 

            // CHARACTER GRAFIK -> immer Default
            $graphic_link = $theme['imgdir']."/".$graphic_defaultgraphic;
        }

        // Szenenlink
        $thread = get_thread($tid);
        $scene_link = "<a href=\"showthread.php?tid=".$tid."&amp;pid=".$pid."#pid".$pid."\">".$thread['subject']."</a>";

        // Postdatum
        $postdate = my_date('relative', $date);

        // Profilfelder
        $characterfield = inplayquotes_build_characterfield($uid);
        // Szeneninfos
        $scenefield = inplayquotes_build_scenefield($tid);

        // Reaktionen
        $pos = strpos(",".$active_charastring.",", ",".$uid.",");
        if ($reactions_setting == 1) {

            // Leer laufen lassen
            $quote_preview = "";
            $prev_quote = "";

            // Mit Infos füllen
            if(my_strlen($inplayquote) > 200) {
                $prev_quote = my_substr($inplayquote, 0, 200)."...";
            } else {
                $prev_quote = $inplayquote;
            }
            $quote_preview = $lang->sprintf($lang->inplayquotes_reactions_quote_preview, $charactername, $prev_quote);

            // Pro Charakter
            if ($reactions_option == 1) {
                // Bishierige Reaktionen auf das Zitat
                $query_reactionsUser = $db->query("SELECT reaction FROM ".TABLE_PREFIX."inplayquotes_reactions
                WHERE qid = '".$qid."'
                AND uid = '".$active_uid."'                       
                ");    
            } 
            // pro Spieler
            else {
                // Bishierige Reaktionen auf das Zitat
                $query_reactionsUser = $db->query("SELECT reaction FROM ".TABLE_PREFIX."inplayquotes_reactions
                WHERE qid = '".$qid."'
                AND uid IN (".$active_charastring.")                          
                ");   
            } 

            $reactionsUser_qids = "";
            while ($reactionsUser = $db->fetch_array($query_reactionsUser)){
                $reactionsUser_qids .= $reactionsUser['reaction'].",";
            } 
            if(!empty($reactionsUser_qids)) {
                // letztes Komma abschneiden 
                $reactionsUser_string = substr($reactionsUser_qids, 0, -1);
                $reaction_sql = "WHERE rsid NOT IN(".$reactionsUser_string.")";

                // Pro Charakter
                if ($reactions_option == 1) {
                    $allreacted_query = $db->query("SELECT r.reaction, rs.image, MAX(rid) AS max_rid FROM ".TABLE_PREFIX."inplayquotes_reactions r
                    LEFT JOIN ".TABLE_PREFIX."inplayquotes_reactions_settings rs ON rs.rsid = r.reaction
                    WHERE uid = '".$active_uid."' 
                    AND qid = '".$qid."' 
                    GROUP BY r.reaction
                    ");
                }
                // pro Spieler
                else {
                    $allreacted_query = $db->query("SELECT r.reaction, rs.image  FROM ".TABLE_PREFIX."inplayquotes_reactions r
                    LEFT JOIN ".TABLE_PREFIX."inplayquotes_reactions_settings rs ON rs.rsid = r.reaction
                    WHERE uid IN (".$active_charastring.") 
                    AND qid = '".$qid."' 
                    GROUP BY r.reaction
                    ");
                }

                $reacted_images = "";
                $reacted_reactions = "";
                while($allreacted = $db->fetch_array($allreacted_query)) {
        
                    // Leer laufen lassen
                    $reaction = "";          
                    $image = "";

                    // Mit Infos füllen
                    $reaction = $allreacted['reaction'];         
                    $image = $allreacted['image'];

                    eval("\$reacted_images .= \"".$templates->get("inplayquotes_reactions_reacted_image")."\";");
                }

                eval("\$reacted_reactions = \"".$templates->get("inplayquotes_reactions_reacted")."\";");
            } else {
                $reaction_sql = "";
                $reacted_reactions = "";
            }

            // Bilder
            $allreactions_query = $db->query("SELECT * FROM ".TABLE_PREFIX."inplayquotes_reactions_settings
            ".$reaction_sql);

            $reactions_images = "";
            while($allreaction = $db->fetch_array($allreactions_query)) {
                // Leer laufen lassen
                $rsid = "";
                $name = "";
                $image = "";

                // Mit Infos füllen
                $rsid = $allreaction['rsid'];
                $name = $allreaction['name'];
                $image = $allreaction['image'];

                eval("\$reactions_images .= \"".$templates->get("inplayquotes_reactions_add_popup_image")."\";");
            }

            eval("\$reactions_popup = \"".$templates->get("inplayquotes_reactions_add_popup")."\";");

            $check = $db->num_rows($db->query("SELECT * FROM ".TABLE_PREFIX."inplayquotes_reactions
            WHERE qid = '".$qid."'"));
            // Hat schon Reaktionen
            if ($check > 0) {

                $reactions_query = $db->query("SELECT rs.*, GROUP_CONCAT(r.rid) AS rid_list FROM ".TABLE_PREFIX."inplayquotes_reactions r 
                LEFT JOIN ".TABLE_PREFIX."inplayquotes_reactions_settings rs ON rs.rsid = r.reaction 
                WHERE qid = '".$qid."' 
                GROUP BY rs.rsid");
                $stored_reactions = "";
                while($reaction = $db->fetch_array($reactions_query)) {
                    // Leer laufen lassen
                    $rsid = "";
                    $name = "";
                    $image = "";
                    $rid = "";
                    $count = "";
                    $title = "";

                    // Mit Infos füllen
                    $rsid = $reaction['rsid'];
                    $name = $reaction['name'];
                    $image = $reaction['image'];

                    $query_quote = $db->query("SELECT reaction,uid,username FROM ".TABLE_PREFIX."inplayquotes_reactions
                    WHERE qid = '".$qid."'  
                    AND reaction = '".$rsid."'
                    ORDER BY username
                    ");

                    $count = $db->num_rows($query_quote);

                    // Namen von den Leuten
                    // Charakternamen
                    if ($reactions_option == 1) {
                        $all_usernames = "";
                        while ($alluser = $db->fetch_array($query_quote)){
                            $user = get_user($alluser['uid']);
                            // Vorhanden -> daten aus der Users Tabelle
                            if (!empty($user)) {
                                $all_usernames .= $user['username'].", ";
                            } else {
                                $all_usernames .= $alluser['username'].", ";
                            }
                        } 
                        // letztes Komma abschneiden 
                        $title = substr($all_usernames, 0, -2);
                    }
                    // Spielername
                    else {
                        $all_playername = "";
                        while ($alluser = $db->fetch_array($query_quote)){
                            $user = get_user($alluser['uid']);
                            // Vorhanden -> daten aus der Users Tabelle
                            if (!empty($user)) {
                                // wenn Zahl => klassisches Profilfeld
                                if (is_numeric($playername_setting)) {
                                    $playername = $db->fetch_field($db->simple_select("userfields", "fid".$playername_setting, "ufid = '".$alluser['uid']."'"), "fid".$playername_setting);
                                } else {
                                    $playerid = $db->fetch_field($db->simple_select("application_ucp_fields", "id", "fieldname = '".$playername_setting."'"), "id");
                                    $playername = $db->fetch_field($db->simple_select("application_ucp_userfields", "value", "fieldid = '".$playerid."' AND uid = '".$alluser['uid']."'"), "value");
                                }
                            } else {
                                $playername = $alluser['username'].$lang->inplayquotes_filter_select_character_formerly;
                            }
                            $all_playername .= $playername.", ";
                        } 
                        // letztes Komma abschneiden 
                        $title = substr($all_playername, 0, -2);
                    }

                    eval("\$stored_reactions .= \"".$templates->get("inplayquotes_reactions_stored")."\";");
                }

                // Pro Charakter
                if ($reactions_option == 1) {
                    $account_reactions = $db->num_rows($db->query("SELECT reaction FROM ".TABLE_PREFIX."inplayquotes_reactions
                    WHERE qid = '".$qid."'  
                    AND uid = '".$active_uid."'                
                    "));

                    if ($all_reactions_options != $account_reactions) {
                        $check_add = 1;
                    } else {
                        $check_add = 0;
                    }   
                } 
                // pro Spieler
                else {
                    $player_reactions = $db->num_rows($db->query("SELECT reaction FROM ".TABLE_PREFIX."inplayquotes_reactions
                    WHERE qid = '".$qid."'  
                    AND uid IN (".$active_charastring.")                
                    "));

                    $account_reactions = $db->num_rows($db->query("SELECT reaction FROM ".TABLE_PREFIX."inplayquotes_reactions
                    WHERE qid = '".$qid."'  
                    AND uid = '".$active_uid."'                
                    "));

                    $all_reactions_math = $all_reactions_options*$count_allcharas;

                    if ($all_reactions_math != $player_reactions AND $all_reactions_options != $account_reactions) {
                        $check_add = 1;
                    } else {
                        $check_add = 0;
                    }    
                } 

                if ($pos === false AND $check_add == 1 AND $active_uid != 0) {
                    eval("\$reactions_add = \"".$templates->get("inplayquotes_reactions_add")."\";");
                } else {
                    $reactions_add = "";
                }

                if ($mybb->usergroup['canmodcp'] == '1') {
                    $delete_reactions = "<span class=\"inplayquotes_overview_bit_reactions_delete\"><a href=\"misc.php?action=inplayquotes&amp;allreactions_delete=".$qid."\">".$lang->inplayquotes_allreactions_delete."</a></span>";
                } else {
                    $delete_reactions = "";
                }

                eval("\$reactions = \"".$templates->get("inplayquotes_reactions")."\";");
            } else {
                $reacted_reactions = "";

                if ($pos === false AND $active_uid != 0) {
                    eval("\$reactions = \"".$templates->get("inplayquotes_reactions_add")."\";");
                } else {
                    $reactions = "";
                }
            }
        } else {
            $reactions = "";
        }

        // Lösch Link
        if(($mybb->usergroup['canmodcp'] == '1' OR $pos !== false) AND $active_uid != 0){
            $del_quote = "<a href=\"misc.php?action=inplayquotes&quote_delete=".$qid."\" onClick=\"return confirm('Möchtest du dieses Zitat wirklich löschen?');\">".$lang->inplayquotes_quote_delete."</a>";
        } else {
            $del_quote = "";
        }

        eval("\$inplayquote_bit = \"".$templates->get("inplayquotes_index_bit")."\";");
    }

    eval("\$forum['inplayquotes_index'] = \"".$templates->get("inplayquotes_index")."\";");
}

// DATENBANKTABELLEN
function inplayquotes_database() {

    global $db;
    
    // DATENBANKEN ERSTELLEN
    // Inplayzitate
    if (!$db->table_exists("inplayquotes")) {
        $db->query("CREATE TABLE ".TABLE_PREFIX."inplayquotes(
            `qid` int(11) unsigned NOT NULL AUTO_INCREMENT,
            `uid` int(11) unsigned NOT NULL,
            `username` VARCHAR(120) COLLATE utf8_general_ci NOT NULL,
            `tid` int(11) unsigned NOT NULL,
            `pid` int(11) unsigned NOT NULL,
            `date` int(11) unsigned NOT NULL,
            `quote` VARCHAR(5000) COLLATE utf8_general_ci NOT NULL,
            PRIMARY KEY(`qid`),
            KEY `qid` (`qid`)
            )
            ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci AUTO_INCREMENT=1    
        ");
    }
    // vergebenen Reaktionen
    if (!$db->table_exists("inplayquotes_reactions")) {
        $db->query("CREATE TABLE ".TABLE_PREFIX."inplayquotes_reactions(
            `rid` int(11) unsigned NOT NULL AUTO_INCREMENT,
            `reaction` int(11) unsigned NOT NULL,
            `qid` int(11) unsigned NOT NULL,
            `uid` int(11) unsigned NOT NULL,
            `username` VARCHAR(120) COLLATE utf8_general_ci NOT NULL,
            PRIMARY KEY(`rid`),
            KEY `rid` (`rid`)
            )
            ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci AUTO_INCREMENT=1    
        ");
    }
    // Reaktionen Einstellungen
    if (!$db->table_exists("inplayquotes_reactions_settings")) {
        $db->query("CREATE TABLE ".TABLE_PREFIX."inplayquotes_reactions_settings(
            `rsid` int(10) unsigned NOT NULL AUTO_INCREMENT,
            `name` varchar(255) COLLATE utf8_general_ci NOT NULL,
            `image` varchar(255) COLLATE utf8_general_ci NOT NULL,
            PRIMARY KEY(`rsid`),
            KEY `rsid` (`rsid`)
            )
            ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci AUTO_INCREMENT=1    
        ");
    }
}

// EINSTELLUNGEN
function inplayquotes_settings($type = 'install') {

    global $db; 
			
	$setting_array = array(
        'inplayquotes_allowgroups' => array(
            'title' => 'Erlaubte Gruppen',
			'description' => 'Welche Gruppen dürfen Zitate hinzufügen?',
			'optionscode' => 'groupselect',
			'value' => '4', // Default
			'disporder' => 1
        ),
		'inplayquotes_quotesarea' => array(
			'title' => 'Zitate Bereich',
            'description' => 'Aus welchem Bereich vom Forum können User Zitate einreichen? Es reicht aus, die übergeordneten Kategorien zu markieren.',
            'optionscode' => 'forumselect',
            'value' => 'none', // Default
            'disporder' => 2
		),
		'inplayquotes_excludedarea' => array(
			'title' => 'ausgeschlossene Foren',
            'description' => 'Gibt es Foren, die innerhalb der ausgewählten Kategorie liegen aber nicht zum Zitate Bereich gezählt werden sollen (z.B. Communication).',
            'optionscode' => 'forumselect',
            'value' => 'none', // Default
            'disporder' => 3
		),
		'inplayquotes_user_alert' => array(
			'title' => 'Benachrichtigungssystem',
			'description' => 'Wie sollen User darüber in Kenntnis gesetzt werden, dass sie aus dem Inplay zitiert wurden?',
			'optionscode' => 'select\n0=MyAlerts\n1=Private Nachricht',
			'value' => 0, // Default
			'disporder' => 4
		),
		'inplayquotes_overview_guest' => array(
			'title' => 'Gästeberechtigung',
            'description' => 'Dürfen Gäste die Übersicht aller Inplayzitate sehen?',
            'optionscode' => 'yesno',
            'value' => '1', // Default
            'disporder' => 5
		),
		'inplayquotes_overview_multipage' => array(
			'title' => 'Zitate pro Seite',
            'description' => 'Wie viele Inplayzitate sollen pro Seite der Übersicht angezeigt werden (0 = Keine Beschränkung)?',
            'optionscode' => 'numeric',
            'value' => '0', // Default
            'disporder' => 6
		),
		'inplayquotes_overview_filter' => array(
			'title' => 'Filteroptionen',
            'description' => 'Nach welchen Optionen können die Zitate auf der Übersichtsseite gefiltert werden?',
            'optionscode' => 'checkbox\nplayer=nach Spieler\ncharacter=nach Charakter\ntimestamp=nach Postdatum',
            'value' => '', // Default
            'disporder' => 7
		),
		'inplayquotes_overview_graphic' => array(
			'title' => 'Grafiktyp',
            'description' => 'Welche Grafik soll vom zitierten Charakter angezeigt werden? Zur Auswahl steht klassisch der Avatar, ein Element aus dem Uploadsystem von little.evil.genius, ein klassisches Profilfeld oder ein Feld aus dem Steckbriefplugin von risuena.',
            'optionscode' => 'select\n0=Avatar\n1=Upload-Element\n2=Profilfeld\n3=Steckbrieffeld',
            'value' => '0', // Default
            'disporder' => 8
		),
		'inplayquotes_overview_graphic_uploadsystem' => array(
			'title' => 'Identifikator von dem Upload-Element',
            'description' => 'Wie lautet der Identifikator von dem Upload-Element, welches vom zitierten Charakter als Grafik genutzt werden soll',
            'optionscode' => 'text',
            'value' => 'index', // Default
            'disporder' => 9
		),
		'inplayquotes_overview_graphic_profilefield' => array(
			'title' => 'FID von dem Profilfeld',
            'description' => 'Wie lautet die FID von dem Profilfeld, welches vom zitierten Charakter als Grafik genutzt werden soll?',
            'optionscode' => 'numeric',
            'value' => '6', // Default
            'disporder' => 10
		),
		'inplayquotes_overview_graphic_characterfield' => array(
			'title' => 'Identifikator von dem Steckbrieffeld',
            'description' => 'Wie lautet der Identifikator von dem Steckbrieffeld, welches vom zitierten Charakter als Grafik genutzt werden soll?',
            'optionscode' => 'text',
            'value' => 'index_pic', // Default
            'disporder' => 11
		),
		'inplayquotes_overview_graphic_defaultgraphic' => array(
			'title' => 'Standard-Grafik',
            'description' => 'Wie heißt die Bilddatei für die Standard-Grafik? Diese Grafik wird, falls ein Charakter noch keine entsprechende Grafik besitzt, oder es sich um einen gelöschten Charakter handelt, stattdessen angezeigt. Damit die Grafik für jedes Design angepasst wird, sollte der Dateiname in allen Ordner für die Designs gleich heißen.',
            'optionscode' => 'text',
            'value' => 'default_avatar.png', // Default
            'disporder' => 12
		),
        'inplayquotes_overview_graphic_guest' => array(
            'title' => 'Gäste-Ansicht',
            'description' => 'Sollen die Grafiken von den zitierten Charakteren vor Gästen versteckt werden? Statt der Grafik wird die festgelegte Standard-Grafik angezeigt.',
            'optionscode' => 'yesno',
            'value' => '1', // Default
            'disporder' => 13
        ),
        'inplayquotes_profilfeldsystem' => array(
            'title' => 'Profilfeldsystem',
            'description' => 'Um zusätzliche Informationen über dem zitierten Charakter/Spieler auszugeben, muss angegeben werden, mit welchem Profilfeldsystem gearbeitet wird. Es kann auch ausgewählt werden, dass beide Varianten verwendet werden.',
            'optionscode' => 'select\n0=klassische Profilfelder\n1=Steckbrief-Plugin\n2=beide Varianten',
            'value' => '0', // Default
            'disporder' => 14
        ),
		'inplayquotes_reactions' => array(
			'title' => 'Overview: Reaktionen auf Zitate',
            'description' => 'Dürfen User auf eingereichten Zitate reagieren?',
            'optionscode' => 'yesno',
            'value' => '1', // Default
            'disporder' => 15
		),
		'inplayquotes_reactions_option' => array(
			'title' => 'Vergabe der Reaktionen',
            'description' => 'Darf jeder Spieler nur einmal insgesamt (egal welcher Charakter) auf ein Zitat reagieren oder mit jedem Charakter jeweils einmal?<br><b>Hinweis:</b> Die einmalige Vergabe bezieht sich auf jede Reaktionsmöglichkeit einzeln.',
            'optionscode' => 'select\n0=pro Spieler\n1=pro Charakter',
            'value' => '1', // Default
            'disporder' => 16
		),
		'inplayquotes_playername' => array(
			'title' => 'Spielername',
			'description' => 'Wie lautet die FID / der Identifikator von dem Profilfeld/Steckbrieffeld für den Spielernamen?<br>
            <b>Hinweis:</b> Bei klassischen Profilfeldern muss eine Zahl eingetragen werden. Bei dem Steckbrief-Plugin von Risuena muss der Name/Identifikator des Felds eingetragen werden.',
			'optionscode' => 'text',
			'value' => '4', // Default
			'disporder' => 17
		),
		'inplayquotes_inplaytracker' => array(
			'title' => 'Szeneninformation ausgeben',
            'description' => 'Um zusätzliche Informationen über die Szene ausgeben zu können, aus der das Zitat stammt, muss angegeben werden welcher Inplaytracker benutzt wird.',
            'optionscode' => 'select\n0=keine Informationen\n1=Inplaytracker 2.0 von sparks fly\n2=Inplaytracker 3.0 von sparks fly\n3=Szenentracker von risuena\n4=Inplaytracker von little.evil.genius\n5=Inplaytracker 1.0 von Ales\n6=Inplaytracker 2.0 von Ales',
            'value' => '0', // Default
            'disporder' => 18
		),
        'inplayquotes_lists' => array(
            'title' => 'Listen PHP',
            'description' => 'Wie heißt die Hauptseite der Listen-Seite? Dies dient zur Ergänzung der Navigation. Falls nicht gewünscht einfach leer lassen.',
            'optionscode' => 'text',
            'value' => 'lists.php', // Default
            'disporder' => 19
        ),
		'inplayquotes_lists_type' => array(
			'title' => 'Listen Menü',
			'description' => 'Soll über die Variable {$lists_menu} das Menü der Listen aufgerufen werden?<br>Wenn ja, muss noch angegeben werden, ob eine eigene PHP-Datei oder das Automatische Listen-Plugin von sparks fly genutzt?',
			'optionscode' => 'select\n0=eigene Listen/PHP-Datei\n1=Automatische Listen-Plugin\n2=keine Menü-Anzeige',
			'value' => '2', // Default
			'disporder' => 20
		),
        'inplayquotes_lists_menu' => array(
            'title' => 'Listen Menü Template',
            'description' => 'Damit das Listen Menü richtig angezeigt werden kann, muss hier einmal der Name von dem Tpl von dem Listen-Menü angegeben werden.',
            'optionscode' => 'text',
            'value' => 'lists_nav', // Default
            'disporder' => 21
        ),
		'inplayquotes_profile' => array(
			'title' => 'zufälliges Zitat im Profil',
            'description' => 'Soll im Profil ein zufälliges Zitat des Charakters angezeigt werden? Sollte kein passendes Zitat vorhanden sein, wird ein Default Text angezeigt.',
            'optionscode' => 'yesno',
            'value' => '0', // Default
            'disporder' => 22
		),
		'inplayquotes_deletion' => array(
			'title' => 'Zitat löschen',
            'description' => 'Sollen Inplayzitate von gelöschten Usern gelöscht werden?',
            'optionscode' => 'yesno',
            'value' => '0', // Default
            'disporder' => 23
		),
		'inplayquotes_indexarea' => array(
			'title' => 'Anzeige auf dem Index',
            'description' => 'Soll ein zufälliges Inplayzitat über einem bestimmten Forum dargestellt werden? Die Index-Variable kann dennoch benutzt werden.',
            'optionscode' => 'forumselectsingle',
            'value' => '', // Default
            'disporder' => 24
		),
	);

    $gid = $db->fetch_field($db->write_query("SELECT gid FROM ".TABLE_PREFIX."settinggroups WHERE name = 'inplayquotes' LIMIT 1;"), "gid");

    if ($type == 'install') {
        foreach ($setting_array as $name => $setting) {
          $setting['name'] = $name;
          $setting['gid'] = $gid;
          $db->insert_query('settings', $setting);
        }  
    }

    if ($type == 'update') {

        // Einzeln durchgehen 
        foreach ($setting_array as $name => $setting) {
            $setting['name'] = $name;
            $check = $db->write_query("SELECT name FROM ".TABLE_PREFIX."settings WHERE name = '".$name."'"); // Überprüfen, ob sie vorhanden ist
            $check = $db->num_rows($check);
            $setting['gid'] = $gid;
            if ($check == 0) { // nicht vorhanden, hinzufügen
              $db->insert_query('settings', $setting);
            } else { // vorhanden, auf Änderungen überprüfen
                
                $current_setting = $db->fetch_array($db->write_query("
                    SELECT title, description, optionscode, disporder 
                    FROM ".TABLE_PREFIX."settings 
                    WHERE name = '".$db->escape_string($name)."'
                "));
            
                $update_needed = false;
                $update_data = array();
            
                if ($current_setting['title'] != $setting['title']) {
                    $update_data['title'] = $setting['title'];
                    $update_needed = true;
                }
                if ($current_setting['description'] != $setting['description']) {
                    $update_data['description'] = $setting['description'];
                    $update_needed = true;
                }
                if ($current_setting['optionscode'] != $setting['optionscode']) {
                    $update_data['optionscode'] = $setting['optionscode'];
                    $update_needed = true;
                }
                if ($current_setting['disporder'] != $setting['disporder']) {
                    $update_data['disporder'] = $setting['disporder'];
                    $update_needed = true;
                }
            
                if ($update_needed) {
                    $db->update_query('settings', $update_data, "name = '".$db->escape_string($name)."'");
                }
            }            
        }  
    }

    rebuild_settings();
}

// TEMPLATES
function inplayquotes_templates($mode = '') {

    global $db;

    $templates[] = array(
        'title'		=> 'inplayquotes_index',
        'template'	=> $db->escape_string('<div class="inplayquotes_index">
        <div class="inplayquotes_index-headline">{$lang->inplayquotes_index_headline}</div>
        {$inplayquote_bit}
        <div class="inplayquotes_index-allquotes">
            <span class="smalltext"><a href="misc.php?action=inplayquotes">{$lang->inplayquotes_index_link}</a></span>
        </div>
        </div>'),
        'sid'		=> '-2',
        'dateline'	=> TIME_NOW
    );

    $templates[] = array(
        'title'		=> 'inplayquotes_index_bit',
        'template'	=> $db->escape_string('<div class="inplayquotes_index_bit">
        <div class="inplayquotes_index_bit_avatar">
            <img src="{$graphic_link}">
            {$del_quote}
        </div>
        <div class="inplayquotes_index_bit_container">
            <div class="inplayquotes_index_bit_quote">
                {$inplayquote}
            </div>
            <div class="inplayquotes_index_bit_footer">
                <div class="inplayquotes_index_bit_reaction">{$reactions}</div>
                <div class="inplayquotes_index_bit_user">
                    <b>{$charactername_link}</b><br>
                    <span>{$scene_link}</span><br>
                    <span>{$postdate}</span>
                </div>
            </div>
        </div>
        </div>'),
        'sid'		=> '-2',
        'dateline'	=> TIME_NOW
    );

    $templates[] = array(
        'title'		=> 'inplayquotes_index_bit_none',
        'template'	=> $db->escape_string('<div class="inplayquotes_index_bit">
        {$lang->inplayquotes_index_bit_none}
    </div>'),
        'sid'		=> '-2',
        'dateline'	=> TIME_NOW
    );

    $templates[] = array(
        'title'		=> 'inplayquotes_memberprofile',
        'template'	=> $db->escape_string('<div class="inplayquotes_memberprofile">
        <div class="inplayquotes_memberprofile-headline">{$lang->inplayquotes_memberprofile_headline}</div>
        {$inplayquote_bit}
        <div class="inplayquotes_memberprofile-allquotes">
            <span class="smalltext">{$all_inplayquotes}</span>
        </div>
        </div>'),
        'sid'		=> '-2',
        'dateline'	=> TIME_NOW
    );

    $templates[] = array(
        'title'		=> 'inplayquotes_memberprofile_bit',
        'template'	=> $db->escape_string('<div class="inplayquotes_memberprofile_bit">
        <div class="inplayquotes_memberprofile_bit_container">
            <div class="inplayquotes_memberprofile_bit_quote">
                {$inplayquote}
            </div>
            <div class="inplayquotes_memberprofile_bit_footer">
                <span>{$scene_link}</span><br>
                <span>{$postdate}</span>
            </div>
        </div>
     </div>'),
        'sid'		=> '-2',
        'dateline'	=> TIME_NOW
    );

    $templates[] = array(
        'title'		=> 'inplayquotes_memberprofile_bit_none',
        'template'	=> $db->escape_string('<div class="inplayquotes_memberprofile_bit">
        <div class="inplayquotes_memberprofile_bit_container">
            {$lang->inplayquotes_memberprofile_bit_none}
        </div>
        </div>'),
        'sid'		=> '-2',
        'dateline'	=> TIME_NOW
    );

    $templates[] = array(
        'title'		=> 'inplayquotes_overview',
        'template'	=> $db->escape_string('<html>
        <head>
            <title>{$mybb->settings[\'bbname\']} - {$lang->inplayquotes_overview}</title>
            {$headerinclude}</head>
        <body>
            {$header}
            <table width="100%" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}">
                <tr>
                    <td valign="top">
                        <div id="inplayquotes_overview">
                            <div class="inplayquotes-headline">{$lang->inplayquotes_overview}</div>
                            {$filter_option}
                            <div class="inplayquotes-body">
                                {$inplayquotes_bit}
                                {$multipage}
                            </div>
                        </div>
                    </td>
                </tr>
            </table>
            {$footer}
        </body>
        </html>'),
        'sid'		=> '-2',
        'dateline'	=> TIME_NOW
    );

    $templates[] = array(
        'title'		=> 'inplayquotes_overview_bit',
        'template'	=> $db->escape_string('<div class="inplayquotes_overview_bit">
        <div class="inplayquotes_overview_bit_avatar">
            <img src="{$graphic_link}">
            {$del_quote}
        </div>
        <div class="inplayquotes_overview_bit_container">
            <div class="inplayquotes_overview_bit_quote">
                {$inplayquote}
            </div>
            <div class="inplayquotes_overview_bit_footer">
                <div class="inplayquotes_overview_bit_reaction">{$reactions}</div>
                <div class="inplayquotes_overview_bit_user">
                    <b>{$charactername_link}</b><br>
                    <span>{$scene_link}</span><br>
                    <span>{$postdate}</span>
                </div>
            </div>
        </div>
    </div>'),
        'sid'		=> '-2',
        'dateline'	=> TIME_NOW
    );

    $templates[] = array(
        'title'		=> 'inplayquotes_overview_filter',
        'template'	=> $db->escape_string('<form id="inplayquotes_filter" method="get" action="misc.php">
        <input type="hidden" name="action" value="inplayquotes" />
        <div class="inplayquotes-filter">
            <div class="inplayquotes-filter-headline">{$lang->inplayquotes_filter}</div>
            <div class="inplayquotes-filteroptions">
                {$filter_bit}
            </div>
            <center>
                <input type="submit" name="inplayquotes_search_filter" value="{$lang->inplayquotes_filter_button}" id="inplayquotes_search_filter" class="button">
            </center>
        </div>
        </form>'),
        'sid'		=> '-2',
        'dateline'	=> TIME_NOW
    );

    $templates[] = array(
        'title'		=> 'inplayquotes_overview_filter_bit',
        'template'	=> $db->escape_string('<div class="inplayquotes_overview_filter_bit">
        <div class="inplayquotes-filter-bit-headline">{$filter_headline}</div>
        <div class="inplayquotes-filter-bit-dropbox">
            {$filter_select}
        </div>
    </div>'),
        'sid'		=> '-2',
        'dateline'	=> TIME_NOW
    );

    $templates[] = array(
        'title'		=> 'inplayquotes_postbit',
        'template'	=> $db->escape_string('<a href="" onclick="$(\'#inplayquotes_{$pid}\').modal({ fadeDuration: 250, keepelement: true, zIndex: (typeof modal_zindex !== \'undefined\' ? modal_zindex : 9999) }); return false;" class="postbit_quote postbit_mirage"><span>{$lang->inplayquotes_postbit}</span></a>
        <script>
            // Funktion zum Speichern des markierten Textes und Einfügen in die Textarea
            function saveSelectedText(event) {
                if (window.getSelection().toString().length) {
                    var postId = event.target.id.replace(\'pid_\', \'\');
                    document.getElementById("quotebox" + postId).value = window.getSelection().toString();
                }
            }
        
            // Eventlistener für das Speichern des markierten Textes auf jedem Beitrag/Post im Thema
            var posts = document.querySelectorAll(\'.post_body\');
            posts.forEach(function(post) {
                post.addEventListener(\'mouseup\', function(event) {
                    saveSelectedText(event);
                });
            });
        </script>
        {$inplayquotes_popup}'),
        'sid'		=> '-2',
        'dateline'	=> TIME_NOW
    );

    $templates[] = array(
        'title'		=> 'inplayquotes_postbit_popup',
        'template'	=> $db->escape_string('<div id="inplayquotes_{$pid}" class="modal" style="display: none;">
        <div class="inplayquotes_popup">
            <div class="inplayquotes_popup-headline">{$lang->inplayquotes_postbit_popup}</div>
            <div class="inplayquotes_popup-quoteInfo">
                {$quote_infos}
            </div>
            <form id="new_inplayquotes" method="post" action="misc.php?action=add_inplayquote&pid={$pid}">
                <div class="inplayquotes_popup-textarea">
                    {$lang->inplayquotes_postbit_popup_quote}
                    <textarea name="inplayquote" id="quotebox{$pid}" style="width: 300px; height: 100px;" maxlength="5000"></textarea>
                </div>
                <div class="inplayquotes_popup-button">
                    <input type="hidden" name="sendby" value="{$active_uid}" />
                    <input type="submit" name="inplayquotes_submit" id="inplayquotes_submit" value="{$lang->inplayquotes_postbit_popup_button}" class="button">
                </div>
            </form>
        </div>
        </div>'),
        'sid'		=> '-2',
        'dateline'	=> TIME_NOW
    );

    $templates[] = array(
        'title'		=> 'inplayquotes_reactions',
        'template'	=> $db->escape_string('<div class="inplayquotes_overview_bit_reaction">
        <div class="inplayquotes_overview_bit_reaction_bit">
            {$stored_reactions}
            {$reactions_add}
        </div>
        {$reacted_reactions}
        {$delete_reactions}
        </div>'),
        'sid'		=> '-2',
        'dateline'	=> TIME_NOW
    );

    $templates[] = array(
        'title'		=> 'inplayquotes_reactions_add',
        'template'	=> $db->escape_string('<div class="inplayquotes_overview_bit_reaction">
        <a href="" onclick="$(\'#inplayquotesReactions_{$qid}\').modal({ fadeDuration: 250, keepelement: true, zIndex: (typeof modal_zindex !== \'undefined\' ? modal_zindex : 9999) }); return false;" class="postbit_quote postbit_mirage"><span>{$lang->inplayquotes_reactions_add}</span></a>
        </div>
        {$reactions_popup}'),
        'sid'		=> '-2',
        'dateline'	=> TIME_NOW
    );

    $templates[] = array(
        'title'		=> 'inplayquotes_reactions_add_popup',
        'template'	=> $db->escape_string('<div id="inplayquotesReactions_{$qid}" class="modal" style="display: none;">
        <div class="inplayquotes_popup">
            <div class="inplayquotes_popup-headline">{$lang->inplayquotes_reactions_add}</div>
            <div class="inplayquotes_popup-quoteInfo">
                {$quote_preview}
            </div>
            <div class="inplayquotes_popup-subline">{$lang->inplayquotes_reactions_add_desc}</div>
            <form id="new_inplayquotesReactions" method="post" action="misc.php?action=add_inplayquote_reaction">
                <input type="hidden" name="sendby" value="{$active_uid}" />
                <input type="hidden" name="selected_reaction_id" id="selected_reaction_id" /> 
                <div class="inplayquotes_popup-reactions">
                    {$reactions_images}
                </div>
            </form>
        </div>
        </div>
    
        <script>
        function selectReaction(reactionId, qid) {
            document.getElementById(\'selected_reaction_id\').value = reactionId;
            document.getElementById(\'new_inplayquotesReactions\').setAttribute(\'action\', \'misc.php?action=add_inplayquote_reaction&qid=\' + qid);
            document.getElementById(\'new_inplayquotesReactions\').submit();
        }
        </script>'),
        'sid'		=> '-2',
        'dateline'	=> TIME_NOW
    );

    $templates[] = array(
        'title'		=> 'inplayquotes_reactions_add_popup_image',
        'template'	=> $db->escape_string('<img src="{$mybb->settings[\'bburl\']}/{$image}" alt="" style="cursor: pointer;" onclick="selectReaction({$rsid}, {$qid})">
        <script>
            function selectReaction(reactionId) {
                document.getElementById(\'selected_reaction_id\').value = reactionId;
                document.getElementById(\'new_inplayquotesReactions\').submit();
            }
        </script>'),
        'sid'		=> '-2',
        'dateline'	=> TIME_NOW
    );

    $templates[] = array(
        'title'		=> 'inplayquotes_reactions_reacted',
        'template'	=> $db->escape_string('<div class="inplayquotes_overview_bit_reaction-reacted">
        {$lang->inplayquotes_reactions_reacted}
        {$reacted_images}
        </div>'),
        'sid'		=> '-2',
        'dateline'	=> TIME_NOW
    );

    $templates[] = array(
        'title'		=> 'inplayquotes_reactions_reacted_image',
        'template'	=> $db->escape_string('<img src="{$image}"> <a href="misc.php?action=inplayquotes&reactions_delete={$reaction}&reactions_quote={$qid}">x</a>'),
        'sid'		=> '-2',
        'dateline'	=> TIME_NOW
    );

    $templates[] = array(
        'title'		=> 'inplayquotes_reactions_stored',
        'template'	=> $db->escape_string('<div class="inplayquotes_overview_bit_reaction_images">
        <img src="{$mybb->settings[\'bburl\']}/{$image}" title="{$title}" /> <span>{$count}</span>
        </div>'),
        'sid'		=> '-2',
        'dateline'	=> TIME_NOW
    );

    if ($mode == "update") {

        foreach ($templates as $template) {
            $query = $db->simple_select("templates", "tid, template", "title = '".$template['title']."' AND sid = '-2'");
            $existing_template = $db->fetch_array($query);

            if($existing_template) {
                if ($existing_template['template'] !== $template['template']) {
                    $db->update_query("templates", array(
                        'template' => $template['template'],
                        'dateline' => TIME_NOW
                    ), "tid = '".$existing_template['tid']."'");
                }
            }
            
            else {
                $db->insert_query("templates", $template);
            }
        }

    } else {
        foreach ($templates as $template) {
            $check = $db->num_rows($db->simple_select("templates", "title", "title = '".$template['title']."'"));
            if ($check == 0) {
                $db->insert_query("templates", $template);
            }
        }
    }
}

// STYLESHEET MASTER
function inplayquotes_stylesheet() {

    global $db;
    
    $css = array(
        'name' => 'inplayquotes.css',
        'tid' => 1,
        'attachedto' => '',
        "stylesheet" => '.inplayquotes_popup {
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
        }',
        'cachefile' => $db->escape_string(str_replace('/', '', 'inplayquotes.css')),
        'lastmodified' => time()
    );

    return $css;
}

// STYLESHEET UPDATE
function inplayquotes_stylesheet_update() {

    // Update-Stylesheet
    // wird an bestehende Stylesheets immer ganz am ende hinzugefügt
    $update = '';

    // Definiere den  Überprüfung-String (muss spezifisch für die Überprüfung sein)
    $update_string = '';

    return array(
        'stylesheet' => $update,
        'update_string' => $update_string
    );
}

// UPDATE CHECK
function inplayquotes_is_updated(){

    global $db;

    $expected_optionscode = "select\n0=keine Informationen\n1=Inplaytracker 2.0 von sparks fly\n2=Inplaytracker 3.0 von sparks fly\n3=Szenentracker von risuena\n4=Inplaytracker von little.evil.genius\n5=Inplaytracker 1.0 von Ales\n6=Inplaytracker 2.0 von Ales";

    $query = $db->simple_select("settings", "optionscode", "name = 'inplayquotes_inplaytracker'");
    $current_optionscode = $db->fetch_field($query, "optionscode");

    if ($current_optionscode == $expected_optionscode) {
        return true;
    }

    return false;
}
