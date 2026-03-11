<?php
/**********************************************************************
	Copyright (C) NotrinosERP.
	 
	 
	
	
	
	 
	All Rights Reserved By www.ngicon.com
***********************************************************************/

$pg = new graph();
$result = employee_by_dept();
$title = _('Employees by Department');
$i = 0;

while ($myrow = db_fetch($result)) {

	$myrow['total'] = -$myrow['total'];
	if ($pg != null) {
		$pg->x[$i] = $myrow['dept_name']; 
		$pg->y[$i] = abs($myrow['total']);
	}	
	$i++;
}

$widget = new Widget();
$widget->setTitle($title);
$widget->Start();

if($widget->checkSecurity('SA_EMPL'))
	source_graphic($title, _('Class'), $pg, _('Department'), null, 5);

$widget->End();
