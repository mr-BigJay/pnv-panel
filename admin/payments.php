<?php

// Live panel may run from /bigjay_controller/ or /admin/.
// Soft-load auth/functions from either location (hard require_once fatals → blank page).
$__paymentsBootAuth = [
    __DIR__ . '/auth.php',
    __DIR__ . '/../admin/auth.php',
];
foreach($__paymentsBootAuth as $__paymentsBootFile){
    if(is_file($__paymentsBootFile)){
        require_once $__paymentsBootFile;
        break;
    }
}
$__paymentsBootFunc = [
    __DIR__ . '/functions.php',
    __DIR__ . '/../admin/functions.php',
];
foreach($__paymentsBootFunc as $__paymentsBootFile){
    if(is_file($__paymentsBootFile)){
        require_once $__paymentsBootFile;
        break;
    }
}
unset($__paymentsBootAuth, $__paymentsBootFunc, $__paymentsBootFile);

if(!defined('PNV_ADMIN_EMBEDDED')){
    // If this file itself lives in bigjay_controller, treat as embedded.
    if(substr(str_replace('\\', '/', __DIR__), -18) === '/bigjay_controller'){
        define('PNV_ADMIN_EMBEDDED', true);
    }
}

if(!function_exists('pnvAdminUrl')){
    function pnvAdminUrl($path = 'index.php'){
        $base = defined('PNV_ADMIN_BASE') ? rtrim(PNV_ADMIN_BASE, '/') : '/bigjay_controller';
        if($path === '' || $path === 'index.php'){
            return $base . '/';
        }
        if(strpos($path, '?') !== false){
            [$file, $query] = explode('?', $path, 2);
            return $base . '/' . ltrim($file, '/') . '?' . $query;
        }
        return $base . '/' . ltrim($path, '/');
    }
}

if(!function_exists('pnvAdminEntryUrl')){
    function pnvAdminEntryUrl(){
        return rtrim(defined('PNV_ADMIN_BASE') ? PNV_ADMIN_BASE : '/bigjay_controller', '/') . '/';
    }
}

if(!function_exists('pnvAdminSoftRedirect')){
    /**
     * داخل لایه‌اوت ادمین منو را نگه می‌دارد (بدون پاک‌کردن buffer / بدون سند HTML جدید).
     */
    function pnvAdminSoftRedirect($url){
        $url = (string)$url;
        $GLOBALS['pnv_admin_soft_redirect'] = $url;
        $embedded = (defined('PNV_ADMIN_EMBEDDED') && PNV_ADMIN_EMBEDDED) || headers_sent();
        if(!$embedded){
            header('Location: ' . $url);
            exit;
        }
    }
}

if(!function_exists('pnvAdminRedirect')){
    function pnvAdminRedirect($url){
        pnvAdminSoftRedirect($url);
    }
}

// Accept new pnv_admin session OR legacy $_SESSION['admin'].
// Do NOT call pnvAdminIsLoggedIn() — it unsets legacy admin and blanks the page.
$paymentsLoggedIn = (
    !empty($_SESSION['pnv_admin']['user'])
    && !empty($_SESSION['pnv_admin']['token'])
) || !empty($_SESSION['admin']);

if(!$paymentsLoggedIn){
    echo '<div style="padding:20px;color:#fecaca;background:#7f1d1d;border-radius:12px;margin:12px 0;">نشست مدیریت معتبر نیست. دوباره وارد شوید.</div>';
    return;
}

$_SESSION['admin'] = true;

if(is_file(__DIR__ . '/../xui_lib.php')){
    require_once __DIR__ . '/../xui_lib.php';
}
elseif(is_file(dirname(__DIR__) . '/xui_lib.php')){
    require_once dirname(__DIR__) . '/xui_lib.php';
}

$paymentsFile = dirname(__DIR__) . '/invoices/payments.csv';
$usersFile = dirname(__DIR__) . '/db/users.json';

if(!is_file($paymentsFile) && is_file(__DIR__ . '/../invoices/payments.csv')){
    $paymentsFile = __DIR__ . '/../invoices/payments.csv';
}

if(!is_file($usersFile) && is_file(__DIR__ . '/../db/users.json')){
    $usersFile = __DIR__ . '/../db/users.json';
}

$payments = [];
$users = [];

if(file_exists($usersFile)){
    $users = json_decode(file_get_contents($usersFile), true);
}

if(!is_array($users)){
    $users = [];
}

if(file_exists($paymentsFile)){
    $f = fopen($paymentsFile,'r');

    while(($d = fgetcsv($f)) !== FALSE){
        $payments[] = $d;
    }

    fclose($f);
}

if(!function_exists('getUserMobile')){

    function getUserMobile($username, $users){

        foreach($users as $u){

            if(
                strtolower(trim($u['username'] ?? ''))
                ==
                strtolower(trim($username))
            ){

                return $u['mobile'] ?? '-';

            }

        }

        return '-';

    }

}

if(!function_exists('isValidSubscriptionLink')){

    function isValidSubscriptionLink($link){

        $link = trim($link);

        if($link === ''){
            return false;
        }

        if(!filter_var($link, FILTER_VALIDATE_URL)){
            return false;
        }

        $validDomains = [
            'vip.boozhaan.ir',
            'vip2.boozhaan.ir',
            'vip3.boozhaan.ir',
            'vip4.boozhaan.ir'
        ];

        foreach($validDomains as $d){
            if(stripos($link, $d) !== false){
                return true;
            }
        }

        return false;
    }

}

$paymentMessage = '';
$paymentError = '';

if(isset($_SESSION['payment_message'])){
    $paymentMessage = $_SESSION['payment_message'];
    unset($_SESSION['payment_message']);
}

if(isset($_SESSION['payment_error'])){
    $paymentError = $_SESSION['payment_error'];
    unset($_SESSION['payment_error']);
}

$allowedPerPage = [20, 50, 100];

// ==================== عملیات POST ====================

if(isset($_POST['approve_payment'])){

    $index = intval($_POST['approve_index']);

    $link = trim($_POST['approve_link'] ?? '');
    $redirectPer = intval($_POST['per'] ?? $_GET['per'] ?? 20);

    if(!in_array($redirectPer, $allowedPerPage, true)){
        $redirectPer = 20;
    }

    $paymentsCleanUrl = pnvAdminUrl('index.php?page=payments&per=' . $redirectPer);
    $xuiConfig = function_exists('xuiLoadConfig') ? xuiLoadConfig() : [];
    $handled = false;

    if(function_exists('xuiIsEnabled') ? xuiIsEnabled($xuiConfig) : !empty($xuiConfig['enabled'])){

        $result = xuiApprovePaymentIndex($index, 'خرید');

        if(empty($result['ok'])){
            $_SESSION['payment_error'] = 'تایید خودکار ناموفق: ' . ($result['error'] ?? 'خطای نامشخص');
            $paymentError = $_SESSION['payment_error'];
            unset($_SESSION['payment_error']);
        } else {
            $_SESSION['payment_message'] = 'پرداخت تایید و اشتراک ساخته شد: ' . ($result['link'] ?? '');
            $paymentMessage = $_SESSION['payment_message'];
            unset($_SESSION['payment_message']);
        }
        pnvAdminSoftRedirect($paymentsCleanUrl);
        $handled = true;

    }

    if(!$handled){

    if(!isValidSubscriptionLink($link)){
        $_SESSION['payment_error'] = 'برای تایید پرداخت، وارد کردن لینک اشتراک معتبر الزامی است';
        $paymentError = $_SESSION['payment_error'];
        unset($_SESSION['payment_error']);
        pnvAdminSoftRedirect($paymentsCleanUrl);
    } else {

    if(isset($payments[$index])){

        $payments[$index][6] = 'تایید شد';

        $payments[$index][7] = $link;

    }

    $fp = fopen($paymentsFile,'w');

    foreach($payments as $p){

        fputcsv($fp, $p);

    }

    fclose($fp);

    $_SESSION['payment_message'] = 'پرداخت با موفقیت تایید شد';
    $paymentMessage = $_SESSION['payment_message'];
    unset($_SESSION['payment_message']);
    pnvAdminSoftRedirect($paymentsCleanUrl);

    }

    }

}

if(isset($_POST['reject_payment'])){

    $index = intval($_POST['reject_index']);

    $reason = trim($_POST['reject_reason']);
    $redirectPer = intval($_POST['per'] ?? $_GET['per'] ?? 20);

    if(!in_array($redirectPer, $allowedPerPage, true)){
        $redirectPer = 20;
    }

    if(isset($payments[$index])){

        $payments[$index][6] = 'رد شد';

        $payments[$index][7] = $reason;

    }

    $fp = fopen($paymentsFile,'w');

    if($fp){
        if(flock($fp, LOCK_EX)){
            foreach($payments as $p){
                fputcsv($fp, $p);
            }
            fflush($fp);
            flock($fp, LOCK_UN);
        }
        fclose($fp);
    }

    $_SESSION['payment_message'] = 'پرداخت رد شد';
    $paymentMessage = $_SESSION['payment_message'];
    unset($_SESSION['payment_message']);
    pnvAdminSoftRedirect(pnvAdminUrl('index.php?page=payments&per=' . $redirectPer));

}

if(isset($_POST['delete_payment']) || isset($_GET['deletepayment'])){

    $id = isset($_POST['delete_index'])
        ? intval($_POST['delete_index'])
        : intval($_GET['deletepayment'] ?? -1);
    $redirectPer = intval($_POST['per'] ?? $_GET['per'] ?? 20);

    if(!in_array($redirectPer, $allowedPerPage, true)){
        $redirectPer = 20;
    }

    if($id >= 0 && isset($payments[$id])){

        unset($payments[$id]);

        $payments = array_values($payments);

        $fp = fopen($paymentsFile,'w');

        if($fp){
            if(flock($fp, LOCK_EX)){
                foreach($payments as $p){
                    fputcsv($fp, $p);
                }
                fflush($fp);
                flock($fp, LOCK_UN);
            }
            fclose($fp);
        }

        $_SESSION['payment_message'] = 'پرداخت حذف شد';
        $paymentMessage = $_SESSION['payment_message'];
        unset($_SESSION['payment_message']);
    }

    // لیست همان‌جا می‌ماند؛ فقط URL از deletepayment پاک می‌شود
    pnvAdminSoftRedirect(pnvAdminUrl('index.php?page=payments&per=' . $redirectPer));

}

// ==================== آماده‌سازی لیست خرید ====================

$buyPayments = [];

foreach($payments as $index => $pay){

    $type = trim($pay[9] ?? '');

    if($type == 'خرید' || $type == ''){

        $buyPayments[] = [

            'index' => $index,

            'data' => $pay

        ];

    }

}

$buyPayments = array_reverse($buyPayments);

$currentPage = intval($_GET['p'] ?? 1);

if($currentPage < 1){
    $currentPage = 1;
}

$perPage = intval($_GET['per'] ?? 20);

if(!in_array($perPage, $allowedPerPage, true)){
    $perPage = 20;
}

$totalItems = count($buyPayments);

$totalPages = max(1, (int)ceil($totalItems / $perPage));

if($currentPage > $totalPages){
    $currentPage = $totalPages;
}

$start = ($currentPage - 1) * $perPage;

$buyPaymentsPage = array_slice($buyPayments, $start, $perPage);

$rangeFrom = $totalItems > 0 ? $start + 1 : 0;
$rangeTo = min($start + $perPage, $totalItems);

function paymentsListUrl($page, $per){

    return pnvAdminUrl(
        'index.php?page=payments&p=' . intval($page) . '&per=' . intval($per)
    );

}

function paymentsFormatPlanLines($plan){

    $plan = trim((string)$plan);

    if($plan === '' || $plan === '-'){
        return ['-', ''];
    }

    if(strpos($plan, ' - ') !== false){

        [$size, $price] = explode(' - ', $plan, 2);

        $size = trim($size);
        $price = trim($price);

        if(preg_match('/(\d+)/u', $price, $match)){
            $price = $match[1];
        }

        return [$size, $price];

    }

    return [$plan, ''];

}

?>

<style>

.payTableWrap{
width:100%;
max-width:100%;
overflow-x:auto;
-webkit-overflow-scrolling:touch;
}

.paymentsPage .payTable{
width:100% !important;
max-width:100% !important;
table-layout:fixed !important;
border-collapse:collapse !important;
background:#1e293b;
border-radius:16px;
}

.paymentsPage .payTable th,
.paymentsPage .payTable td{
padding:10px 6px !important;
border-bottom:1px solid #334155 !important;
text-align:center !important;
vertical-align:middle !important;
color:white !important;
}

.paymentsPage .payTable th{
background:#334155 !important;
font-size:13px !important;
font-weight:600;
}

.paymentsPage .payTable td{
font-size:12px !important;
}

.paymentsPage .payTable .col-plan{
text-align:center !important;
vertical-align:middle !important;
}

.paymentsPage .planCell{
display:flex !important;
flex-direction:column !important;
align-items:center !important;
justify-content:center !important;
gap:3px !important;
line-height:1.15 !important;
}

.paymentsPage .planSize{
display:block !important;
font-size:12px !important;
font-weight:600 !important;
}

.paymentsPage .planPrice{
display:block !important;
font-size:15px !important;
font-weight:700 !important;
color:#f8fafc !important;
}

.paymentsPage .payTable .col-num{
width:11%;
}

.paymentsPage .payTable .col-user{
width:20%;
word-break:break-word;
line-height:1.35;
}

.paymentsPage .payTable .col-plan{
width:36%;
}

.paymentsPage .payTable .col-status{
width:10%;
}

.paymentsPage .payTable .col-actions{
width:23%;
}

.statusDot{
width:12px;
height:12px;
border-radius:50%;
display:inline-block;
flex-shrink:0;
}

.statusDot--green{
background:#22c55e;
box-shadow:0 0 0 2px rgba(34,197,94,.25);
}

.statusDot--red{
background:#ef4444;
box-shadow:0 0 0 2px rgba(239,68,68,.25);
}

.statusDot--yellow{
background:#facc15;
box-shadow:0 0 0 2px rgba(250,204,21,.25);
}

.menuWrap{
position:relative;
display:inline-block;
width:34px;
}

.menuBtn{
width:34px;
height:34px;
border:none;
border-radius:10px;
background:#334155;
color:white;
font-size:18px;
cursor:pointer;
padding:0;
}

.dropdown{
    display:none;
    position:absolute;
    right:0;
    top:45px;
    background:#0f172a;
    width:200px;
    padding:10px;
    border-radius:14px;
    z-index:999;
    box-shadow:0 10px 30px rgba(0,0,0,0.4);
    direction:rtl;
}

.dropdown.active{
    display:block;
}

.dropdown button{
    width:100%;
    padding:11px;
    border:none;
    border-radius:10px;
    margin-bottom:8px;
    background:#334155;
    color:white;
    cursor:pointer;
    font-size:13px;
    text-align:right;
}

.dropdown .red{
    background:#ef4444;
}

.modalOverlay{
    position:fixed;
    inset:0;
    background:rgba(0,0,0,0.45);
    backdrop-filter:blur(6px);
    display:none;
    justify-content:center;
    align-items:center;
    z-index:99999;
    padding:16px;
}

.modal{
    background:#1e293b;
    width:100%;
    max-width:430px;
    border-radius:20px;
    padding:22px;
    color:white;
}

.modalTitle{
    font-size:20px;
    text-align:center;
    margin-bottom:18px;
    font-weight:bold;
}

.modalInfo{
    background:#0f172a;
    padding:14px;
    border-radius:12px;
    line-height:28px;
    font-size:13px;
    margin-bottom:16px;
}

.bigText{
    background:#0f172a;
    padding:18px;
    border-radius:14px;
    font-size:16px;
    line-height:34px;
    word-break:break-all;
    margin-bottom:16px;
}

.modal input,
.modal select{
    width:100%;
    padding:12px;
    border:none;
    border-radius:10px;
    margin-bottom:12px;
    background:#0f172a;
    color:white;
    font-size:14px;
    box-sizing:border-box;
}

.modalBtns{
    display:flex;
    gap:10px;
    margin-top:12px;
}

.modalBtns button{
    flex:1;
    padding:12px;
    border:none;
    border-radius:12px;
    cursor:pointer;
    color:white;
    font-size:14px;
}

.green{
    background:#22c55e;
}

.redBtn{
    background:#ef4444;
}

.gray{
    background:#475569;
}

.payPager{
margin-top:18px;
display:flex;
align-items:center;
justify-content:space-between;
gap:12px;
flex-wrap:wrap;
background:#1e293b;
border:1px solid #334155;
border-radius:12px;
padding:12px 14px;
}

.payPagerSize{
display:flex;
align-items:center;
gap:8px;
font-size:13px;
color:#cbd5e1;
}

.payPerSelect{
padding:8px 10px;
border:1px solid #475569;
border-radius:8px;
background:#0f172a;
color:white;
font-family:inherit;
font-size:13px;
min-width:64px;
}

.payPagerNav{
display:flex;
align-items:center;
gap:8px;
}

.payPagerInfo{
font-size:13px;
color:#cbd5e1;
white-space:nowrap;
}

.payPagerBtn{
min-width:36px;
height:36px;
padding:0 10px;
display:inline-flex;
align-items:center;
justify-content:center;
border:1px solid #475569;
border-radius:8px;
background:#0f172a;
color:white;
text-decoration:none;
font-size:14px;
box-sizing:border-box;
}

.payPagerBtn.is-active{
background:#2563eb;
border-color:#2563eb;
color:white;
}

.payPagerBtn.is-disabled{
opacity:.45;
pointer-events:none;
}

@media(max-width:900px){

.dropdown{
right:auto;
left:0;
}

}

@media(max-width:768px){

.box{
padding:12px;
overflow:hidden;
}

.paymentsPage .payTable th,
.paymentsPage .payTable td{
padding:7px 3px !important;
font-size:10px !important;
}

.paymentsPage .payTable .col-num{
width:9% !important;
font-size:9px !important;
}

.paymentsPage .payTable .col-user{
width:18% !important;
font-size:10px !important;
}

.paymentsPage .payTable .col-plan{
width:40% !important;
}

.paymentsPage .payTable .col-plan .planSize{
font-size:11px !important;
}

.paymentsPage .payTable .col-plan .planPrice{
font-size:13px !important;
}

.paymentsPage .payTable .col-status{
width:9% !important;
}

.paymentsPage .payTable .col-actions{
width:8% !important;
}

.menuWrap{
width:30px;
}

.menuBtn{
width:30px;
height:30px;
font-size:16px;
}

.payPager{
flex-direction:column;
align-items:stretch;
gap:10px;
}

.payPagerNav{
justify-content:space-between;
width:100%;
}

.dropdown{
width:180px;
}

}

</style>

<div class="paymentsPage" data-payments-ui="v5">

<div class="box">
<?php
$softUrl = (string)($GLOBALS['pnv_admin_soft_redirect'] ?? '');
if($softUrl !== ''){
    $softJson = json_encode($softUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    echo '<script>try{history.replaceState({},"",' . $softJson . ');}catch(e){}</script>';
}
?>

    <h2>

        لیست خرید های جدید

    </h2>

    <?php if($paymentMessage !== ''){ ?>
    <div style="background:#14532d;color:#bbf7d0;padding:12px 14px;border-radius:12px;margin-bottom:14px;line-height:1.8"><?php echo htmlspecialchars($paymentMessage, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php } ?>
    <?php if($paymentError !== ''){ ?>
    <div style="background:#7f1d1d;color:#fecaca;padding:12px 14px;border-radius:12px;margin-bottom:14px;line-height:1.8"><?php echo htmlspecialchars($paymentError, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php } ?>

    <div class="payTableWrap">

    <table class="payTable">

        <thead>

            <tr>

                <th class="col-num">شماره</th>

                <th class="col-user">کاربر</th>

                <th class="col-plan">پلن اشتراک</th>

                <th class="col-status">وضعیت</th>

                <th class="col-actions">عملیات</th>

            </tr>

        </thead>

        <tbody>

        <?php foreach($buyPaymentsPage as $row){

            $i = $row['index'];

            $p = $row['data'];

            $status = trim($p[6] ?? '');

            $statusDotClass = 'statusDot--yellow';
            $statusTitle = 'در حال بررسی';

            if($status === 'تایید شد'){
                $statusDotClass = 'statusDot--green';
                $statusTitle = 'تایید شد';
            }

            if($status === 'رد شد'){
                $statusDotClass = 'statusDot--red';
                $statusTitle = 'رد شد';
            }

            $mobile =
            getUserMobile(
                $p[0] ?? '',
                $users
            );

            [$planSize, $planPrice] = paymentsFormatPlanLines($p[2] ?? '');

        ?>

            <tr>

                <td class="col-num">

                    <?php echo $i + 1; ?>

                </td>

                <td class="col-user">

                    <?php echo htmlspecialchars($p[0] ?? '-'); ?>

                </td>

                <td class="col-plan">

                    <div class="planCell">
                        <span class="planSize"><?php echo htmlspecialchars($planSize, ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php if($planPrice !== ''){ ?>
                        <span class="planPrice"><?php echo htmlspecialchars($planPrice, ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php } ?>
                    </div>

                </td>

                <td class="col-status">

                    <span
                        class="statusDot <?php echo $statusDotClass; ?>"
                        title="<?php echo htmlspecialchars($statusTitle, ENT_QUOTES, 'UTF-8'); ?>"></span>

                </td>

                <td class="col-actions">

                    <div class="menuWrap">

                        <button
                            class="menuBtn"
                            onclick="toggleMenu('m<?php echo $i; ?>')">

                            ⋮

                        </button>

                        <div
                            class="dropdown"
                            id="m<?php echo $i; ?>">

                            <button
                                onclick='showConfig(
                                <?php echo json_encode($p[0] ?? ""); ?>,
                                <?php echo json_encode($mobile); ?>,
                                <?php echo json_encode($p[4] ?? ""); ?>,
                                <?php echo json_encode($p[5] ?? ""); ?>,
                                <?php echo json_encode($p[1] ?? ""); ?>,
                                <?php echo json_encode($p[2] ?? ""); ?>
                                )'>

                                نام کانفیگ

                            </button>

                            <button
                                onclick='showPayment(
                                <?php echo json_encode($p[0] ?? ""); ?>,
                                <?php echo json_encode($mobile); ?>,
                                <?php echo json_encode($p[1] ?? ""); ?>,
                                <?php echo json_encode($p[3] ?? ""); ?>,
                                <?php echo json_encode($p[4] ?? ""); ?>,
                                <?php echo json_encode($p[5] ?? ""); ?>
                                )'>

                                جزئیات پرداخت

                            </button>

                            <button
                                onclick='showAction(
                                <?php echo $i; ?>,
                                <?php echo json_encode($p[0] ?? ""); ?>,
                                <?php echo json_encode($mobile); ?>,
                                <?php echo json_encode($p[1] ?? ""); ?>,
                                <?php echo json_encode($status); ?>,
                                <?php echo json_encode($p[7] ?? ""); ?>
                                )'>

                                عملیات

                            </button>

                            <button
                                class="red"
                                onclick='showDelete(
                                <?php echo $i; ?>,
                                <?php echo json_encode($p[0] ?? ""); ?>,
                                <?php echo json_encode($mobile); ?>,
                                <?php echo json_encode($p[1] ?? ""); ?>
                                )'>

                                حذف

                            </button>

                        </div>

                    </div>

                </td>

            </tr>

        <?php } ?>

        </tbody>

    </table>

    </div>

    <div class="payPager">

        <div class="payPagerSize">
            <span>نمایش</span>
            <select
                class="payPerSelect"
                onchange="window.location.href=this.value;">

                <option
                    value="<?php echo htmlspecialchars(paymentsListUrl(1, 20), ENT_QUOTES, 'UTF-8'); ?>"
                    <?php echo $perPage === 20 ? 'selected' : ''; ?>>

                    ۲۰

                </option>

                <option
                    value="<?php echo htmlspecialchars(paymentsListUrl(1, 50), ENT_QUOTES, 'UTF-8'); ?>"
                    <?php echo $perPage === 50 ? 'selected' : ''; ?>>

                    ۵۰

                </option>

                <option
                    value="<?php echo htmlspecialchars(paymentsListUrl(1, 100), ENT_QUOTES, 'UTF-8'); ?>"
                    <?php echo $perPage === 100 ? 'selected' : ''; ?>>

                    ۱۰۰

                </option>

            </select>
            <span>مورد در هر صفحه</span>
        </div>

        <div class="payPagerNav">
            <span class="payPagerInfo">
                <?php echo number_format($rangeFrom); ?>-<?php echo number_format($rangeTo); ?>
                از
                <?php echo number_format($totalItems); ?>
                مورد
            </span>

            <a
                href="<?php echo htmlspecialchars(paymentsListUrl(max(1, $currentPage - 1), $perPage), ENT_QUOTES, 'UTF-8'); ?>"
                class="payPagerBtn <?php echo $currentPage <= 1 ? 'is-disabled' : ''; ?>"
                aria-label="صفحه قبل">

                ‹

            </a>

            <span class="payPagerBtn is-active">

                <?php echo number_format($currentPage); ?>

            </span>

            <a
                href="<?php echo htmlspecialchars(paymentsListUrl(min($totalPages, $currentPage + 1), $perPage), ENT_QUOTES, 'UTF-8'); ?>"
                class="payPagerBtn <?php echo $currentPage >= $totalPages ? 'is-disabled' : ''; ?>"
                aria-label="صفحه بعد">

                ›

            </a>
        </div>

    </div>

</div>

<div class="modalOverlay" id="modal">

    <div class="modal" id="modalContent"></div>

</div>

</div>

<script>

const paymentsListBase = <?php echo json_encode(pnvAdminUrl('index.php?page=payments'), JSON_UNESCAPED_UNICODE); ?>;
const paymentsPerPage = <?php echo (int)$perPage; ?>;

function toggleMenu(id){

    document.querySelectorAll('.dropdown').forEach(el => {

        if(el.id != id){

            el.classList.remove('active');

        }

    });

    document
    .getElementById(id)
    .classList.toggle('active');

}

function closeModal(){

    document
    .getElementById('modal')
    .style.display = 'none';

}

function openModal(html){

    document
    .getElementById('modalContent')
    .innerHTML = html;

    document
    .getElementById('modal')
    .style.display = 'flex';

}

function showConfig(
    user,
    mobile,
    date,
    time,
    config,
    plan
){

    let last4 = '';

    if(mobile){

        mobile = mobile.toString();

        last4 = mobile.slice(-4);

    }

let planNumber = '';

let match =
plan.match(/\d+/);

if(match){

planNumber = match[0];

}

let finalName =
config
+
'_'
+
last4
+
'_'
+
planNumber;

    openModal(`

        <div class="modalTitle">

            نام نهایی کانفیگ

        </div>

        <div class="modalInfo">

            نام کاربر: ${user}<br>
            شماره موبایل: ${mobile}<br>
            تاریخ: ${date}<br>
            ساعت: ${time}

        </div>

        <div class="bigText" id="cfgText">

            ${finalName}

        </div>

        <button
            class="green"
            style="width:100%;padding:12px;border:none;border-radius:12px;color:white;"
            onclick="copyText('cfgText')">

            کپی نام کانفیگ

        </button>

        <div class="modalBtns">

            <button
                class="gray"
                onclick="closeModal()">

                بستن

            </button>

        </div>

    `);

}

function showPayment(
    user,
    mobile,
    config,
    track,
    date,
    time
){

    openModal(`

        <div class="modalTitle">

            جزئیات پرداخت

        </div>

        <div class="modalInfo">

            نام کاربر: ${user}<br>
            شماره موبایل: ${mobile}<br>
            نام کانفیگ: ${config}

        </div>

        <div class="bigText">

            شماره پیگیری: ${track}<br>
            تاریخ: ${date}<br>
            ساعت: ${time}

        </div>

        <div class="modalBtns">

            <button
                class="gray"
                onclick="closeModal()">

                بستن

            </button>

        </div>

    `);

}

function showAction(
    id,
    user,
    mobile,
    config,
    status='',
    savedLink=''
){

    let content = '';

    if(status === 'تایید شد'){

        content = `

            <div class="bigText">

                ${savedLink}

            </div>

            <div class="modalBtns">

                <button
                    class="gray"
                    onclick="closeModal()">

                    بستن

                </button>

            </div>

        `;

    }

    else if(status === 'رد شد'){

        content = `

            <div style="background:#450a0a;padding:14px;border-radius:12px;line-height:30px;margin-bottom:15px;">

                ${savedLink}

            </div>

            <div class="modalBtns">

                <button
                    class="gray"
                    onclick="closeModal()">

                    بستن

                </button>

            </div>

        `;

    }

    else{

        content = `

            <form method="POST">

                <input type="hidden" name="per" value="${paymentsPerPage}">

                <input
                    type="hidden"
                    name="approve_index"
                    value="${id}">

                <input
                    type="text"
                    name="approve_link"
                    id="approveLink"
                    placeholder="لینک اشتراک">

                <div class="modalBtns">

                    <button
                        type="button"
                        class="gray"
                        onclick="pasteClipboard()">

                        Paste

                    </button>

                    <button
                        type="submit"
                        name="approve_payment"
                        class="green">

                        تایید

                    </button>

                </div>

            </form>

            <hr style="margin:20px 0;border-color:#334155;">

            <form method="POST">

                <input type="hidden" name="per" value="${paymentsPerPage}">

                <input
                    type="hidden"
                    name="reject_index"
                    value="${id}">

                <select name="reject_reason">

                    <option value="اطلاعات پرداخت اشتباه است">

                        اطلاعات پرداخت اشتباه است

                    </option>

                    <option value="اطلاعات پرداخت تکراری است">

                        اطلاعات پرداخت تکراری است

                    </option>

                </select>

                <button
                    type="submit"
                    name="reject_payment"
                    class="redBtn"
                    style="width:100%;padding:12px;border:none;border-radius:12px;color:white;">

                    رد پرداخت

                </button>

            </form>

            <div class="modalBtns">

                <button
                    class="gray"
                    onclick="closeModal()">

                    بستن

                </button>

            </div>

        `;

    }

    openModal(`

        <div class="modalTitle">

            عملیات پرداخت

        </div>

        <div class="modalInfo">

            نام کاربر: ${user}<br>
            شماره موبایل: ${mobile}<br>
            نام کانفیگ: ${config}

        </div>

        ${content}

    `);

}

function showDelete(
    id,
    user,
    mobile,
    config
){

    openModal(`

        <div class="modalTitle">

            حذف پرداخت

        </div>

        <div class="modalInfo">

            نام کاربر: ${user}<br>
            شماره موبایل: ${mobile}<br>
            نام کانفیگ: ${config}

        </div>

        <div class="modalBtns">

            <button
                class="redBtn"
                onclick="confirmDelete(${id})">

                حذف

            </button>

            <button
                class="gray"
                onclick="closeModal()">

                بستن

            </button>

        </div>

    `);

}

function confirmDelete(id){

    if(!confirm('مطمئن هستید؟')){
        return;
    }

    // POST تا بعد از حذف URL تمیز بماند و صفحه هنگ نکند
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = paymentsListBase;
    form.style.display = 'none';

    const fields = {
        delete_payment: '1',
        delete_index: String(id),
        per: String(paymentsPerPage)
    };

    Object.keys(fields).forEach(function(name){
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        input.value = fields[name];
        form.appendChild(input);
    });

    document.body.appendChild(form);
    form.submit();

}

function copyText(id){

    let text =
    document.getElementById(id).innerText;

    navigator.clipboard.writeText(text);

    alert('کپی شد');

}

async function pasteClipboard(){

    try{

        const text =
        await navigator.clipboard.readText();

        document
        .getElementById('approveLink')
        .value = text;

    }

    catch(e){

        alert('دسترسی clipboard داده نشده');

    }

}

document.addEventListener('click', function(e){

    if(!e.target.closest('.menuWrap')){

        document.querySelectorAll('.dropdown').forEach(el => {

            el.classList.remove('active');

        });

    }

});

</script>