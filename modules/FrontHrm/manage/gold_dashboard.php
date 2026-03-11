<?php
/*=================================================================\
|  GOLD SHOP DASHBOARD & LEDGER (FrontAccounting Hook - Single File)|
|  Place in: /modules/YourModule/manage/gold_dashboard.php         |
\=================================================================*/
$path_to_root = '../../..';
$page_security = 'SA_EMPL';

include_once($path_to_root . '/includes/session.inc');
add_access_extensions();
include_once($path_to_root . '/includes/ui.inc');

// ==========================================
// 1. DATABASE CONNECTION (PDO)
// ==========================================
class GoldDatabase {
    public $conn;
    public $conn_sms;
    private static $instance = null;

    private function __construct() {
        try {
            $this->conn = new PDO("mysql:host=localhost;dbname=gold-shop;charset=utf8mb4", "root", "");
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

            $this->conn_sms = new PDO("mysql:host=localhost;dbname=gold-shop_sms;charset=utf8mb4", "root", "");
            $this->conn_sms->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn_sms->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            die(json_encode(['success' => false, 'msg' => "Database Connection failed: " . $e->getMessage()]));
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new GoldDatabase();
        }
        return self::$instance;
    }
}

// Bengali Number Converter
function en2bn($number) {
    $en = ["0","1","2","3","4","5","6","7","8","9"];
    $bn = ["০","১","২","৩","৪","৫","৬","৭","৮","৯"];
    return str_replace($en, $bn, $number);
}

// ==========================================
// 2. ACTION ROUTER
// ==========================================
$action = $_REQUEST['action'] ?? '';

if ($action !== '') {
    $db     = GoldDatabase::getInstance()->conn;
    $db_sms = GoldDatabase::getInstance()->conn_sms;

    // ─── LEDGER ACTIONS ───────────────────────────────────────────
    if ($action === 'storeLedger' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $stmt = $db->prepare("INSERT INTO ledger_entries
            (serial_no, supplier_id, date, jewelry_type, amount, locker_code, delivery_date,
             loan_amount, interest_rate, remant,
             shop_date, shop_delivery_date, shop_loan_amount, shop_interest_rate, shop_remant)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $serial = 'LEDG-' . time();
        $stmt->execute([
            $serial,
            $_POST['customer_id'],
            $_POST['date'],
            $_POST['jewelry_type'],
            $_POST['amount'] ?: 0,
            $_POST['locker_no'] ?? '',
            !empty($_POST['delivery_date'])       ? $_POST['delivery_date']       : null,
            $_POST['loan_amount']                 ?: 0,
            $_POST['interest_rate']               ?: 0,
            $_POST['remant']                      ?: 0,
            !empty($_POST['shop_date'])           ? $_POST['shop_date']           : null,
            !empty($_POST['shop_delivery_date'])  ? $_POST['shop_delivery_date']  : null,
            $_POST['shop_loan_amount']            ?: 0,
            $_POST['shop_interest_rate']          ?: 0,
            $_POST['shop_remant']                 ?: 0,
        ]);
        header('Location: gold_dashboard.php?success=1'); exit;
    }

    elseif ($action === 'updateLedger' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $stmt = $db->prepare("UPDATE ledger_entries SET
            date=?, jewelry_type=?, amount=?, locker_code=?, delivery_date=?,
            loan_amount=?, interest_rate=?, remant=?,
            shop_date=?, shop_delivery_date=?, shop_loan_amount=?, shop_interest_rate=?, shop_remant=?
            WHERE id=?");
        $stmt->execute([
            $_POST['date'],
            $_POST['jewelry_type'],
            $_POST['amount']                     ?: 0,
            $_POST['locker_no']                  ?? '',
            !empty($_POST['delivery_date'])      ? $_POST['delivery_date']       : null,
            $_POST['loan_amount']                ?: 0,
            $_POST['interest_rate']              ?: 0,
            $_POST['remant']                     ?: 0,
            !empty($_POST['shop_date'])          ? $_POST['shop_date']           : null,
            !empty($_POST['shop_delivery_date']) ? $_POST['shop_delivery_date']  : null,
            $_POST['shop_loan_amount']           ?: 0,
            $_POST['shop_interest_rate']         ?: 0,
            $_POST['shop_remant']                ?: 0,
            $_POST['ledger_id'],
        ]);
        header('Location: gold_dashboard.php?success=1'); exit;
    }

    elseif ($action === 'payLedger' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $payment_date   = !empty($_POST['payment_date']) ? $_POST['payment_date'] : date('Y-m-d');
        $payment_amount = $_POST['payment_amount'];
        $stmt = $db->prepare("UPDATE ledger_entries
            SET paid_amount = paid_amount + ?, last_payment_date = ?, last_payment_amount = ?
            WHERE id=?");
        $stmt->execute([$payment_amount, $payment_date, $payment_amount, $_POST['ledger_id']]);
        echo json_encode(['success' => true, 'paid' => $payment_amount,
            'last_payment_date' => $payment_date, 'last_payment_amount' => $payment_amount]);
        exit;
    }

    elseif ($action === 'getCustomerHistory' && isset($_GET['id'])) {
        $stmt = $db->prepare("SELECT * FROM ledger_entries WHERE supplier_id = ? ORDER BY date DESC");
        $stmt->execute([$_GET['id']]);
        $rawHistory = $stmt->fetchAll();
        $history = [];
        $today = new DateTime();
        $today_date_only = new DateTime($today->format('Y-m-d'));
        foreach ($rawHistory as $row) {
            $loan = (float)$row['loan_amount'];
            $rate = (float)$row['interest_rate'];
            $calculated_remant = (float)$row['remant'];
            if ($loan > 0 && $rate > 0) {
                $base_interest = $loan * ($rate / 100);
                $issueDate = new DateTime($row['date']);
                $interval = $issueDate->diff($today);
                $months_passed = ($interval->y * 12) + $interval->m;
                $penalty = 0;
                if (!empty($row['delivery_date'])) {
                    $graceEnd = (new DateTime($row['delivery_date']))->modify('+5 days');
                    if ($today_date_only >= new DateTime($graceEnd->format('Y-m-d'))) {
                        $penalty = $base_interest * 0.5;
                    }
                }
                $calculated_remant += ($base_interest * $months_passed) + $penalty;
            }
            $row['calculated_remant'] = $calculated_remant;
            $history[] = $row;
        }
        header('Content-Type: application/json');
        echo json_encode($history);
        exit;
    }

    elseif ($action === 'getLockerHistory' && isset($_GET['code'])) {
        $stmt = $db->prepare("SELECT l.*, dm.debtor_ref AS customer_name
            FROM ledger_entries l
            JOIN 0_debtors_master dm ON l.supplier_id = dm.debtor_no
            WHERE l.locker_code = ? AND l.shop_loan_amount > 0
            ORDER BY l.shop_date DESC");
        $stmt->execute([$_GET['code']]);
        $rawHistory = $stmt->fetchAll();
        $history = [];
        $today = new DateTime();
        $today_date_only = new DateTime($today->format('Y-m-d'));
        foreach ($rawHistory as $row) {
            $loan = (float)$row['shop_loan_amount'];
            $rate = (float)$row['shop_interest_rate'];
            $calculated_remant = (float)$row['shop_remant'];
            if ($loan > 0 && $rate > 0 && !empty($row['shop_date'])) {
                $base_interest = $loan * ($rate / 100);
                $issueDate = new DateTime($row['shop_date']);
                $interval = $issueDate->diff($today);
                $months_passed = ($interval->y * 12) + $interval->m;
                $penalty = 0;
                if (!empty($row['shop_delivery_date'])) {
                    $graceEnd = (new DateTime($row['shop_delivery_date']))->modify('+5 days');
                    if ($today_date_only >= new DateTime($graceEnd->format('Y-m-d'))) {
                        $penalty = $base_interest * 0.5;
                    }
                }
                $calculated_remant += ($base_interest * $months_passed) + $penalty;
            }
            $row['shop_calculated_remant'] = $calculated_remant;
            $history[] = $row;
        }
        header('Content-Type: application/json');
        echo json_encode($history);
        exit;
    }

    // ─── GOLD RATE ACTIONS ────────────────────────────────────────
    elseif ($action === 'storeRate' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $stmt = $db->prepare("INSERT INTO gold_rates
            (date, carat_22_per_gram, carat_21_per_gram, carat_18_per_gram, traditional_per_gram)
            VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$_POST['date'], $_POST['carat_22_per_gram'],
            $_POST['carat_21_per_gram'], $_POST['carat_18_per_gram'], $_POST['traditional_per_gram']]);
        header('Location: gold_dashboard.php?success=1'); exit;
    }

    elseif ($action === 'updateRate' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $stmt = $db->prepare("UPDATE gold_rates
            SET date=?, carat_22_per_gram=?, carat_21_per_gram=?, carat_18_per_gram=?, traditional_per_gram=?
            WHERE id=?");
        $stmt->execute([$_POST['date'], $_POST['carat_22_per_gram'],
            $_POST['carat_21_per_gram'], $_POST['carat_18_per_gram'],
            $_POST['traditional_per_gram'], $_POST['rate_id']]);
        header('Location: gold_dashboard.php?success=1'); exit;
    }

    elseif ($action === 'deleteRate' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $stmt = $db->prepare("DELETE FROM gold_rates WHERE id=?");
        $stmt->execute([$_POST['rate_id']]);
        header('Location: gold_dashboard.php?success=1'); exit;
    }

    // ─── SMS TEMPLATE ACTIONS ─────────────────────────────────────
    elseif ($action === 'saveTemplate' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $stmt = $db->prepare("INSERT INTO wish_massage (occasion, massage, action) VALUES (?, ?, 0)");
        $stmt->execute([$_POST['title'], $_POST['message']]);
        header('Location: gold_dashboard.php?success=1'); exit;
    }

    elseif ($action === 'updateTemplate' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $stmt = $db->prepare("UPDATE wish_massage SET occasion = ?, massage = ? WHERE id = ?");
        $stmt->execute([$_POST['title'], $_POST['message'], $_POST['template_id']]);
        header('Location: gold_dashboard.php?success=1'); exit;
    }

    elseif ($action === 'toggleTemplateStatus' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if ($_POST['current_status'] == 0) {
            $db->query("UPDATE wish_massage SET action = 0");
            $stmt = $db->prepare("UPDATE wish_massage SET action = 1 WHERE id = ?");
            $stmt->execute([$_POST['id']]);
        } else {
            $stmt = $db->prepare("UPDATE wish_massage SET action = 0 WHERE id = ?");
            $stmt->execute([$_POST['id']]);
        }
        header('Location: gold_dashboard.php?success=1'); exit;
    }

    // ─── SMS SEND ACTIONS ─────────────────────────────────────────
    elseif ($action === 'sendManualSms' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $mobile      = $_POST['phone'];
        $template_id = $_POST['template_id'];
        $ledger_id   = $_POST['ledger_id'];

        $user_account = $db_sms->query("SELECT * FROM user_account LIMIT 1")->fetch();
        if (!$user_account) { echo json_encode(['success' => false, 'msg' => 'No API Account found.']); exit; }

        $stmt = $db->prepare("SELECT massage FROM wish_massage WHERE id = ?");
        $stmt->execute([$template_id]);
        $template = $stmt->fetch();
        if (!$template) { echo json_encode(['success' => false, 'msg' => 'Template not found.']); exit; }

        $message_text = $template['massage'];
        $masking_name = urlencode($user_account["masking_name"]);
        $user_password = urlencode($user_account["masking_user_password"]);
        $url = "https://msg.mram.com.bd/smsapi?api_key=" . $user_password
             . "&type=text&contacts=" . $mobile
             . "&senderid=" . $masking_name
             . "&msg=" . urlencode($message_text);

        $curl = curl_init();
        curl_setopt_array($curl, [CURLOPT_URL => $url, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30]);
        $sms_send_result = curl_exec($curl);
        curl_close($curl);

        if (substr($sms_send_result, 0, 17) === "SMS SUBMITTED: ID") {
            $db_sms->prepare("INSERT INTO send_sms (massege, mobile_no, from_name, sms_status, failed_sms_status)
                VALUES (?, ?, 'Manual', '1', '0')")->execute([$message_text, $mobile]);
            $db->prepare("UPDATE ledger_entries SET last_due_sms_date = CURDATE() WHERE id=?")->execute([$ledger_id]);
            echo json_encode(['success' => true, 'msg' => 'এসএমএস সফলভাবে পাঠানো হয়েছে!']);
        } else {
            $db_sms->prepare("INSERT INTO send_sms (massege, mobile_no, from_name, sms_status, failed_sms_status)
                VALUES (?, ?, 'Manual', '0', '1')")->execute([$message_text, $mobile]);
            echo json_encode(['success' => false, 'msg' => 'এসএমএস পাঠানো ব্যর্থ হয়েছে! API Error.']);
        }
        exit;
    }

    elseif ($action === 'triggerAutoSMS') {
        $user_account = $db_sms->query("SELECT * FROM user_account LIMIT 1")->fetch();
        if (!$user_account) { echo json_encode(['status' => 'error', 'msg' => 'No Account']); exit; }

        $template = $db->query("SELECT massage AS message FROM wish_massage WHERE action = 1 LIMIT 1")->fetch();
        if (!$template) { echo json_encode(['status' => 'error', 'msg' => 'No active template']); exit; }

        $message_text    = $template['message'];
        $masking_name    = urlencode($user_account["masking_name"]);
        $user_password   = urlencode($user_account["masking_user_password"]);
        $use_sms         = $user_account['use_sms'];
        $remaining_sms   = $user_account['remaining_sms'];
        $failed_sms_count = $user_account['failed_sms_count'];
        $lengthParSms    = (strlen($message_text) == strlen(utf8_decode($message_text))) ? 160 : 70;

        $query = "SELECT l.id, l.date, l.last_payment_date, dm.tax_id AS phone
            FROM ledger_entries l
            JOIN 0_debtors_master dm ON l.supplier_id = dm.debtor_no
            WHERE l.loan_amount > 0
            AND (l.last_due_sms_date IS NULL OR DATEDIFF(CURDATE(), l.last_due_sms_date) >= 30)";
        $ledgers = $db->query($query)->fetchAll();
        $today = new DateTime();
        $sent_count = 0;

        foreach ($ledgers as $row) {
            if ($remaining_sms < 1 || $sent_count >= 5) break;
            $issueDate    = new DateTime($row['date']);
            $months_passed = ($issueDate->diff($today)->y * 12) + $issueDate->diff($today)->m;
            $last_pay_diff_days = !empty($row['last_payment_date'])
                ? (new DateTime($row['last_payment_date']))->diff($today)->days
                : $issueDate->diff($today)->days;

            if ($months_passed >= 3 && $last_pay_diff_days >= 90 && !empty($row['phone'])) {
                $mobile  = $row['phone'];
                $url     = "https://msg.mram.com.bd/smsapi?api_key=" . $user_password
                         . "&type=text&contacts=" . $mobile
                         . "&senderid=" . $masking_name
                         . "&msg=" . urlencode($message_text);
                $curl = curl_init();
                curl_setopt_array($curl, [CURLOPT_URL => $url, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30]);
                $sms_send_result = curl_exec($curl);
                curl_close($curl);
                $sms_cost = ceil(strlen($message_text) / $lengthParSms);

                if (substr($sms_send_result, 0, 17) === "SMS SUBMITTED: ID") {
                    $use_sms       += $sms_cost;
                    $remaining_sms -= $sms_cost;
                    $db_sms->prepare("INSERT INTO send_sms (massege, mobile_no, from_name, remaing_sms, sms_status, failed_sms_status)
                        VALUES (?, ?, ?, ?, '1', '0')")->execute([$message_text, $mobile, 'AutoSystem', $remaining_sms]);
                    $db->prepare("UPDATE ledger_entries SET last_due_sms_date = CURDATE() WHERE id=?")->execute([$row['id']]);
                    $sent_count++;
                } else {
                    $failed_sms_count++;
                    $db_sms->prepare("INSERT INTO send_sms (massege, mobile_no, from_name, remaing_sms, sms_status, failed_sms_status)
                        VALUES (?, ?, ?, ?, '0', '1')")->execute([$message_text, $mobile, 'AutoSystem', $remaining_sms]);
                }
                $db_sms->prepare("UPDATE user_account SET use_sms=?, remaining_sms=?, failed_sms_count=? WHERE id=?")
                    ->execute([$use_sms, $remaining_sms, $failed_sms_count, $user_account['id']]);
            }
        }
        echo json_encode(['status' => 'success', 'sent' => $sent_count]);
        exit;
    }
}

// ==========================================
// 3. FRONTACCOUNTING PAGE SETUP & DATA FETCH
// ==========================================
page(_("Gold Shop Dashboard"), false, false, "", "");

$db     = GoldDatabase::getInstance()->conn;
$db_sms = GoldDatabase::getInstance()->conn_sms;

// ─── Fetch UI Data ────────────────────────────────────────────────
$latestRate = $db->query("SELECT * FROM gold_rates ORDER BY date DESC LIMIT 1")->fetch();
$allRates   = $db->query("SELECT * FROM gold_rates ORDER BY date DESC")->fetchAll();

$customers = $db->query(
    "SELECT dm.debtor_no AS id, cp.name, cp.address, cp.phone, cp.fax AS nid_no, cp.email,
            dm.tax_id AS primary_phone
     FROM 0_debtors_master dm
     JOIN 0_crm_contacts cc ON dm.debtor_no = cc.entity_id
     JOIN 0_crm_persons cp  ON cc.person_id = cp.id
     WHERE cc.type = 'customer' AND cc.action = 'general'
     ORDER BY cp.name ASC"
)->fetchAll();
$totalCustomers = count($customers);

$locations = $db->query("SELECT loc_code, location_name, fax FROM 0_locations")->fetchAll();

$rawLedgers = $db->query(
    "SELECT l.*, dm.name AS debtor_master_name, cp.name AS customer_name,
            dm.debtor_no, dm.debtor_ref, cp.phone, dm.tax_id AS primary_phone,
            cp.address, cp.fax AS nid_no, loc.location_name AS locker_name
     FROM ledger_entries l
     JOIN 0_debtors_master dm ON l.supplier_id = dm.debtor_no
     JOIN 0_crm_contacts cc   ON dm.debtor_no = cc.entity_id
     JOIN 0_crm_persons cp    ON cc.person_id = cp.id
     LEFT JOIN 0_locations loc ON l.locker_code = loc.loc_code
     WHERE cc.type = 'customer' AND cc.action = 'general'
     ORDER BY l.date DESC"
)->fetchAll();

$templates  = $db->query(
    "SELECT id, occasion AS title, massage AS message, action AS status FROM wish_massage ORDER BY id DESC"
)->fetchAll();

$smsLogsRaw = $db_sms->query(
    "SELECT mobile_no, sms_status, created_at FROM send_sms ORDER BY id DESC"
)->fetchAll();
$sms_logs = [];
foreach ($smsLogsRaw as $log) {
    if (!isset($sms_logs[$log['mobile_no']])) {
        $sms_logs[$log['mobile_no']] = $log;
    }
}

// ─── Process Ledgers ─────────────────────────────────────────────
$ledgers      = [];
$due_ledgers  = [];
$today        = new DateTime();
$today_date_only = new DateTime($today->format('Y-m-d'));

foreach ($rawLedgers as $row) {
    $issueDate = new DateTime($row['date']);
    $interval  = $issueDate->diff($today);
    $months_passed = ($interval->y * 12) + $interval->m;

    $last_pay_diff_days = !empty($row['last_payment_date'])
        ? (new DateTime($row['last_payment_date']))->diff($today)->days
        : $issueDate->diff($today)->days;

    $ageStr = '';
    if ($interval->y > 0) $ageStr .= $interval->y . ' বছর ';
    if ($interval->m > 0) $ageStr .= $interval->m . ' মাস ';
    if ($interval->d > 0) $ageStr .= $interval->d . ' দিন';
    $row['age_string'] = ($ageStr === '') ? '০ দিন' : trim($ageStr);

    $loan = (float)$row['loan_amount'];
    $rate = (float)$row['interest_rate'];
    $calculated_remant = (float)$row['remant'];

    if ($loan > 0 && $rate > 0) {
        $base_interest = $loan * ($rate / 100);
        $penalty = 0;
        if (!empty($row['delivery_date'])) {
            $graceEnd = (new DateTime($row['delivery_date']))->modify('+5 days');
            if ($today_date_only >= new DateTime($graceEnd->format('Y-m-d'))) {
                $penalty = $base_interest * 0.5;
            }
        }
        $calculated_remant += ($base_interest * $months_passed) + $penalty;
    }
    $row['calculated_remant'] = $calculated_remant;

    $mobile = !empty($row['phone']) ? $row['phone'] : ($row['primary_phone'] ?? '');
    $row['actual_phone'] = $mobile;

    if ($mobile && isset($sms_logs[$mobile])) {
        $row['sms_status'] = $sms_logs[$mobile]['sms_status'];
        $row['sms_time']   = $sms_logs[$mobile]['created_at'];
    } else {
        $row['sms_status'] = null;
        $row['sms_time']   = null;
    }

    $total_due       = ($loan + $calculated_remant) - (float)$row['paid_amount'];
    $row['is_closed'] = ($total_due <= 0);
    $ledgers[]        = $row;

    if ($total_due > 0 && $months_passed >= 3 && $last_pay_diff_days >= 90) {
        $due_ledgers[] = $row;
    }
}
?>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css">
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Bengali:wght@400;600&display=swap" rel="stylesheet">
<style>
    body, .container-fluid { font-family: 'Noto Sans Bengali', sans-serif !important; }
    .bg-gold { background-color: #C9A227 !important; color: #000; }
    .text-gold { color: #D4AF37; }
    .table-ledger th { background-color: #C9A227; color: #000; }
    .invoice-box { border: 2px solid #D4AF37; padding: 30px; background: #fff; margin-top: 20px; }
    .shop-title { font-size: 28px; font-weight: bold; color: #800000; }
    .stat-card { border-left: 5px solid #C9A227; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
    .invoice-print-area { padding: 40px; background: #fff; border: 1px solid #ddd; }
    .modal-dialog-scrollable { height: calc(100vh - 56px) !important; }
    .modal-dialog-scrollable .modal-content { height: 100% !important; max-height: 100% !important; overflow: hidden !important; display: flex !important; flex-direction: column !important; }
    .modal-dialog-scrollable .modal-body { overflow-y: auto !important; flex: 1 1 auto !important; min-height: 0 !important; }
    .pdf-export-active { width: 1400px !important; max-width: 1400px !important; }
    .pdf-export-active .table-responsive { overflow: visible !important; }
    .pdf-export-active table { font-size: 13px !important; width: 100% !important; }
    .pdf-export-active th, .pdf-export-active td { padding: 8px 5px !important; word-wrap: break-word; }
    #mainLedgerTable tfoot th { color: #000 !important; background-color: #e9ecef !important; border: 1px solid #000 !important; font-weight: bold !important; }
    @media print {
        #header, #footer, #inquiry_form { display: none !important; }
        body * { visibility: hidden; }
        #invoicePrintArea, #invoicePrintArea * { visibility: visible; }
        #invoicePrintArea { position: absolute; left: 0; top: 0; width: 100%; border: none; padding: 0; }
        #customReportArea, #customReportArea * { visibility: visible; }
        #customReportArea { position: absolute; left: 0; top: 0; width: 100%; border: none; padding: 0; margin: 0; }
        .no-print { display: none !important; }
        .table-responsive { overflow: visible !important; }
    }
</style>

<!-- Selection Mode Banner -->
<div id="selectionBanner" class="bg-danger text-white text-center py-2 sticky-top shadow-lg" style="display:none; z-index:9999; font-size:18px;">
    <span class="fw-bold" id="modeText"></span> করার জন্য নিচের যেকোনো একটি সেকশনে ক্লিক করুন।
    <button onclick="cancelSelection()" class="btn btn-sm btn-dark ms-3 fw-bold">বাতিল করুন</button>
</div>

<div class="container-fluid mt-3" style="font-family:'Noto Sans Bengali',sans-serif;">
    <div class="row">

        <!-- ── Top Action Bar ────────────────────────────────────── -->
        <div class="col-12 mb-3 no-print d-flex gap-2 bg-light p-3 border rounded shadow-sm flex-wrap">
            <button class="btn btn-dark fw-bold" data-bs-toggle="modal" data-bs-target="#rateModal">স্বর্ণের দাম ও ইতিহাস</button>
            <button class="btn btn-warning fw-bold text-dark" data-bs-toggle="modal" data-bs-target="#ledgerModal">নতুন লেজার এন্ট্রি</button>
            <button class="btn btn-info fw-bold" data-bs-toggle="modal" data-bs-target="#messageCheckModal">ম্যাসেজ চেক (Due SMS)</button>
            <button class="btn btn-secondary fw-bold" data-bs-toggle="modal" data-bs-target="#messageTemplateModal">মেসেজ টেমপ্লেট</button>
        </div>

        <!-- ── Main Content ──────────────────────────────────────── -->
        <div class="col-12">

            <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show no-print">
                <strong>সফল!</strong> ডাটা সফলভাবে সেভ বা আপডেট হয়েছে।
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2 no-print">
                <h2 class="text-dark fw-bold mb-0">গোল্ড শপ ড্যাশবোর্ড</h2>
                <div>
                    <button onclick="enableSelectionMode('print')" class="btn btn-dark px-4 me-2 fw-bold">প্রিন্ট মোড</button>
                    <button class="btn btn-danger px-4 fw-bold" data-bs-toggle="modal" data-bs-target="#reportModal">PDF রিপোর্ট</button>
                </div>
            </div>

            <!-- Stat Cards -->
            <div class="row g-4 mb-4">
                <div class="col-md-6">
                    <div class="stat-card">
                        <h6 class="text-muted">আজকের স্বর্ণের দর (২২ ক্যারেট)</h6>
                        <h3 class="mb-0 text-dark">৳<?= en2bn($latestRate ? number_format($latestRate['carat_22_per_gram'] * 11.664, 2) : '0.00') ?> <small class="fs-6 text-muted">/ ভরি</small></h3>
                        <small class="text-primary fw-bold">গ্রাম: ৳<?= en2bn($latestRate ? number_format($latestRate['carat_22_per_gram'], 2) : '0.00') ?></small>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="stat-card">
                        <h6 class="text-muted">মোট গ্রাহক সংখ্যা</h6>
                        <h3 class="mb-0"><?= en2bn($totalCustomers) ?> জন</h3>
                        <small class="text-primary">সিস্টেমে নিবন্ধিত</small>
                    </div>
                </div>
            </div>

            <!-- Ledger Report Area -->
            <div id="customReportArea" class="invoice-box shadow-sm bg-white">
                <div class="text-center w-100 mb-4 border-bottom border-warning pb-3">
                    <div class="shop-title">সোনার প্রাসাদ জুয়েলার্স</div>
                    <p class="mb-0">১২২, নিউ মার্কেট সিটি কমপ্লেক্স, ঢাকা-১২০৫</p>
                    <p>মোবাইল: <?= en2bn('01700-000000') ?></p>
                    <h4 class="text-dark mt-3 fw-bold no-print" id="reportTitle">সর্বশেষ লেজার হিসাব (রানিং)</h4>
                </div>

                <!-- Dashboard Filters -->
                <div class="row align-items-end mb-3 no-print bg-light p-3 rounded border shadow-sm" id="dashboardFilterContainer">
                    <div class="col-md-3">
                        <label class="fw-bold mb-1 text-muted">শুরুর তারিখ</label>
                        <input type="date" id="dashFromDate" class="form-control border-primary" onchange="applyDashboardFilters()">
                    </div>
                    <div class="col-md-3">
                        <label class="fw-bold mb-1 text-muted">শেষের তারিখ</label>
                        <input type="date" id="dashToDate" class="form-control border-primary" onchange="applyDashboardFilters()">
                    </div>
                    <div class="col-md-4">
                        <label class="fw-bold mb-1 text-muted">গ্রাহকের নাম বা আইডি খুঁজুন</label>
                        <input type="text" id="dashSearch" class="form-control border-primary shadow-sm" placeholder="খুঁজুন..." onkeyup="applyDashboardFilters()">
                    </div>
                    <div class="col-md-2 text-end">
                        <button class="btn btn-dark w-100 fw-bold" onclick="resetDashboardFilters()">রিসেট</button>
                    </div>
                </div>

                <!-- Main Ledger Table -->
                <div class="table-responsive">
                <table class="table table-bordered table-ledger table-hover text-center align-middle" style="font-size:13px;" id="mainLedgerTable">
                    <thead>
                        <tr>
                            <th>ক্রমিক</th><th>গ্রাহক আইডি</th><th>গ্রাহক</th><th>তারিখ</th>
                            <th>অতিক্রান্ত সময়</th><th>গহনার ধরন</th><th>হিসাব (লোন + লভ্যাংশ)</th>
                            <th>পেমেন্ট ও বকেয়া</th><th>সর্বশেষ পেমেন্ট</th><th>লকার</th>
                            <th>ডেলিভারি</th><th class="no-print">অ্যাকশন</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($ledgers as $row):
                        $loan       = (float)($row['loan_amount']        ?? 0);
                        $remant     = (float)($row['calculated_remant']  ?? 0);
                        $paid       = (float)($row['paid_amount']        ?? 0);
                        $total_due  = ($loan + $remant) - $paid;
                        $shop_loan  = (float)($row['shop_loan_amount']   ?? 0);
                        $shop_remant = (float)($row['shop_calculated_remant'] ?? 0);
                        $row_json   = htmlspecialchars(json_encode($row, JSON_INVALID_UTF8_IGNORE) ?: '{}', ENT_QUOTES, 'UTF-8');
                        $is_closed_class = !empty($row['is_closed']) ? 'is-closed' : 'is-running';
                    ?>
                    <tr class="ledger-row <?= $is_closed_class ?>"
                        data-id="<?= $row['id'] ?? '' ?>"
                        data-date="<?= $row['date'] ?? '' ?>"
                        data-customer="<?= $row['debtor_no'] ?? '' ?>"
                        data-locker="<?= htmlspecialchars($row['locker_code'] ?? '') ?>"
                        data-loan="<?= $loan ?>"
                        data-remant="<?= $remant ?>"
                        data-paid="<?= $paid ?>"
                        data-due="<?= $total_due ?>"
                        data-shop-loan="<?= $shop_loan ?>"
                        data-shop-remant="<?= $shop_remant ?>"
                        style="display:none;">

                        <td class="fw-bold fs-5 text-primary"><?= en2bn($row['debtor_no'] ?? '') ?></td>
                        <td class="fw-bold text-dark"><?= htmlspecialchars($row['debtor_master_name'] ?? '') ?></td>
                        <td class="text-start">
                            <span class="fw-bold d-block text-dark fs-6"><?= htmlspecialchars($row['debtor_ref'] ?? '') ?></span>
                            <span class="d-block text-muted" style="font-size:11px;">মোবাইল: <?= en2bn(htmlspecialchars($row['actual_phone'] ?? '')) ?></span>
                        </td>
                        <td><?= en2bn(!empty($row['date']) ? date('d-m-Y', strtotime($row['date'])) : '') ?></td>
                        <td><span class="badge bg-danger fs-6"><?= en2bn($row['age_string'] ?? '') ?></span></td>
                        <td><?= htmlspecialchars($row['jewelry_type'] ?? '') ?><br>
                            <small class="text-muted">মূল্য: ৳<?= en2bn(number_format((float)($row['amount'] ?? 0), 2)) ?></small></td>
                        <td><small class="text-danger">লোন: <?= en2bn(number_format($loan, 2)) ?></small><br>
                            <small class="text-success">লভ্যাংশ: <?= en2bn(number_format($remant, 2)) ?></small></td>
                        <td class="pay-cell">
                            <small class="text-primary paid-label">জমা: <?= en2bn(number_format($paid, 2)) ?></small><br>
                            <small class="text-danger fw-bold due-label">বকেয়া: <?= en2bn(number_format($total_due, 2)) ?></small>
                        </td>
                        <td class="last-pay-cell">
                            <?php if (!empty($row['last_payment_date'])): ?>
                                <span class="badge bg-success"><?= en2bn(date('d-m-Y', strtotime($row['last_payment_date']))) ?></span><br>
                                <small class="text-muted"><?= en2bn(number_format((float)($row['last_payment_amount'] ?? 0), 2)) ?> ৳</small>
                            <?php else: ?><span class="text-muted small">-</span><?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($row['locker_code'])): ?>
                                <button class="btn btn-sm btn-outline-primary fw-bold w-100 no-print"
                                    data-row='<?= $row_json ?>' onclick="openShopDetailsModal(this)">
                                    <?= htmlspecialchars($row['locker_name'] ?? $row['locker_code'] ?? '') ?>
                                </button>
                                <span class="d-none print-only"><?= htmlspecialchars($row['locker_name'] ?? $row['locker_code'] ?? '') ?></span>
                            <?php else: ?>-<?php endif; ?>
                        </td>
                        <td class="fw-bold text-dark"><?= !empty($row['delivery_date']) ? en2bn(date('d-m-Y', strtotime($row['delivery_date']))) : '-' ?></td>
                        <td class="no-print">
                            <button class="btn btn-sm btn-dark mb-1 w-100" data-row='<?= $row_json ?>' onclick="openEditModal(this)">এডিট</button>
                            <button class="btn btn-sm btn-success w-100" data-row='<?= $row_json ?>' onclick="openPayModal(this)">পেমেন্ট</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot style="background-color:#f8f9fa !important; font-size:14px;">
                        <tr style="border-top:2px solid #000;">
                            <th colspan="6" class="text-end fw-bold" style="color:#000 !important;">মোট হিসাব সামারি:</th>
                            <th id="sum_loan_remant" class="fw-bold" style="color:#000 !important;"></th>
                            <th id="sum_paid_due" class="fw-bold" style="color:#000 !important;"></th>
                            <th colspan="3" id="sum_shop" class="fw-bold text-start" style="color:#000 !important;"></th>
                            <th class="no-print"></th>
                        </tr>
                    </tfoot>
                </table>
                </div>

                <nav class="mt-3 no-print">
                    <ul class="pagination justify-content-center" id="paginationControls"></ul>
                </nav>
            </div><!-- /customReportArea -->

        </div><!-- /col-12 -->
    </div><!-- /row -->
</div><!-- /container-fluid -->


<!-- ================================================================
     MODALS
================================================================ -->

<!-- ── PDF Report Modal ──────────────────────────────────────────── -->
<div class="modal fade no-print" id="reportModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-danger">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title fw-bold">অ্যাডভান্সড লেজার রিপোর্ট (PDF)</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="fw-bold mb-1">শুরুর তারিখ (ঐচ্ছিক)</label>
                        <input type="date" id="filterFrom" class="form-control border-danger">
                    </div>
                    <div class="col-md-6">
                        <label class="fw-bold mb-1">শেষের তারিখ (ঐচ্ছিক)</label>
                        <input type="date" id="filterTo" class="form-control border-danger">
                    </div>
                    <div class="col-md-6">
                        <label class="fw-bold mb-1">গ্রাহক অনুযায়ী (ঐচ্ছিক)</label>
                        <select id="filterCustomer" class="form-select" style="width:100%;">
                            <option value="">-- সকল গ্রাহক --</option>
                            <?php foreach ($customers as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= en2bn($c['id']) ?> - <?= htmlspecialchars($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="fw-bold mb-1">লকার অনুযায়ী (ঐচ্ছিক)</label>
                        <select id="filterLocker" class="form-select" style="width:100%;">
                            <option value="">-- সকল লকার --</option>
                            <?php foreach ($locations as $loc): ?>
                            <option value="<?= htmlspecialchars($loc['loc_code']) ?>"><?= htmlspecialchars($loc['location_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-12 mt-3">
                        <label class="fw-bold mb-1 text-primary">হিসাবের ধরন (স্ট্যাটাস)</label>
                        <select id="filterStatus" class="form-select border-primary" style="font-size:15px;font-weight:bold;">
                            <option value="running" selected>শুধুমাত্র রানিং লেজার (যাদের বকেয়া আছে)</option>
                            <option value="closed">শুধুমাত্র পরিশোধিত লেজার (যাদের বকেয়া নেই)</option>
                            <option value="all">সব লেজার (রানিং এবং পরিশোধিত একসাথে)</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light d-flex justify-content-between">
                <button type="button" class="btn btn-secondary fw-bold" data-bs-dismiss="modal">বাতিল</button>
                <div>
                    <button type="button" class="btn btn-outline-dark fw-bold me-2" onclick="generateFilteredPDF('customer')">সকল গ্রাহক</button>
                    <button type="button" class="btn btn-outline-primary fw-bold me-2" onclick="generateFilteredPDF('locker')">সকল লকার</button>
                    <button type="button" class="btn btn-danger fw-bold shadow-sm" onclick="generateFilteredPDF('normal')">ডাউনলোড</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ── Due SMS Check Modal ───────────────────────────────────────── -->
<div class="modal fade no-print" id="messageCheckModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-info text-dark">
                <h5 class="modal-title fw-bold">ম্যাসেজ চেক (৩ মাসের বকেয়া লিস্ট)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <div class="alert alert-warning m-0 text-center fw-bold border-0 border-bottom rounded-0" style="font-size:13px;">
                    যাদের বয়স ৩ মাসের বেশি এবং গত ৯০ দিনে কোনো পেমেন্ট করেনি, তাদেরকে এই লিস্টে দেখানো হচ্ছে।
                    অটোমেটিক মেসেজ শুধুমাত্র এদেরকেই প্রতি মাসে একবার পাঠানো হয়। আপনি চাইলে ম্যানুয়ালিও পাঠাতে পারেন।
                </div>
                <div class="table-responsive">
                <table class="table table-bordered table-hover text-center align-middle" style="font-size:13px;">
                    <thead class="table-light sticky-top">
                        <tr>
                            <th>ক্রমিক</th><th>গ্রাহক</th><th>মোবাইল</th><th>তারিখ</th>
                            <th>অতিক্রান্ত সময়</th><th>হিসাব (লোন + লভ্যাংশ)</th><th>এসএমএস স্ট্যাটাস</th><th>অ্যাকশন</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (count($due_ledgers) > 0): foreach ($due_ledgers as $row): ?>
                    <tr>
                        <td class="fw-bold fs-5 text-primary"><?= en2bn($row['debtor_no'] ?? '') ?></td>
                        <td class="fw-bold text-dark text-start">
                            <?= htmlspecialchars($row['debtor_master_name'] ?? '') ?><br>
                            <span class="text-muted small"><?= htmlspecialchars($row['debtor_ref'] ?? '') ?></span>
                        </td>
                        <td><?= en2bn(htmlspecialchars($row['actual_phone'] ?? '')) ?></td>
                        <td><?= en2bn(!empty($row['date']) ? date('d-m-Y', strtotime($row['date'])) : '') ?></td>
                        <td><span class="badge bg-danger fs-6"><?= en2bn($row['age_string'] ?? '') ?></span></td>
                        <td>
                            <small class="text-danger">লোন: <?= en2bn(number_format((float)($row['loan_amount'] ?? 0), 2)) ?></small><br>
                            <small class="text-success">লভ্যাংশ: <?= en2bn(number_format((float)($row['calculated_remant'] ?? 0), 2)) ?></small>
                        </td>
                        <td>
                            <?php if (isset($row['sms_status']) && $row['sms_status'] == 1): ?>
                                <span class="badge bg-success d-block mb-1">পাঠানো হয়েছে</span>
                                <small class="text-muted"><?= !empty($row['sms_time']) ? en2bn(date('d-m-Y h:i A', strtotime($row['sms_time']))) : '' ?></small>
                            <?php elseif (isset($row['sms_status']) && $row['sms_status'] === '0'): ?>
                                <span class="badge bg-danger d-block mb-1">ফেইলড</span>
                                <small class="text-muted"><?= !empty($row['sms_time']) ? en2bn(date('d-m-Y h:i A', strtotime($row['sms_time']))) : '' ?></small>
                            <?php else: ?>
                                <span class="badge bg-secondary">যায়নি</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($row['actual_phone'])): ?>
                                <button class="btn btn-sm btn-primary fw-bold"
                                    onclick="openManualSmsModal('<?= $row['id'] ?>','<?= htmlspecialchars($row['actual_phone']) ?>','<?= htmlspecialchars($row['debtor_master_name'] ?? '') ?>')">
                                    ম্যানুয়ালি
                                </button>
                            <?php else: ?><span class="text-danger small">নম্বর নেই</span><?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; else: ?>
                        <tr><td colspan="8" class="text-muted py-4">বর্তমানে ৩ মাসের কোনো বকেয়া গ্রাহক নেই।</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ── Manual SMS Modal ─────────────────────────────────────────── -->
<div class="modal fade no-print" id="manualSmsModal" tabindex="-1">
    <div class="modal-dialog">
        <form id="manualSmsForm" class="modal-content border-primary">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold">গ্রাহককে এসএমএস পাঠান</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" name="action" value="sendManualSms">
                <input type="hidden" name="ledger_id" id="manual_sms_ledger_id">
                <div class="mb-3">
                    <label class="fw-bold text-muted mb-1">গ্রাহকের নাম</label>
                    <h5 class="fw-bold text-dark" id="manual_sms_name"></h5>
                </div>
                <div class="mb-3">
                    <label class="fw-bold text-muted mb-1">মোবাইল নম্বর</label>
                    <input type="text" name="phone" id="manual_sms_phone" class="form-control fw-bold" readonly>
                </div>
                <div class="mb-4">
                    <label class="fw-bold mb-1">টেমপ্লেট নির্বাচন করুন *</label>
                    <select name="template_id" class="form-select" required>
                        <option value="">-- টেমপ্লেট বেছে নিন --</option>
                        <?php foreach ($templates as $tpl): ?>
                        <option value="<?= $tpl['id'] ?>"><?= htmlspecialchars($tpl['title'] ?? '') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="alert alert-info text-center d-none fw-bold" id="manualSmsStatus"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">বাতিল</button>
                <button type="submit" class="btn btn-primary px-4 fw-bold" id="manualSmsBtn">এসএমএস পাঠান</button>
            </div>
        </form>
    </div>
</div>

<!-- ── SMS Template Modal ───────────────────────────────────────── -->
<div class="modal fade no-print" id="messageTemplateModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold">মেসেজ টেমপ্লেট ও এক্টিভেশন</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <form id="templateForm" action="gold_dashboard.php" method="POST"
                      class="mb-4 border-bottom pb-4 row g-2 bg-light p-3 rounded shadow-sm">
                    <input type="hidden" name="action" id="tpl_action" value="saveTemplate">
                    <input type="hidden" name="template_id" id="tpl_id" value="">
                    <h6 class="fw-bold text-success" id="tpl_form_heading">নতুন টেমপ্লেট তৈরি করুন</h6>
                    <div class="col-md-4">
                        <label class="fw-bold">টাইটেল</label>
                        <input type="text" name="title" id="tpl_title" class="form-control" required placeholder="যেমন: ৩ মাসের বকেয়া">
                    </div>
                    <div class="col-md-8">
                        <label class="fw-bold">মেসেজ টেক্সট</label>
                        <textarea name="message" id="tpl_message" class="form-control" rows="2" required placeholder="আপনার মেসেজ লিখুন..."></textarea>
                    </div>
                    <div class="col-12 text-end mt-2">
                        <button type="button" class="btn btn-secondary fw-bold px-3 me-2" style="display:none;" id="tpl_cancel_btn" onclick="cancelTemplateEdit()">বাতিল</button>
                        <button type="submit" class="btn btn-success fw-bold px-4" id="tpl_submit_btn">সেভ করুন</button>
                    </div>
                </form>
                <h6 class="fw-bold text-dark mb-3">আপনার টেমপ্লেটসমূহ:</h6>
                <table class="table table-bordered align-middle">
                    <thead class="table-light"><tr><th>টাইটেল</th><th>মেসেজ</th><th>স্ট্যাটাস</th><th>অ্যাকশন</th></tr></thead>
                    <tbody>
                    <?php foreach ($templates as $tpl):
                        $safe_tpl = htmlspecialchars(json_encode($tpl), ENT_QUOTES, 'UTF-8'); ?>
                    <tr>
                        <td class="fw-bold"><?= htmlspecialchars($tpl['title'] ?? '') ?></td>
                        <td style="max-width:300px;"><?= htmlspecialchars($tpl['message'] ?? '') ?></td>
                        <td class="text-center">
                            <form action="gold_dashboard.php" method="POST">
                                <input type="hidden" name="action" value="toggleTemplateStatus">
                                <input type="hidden" name="id" value="<?= $tpl['id'] ?>">
                                <input type="hidden" name="current_status" value="<?= $tpl['status'] ?>">
                                <?php if (isset($tpl['status']) && $tpl['status'] == 1): ?>
                                    <button type="submit" class="btn btn-sm btn-success fw-bold">এক্টিভ ✔</button>
                                <?php else: ?>
                                    <button type="submit" class="btn btn-sm btn-outline-secondary">বন্ধ আছে</button>
                                <?php endif; ?>
                            </form>
                        </td>
                        <td class="text-center">
                            <button type="button" class="btn btn-sm btn-dark" data-tpl='<?= $safe_tpl ?>' onclick="editTemplate(this)">এডিট</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ── Shop / Locker Details Modal (Profit-Loss) ─────────────────── -->
<div class="modal fade no-print" id="shopDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-primary">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold">লকার ও গ্রাহক তুলনা (Profit/Loss)</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="d-flex justify-content-between mb-4 border-bottom pb-3">
                    <div>
                        <h3 class="text-primary fw-bold mb-1" id="sd_locker_name"></h3>
                        <span class="text-danger fw-bold" id="sd_months_passed"></span>
                    </div>
                    <div class="text-end">
                        <span class="d-block text-muted">লকার এন্ট্রি: <span id="sd_shop_date" class="fw-bold text-dark"></span></span>
                        <span class="d-block text-muted">লকার ডেলিভারি: <span id="sd_shop_delivery" class="fw-bold text-danger"></span></span>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered text-center align-middle fs-6">
                        <thead class="table-light">
                            <tr>
                                <th class="text-start">বিবরণ</th>
                                <th class="text-danger">গ্রাহকের হিসাব (আমরা পাবো)</th>
                                <th class="text-primary">মহাজনের হিসাব (আমরা দেবো)</th>
                                <th class="text-success">পার্থক্য (আমাদের লাভ)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td class="fw-bold text-start">লোন (ঋণ)</td><td class="text-danger">৳<span id="sd_cust_loan"></span></td><td class="text-primary">৳<span id="sd_shop_loan"></span></td><td class="fw-bold">৳<span id="sd_diff_loan"></span></td></tr>
                            <tr><td class="fw-bold text-start">মোট লভ্যাংশ (বর্তমান পর্যন্ত)</td><td class="text-danger">৳<span id="sd_cust_remant"></span></td><td class="text-primary">৳<span id="sd_shop_remant"></span></td><td class="fw-bold">৳<span id="sd_diff_remant"></span></td></tr>
                            <tr class="table-secondary fs-5"><td class="fw-bold text-start">মোট হিসাব</td><td class="text-danger fw-bold">৳<span id="sd_cust_total"></span></td><td class="text-primary fw-bold">৳<span id="sd_shop_total"></span></td><td class="fw-bold text-success">৳<span id="sd_diff_total"></span></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ── Payment & Invoice Modal ──────────────────────────────────── -->
<div class="modal fade no-print" id="payModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content bg-light">
            <div class="modal-header bg-success text-white no-print">
                <h5 class="modal-title fw-bold">পেমেন্ট গ্রহণ ও ইনভয়েস</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" onclick="location.reload()"></button>
            </div>
            <div class="modal-body p-4">
                <!-- Payment Input -->
                <div class="bg-white p-4 rounded shadow-sm border mb-4 no-print">
                    <h4 class="text-center text-dark mb-4 fw-bold border-bottom pb-2">পেমেন্ট এন্ট্রি ও ইনভয়েস ডাউনলোড</h4>
                    <form id="payForm">
                        <input type="hidden" name="action" value="payLedger">
                        <input type="hidden" name="ledger_id" id="pay_ledger_id">
                        <div class="row align-items-center justify-content-center g-3">
                            <div class="col-md-3 text-center">
                                <div class="p-3 bg-danger bg-opacity-10 rounded">
                                    <div class="text-muted small">মোট বকেয়া</div>
                                    <h4 class="mb-0 text-danger fw-bold">৳<span id="inv_current_due_top">0.00</span></h4>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="fw-bold mb-1">জমার পরিমাণ (৳)</label>
                                <input type="number" step="0.01" min="0.01" name="payment_amount"
                                    class="form-control form-control-lg fw-bold text-center text-success fs-4"
                                    id="paymentInput" placeholder="0.00" required>
                            </div>
                            <div class="col-md-3">
                                <label class="fw-bold mb-1">পেমেন্টের তারিখ</label>
                                <input type="date" name="payment_date" id="payment_date_input"
                                    class="form-control form-control-lg" value="<?= date('Y-m-d') ?>">
                            </div>
                            <div class="col-md-2 d-grid align-self-end">
                                <button type="submit" class="btn btn-success btn-lg fw-bold" id="savePaymentBtn">সেভ করুন</button>
                            </div>
                        </div>
                    </form>
                    <div id="successMessage" style="display:none;" class="alert alert-success fw-bold mt-3 text-center fs-5">✅ পেমেন্ট সফলভাবে সংরক্ষিত হয়েছে!</div>
                    <div class="d-flex justify-content-center mt-4 pt-3 border-top">
                        <button type="button" class="btn btn-dark btn-lg me-3 px-5 fw-bold" onclick="window.print()">প্রিন্ট করুন</button>
                        <button type="button" class="btn btn-danger btn-lg px-5 fw-bold" onclick="downloadInvoicePDF()">PDF ডাউনলোড</button>
                    </div>
                </div>
                <!-- Invoice Print Area -->
                <div id="invoicePrintArea" class="invoice-print-area shadow-sm rounded bg-white">
                    <div class="text-center border-bottom pb-4 mb-4">
                        <h2 class="fw-bold" style="color:#D4AF37;">সোনার প্রাসাদ জুয়েলার্স</h2>
                        <p class="mb-1">১২২, নিউ মার্কেট সিটি কমপ্লেক্স, গ্রাউন্ড ফ্লোর, ঢাকা-১২০৫</p>
                        <p class="mb-0">মোবাইল: <?= en2bn('01700-000000') ?></p>
                    </div>
                    <div class="row mb-4">
                        <div class="col-6">
                            <h6 class="fw-bold text-muted">গ্রাহকের বিবরণ:</h6>
                            <h5 class="fw-bold text-dark" id="inv_cust_name"></h5>
                            <p class="mb-1">মোবাইল: <span id="inv_cust_phone"></span></p>
                            <p class="mb-1">NID: <span id="inv_cust_nid"></span></p>
                            <p class="mb-0">ঠিকানা: <span id="inv_cust_address"></span></p>
                        </div>
                        <div class="col-6 text-end">
                            <h6 class="fw-bold text-muted">ইনভয়েস বিবরণ:</h6>
                            <h5 class="fw-bold text-dark">রসিদ নং: <span id="inv_serial"></span></h5>
                            <p class="mb-1">প্রিন্ট তারিখ: <?= en2bn(date('d-m-Y')) ?></p>
                            <p class="mb-1">এন্ট্রির তারিখ: <span id="inv_date"></span></p>
                            <p class="mb-1 text-primary fw-bold">পেমেন্টের তারিখ: <span id="inv_payment_date_display"></span></p>
                            <p class="mb-1 text-danger fw-bold">পরবর্তী পেমেন্ট তারিখ: <span id="inv_next_date"></span></p>
                        </div>
                    </div>
                    <div class="table-responsive mb-4">
                        <table class="table table-bordered text-center align-middle">
                            <thead class="table-light">
                                <tr><th>গহনার বিবরণ</th><th>গহনার মূল্য (৳)</th><th>লোন (ঋণ) (৳)</th><th>মোট লভ্যাংশ (৳)</th><th>মোট হিসাব (৳)</th></tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td id="inv_jewelry"></td>
                                    <td id="inv_amount"></td>
                                    <td id="inv_loan" class="text-danger fw-bold"></td>
                                    <td id="inv_remant" class="text-success fw-bold"></td>
                                    <td id="inv_total_calc" class="fw-bold bg-light"></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="row justify-content-end">
                        <div class="col-md-5">
                            <table class="table table-sm table-borderless">
                                <tr><td class="text-end fw-bold">মোট পাওনা:</td><td class="text-end fw-bold fs-5">৳<span id="inv_total_due_display"></span></td></tr>
                                <tr class="border-bottom"><td class="text-end text-muted">পূর্বে জমা:</td><td class="text-end text-muted">৳<span id="inv_paid_already"></span></td></tr>
                                <tr class="border-bottom"><td class="text-end text-muted small">পূর্বের জমার তারিখ:</td><td class="text-end text-muted small"><span id="inv_last_payment_date"></span></td></tr>
                                <tr><td class="text-end fw-bold text-danger pt-2">বর্তমান বকেয়া:</td><td class="text-end fw-bold text-danger fs-4 pt-2">৳<span id="inv_current_due"></span></td></tr>
                                <tr id="newPaymentRow" style="display:none;" class="border-top border-dark">
                                    <td class="text-end fw-bold text-success pt-3">নতুন জমা:</td>
                                    <td class="text-end fw-bold text-success fs-4 pt-3">৳<span id="inv_new_payment_text"></span></td>
                                </tr>
                                <tr id="finalDueRow" style="display:none;">
                                    <td class="text-end fw-bold text-danger">অবশিষ্ট বকেয়া:</td>
                                    <td class="text-end fw-bold text-danger fs-4">৳<span id="inv_final_due_text"></span></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    <div class="mt-5 pt-5 row text-center border-top no-print" style="opacity:0.5;">
                        <div class="col-6"><p>গ্রাহকের স্বাক্ষর</p></div>
                        <div class="col-6"><p>কর্তৃপক্ষের স্বাক্ষর</p></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ── Gold Rate Modal ───────────────────────────────────────────── -->
<div class="modal fade no-print" id="rateModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-dark text-warning">
                <h5 class="modal-title">স্বর্ণের বর্তমান দাম আপডেট ও ইতিহাস</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Add Rate Form -->
                <form action="gold_dashboard.php" method="POST" class="row g-3 mb-4 border-bottom pb-4">
                    <input type="hidden" name="action" value="storeRate">
                    <div class="col-12 mb-2">
                        <label class="fw-bold">নতুন তারিখ *</label>
                        <input type="date" name="date" class="form-control w-25" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="text-primary fw-bold">২২ ক্যারেট (প্রতি ভরি) ৳</label>
                        <input type="number" step="0.01" id="bhori_22" class="form-control border-primary" oninput="calcGram('22')">
                        <label class="text-dark mt-2">২২ ক্যারেট (প্রতি গ্রাম) ৳ *</label>
                        <input type="number" step="0.01" name="carat_22_per_gram" id="gram_22" class="form-control bg-light" oninput="calcBhori('22')" required>
                    </div>
                    <div class="col-md-3 border-start">
                        <label class="text-primary fw-bold">২১ ক্যারেট (প্রতি ভরি) ৳</label>
                        <input type="number" step="0.01" id="bhori_21" class="form-control border-primary" oninput="calcGram('21')">
                        <label class="text-dark mt-2">২১ ক্যারেট (প্রতি গ্রাম) ৳ *</label>
                        <input type="number" step="0.01" name="carat_21_per_gram" id="gram_21" class="form-control bg-light" oninput="calcBhori('21')" required>
                    </div>
                    <div class="col-md-3 mt-4">
                        <label class="text-primary fw-bold">১৮ ক্যারেট (প্রতি ভরি) ৳</label>
                        <input type="number" step="0.01" id="bhori_18" class="form-control border-primary" oninput="calcGram('18')">
                        <label class="text-dark mt-2">১৮ ক্যারেট (প্রতি গ্রাম) ৳ *</label>
                        <input type="number" step="0.01" name="carat_18_per_gram" id="gram_18" class="form-control bg-light" oninput="calcBhori('18')" required>
                    </div>
                    <div class="col-md-3 border-start mt-4">
                        <label class="text-primary fw-bold">সনাতন (প্রতি ভরি) ৳</label>
                        <input type="number" step="0.01" id="bhori_trad" class="form-control border-primary" oninput="calcGram('trad')">
                        <label class="text-dark mt-2">সনাতন (প্রতি গ্রাম) ৳ *</label>
                        <input type="number" step="0.01" name="traditional_per_gram" id="gram_trad" class="form-control bg-light" oninput="calcBhori('trad')" required>
                    </div>
                    <div class="col-12 mt-4 text-center">
                        <button type="submit" class="btn bg-gold px-5 py-2 fw-bold fs-5">নতুন দাম সেভ করুন</button>
                    </div>
                </form>
                <!-- Rate History Table -->
                <h6 class="text-danger fw-bold mb-3">পূর্ববর্তী স্বর্ণের দাম (History)</h6>
                <div class="table-responsive">
                <table class="table table-bordered text-center align-middle" style="font-size:14px;">
                    <thead class="table-dark sticky-top">
                        <tr><th>সময়কাল</th><th>২২ ক্যারেট</th><th>২১ ক্যারেট</th><th>১৮ ক্যারেট</th><th>সনাতন</th><th>অ্যাকশন</th></tr>
                    </thead>
                    <tbody>
                    <?php for ($i = 0; $i < count($allRates); $i++):
                        $r        = $allRates[$i];
                        $fromDate = date('d-m-Y', strtotime($r['date']));
                        $toDate   = ($i === 0) ? 'বর্তমান' : date('d-m-Y', strtotime('-1 day', strtotime($allRates[$i-1]['date'])));
                        $safe_r   = htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8');
                    ?>
                    <tr>
                        <td class="fw-bold bg-light"><?= en2bn($fromDate) ?> হতে <?= en2bn($toDate) ?></td>
                        <td><span class="d-block text-primary fw-bold fs-6">৳<?= en2bn(number_format($r['carat_22_per_gram']*11.664,2)) ?></span><span class="d-block text-muted" style="font-size:11px;">গ্রাম: ৳<?= en2bn(number_format($r['carat_22_per_gram'],2)) ?></span></td>
                        <td><span class="d-block text-primary fw-bold fs-6">৳<?= en2bn(number_format($r['carat_21_per_gram']*11.664,2)) ?></span><span class="d-block text-muted" style="font-size:11px;">গ্রাম: ৳<?= en2bn(number_format($r['carat_21_per_gram'],2)) ?></span></td>
                        <td><span class="d-block text-primary fw-bold fs-6">৳<?= en2bn(number_format($r['carat_18_per_gram']*11.664,2)) ?></span><span class="d-block text-muted" style="font-size:11px;">গ্রাম: ৳<?= en2bn(number_format($r['carat_18_per_gram'],2)) ?></span></td>
                        <td><span class="d-block text-primary fw-bold fs-6">৳<?= en2bn(number_format($r['traditional_per_gram']*11.664,2)) ?></span><span class="d-block text-muted" style="font-size:11px;">গ্রাম: ৳<?= en2bn(number_format($r['traditional_per_gram'],2)) ?></span></td>
                        <td>
                            <div class="d-flex justify-content-center gap-1 flex-column">
                                <button type="button" class="btn btn-sm btn-dark" data-row='<?= $safe_r ?>' onclick="openEditRateModal(this)">এডিট</button>
                                <form action="gold_dashboard.php" method="POST" onsubmit="return confirm('আপনি কি নিশ্চিত?');">
                                    <input type="hidden" name="action" value="deleteRate">
                                    <input type="hidden" name="rate_id" value="<?= $r['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-danger w-100">ডিলিট</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endfor; ?>
                    </tbody>
                </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ── Edit Rate Modal ───────────────────────────────────────────── -->
<div class="modal fade no-print" id="editRateModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form action="gold_dashboard.php" method="POST" class="modal-content">
            <input type="hidden" name="action" value="updateRate">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold">স্বর্ণের দাম এডিট করুন</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body row g-3">
                <input type="hidden" name="rate_id" id="edit_rate_id">
                <div class="col-12"><label>তারিখ *</label><input type="date" name="date" id="edit_rate_date" class="form-control" required></div>
                <div class="col-md-6">
                    <label class="text-primary fw-bold">২২ ক্যারেট (প্রতি ভরি) ৳</label><input type="number" step="0.01" id="edit_bhori_22" class="form-control" oninput="calcEditRateGram('22')">
                    <label class="text-dark mt-2">২২ ক্যারেট (প্রতি গ্রাম) ৳ *</label><input type="number" step="0.01" name="carat_22_per_gram" id="edit_gram_22" class="form-control bg-light" oninput="calcEditRateBhori('22')" required>
                </div>
                <div class="col-md-6 border-start">
                    <label class="text-primary fw-bold">২১ ক্যারেট (প্রতি ভরি) ৳</label><input type="number" step="0.01" id="edit_bhori_21" class="form-control" oninput="calcEditRateGram('21')">
                    <label class="text-dark mt-2">২১ ক্যারেট (প্রতি গ্রাম) ৳ *</label><input type="number" step="0.01" name="carat_21_per_gram" id="edit_gram_21" class="form-control bg-light" oninput="calcEditRateBhori('21')" required>
                </div>
                <div class="col-md-6 mt-4">
                    <label class="text-primary fw-bold">১৮ ক্যারেট (প্রতি ভরি) ৳</label><input type="number" step="0.01" id="edit_bhori_18" class="form-control" oninput="calcEditRateGram('18')">
                    <label class="text-dark mt-2">১৮ ক্যারেট (প্রতি গ্রাম) ৳ *</label><input type="number" step="0.01" name="carat_18_per_gram" id="edit_gram_18" class="form-control bg-light" oninput="calcEditRateBhori('18')" required>
                </div>
                <div class="col-md-6 border-start mt-4">
                    <label class="text-primary fw-bold">সনাতন (প্রতি ভরি) ৳</label><input type="number" step="0.01" id="edit_bhori_trad" class="form-control" oninput="calcEditRateGram('trad')">
                    <label class="text-dark mt-2">সনাতন (প্রতি গ্রাম) ৳ *</label><input type="number" step="0.01" name="traditional_per_gram" id="edit_gram_trad" class="form-control bg-light" oninput="calcEditRateBhori('trad')" required>
                </div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-dark w-100">আপডেট করুন</button></div>
        </form>
    </div>
</div>

<!-- ── New Ledger Entry Modal ────────────────────────────────────── -->
<div class="modal fade no-print" id="ledgerModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <form action="gold_dashboard.php" method="POST" class="modal-content">
            <input type="hidden" name="action" value="storeLedger">
            <div class="modal-header bg-gold">
                <h5 class="modal-title fw-bold">নতুন লেজার / লকার এন্ট্রি</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="fw-bold">গ্রাহক নির্বাচন করুন *</label>
                        <select name="customer_id" id="customer_id_select" class="form-select" required>
                            <option value=""></option>
                            <?php foreach ($customers as $c): ?>
                            <option value="<?= $c['id'] ?>"
                                data-search="<?= htmlspecialchars($c['name'] ?? '') ?> <?= htmlspecialchars($c['primary_phone'] ?? '') ?> <?= $c['id'] ?>">
                                <?= en2bn($c['id']) ?> - <?= htmlspecialchars($c['name'] ?? '') ?> (<?= en2bn(htmlspecialchars($c['primary_phone'] ?? '')) ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="fw-bold">তারিখ *</label>
                        <input type="date" name="date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="col-12">
                        <label class="fw-bold">গহনার ধরন / বিবরণ *</label>
                        <input type="text" name="jewelry_type" class="form-control" placeholder="যেমন: ২২ ক্যারেট চেইন, ২ ভরি" required>
                    </div>
                    <div class="col-md-4">
                        <label>গহনার মূল্য (ঐচ্ছিক) ৳</label>
                        <input type="number" name="amount" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label>ডেলিভারি তারিখ</label>
                        <input type="date" name="delivery_date" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label>লকার নং (KD/PD লেভেল-২)</label>
                        <select name="locker_no" id="locker_no_select" class="form-select">
                            <option value="">-- লকার নির্বাচন করুন --</option>
                            <?php foreach ($locations as $loc): ?>
                            <option value="<?= htmlspecialchars($loc['loc_code'] ?? '') ?>"
                                data-search="<?= htmlspecialchars($loc['loc_code'] ?? '') ?> <?= htmlspecialchars($loc['location_name'] ?? '') ?>">
                                <?= htmlspecialchars($loc['location_name'] ?? '') ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <!-- Customer Ledger -->
                    <div class="col-12 mt-3"><h6 class="border-bottom pb-2 text-danger fw-bold">গ্রাহকের হিসাব (Customer Ledger)</h6></div>
                    <div class="col-md-4">
                        <label>গ্রাহকের লোন (ঋণ) ৳</label>
                        <input type="number" step="0.01" name="loan_amount" id="formLoan" class="form-control border-danger" value="0" oninput="updateLiveEquations()">
                    </div>
                    <div class="col-md-4">
                        <label>গ্রাহকের মাসিক সুদের হার (%)</label>
                        <input type="number" step="0.01" name="interest_rate" id="formRate" class="form-control border-danger" value="0" oninput="updateLiveEquations()">
                    </div>
                    <div class="col-md-4">
                        <label>গ্রাহকের প্রাথমিক লভ্যাংশ ৳</label>
                        <input type="number" step="0.01" name="remant" id="formRemant" class="form-control bg-light" readonly>
                    </div>
                    <!-- Shop Ledger (hidden until locker selected) -->
                    <div id="shop_ledger_block" class="col-12 mt-3 bg-light p-3 border rounded" style="display:none;">
                        <h6 class="border-bottom pb-2 text-primary fw-bold">মহাজন / লকার হিসাব (Shop Ledger)</h6>
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label>লকার এন্ট্রি তারিখ</label>
                                <input type="date" name="shop_date" class="form-control border-primary" value="<?= date('Y-m-d') ?>">
                            </div>
                            <div class="col-md-3">
                                <label>লকার ডেলিভারি তারিখ</label>
                                <input type="date" name="shop_delivery_date" class="form-control border-primary">
                            </div>
                            <div class="col-md-3">
                                <label>মহাজন থেকে লোন (ঋণ) ৳</label>
                                <input type="number" step="0.01" name="shop_loan_amount" id="formShopLoan" class="form-control border-primary" value="0" oninput="updateLiveEquations()">
                            </div>
                            <div class="col-md-3">
                                <label>মহাজনের সুদের হার (%)</label>
                                <input type="number" step="0.01" name="shop_interest_rate" id="formShopRate" class="form-control border-primary" value="0" oninput="updateLiveEquations()">
                            </div>
                            <div class="col-12">
                                <label>মহাজনের মাসিক লভ্যাংশ ৳</label>
                                <input type="number" step="0.01" name="shop_remant" id="formShopRemant" class="form-control bg-white" readonly>
                            </div>
                        </div>
                        <div class="alert alert-warning mt-3 mb-0 text-center fw-bold" id="equation_display">দয়া করে ঋণ এবং সুদের হার লিখুন</div>
                    </div>
                </div>
                <!-- Customer History (ajax) -->
                <div id="customerHistoryContainer" class="mt-4" style="display:none;">
                    <h6 class="border-bottom pb-2 text-dark fw-bold">এই গ্রাহকের পূর্ববর্তী লেজার ইতিহাস</h6>
                    <div class="table-responsive">
                    <table class="table table-sm table-bordered text-center" style="font-size:13px;">
                        <thead class="table-secondary">
                            <tr><th>ক্রমিক</th><th>তারিখ</th><th>গহনা</th><th>লোন</th><th>লভ্যাংশ</th><th>জমা</th><th>বকেয়া</th></tr>
                        </thead>
                        <tbody id="historyTableBody"></tbody>
                    </table>
                    </div>
                </div>
                <!-- Locker History (ajax) -->
                <div id="lockerHistoryContainer" class="mt-4" style="display:none;">
                    <h6 class="border-bottom pb-2 text-primary fw-bold">এই লকারের পূর্ববর্তী লেজার ইতিহাস</h6>
                    <div class="table-responsive">
                    <table class="table table-sm table-bordered text-center" style="font-size:13px;">
                        <thead class="table-primary">
                            <tr><th>ক্রমিক</th><th>গ্রাহক</th><th>লকার এন্ট্রি</th><th>লকার ডেলিভারি</th><th>মহাজনের লোন</th><th>মোট লভ্যাংশ</th><th>মোট পাওনা</th></tr>
                        </thead>
                        <tbody id="lockerHistoryTableBody"></tbody>
                    </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">বাতিল</button>
                <button type="submit" class="btn btn-dark px-5">সেভ করুন</button>
            </div>
        </form>
    </div>
</div>

<!-- ── Edit Ledger Modal ─────────────────────────────────────────── -->
<div class="modal fade no-print" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <form action="gold_dashboard.php" method="POST" class="modal-content">
            <input type="hidden" name="action" value="updateLedger">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold">লেজার এডিট করুন</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="ledger_id" id="edit_ledger_id">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label>তারিখ *</label>
                        <input type="date" name="date" id="edit_date" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label>লকার নং</label>
                        <select name="locker_no" id="edit_locker_no" class="form-select">
                            <option value="">-- লকার নির্বাচন করুন --</option>
                            <?php foreach ($locations as $loc): ?>
                            <option value="<?= htmlspecialchars($loc['loc_code'] ?? '') ?>"
                                data-search="<?= htmlspecialchars($loc['loc_code'] ?? '') ?> <?= htmlspecialchars($loc['location_name'] ?? '') ?>">
                                <?= htmlspecialchars($loc['location_name'] ?? '') ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label>গহনার ধরন / বিবরণ *</label>
                        <input type="text" name="jewelry_type" id="edit_jewelry_type" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label>গহনার মূল্য ৳</label>
                        <input type="number" name="amount" id="edit_amount" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label>ডেলিভারি তারিখ</label>
                        <input type="date" name="delivery_date" id="edit_delivery_date" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label>লোন (ঋণ) ৳</label>
                        <input type="number" step="0.01" name="loan_amount" id="edit_loan" class="form-control" oninput="updateEditLiveEquations()">
                    </div>
                    <div class="col-md-4">
                        <label>সুদের হার (%)</label>
                        <input type="number" step="0.01" name="interest_rate" id="edit_rate" class="form-control" oninput="updateEditLiveEquations()">
                    </div>
                    <div class="col-md-4">
                        <label>লভ্যাংশ ৳</label>
                        <input type="number" step="0.01" name="remant" id="edit_remant" class="form-control bg-light" readonly>
                    </div>
                    <!-- Edit Shop Ledger Block -->
                    <div id="edit_shop_ledger_block" class="col-12 mt-3 bg-light p-3 border rounded" style="display:none;">
                        <h6 class="border-bottom pb-2 text-primary fw-bold">মহাজন / লকার হিসাব (Shop Ledger)</h6>
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label>লকার এন্ট্রি তারিখ</label>
                                <input type="date" name="shop_date" id="edit_shop_date" class="form-control border-primary">
                            </div>
                            <div class="col-md-3">
                                <label>লকার ডেলিভারি তারিখ</label>
                                <input type="date" name="shop_delivery_date" id="edit_shop_delivery_date" class="form-control border-primary">
                            </div>
                            <div class="col-md-3">
                                <label>মহাজনের লোন (ঋণ) ৳</label>
                                <input type="number" step="0.01" name="shop_loan_amount" id="edit_shop_loan" class="form-control border-primary" oninput="updateEditLiveEquations()">
                            </div>
                            <div class="col-md-3">
                                <label>মহাজনের সুদের হার (%)</label>
                                <input type="number" step="0.01" name="shop_interest_rate" id="edit_shop_rate" class="form-control border-primary" oninput="updateEditLiveEquations()">
                            </div>
                            <div class="col-12">
                                <label>মহাজনের মাসিক লভ্যাংশ ৳</label>
                                <input type="number" step="0.01" name="shop_remant" id="edit_shop_remant" class="form-control bg-white" readonly>
                            </div>
                        </div>
                        <div class="alert alert-warning mt-3 mb-0 text-center fw-bold" id="edit_equation_display"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-dark w-100">আপডেট করুন</button>
            </div>
        </form>
    </div>
</div>


<!-- ================================================================
     SCRIPTS
================================================================ -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

<script>
// ── Utility helpers ──────────────────────────────────────────────
function en2bnJS(n) { const b=['০','১','২','৩','৪','৫','৬','৭','৮','৯']; return String(n).replace(/[0-9]/g,w=>b[+w]); }
function fmtDate(s) { if(!s)return'-'; const p=s.split('-'); return p.length===3?`${p[2]}-${p[1]}-${p[0]}`:s; }
function fmtMoney(v) { return en2bnJS(parseFloat(v||0).toFixed(2)); }

// ── Pagination & Filter ──────────────────────────────────────────
window.filteredRows = [];
window.currentPage  = 1;
const ROWS_PER_PAGE = 30;

function applyDashboardFilters() {
    const input    = document.getElementById('dashSearch').value.toLowerCase();
    const fromDate = document.getElementById('dashFromDate').value;
    const toDate   = document.getElementById('dashToDate').value;
    const fd = fromDate ? new Date(fromDate) : null;
    const td = toDate   ? new Date(toDate)   : null;

    const allRows = Array.from(document.querySelectorAll('#mainLedgerTable tbody tr.ledger-row'));
    window.filteredRows = [];

    allRows.forEach(row => {
        if (row.classList.contains('is-closed')) { row.style.display = 'none'; return; }
        const idText   = (row.querySelector('td:nth-child(2)') || {}).textContent?.toLowerCase() || '';
        const nameText = (row.querySelector('td:nth-child(3)') || {}).textContent?.toLowerCase() || '';
        const rowDate  = new Date(row.dataset.date);
        let show = true;
        if (input && !(idText.includes(input) || nameText.includes(input))) show = false;
        if (fd && rowDate < fd) show = false;
        if (td && rowDate > td) show = false;
        if (show) { window.filteredRows.push(row); } else { row.style.display = 'none'; }
    });

    calculateSummaryArray(window.filteredRows);
    renderPage(1);
}

function renderPage(page) {
    const totalPages = Math.ceil(window.filteredRows.length / ROWS_PER_PAGE);
    if (page < 1) page = 1;
    if (page > totalPages && totalPages > 0) page = totalPages;
    window.currentPage = page;
    window.filteredRows.forEach((row, i) => {
        row.style.display = (i >= (page-1)*ROWS_PER_PAGE && i < page*ROWS_PER_PAGE) ? 'table-row' : 'none';
    });
    buildPaginationUI(totalPages, page);
}

function buildPaginationUI(total, current) {
    const ul = document.getElementById('paginationControls');
    ul.innerHTML = '';
    if (total <= 1) return;
    ul.innerHTML += `<li class="page-item ${current===1?'disabled':''}"><a class="page-link text-dark" href="#" onclick="renderPage(${current-1});return false;">পূর্ববর্তী</a></li>`;
    const s = Math.max(1, current-2), e = Math.min(total, current+2);
    for (let i = s; i <= e; i++) {
        ul.innerHTML += `<li class="page-item ${i===current?'active':''}"><a class="page-link ${i===current?'bg-dark border-dark':'text-dark'}" href="#" onclick="renderPage(${i});return false;">${en2bnJS(i)}</a></li>`;
    }
    ul.innerHTML += `<li class="page-item ${current===total?'disabled':''}"><a class="page-link text-dark" href="#" onclick="renderPage(${current+1});return false;">পরবর্তী</a></li>`;
}

function resetDashboardFilters() {
    document.getElementById('dashSearch').value   = '';
    document.getElementById('dashFromDate').value = '';
    document.getElementById('dashToDate').value   = '';
    applyDashboardFilters();
}

function calculateSummaryArray(rows) {
    let cL=0,cR=0,cP=0,cD=0,sL=0,sR=0;
    rows.forEach(r=>{
        cL += parseFloat(r.dataset.loan)||0;
        cR += parseFloat(r.dataset.remant)||0;
        cP += parseFloat(r.dataset.paid)||0;
        cD += parseFloat(r.dataset.due)||0;
        sL += parseFloat(r.dataset.shopLoan)||0;
        sR += parseFloat(r.dataset.shopRemant)||0;
    });
    document.getElementById('sum_loan_remant').innerHTML = `লোন: ৳${fmtMoney(cL)}<br>লভ্যাংশ: ৳${fmtMoney(cR)}`;
    document.getElementById('sum_paid_due').innerHTML    = `জমা: ৳${fmtMoney(cP)}<br>বকেয়া: ৳${fmtMoney(cD)}`;
    document.getElementById('sum_shop').innerHTML        = `মহাজনের লোন: ৳${fmtMoney(sL)}<br>মহাজনের লভ্যাংশ: ৳${fmtMoney(sR)}`;
}

window.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('#mainLedgerTable tbody tr.ledger-row').forEach((r,i) => r.dataset.origIndex = i);
    applyDashboardFilters();
});

// Trigger auto-SMS silently on page load
$(document).ready(function() {
    fetch('gold_dashboard.php?action=triggerAutoSMS').then(r=>r.text()).catch(()=>{});
});

// ── Select2 Init ─────────────────────────────────────────────────
function customSearchMatcher(params, data) {
    if ($.trim(params.term) === '') return data;
    if (!data.text) return null;
    const term = params.term.toLowerCase();
    if (data.text.toLowerCase().includes(term)) return data;
    const ds = $(data.element).data('search');
    if (ds && String(ds).toLowerCase().includes(term)) return data;
    return null;
}

$(function() {
    $('#customer_id_select').select2({ theme:'bootstrap-5', dropdownParent:$('#ledgerModal'), placeholder:'-- গ্রাহক নির্বাচন করুন --', width:'100%', matcher:customSearchMatcher })
        .on('change', function(){ fetchCustomerHistory(this.value); });
    $('#locker_no_select').select2({ theme:'bootstrap-5', dropdownParent:$('#ledgerModal'), placeholder:'-- লকার নির্বাচন করুন (ঐচ্ছিক) --', width:'100%', matcher:customSearchMatcher })
        .on('change', function(){ const v=$(this).val(); toggleShopLedger(v); fetchLockerHistory(v); });
    $('#edit_locker_no').select2({ theme:'bootstrap-5', dropdownParent:$('#editModal'), placeholder:'-- লকার নির্বাচন করুন (ঐচ্ছিক) --', width:'100%', matcher:customSearchMatcher })
        .on('change', function(){ toggleEditShopLedger($(this).val()); });
    $('#filterCustomer').select2({ theme:'bootstrap-5', dropdownParent:$('#reportModal'), placeholder:'-- সকল গ্রাহক --', width:'100%' });
    $('#filterLocker').select2({ theme:'bootstrap-5', dropdownParent:$('#reportModal'), placeholder:'-- সকল লকার --', width:'100%' });
});

// ── Bhori / Gram Conversion ──────────────────────────────────────
const BHORI = 11.664;
function calcBhori(t)        { document.getElementById('bhori_'+t).value     = (parseFloat(document.getElementById('gram_'+t).value||0)*BHORI).toFixed(2); }
function calcGram(t)          { document.getElementById('gram_'+t).value      = (parseFloat(document.getElementById('bhori_'+t).value||0)/BHORI).toFixed(2); }
function calcEditRateBhori(t) { document.getElementById('edit_bhori_'+t).value = (parseFloat(document.getElementById('edit_gram_'+t).value||0)*BHORI).toFixed(2); }
function calcEditRateGram(t)  { document.getElementById('edit_gram_'+t).value  = (parseFloat(document.getElementById('edit_bhori_'+t).value||0)/BHORI).toFixed(2); }

// ── Rate Modal ───────────────────────────────────────────────────
function openEditRateModal(btn) {
    const d = JSON.parse(btn.getAttribute('data-row'));
    document.getElementById('edit_rate_id').value    = d.id;
    document.getElementById('edit_rate_date').value  = d.date;
    document.getElementById('edit_gram_22').value    = d.carat_22_per_gram;
    document.getElementById('edit_gram_21').value    = d.carat_21_per_gram;
    document.getElementById('edit_gram_18').value    = d.carat_18_per_gram;
    document.getElementById('edit_gram_trad').value  = d.traditional_per_gram;
    ['22','21','18','trad'].forEach(calcEditRateBhori);
    new bootstrap.Modal(document.getElementById('editRateModal')).show();
}

// ── Shop Ledger Toggle ───────────────────────────────────────────
function toggleShopLedger(val) {
    document.getElementById('shop_ledger_block').style.display = val ? 'block' : 'none';
    if (!val) { document.getElementById('formShopLoan').value='0'; document.getElementById('formShopRate').value='0'; updateLiveEquations(); }
}
function toggleEditShopLedger(val) {
    document.getElementById('edit_shop_ledger_block').style.display = val ? 'block' : 'none';
    if (!val) { document.getElementById('edit_shop_loan').value='0'; document.getElementById('edit_shop_rate').value='0'; updateEditLiveEquations(); }
}

// ── Live Equation Display ────────────────────────────────────────
function updateLiveEquations() {
    const cL=parseFloat(document.getElementById('formLoan').value)||0;
    const cR=parseFloat(document.getElementById('formRate').value)||0;
    const cRem=(cL*cR)/100;
    document.getElementById('formRemant').value=cRem.toFixed(2);
    const sL=parseFloat(document.getElementById('formShopLoan').value)||0;
    const sR=parseFloat(document.getElementById('formShopRate').value)||0;
    const sRem=(sL*sR)/100;
    document.getElementById('formShopRemant').value=sRem.toFixed(2);
    document.getElementById('equation_display').innerHTML=
        `কাস্টমার ঋণ: ৳${fmtMoney(cL)} | মহাজন থেকে ঋণ: ৳${fmtMoney(sL)} | মূলধনের পার্থক্য: ৳${fmtMoney(cL-sL)}<br>`+
        `কাস্টমার লভ্যাংশ: ৳${fmtMoney(cRem)} | মহাজনের লভ্যাংশ: ৳${fmtMoney(sRem)} | নিট মাসিক লাভ: ৳${fmtMoney(cRem-sRem)}`;
}
function updateEditLiveEquations() {
    const cL=parseFloat(document.getElementById('edit_loan').value)||0;
    const cR=parseFloat(document.getElementById('edit_rate').value)||0;
    const cRem=(cL*cR)/100;
    document.getElementById('edit_remant').value=cRem.toFixed(2);
    const sL=parseFloat(document.getElementById('edit_shop_loan').value)||0;
    const sR=parseFloat(document.getElementById('edit_shop_rate').value)||0;
    const sRem=(sL*sR)/100;
    document.getElementById('edit_shop_remant').value=sRem.toFixed(2);
    document.getElementById('edit_equation_display').innerHTML=
        `কাস্টমার ঋণ: ৳${fmtMoney(cL)} | মহাজন থেকে ঋণ: ৳${fmtMoney(sL)} | মূলধনের পার্থক্য: ৳${fmtMoney(cL-sL)}<br>`+
        `কাস্টমার লভ্যাংশ: ৳${fmtMoney(cRem)} | মহাজনের লভ্যাংশ: ৳${fmtMoney(sRem)} | নিট মাসিক লাভ: ৳${fmtMoney(cRem-sRem)}`;
}

// ── Edit Ledger Modal ────────────────────────────────────────────
function openEditModal(btn) {
    const d = JSON.parse(btn.getAttribute('data-row'));
    document.getElementById('edit_ledger_id').value      = d.id;
    document.getElementById('edit_date').value           = d.date;
    document.getElementById('edit_jewelry_type').value   = d.jewelry_type;
    document.getElementById('edit_amount').value         = d.amount;
    document.getElementById('edit_delivery_date').value  = d.delivery_date || '';
    document.getElementById('edit_loan').value           = d.loan_amount;
    document.getElementById('edit_rate').value           = d.interest_rate;
    document.getElementById('edit_remant').value         = d.remant;
    $('#edit_locker_no').val(d.locker_code||'').trigger('change.select2');
    if (d.locker_code) {
        document.getElementById('edit_shop_date').value          = d.shop_date || '';
        document.getElementById('edit_shop_delivery_date').value = d.shop_delivery_date || '';
        document.getElementById('edit_shop_loan').value          = d.shop_loan_amount || 0;
        document.getElementById('edit_shop_rate').value          = d.shop_interest_rate || 0;
        toggleEditShopLedger(true);
    } else {
        toggleEditShopLedger(false);
    }
    updateEditLiveEquations();
    new bootstrap.Modal(document.getElementById('editModal')).show();
}

// ── Shop Details (Profit/Loss) Modal ─────────────────────────────
function openShopDetailsModal(btn) {
    const d = JSON.parse(btn.getAttribute('data-row'));
    document.getElementById('sd_locker_name').innerText   = d.locker_name || d.locker_code;
    document.getElementById('sd_months_passed').innerText = d.age_string ? '(' + d.age_string + ')' : '';
    document.getElementById('sd_shop_date').innerText     = en2bnJS(fmtDate(d.shop_date));
    document.getElementById('sd_shop_delivery').innerText = d.shop_delivery_date ? en2bnJS(fmtDate(d.shop_delivery_date)) : '-';
    const cLoan   = parseFloat(d.loan_amount || 0);
    const sLoan   = parseFloat(d.shop_loan_amount || 0);
    const cRemant = parseFloat(d.calculated_remant || 0);
    const sRemant = parseFloat(d.shop_calculated_remant || d.shop_remant || 0);
    const cTotal  = cLoan + cRemant;
    const sTotal  = sLoan + sRemant;
    document.getElementById('sd_cust_loan').innerText    = fmtMoney(cLoan);
    document.getElementById('sd_shop_loan').innerText    = fmtMoney(sLoan);
    document.getElementById('sd_diff_loan').innerText    = fmtMoney(cLoan - sLoan);
    document.getElementById('sd_cust_remant').innerText  = fmtMoney(cRemant);
    document.getElementById('sd_shop_remant').innerText  = fmtMoney(sRemant);
    document.getElementById('sd_diff_remant').innerText  = fmtMoney(cRemant - sRemant);
    document.getElementById('sd_cust_total').innerText   = fmtMoney(cTotal);
    document.getElementById('sd_shop_total').innerText   = fmtMoney(sTotal);
    document.getElementById('sd_diff_total').innerText   = fmtMoney(cTotal - sTotal);
    new bootstrap.Modal(document.getElementById('shopDetailsModal')).show();
}

// ── SMS Template Editing ─────────────────────────────────────────
function editTemplate(btn) {
    const tpl = JSON.parse(btn.getAttribute('data-tpl'));
    document.getElementById('tpl_action').value       = 'updateTemplate';
    document.getElementById('tpl_id').value           = tpl.id;
    document.getElementById('tpl_title').value        = tpl.title;
    document.getElementById('tpl_message').value      = tpl.message;
    document.getElementById('tpl_form_heading').innerText = 'টেমপ্লেট আপডেট করুন';
    document.getElementById('tpl_submit_btn').innerText   = 'আপডেট করুন';
    document.getElementById('tpl_cancel_btn').style.display = 'inline-block';
}
function cancelTemplateEdit() {
    document.getElementById('tpl_action').value       = 'saveTemplate';
    document.getElementById('tpl_id').value           = '';
    document.getElementById('tpl_title').value        = '';
    document.getElementById('tpl_message').value      = '';
    document.getElementById('tpl_form_heading').innerText = 'নতুন টেমপ্লেট তৈরি করুন';
    document.getElementById('tpl_submit_btn').innerText   = 'সেভ করুন';
    document.getElementById('tpl_cancel_btn').style.display = 'none';
}

// ── Manual SMS ───────────────────────────────────────────────────
function openManualSmsModal(ledger_id, phone, name) {
    document.getElementById('manual_sms_ledger_id').value = ledger_id;
    document.getElementById('manual_sms_phone').value     = phone;
    document.getElementById('manual_sms_name').innerText  = name;
    document.getElementById('manualSmsStatus').className  = 'alert alert-info text-center d-none fw-bold';
    bootstrap.Modal.getInstance(document.getElementById('messageCheckModal')).hide();
    new bootstrap.Modal(document.getElementById('manualSmsModal')).show();
}

document.getElementById('manualSmsForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const btn    = document.getElementById('manualSmsBtn');
    const status = document.getElementById('manualSmsStatus');
    btn.disabled = true; btn.innerText = 'পাঠানো হচ্ছে...';
    status.className = 'alert alert-info text-center fw-bold';
    status.innerText = 'অপেক্ষা করুন...';
    fetch('gold_dashboard.php', { method:'POST', body:new FormData(this) })
        .then(r => r.json()).then(res => {
            if (res.success) {
                status.className = 'alert alert-success text-center fw-bold';
                status.innerText = res.msg;
                setTimeout(() => location.reload(), 2000);
            } else {
                status.className = 'alert alert-danger text-center fw-bold';
                status.innerText = res.msg;
                btn.disabled = false; btn.innerText = 'এসএমএস পাঠান';
            }
        }).catch(() => {
            status.className = 'alert alert-danger text-center fw-bold';
            status.innerText = 'সার্ভার এরর!';
            btn.disabled = false; btn.innerText = 'এসএমএস পাঠান';
        });
});

// ── AJAX History Loaders ─────────────────────────────────────────
function fetchCustomerHistory(id) {
    const container = document.getElementById('customerHistoryContainer');
    const tbody     = document.getElementById('historyTableBody');
    if (!id) { container.style.display='none'; return; }
    fetch(`gold_dashboard.php?action=getCustomerHistory&id=${id}`)
        .then(r => r.json()).then(rows => {
            tbody.innerHTML = '';
            if (rows.length) {
                rows.forEach(row => {
                    const rem  = parseFloat(row.calculated_remant || row.remant || 0);
                    const loan = parseFloat(row.loan_amount || 0);
                    const paid = parseFloat(row.paid_amount || 0);
                    const due  = (loan + rem) - paid;
                    tbody.innerHTML += `<tr><td>${en2bnJS(row.serial_no)}</td><td>${en2bnJS(fmtDate(row.date))}</td><td>${row.jewelry_type}</td><td class="text-danger">${fmtMoney(loan)}</td><td class="text-success">${fmtMoney(rem)}</td><td class="text-primary">${fmtMoney(paid)}</td><td class="fw-bold text-danger">${fmtMoney(due)}</td></tr>`;
                });
            } else {
                tbody.innerHTML = '<tr><td colspan="7" class="text-muted py-3">এই গ্রাহকের পূর্বের কোনো এন্ট্রি নেই।</td></tr>';
            }
            container.style.display = 'block';
        });
}

function fetchLockerHistory(code) {
    const container = document.getElementById('lockerHistoryContainer');
    const tbody     = document.getElementById('lockerHistoryTableBody');
    if (!code) { container.style.display='none'; return; }
    fetch(`gold_dashboard.php?action=getLockerHistory&code=${code}`)
        .then(r => r.json()).then(rows => {
            tbody.innerHTML = '';
            if (rows.length) {
                rows.forEach(row => {
                    const loan = parseFloat(row.shop_loan_amount || 0);
                    const rem  = parseFloat(row.shop_calculated_remant || row.shop_remant || 0);
                    tbody.innerHTML += `<tr><td>${en2bnJS(row.serial_no)}</td><td>${row.customer_name}</td><td>${en2bnJS(fmtDate(row.shop_date))}</td><td>${en2bnJS(fmtDate(row.shop_delivery_date))}</td><td class="text-danger">${fmtMoney(loan)}</td><td class="text-success">${fmtMoney(rem)}</td><td class="fw-bold text-primary">${fmtMoney(loan+rem)}</td></tr>`;
                });
            } else {
                tbody.innerHTML = '<tr><td colspan="7" class="text-muted py-3">এই লকারের পূর্বের কোনো মহাজন লেজার এন্ট্রি নেই।</td></tr>';
            }
            container.style.display = 'block';
        });
}

// ── Payment Modal ────────────────────────────────────────────────
let currentInvoiceDue = 0, currentTR = null;

function openPayModal(btn) {
    const d = JSON.parse(btn.getAttribute('data-row'));
    currentTR = btn.closest('tr');
    document.getElementById('successMessage').style.display   = 'none';
    document.getElementById('newPaymentRow').style.display    = 'none';
    document.getElementById('finalDueRow').style.display      = 'none';
    document.getElementById('paymentInput').value             = '';
    document.getElementById('paymentInput').readOnly          = false;
    document.getElementById('savePaymentBtn').disabled        = false;
    document.getElementById('payment_date_input').value       = new Date().toISOString().slice(0,10);
    document.getElementById('pay_ledger_id').value            = d.id;
    document.getElementById('inv_cust_name').innerText        = d.debtor_ref || d.customer_name || '';
    document.getElementById('inv_cust_phone').innerText       = en2bnJS(d.phone || '');
    document.getElementById('inv_cust_nid').innerText         = d.nid_no ? en2bnJS(d.nid_no) : '-';
    document.getElementById('inv_cust_address').innerText     = d.address || '-';
    document.getElementById('inv_serial').innerText           = en2bnJS(d.serial_no || d.debtor_no || '');
    document.getElementById('inv_date').innerText             = en2bnJS(fmtDate(d.date));
    document.getElementById('inv_next_date').innerText        = d.delivery_date ? en2bnJS(fmtDate(d.delivery_date)) : '-';
    document.getElementById('inv_jewelry').innerText          = d.jewelry_type;
    document.getElementById('inv_amount').innerText           = fmtMoney(d.amount);
    document.getElementById('inv_loan').innerText             = fmtMoney(d.loan_amount);
    const remant     = parseFloat(d.calculated_remant || 0);
    const totalCalc  = parseFloat(d.loan_amount || 0) + remant;
    const paid       = parseFloat(d.paid_amount || 0);
    document.getElementById('inv_remant').innerText           = fmtMoney(remant);
    document.getElementById('inv_total_calc').innerText       = fmtMoney(totalCalc);
    document.getElementById('inv_total_due_display').innerText = fmtMoney(totalCalc);
    document.getElementById('inv_paid_already').innerText     = fmtMoney(paid);
    document.getElementById('inv_last_payment_date').innerText = d.last_payment_date ? en2bnJS(fmtDate(d.last_payment_date)) : '-';
    currentInvoiceDue = totalCalc - paid;
    document.getElementById('inv_current_due').innerText      = fmtMoney(currentInvoiceDue);
    document.getElementById('inv_current_due_top').innerText  = fmtMoney(currentInvoiceDue);
    document.getElementById('paymentInput').max               = currentInvoiceDue;
    document.getElementById('inv_payment_date_display').innerText = '-';
    new bootstrap.Modal(document.getElementById('payModal')).show();
}

document.getElementById('payForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const paymentVal  = parseFloat(document.getElementById('paymentInput').value);
    const paymentDate = document.getElementById('payment_date_input').value;
    if (!paymentVal || paymentVal <= 0) return alert('সঠিক পেমেন্টের পরিমাণ লিখুন।');
    if (paymentVal > currentInvoiceDue + 0.001) return alert('পেমেন্টের পরিমাণ বকেয়ার চেয়ে বেশি হতে পারবে না।');
    document.getElementById('savePaymentBtn').disabled = true;

    fetch('gold_dashboard.php', { method:'POST', body:new FormData(this) })
        .then(r => r.json()).then(res => {
            if (!res.success) {
                alert('পেমেন্ট সেভ হয়নি। আবার চেষ্টা করুন।');
                document.getElementById('savePaymentBtn').disabled = false;
                return;
            }
            const finalDue = currentInvoiceDue - paymentVal;
            document.getElementById('inv_payment_date_display').innerText = en2bnJS(fmtDate(paymentDate));
            document.getElementById('inv_new_payment_text').innerText     = fmtMoney(paymentVal);
            document.getElementById('inv_final_due_text').innerText       = fmtMoney(finalDue);
            document.getElementById('newPaymentRow').style.display        = 'table-row';
            document.getElementById('finalDueRow').style.display          = 'table-row';
            document.getElementById('successMessage').style.display       = 'block';
            document.getElementById('paymentInput').readOnly              = true;

            if (currentTR) {
                const currentPaid = parseFloat(document.getElementById('inv_paid_already').innerText.replace(/[^\d.]/g,'')) || 0;
                const newPaid = currentPaid + paymentVal;
                const payCell  = currentTR.querySelector('.pay-cell');
                if (payCell) payCell.innerHTML = `<small class="text-primary paid-label">জমা: ${fmtMoney(newPaid)}</small><br><small class="text-danger fw-bold due-label">বকেয়া: ${fmtMoney(finalDue)}</small>`;
                const lastCell = currentTR.querySelector('.last-pay-cell');
                if (lastCell) lastCell.innerHTML = `<span class="badge bg-success">${en2bnJS(fmtDate(paymentDate))}</span><br><small class="text-muted">${fmtMoney(paymentVal)} ৳</small>`;
                currentTR.dataset.paid = newPaid;
                currentTR.dataset.due  = finalDue;
                if (finalDue <= 0) {
                    currentTR.classList.add('is-closed');
                    applyDashboardFilters();
                } else {
                    calculateSummaryArray(window.filteredRows);
                }
            }
        }).catch(err => {
            alert('সার্ভারে সমস্যা হয়েছে: ' + err.message);
            document.getElementById('savePaymentBtn').disabled = false;
        });
});

// ── PDF Export ───────────────────────────────────────────────────
function downloadInvoicePDF() {
    html2pdf().from(document.getElementById('invoicePrintArea')).set({
        margin: 10, filename:'invoice_sonar_prasad.pdf',
        html2canvas:{ scale:2 }, jsPDF:{ unit:'mm', format:'a4', orientation:'portrait' }
    }).save().then(() => { location.reload(); });
}

function generateFilteredPDF(reportType = 'normal') {
    const from   = document.getElementById('filterFrom').value;
    const to     = document.getElementById('filterTo').value;
    let cust     = document.getElementById('filterCustomer').value;
    let lock     = document.getElementById('filterLocker').value;
    const status = document.getElementById('filterStatus').value;
    if (reportType === 'customer') { cust=''; lock=''; }
    if (reportType === 'locker')   { lock=''; cust=''; }

    const fd = from ? new Date(from) : null;
    const td = to   ? new Date(to)   : null;

    const reportElement = document.getElementById('customReportArea');
    const tbody         = document.querySelector('#mainLedgerTable tbody');
    const rowsArray     = Array.from(tbody.querySelectorAll('tr.ledger-row'));
    const pdfVisibleRows = [];

    reportElement.classList.add('pdf-export-active');

    rowsArray.forEach(r => {
        const rowDate  = new Date(r.dataset.date);
        const isClosed = r.classList.contains('is-closed');
        let show = true;
        if (fd && rowDate < fd)        show = false;
        if (td && rowDate > td)        show = false;
        if (cust && r.dataset.customer !== cust) show = false;
        if (lock && r.dataset.locker  !== lock)  show = false;
        if (status === 'running' && isClosed)    show = false;
        if (status === 'closed'  && !isClosed)   show = false;
        if (show) { r.style.display='table-row'; r.classList.remove('d-none'); pdfVisibleRows.push(r); }
        else { r.style.display='none'; }
    });

    if (pdfVisibleRows.length === 0) {
        alert('এই ফিল্টারের জন্য কোনো ডেটা পাওয়া যায়নি।');
        reportElement.classList.remove('pdf-export-active');
        applyDashboardFilters();
        return;
    }

    if (reportType === 'customer') {
        rowsArray.sort((a,b) => a.querySelector('td:nth-child(3)').innerText.localeCompare(b.querySelector('td:nth-child(3)').innerText));
        rowsArray.forEach(r => tbody.appendChild(r));
    } else if (reportType === 'locker') {
        rowsArray.sort((a,b) => a.querySelector('td:nth-child(10)').innerText.localeCompare(b.querySelector('td:nth-child(10)').innerText));
        rowsArray.forEach(r => tbody.appendChild(r));
    }

    calculateSummaryArray(pdfVisibleRows);

    let title = 'লেজার রিপোর্ট';
    if (reportType === 'customer') title = 'সকল গ্রাহক অনুযায়ী লেজার রিপোর্ট';
    else if (reportType === 'locker') title = 'সকল লকার অনুযায়ী লেজার রিপোর্ট';
    else {
        title += cust ? ' - গ্রাহক: ' + ($('#filterCustomer option:selected').text().split('-')[1]||'').trim() : ' - সকল গ্রাহক';
        title += lock ? ' - লকার: ' + $('#filterLocker option:selected').text().trim() : ' - সকল লকার';
    }
    if (from && to) title += ` (${en2bnJS(fmtDate(from))} হতে ${en2bnJS(fmtDate(to))})`;
    else if (from)  title += ` (${en2bnJS(fmtDate(from))} হতে বর্তমান)`;
    else if (to)    title += ` (শুরু হতে ${en2bnJS(fmtDate(to))})`;
    if (status === 'all')    title += ' (সম্পূর্ণ হিস্ট্রি)';
    else if (status==='closed') title += ' (পরিশোধিত)';
    else title += ' (রানিং)';

    document.getElementById('reportTitle').innerText = title;

    const paginDiv = document.getElementById('paginationControls');
    const filterDiv = document.getElementById('dashboardFilterContainer');
    if (paginDiv)  paginDiv.style.display  = 'none';
    if (filterDiv) filterDiv.style.display = 'none';

    html2pdf().from(reportElement).set({
        margin: [10,5,10,5], filename:'ledger_report.pdf',
        image: { type:'jpeg', quality:0.98 },
        html2canvas: { scale:2, useCORS:true, windowWidth:1400 },
        jsPDF: { unit:'mm', format:'a4', orientation:'landscape' },
        pagebreak: { mode:['css','legacy'] }
    }).save().then(() => {
        reportElement.classList.remove('pdf-export-active');
        if (paginDiv)  paginDiv.style.display  = 'flex';
        if (filterDiv) filterDiv.style.display = 'flex';
        rowsArray.sort((a,b) => parseInt(a.dataset.origIndex)-parseInt(b.dataset.origIndex));
        rowsArray.forEach(r => tbody.appendChild(r));
        document.getElementById('reportTitle').innerText = 'সর্বশেষ লেজার হিসাব (রানিং)';
        applyDashboardFilters();
        bootstrap.Modal.getInstance(document.getElementById('reportModal')).hide();
    });
}

// ── Selection / Print Mode ───────────────────────────────────────
let currentExportMode = '';
function enableSelectionMode(mode) {
    currentExportMode = mode;
    document.getElementById('selectionBanner').style.display = 'block';
    document.getElementById('modeText').innerText = mode === 'print' ? 'প্রিন্ট' : 'PDF ডাউনলোড';
}
function cancelSelection() {
    currentExportMode = '';
    document.getElementById('selectionBanner').style.display = 'none';
    document.querySelectorAll('.print-active').forEach(el => el.classList.remove('print-active'));
}
</script>

<?php end_page(); ?>