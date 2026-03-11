<?php
/**********************************************************************
    Copyright (C) NGICON ERP (Next Generation icon ERP).
	




   
 All Rights Reserved By www.ngicon.com
***********************************************************************/
class posmanage_app extends application 
{
	function __construct() 
	{
		parent::__construct("posapp", _($this->help_context = "&POS"));
	
		$this->add_module(_("Transactions"));
	if (get_company_pref('SA_SPS'))			
		$this->add_lapp_function(0, _("Super Point Of Sales"),
			"pos/", 'SA_SPS', MENU_TRANSACTION);


		$this->add_module(_("Inquiries and Reports"));
	if (get_company_pref('SA_POSSS'))			
		$this->add_lapp_function(1, _("Dynamic Sales Summary"),
			"barcode/nsummary.php", 'SA_POSSS', MENU_INQUIRY);
			
	if (get_company_pref('SA_POSSS'))			
		$this->add_lapp_function(1, _("Sales Summary"),
			"barcode/summary.php", 'SA_POSSS', MENU_INQUIRY);			
		

		$this->add_module(_("Maintenance"));
	if (get_company_pref('SA_POSSS'))			
		$this->add_lapp_function(2, _("Sales Return"),
			"barcode/return.php", 'SA_POSSS', MENU_INQUIRY);
			
			
		$this->add_extensions();
	}
}


