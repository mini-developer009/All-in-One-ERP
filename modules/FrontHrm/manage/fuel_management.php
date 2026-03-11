<?php
/*=================================================================\
|  COMPLETE VEHICLE FUEL TRACKING SYSTEM                           |
|  FrontAccounting HRM Module                                      |
|  Tabs: Dashboard | Fuel Log | Efficiency | Requisitions | Analytics |
\=================================================================*/
$path_to_root = '../../..';
$page_security = 'SA_ATTACHDOCUMENT';

include_once($path_to_root . '/modules/FrontHrm/includes/hrm_classes.inc');
include_once($path_to_root . '/includes/session.inc');
add_access_extensions();
include_once($path_to_root . '/includes/date_functions.inc');
include_once($path_to_root . '/includes/ui.inc');
include_once($path_to_root . '/includes/data_checks.inc');

$active_tab = isset($_GET['tab']) ? $_GET['tab'] : (isset($_POST['tab']) ? $_POST['tab'] : 'dashboard');

// =================================================================
// SHARED DB HELPERS
// =================================================================
function fuel_companies() {
    $sql = "SELECT MIN(id) as id, TRIM(name) as name
            FROM " . TB_PREF . "dimensions
            WHERE type_=2 AND closed=0 AND name!=''
            GROUP BY TRIM(name) ORDER BY name";
    $r = db_query($sql, "fuel_companies");
    $out = array();
    while ($row = db_fetch($r)) $out[] = $row;
    return $out;
}

function fuel_vehicles($company_id = '') {
    if (!empty($company_id)) {
        $sql = "SELECT v.id, v.reference
                FROM " . TB_PREF . "dimensions v
                INNER JOIN " . TB_PREF . "dimensions c ON TRIM(c.name)=TRIM(v.name)
                WHERE v.type_=2 AND v.closed=0 AND c.id=" . db_escape($company_id) . "
                ORDER BY v.reference";
    } else {
        $sql = "SELECT id, reference FROM " . TB_PREF . "dimensions
                WHERE type_=2 AND closed=0 ORDER BY reference";
    }
    $r = db_query($sql, "fuel_vehicles");
    $out = array();
    while ($row = db_fetch($r)) $out[] = $row;
    return $out;
}

function fuel_drivers() {
    $sql = "SELECT e.emp_id, CONCAT(e.emp_first_name,' ',e.emp_last_name) as name
            FROM " . TB_PREF . "employee e
            INNER JOIN " . TB_PREF . "position p ON p.position_id = e.position_id
            WHERE e.inactive=0 AND p.position_name LIKE '%Driver%'
            ORDER BY e.emp_first_name, e.emp_last_name";
    $r = db_query($sql, "fuel_drivers");
    $out = array();
    while ($row = db_fetch($r)) $out[] = $row;
    return $out;
}

function fuel_vehicle_company($vehicle_id) {
    $row = db_fetch(db_query(
        "SELECT MIN(c.id) as cid FROM " . TB_PREF . "dimensions v
         INNER JOIN " . TB_PREF . "dimensions c ON TRIM(c.name)=TRIM(v.name)
         WHERE v.id=" . db_escape($vehicle_id) . " AND v.type_=2 AND c.type_=2",
        "fvc"));
    return $row ? $row['cid'] : 0;
}

function fuel_recalc_efficiency($vehicle_id, $new_log_id, $new_odo, $new_qty, $new_cost, $new_date) {
    if ($new_odo <= 0) return;
    $prev = db_fetch(db_query(
        "SELECT odometer FROM " . TB_PREF . "vehicle_fuel_log
         WHERE vehicle_id=" . db_escape($vehicle_id) . " AND id<" . db_escape($new_log_id) . " AND odometer>0
         ORDER BY date DESC, id DESC LIMIT 1", "eff_prev"));
    if (!$prev || $prev['odometer'] <= 0 || $new_odo <= $prev['odometer']) return;
    $dist = $new_odo - $prev['odometer'];
    $kpl  = $new_qty > 0 ? round($dist / $new_qty, 4) : 0;
    $cpk  = $dist > 0 ? round($new_cost / $dist, 4) : 0;
    db_query("DELETE FROM " . TB_PREF . "fuel_efficiency WHERE fuel_log_id=" . db_escape($new_log_id), "eff_del");
    db_query("INSERT INTO " . TB_PREF . "fuel_efficiency
        (fuel_log_id,vehicle_id,date,distance_km,fuel_used,km_per_liter,cost_per_km)
        VALUES (" . db_escape($new_log_id) . "," . db_escape($vehicle_id) . ",'" . date2sql($new_date) . "',$dist," . db_escape($new_qty) . ",$kpl,$cpk)",
        "eff_ins");
}

// =================================================================
// REUSABLE NATIVE SELECT RENDERERS
// =================================================================
function fuel_csel($nm, $sel, $submit_on_change=false){
    $class = $submit_on_change ? "combo Js_submit" : "combo";
    echo "<select name='$nm' id='$nm' class='$class'>\n<option value=''>-- Company --</option>\n";
    $res = db_query("SELECT id, name FROM " . TB_PREF . "dimensions WHERE type_=1 AND closed=0 ORDER BY name", "companies");
    $selected = ($sel === null) ? get_post($nm) : $sel;
    while($c = db_fetch($res)){
        $s = ((string)$selected===(string)$c['id']) ? ' selected' : '';
        echo "<option value='{$c['id']}'$s>{$c['name']}</option>\n";
    }
    echo "</select>\n";
}

function fuel_vsel($nm, $sel, $company_id='', $submit_on_change=false){
    $class = $submit_on_change ? "combo Js_submit" : "combo";
    echo "<select name='$nm' id='$nm' class='$class'>\n<option value=''>-- Vehicle --</option>\n";
    
    if (!empty($company_id)) {
        $sql = "SELECT d.id, d.reference 
                FROM " . TB_PREF . "dimensions d 
                INNER JOIN " . TB_PREF . "asset_create ac ON (ac.registration_no = d.id OR ac.registration_no = d.reference)
                INNER JOIN " . TB_PREF . "bus_root br ON br.asset_create_id = ac.id 
                WHERE d.type_=2 AND d.closed=0 AND br.name_of_company = " . db_escape($company_id) . "
                ORDER BY d.reference";
    } else {
        $sql = "SELECT id, reference FROM " . TB_PREF . "dimensions WHERE type_=2 AND closed=0 ORDER BY reference";
    }
    
    $res = db_query($sql, "vehicles");
    $selected = ($sel === null) ? get_post($nm) : $sel;
    while($v = db_fetch($res)){
        $s = ((string)$selected===(string)$v['id']) ? ' selected' : '';
        echo "<option value='{$v['id']}'$s>{$v['reference']}</option>\n";
    }
    echo "</select>\n";
}

function fuel_dsel($nm, $sel){
    echo "<select name='$nm' id='$nm' class='combo'>\n<option value='0'>-- Driver --</option>\n";
    foreach(fuel_drivers() as $d){
        $s = ((string)$sel===(string)$d['emp_id']) ? ' selected' : '';
        echo "<option value='{$d['emp_id']}'$s>{$d['name']}</option>\n";
    }
    echo "</select>\n";
}

function fuel_ftsel($nm, $sel){
    echo "<select name='$nm' class='combo'>\n";
    foreach(array('Diesel','Petrol','CNG','Electric','Octane') as $f){
        $s = ($sel==$f) ? ' selected' : '';
        echo "<option$s>$f</option>\n";
    }
    echo "</select>\n";
}

// =================================================================
// TWO-WAY FILTER INTERCEPT LOGIC
// =================================================================
global $Ajax;

function handle_two_way_filter($comp_field, $veh_field) {
    global $Ajax;
    if (list_updated($comp_field)) {
        $_POST[$veh_field] = '';
        $Ajax->activate($veh_field);
        $Ajax->activate('trans_tbl');
    }
    if (list_updated($veh_field)) {
        $veh_id = get_post($veh_field);
        if (!empty($veh_id)) {
            $v_row = db_fetch(db_query("SELECT TRIM(name) as name FROM ".TB_PREF."dimensions WHERE id=".db_escape($veh_id)));
            if ($v_row) {
                $c_row = db_fetch(db_query("SELECT MIN(id) as cid FROM ".TB_PREF."dimensions WHERE type_=2 AND TRIM(name)=".db_escape($v_row['name'])));
                $_POST[$comp_field] = $c_row['cid'];
                $Ajax->activate($comp_field);
            }
        }
        $Ajax->activate('trans_tbl');
    }
}

if ($active_tab == 'fuellog' || $active_tab == 'requisitions' || $active_tab == 'efficiency') {
    handle_two_way_filter('f_company_id', 'f_vehicle_id');
}
if ($active_tab == 'fuellog' || $active_tab == 'requisitions') {
    if (empty($_POST['View'])) handle_two_way_filter('company_id', 'vehicle_id');
}
if ($active_tab == 'analytics') {
    if (list_updated('f_company_id')) $Ajax->activate('data_grid');
}

// =================================================================
// PAGE HEADER
// =================================================================
$js = user_use_date_picker() ? get_js_date_picker() : '';
page(_('Vehicle Fuel Tracking System'), false, false, '', $js);

$tabs = array(
    'dashboard'   => 'Dashboard',
    'fuellog'     => 'Fuel Log',
    'efficiency'  => 'Efficiency',
    'requisitions'=> 'Requisitions',
    'analytics'   => 'Analytics',
);
echo "<div style='margin-bottom:0;border-bottom:3px solid #1a6ba0;'>";
foreach($tabs as $k=>$lbl){
    $a=($active_tab==$k);
    $st=$a?'background:#1a6ba0;color:#fff;border-color:#1a6ba0':'background:#eef3f8;color:#1a6ba0;border:1px solid #c5d9e8;border-bottom:none';
    echo "<a href='fuel_management.php?tab=$k' style='display:inline-block;padding:9px 22px;margin-right:2px;text-decoration:none;border-radius:5px 5px 0 0;font-weight:bold;font-size:13px;$st'>$lbl</a>";
}
echo "</div><div style='background:#fff;border:1px solid #c5d9e8;border-top:none;padding:18px;margin-bottom:15px;'>\n";

// =================================================================
// GRID HELPER FUNCTIONS
// =================================================================
function _fl_edit($r)  { return button('Edit'.$r['id'],'Edit','Edit',ICON_EDIT); }
function _fl_del($r)   { return button('Delete'.$r['id'],'Delete','Delete',ICON_DELETE); }
function _rq_edit($r)  { return button('Edit'.$r['id'],'Edit','Edit',ICON_EDIT); }
function _rq_del($r)   { return button('Delete'.$r['id'],'Delete','Delete',ICON_DELETE); }

// =================================================================
// TAB: DASHBOARD
// =================================================================
if($active_tab=='dashboard'){
    $today = date('Y-m-d');
    $ms    = date('Y-m-01');
    $ys    = date('Y-01-01');

    $r1  = db_fetch(db_query("SELECT COUNT(*) c, COALESCE(SUM(quantity),0) q, COALESCE(SUM(cost),0) x FROM " . TB_PREF . "vehicle_fuel_log WHERE date>='$ms'","d1"));
    $r2  = db_fetch(db_query("SELECT COALESCE(SUM(cost),0) x FROM " . TB_PREF . "vehicle_fuel_log WHERE date='$today'","d2"));
    $r3  = db_fetch(db_query("SELECT COUNT(*) c FROM " . TB_PREF . "fuel_requisition WHERE status=0","d3"));
    $r4  = db_fetch(db_query("SELECT COALESCE(SUM(cost),0) x FROM " . TB_PREF . "vehicle_fuel_log WHERE date>='$ys'","d4"));

    $cards=array(
        array('Month Fills',         $r1['c'],                        '#2980b9','#eaf4fb'),
        array('Month Fuel (Ltrs)',   number_format($r1['q'],1),       '#27ae60','#eafaf1'),
        array('Month Cost',          'Tk '.number_format($r1['x'],0),'#e67e22','#fef9e7'),
        array('Today Cost',          'Tk '.number_format($r2['x'],0),'#8e44ad','#f5eef8'),
        array('Pending Requisitions',$r3['c'],                        '#c0392b','#fdedec'),
        array('Year-to-Date Cost',   'Tk '.number_format($r4['x'],0),'#16a085','#e8f8f5'),
    );
    echo "<div style='display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:22px;'>";
    foreach($cards as $c){
        echo "<div style='border-radius:8px;padding:18px 20px;background:{$c[3]};border-left:5px solid {$c[2]};box-shadow:0 1px 4px rgba(0,0,0,.08);'>
            <div style='font-size:26px;font-weight:bold;color:{$c[2]};'>{$c[1]}</div>
            <div style='color:#555;font-size:12px;margin-top:4px;text-transform:uppercase;letter-spacing:.5px;'>{$c[0]}</div>
        </div>";
    }
    echo "</div>";

    echo "<div style='display:grid;grid-template-columns:1fr 1fr;gap:18px;'>";

    // Top consumers
    echo "<div style='border:1px solid #dde;border-radius:6px;padding:15px;'>
        <div style='font-weight:bold;color:#1a6ba0;font-size:14px;border-bottom:1px solid #eee;padding-bottom:8px;margin-bottom:10px;'>Top Fuel Consumers — This Month</div>
        <table width='100%' style='border-collapse:collapse;font-size:13px;'>
        <tr style='background:#f0f5fa;'><th style='padding:6px 8px;text-align:left;'>Vehicle</th><th style='padding:6px 8px;text-align:left;'>Company</th><th style='padding:6px 8px;text-align:right;'>Ltrs</th><th style='padding:6px 8px;text-align:right;'>Cost</th></tr>";
    $res=db_query("SELECT d.reference, dc.name cname, SUM(fl.quantity) q, SUM(fl.cost) x
        FROM " . TB_PREF . "vehicle_fuel_log fl
        LEFT JOIN " . TB_PREF . "dimensions d  ON d.id=fl.vehicle_id
        LEFT JOIN " . TB_PREF . "dimensions dc ON dc.id=fl.company_id
        WHERE fl.date>='$ms' GROUP BY fl.vehicle_id ORDER BY q DESC LIMIT 8","tc");
    $alt=false;
    while($row=db_fetch($res)){
        $bg=$alt?'#f9f9f9':'#fff'; $alt=!$alt;
        echo "<tr style='background:$bg;'>
            <td style='padding:6px 8px;font-weight:bold;'>{$row['reference']}</td>
            <td style='padding:6px 8px;color:#666;font-size:12px;'>{$row['cname']}</td>
            <td style='padding:6px 8px;text-align:right;'>".number_format($row['q'],1)."</td>
            <td style='padding:6px 8px;text-align:right;color:#e67e22;font-weight:bold;'>".number_format($row['x'],0)."</td>
        </tr>";
    }
    echo "</table></div>";

    // Recent fuel entries
    echo "<div style='border:1px solid #dde;border-radius:6px;padding:15px;'>
        <div style='font-weight:bold;color:#1a6ba0;font-size:14px;border-bottom:1px solid #eee;padding-bottom:8px;margin-bottom:10px;'>Recent Fuel Entries</div>
        <table width='100%' style='border-collapse:collapse;font-size:13px;'>
        <tr style='background:#f0f5fa;'><th style='padding:6px 8px;text-align:left;'>Date</th><th style='padding:6px 8px;text-align:left;'>Vehicle</th><th style='padding:6px 8px;text-align:left;'>Driver</th><th style='padding:6px 8px;text-align:right;'>Ltrs</th><th style='padding:6px 8px;text-align:right;'>Cost</th></tr>";
    $res=db_query("SELECT fl.date, d.reference, fl.quantity, fl.cost,
        CONCAT(e.emp_first_name,' ',e.emp_last_name) dname, fl.fuel_type
        FROM " . TB_PREF . "vehicle_fuel_log fl
        LEFT JOIN " . TB_PREF . "dimensions d ON d.id=fl.vehicle_id
        LEFT JOIN " . TB_PREF . "employee e ON e.emp_id=fl.driver_id
        ORDER BY fl.date DESC, fl.id DESC LIMIT 10","re");
    $alt=false;
    while($row=db_fetch($res)){
        $bg=$alt?'#f9f9f9':'#fff'; $alt=!$alt;
        echo "<tr style='background:$bg;'>
            <td style='padding:6px 8px;'>".sql2date($row['date'])."</td>
            <td style='padding:6px 8px;font-weight:bold;'>{$row['reference']}</td>
            <td style='padding:6px 8px;color:#666;font-size:12px;'>".($row['dname']?:'-')."</td>
            <td style='padding:6px 8px;text-align:right;'>".number_format($row['quantity'],1)."</td>
            <td style='padding:6px 8px;text-align:right;color:#e67e22;font-weight:bold;'>".number_format($row['cost'],0)."</td>
        </tr>";
    }
    echo "</table></div>";
    echo "</div>";

    if($r3['c'] > 0){
        echo "<div style='background:#fdf3cd;border:1px solid #ffc107;border-radius:6px;padding:12px 16px;margin-top:16px;color:#856404;font-weight:bold;'>
            &#9888; {$r3['c']} fuel requisition(s) are pending approval.
            <a href='fuel_management.php?tab=requisitions&f_status_filter=0' style='color:#1a6ba0;margin-left:10px;'>Review now &rarr;</a>
        </div>";
    }
}

// =================================================================
// TAB: FUEL LOG
// =================================================================
elseif($active_tab=='fuellog'){
    simple_page_mode(true);

    if(isset($_GET['LogId'])) {
        $selected_id = $_GET['LogId'];
        $Mode = 'Edit';
    }

    if($Mode=='ADD_ITEM'||$Mode=='UPDATE_ITEM'){
        $err=0;
        $qty  = input_num('quantity',0);
        $cost = input_num('cost',0);
        $odo  = input_num('odometer',0);

        if(empty($_POST['company_id']))  {display_error('Select a company from the top filters.'); $err=1;}
        elseif(empty($_POST['vehicle_id'])){display_error('Select a vehicle from the top filters.'); $err=1;}
        elseif($qty<=0)                  {display_error('Quantity must be > 0.'); set_focus('quantity'); $err=1;}
        elseif($cost<=0)                 {display_error('Cost must be > 0.'); set_focus('cost'); $err=1;}
        elseif(!is_date($_POST['date'])) {display_error('Invalid date.'); set_focus('date'); $err=1;}

        if(!$err){
            $dt   = date2sql($_POST['date']);
            $vid  = (int)$_POST['vehicle_id'];
            $cid  = (int)$_POST['company_id'];
            $did  = (int)get_post('driver_id',0);
            $ft   = db_escape(get_post('fuel_type','Diesel'));
            $stn  = db_escape(get_post('station_name',''));
            $fb   = db_escape(get_post('filled_by',''));
            $memo = db_escape(get_post('memo',''));

            if($Mode=='ADD_ITEM'){
                db_query("INSERT INTO " . TB_PREF . "vehicle_fuel_log
                    (company_id,vehicle_id,date,quantity,cost,odometer,memo,fuel_type,station_name,filled_by,driver_id)
                    VALUES ($cid,$vid,'$dt'," . db_escape($qty) . "," . db_escape($cost) . ",$odo,$memo,$ft,$stn,$fb,$did)", "add_fuellog");
                
                $newid = db_insert_id(); 
                fuel_recalc_efficiency($vid, $newid, $odo, $qty, $cost, $_POST['date']);
                display_notification("Fuel log entry added.");
            } else {
                db_query("UPDATE " . TB_PREF . "vehicle_fuel_log SET
                    company_id=$cid,vehicle_id=$vid,date='$dt',
                    quantity=" . db_escape($qty) . ",cost=" . db_escape($cost) . ",odometer=$odo,
                    memo=$memo,fuel_type=$ft,station_name=$stn,filled_by=$fb,driver_id=$did
                    WHERE id=" . db_escape($selected_id), "upd_fuellog");
                fuel_recalc_efficiency($vid, $selected_id, $odo, $qty, $cost, $_POST['date']);
                display_notification("Fuel log updated.");
            }
            $Mode='RESET';
        }
        refresh_pager('fl_tbl');
    }
    if($Mode=='Delete'){
        db_query("DELETE FROM " . TB_PREF . "vehicle_fuel_log WHERE id=" . db_escape($selected_id),"del_fl");
        db_query("DELETE FROM " . TB_PREF . "fuel_efficiency WHERE fuel_log_id=" . db_escape($selected_id),"del_eff");
        display_notification("Entry deleted.");
        $Mode='RESET';
    }
    if($Mode=='RESET'){
        unset($_POST['quantity'],$_POST['cost'],$_POST['odometer'],$_POST['memo'],$_POST['fuel_type'],$_POST['station_name'],$_POST['filled_by'],$_POST['driver_id']);
        $_POST['date']=Today(); $selected_id=-1;
    }

    start_form(true);
    hidden('tab','fuellog');

    start_table(TABLESTYLE_NOBORDER);
    start_row();
    echo "<td class='label'>" . _('Company:') . "</td>";
    fuel_csel('f_company_id', get_post('f_company_id'), true);
    echo "<td class='label'>" . _('Vehicle:') . "</td>";
    fuel_vsel('f_vehicle_id', get_post('f_vehicle_id'), get_post('f_company_id'), true);
    date_cells(_('From:'), 'from_date', '', null, 0, 0, -365, null, true);
    date_cells(_('To:'), 'to_date', '', null, 0, 0, 1, null, true);
    submit_cells('Search','Search','','','default');
    end_row();
    end_table(1);

    // Data Grid
    div_start('trans_tbl');
    $w="1=1";
    $sv=get_post('f_vehicle_id'); $sc=get_post('f_company_id');
    if(!empty($sv))      $w.=" AND fl.vehicle_id=".db_escape($sv);
    elseif(!empty($sc))  $w.=" AND fl.company_id=".db_escape($sc);
    else                 $w.=" AND 1=0"; 
    
    $fd=get_post('from_date'); $td=get_post('to_date');
    if($fd) $w.=" AND fl.date>='".date2sql($fd)."'";
    if($td) $w.=" AND fl.date<='".date2sql($td)."'";

    $sql="SELECT fl.id, fl.date, d.reference as vref, dc.name as cname,
            CONCAT(e.emp_first_name,' ',e.emp_last_name) as dname,
            fl.quantity, fl.cost, fl.odometer, fl.fuel_type, fl.station_name, fl.memo
          FROM " . TB_PREF . "vehicle_fuel_log fl
          LEFT JOIN " . TB_PREF . "dimensions d  ON d.id=fl.vehicle_id
          LEFT JOIN " . TB_PREF . "dimensions dc ON dc.id=fl.company_id
          LEFT JOIN " . TB_PREF . "employee e    ON e.emp_id=fl.driver_id
          WHERE $w ORDER BY fl.date DESC, fl.id DESC";

    $cols=array(
        'Date'       =>array('name'=>'date', 'type'=>'date', 'ord'=>'desc'),
        'Vehicle'    =>array('name'=>'vref'),
        'Company'    =>array('name'=>'cname'),
        'Driver'     =>array('name'=>'dname'),
        'Type'       =>array('name'=>'fuel_type'),
        'Qty (L)'    =>array('name'=>'quantity', 'align'=>'right'),
        'Cost (Tk)'  =>array('name'=>'cost', 'align'=>'right'),
        'Odometer'   =>array('name'=>'odometer', 'align'=>'right'),
        'Station'    =>array('name'=>'station_name'),
        ''           =>array('insert'=>true,'fun'=>'_fl_edit','align'=>'center'),
        ' '          =>array('insert'=>true,'fun'=>'_fl_del', 'align'=>'center'),
    );
    $tbl=&new_FrontHrm_pager('fl_tbl',$sql,$cols);
    $tbl->width='100%';
    display_FrontHrm_pager($tbl);
    div_end();
    br();

    // Entry Form
    if(empty($_POST['View'])) {
        start_table(TABLESTYLE2);
        if($selected_id != -1 && $Mode == 'Edit') {
            $row=db_fetch(db_query("SELECT * FROM " . TB_PREF . "vehicle_fuel_log WHERE id=".db_escape($selected_id),"edit_fl"));
            $_POST['date']         = sql2date($row['date']);
            $_POST['quantity']     = price_format($row['quantity']);
            $_POST['cost']         = price_format($row['cost']);
            $_POST['odometer']     = $row['odometer'];
            $_POST['memo']         = $row['memo'];
            $_POST['fuel_type']    = $row['fuel_type'];
            $_POST['station_name'] = $row['station_name'];
            $_POST['filled_by']    = $row['filled_by'];
            $_POST['driver_id']    = $row['driver_id'];
            $_POST['company_id']   = $row['company_id'];
            $_POST['vehicle_id']   = $row['vehicle_id'];
            hidden('selected_id',$selected_id);
            label_row('Log ID:',$selected_id);
        } elseif(!isset($_POST['date'])) $_POST['date']=Today();

        start_row();
        echo "<td class='label'>Company: *</td>"; fuel_csel('company_id', get_post('company_id'), true);
        echo "<td class='label'>Vehicle: *</td>"; fuel_vsel('vehicle_id', get_post('vehicle_id'), get_post('company_id'), true);
        end_row();

        start_row();
        echo "<td class='label'>Driver:</td><td>";
        echo "<select name='driver_id' class='combo'><option value='0'>-- Driver --</option>";
        foreach(fuel_drivers() as $d){
            $s = (get_post('driver_id') == $d['emp_id']) ? ' selected' : '';
            echo "<option value='{$d['emp_id']}'$s>{$d['name']}</option>";
        }
        echo "</select></td>";
        date_cells('Date of Refueling: *', 'date');
        end_row();
        
        start_row();
        amount_cells('Fuel Qty (Ltrs): *', 'quantity', null, 'combo', '', 2);
        amount_cells('Total Cost (Tk): *', 'cost', null, 'combo', '', 2);
        end_row();
        
        start_row();
        text_cells('Odometer (km):', 'odometer', null, 15);
        echo "<td class='label'>Fuel Type:</td><td>";
        echo "<select name='fuel_type' class='combo'>";
        foreach(array('Diesel','Petrol','CNG','Electric','Octane') as $f){
            $s = (get_post('fuel_type', 'Diesel') == $f) ? ' selected' : '';
            echo "<option$s>$f</option>";
        }
        echo "</select></td>";
        end_row();

        start_row();
        text_cells('Station Name:', 'station_name', null, 30);
        text_cells('Filled By:', 'filled_by', null, 30);
        end_row();

        textarea_row('Memo:', 'memo', null, 35, 3);
        end_table(1);

        submit_add_or_update_center($selected_id == -1, '', 'process');
    } 
    end_form();
}

// =================================================================
// TAB: EFFICIENCY (With Actual Vs Target Variance Editing!)
// =================================================================
elseif($active_tab=='efficiency'){
    simple_page_mode(true);

    // Save benchmark targets to database
    if (isset($_POST['save_target'])) {
        $vid = (int)$_POST['target_vehicle_id'];
        $t_tkm = input_num('target_total_km', 0);
        $t_akpl = input_num('target_avg_kpl', 0);
        $t_mink = input_num('target_min_kpl', 0);
        $t_maxk = input_num('target_max_kpl', 0);
        $t_cpk = input_num('target_cpk', 0);

        $sql = "REPLACE INTO " . TB_PREF . "vehicle_expected_efficiency
                (vehicle_id, target_total_km, target_avg_kpl, target_min_kpl, target_max_kpl, target_cpk)
                VALUES ($vid, $t_tkm, $t_akpl, $t_mink, $t_maxk, $t_cpk)";
        db_query($sql, "Could not save targets");
        display_notification(_("Vehicle efficiency targets saved successfully."));
    }

    $sc=get_post('f_company_id'); $sv=get_post('f_vehicle_id');
    $fd=get_post('from_date');  $td=get_post('to_date');

    start_form(false);
    hidden('tab','efficiency');

    start_table(TABLESTYLE_NOBORDER);
    start_row();
    echo "<td class='label'>Company:</td>"; fuel_csel('f_company_id', $sc, true);
    echo "<td class='label'>Vehicle:</td>"; fuel_vsel('f_vehicle_id', $sv, $sc, true);
    date_cells('From:','from_date','',null,0,0,-365,null,true);
    date_cells('To:','to_date','',null,0,0,1,null,true);
    submit_cells('Search','Search','','','default');
    end_row();
    end_table(1);

    div_start('trans_tbl');
    $w="1=1";
    if(!empty($sv))     $w.=" AND fl.vehicle_id=".db_escape($sv);
    elseif(!empty($sc)) $w.=" AND fl.company_id=".db_escape($sc);
    if($fd) $w.=" AND fl.date>='".date2sql($fd)."'";
    if($td) $w.=" AND fl.date<='".date2sql($td)."'";

    $sql="SELECT d.reference, dc.name cname, COUNT(fl.id) fills,
            SUM(ef.distance_km) tkm, SUM(fl.quantity) tfl, SUM(fl.cost) tcost,
            AVG(ef.km_per_liter) akpl, AVG(ef.cost_per_km) acpk,
            MIN(ef.km_per_liter) minkpl, MAX(ef.km_per_liter) maxkpl, fl.vehicle_id,
            COALESCE(ve.target_total_km, 0) as t_tkm,
            COALESCE(ve.target_avg_kpl, 0) as t_akpl,
            COALESCE(ve.target_min_kpl, 0) as t_mink,
            COALESCE(ve.target_max_kpl, 0) as t_maxk,
            COALESCE(ve.target_cpk, 0) as t_cpk
          FROM " . TB_PREF . "vehicle_fuel_log fl
          LEFT JOIN " . TB_PREF . "fuel_efficiency ef ON ef.fuel_log_id=fl.id
          LEFT JOIN " . TB_PREF . "dimensions d  ON d.id=fl.vehicle_id
          LEFT JOIN " . TB_PREF . "dimensions dc ON dc.id=fl.company_id
          LEFT JOIN " . TB_PREF . "vehicle_expected_efficiency ve ON ve.vehicle_id=fl.vehicle_id
          WHERE $w GROUP BY fl.vehicle_id ORDER BY fills DESC";

    echo "<div style='font-weight:bold;color:#1a6ba0;font-size:14px;border-bottom:1px solid #eee;padding-bottom:6px;margin-bottom:12px;'>Vehicle Efficiency Summary (Actual vs Target)</div>";
    echo "<table width='100%' style='border-collapse:collapse;font-size:13px;'>
    <tr style='background:#1a6ba0;color:#fff;font-weight:bold;'>
        <td style='padding:9px;'>Vehicle</td><td style='padding:9px;'>Company</td>
        <td style='padding:9px;text-align:right;'>Fills</td>
        <td style='padding:9px;text-align:right;'>Total KM</td>
        <td style='padding:9px;text-align:right;'>Avg KM/L</td>
        <td style='padding:9px;text-align:right;'>Min KM/L</td>
        <td style='padding:9px;text-align:right;'>Max KM/L</td>
        <td style='padding:9px;text-align:right;'>Cost/KM</td>
        <td style='padding:9px;text-align:right;'>Total Cost</td>
        <td style='padding:9px;text-align:center;'>Action</td>
    </tr>";
    
    // Variance renderer function
    function render_variance($actual, $target, $is_cost = false) {
        $actual = (float)$actual; $target = (float)$target;
        if ($target == 0) return "<div style='font-weight:bold; color:#555'>" . number_format($actual, 2) . "</div><div style='font-size:10px; color:#888;'>Tgt: Set Target</div>";
        
        $color = '#555';
        if ($is_cost) {
            if ($actual > $target * 1.1) $color = '#e74c3c'; // Over cost limit (bad)
            elseif ($actual < $target) $color = '#27ae60';   // Under cost limit (good)
        } else {
            if ($actual < $target * 0.9) $color = '#e74c3c'; // Low efficiency (fuel cut off)
            elseif ($actual >= $target) $color = '#27ae60';  // High efficiency
        }

        return "<div style='font-weight:bold; color:$color'>" . number_format($actual, 2) . "</div>
                <div style='font-size:10px; color:#888;'>Tgt: " . number_format($target, 2) . "</div>";
    }

    $alt=false; $res=db_query($sql,"eff_sum");
    if(db_num_rows($res) == 0) {
        echo "<tr><td colspan='10' style='padding:15px;text-align:center;color:#888;'>No data available for the selected filters.</td></tr>";
    } else {
        while($row=db_fetch($res)){
            $bg=$alt?'#f9f9f9':'#fff'; $alt=!$alt;
            
            echo "<tr style='background:$bg; border-bottom:1px solid #eee;'>
                <td style='padding:7px 9px;font-weight:bold;'><a href='fuel_management.php?tab=efficiency&f_vehicle_id={$row['vehicle_id']}&f_company_id=".fuel_vehicle_company($row['vehicle_id'])."' style='color:#1a6ba0;'>{$row['reference']}</a></td>
                <td style='padding:7px 9px;color:#555;font-size:12px;'>{$row['cname']}</td>
                <td style='padding:7px 9px;text-align:right;'>{$row['fills']}</td>
                <td style='padding:7px 9px;text-align:right;'>".render_variance($row['tkm'], $row['t_tkm'])."</td>
                <td style='padding:7px 9px;text-align:right;'>".render_variance($row['akpl'], $row['t_akpl'])."</td>
                <td style='padding:7px 9px;text-align:right;'>".render_variance($row['minkpl'], $row['t_mink'])."</td>
                <td style='padding:7px 9px;text-align:right;'>".render_variance($row['maxkpl'], $row['t_maxk'])."</td>
                <td style='padding:7px 9px;text-align:right;'>".render_variance($row['acpk'], $row['t_cpk'], true)."</td>
                <td style='padding:7px 9px;text-align:right;font-weight:bold;color:#e67e22; vertical-align:top;'>".number_format((float)$row['tcost'],0)."</td>
                <td style='padding:7px 9px;text-align:center; vertical-align:top;'>
                    <a href='fuel_management.php?tab=efficiency&TargetId={$row['vehicle_id']}' style='color:#e67e22; font-weight:bold; font-size:11px; border:1px solid #e67e22; padding:2px 5px; border-radius:3px; text-decoration:none;'>Set Targets</a>
                </td>
            </tr>";
        }
    }
    echo "</table>";

    // Target Editing Form
    $target_id = isset($_GET['TargetId']) ? (int)$_GET['TargetId'] : -1;
    if ($target_id != -1) {
        $veh_name = db_fetch(db_query("SELECT reference FROM ".TB_PREF."dimensions WHERE id=$target_id"))['reference'];
        $row = db_fetch(db_query("SELECT * FROM ".TB_PREF."vehicle_expected_efficiency WHERE vehicle_id=$target_id"));

        $_POST['target_total_km'] = price_format($row ? $row['target_total_km'] : 0);
        $_POST['target_avg_kpl'] = price_format($row ? $row['target_avg_kpl'] : 0);
        $_POST['target_min_kpl'] = price_format($row ? $row['target_min_kpl'] : 0);
        $_POST['target_max_kpl'] = price_format($row ? $row['target_max_kpl'] : 0);
        $_POST['target_cpk'] = price_format($row ? $row['target_cpk'] : 0);

        echo "<div style='background:#fdf3cd;border:1px solid #ffc107;border-radius:6px;padding:14px;margin-top:20px;'>";
        echo "<div style='font-weight:bold;color:#856404;font-size:14px;margin-bottom:12px;'>Set Benchmark Targets for: $veh_name</div>";

        start_table(TABLESTYLE2);
        hidden('target_vehicle_id', $target_id);
        start_row();
        amount_cells('Target Total KM:', 'target_total_km', null, 'combo', null, 2);
        amount_cells('Target Avg KM/L:', 'target_avg_kpl', null, 'combo', null, 2);
        end_row();
        start_row();
        amount_cells('Target Min KM/L:', 'target_min_kpl', null, 'combo', null, 2);
        amount_cells('Target Max KM/L:', 'target_max_kpl', null, 'combo', null, 2);
        end_row();
        start_row();
        amount_cells('Target Cost/KM (Tk):', 'target_cpk', null, 'combo', null, 4);
        end_row();
        end_table(1);

        submit_center('save_target', 'Save Vehicle Targets', true, '', 'default');
        echo "</div>";
    }

    div_end();
    end_form();
}

// =================================================================
// TAB: REQUISITIONS
// =================================================================
elseif($active_tab=='requisitions'){
    simple_page_mode(true);

    if(isset($_GET['LogId'])) {
        $selected_id = $_GET['LogId'];
        $Mode = 'Edit';
    }

    if($Mode=='ADD_ITEM'||$Mode=='UPDATE_ITEM'){
        $err=0;
        $qty  = input_num('quantity',0);
        $uprc = input_num('unit_price',0);
        $tot  = round($qty * $uprc, 2);

        if(empty($_POST['company_id']))    {display_error('Select a company in the Entry form.');  set_focus('company_id'); $err=1;}
        elseif(empty($_POST['vehicle_id'])){display_error('Select a vehicle in the Entry form.');  set_focus('vehicle_id'); $err=1;}
        elseif($qty<=0)                    {display_error('Quantity must be > 0.'); set_focus('quantity'); $err=1;}
        elseif(!is_date($_POST['req_date'])){display_error('Invalid date.');      set_focus('req_date');   $err=1;}

        if(!$err){
            $dt   = date2sql($_POST['req_date']);
            $vid  = (int)$_POST['vehicle_id'];
            $cid  = (int)$_POST['company_id'];
            $did  = (int)get_post('driver_id',0);
            $ft   = db_escape(get_post('fuel_type','Diesel'));
            $stn  = db_escape(get_post('station_name',''));
            $appr = db_escape(get_post('approved_by',''));
            $memo = db_escape(get_post('memo',''));
            $stat = (int)get_post('status',0);

            if($Mode=='ADD_ITEM'){
                db_query("INSERT INTO " . TB_PREF . "fuel_requisition
                    (req_date,vehicle_id,company_id,driver_id,fuel_type,quantity,unit_price,total_cost,station_name,approved_by,status,memo)
                    VALUES ('$dt',$vid,$cid,$did,$ft," . db_escape($qty) . "," . db_escape($uprc) . "," . db_escape($tot) . ",$stn,$appr,$stat,$memo)",
                    "add_req");
                display_notification("Requisition created successfully.");
            } else {
                db_query("UPDATE " . TB_PREF . "fuel_requisition SET
                    req_date='$dt',vehicle_id=$vid,company_id=$cid,driver_id=$did,
                    fuel_type=$ft,quantity=" . db_escape($qty) . ",unit_price=" . db_escape($uprc) . ",total_cost=" . db_escape($tot) . ",
                    station_name=$stn,approved_by=$appr,status=$stat,memo=$memo
                    WHERE id=".db_escape($selected_id),"upd_req");
                display_notification("Requisition updated successfully.");
            }
            $Mode='RESET';
        }
        refresh_pager('rq_tbl');
    }
    if($Mode=='Delete'){
        db_query("DELETE FROM " . TB_PREF . "fuel_requisition WHERE id=".db_escape($selected_id),"del_req");
        display_notification("Deleted.");
        $Mode='RESET';
    }
    if($Mode=='RESET'){
        unset($_POST['quantity'],$_POST['unit_price'],$_POST['station_name'],$_POST['approved_by'],$_POST['memo'],$_POST['driver_id']);
        $_POST['req_date']=Today(); $_POST['status']='0'; $selected_id=-1;
    }

    $sc=get_post('f_company_id'); $sv=get_post('f_vehicle_id'); $sf=get_post('f_status_filter');

    start_form(true);
    hidden('tab','requisitions');

    start_table(TABLESTYLE_NOBORDER);
    start_row();
    echo "<td class='label'>Company:</td>"; fuel_csel('f_company_id', $sc, true);
    echo "<td class='label'>Vehicle:</td>"; fuel_vsel('f_vehicle_id', $sv, $sc, true);
    echo "<td class='label'>Status:</td><td>
        <select name='f_status_filter' class='combo Js_submit'>
            <option value=''>All</option>
            <option value='0'".($sf==='0'?' selected':'').">Pending</option>
            <option value='1'".($sf==='1'?' selected':'').">Approved</option>
            <option value='2'".($sf==='2'?' selected':'').">Rejected</option>
        </select></td>";
    date_cells('From:','from_date','',null,0,0,-365,null,true);
    date_cells('To:','to_date','',null,0,0,1,null,true);
    submit_cells('Search','Search','','','default');
    end_row();
    end_table(1);

    div_start('trans_tbl');
    $w="1=1";
    if(!empty($sv))     $w.=" AND rq.vehicle_id=".db_escape($sv);
    elseif(!empty($sc)) $w.=" AND rq.company_id=".db_escape($sc);
    if($sf!=='')        $w.=" AND rq.status=".db_escape($sf);
    $fd=get_post('from_date'); $td_=get_post('to_date');
    if($fd) $w.=" AND rq.req_date>='".date2sql($fd)."'";
    if($td_)$w.=" AND rq.req_date<='".date2sql($td_)."'";

    $sql="SELECT rq.*, d.reference vref, dc.name cname,
            CONCAT(e.emp_first_name,' ',e.emp_last_name) dname
          FROM " . TB_PREF . "fuel_requisition rq
          LEFT JOIN " . TB_PREF . "dimensions d  ON d.id=rq.vehicle_id
          LEFT JOIN " . TB_PREF . "dimensions dc ON dc.id=rq.company_id
          LEFT JOIN " . TB_PREF . "employee e    ON e.emp_id=rq.driver_id
          WHERE $w ORDER BY rq.req_date DESC, rq.id DESC";

    $cols=array(
        'Date'       =>array('name'=>'req_date',  'fun'=>'_rq_date','ord'=>'desc'),
        'Vehicle'    =>array('name'=>'vref'),
        'Company'    =>array('name'=>'cname'),
        'Driver'     =>array('name'=>'dname'),
        'Fuel Type'  =>array('name'=>'fuel_type'),
        'Qty (L)'    =>array('name'=>'quantity',  'fun'=>'_rq_qty', 'align'=>'right'),
        'Total Cost' =>array('name'=>'total_cost','fun'=>'_rq_tot', 'align'=>'right'),
        'Approved By'=>array('name'=>'approved_by'),
        'Status'     =>array('name'=>'status',    'fun'=>'_rq_stat','align'=>'center'),
        ''           =>array('insert'=>true,'fun'=>'_rq_edit','align'=>'center'),
        ' '          =>array('insert'=>true,'fun'=>'_rq_del', 'align'=>'center'),
    );
    $tbl=&new_FrontHrm_pager('rq_tbl',$sql,$cols);
    $tbl->width='100%';
    display_FrontHrm_pager($tbl);
    div_end();
    br();

    if(empty($_POST['View'])) {
        echo "<div style='background:#f8fbff;border:1px solid #c5d9e8;border-radius:6px;padding:14px;margin-top:10px;'>";
        echo "<div style='font-weight:bold;color:#1a6ba0;font-size:14px;margin-bottom:12px;border-bottom:1px solid #dde;padding-bottom:6px;'>".($selected_id==-1?'New Requisition':'Edit Requisition')."</div>";
        start_table(TABLESTYLE2);
        
        if($selected_id!=-1 && $Mode=='Edit'){
            $row=db_fetch(db_query("SELECT * FROM " . TB_PREF . "fuel_requisition WHERE id=".db_escape($selected_id),"edit_rq"));
            $_POST['req_date']     = sql2date($row['req_date']);
            $_POST['quantity']     = price_format($row['quantity']);
            $_POST['unit_price']   = price_format($row['unit_price']);
            $_POST['memo']         = $row['memo'];
            $_POST['fuel_type']    = $row['fuel_type'];
            $_POST['station_name'] = $row['station_name'];
            $_POST['approved_by']  = $row['approved_by'];
            $_POST['driver_id']    = $row['driver_id'];
            $_POST['company_id']   = $row['company_id'];
            $_POST['vehicle_id']   = $row['vehicle_id'];
            $_POST['status']       = $row['status'];
            hidden('selected_id',$selected_id);
            label_row('Requisition ID:',$selected_id);
        } elseif(!isset($_POST['req_date'])) { 
            $_POST['req_date']=Today(); $_POST['status']='0'; 
        }

        start_row();
        echo "<td class='label'>Company: *</td>"; fuel_csel('company_id', get_post('company_id'), true);
        echo "<td class='label'>Vehicle: *</td>"; fuel_vsel('vehicle_id', get_post('vehicle_id'), get_post('company_id'), true);
        end_row();

        start_row();
        echo "<td class='label'>Driver:</td><td>";
        echo "<select name='driver_id' class='combo'><option value='0'>-- Driver --</option>";
        foreach(fuel_drivers() as $d){
            $s = (get_post('driver_id') == $d['emp_id']) ? ' selected' : '';
            echo "<option value='{$d['emp_id']}'$s>{$d['name']}</option>";
        }
        echo "</select></td>";
        date_cells('Date: *', 'req_date');
        end_row();

        start_row();
        amount_cells('Qty (Ltrs): *', 'quantity', null, 'combo', '', 2);
        amount_cells('Unit Price/Ltr:', 'unit_price', null, 'combo', '', 2);
        end_row();

        start_row();
        echo "<td class='label'>Fuel Type:</td><td>";
        echo "<select name='fuel_type' class='combo'>";
        foreach(array('Diesel','Petrol','CNG','Electric','Octane') as $f){
            $s = (get_post('fuel_type', 'Diesel') == $f) ? ' selected' : '';
            echo "<option$s>$f</option>";
        }
        echo "</select></td>";
        echo "<td class='label'>Status:</td><td>
            <select name='status' class='combo'>
                <option value='0'".((string)get_post('status','0')==='0'?' selected':'').">Pending</option>
                <option value='1'".((string)get_post('status')==='1'?' selected':'').">Approved</option>
                <option value='2'".((string)get_post('status')==='2'?' selected':'').">Rejected</option>
            </select></td>";
        end_row();

        start_row();
        text_cells('Station Name:', 'station_name', null, 30, 100);
        text_cells('Approved By:', 'approved_by', null, 30, 100);
        end_row();
        
        textarea_row('Memo:', 'memo', null, 40, 2);
        end_table(1);

        submit_add_or_update_center($selected_id==-1,'','process');
        echo "</div>";
    }
    end_form();
}

// =================================================================
// TAB: ANALYTICS
// =================================================================
elseif($active_tab=='analytics'){
    start_form(false);
    hidden('tab','analytics');

    $sc   = get_post('f_company_id');
    $yr   = get_post('year_filter', date('Y'));
    $mo   = get_post('month_filter','');

    start_table(TABLESTYLE_NOBORDER);
    start_row();
    echo "<td class='label'>Company:</td>"; fuel_csel('f_company_id', $sc, true);
    echo "<td class='label'>Year:</td><td>
        <select name='year_filter' class='combo Js_submit'>"; 
    for($y=date('Y');$y>=date('Y')-3;$y--){
        $s=($yr==$y)?' selected':'';
        echo "<option value='$y'$s>$y</option>";
    }
    echo "</select></td><td class='label'>Month:</td><td>
        <select name='month_filter' class='combo Js_submit'>
        <option value=''>All Months</option>";
    $mnames=array('01'=>'January','02'=>'February','03'=>'March','04'=>'April','05'=>'May','06'=>'June',
                  '07'=>'July','08'=>'August','09'=>'September','10'=>'October','11'=>'November','12'=>'December');
    foreach($mnames as $mk=>$mv){
        $s=($mo==$mk)?' selected':'';
        echo "<option value='$mk'$s>$mv</option>";
    }
    echo "</select></td>";
    submit_cells('Search','Search','','','default');
    end_row();
    end_table(1);

    div_start('data_grid');
    $cw = !empty($sc) ? " AND fl.company_id=".db_escape($sc) : "";
    $yw = " AND YEAR(fl.date)=".db_escape($yr);
    $mw = !empty($mo) ? " AND MONTH(fl.date)=".db_escape((int)$mo) : "";

    // --- Monthly trend table ---
    echo "<div style='display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-bottom:20px;'>";

    echo "<div style='border:1px solid #dde;border-radius:6px;padding:15px;'>
        <div style='font-weight:bold;color:#1a6ba0;font-size:14px;border-bottom:1px solid #eee;padding-bottom:6px;margin-bottom:10px;'>Monthly Fuel Cost — $yr</div>
        <table width='100%' style='border-collapse:collapse;font-size:13px;'>
        <tr style='background:#f0f5fa;font-weight:bold;'>
            <td style='padding:7px 8px;'>Month</td>
            <td style='padding:7px 8px;text-align:right;'>Fills</td>
            <td style='padding:7px 8px;text-align:right;'>Qty (L)</td>
            <td style='padding:7px 8px;text-align:right;'>Cost (Tk)</td>
            <td style='padding:7px 8px;text-align:right;'>Vehicles</td>
        </tr>";
    $res=db_query("SELECT MONTH(fl.date) mo, COUNT(*) fills, SUM(fl.quantity) qty, SUM(fl.cost) cost, COUNT(DISTINCT fl.vehicle_id) vehs
        FROM " . TB_PREF . "vehicle_fuel_log fl
        WHERE 1=1 $cw $yw
        GROUP BY MONTH(fl.date) ORDER BY mo","mtrend");
    $mns=array(1=>'Jan',2=>'Feb',3=>'Mar',4=>'Apr',5=>'May',6=>'Jun',7=>'Jul',8=>'Aug',9=>'Sep',10=>'Oct',11=>'Nov',12=>'Dec');
    $alt=false; $grandq=0; $grandc=0;
    while($row=db_fetch($res)){
        $bg=$alt?'#f9f9f9':'#fff'; $alt=!$alt;
        $grandq+=$row['qty']; $grandc+=$row['cost'];
        echo "<tr style='background:$bg;'>
            <td style='padding:6px 8px;font-weight:bold;'>{$mns[$row['mo']]}</td>
            <td style='padding:6px 8px;text-align:right;'>{$row['fills']}</td>
            <td style='padding:6px 8px;text-align:right;'>".number_format($row['qty'],1)."</td>
            <td style='padding:6px 8px;text-align:right;font-weight:bold;color:#e67e22;'>".number_format($row['cost'],0)."</td>
            <td style='padding:6px 8px;text-align:right;'>{$row['vehs']}</td>
        </tr>";
    }
    echo "<tr style='background:#e8f4fd;font-weight:bold;border-top:2px solid #1a6ba0;'>
        <td style='padding:7px 8px;'>TOTAL</td><td></td>
        <td style='padding:7px 8px;text-align:right;'>".number_format($grandq,1)."</td>
        <td style='padding:7px 8px;text-align:right;color:#c0392b;'>".number_format($grandc,0)."</td>
        <td></td>
    </tr>";
    echo "</table></div>";

    // Company breakdown
    echo "<div style='border:1px solid #dde;border-radius:6px;padding:15px;'>
        <div style='font-weight:bold;color:#1a6ba0;font-size:14px;border-bottom:1px solid #eee;padding-bottom:6px;margin-bottom:10px;'>Cost by Company — $yr".($mo?" ({$mns[(int)$mo]})":'')."</div>
        <table width='100%' style='border-collapse:collapse;font-size:13px;'>
        <tr style='background:#f0f5fa;font-weight:bold;'>
            <td style='padding:7px 8px;'>Company</td>
            <td style='padding:7px 8px;text-align:right;'>Vehicles</td>
            <td style='padding:7px 8px;text-align:right;'>Qty (L)</td>
            <td style='padding:7px 8px;text-align:right;'>Cost (Tk)</td>
            <td style='padding:7px 8px;text-align:right;'>Share %</td>
        </tr>";
    $res=db_query("SELECT dc.name cname, COUNT(DISTINCT fl.vehicle_id) vehs, SUM(fl.quantity) qty, SUM(fl.cost) cost
        FROM " . TB_PREF . "vehicle_fuel_log fl
        LEFT JOIN " . TB_PREF . "dimensions dc ON dc.id=fl.company_id
        WHERE 1=1 $cw $yw $mw
        GROUP BY fl.company_id ORDER BY cost DESC","cbreak");
    $rows_c=array(); $totc=0;
    while($row=db_fetch($res)){ $rows_c[]=$row; $totc+=$row['cost']; }
    $alt=false;
    foreach($rows_c as $row){
        $bg=$alt?'#f9f9f9':'#fff'; $alt=!$alt;
        $pct=$totc>0?round($row['cost']/$totc*100,1):0;
        $bar=round($pct);
        echo "<tr style='background:$bg;'>
            <td style='padding:6px 8px;'>{$row['cname']}</td>
            <td style='padding:6px 8px;text-align:right;'>{$row['vehs']}</td>
            <td style='padding:6px 8px;text-align:right;'>".number_format($row['qty'],1)."</td>
            <td style='padding:6px 8px;text-align:right;font-weight:bold;color:#e67e22;'>".number_format($row['cost'],0)."</td>
            <td style='padding:6px 8px;text-align:right;'>
                <div style='background:#eee;border-radius:3px;height:14px;width:100%;position:relative;'>
                    <div style='background:#1a6ba0;width:$bar%;height:14px;border-radius:3px;'></div>
                    <span style='position:absolute;top:0;right:4px;font-size:11px;line-height:14px;font-weight:bold;'>$pct%</span>
                </div>
            </td>
        </tr>";
    }
    echo "</table></div>";
    echo "</div>"; 

    // Fuel type breakdown + driver analysis
    echo "<div style='display:grid;grid-template-columns:1fr 1fr;gap:18px;'>";

    echo "<div style='border:1px solid #dde;border-radius:6px;padding:15px;'>
        <div style='font-weight:bold;color:#1a6ba0;font-size:14px;border-bottom:1px solid #eee;padding-bottom:6px;margin-bottom:10px;'>Fuel Type Breakdown</div>
        <table width='100%' style='border-collapse:collapse;font-size:13px;'>
        <tr style='background:#f0f5fa;font-weight:bold;'>
            <td style='padding:7px 8px;'>Fuel Type</td>
            <td style='padding:7px 8px;text-align:right;'>Fills</td>
            <td style='padding:7px 8px;text-align:right;'>Total (L)</td>
            <td style='padding:7px 8px;text-align:right;'>Total Cost</td>
        </tr>";
    $res=db_query("SELECT fuel_type, COUNT(*) fills, SUM(quantity) qty, SUM(cost) cost
        FROM " . TB_PREF . "vehicle_fuel_log fl WHERE 1=1 $cw $yw $mw
        GROUP BY fuel_type ORDER BY cost DESC","ftb");
    $alt=false;
    while($row=db_fetch($res)){
        $bg=$alt?'#f9f9f9':'#fff'; $alt=!$alt;
        echo "<tr style='background:$bg;'>
            <td style='padding:6px 8px;font-weight:bold;'>{$row['fuel_type']}</td>
            <td style='padding:6px 8px;text-align:right;'>{$row['fills']}</td>
            <td style='padding:6px 8px;text-align:right;'>".number_format($row['qty'],1)."</td>
            <td style='padding:6px 8px;text-align:right;font-weight:bold;color:#e67e22;'>".number_format($row['cost'],0)."</td>
        </tr>";
    }
    echo "</table></div>";

    // Top drivers by cost
    echo "<div style='border:1px solid #dde;border-radius:6px;padding:15px;'>
        <div style='font-weight:bold;color:#1a6ba0;font-size:14px;border-bottom:1px solid #eee;padding-bottom:6px;margin-bottom:10px;'>Top Drivers by Fuel Cost</div>
        <table width='100%' style='border-collapse:collapse;font-size:13px;'>
        <tr style='background:#f0f5fa;font-weight:bold;'>
            <td style='padding:7px 8px;'>Driver</td>
            <td style='padding:7px 8px;text-align:right;'>Fills</td>
            <td style='padding:7px 8px;text-align:right;'>Total (L)</td>
            <td style='padding:7px 8px;text-align:right;'>Total Cost</td>
        </tr>";
    $res=db_query("SELECT CONCAT(e.emp_first_name,' ',e.emp_last_name) dname,
            COUNT(*) fills, SUM(fl.quantity) qty, SUM(fl.cost) cost
        FROM " . TB_PREF . "vehicle_fuel_log fl
        LEFT JOIN " . TB_PREF . "employee e ON e.emp_id=fl.driver_id
        WHERE fl.driver_id>0 $cw $yw $mw
        GROUP BY fl.driver_id ORDER BY cost DESC LIMIT 10","tdrv");
    $alt=false;
    while($row=db_fetch($res)){
        $bg=$alt?'#f9f9f9':'#fff'; $alt=!$alt;
        echo "<tr style='background:$bg;'>
            <td style='padding:6px 8px;font-weight:bold;'>{$row['dname']}</td>
            <td style='padding:6px 8px;text-align:right;'>{$row['fills']}</td>
            <td style='padding:6px 8px;text-align:right;'>".number_format($row['qty'],1)."</td>
            <td style='padding:6px 8px;text-align:right;font-weight:bold;color:#e67e22;'>".number_format($row['cost'],0)."</td>
        </tr>";
    }
    echo "</table></div>";
    echo "</div>"; 
    div_end();
    end_form();
}

echo "</div>"; 
end_page();
?>