<?php

if (isset($_GET['sel_app'])) {
	
	$page_security = 'SA_SETUPDISPLAY'; 
	$path_to_root = "../..";

	include_once($path_to_root . "/includes/session.inc");
	include_once($path_to_root . "/includes/ui.inc");
	// include_once($path_to_root . "/reporting/includes/class.graphic.inc");
	include_once("charts.inc");

	$js = "";
	if ($SysPrefs->use_popup_windows)
		$js .= get_js_open_window(800, 500);

	page(_($help_context = "Dashboard"), false, false, "", $js);
	dashboard($_GET['sel_app']);
	end_page();
	exit;
}
