<?php
/**********************************************************************
	Copyright (C) NotrinosERP.
	 
	 
	
	
	
	 
	All Rights Reserved By www.ngicon.com
***********************************************************************/

$width = 100;
$result = employees_by_age();
$title = _('Employees by Age');

$widget = new Widget();
$widget->setTitle($title);
$widget->Start();

if($widget->checkSecurity('SA_EMPL')) {
	$th = array(_('Ages'), _('Employees'));
	start_table(TABLESTYLE, "width='$width%'");
	table_header($th);
	$k = 0; //row colour counter
	foreach ($result as $age=>$val) {
		alt_table_row_color($k);

		label_cell($age);
		qty_cell($val, false, 0);
		end_row();
	}
	end_table();
}

$widget->End();
