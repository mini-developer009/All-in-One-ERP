<?php
/**********************************************************************
    Copyright (C) NGICON ERP (Next Generation icon ERP).
	 
	 
	
    
    
     
    All Rights Reserved By www.ngicon.com
***********************************************************************/

function add_item_purchasing_data($supplier_id, $stock_id, $price,
	$suppliers_uom, $conversion_factor, $supplier_description)
{
	$supp_desc = db_escape($supplier_description);
	if (strlen($supp_desc) > 50) // to be fixed and removed in 2.5
		$supp_desc = substr($supp_desc, 0, 50)."'";
	$sql = "INSERT INTO ".TB_PREF."purch_data (supplier_id, stock_id, price, suppliers_uom,
		conversion_factor, supplier_description) VALUES (";
	$sql .= db_escape($supplier_id).", ".db_escape($stock_id). ", "
		.$price . ", ".db_escape($suppliers_uom ). ", "
		.$conversion_factor . ", "
		.$supp_desc . ")";

	db_query($sql,"The supplier purchasing details could not be added");
}

function update_item_purchasing_data($selected_id, $stock_id, $price,
	$suppliers_uom, $conversion_factor, $supplier_description)
{
	$supp_desc = db_escape($supplier_description);
	if (strlen($supp_desc) > 50) // to be fixed and removed in 2.5
		$supp_desc = substr($supp_desc, 0, 50) ."'";
	$sql = "UPDATE ".TB_PREF."purch_data SET price=" . $price . ",
		suppliers_uom=".db_escape($suppliers_uom) . ",
		conversion_factor=" . $conversion_factor . ",
		supplier_description=" . $supp_desc . "
		WHERE stock_id=".db_escape($stock_id) . " AND
		supplier_id=".db_escape($selected_id);
	db_query($sql,"The supplier purchasing details could not be updated");
}

function delete_item_purchasing_data($selected_id, $stock_id)
{
	$sql = "DELETE FROM ".TB_PREF."purch_data WHERE supplier_id=".db_escape($selected_id)."
		AND stock_id=".db_escape($stock_id);
	db_query($sql,"could not delete purchasing data");
}

function get_items_purchasing_data($stock_id)
{
    $sql = "SELECT pdata.*, supplier.supp_name,	supplier.curr_code
		FROM ".TB_PREF."purch_data pdata
			INNER JOIN ".TB_PREF."suppliers supplier ON pdata.supplier_id=supplier.supplier_id
		WHERE stock_id = ".db_escape($stock_id);

    return db_query($sql, "The supplier purchasing details for the selected part could not be retrieved");
}

function get_item_purchasing_data($selected_id, $stock_id)
{
	$sql = "SELECT pdata.*, supplier.supp_name
		FROM ".TB_PREF."purch_data pdata
			INNER JOIN ".TB_PREF."suppliers supplier ON pdata.supplier_id=supplier.supplier_id
		WHERE pdata.supplier_id=".db_escape($selected_id)."
			AND pdata.stock_id=".db_escape($stock_id);

	$result = db_query($sql, "The supplier purchasing details for the selected supplier and item could not be retrieved");

	return db_fetch($result);
}
