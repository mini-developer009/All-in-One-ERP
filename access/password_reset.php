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

	if (!isset($def_coy))
		$def_coy = 0;
	$def_theme = "icon";

	$login_timeout = $_SESSION["wa_current_user"]->last_act;

	$title = $SysPrefs->app_title." ".$version." - "._("Password reset");
	$encoding = isset($_SESSION['language']->encoding) ? $_SESSION['language']->encoding : "iso-8859-1";
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
	echo $js;
	echo "</head>\n";

	echo "<body id='loginscreen' $onload>\n";
	
	echo "<center>";
	echo "<div class='login-container'>";
	start_form(false, false, @$_SESSION['timeout']['uri'], "resetform");

	echo "<input type='hidden' id=ui_mode name='ui_mode' value='".fallback_mode()."' >\n";
	echo "<img src='$path_to_root/themes/icon/images/ngiconerp.png'>";
	echo "<div class='input-block'>";
	echo "<input required class='input' id='pass' name='email_entry_field' type='password'>";
	echo "<label for='pass' class='card'>Email</label>";
	echo "</div>";

    $coy =  user_company();
    if (!isset($coy))
        $coy = $def_coy;

    echo "<div class='input-block'>";
	echo "<input required class='input' id='coy' name='company_login_nickname' type='text'>";
	echo "<label for='coy' class='card'>Company</label>";
	echo "</div>";

	echo "<button class='login-submit' type='submit' name='SubmitReset' onclick='set_fullmode();'>"._("Send password")."</button>";
	echo "<a>"._("New password will be sent to your Email.")."</a>";
	echo "</div>";
	echo "</center>";

	end_form(1);
	$Ajax->addScript(true, "document.forms[0].password.focus();");

    echo "<script language='JavaScript' type='text/javascript'>
    //<![CDATA[
            <!--
            document.forms[0].email_entry_field.select();
            document.forms[0].email_entry_field.focus();
            //-->
    //]]>
    </script>";
    div_end();
	echo "</body></html>\n";