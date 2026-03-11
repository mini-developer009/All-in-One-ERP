<?php
/**********************************************************************
    Copyright (C) NGICON ERP (Next Generation icon ERP).
	 
	 
	
    
    
     
    All Rights Reserved By www.ngicon.com
***********************************************************************/
	$path_to_root = "..";

	include_once($path_to_root . "/includes/session.inc");
	include_once($path_to_root . "/includes/ui.inc");
	include_once($path_to_root . "/includes/data_checks.inc");
	include_once($path_to_root . "/reporting/includes/class.graphic.inc");
	if (file_exists("$path_to_root/themes/".user_theme()."/dashboard.inc"))
		include_once("$path_to_root/themes/".user_theme()."/dashboard.inc"); // yse theme dashboard.inc
	else
		include_once("$path_to_root/includes/dashboard.inc"); // here are all the dashboard routines.
	$page_security = 'SA_SETUPDISPLAY'; // A very low access level. The real access level is inside the routines.
	$app = isset($_GET['sel_app']) ? $_GET['sel_app'] : (isset($_POST['sel_app']) ? $_POST['sel_app'] : "orders");
	if (get_post('id'))
	{
		dashboard($app);
		exit;
	}
	
	$js = "";
	if ($SysPrefs->use_popup_windows)
		$js .= get_js_open_window(800, 500);

	page(_($help_context = "Dashboard"), false, false, "", $js);
	dashboard($app);
	end_page();
	exit;

