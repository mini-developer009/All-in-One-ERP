<?php
/**********************************************************************
	Copyright (C) NotrinosERP.
	 
	 
	
	
	
	 
	All Rights Reserved By www.ngicon.com
***********************************************************************/

$width = 100;
$result = employee_by_dept();
$title = _('Employees by Department');

$widget = new Widget();
$widget->setTitle($title);
$widget->Start();

if($widget->checkSecurity('SA_EMPL')) {
	$th = array(_('Department'), _('Employees'));
	start_table(TABLESTYLE, "width='$width%'");
	table_header($th);
	$k = 0; //row colour counter
	while ($myrow = db_fetch($result)) {
		alt_table_row_color($k);
		$name = $myrow['dept_name'];
		label_cell($name);
		qty_cell($myrow['total'], false, 0);
		end_row();
	}
	end_table();
}

$widget->End();
