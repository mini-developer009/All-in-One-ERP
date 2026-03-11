<?php
/**********************************************************************
    Copyright (C) NGICON ERP (Next Generation icon ERP).
	 
	 
	
    
    
     
    All Rights Reserved By www.ngicon.com
***********************************************************************/
$page_security = 'SA_FORMSETUP';
$path_to_root="..";

include_once($path_to_root . "/includes/session.inc");
include_once('../includes/ui/class.reflines_crud.inc');

include_once($path_to_root . "/includes/ui.inc");

page(_($help_context = "Transaction References"));

start_form();

$companies = new fa_reflines();

$companies->show();

end_form();

end_page();