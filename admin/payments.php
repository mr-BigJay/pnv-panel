<?php

// Live panel includes this from /bigjay_controller/ where auth.php & functions.php
// are often missing (HTTP 404). Hard require_once fatals → blank content area.
if(!function_exists('pnvAdminIsLoggedIn')){
    foreach ([
        __DIR__ . '/auth.php',
        __DIR__ . '/../admin/auth.php',
    ] as $paymentsBootFile) {
        if (is_file($paymentsBootFile)) {
            require_once $paymentsBootFile;
            if(function_exists('pnvAdminIsLoggedIn')){
                break;
            }
        }
    }
}

if(!function_exists('getUserMobile')){
    foreach ([
        __DIR__ . '/functions.php',
        __DIR__ . '/../admin/functions.php',
    ] as $paymentsBootFile) {
        if (is_file($paymentsBootFile)) {
            require_once $paymentsBootFile;
            if(function_exists('getUserMobile')){
                break;
            }
        }
    }
}

if(!function_exists('pnvFormatPaymentRowDateTime')){
    foreach ([
        __DIR__ . '/pnv_date_bootstrap.php',
        __DIR__ . '/../pnv_date_bootstrap.php',
    ] as $dateBootFile) {
        if (is_file($dateBootFile)) {
            require_once $dateBootFile;
            break;
        }
    }
}

if (!function_exists('pnvAdminUrl')) {
    function pnvAdminUrl($path = 'index.php') {
        $base = defined('PNV_ADMIN_BASE') ? rtrim(PNV_ADMIN_BASE, '/') : '/bigjay_controller';
        if ($path === '' || $path === 'index.php') {
            return $base . '/';
        }
        if (strpos($path, '?') !== false) {
            [$file, $query] = explode('?', $path, 2);
            return $base . '/' . ltrim($file, '/') . '?' . $query;
        }
        return $base . '/' . ltrim($path, '/');
    }
}

// Accept new pnv_admin session OR legacy $_SESSION['admin'].
// Do NOT call pnvAdminIsLoggedIn() — it unsets legacy admin and blanks the page.
$paymentsLoggedIn = (
    !empty($_SESSION['pnv_admin']['user'])
    && !empty($_SESSION['pnv_admin']['token'])
) || !empty($_SESSION['admin']);

if (!$paymentsLoggedIn) {
    echo '<div style="padding:20px;color:#fecaca;background:#7f1d1d;border-radius:12px;margin:12px 0;">نشست مدیریت معتبر نیست. دوباره وارد شوید.</div>';
    return;
}

$_SESSION['admin'] = true;

if (is_file(__DIR__ . '/../xui_lib.php')) {
    require_once __DIR__ . '/../xui_lib.php';
}

if (is_file(__DIR__ . '/../instant_pay_lib.php')) {
    require_once __DIR__ . '/../instant_pay_lib.php';
}

$paymentsFile = dirname(__DIR__) . '/invoices/payments.csv';
$usersFile = dirname(__DIR__) . '/db/users.json';
if (!is_file($paymentsFile) && is_file(__DIR__ . '/../invoices/payments.csv')) {
    $paymentsFile = __DIR__ . '/../invoices/payments.csv';
}
if (!is_file($usersFile) && is_file(__DIR__ . '/../db/users.json')) {
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

if(function_exists('instantPayPurgeStaleAdminRows')){
    instantPayPurgeStaleAdminRows();
    $payments = [];

    if(file_exists($paymentsFile)){
        $f = fopen($paymentsFile,'r');

        while(($d = fgetcsv($f)) !== FALSE){
            $payments[] = $d;
        }

        fclose($f);
    }
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

    $xuiConfig = xuiLoadConfig();

    if(function_exists('xuiIsEnabled') ? xuiIsEnabled($xuiConfig) : !empty($xuiConfig['enabled'])){

        $result = xuiApprovePaymentIndex($index, 'خرید');

        if(empty($result['ok'])){
            $_SESSION['payment_error'] = 'تایید خودکار ناموفق: ' . ($result['error'] ?? 'خطای نامشخص');
            header('Location: ' . pnvAdminUrl('index.php?page=payments&per=' . $redirectPer));
            exit;
        }

        $_SESSION['payment_message'] = 'پرداخت تایید و اشتراک ساخته شد: ' . ($result['link'] ?? '');
        header('Location: ' . pnvAdminUrl('index.php?page=payments&per=' . $redirectPer));
        exit;

    }

    if(!isValidSubscriptionLink($link)){
        $_SESSION['payment_error'] = 'برای تایید پرداخت، وارد کردن لینک اشتراک معتبر الزامی است';
        header('Location: ' . pnvAdminUrl('index.php?page=payments&per=' . $redirectPer));
        exit;
    }

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
    header('Location: ' . pnvAdminUrl('index.php?page=payments&per=' . $redirectPer));

    exit;

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

    foreach($payments as $p){

        fputcsv($fp, $p);

    }

    fclose($fp);

    header('Location: ' . pnvAdminUrl('index.php?page=payments&per=' . $redirectPer));

    exit;

}

if(isset($_GET['deletepayment'])){

    $id = intval($_GET['deletepayment']);

    if(isset($payments[$id])){

        unset($payments[$id]);

        $payments = array_values($payments);

    }

    $fp = fopen($paymentsFile,'w');

    foreach($payments as $p){

        fputcsv($fp, $p);

    }

    fclose($fp);

    header('Location: ' . pnvAdminUrl('index.php?page=payments&per=' . intval($_GET['per'] ?? 20)));

    exit;

}

// ==================== آماده‌سازی لیست خرید ====================

$buyPayments = [];

foreach($payments as $index => $pay){

    $type = trim($pay[9] ?? '');

    if($type == 'خرید' || $type == ''){

        if(function_exists('instantPayAdminRowVisible') && !instantPayAdminRowVisible($pay)){
            continue;
        }

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

function paymentsListUrl($page, $per){

    return pnvAdminUrl(
        'index.php?page=payments&p=' . intval($page) . '&per=' . intval($per)
    );

}

function paymentsFormatPlanLine($plan){

    $plan = trim((string)$plan);

    if($plan === '' || $plan === '-'){
        return '—';
    }

    if(strpos($plan, ' - ') !== false){

        [$size, $price] = explode(' - ', $plan, 2);

        $size = trim($size);
        $price = trim($price);

        if($price !== ''){
            $price = preg_replace('/\s*هزار\s*تومان/u', ' تومن', $price);
            $price = preg_replace('/\s*تومان/u', ' تومن', $price);
            $price = preg_replace('/\s+/u', ' ', trim($price));
        }

        if($size !== '' && $price !== ''){
            return $size . ' - ' . $price;
        }

        if($size !== ''){
            return $size;
        }

        return $price;

    }

    return $plan;

}

?>

<link rel="stylesheet" href="/fonts.css">

<style>

body{
background:linear-gradient(165deg,#0B1220 0%,#182537 55%,#0f172a 100%) !important;
background-attachment:fixed !important;
font-family:tahoma,sans-serif !important;
}

.content{
background:transparent !important;
}

.content > .box.paymentsPage{
background:rgba(24,37,55,.72);
backdrop-filter:blur(14px);
-webkit-backdrop-filter:blur(14px);
border:1px solid rgba(148,163,184,.12);
border-radius:24px;
padding:20px 16px 18px;
box-shadow:0 18px 50px rgba(0,0,0,.35);
overflow:visible;
}

.paymentsPage h2{
margin:0 0 18px;
font-size:24px;
font-family:"Lalezar",tahoma,sans-serif;
font-weight:400;
letter-spacing:0;
text-align:right;
color:#fff;
}

.payUiTag{
display:inline-block;
margin-right:8px;
padding:2px 8px;
border-radius:999px;
background:rgba(34,197,94,.18);
color:#86efac;
font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;
font-size:11px;
font-weight:700;
vertical-align:middle;
letter-spacing:.04em;
}

.payList{
display:flex;
flex-direction:column;
gap:14px;
overflow:visible;
}

.payCard{
display:grid;
grid-template-columns:minmax(0,.95fr) minmax(0,1.55fr) max-content;
align-items:center;
gap:12px;
padding:16px 14px;
border-radius:16px;
background:#1e293b;
border:1px solid rgba(148,163,184,.10);
box-shadow:0 10px 28px rgba(0,0,0,.28);
position:relative;
overflow:visible;
}

.payCardEmpty{
justify-content:center;
text-align:center;
color:#94a3b8;
font-size:14px;
padding:28px 16px;
}

.payColUser,
.payColPlan{
min-width:0;
}

.payColUser{
text-align:right;
padding-inline-end:2px;
}

.payUserName{
display:block;
font-size:14px;
font-weight:700;
color:#fff;
line-height:1.35;
white-space:nowrap;
overflow:hidden;
text-overflow:ellipsis;
}

.payUserMobile{
display:block;
margin-top:4px;
font-size:12px;
color:#94a3b8;
line-height:1.3;
white-space:nowrap;
overflow:hidden;
text-overflow:ellipsis;
direction:ltr;
unicode-bidi:plaintext;
}

.payColPlan{
text-align:center;
}

.payConfigName{
display:block;
font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;
font-size:12.5px;
font-weight:700;
text-align:center;
margin-bottom:6px;
white-space:nowrap;
overflow:hidden;
text-overflow:ellipsis;
color:#fff;
}

.payPlanLine{
display:block;
text-align:center;
color:#86efac;
font-size:12px;
font-weight:600;
line-height:1.35;
white-space:nowrap;
overflow:hidden;
text-overflow:ellipsis;
}

.payActions{
display:flex;
align-items:center;
justify-content:center;
gap:8px;
position:relative;
flex-shrink:0;
overflow:visible;
}

.menuBtn{
width:30px !important;
height:30px;
min-width:30px;
border:none;
border-radius:10px;
background:#334155;
color:#e2e8f0;
font-size:15px;
cursor:pointer;
line-height:1;
display:inline-flex;
align-items:center;
justify-content:center;
flex:0 0 30px;
box-sizing:border-box;
margin:0 !important;
padding:0 !important;
box-shadow:inset 0 0 0 1px rgba(148,163,184,.16);
-webkit-tap-highlight-color:transparent;
touch-action:manipulation;
}

.statusIcon{
width:22px !important;
height:22px;
min-width:22px;
border:none;
border-radius:999px;
color:#fff;
display:inline-flex;
align-items:center;
justify-content:center;
flex:0 0 22px;
box-sizing:border-box;
margin:0 !important;
padding:0 !important;
box-shadow:0 4px 10px rgba(0,0,0,.25);
}

.statusIcon svg{
width:12px;
height:12px;
display:block;
stroke:#fff;
}

.statusIcon.is-ok{background:#22c55e}
.statusIcon.is-no{background:#ef4444}
.statusIcon.is-pending{background:#f59e0b}
.statusIcon.is-progress{background:#2563eb}

.dropdown{
display:none;
position:absolute;
top:calc(100% + 6px);
left:0;
right:auto;
background:#0f172a;
width:180px;
padding:10px;
border-radius:14px;
z-index:50;
box-shadow:0 14px 36px rgba(0,0,0,.45);
border:1px solid #334155;
}

.dropdown.active{display:block}

.dropdown button{
width:100%;
padding:11px;
border:none;
border-radius:10px;
margin-bottom:8px;
background:#334155;
color:#fff;
cursor:pointer;
font-family:inherit;
-webkit-tap-highlight-color:transparent;
text-align:right;
font-size:13px;
}

.dropdown button:last-child{margin-bottom:0}
.red{background:#ef4444 !important}

.modalOverlay{
position:fixed;
inset:0;
background:rgba(0,0,0,.55);
display:none;
justify-content:center;
align-items:center;
padding:15px;
z-index:9999999;
}

.modal{
background:#1e293b;
padding:20px;
border-radius:18px;
width:100%;
max-width:420px;
color:#fff;
font-family:inherit;
}

.modal h3{margin:0 0 12px;text-align:center}

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
margin-bottom:12px;
border:none;
border-radius:10px;
background:#0f172a;
color:#fff;
box-sizing:border-box;
font-family:inherit;
}

.modalBtns{display:flex;gap:10px;margin-top:12px}
.modalBtns button{
flex:1;
padding:12px;
border:none;
border-radius:10px;
cursor:pointer;
color:#fff;
font-family:inherit;
}

.green{background:#22c55e}
.gray{background:#475569}
.redBtn{background:#ef4444}

.paymentsPage .pagination{
margin-top:18px;
text-align:center;
}

.paymentsPage .pagination a{
display:inline-flex;
align-items:center;
justify-content:center;
min-width:36px;
padding:8px 12px;
margin:4px;
background:#1e293b;
color:#fff;
border-radius:10px;
text-decoration:none;
border:1px solid rgba(148,163,184,.12);
}

.paymentsPage .pagination a.active{
background:#22c55e;
border-color:transparent;
}

.paymentsPage .payAlertOk{
background:#14532d;
color:#bbf7d0;
padding:12px 14px;
border-radius:12px;
margin-bottom:14px;
line-height:1.8;
}

.paymentsPage .payAlertErr{
background:#7f1d1d;
color:#fecaca;
padding:12px 14px;
border-radius:12px;
margin-bottom:14px;
line-height:1.8;
}

@media(max-width:560px){
.content > .box.paymentsPage{
padding:16px 12px 14px;
border-radius:22px;
}
.payCard{
grid-template-columns:minmax(0,.9fr) minmax(0,1.4fr) max-content;
gap:10px;
padding:14px 12px;
}
.payUserName{font-size:13px}
.payUserMobile{font-size:11px}
.payConfigName{font-size:11.5px}
.payPlanLine{font-size:11px}
.menuBtn{
width:28px !important;
height:28px;
min-width:28px;
flex:0 0 28px;
}
.statusIcon{
width:20px !important;
height:20px;
min-width:20px;
flex:0 0 20px;
}
.statusIcon svg{width:11px;height:11px}
}

</style>

<div class="box paymentsPage" data-payments-ui="cards">

<h2>لیست خرید های جدید <span class="payUiTag" title="payments UI cards v20260815">cards</span></h2>

<?php if($paymentMessage !== ''){ ?>
<div class="payAlertOk"><?php echo htmlspecialchars($paymentMessage, ENT_QUOTES, 'UTF-8'); ?></div>
<?php } ?>
<?php if($paymentError !== ''){ ?>
<div class="payAlertErr"><?php echo htmlspecialchars($paymentError, ENT_QUOTES, 'UTF-8'); ?></div>
<?php } ?>

<div class="payList">

<?php if($totalItems === 0){ ?>
<div class="payCard payCardEmpty">خریدی برای نمایش نیست</div>
<?php } ?>

<?php foreach($buyPaymentsPage as $row){

$i = $row['index'];
$p = $row['data'];

$status = trim((string)($p[6] ?? ''));
if($status === ''){
$status = 'درحال بررسی';
}

$statusClass = 'is-pending';
$statusTitle = 'درحال بررسی';
if(function_exists('instantPayAdminRowStatusMeta')){
    $payStatusMeta = instantPayAdminRowStatusMeta($p);
    $statusTitle = $payStatusMeta['title'] ?? $statusTitle;
    if(($payStatusMeta['class'] ?? '') === 'statusDot--green'){
        $statusClass = 'is-ok';
    }
    elseif(($payStatusMeta['class'] ?? '') === 'statusDot--red'){
        $statusClass = 'is-no';
    }
    elseif(($payStatusMeta['class'] ?? '') === 'statusDot--blue'){
        $statusClass = 'is-progress';
    }
    elseif($status === 'تایید شد'){
        $statusClass = 'is-ok';
        $statusTitle = 'تایید شد';
    }
    elseif($status === 'رد شد'){
        $statusClass = 'is-no';
        $statusTitle = 'رد شد';
    }
}
else{
    if($status === 'تایید شد'){
        $statusClass = 'is-ok';
        $statusTitle = 'تایید شد';
    }
    elseif($status === 'رد شد'){
        $statusClass = 'is-no';
        $statusTitle = 'رد شد';
    }
}

$mobile = getUserMobile($p[0] ?? '', $users);
$configName = trim((string)($p[1] ?? ''));
if($configName === ''){
$configName = '—';
}
$planLine = paymentsFormatPlanLine($p[2] ?? '');
$payWhen = pnvFormatPaymentRowDateTime($p);

?>

<div class="payCard">

<div class="payColUser">
<span class="payUserName"><?php echo htmlspecialchars($p[0] ?? '-', ENT_QUOTES, 'UTF-8'); ?></span>
<span class="payUserMobile"><?php echo htmlspecialchars($mobile !== '' ? $mobile : '—', ENT_QUOTES, 'UTF-8'); ?></span>
</div>

<div class="payColPlan">
<span class="payConfigName"><?php echo htmlspecialchars($configName, ENT_QUOTES, 'UTF-8'); ?></span>
<span class="payPlanLine"><?php echo htmlspecialchars($planLine, ENT_QUOTES, 'UTF-8'); ?></span>
</div>

<div class="payActions">

<?php if($statusClass === 'is-ok'){ ?>
<span class="statusIcon is-ok" title="<?php echo htmlspecialchars($statusTitle, ENT_QUOTES, 'UTF-8'); ?>" aria-label="<?php echo htmlspecialchars($statusTitle, ENT_QUOTES, 'UTF-8'); ?>">
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
</span>
<?php } elseif($statusClass === 'is-no'){ ?>
<span class="statusIcon is-no" title="<?php echo htmlspecialchars($statusTitle, ENT_QUOTES, 'UTF-8'); ?>" aria-label="<?php echo htmlspecialchars($statusTitle, ENT_QUOTES, 'UTF-8'); ?>">
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
</span>
<?php } else { ?>
<span class="statusIcon <?php echo htmlspecialchars($statusClass, ENT_QUOTES, 'UTF-8'); ?>" title="<?php echo htmlspecialchars($statusTitle, ENT_QUOTES, 'UTF-8'); ?>" aria-label="<?php echo htmlspecialchars($statusTitle, ENT_QUOTES, 'UTF-8'); ?>">
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
</span>
<?php } ?>

<button
class="menuBtn"
type="button"
aria-label="منو"
onclick="openMenu(event,'m<?php echo $i; ?>')">
⋮
</button>

<div class="dropdown" id="m<?php echo $i; ?>" onclick="event.stopPropagation()">
<button type="button" onclick='showConfig(
<?php echo json_encode($p[0] ?? ""); ?>,
<?php echo json_encode($mobile); ?>,
<?php echo json_encode($payWhen["date"]); ?>,
<?php echo json_encode($payWhen["time"]); ?>,
<?php echo json_encode($p[1] ?? ""); ?>,
<?php echo json_encode($p[2] ?? ""); ?>
)'>نام کانفیگ</button>
<button type="button" onclick='showPayment(
<?php echo json_encode($p[0] ?? ""); ?>,
<?php echo json_encode($mobile); ?>,
<?php echo json_encode($p[1] ?? ""); ?>,
<?php echo json_encode($p[3] ?? ""); ?>,
<?php echo json_encode($payWhen["date"]); ?>,
<?php echo json_encode($payWhen["time"]); ?>
)'>جزئیات پرداخت</button>
<button type="button" onclick='showAction(
<?php echo $i; ?>,
<?php echo json_encode($p[0] ?? ""); ?>,
<?php echo json_encode($mobile); ?>,
<?php echo json_encode($p[1] ?? ""); ?>,
<?php echo json_encode($status); ?>,
<?php echo json_encode($p[7] ?? ""); ?>
)'>عملیات</button>
<button type="button" class="red" onclick='showDelete(
<?php echo $i; ?>,
<?php echo json_encode($p[0] ?? ""); ?>,
<?php echo json_encode($mobile); ?>,
<?php echo json_encode($p[1] ?? ""); ?>
)'>حذف</button>
</div>

</div>

</div>

<?php } ?>

</div>

<?php if($totalPages > 1){ ?>
<div class="pagination">
<?php for($x = 1; $x <= $totalPages; $x++){ ?>
<a
href="<?php echo htmlspecialchars(paymentsListUrl($x, $perPage), ENT_QUOTES, 'UTF-8'); ?>"
class="<?php echo ($currentPage === $x) ? 'active' : ''; ?>">
<?php echo $x; ?>
</a>
<?php } ?>
</div>
<?php } ?>

</div>

<div class="modalOverlay" id="modal">
<div class="modal" id="modalContent"></div>
</div>

<script>

const paymentsListBase = <?php echo json_encode(pnvAdminUrl('index.php?page=payments'), JSON_UNESCAPED_UNICODE); ?>;
const paymentsPerPage = <?php echo (int)$perPage; ?>;

function closeMenus(){
document.querySelectorAll('.dropdown.active').forEach(function(el){
el.classList.remove('active');
});
}

var payMenuIgnoreUntil = 0;

function openMenu(e,id){
if(e && e.preventDefault){ e.preventDefault(); }
if(e && e.stopPropagation){ e.stopPropagation(); }

var m=document.getElementById(id);
if(!m){ return; }

var willOpen = !m.classList.contains('active');
closeMenus();

if(!willOpen){
return;
}

m.classList.add('active');
payMenuIgnoreUntil = Date.now() + 350;
}

document.addEventListener('click', function(){
if(Date.now() < payMenuIgnoreUntil){
return;
}
closeMenus();
});

document.addEventListener('keydown', function(ev){
if(ev.key === 'Escape'){
closeMenus();
}
});

window.addEventListener('resize', closeMenus);

function closeModal(){
document.getElementById('modal').style.display='none';
}

function openModal(html){
closeMenus();
document.getElementById('modalContent').innerHTML=html;
document.getElementById('modal').style.display='flex';
}

function showConfig(user,mobile,date,time,config,plan){

let last4='';
if(mobile){
mobile=mobile.toString();
last4=mobile.slice(-4);
}

let planNumber='';
let match=plan.match(/\d+/);
if(match){
planNumber=match[0];
}

let finalName=config+'_'+last4+'_'+planNumber;

openModal(
'<div class="modalTitle">نام نهایی کانفیگ</div>'+
'<div class="modalInfo">'+
'نام کاربر: '+user+'<br>'+
'شماره موبایل: '+mobile+'<br>'+
'تاریخ: '+date+'<br>'+
'ساعت: '+time+
'</div>'+
'<div class="bigText" id="cfgText">'+finalName+'</div>'+
'<button class="green" style="width:100%;padding:12px;border:none;border-radius:12px;color:white;margin-bottom:12px;" onclick="copyText(\'cfgText\')">کپی نام کانفیگ</button>'+
'<div class="modalBtns"><button class="gray" onclick="closeModal()">بستن</button></div>'
);
}

function showPayment(user,mobile,config,track,date,time){
openModal(
'<div class="modalTitle">جزئیات پرداخت</div>'+
'<div class="modalInfo">'+
'نام کاربر: '+user+'<br>'+
'شماره موبایل: '+mobile+'<br>'+
'نام کانفیگ: '+config+
'</div>'+
'<div class="bigText">'+
'شماره پیگیری: '+track+'<br>'+
'تاریخ: '+date+'<br>'+
'ساعت: '+time+
'</div>'+
'<div class="modalBtns"><button class="gray" onclick="closeModal()">بستن</button></div>'
);
}

function showAction(id,user,mobile,config,status,savedLink){

status=status||'';
savedLink=savedLink||'';

let content='';

if(status==='تایید شد'){
content='<div class="bigText">'+savedLink+'</div>'+
'<div class="modalBtns"><button class="gray" onclick="closeModal()">بستن</button></div>';
}
else if(status==='رد شد'){
content='<div style="background:#450a0a;padding:14px;border-radius:12px;line-height:30px;margin-bottom:15px;">'+savedLink+'</div>'+
'<div class="modalBtns"><button class="gray" onclick="closeModal()">بستن</button></div>';
}
else{
content=
'<form method="POST">'+
'<input type="hidden" name="per" value="'+paymentsPerPage+'">'+
'<input type="hidden" name="approve_index" value="'+id+'">'+
'<input type="text" name="approve_link" id="approveLink" placeholder="لینک اشتراک">'+
'<div class="modalBtns">'+
'<button type="button" class="gray" onclick="pasteClipboard()">Paste</button>'+
'<button type="submit" name="approve_payment" class="green">تایید</button>'+
'</div></form>'+
'<hr style="margin:20px 0;border-color:#334155;">'+
'<form method="POST">'+
'<input type="hidden" name="per" value="'+paymentsPerPage+'">'+
'<input type="hidden" name="reject_index" value="'+id+'">'+
'<select name="reject_reason">'+
'<option value="اطلاعات پرداخت اشتباه است">اطلاعات پرداخت اشتباه است</option>'+
'<option value="اطلاعات پرداخت تکراری است">اطلاعات پرداخت تکراری است</option>'+
'</select>'+
'<button type="submit" name="reject_payment" class="redBtn" style="width:100%;padding:12px;border:none;border-radius:12px;color:white;">رد پرداخت</button>'+
'</form>'+
'<div class="modalBtns"><button class="gray" onclick="closeModal()">بستن</button></div>';
}

openModal(
'<div class="modalTitle">عملیات پرداخت</div>'+
'<div class="modalInfo">'+
'نام کاربر: '+user+'<br>'+
'شماره موبایل: '+mobile+'<br>'+
'نام کانفیگ: '+config+
'</div>'+
content
);
}

function showDelete(id,user,mobile,config){
openModal(
'<div class="modalTitle">حذف پرداخت</div>'+
'<div class="modalInfo">'+
'نام کاربر: '+user+'<br>'+
'شماره موبایل: '+mobile+'<br>'+
'نام کانفیگ: '+config+
'</div>'+
'<div class="modalBtns">'+
'<button class="redBtn" onclick="confirmDelete('+id+')">حذف</button>'+
'<button class="gray" onclick="closeModal()">بستن</button>'+
'</div>'
);
}

function confirmDelete(id){
if(confirm('مطمئن هستید؟')){
location.href=paymentsListBase+'&deletepayment='+id+'&per='+paymentsPerPage;
}
}

function copyText(id){
navigator.clipboard.writeText(document.getElementById(id).innerText);
alert('کپی شد');
}

async function pasteClipboard(){
try{
const text=await navigator.clipboard.readText();
document.getElementById('approveLink').value=text;
}catch(e){
alert('دسترسی clipboard داده نشده');
}
}

</script>
