<?php

function dashboard($sel_app)
{
	$selected_app = $sel_app;


	if (is_object($sel_app) && !$_SESSION["wa_current_user"]->check_application_access($selected_app))
		return;
	$dir = company_path(). '/pdf_files';

	if ($d = @opendir($dir)) {
		while (($file = readdir($d)) !== false) {
			if (!is_file($dir.'/'.$file) || $file == 'index.php') continue;
			$ftime = filemtime($dir.'/'.$file);
			if (time()-$ftime > 180){
				unlink($dir.'/'.$file);
			}
		}
		closedir($d);
	}
	
	$dashboard_header = new dashboard_header;
	$dashboard_header -> get_header($selected_app);
	$dashboard_header -> show_header();
	
	if ($selected_app == "orders")
		display_sales_statistics();
	elseif ($selected_app == "AP")
		display_supplier_topten();
	elseif ($selected_app == "stock")
		display_stock_topten();
	elseif ($selected_app == "manuf")
		display_stock_topten(1);
	elseif ($selected_app == "assets")
		display_stock_topten(2);
	elseif ($selected_app == "proj")
		display_dimension_topten();
	elseif ($selected_app == "GL")
		display_gl_info();
	else	
		display_all();
}

class dashboard_header {

	var $inner_desc1 = "undefined";
	var $inner_desc2 = "undefined";
	var $inner_desc3 = "undefined";
	var $inner_desc4 = "undefined";
	
	var $count_item1 = "0";
	var $count_item2 = "0";
	var $count_item3 = "0";
	var $count_item4 = "0";
	
	function get_header($selapp=null) {
		
		if ($selapp == "orders")
			$this->generate_header(_("Customers"), _("Branches"), _("Sales People"), _('Sales Areas'), $selapp);
		elseif ($selapp == "AP")
			$this->generate_header(_("Suppliers"), _("Purchasable items"), _("New Orders"), _("Invoices"), $selapp);
		elseif ($selapp == "stock")
			$this->generate_header(_("Components"), _("Services"), _("Systems"), _("Charges"), $selapp);
		elseif ($selapp == "manuf")
			$this->generate_header(_("Systems"), _("Outstanding Work Orders"), _("New Work Orders"), _("Overdue"), $selapp);
		elseif ($selapp == "assets")
			$this->generate_header(_("Fixed Assets"), _("Categories"), _("Classes"), _("Locations"), $selapp);
		elseif ($selapp == "proj")
			$this->generate_header(_("Dimensions"), _("Outstanding"), _("Currents"), _("Overdue"), $selapp);
		elseif ($selapp == "GL")
			$this->generate_header(_("Receivables"), _("Payables"), _("Today's Deposits"), _("Today's Payments"), $selapp);
		elseif ($selapp == "school")
			$this->generate_header(_("Students"), _("Teachers"), _("Month's Receivables"), _("Late Payments"), $selapp);
		elseif ($selapp == "FrontHrm")
			$this->generate_header(_("Employees"), _("Departments"), _("Unpaid Salaries"), _("Today's Absentee"), $selapp);
		elseif ($selapp == 'system')
			$this->generate_header(_("Users"), _("Access Roles"), _("History"), _("Mb data"), $selapp);
		else
			$this->generate_header();
	}
	
	function generate_header($inner1='Undefined', $inner2='Undefined', $inner3='Undefined', $inner4='Undefined', $selapp=null)
	{
		global $db_connections;

		$this->inner_desc1 = $inner1 == '' ? $this->inner_desc1 : $inner1;
		$this->inner_desc2 = $inner2 == '' ? $this->inner_desc2 : $inner2;
		$this->inner_desc3 = $inner3 == '' ? $this->inner_desc3 : $inner3;
		$this->inner_desc4 = $inner4 == '' ? $this->inner_desc4 : $inner4;
		
		if($selapp == 'orders') {
			$this->count_item1 = db_fetch(db_query("SELECT COUNT(debtor_no) FROM ".TB_PREF."debtors_master WHERE !inactive"))[0];
			$this->count_item2 = db_fetch(db_query("SELECT COUNT(branch_code) FROM ".TB_PREF."cust_branch WHERE !inactive"))[0];
			$this->count_item3 = db_fetch(db_query("SELECT COUNT(salesman_code) FROM ".TB_PREF."salesman WHERE !inactive"))[0];
			$this->count_item4 = db_fetch(db_query("SELECT COUNT(area_code) FROM ".TB_PREF."areas WHERE !inactive"))[0];
		}
		elseif ($selapp == "AP") {
			$this->count_item1 = db_fetch(db_query("SELECT COUNT(*) FROM ".TB_PREF."suppliers WHERE !inactive"))[0];
			$this->count_item2 = db_fetch(db_query("SELECT COUNT(*) FROM ".TB_PREF."stock_master WHERE !no_purchase"))[0];
		}
		elseif ($selapp == "stock") {
			$this->count_item1 = db_fetch(db_query("SELECT COUNT(*) FROM ".TB_PREF."stock_master WHERE !inactive AND category_id=1"))[0];
			$this->count_item2 = db_fetch(db_query("SELECT COUNT(*) FROM ".TB_PREF."stock_master WHERE !inactive AND category_id=4"))[0];
			$this->count_item3 = db_fetch(db_query("SELECT COUNT(*) FROM ".TB_PREF."stock_master WHERE !inactive AND category_id=3"))[0];
			$this->count_item4 = db_fetch(db_query("SELECT COUNT(*) FROM ".TB_PREF."stock_master WHERE !inactive AND category_id=2"))[0];
		}
		elseif ($selapp == "manuf") {
			$this->count_item1 = db_fetch(db_query("SELECT COUNT(*) FROM ".TB_PREF."stock_master WHERE !inactive AND category_id=3"))[0];
		}
		elseif ($selapp == "assets"){
			$this->count_item1 = db_fetch(db_query("SELECT COUNT(*) FROM ".TB_PREF."stock_master WHERE !inactive AND fa_class_id != ''"))[0];
			$this->count_item2 = db_fetch(db_query("SELECT COUNT(*) FROM ".TB_PREF."stock_category WHERE !inactive AND dflt_mb_flag = 'F'"))[0];
			$this->count_item3 = db_fetch(db_query("SELECT COUNT(*) FROM ".TB_PREF."stock_fa_class WHERE !inactive"))[0];
			$this->count_item4 = db_fetch(db_query("SELECT COUNT(*) FROM ".TB_PREF."locations WHERE !inactive AND fixed_asset=1"))[0];
		}
		elseif ($selapp == "proj") {
			$this->count_item1 = db_fetch(db_query("SELECT COUNT(*) FROM ".TB_PREF."dimensions"))[0];
		}
		elseif ($selapp == "GL"){
		
		}
		elseif ($selapp == "school"){
			$this->count_item1 = db_fetch(db_query("SELECT COUNT(*) FROM ".TB_PREF."students"))[0];
			$this->count_item2 = db_fetch(db_query("SELECT COUNT(*) FROM ".TB_PREF."teachers"))[0];
		}
		elseif ($selapp == "FrontHrm") {
			$this->count_item1 = db_fetch(db_query("SELECT COUNT(*) FROM ".TB_PREF."employee"))[0];
			$this->count_item2 = db_fetch(db_query("SELECT COUNT(*) FROM ".TB_PREF."department"))[0];
			$this->count_item3 = db_fetch(db_query("SELECT COUNT(*) FROM (SELECT p.payslip_no FROM ".TB_PREF."gl_trans gl, ".TB_PREF."payslip_detail p WHERE p.payslip_no = gl.payslip_no AND gl.type_no NOT IN(SELECT trans_no FROM ".TB_PREF."bank_trans) AND gl.person_type_id = ".PT_EMPLOYEE." GROUP BY gl.type_no) count"))[0];
		}
		elseif ($selapp == "system") {
			$this->count_item1 = db_fetch(db_query("SELECT COUNT(*) FROM ".TB_PREF."users WHERE !inactive"))[0];
			$this->count_item2 = db_fetch(db_query("SELECT COUNT(*) FROM ".TB_PREF."security_roles WHERE !inactive"))[0];
			$this->count_item3 = db_fetch(db_query("SELECT COUNT(*) FROM ".TB_PREF."fiscal_year"))[0];
			$this->count_item4 = db_fetch(db_query("SELECT sum(round(((data_length + index_length) / 1024 / 1024), 2)) FROM information_schema.TABLES WHERE table_schema = '".$db_connections[user_company()]['dbname']."'"))[0];
		}
	}
	
	function show_header() {
	
		echo "<div class='header_box'>";
		echo "<div class='header_item item_0'>
			<span>".$this->count_item1."</span>
			<p>".$this->inner_desc1."<p>
		</div>";
		echo "<div class='header_item item_1'>
			<span>".$this->count_item2."</span>
			<p>".$this->inner_desc2."<p>
		</div>";
		echo "<div class='header_item item_2'>
			<span>".$this->count_item3."</span>
			<p>".$this->inner_desc3."<p>
		</div>";
		echo "<div class='header_item item_3'>
			<span>".$this->count_item4."</span>
			<p>".$this->inner_desc4."<p>
		</div>";
		echo "</div>";
	}
}

function display_title($title, $add_class = '', $panels=array()) {

	echo "<div class='widget_box bootstrap-iso $add_class'>";
	echo "<div class='widget_title'>";
	echo "<span>$title</span>";

	if(count($panels) > 0) {
		echo "<ul class='nav nav-tabs'>";
		foreach($panels as $key=>$val) {
			echo "<li><a data-toggle='tab' href='#".$add_class."_panel$key'><i class='fa fa-$panels[$key]'></i></a></li>";
		}
		echo "</ul>";
	}
	echo "</div>";
}

function display_sales_statistics() {
	$today = Today();

	customer_top($today, 10, 100, array('bar-chart','table','pie-chart'));
	sales_areas($today, 10, 100, array('bar-chart','table','pie-chart'));

	customer_trans($today);
	customer_recurrent_invoices($today);
}

function display_supplier_topten()
{
	$today = Today();
	supplier_top($today, 10, 100);
	supplier_top($today, 10, 100, array('bar-chart','pie-chart'));
	supplier_trans($today);
}

function display_stock_topten($type=0) {
	$today = Today();
	stock_top($today, 10, 100, $type);
	stock_top($today, 10, 100, $type, array('bar-chart'));
}

function display_dimension_topten() {

	$today = Today();
	dimension_top($today, 10, 100);
	dimension_top($today, 10, 100, array('bar-chart', 'pie-chart'));
}	

function display_gl_info() {

	$today = Today();
	cash_flow($today, 100, array('line-chart', 'bar-chart'));
	gl_performance($today, 100, 5);
	gl_top($today, 100, array('pie-chart', 'bar-chart'));
	gl_top($today, 100);
	bank_balance($today, 100);
}	

function display_all()
{
	$today = Today();

	gl_top($today, 100);
	gl_top($today, 100, array('pie-chart'));

	stock_top($today, 3, 100, 0, array('table', 'bar-chart'));
	
	customer_top($today, 3, 100, array('table', 'bar-chart', 'pie-chart'));

	supplier_top($today, 3, 100, array('table', 'bar-chart', 'pie-chart'));

	dimension_top($today, 3, 100, array('table', 'bar-chart', 'pie-chart'));
	stock_top($today, 3, 100, 2);
	stock_top($today, 3, 100, 1, array('table', 'bar-chart', 'pie-chart'));
}

function sales_areas($today, $limit=10, $width="33", $panels=array()) {

	$sql = "SELECT SUM((ov_amount + ov_discount) * rate * IF(t.type = ".ST_CUSTCREDIT.", -1, 1)) total, a.description name FROM ".TB_PREF."areas a, ".TB_PREF."cust_branch b, ".TB_PREF."debtor_trans t WHERE (t.type = ".ST_SALESINVOICE." OR t.type = ".ST_CUSTCREDIT.") AND t.branch_code = b.branch_code AND b.area = a.area_code GROUP BY name";
	$result = db_query($sql, "Transactions could not be calculated");
	$title = sprintf(_("Sales by Regions"), $limit);

	$icons = array();

	foreach($panels as $val) {
		$icons[] = $val;
	}
	if(count($panels) > 1)
		display_title($title, 'areas_top', $icons);
	else
		display_title($title, 'areas_top');
	
	echo "<div class='widget_content tab-content'>";

	if(count($panels) > 0) {
		foreach($panels as $key=>$val) {
			if($val == 'table')
				echo "<div class='tab-pane fade' id='areas_top_panel$key'>";
		}
	}
	if(count($panels) == 0 || in_array('table', $panels)) {
		$th = array(_("Area"), _("Amount"));
		start_table(TABLESTYLE_NOBORDER, "width='$width%'");
		table_header($th);
	}
	
	check_page_security('SA_SALESTRANSVIEW');
	$k = 0;
	$i = 0;
	$pg = array('x'=>array(), 'y'=>array());
	while ($myrow = db_fetch($result))
	{
		$name = $myrow["name"];
		if(count($panels) == 0 || in_array('table', $panels)) {
			alt_table_row_color($k);
			label_cell($name);
			amount_cell($myrow['total']);
		}

		$pg['x'][] = $name; 
		$pg['y'][] = abs($myrow['total']);
		$i++;
		if(count($panels) == 0 || in_array('table', $panels))
			end_row();
	}
	if(count($panels) == 0 || in_array('table', $panels))
		end_table();
	if(count($panels) > 0) {
		foreach($panels as $key=>$val) {
			if($val == 'table')
				echo "</div>";
		}
		if(in_array('bar-chart', $panels)) {
			foreach($panels as $key=>$val) {
				if($val == 'bar-chart')
					echo "<div class='tab-pane fade' id='areas_top_panel$key'>";
			}
			single_bar($today, false, $pg, 'areas_top');
			echo "</div>";
		}
		if(in_array('pie-chart', $panels)) {
			foreach($panels as $key=>$val) {
				if($val == 'pie-chart')
					echo "<div class='tab-pane fade in active' id='areas_top_panel$key'>";
			}
			pie_chart($today, false, $pg, 'areas_top_pie');
			echo "</div>";
		}
	}

	echo "</div>"; // content
	echo "</div>";  // box
	return $title;
}

function customer_top($today, $limit=10, $width="33", $panels=array())
{
	$begin = date2sql(begin_fiscalyear());
	$today1 = date2sql($today);
	$sql = "SELECT SUM((ov_amount + ov_discount) * rate * IF(trans.type = ".ST_CUSTCREDIT.", -1, 1)) AS total,d.debtor_no, d.name FROM
		".TB_PREF."debtor_trans AS trans, ".TB_PREF."debtors_master AS d WHERE trans.debtor_no=d.debtor_no
		AND (trans.type = ".ST_SALESINVOICE." OR trans.type = ".ST_CUSTCREDIT.")
		AND tran_date >= '$begin' AND tran_date <= '$today1' GROUP by d.debtor_no ORDER BY total DESC, d.debtor_no 
		LIMIT $limit";
	$result = db_query($sql);

	$title = sprintf(_("Top %s customers this year"), $limit);

	$icons = array();

	foreach($panels as $val) {
		$icons[] = $val;
	}
	if(count($panels) > 1)
		display_title($title, 'cus_top', $icons);
	else
		display_title($title, 'cus_top');

	echo "<div class='widget_content tab-content'>";

	if(count($panels) > 0) {
		foreach($panels as $key=>$val) {
			if($val == 'table')
				echo "<div class='tab-pane fade' id='cus_top_panel$key'>";
		}
	}

	if(count($panels) == 0 || in_array('table', $panels)) {
		$th = array(_("Customer"), _("Amount"));
		start_table(TABLESTYLE_NOBORDER, "width='$width%'");
		table_header($th);
	}
	
	check_page_security('SA_SALESTRANSVIEW');
	$k = 0; //row colour counter
	$i = 0;
	$pg = array('x'=>array(), 'y'=>array());
	while ($myrow = db_fetch($result))
	{
		$name = $myrow["debtor_no"]." ".$myrow["name"];
		if(count($panels) == 0 || in_array('table', $panels)) {
			alt_table_row_color($k);
			label_cell($name);
			amount_cell($myrow['total']);
		}
		
		$pg['x'][] = $myrow["name"]; 
		$pg['y'][] = abs($myrow['total']);
		$i++;
		if(count($panels) == 0 || in_array('table', $panels))
			end_row();
	}
	if(count($panels) == 0 || in_array('table', $panels))
		end_table();

	if(count($panels) > 0) {
		foreach($panels as $key=>$val) {
			if($val == 'table')
				echo "</div>";
		}
		if(in_array('bar-chart', $panels)) {
			foreach($panels as $key=>$val) {
				if($val == 'bar-chart')
					echo "<div class='tab-pane fade in active' id='cus_top_panel$key'>";
			}
			single_bar($today, false, $pg, 'cus_top');
			echo "</div>";
		}

		if(in_array('pie-chart', $panels)) {
			foreach($panels as $key=>$val) {
				if($val == 'pie-chart')
					echo "<div class='tab-pane fade' id='cus_top_panel$key'>";
			}
			pie_chart($today, false, $pg, 'cus_top_pie');
			echo "</div>";
		}
	}

	echo "</div>"; // content
	echo "</div>";  // box	

	return $title;
}

function supplier_top($today, $limit=10, $width="33", $panels=array()) {
	$begin = date2sql(begin_fiscalyear());
	$today1 = date2sql($today);
	$sql = "SELECT SUM((trans.ov_amount + trans.ov_discount) * rate) AS total, s.supplier_id, s.supp_name FROM
		".TB_PREF."supp_trans AS trans, ".TB_PREF."suppliers AS s WHERE trans.supplier_id=s.supplier_id
		AND (trans.type = ".ST_SUPPINVOICE." OR trans.type = ".ST_SUPPCREDIT.")
		AND tran_date >= '$begin' AND tran_date <= '$today1' GROUP by s.supplier_id ORDER BY total DESC, s.supplier_id 
		LIMIT $limit";
	$result = db_query($sql);

	$title = sprintf(_("Top %s suppliers this year"), $limit);

	$icons = array();
	foreach($panels as $val) {
		$icons[] = $val;
	}
	if(count($panels) > 1)
		display_title($title, "sup_top", $icons);
	else
		display_title($title, "sup_top");
	
	echo "<div class='widget_content tab-content'>";

	if(count($panels) > 0) {
		foreach($panels as $key=>$val) {
			if($val == 'table')
				echo "<div class='tab-pane fade' id='sup_top_panel$key'>";
		}
	}
	if(count($panels) == 0 || in_array('table', $panels)) {
		$th = array(_("Supplier"), _("Amount"));
		start_table(TABLESTYLE_NOBORDER, "width='$width%'");
		table_header($th);
	}
	check_page_security('SA_SUPPTRANSVIEW');
	$k = 0; //row colour counter
	$i = 0;
	$pg = array('x'=>array(), 'y'=>array());
	while ($myrow = db_fetch($result))
	{
		$name = $myrow["supplier_id"]." ".$myrow["supp_name"];
		if(count($panels) == 0 || in_array('table', $panels)) {
			alt_table_row_color($k);
			label_cell($name);
			amount_cell($myrow['total']);
		}
		$pg['x'][] = $myrow["supp_name"]; 
		$pg['y'][] = abs($myrow['total']);
		$i++;
		if(count($panels) == 0 || in_array('table', $panels))
			end_row();
	}
	if(count($panels) == 0 || in_array('table', $panels))
		end_table();

	if(count($panels) > 0) {
		foreach($panels as $key=>$val) {
			if($val == 'table')
				echo "</div>";
		}
		if(in_array('bar-chart', $panels)) {
			foreach($panels as $key=>$val) {
				if($val == 'bar-chart')
					echo "<div class='tab-pane fade in active' id='sup_top_panel$key'>";
			}
			single_bar($today, false, $pg, 'sup_top');
			echo "</div>";
		}
		if(in_array('pie-chart', $panels)) {
			foreach($panels as $key=>$val) {
				if($val == 'pie-chart')
					echo "<div class='tab-pane fade' id='sup_top_panel$key'>";
			}
			pie_chart($today, false, $pg, 'sup_top_pie');
			echo "</div>";
		}
	}
	echo "</div>"; // content
	echo "</div>";  // box
	$pg = array('x'=>array(), 'y'=>array());
}

function stock_top($today, $limit=10, $width="33", $type=0, $panels=array()) {
	if ($type == 2)
		$sec = 'SA_ASSETSANALYTIC';
	elseif ($type == 1)
		$sec = 'SA_WORKORDERANALYTIC';
	else
		$sec = 'SA_ITEMSTRANSVIEW';
	$begin = begin_fiscalyear();
	$begin1 = date2sql($begin);
	$today1 = date2sql($today);

	if ($type == 0) {
		$sql = "SELECT SUM((trans.unit_price * trans.quantity) * d.rate) AS total, s.stock_id, s.description, 
			SUM(trans.quantity) AS qty, SUM((s.material_cost + s.overhead_cost + s.labour_cost) * trans.quantity) AS costs FROM
			".TB_PREF."debtor_trans_details AS trans, ".TB_PREF."stock_master AS s, ".TB_PREF."debtor_trans AS d 
			WHERE trans.stock_id=s.stock_id AND trans.debtor_trans_type=d.type AND trans.debtor_trans_no=d.trans_no
			AND (d.type = ".ST_SALESINVOICE." OR d.type = ".ST_CUSTCREDIT.") ";
	}
	else {
		$sql = "SELECT SUM(m.qty * (s.material_cost + s.labour_cost + s.overhead_cost)) AS total, s.stock_id, s.description, 
			SUM(qty) AS qty FROM ".TB_PREF."stock_master AS s, ".TB_PREF."stock_moves AS m 
			WHERE s.stock_id=m.stock_id ";
		if ($type == 1)
			$sql .= "AND s.mb_flag='M' AND m.qty > 0 ";
		elseif ($type == 2)	
			$sql .= "AND s.mb_flag='F' ";
	}
	if ($type != 2)
		$sql .= "AND tran_date >= '$begin1' ";
	$sql .= "AND tran_date <= '$today1' GROUP by s.stock_id ORDER BY total DESC, s.stock_id 
		LIMIT $limit";
	$result = db_query($sql);
	if ($type == 1)
		$title = sprintf(_("Top %s Manufactured Items in fiscal year"), $limit);
	elseif ($type == 2)
		$title = sprintf(_("Top %s Fixed Assets"), $limit);
	else	
		$title = sprintf(_("Top %s Sold Items in fiscal year"), $limit);

	$icons = array();
	foreach($panels as $val) {
		$icons[] = $val;
	}
	if(count($panels) > 1)
		display_title($title, "stock_top_$type", $icons);
	else
		display_title($title, "stock_top_$type");

	echo "<div class='widget_content tab-content'>";

	if(count($panels) > 0) {
		foreach($panels as $key=>$val) {
			if($val == 'table')
				echo "<div class='tab-pane fade' id='stock_top_".$type."_panel$key'>";
		}
	}
	if(count($panels) == 0 || in_array('table', $panels)) {
		if ($type == 0)	
			$th = array(_("Item"), _("Sales"), _("Costs"), _("Quantity"));
		else	
			$th = array(_("Item"), _("Amount"), _("Quantity"));
	
		start_table(TABLESTYLE_NOBORDER, "width='$width%'");
		table_header($th);
	}
	check_page_security($sec);
	$k = 0; //row colour counter
	$i = 0;
	$pg = array('x'=>array(), 'y'=>array(), 'z'=>array());
	while ($myrow = db_fetch($result))
	{
		$name = $myrow["description"];
		if(count($panels) == 0 || in_array('table', $panels)) {
			alt_table_row_color($k);
			label_cell($name);
			amount_cell($myrow['total']);
			if ($type == 0)
				amount_cell($myrow['costs']);
			qty_cell($myrow['qty']);
		}

		$pg['x'][] = $name; 
		$pg['y'][] = $myrow['total'];
		if ($type == 0)
			$pg['z'][] = $myrow['costs'];
		$i++;
		if(count($panels) == 0 || in_array('table', $panels))
			end_row();
	}
	if(count($panels) == 0 || in_array('table', $panels))
		end_table();

	if(count($panels) > 0) {
		foreach($panels as $key=>$val) {
			if($val == 'table')
				echo "</div>";
		}
		if(in_array('bar-chart', $panels)) {
			foreach($panels as $key=>$val) {
				if($val == 'bar-chart')
				echo "<div class='tab-pane fade in active' id='stock_top_".$type."_panel$key'>";
			}
			if($type == 0)
				double_bar($today, false, $pg, "stock_top_$type");
			else
				single_bar($today, false, $pg, "stock_top_$type");
			echo "</div>";
		}

		if(in_array('pie-chart', $panels)) {
			foreach($panels as $key=>$val) {
				if($val == 'pie-chart')
					echo "<div class='tab-pane fade' id='stock_top_".$type."_panel$key'>";
			}
			pie_chart($today, false, $pg, "stock_top_".$type."_pie");
			echo "</div>";
		}
	}
	echo "</div>"; // content
	echo "</div>";  // box
	return $title;
}

function dimension_top($today, $limit=10, $width="33", $panels=array()) {

	$begin = begin_fiscalyear();
	$begin1 = date2sql($begin);
	$today1 = date2sql($today);
	$sql = "SELECT SUM(-t.amount) AS total, d.reference, d.name FROM
		".TB_PREF."gl_trans AS t,".TB_PREF."dimensions AS d WHERE
		(t.dimension_id = d.id OR t.dimension2_id = d.id) AND
		t.tran_date >= '$begin1' AND t.tran_date <= '$today1' GROUP BY d.id ORDER BY total DESC LIMIT $limit";
	$result = db_query($sql, "Transactions could not be calculated");

	$title = sprintf(_("Top %s Dimensions in fiscal year"), $limit);

	$icons = array();
	foreach($panels as $val) {
		$icons[] = $val;
	}
	if(count($panels) > 1)
		display_title($title, "dim_top", $icons);
	else
		display_title($title, "dim_top");

	echo "<div class='widget_content tab-content'>";

	if(count($panels) > 0) {
		foreach($panels as $key=>$val) {
			if($val == 'table')
				echo "<div class='tab-pane fade' id='dim_top_panel$key'>";
		}
	}

	if(count($panels) == 0 || in_array('table', $panels)) {
		$th = array(_("Dimension"), _("Amount"));
		start_table(TABLESTYLE_NOBORDER, "width='$width%'");
		table_header($th);
	}
	$pg = array('x'=>array(), 'y'=>array());
	check_page_security('SA_DIMTRANSVIEW');
	$k = 0; //row colour counter
	$i = 0;
	while ($myrow = db_fetch($result)) {
		$name = $myrow['reference']." ".$myrow["name"];
		if(count($panels) == 0 || in_array('table', $panels)) {
			alt_table_row_color($k);
			label_cell($name);
			amount_cell($myrow['total']);
		}

		$pg['x'][] = $name; 
		$pg['y'][] = abs($myrow['total']);
		$i++;
		if(count($panels) == 0 || in_array('table', $panels))
			end_row();
	}
	if(count($panels) == 0 || in_array('table', $panels))
		end_table();
	if(count($panels) > 0) {
		foreach($panels as $key=>$val) {
			if($val == 'table')
				echo "</div>";
		}
		if(in_array('bar-chart', $panels)) {
			foreach($panels as $key=>$val) {
				if($val == 'bar-chart')
				echo "<div class='tab-pane fade in active' id='dim_top_panel$key'>";
			}
			single_bar($today, false, $pg, 'dim_top');
			echo "</div>";
		}

		if(in_array('pie-chart', $panels)) {
			foreach($panels as $key=>$val) {
				if($val == 'pie-chart')
					echo "<div class='tab-pane fade' id='dim_top_panel$key'>";
			}
			pie_chart($today, false, $pg, 'dim_top_pie');
			echo "</div>";
		}
	}
	echo "</div>"; // content
	echo "</div>";  // box
	return $title;
}

function gl_top($today, $width="33", $panels=array()) {
	$begin = begin_fiscalyear();
	$begin1 = date2sql($begin);
	$today1 = date2sql($today);
	$sql = "SELECT SUM(amount) AS total, c.class_name, c.ctype FROM
		".TB_PREF."gl_trans,".TB_PREF."chart_master AS a, ".TB_PREF."chart_types AS t, 
		".TB_PREF."chart_class AS c WHERE
		account = a.account_code AND a.account_type = t.id AND t.class_id = c.cid
		AND IF(c.ctype > 3, tran_date >= '$begin1', tran_date >= '0000-00-00') 
		AND tran_date <= '$today1' GROUP BY c.cid ORDER BY c.cid"; 
	$result = db_query($sql, "Transactions could not be calculated");

	$title = _("Class Balances");

	$icons = array();
	foreach($panels as $val) {
		$icons[] = $val;
	}
	if(count($panels) > 1)
		display_title($title, "gl_top", $icons);
	else
		display_title($title, "gl_top");

	echo "<div class='widget_content tab-content'>";

	if(count($panels) > 0) {
		foreach($panels as $key=>$val) {
			if($val == 'table')
				echo "<div class='tab-pane fade' id='gl_top_panel$key'>";
		}
	}
	if(count($panels) == 0 || in_array('table', $panels))
		start_table(TABLESTYLE_NOBORDER, "width='$width%'");

	check_page_security('SA_GLANALYTIC');
	$pg = array('x'=>array(), 'y'=>array());
	$i = 0;
	$total = 0;
	while ($myrow = db_fetch($result))
	{
		if ($myrow['ctype'] > 3)
		{
			$total += $myrow['total'];
			$myrow['total'] = -$myrow['total'];
			$pg['x'][] = $myrow['class_name']; 
			$pg['y'][] = abs($myrow['total']);	
			$i++;
		}
		if(count($panels) == 0 || in_array('table', $panels))
			label_row($myrow['class_name'], number_format2($myrow['total'], user_price_dec()), "class='' style='font-weight:bold;'", "style='font-weight:bold;' align=right");
	}
	$calculated = _("Return");

	if(count($panels) == 0 || in_array('table', $panels)) {
		label_row("&nbsp;", "");
		label_row($calculated, number_format2(-$total, user_price_dec()), "class='' style='font-weight:bold;'", "style='font-weight:bold;' align=right");
	}
	$pg['x'][$i] = $calculated; 
	$pg['y'][$i] = -$total;

	if(count($panels) == 0 || in_array('table', $panels))
		end_table();
	if(count($panels) > 0) {
		foreach($panels as $key=>$val) {
			if($val == 'table')
				echo "</div>";
		}
		if(in_array('bar-chart', $panels)) {
			foreach($panels as $key=>$val) {
				if($val == 'bar-chart')
				echo "<div class='tab-pane fade' id='gl_top_panel$key'>";
			}
			single_bar($today, false, $pg, 'gl_top');
			echo "</div>";
		}

		if(in_array('pie-chart', $panels)) {
			foreach($panels as $key=>$val) {
				if($val == 'pie-chart')
					echo "<div class='tab-pane fade in active' id='gl_top_panel$key'>";
			}
			pie_chart($today, false, $pg, 'gl_top_pie');
			echo "</div>";
		}
	}
	echo "</div>"; //content
	echo "</div>"; //box
	
	return $title;
}

function cash_flow($today, $width="33", $panels=array()) {

    $begin = date2sql(begin_fiscalyear());
    $date = begin_month($today);
	$date = add_months($date, -11);
	list($da, $mo, $yr) = explode_date_to_dmy($date);

	$date13 = date('Y-m-d',mktime(0,0,0,$mo+12,1,$yr));
	$date12 = date('Y-m-d',mktime(0,0,0,$mo+11,1,$yr));
	$date11 = date('Y-m-d',mktime(0,0,0,$mo+10,1,$yr));
	$date10 = date('Y-m-d',mktime(0,0,0,$mo+9,1,$yr));
	$date09 = date('Y-m-d',mktime(0,0,0,$mo+8,1,$yr));
	$date08 = date('Y-m-d',mktime(0,0,0,$mo+7,1,$yr));
	$date07 = date('Y-m-d',mktime(0,0,0,$mo+6,1,$yr));
	$date06 = date('Y-m-d',mktime(0,0,0,$mo+5,1,$yr));
	$date05 = date('Y-m-d',mktime(0,0,0,$mo+4,1,$yr));
	$date04 = date('Y-m-d',mktime(0,0,0,$mo+3,1,$yr));
	$date03 = date('Y-m-d',mktime(0,0,0,$mo+2,1,$yr));
	$date02 = date('Y-m-d',mktime(0,0,0,$mo+1,1,$yr));
	$date01 = date('Y-m-d',mktime(0,0,0,$mo,1,$yr));

  	  	$sql = "SELECT SUM(CASE WHEN trans_date >= '$date01' AND trans_date < '$date02' THEN amount ELSE 0 END) AS '$date01',
		   		SUM(CASE WHEN trans_date >= '$date02' AND trans_date < '$date03' THEN amount ELSE 0 END) AS '$date02',
		   		SUM(CASE WHEN trans_date >= '$date03' AND trans_date < '$date04' THEN amount ELSE 0 END) AS '$date03',
		   		SUM(CASE WHEN trans_date >= '$date04' AND trans_date < '$date05' THEN amount ELSE 0 END) AS '$date04',
		   		SUM(CASE WHEN trans_date >= '$date05' AND trans_date < '$date06' THEN amount ELSE 0 END) AS '$date05',
		   		SUM(CASE WHEN trans_date >= '$date06' AND trans_date < '$date07' THEN amount ELSE 0 END) AS '$date06',
		   		SUM(CASE WHEN trans_date >= '$date07' AND trans_date < '$date08' THEN amount ELSE 0 END) AS '$date07',
		   		SUM(CASE WHEN trans_date >= '$date08' AND trans_date < '$date09' THEN amount ELSE 0 END) AS '$date08',
		   		SUM(CASE WHEN trans_date >= '$date09' AND trans_date < '$date10' THEN amount ELSE 0 END) AS '$date09',
		   		SUM(CASE WHEN trans_date >= '$date10' AND trans_date < '$date11' THEN amount ELSE 0 END) AS '$date10',
		   		SUM(CASE WHEN trans_date >= '$date11' AND trans_date < '$date12' THEN amount ELSE 0 END) AS '$date11',
		   		SUM(CASE WHEN trans_date >= '$date12' AND trans_date < '$date13' THEN amount ELSE 0 END) AS '$date12'
    			FROM ".TB_PREF."bank_trans";

	$result = db_fetch(db_query($sql, "Transactions could not be calculated"));
    
    $title = _("Cash Flow");
	$icons = array();
	foreach($panels as $val) {
		$icons[] = $val;
	}
	if(count($panels) > 1)
	    display_title($title, "cash_flow", $icons);
	else
	    display_title($title, "cash_flow");

	echo "<div class='widget_content tab-content'>";

	if(count($panels) > 0) {
		foreach($panels as $key=>$val) {
			if($val == 'table')
				echo "<div class='tab-pane fade' id='cash_flow_panel$key'>";
		}
	}
	if(count($panels) == 0 || in_array('table', $panels)) {
		
		$th = array();
		foreach($result as $col=>$val) {
            if(!is_numeric($col))
                $th[] = date('M', strtotime($col))." ".date('Y', strtotime($col));
	    }
	
		start_table(TABLESTYLE_NOBORDER, "width='$width%'");
		table_header($th);
	}
	check_page_security('SA_GLANALYTIC');
	$k = 0; //row colour counter

	$cols_arr = array();
	$val_arr = array();
	$pg = array('x'=>array(), 'y'=>array());
	alt_table_row_color($k);
	foreach($result as $col=>$val) {
		if(count($panels) == 0 || in_array('table', $panels)) {
			
            if(!is_numeric($col))
			    amount_cell($val);
		}

		if(!is_numeric($col)) {
		    $cols_arr[] = _(date('M', strtotime($col)))." ".date('Y', strtotime($col)); 
		    $val_arr[] = $val;
        }
	}
	for($i=0; $i < count($cols_arr); $i++) {
	    if($i % 2 == 0)
	        $pg['x'][] = $cols_arr[$i]; 
		else
            $pg['x'][] = '';
	    $pg['y'] = $val_arr;
    }
	if(count($panels) == 0 || in_array('table', $panels)) {
	    end_row();
		end_table();
	}

	if(count($panels) > 0) {
		foreach($panels as $key=>$val) {
			if($val == 'table')
				echo "</div>";
		}
		if(in_array('line-chart', $panels)) {
			foreach($panels as $key=>$val) {
				if($val == 'line-chart')
					echo "<div class='tab-pane fade in active' id='cash_flow_panel$key'>";
			}
			line_chart($today, false, $pg, "cash_flow_line");
			echo "</div>";
		}
		if(in_array('bar-chart', $panels)) {
			foreach($panels as $key=>$val) {
				if($val == 'bar-chart')
				echo "<div class='tab-pane fade' id='cash_flow_panel$key'>";
			}
			single_bar($today, false, $pg, "cash_flow_bar");
			echo "</div>";
		}
	}
	echo "</div>"; // content
	echo "</div>";  // box
	return $title;
}

function gl_performance($today, $width="33", $weeks=5)
{
	global $SysPrefs;
	$pg = array('x'=>array(), 'y'=>array(), 'z'=>array());

	$begin = begin_fiscalyear();
	$begin1 = date2sql($begin);
	$today1 = date2sql($today);
	$sep = $SysPrefs->dateseps[user_date_sep()];
	$sql = "SELECT week_name, sales, costs 
		FROM(SELECT DATE_FORMAT(tran_date, '%Y{$sep}%u') AS week_name, 
			SUM(IF(c.ctype = 4, amount * -1, 0)) AS sales, 
			SUM(IF(c.ctype = 6, amount, 0)) AS costs FROM 
			".TB_PREF."gl_trans, ".TB_PREF."chart_master AS a, ".TB_PREF."chart_types AS t, 
			".TB_PREF."chart_class AS c WHERE(c.ctype = 4 OR c.ctype = 6) 
			AND account = a.account_code AND a.account_type = t.id AND t.class_id = c.cid 
			AND tran_date >= '$begin1' AND tran_date <= '$today1' 
			GROUP BY week_name ORDER BY week_name DESC LIMIT 0, $weeks) b 
		GROUP BY week_name ORDER BY week_name ASC";
	$result = db_query($sql, "Transactions could not be calculated");
	$title = sprintf(_("Last %s weeks Performance"), $weeks);
	check_page_security('SA_GLANALYTIC');

	while ($myrow = db_fetch($result)) {

		$pg['x'][] = $myrow['week_name']; 
		$pg['y'][] = $myrow['sales'];
		$pg['z'][] = $myrow['costs'];
	}	
	
	double_bar($today, $title, $pg, 'gl_perfomance');
}

function source_graphic($today, $title, $x_axis, $pg, $graphic1, $graphic2=null, $type=2, $img_width = '425px')
{
	if (count($pg->y) ==0 || (count($pg->y) == 1 && $pg->y[0] == 0))
		return;
	display_title("$title ($today)", "graph_box");	
	//$pg->title     = $title . " - " . $today;
	$pg->axis_x    = $x_axis;
	$pg->axis_y    = _("Amount");
	$pg->graphic_1 = $graphic1;
	if ($graphic2 != null)
		$pg->graphic_2 = $graphic2;
	$pg->type      = $type;
	$pg->skin      = 1;
	$pg->built_in  = false;
	$filename = company_path(). "/pdf_files/". random_id().".png";
	
	echo "<div class='widget_content'>";
	$pg->display($filename);
	start_table(TABLESTYLE_NOBORDER, "width=100%");
	start_row("class='graph_row'");
	echo "<td>";
	echo "<img src='$filename' border='0' width='$img_width' alt='$title'>";
	echo "</td>";
	end_row();
	end_table();
	echo "</div>"; //content
	echo "</div>"; //box
}

function double_bar($today, $title=false, $pg, $id='')
{
	if($title) {
		display_title("$title ($today)", "graph_box");	
		echo "<div class='widget_content'>";
	}

	echo "<div class='ct-chart double-bar-chart' id='$id' style='position:relative'></div>";

	if($title) {
		echo "</div>"; //content
		echo "</div>"; //box
	}
	
	echo "<script>
	var data_$id = {
    	labels: ".json_encode($pg['x']).",
  		series: [
    		".json_encode($pg['y']).",
    		".json_encode($pg['z'])."
  		]
	};

	var options_$id = {
  		seriesBarDistance: 10,
  		plugins: [Chartist.plugins.tooltip()],
  		axisY: {
  			labelInterpolationFnc: function(value) {
  				if(value > 10000)
  					return value / 1000 + 'k';
  				return value;
  			}
  		}
	};

	var responsiveOptions_$id = [
  		['screen and (max-width: 640px)', {
    		seriesBarDistance: 5,
    		axisX: {
      			labelInterpolationFnc: function (value) {return value[0];}
    		}
  		}]
	];

	new Chartist.Bar('#$id', data_$id, options_$id, responsiveOptions_$id);";
	echo "</script>";
}

function single_bar($today, $title=false, $pg, $id=''){
	if($title) {
		display_title("$title($today)", "graph_box");
		echo "<div class='widget_content'>";
	}
	echo "<div class='ct-chart' id='$id' style='position:relative'></div>";

	if($title) {
		echo "</div>"; //content
		echo "</div>"; //box
	}

	echo "<script>
	var data_$id = {
  		labels: ".json_encode($pg['x']).",
  		series: ".json_encode($pg['y'])."
	};
	var option_$id = {
  		distributeSeries: true,
  		axisY: {
  			labelInterpolationFnc: function(value) {
  				if(value > 1000000 || value < -1000000)
  					return value / 1000000 + 'm';
  				if(value > 10000 || value < -10000)
  					return value / 1000 + 'k'
  				return value;
  			}
  		},
		plugins: [Chartist.plugins.tooltip()]
	};

	new Chartist.Bar('#$id', data_$id, option_$id);
	</script>";
}

function line_chart($today, $title=false, $pg, $id='') {
	if($title) {
		display_title("$title($today)", "graph_box");
		echo "<div class='widget_content'>";
	}
	echo "<div class='ct-chart ct-line-chart' id='$id' style='position:relative'></div>";

	if($title) {
		echo "</div>"; //content
		echo "</div>"; //box
	}

	echo "<script>
	var data_$id = {
  		labels: ".json_encode($pg['x']).",
  		series: [".json_encode($pg['y'])."]
	};
	var option_$id = {
  		distributeSeries: true,
  		showArea: true,
  		axisY: {
  			labelInterpolationFnc: function(value) {
  				if(value > 1000000 || value < -1000000)
  					return value / 1000000 + 'm';
  				if(value > 10000 || value < -10000)
  					return value / 1000 + 'k'
  				return value;
  			}
  		},
		plugins: [Chartist.plugins.tooltip()]
	};

	new Chartist.Line('#$id', data_$id, option_$id);
	</script>";
}

function pie_chart($today, $title=false, $pg=null, $id='')
{
	if($title) {
		display_title("$title($today)", "graph_box");
		echo "<div class='widget_content'>";
	}
	
	echo "<div class='ct-chart ct-pie-chart' id='$id' style='position:relative'></div>";
	echo "<ul class='ct-legend' id='$id"."_legend"."'></ul>";

	if($title) {
		echo "</div>"; //content
		echo "</div>"; //box
	}
	echo "<script>";
	echo "var data_$id = {series: ".json_encode($pg['y'])."};

	var class_arr_$id = ".json_encode($pg['x']).";
	
	var options_$id = {
  		labelInterpolationFnc: function(value) {
  			function sum(a, b) { return a + b };
    		return Math.round(value / data_$id.series.reduce(sum) * 100) + '%';
  		},
  		plugins: [Chartist.plugins.tooltip()]
	};

	var responsiveOptions_$id = [
  		['', {
    		labelOffset: 40,
    		chartPadding: 20,
			labelDirection: 'explode'
  		}],
		//['screen and (max-width: 480px)', {
			//labelOffset: 20
		//}]
	];

	new Chartist.Pie('#$id', data_$id, options_$id, responsiveOptions_$id);

	var legend_$id = $('#$id' + '_legend');
	$.each(class_arr_$id, function(i, val) {
		var listItem = $('<li />')
		.addClass('ct-series-' + i)
        .html(class_arr_".$id."[i])
        .appendTo(legend_$id);
    });";
	echo "</script>";
}

function customer_trans($today)
{
	$today = date2sql($today);

	$sql = "SELECT trans.trans_no, trans.reference,	trans.tran_date, trans.due_date, debtor.debtor_no, 
		debtor.name, branch.br_name, debtor.curr_code,
		(trans.ov_amount + trans.ov_gst + trans.ov_freight 
			+ trans.ov_freight_tax + trans.ov_discount)	AS total,  
		(trans.ov_amount + trans.ov_gst + trans.ov_freight 
			+ trans.ov_freight_tax + trans.ov_discount - trans.alloc) AS remainder,
		DATEDIFF('$today', trans.due_date) AS days 	
		FROM ".TB_PREF."debtor_trans as trans, ".TB_PREF."debtors_master as debtor, 
			".TB_PREF."cust_branch as branch
		WHERE debtor.debtor_no = trans.debtor_no AND trans.branch_code = branch.branch_code
			AND trans.type = ".ST_SALESINVOICE." AND (trans.ov_amount + trans.ov_gst + trans.ov_freight 
			+ trans.ov_freight_tax + trans.ov_discount - trans.alloc) > ".FLOAT_COMP_DELTA." 
			AND DATEDIFF('$today', trans.due_date) > 0 ORDER BY days DESC";
	$result = db_query($sql);
	$title = db_num_rows($result) . _(" overdue Sales Invoices");
  	display_title($title, "cus_trans");
	$th = array("#", _("Ref."), _("Date"), _("Due Date"), _("Customer"), _("Branch"), _("Currency"), 
		_("Total"), _("Remainder"),	_("Days"));
		
	echo "<div class='widget_content'>";
	start_table(TABLESTYLE_NOBORDER, "width=100%");
	table_header($th);
  	$k = 0; //row colour counter
	while ($myrow = db_fetch($result))
	{
		alt_table_row_color($k);
		label_cell(get_trans_view_str(ST_SALESINVOICE, $myrow["trans_no"]));
		label_cell($myrow['reference']);
		label_cell(sql2date($myrow['tran_date']));
		label_cell(sql2date($myrow['due_date']));
		$name = $myrow["debtor_no"]." ".$myrow["name"];
		label_cell($name);
		label_cell($myrow['br_name']);
		label_cell($myrow['curr_code']);
		amount_cell($myrow['total']);
		amount_cell($myrow['remainder']);
		label_cell($myrow['days'], "align='right'");
		end_row();
	}
	end_table();
	echo "</div>"; //content
	echo "</div>"; //box
}

function calculate_next_invoice($myrow)
{
	if ($myrow["last_sent"] == '0000-00-00')
		$next = sql2date($myrow["begin"]);
	else
		$next = sql2date($myrow["last_sent"]);
	$next = add_months($next, $myrow['monthly']);
	$next = add_days($next, $myrow['days']);
	return add_days($next,-1);
}

function customer_recurrent_invoices($today)
{
	$result = get_recurrent_invoices($today);
	$title = _("Overdue Recurrent Invoices");
  	display_title($title, "cus_rec_inv");
	$th = array(_("Description"), _("Template No"),_("Customer"),_("Branch")."/"._("Group"),_("Next invoice"));
	
	echo "<div class='widget_content'>";
	start_table(TABLESTYLE_NOBORDER, "width=100%");
  	table_header($th);
	$k = 0;
	while ($myrow = db_fetch($result)) 
	{
		if (!$myrow['overdue'])
			continue;
		alt_table_row_color($k);

		label_cell($myrow["description"]);
		label_cell(get_customer_trans_view_str(ST_SALESORDER, $myrow["order_no"]));
		if ($myrow["debtor_no"] == 0)
		{
			label_cell("");

			label_cell(get_sales_group_name($myrow["group_no"]));
		}
		else
		{
			label_cell(get_customer_name($myrow["debtor_no"]));
			label_cell(get_branch_name($myrow['group_no']));
		}
		label_cell(calculate_next_invoice($myrow),  "align='center'");
		end_row();
	}
	end_table();
	echo "</div>"; //content
	echo "</div>"; //box
}

function supplier_trans($today)
{
	$today = date2sql($today);
	$sql = "SELECT trans.trans_no, trans.reference, trans.tran_date, trans.due_date, s.supplier_id, 
		s.supp_name, s.curr_code,
		(trans.ov_amount + trans.ov_gst + trans.ov_discount) AS total,  
		(trans.ov_amount + trans.ov_gst + trans.ov_discount - trans.alloc) AS remainder,
		DATEDIFF('$today', trans.due_date) AS days 	
		FROM ".TB_PREF."supp_trans as trans, ".TB_PREF."suppliers as s 
		WHERE s.supplier_id = trans.supplier_id
			AND trans.type = ".ST_SUPPINVOICE." AND (ABS(trans.ov_amount + trans.ov_gst + 
				trans.ov_discount) - trans.alloc) > ".FLOAT_COMP_DELTA."
			AND DATEDIFF('$today', trans.due_date) > 0 ORDER BY days DESC";
	$result = db_query($sql);
	$title = db_num_rows($result) . _(" overdue Purchase Invoices");
	display_title($title, "sup_trans");
	$th = array("#", _("Ref."), _("Date"), _("Due Date"), _("Supplier"), _("Currency"), _("Total"), 
		_("Remainder"),	_("Days"));
	echo "<div class='widget_content'>";
	start_table(TABLESTYLE_NOBORDER, "width=100%");
	table_header($th);
	$k = 0; //row colour counter
	while ($myrow = db_fetch($result))
	{
		alt_table_row_color($k);
		label_cell(get_trans_view_str(ST_SUPPINVOICE, $myrow["trans_no"]));
		label_cell($myrow['reference']);
		label_cell(sql2date($myrow['tran_date']));
		label_cell(sql2date($myrow['due_date']));
		$name = $myrow["supplier_id"]." ".$myrow["supp_name"];
		label_cell($name);
		label_cell($myrow['curr_code']);
		amount_cell($myrow['total']);
		amount_cell($myrow['remainder']);
		label_cell($myrow['days'], "align='right'");
		end_row();
	}
	end_table();
	echo "</div>"; // content
	echo "</div>";  // box
}

function bank_balance($today, $width)
{
	$today = date2sql($today);
	$sql = "SELECT bank_act, bank_account_name, bank_curr_code, SUM(amount) balance FROM ".TB_PREF."bank_trans bt 
	            INNER JOIN ".TB_PREF."bank_accounts ba ON bt.bank_act = ba.id
	            WHERE trans_date <= '$today'
	            AND inactive <> 1
	            GROUP BY bank_act, bank_account_name
				ORDER BY bank_account_name";
	$result = db_query($sql);
	$title = _("Bank Account Balances");
	
	display_title($title, "bank_balance");
	$th = array(_("Account"), _("Currency"), _("Balance"));
	echo "<div class='widget_content'>";
	start_table(TABLESTYLE_NOBORDER, "width='$width%'");
	table_header($th);
	$k = 0; //row colour counter
	while ($myrow = db_fetch($result))
	{
		alt_table_row_color($k);
		label_cell(viewer_link($myrow["bank_account_name"], 'gl/inquiry/bank_inquiry.php?bank_account='.$myrow["bank_act"]));
		label_cell($myrow["bank_curr_code"]);
		amount_cell($myrow['balance']);
		end_row();
	}
	end_table();
	echo "</div>"; // content
	echo "</div>";  // box
}