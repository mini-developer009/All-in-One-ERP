<?php
/**********************************************************************
    Copyright (C) NGICON ERP (Next Generation icon ERP).
	




   
 All Rights Reserved By www.ngicon.com
***********************************************************************/
class barcodemanage_app extends application 
{
	function __construct() 
	{
		parent::__construct("barcodeapp", _($this->help_context = "&Barcode"));
	
		$this->add_module(_("Barcode Management"));
	if (get_company_pref('SA_ITEME'))			
		$this->add_lapp_function(0, _("Product | Service Entry"),
			"barcode/add_item.php", 'SA_ITEME', MENU_TRANSACTION);
			
	if (get_company_pref('SA_ITCODE'))				
		$this->add_lapp_function(0, _("Entry With Code"),
			"barcode/add_item_with_code.php", 'SA_ITCODE', MENU_TRANSACTION);			

	if (get_company_pref('SA_ITEME'))			
		$this->add_lapp_function(0, _("Entry With Feature"),
			"barcodef/add_item_f.php", 'SA_ITEME', MENU_TRANSACTION);

	if (get_company_pref('SA_ITCODE'))				
		$this->add_lapp_function(0, _("Entry With Feature+Code"),
			"barcodef/add_item_with_code_d.php", 'SA_ITCODE', MENU_TRANSACTION);

		$this->add_module(_("Barcode Print"));
	if (get_company_pref('SA_POSVIEWBARCODE'))			
		$this->add_lapp_function(1, _("Print Barcode (Machine)"),
			"barcode/print_barcode.php", 'SA_POSVIEWBARCODE', MENU_INQUIRY);
	if (get_company_pref('SA_POSVIEWBARCODEA4'))			
		$this->add_lapp_function(1, _("Barcode Print A4"),
			"barcode/print_barcodea4.php", 'SA_POSVIEWBARCODEA4', MENU_INQUIRY);

		$this->add_module(_("Calculations"));
		
	if (get_company_pref('SA_POSSS'))			
		$this->add_lapp_function(2, _("Dynamic Sales Summary"),
			"barcode/nsummary.php", 'SA_POSSS', MENU_INQUIRY);
			
	if (get_company_pref('SA_POSSTOCKSUMMARY'))			
		$this->add_lapp_function(2, _("Admin Sales Summary"),
			"barcode/summaryadmin.php?", 'SA_POSSTOCKSUMMARY', MENU_ENTRY);

	if (get_company_pref('SA_POSSTOCKSUMMARY'))			
		$this->add_lapp_function(2, _("Custom Report"),
			"barcode/custom.php?", 'SA_POSSTOCKSUMMARY', MENU_ENTRY);			
			
	if (get_company_pref('SA_POSSTOCKSUMMARY'))			
		$this->add_lapp_function(2, _("Admin Stock Summary"),
			"barcode/all_items.php?", 'SA_POSSTOCKSUMMARY', MENU_ENTRY);
	if (get_company_pref('SA_POSSSTOCKSUMMARY'))			
		$this->add_lapp_function(2, _("Customers Informations"),
			"barcode/customers.php?", 'SA_POSSSTOCKSUMMARY', MENU_ENTRY);			
	if (get_company_pref('SA_POSSSTOCKSUMMARY'))			
		$this->add_lapp_function(2, _("Shop Stock Summary"),
			"barcode/all_itemspu.php?", 'SA_POSSSTOCKSUMMARY', MENU_ENTRY);			

			
	if (get_company_pref('SA_POSSTOCKEXPIRE'))			
		$this->add_lapp_function(2, _("Create Offer"),
			"barcode/discounted_product.php", 'SA_POSSTOCKEXPIRE', MENU_ENTRY);	
	if (get_company_pref('SA_POSSTOCKEXPIRE'))			
		$this->add_lapp_function(2, _("Stop Offer"),
			"barcode/stop_offer.php", 'SA_POSSTOCKEXPIRE', MENU_ENTRY);			
			

	if (get_company_pref('SA_POSSSTOCKSUMMARY'))			
		$this->add_lapp_function(2, _("Offer Stock Summary"),
			"barcode/product_price.php?", 'SA_POSSSTOCKSUMMARY', MENU_ENTRY);				
			
	if (get_company_pref('SA_POSSTOCKALERT'))			
		$this->add_lapp_function(2, _("Admin Stock Alert"),
			"barcode/alert_list.php?", 'SA_POSSTOCKALERT', MENU_ENTRY);
	if (get_company_pref('SA_POSSTOCKEXPIRE'))			
		$this->add_lapp_function(2, _("Admin Stock Expirelist"),
			"barcode/expire_date_list.php?", 'SA_POSSTOCKEXPIRE', MENU_ENTRY);

		$this->add_extensions();
	}
}


