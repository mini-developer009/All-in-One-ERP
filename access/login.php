<?php

	if (!isset($path_to_root) || isset($_GET['path_to_root']) || isset($_POST['path_to_root']))
		die(_("Restricted access"));
	include_once($path_to_root . "/includes/ui.inc");
	include_once($path_to_root . "/includes/page/header.inc");

	$js = "<script language='JavaScript' type='text/javascript'>
function defaultCompany()
{
	document.forms[0].company_login_name.options[".user_company()."].selected = true;
}
</script>";
	add_js_file('login.js');
	// Display demo user name and password within login form if allow_demo_mode option is true
	if ($SysPrefs->allow_demo_mode == true)
	{
	    $demo_text = _("Login as user: demouser and password: password");
	}
	else
	{
		$demo_text = _("Please login here");
    	if (@$SysPrefs->allow_password_reset) {
      		$demo_text = "<a href='$path_to_root/index.php?reset=1'>"._("")."</a>";
    	}
	}

	if (check_faillog())
	{
		$blocked_msg = '<span class="redfg">'._('Too many failed login attempts.<br>Please wait a while or try later.').'</span>';

	    $js .= "<script>setTimeout(function() {
	    	document.getElementsByName('SubmitUser')[0].disabled=0;
	    	document.getElementById('log_msg').innerHTML='$demo_text'}, 1000*".$SysPrefs->login_delay.");</script>";
	    $demo_text = $blocked_msg;
	}
	flush_dir(user_js_cache());
	if (!isset($def_coy))
		$def_coy = 0;
	$def_theme = "icon";

	$login_timeout = $_SESSION["wa_current_user"]->last_act;

	$title = $login_timeout ? _('Authorization timeout') : $SysPrefs->app_title." ".$version." - "._("Login");
	$encoding = isset($_SESSION['language']->encoding) ? $_SESSION['language']->encoding : "utf-8";
	$rtl = isset($_SESSION['language']->dir) ? $_SESSION['language']->dir : "ltr";
	$onload = !$login_timeout ? "onload='defaultCompany()'" : "";

//	echo "<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\" \"http://www.w3.org/TR/html4/loose.dtd\">\n";
	echo "<html dir='$rtl' >\n";
	echo "<head profile=\"http://www.w3.org/2005/10/profile\"><title>$title</title>\n";
   	echo "<meta http-equiv='Content-type' content='text/html; charset=$encoding' >\n";
	echo '<meta name="viewport" content="width=device-width,initial-scale=1">';
	echo "<link href='$path_to_root/themes/icon/access.css' rel='stylesheet' type='text/css'> \n";
 	echo "<link href='$path_to_root/themes/icon/images/favicon.png' rel='icon' type='image/x-icon'> \n";
	send_scripts();

	if(!$login_timeout)
		echo $js;

	echo "</head>\n";
	echo "<body id='loginscreen' $onload>\n";
	
	start_form(false, false, $_SESSION['timeout']['uri'], "loginform");

	if($login_timeout)
		echo "<center>"._('Authorization timeout')."</center><br>";
	
	echo "<center>";
	echo "<div class='login-container'>";

	$value = $login_timeout ? $_SESSION['wa_current_user']->loginname : ($SysPrefs->allow_demo_mode ? "demouser":"");
	echo "<img src='$path_to_root/themes/icon/images/ngiconerp.png'>";
	echo "<div class='input-block'>";
	echo "<input required class='input' id='user' name='user_name_entry_field' type='text' value=''>";
	echo "<label for='user' class='card'>Username</label>";
	echo "</div>";

	$password = $SysPrefs->allow_demo_mode ? "password":"";

	echo "<div class='input-block'>";
	echo "<input required class='input' id='pass' name='password' type='password' value=''>";
	echo "<label for='pass' class='card'>Password</label>";
	echo "</div>";

	if ($login_timeout)
		hidden('company_login_name', user_company());
	else {
		$coy =  user_company();
		if (!isset($coy))
			$coy = $def_coy;

		echo "<div class='input-block'>";
		if (!@$SysPrefs->text_company_selection) {

			echo "<select class='input' id='coy' name='company_login_name'>\n";
			for ($i = 0; $i < count($db_connections); $i++)
				echo "<option value=$i ".($i==$coy ? 'selected':'') .">" . $db_connections[$i]["name"] . "</option>";
			echo "</select>\n";
			$label_class = 'card_select';
		}
		else {
			echo "<input required class='input' id='coy' name='company_login_nickname' type='text' value='World'>";
			$label_class = 'card';
		}
		echo "<label for='coy' class=$label_class>Company</label>";
		echo "</div>";
	}; 

	echo "<input type='hidden' id=ui_mode name='ui_mode' value='".!fallback_mode()."' >\n";
	echo "<button class='login-submit' type='submit' name='SubmitUser' title='Login'"
		." onclick='set_fullmode();'".(isset($blocked_msg) ? " disabled" : '')." >&#10095;</button>";
	echo "<a href='$path_to_root/index.php?reset=1'>"._("")."</a>";

	echo "</div>";
	echo "</center>";

	foreach($_SESSION['timeout']['post'] as $p => $val) {
		// add all request variables to be resend together with login data
		if (!in_array($p, array('ui_mode', 'user_name_entry_field', 
			'password', 'SubmitUser', 'company_login_name'))) 
			if (!is_array($val))
				echo "<input type='hidden' name='$p' value='$val'>";
			else
				foreach($val as $i => $v)
					echo "<input type='hidden' name='{$p}[$i]' value='$v'>";
	}
	end_form(1);
	$Ajax->addScript(true, "document.forms[0].password.focus();");

    echo "<script language='JavaScript' type='text/javascript'>
    //<![CDATA[
            <!--
            document.forms[0].user_name_entry_field.select();
            document.forms[0].user_name_entry_field.focus();
            //-->
			localStorage.removeItem('dark');
    //]]>
    </script>";

	echo "</body></html>\n";