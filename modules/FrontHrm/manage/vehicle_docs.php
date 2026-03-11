<?php
/*=======================================================\
|                        FrontHrm                        |
|--------------------------------------------------------|
|   Vehicle Attach Documents & Inquiry                   |
\=======================================================*/

$path_to_root = '../../..';

if(isset($_GET['View'])) $_POST['View'] = $_GET['View'];
$page_security = empty($_POST['View']) ? 'SA_ATTACHDOCUMENT' : 'SA_EMPL';

include_once($path_to_root . '/modules/FrontHrm/includes/hrm_classes.inc');
include_once($path_to_root . '/includes/session.inc');
add_access_extensions();

include_once($path_to_root . '/includes/date_functions.inc');
include_once($path_to_root . '/includes/ui.inc');
include_once($path_to_root . '/includes/data_checks.inc');
include_once($path_to_root . '/admin/db/transactions_db.inc');

include_once($path_to_root . '/modules/FrontHrm/includes/frontHrm_db.inc');
include_once($path_to_root . '/modules/FrontHrm/includes/frontHrm_ui.inc');

// =======================================================================
// BULLETPROOF VEHICLE DATABASE FUNCTIONS
// =======================================================================
if (!function_exists('add_vehicle_document')) {
    function add_vehicle_document($vehicle_id, $company_id, $type_id, $doc_title, $issue_date, $expiry_date, $alert, $filename, $unique_name, $filesize, $filetype) {
        $issue_date = date2sql($issue_date);
        $expiry_date = date2sql($expiry_date);
        $sql = "INSERT INTO " . TB_PREF . "vehicle_docs (vehicle_id, company_id, type_id, description, issue_date, expiry_date, alert, filename, unique_name, filesize, filetype)
                VALUES (" . db_escape($vehicle_id) . ", " . db_escape($company_id) . ", " . db_escape($type_id) . ", " . db_escape($doc_title) . ", '$issue_date', '$expiry_date', " . db_escape($alert) . ", " . db_escape($filename) . ", " . db_escape($unique_name) . ", " . db_escape($filesize) . ", " . db_escape($filetype) . ")";
        db_query($sql, "The vehicle document could not be added");
    }

    function update_vehicle_document($id, $vehicle_id, $company_id, $type_id, $doc_title, $issue_date, $expiry_date, $alert, $filename, $unique_name, $filesize, $filetype) {
        $issue_date = date2sql($issue_date);
        $expiry_date = date2sql($expiry_date);
        $sql = "UPDATE " . TB_PREF . "vehicle_docs SET
                vehicle_id=" . db_escape($vehicle_id) . ", company_id=" . db_escape($company_id) . ", type_id=" . db_escape($type_id) . ", description=" . db_escape($doc_title) . ", issue_date='$issue_date', expiry_date='$expiry_date', alert=" . db_escape($alert);
        if ($filename != "") {
            $sql .= ", filename=" . db_escape($filename) . ", unique_name=" . db_escape($unique_name) . ", filesize=" . db_escape($filesize) . ", filetype=" . db_escape($filetype);
        }
        $sql .= " WHERE id=" . db_escape($id);
        db_query($sql, "The vehicle document could not be updated");
    }

    function delete_vehicle_document($id) {
        $sql = "DELETE FROM " . TB_PREF . "vehicle_docs WHERE id=" . db_escape($id);
        db_query($sql, "The vehicle document could not be deleted");
    }

    function get_vehicle_document($id) {
        $sql = "SELECT * FROM " . TB_PREF . "vehicle_docs WHERE id=" . db_escape($id);
        $result = db_query($sql, "could not get vehicle document");
        return db_fetch($result);
    }

    function get_sql_for_vehicle_documents($vehicle_id, $company_id, $type_id, $alert, $no_alert, $expired_from, $expired_to, $issue_from, $issue_to, $string) {
        $sql = "SELECT vd.id, vd.vehicle_id, vd.company_id, vd.type_id, vd.description as doc_title, vd.issue_date, vd.expiry_date, vd.alert, vd.filename, vd.filesize, vd.filetype,
                       dv.reference AS vehicle_ref, dc.name AS company_name
                FROM " . TB_PREF . "vehicle_docs vd
                LEFT JOIN " . TB_PREF . "dimensions dv ON dv.id = vd.vehicle_id AND dv.type_=2
                LEFT JOIN " . TB_PREF . "dimensions dc ON dc.id = vd.company_id AND dc.type_=1
                WHERE 1=1";

        if (empty($_POST['View'])) {
            // Entry mode: show nothing until a vehicle is selected
            if ($vehicle_id != null && $vehicle_id != '') {
                $sql .= " AND vd.vehicle_id = " . db_escape($vehicle_id);
            } else {
                $sql .= " AND 1=0";
            }
            if ($company_id != null && $company_id != '') $sql .= " AND vd.company_id = " . db_escape($company_id);
        } else {
            // View mode: apply all filters normally
            if ($vehicle_id != null && $vehicle_id != '') $sql .= " AND vd.vehicle_id = " . db_escape($vehicle_id);
            if ($company_id != null && $company_id != '') $sql .= " AND vd.company_id = " . db_escape($company_id);
        }
        if ($type_id != null && $type_id != '') $sql .= " AND vd.type_id = " . db_escape($type_id);
        if ($alert && !$no_alert) $sql .= " AND vd.alert = 1";
        elseif (!$alert && $no_alert) $sql .= " AND vd.alert = 0";
        if ($expired_from != null && $expired_from != '') $sql .= " AND vd.expiry_date >= '" . date2sql($expired_from) . "'";
        if ($expired_to != null && $expired_to != '') $sql .= " AND vd.expiry_date <= '" . date2sql($expired_to) . "'";
        if ($issue_from != null && $issue_from != '') $sql .= " AND vd.issue_date >= '" . date2sql($issue_from) . "'";
        if ($issue_to != null && $issue_to != '') $sql .= " AND vd.issue_date <= '" . date2sql($issue_to) . "'";
        if ($string != null && $string != '') $sql .= " AND vd.description LIKE " . db_escape("%$string%");

        return $sql;
    }
}

// =======================================================================
// CUSTOM DROPDOWN UI ELEMENTS
// =======================================================================
function vehicle_list_cells($label, $name, $selected_id=null, $first_option=false, $submit_on_change=false) {
    if ($label != null) echo "<td class='label'>$label</td>\n";
    echo "<td>";
    $onchange = $submit_on_change ? " onchange=\"this.form.submit();\"" : "";
    echo "<select name='$name' id='vehicle_id_select' class='combo'$onchange>";
    if ($first_option) echo "<option value=''>$first_option</option>";
    $res = db_query("SELECT id, reference FROM " . TB_PREF . "dimensions WHERE type_=2 AND closed=0 ORDER BY reference", "Could not get vehicles");
    while ($row = db_fetch($res)) {
        $sel = ($selected_id == $row['id']) ? "selected" : "";
        echo "<option value='" . $row['id'] . "' $sel>" . $row['reference'] . "</option>";
    }
    echo "</select>\n</td>\n";
}

function company_list_cells($label, $name, $selected_id=null, $first_option=false, $submit_on_change=false) {
    if ($label != null) echo "<td class='label'>$label</td>\n";
    echo "<td>";
    $onchange = $submit_on_change ? " onchange=\"this.form.submit();\"" : "";
    echo "<select name='$name' id='company_id_select' class='combo'$onchange>";
    if ($first_option) echo "<option value=''>$first_option</option>";
    $res = db_query("SELECT id, name FROM " . TB_PREF . "dimensions WHERE type_=1 AND closed=0 ORDER BY name", "Could not get companies");
    while ($row = db_fetch($res)) {
        $sel = ($selected_id == $row['id']) ? "selected" : "";
        echo "<option value='" . $row['id'] . "' $sel>" . $row['name'] . "</option>";
    }
    echo "</select>\n</td>\n";
}

// =======================================================================
// VIEW & DOWNLOAD HANDLERS
// =======================================================================
if(isset($_GET['vw'])) $view_id = $_GET['vw']; else $view_id = find_submit('view');
if($view_id != -1) {
    $row = get_vehicle_document($view_id);
    if($row['filename'] != "") {
        if(in_ajax()) $Ajax->popup($_SERVER['PHP_SELF'].'?vw='.$view_id);
        else {
            header('Content-type: ' . ($row['filetype'] ?: 'application/octet-stream'));
            header('Content-Length: ' . $row['filesize']);
            header('Content-Disposition: inline');
            echo file_get_contents(company_path().'/attachments/'.$row['unique_name']);
            exit();
        }
    }   
}

if(isset($_GET['dl'])) $download_id = $_GET['dl']; else $download_id = find_submit('download');
if($download_id != -1) {
    $row = get_vehicle_document($download_id);
    if($row['filename'] != '') {
        if(in_ajax()) $Ajax->redirect($_SERVER['PHP_SELF'].'?dl='.$download_id);
        else {
            header('Content-type: ' . ($row['filetype'] ?: 'application/octet-stream'));
            header('Content-Length: ' . $row['filesize']);
            header('Content-Disposition: attachment; filename='.$row['filename']);
            echo file_get_contents(company_path().'/attachments/'.$row['unique_name']);
            exit();
        }
    }   
}

$js = '';
if($SysPrefs->use_popup_windows) $js .= get_js_open_window(1200, 600);
if(user_use_date_picker()) $js .= get_js_date_picker();

page(_($help_context = empty($_POST['View']) ? 'Vehicle Attach Documents' : 'Vehicle Document Inquiry'), isset($_GET['VehicleId'])&&isset($_GET['DocId']), false, '', $js);

if(!db_has_doc_type()) {
    display_error(_('There are no <b>Document Types</b> defined in the system'));
    display_footer_exit();
}

simple_page_mode(true);

if(isset($_GET['VehicleId'])) $_POST['vehicle_id'] = $_GET['VehicleId'];
if(isset($_GET['DocId'])) {
    $selected_id = $_GET['DocId'];
    $Mode = 'Edit';
}

if($Mode == 'ADD_ITEM' || $Mode == 'UPDATE_ITEM') {
    $error = 0;
    
    $alert_val = check_value('alert') ? 1 : 0; 

    if(empty($_POST['vehicle_id'])) { display_error(_('Select a vehicle from the top menu.')); set_focus('vehicle_id'); $error = 1; }
    elseif(empty($_POST['company_id'])) { display_error(_('Select a company from the top menu.')); set_focus('company_id'); $error = 1; }
    elseif(empty($_POST['type_id'])) { display_error(_('Select a document type.')); set_focus('type_id'); $error = 1; }
    elseif(strlen(trim($_POST['doc_title'])) == 0) { display_error(_('The document title cannot be empty.')); set_focus('doc_title'); $error = 1; }
    elseif(date_comp($_POST['issue_date'], $_POST['expiry_date']) > 0) { display_error(_('Issue date cannot be after expiry date.')); set_focus('issue_date'); $error = 1; }
    elseif($Mode == 'ADD_ITEM' && (!isset($_FILES['filename']) || $_FILES['filename']['error'] == UPLOAD_ERR_NO_FILE)){ display_error(_('Select an attachment file.')); $error = 1; }
    elseif(isset($_FILES['filename']) && $_FILES['filename']['error'] == UPLOAD_ERR_INI_SIZE) { display_error(_('The file size is over the maximum allowed.')); $error = 1; }

    if($error == 0) {
        $dir = company_path().'/attachments';
        if(!file_exists($dir)) {
            mkdir ($dir,0777, true);
            $fp = fopen($dir.'/index.php', 'w');
            fwrite($fp, "<?php\nheader(\"Location: ../index.php\");\n");
            fclose($fp);
        }

        $upload_file = (isset($_FILES['filename']) && $_FILES['filename']['error'] == 0);

        if($Mode == 'ADD_ITEM') {
            $unique_name = random_id();
            $filename = $upload_file ? basename($_FILES['filename']['name']) : '';
            $filesize = $upload_file ? $_FILES['filename']['size'] : 0;
            $filetype = $upload_file ? $_FILES['filename']['type'] : '';
            if($upload_file) move_uploaded_file($_FILES['filename']['tmp_name'], $dir.'/'.$unique_name);
            add_vehicle_document($_POST['vehicle_id'], $_POST['company_id'], $_POST['type_id'], $_POST['doc_title'], $_POST['issue_date'], $_POST['expiry_date'], $alert_val, $filename, $unique_name, $filesize, $filetype);
            display_notification(_("Attachment has been inserted safely.")); 
        } else {
            $row = get_vehicle_document($selected_id);
            $unique_name = $row['unique_name'];
            $filename = $row['filename'];
            $filesize = $row['filesize'];
            $filetype = $row['filetype'];
            if ($upload_file) {
                if(!empty($unique_name) && file_exists($dir."/".$unique_name)) unlink($dir.'/'.$unique_name);
                $unique_name = random_id();
                $filename = basename($_FILES['filename']['name']);
                $filesize = $_FILES['filename']['size'];
                $filetype = $_FILES['filename']['type'];
                move_uploaded_file($_FILES['filename']['tmp_name'], $dir.'/'.$unique_name);
            }
            update_vehicle_document($selected_id, $_POST['vehicle_id'], $_POST['company_id'], $_POST['type_id'], $_POST['doc_title'], $_POST['issue_date'], $_POST['expiry_date'], $alert_val, $filename, $unique_name, $filesize, $filetype); 
            display_notification(_('Attachment has been updated safely.')); 
        }
        $Mode = 'RESET';
    }
    refresh_pager('trans_tbl');
}

if($Mode == 'Delete') {
    $row = get_vehicle_document($selected_id);
    $dir =  company_path().'/attachments';
    if (!empty($row['unique_name']) && file_exists($dir.'/'.$row['unique_name'])) unlink($dir."/".$row['unique_name']);
    delete_vehicle_document($selected_id);  
    display_notification(_('Attachment has been deleted.')); 
    $Mode = 'RESET';
}

if($Mode == 'RESET'){
    unset($_POST['doc_title'], $_POST['type_id'], $_POST['alert']);
    $selected_id = -1;
}

function viewing_controls() {
    global $selected_id;
    start_table(TABLESTYLE_NOBORDER);
    
    if(empty($_POST['View'])) {
        start_row();

        // COMPANY FIRST
        company_list_cells(null, 'company_id', get_post('company_id'), _('Select company'), true);

        // VEHICLE SECOND
        vehicle_list_cells(null, 'vehicle_id', get_post('vehicle_id'), _('Select vehicle'), false);

        if (list_updated('vehicle_id') || list_updated('company_id'))
            $selected_id = -1;

        end_row();
    }
    else {
        start_row();
        ref_cells(_('Enter search string:'), 'string', _('Enter fragment or leave empty'), null, null, true);

        // COMPANY FIRST
        company_list_cells(null, 'company_id', get_post('company_id'), _('All companies'), true);

        // VEHICLE SECOND
        vehicle_list_cells(null, 'vehicle_id', get_post('vehicle_id'), _('All vehicles'), true);

        doctype_list_cells(null, 'type_id', null, _('All document type'), true);
        check_cells(_('Alert:'), 'alert', null, true);
        check_cells(_('Not Alert:'), 'no_alert', null, true);

        end_row();

        start_row();
        date_cells(_('Expired:'), 'expired_from', '', null, 0, 0, -5, null, true);
        date_cells(_('To:'), 'expired_to', '', null, 0, 0, 5, null, true);
        date_cells(_('Issued:'), 'issue_from', '', null, 0, 0, -5, null, true);
        date_cells(_('To:'), 'issue_to', '', null, 0, 0, 5, null, true);
        submit_cells('Search', _('Search'), '', '', 'default');
        end_row();
    }

    end_table(1);
}

function type_name($row){ return get_doc_types($row['type_id'])['type_name']; }
function is_alert($row) { return $row['alert'] == 1 ? _('Yes') : ''; }
function vehicle_ref_col($row) { return isset($row['vehicle_ref']) ? $row['vehicle_ref'] : $row['vehicle_id']; }
function company_name_col($row) { return isset($row['company_name']) ? $row['company_name'] : $row['company_id']; }

function remaining_days($row) {
    if (empty($row['expiry_date']) || $row['expiry_date'] == '0000-00-00') return '';
    $exp = strtotime($row['expiry_date']);
    $now = strtotime(date('Y-m-d'));
    $days = round(($exp - $now) / 86400);
    if ($days < 0) return "<span style='color: #d9534f; font-weight: bold;'>Expired (" . abs($days) . " days)</span>";
    elseif ($days == 0) return "<span style='color: #f0ad4e; font-weight: bold;'>Expires Today</span>";
    else return "<span style='color: #26B99A; font-weight: bold;'>" . $days . " days</span>";
}

function edit_link($row){
    if(!empty($_POST['View'])) return viewer_link(_('Click to edit this document'), 'modules/FrontHrm/manage/vehicle_docs.php?VehicleId='.$row['vehicle_id'].'&DocId='.$row['id'], '', '', ICON_EDIT);
    else return button('Edit'.$row['id'], _('Edit'), _('Edit'), ICON_EDIT);
}
function view_link($row){ return button('view'.$row['id'], _('View'), _('View'), ICON_VIEW); }
function download_link($row){ return button('download'.$row['id'], _('Download'), _('Download'), ICON_DOWN); }
function delete_link($row) { return button('Delete'.$row['id'], _('Delete'), _('Delete'), ICON_DELETE); }
function check_expired($row) { return date_comp(Today(), sql2date($row['expiry_date'])) > 0 && $row['alert'] != 0; }
function check_warning($row) {
    $alert_from = get_alert_from($row['type_id'], sql2date($row['expiry_date']));
    return date_comp(Today(), $alert_from) > 0 && $row['alert'] != 0;
}

function display_rows() {
    $sql = get_sql_for_vehicle_documents(get_post('vehicle_id'), get_post('company_id'), get_post('type_id'), get_post('alert'), get_post('no_alert'), get_post('expired_from'), get_post('expired_to'), get_post('issue_from'), get_post('issue_to'), get_post('string'));
    
    $cols = array(
        _('Doc No') => array('name'=>'id', 'ord'=>'asc', 'align'=>'center'),
        _('Vehicle') => array('name'=>'vehicle_ref', 'ord'=>'asc', 'fun'=>'vehicle_ref_col'),
        _('Company') => array('name'=>'company_name', 'ord'=>'asc', 'fun'=>'company_name_col'),
        _('Document Type') => array('name'=>'type_id', 'ord'=>'asc', 'fun'=>'type_name'),
        _('Document Title') => array('name'=>'doc_title', 'ord'=>'asc'),
        _('Issue Date') => array('name'=>'issue_date','type'=>'date', 'ord'=>'desc'),
        _('Expiry Date') => array('name'=>'expiry_date','type'=>'date','ord'=>'desc'),
        _('Alert') => array('name'=>'alert', 'ord'=>'asc', 'fun'=>'is_alert','align'=>'center'),
        _('Remaining Days') => array('insert'=>true, 'fun'=>'remaining_days', 'align'=>'center'),
        _('Filename') => array('name'=>'filename', 'ord'=>'asc'),
        $cols[] = array('insert'=>true, 'fun'=>'edit_link','align'=>'center'),
        array('insert'=>true, 'fun'=>'view_link','align'=>'center'),
        array('insert'=>true, 'fun'=>'download_link','align'=>'center')
    );

    if(empty($_POST['View'])) $cols[] = array('insert'=>true, 'fun'=>'delete_link','align'=>'center');

    $table =& new_FrontHrm_pager('trans_tbl', $sql, $cols);
    $table->set_marker_warnings('check_warning', _('Marked rows are nearly expired'));
    $table->set_marker('check_expired', _('Marked rows are expired'));
    $table->width = 'auto';
    display_FrontHrm_pager($table);
}

// In View mode use plain POST so dropdown onchange submits correctly
start_form(empty($_POST['View']) ? true : false);

viewing_controls();
display_rows();
br();

if(empty($_POST['View'])) {
    start_table(TABLESTYLE2);

    if($selected_id != -1) {
        if($Mode == 'Edit') {
            $row = get_vehicle_document($selected_id);
            $_POST['type_id']  = $row['type_id'];
            $_POST['doc_title']  = $row['description'];
            $_POST['issue_date']  = sql2date($row['issue_date']);
            $_POST['expiry_date']  = sql2date($row['expiry_date']);
            $_POST['alert']  = $row['alert'];
            hidden('unique_name', $row['unique_name']);
        }   
        hidden('selected_id', isset($_GET['DocId']) ? $_GET['DocId'] : $selected_id);
        label_row(_('Document Number:'), '&nbsp;&nbsp;'.$selected_id);
    }

    doctype_list_row(_('Document type:'), 'type_id', null, _('Select document type'));
    text_row_ex(_('Document title:'), 'doc_title', 40);
    date_row(_('Issue date:'), 'issue_date');
    date_row(_('Expiry date:'), 'expiry_date');
    file_row(_('Attached File:'), 'filename', 'filename');
    check_row(_('Alert:'), 'alert');

    end_table(1);
    submit_add_or_update_center($selected_id == -1, '', 'process');
}

hidden('View', @$_GET['View']);
end_form();
?>

<?php
end_page();
?>