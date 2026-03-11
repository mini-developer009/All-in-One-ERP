<?php
/**********************************************************************
    Copyright (C) NGICON ERP (Next Generation icon ERP).
	




   
 All Rights Reserved By www.ngicon.com
***********************************************************************/
class smsmanage_app extends application 
{
	function __construct() 
	{
		parent::__construct("smsapp", _($this->help_context = "&SMS"));
	
		$this->add_module(_("Transactions"));

		$this->add_module(_("Inquiries and Reports"));
	if (get_company_pref('SA_SMSCI'))			
		$this->add_lapp_function(1, _("Customer information"),
			"barcode/customers.php", 'SA_SMSCI', MENU_INQUIRY);
			$this->add_lapp_function(1, _("SMS Report"),
			"sms/sms_report.php", 'SA_SMSSEND', MENU_INQUIRY);



			$this->add_lapp_function(0, _("SMS CAMPAIGN"),
			"sms/sms_sending.php", 'SA_SMSSEND', MENU_TRANSACTION);
			// $this->add_lapp_function(0, _("Customer Send SMS"),
			// "webadmin/customer_send_sms.php", 'SA_SMSSEND', MENU_TRANSACTION);

		$this->add_extensions();
	}
}


