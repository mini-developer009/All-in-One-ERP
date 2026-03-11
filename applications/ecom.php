<?php
/**********************************************************************
    Copyright (C) NGICON ERP (Next Generation icon ERP).
	




   
 All Rights Reserved By www.ngicon.com
***********************************************************************/
class ecommanage_app extends application 
{
	function __construct() 
	{
		parent::__construct("ecomapp", _($this->help_context = "&E-Commerce"));
	
		$this->add_module(_("Transactions"));
		//	$this->add_lapp_function(0, _("Change Logo"),
		//	"webadmin/logo.php", 'SA_SALESQUOTE', MENU_TRANSACTION);
		//	$this->add_lapp_function(0, _("Change Title"),
		//	"webadmin/change.php?type=title", 'SA_SALESQUOTE', MENU_TRANSACTION);
		//	$this->add_lapp_function(0, _("Change Phone no."),
		//	"webadmin/change.php?type=mobile", 'SA_SALESQUOTE', MENU_TRANSACTION);
		
		//	$this->add_lapp_function(0, _("Change Email"),
		//	"webadmin/change.php?type=email", 'SA_SALESQUOTE', MENU_TRANSACTION);
			$this->add_lapp_function(0, _("Change Address"),
			"webadmin/change.php?type=address", 'SA_SALESQUOTE', MENU_TRANSACTION);
			$this->add_lapp_function(0, _("Change Slider"),
			"webadmin/slider.php", 'SA_SALESQUOTE', MENU_TRANSACTION);
			$this->add_lapp_function(0, _("Change Footer Text"),
			"webadmin/change_footer.php", 'SA_SALESQUOTE', MENU_TRANSACTION);
			$this->add_lapp_function(0, _("Change working hour"),
			"webadmin/working_hour.php", 'SA_SALESQUOTE', MENU_TRANSACTION);
			$this->add_lapp_function(0, _("Change Map"),
			"webadmin/change_map.php", 'SA_SALESQUOTE', MENU_TRANSACTION);
			$this->add_lapp_function(0, _("User Info"),
			"webadmin/user_info.php", 'SA_SALESQUOTE', MENU_TRANSACTION);
			$this->add_lapp_function(0, _("SUMMARY"),
			"webadmin/summaryadmin.php", 'SA_SALESQUOTE', MENU_TRANSACTION);
		//	$this->add_lapp_function(0, _("Show Order"),
		//	"webadmin/show_order.php", 'SA_SALESQUOTE', MENU_TRANSACTION);
			$this->add_lapp_function(0, _("Create Menu"),
			"webadmin/add_product_group.php?add_menu=", 'SA_SALESQUOTE', MENU_TRANSACTION);
			$this->add_lapp_function(0, _("Add Category Image"),
			"webadmin/add-stock-img.php?viewall=", 'SA_SALESQUOTE', MENU_TRANSACTION);
			$this->add_lapp_function(0, _("Add Product Alt Image"),
			"webadmin/product.php?viewallproduct=", 'SA_SALESQUOTE', MENU_TRANSACTION);
		$this->add_module(_("Order and Reports"));

			$this->add_lapp_function(0, _("Show Order"),
			"webadmin/show_order_new.php", 'SA_SALESQUOTE', MENU_TRANSACTION);
			$this->add_lapp_function(0, _("Delivery Charge"),
			"webadmin/deliveryCharge.php", 'SA_SALESQUOTE', MENU_TRANSACTION);
			$this->add_lapp_function(0, _("Customer Registration Permission"),
			"webadmin/reg_permission.php?reg_per=", 'SA_SALESQUOTE', MENU_TRANSACTION);
			$this->add_lapp_function(0, _("Customer Feedback"),
			"webadmin/customer_feedback.php", 'SA_SALESQUOTE', MENU_TRANSACTION);
			$this->add_lapp_function(0, _("Change Bkash Payment Number"),
			"webadmin/change2.php?bkashpayment=", 'SA_SALESQUOTE', MENU_TRANSACTION);

			$this->add_lapp_function(0, _("SMS Sending"),
			"webadmin/sms_sending.php", 'SA_SALESQUOTE', MENU_TRANSACTION);
			// $this->add_lapp_function(0, _("Customer Send SMS"),
			// "webadmin/customer_send_sms.php", 'SA_SALESQUOTE', MENU_TRANSACTION);
			$this->add_lapp_function(0, _("SMS Report"),
			"webadmin/sms_report.php", 'SA_SALESQUOTE', MENU_TRANSACTION);

			$this->add_lapp_function(0, _("Invoice Info"),
			"webadmin/change2.php?sys_pref=invoicefooter", 'SA_SALESQUOTE', MENU_TRANSACTION);
			$this->add_lapp_function(0, _("Receipt Info"),
			"webadmin/change2.php?sys_pref=receiptfooter", 'SA_SALESQUOTE', MENU_TRANSACTION);



			$this->add_lapp_function(0, _("Change Hotline"),
			"webadmin/change2.php?changefootercontactnumber=", 'SA_SALESQUOTE', MENU_TRANSACTION);			

			$this->add_lapp_function(0, _("Change Email"),
			"webadmin/change2.php?changeemail=", 'SA_SALESQUOTE', MENU_TRANSACTION);				
			
			$this->add_lapp_function(0, _("Change Logo and Header Call Text"),
			"webadmin/change2.php?changelogo=", 'SA_SALESQUOTE', MENU_TRANSACTION);
			$this->add_lapp_function(0, _("Change Title"),
			"webadmin/change2.php?changetitle=", 'SA_SALESQUOTE', MENU_TRANSACTION);
			$this->add_lapp_function(0, _("Change Theme Color"),
			"webadmin/change2.php?changeThemeColor=", 'SA_SALESQUOTE', MENU_TRANSACTION);
			$this->add_lapp_function(0, _("Flash Sale Status"),
			"webadmin/change2.php?flashsale=", 'SA_SALESQUOTE', MENU_TRANSACTION);
			$this->add_lapp_function(0, _("Set Flash Sale Time"),
			"webadmin/change2.php?flashsaletime=", 'SA_SALESQUOTE', MENU_TRANSACTION);			
			$this->add_lapp_function(0, _("Marchant Request"),
			"webadmin/marchant/index.php", 'SA_SALESQUOTE', MENU_TRANSACTION);
			$this->add_lapp_function(0, _("Go to Marchant Shop"),
			"webadmin/marchant/marchantshop.php?shoplist=", 'SA_SALESQUOTE', MENU_TRANSACTION);
			$this->add_lapp_function(0, _("Pending Product"),
			"webadmin/marchant/marchant-product.php?pending_product=", 'SA_SALESQUOTE', MENU_TRANSACTION);
			$this->add_lapp_function(0, _("All Marchant Product"),
			"webadmin/marchant/marchant-product.php?all_product=", 'SA_SALESQUOTE', MENU_TRANSACTION);			

		$this->add_extensions();
	}
}


