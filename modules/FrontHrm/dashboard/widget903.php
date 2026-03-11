<?php
/**********************************************************************
	Copyright (C) NotrinosERP.
	 
	 
	
	
	
	 
	All Rights Reserved By www.ngicon.com
***********************************************************************/

$pg = new graph();

$result = employees_by_age();
$title = _('Employees by Age');

if ($pg != null) {
	foreach($result as $name=>$val) {
		$pg->x[] = $name; 
		$pg->y[] = abs($val);
	}
}

$widget = new Widget();
$widget->setTitle($title);
$widget->Start();

if($widget->checkSecurity('SA_EMPL'))
	source_graphic($title, _('Class'), $pg, _('Ages'), null, 5);

$widget->End();
