<?php

define("FA_LOGOUT_PHP_FILE","");

$page_security = 'SA_OPEN';
$path_to_root="..";
include($path_to_root . "/includes/session.inc");
add_js_file('login.js');

$title = $SysPrefs->app_title." ".$version." - "._("Logout");
$encoding = isset($_SESSION['language']->encoding) ? $_SESSION['language']->encoding : "iso-8859-1";
$rtl = isset($_SESSION['language']->dir) ? $_SESSION['language']->dir : "ltr";

echo "<html dir='$rtl' >\n";
echo "<head profile=\"http://www.w3.org/2005/10/profile\"><title>$title</title>\n";
echo "<meta http-equiv='Content-type' content='text/html; charset=$encoding' >\n";
echo '<meta name="viewport" content="width=device-width,initial-scale=1">';
echo "<link href='$path_to_root/themes/icon/access.css' rel='stylesheet' type='text/css'> \n";
echo "<link href='$path_to_root/themes/icon/images/favicon.png' rel='icon' type='image/x-icon'> \n";

echo "<center>";
echo _("Thank you for using")." "."<b>".$SysPrefs->app_title."&nbsp;".$version."</b><br><br>";

echo "<a href='$path_to_root/index.php'><b>" . _("Click here to Login Again.") . "</b></a>";
echo "</center>";

echo "</body></html>\n";

session_unset();
@session_destroy();
