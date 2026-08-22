<?php

// bigjay_controller اغلب auth/functions را کنار index ندارد؛ soft-load کن
$__pnvBootCandidates = [
    __DIR__ . '/auth.php',
    __DIR__ . '/../admin/auth.php',
];
foreach($__pnvBootCandidates as $__pnvBootFile){
    if(is_file($__pnvBootFile)){
        require_once $__pnvBootFile;
        if(function_exists('pnvAdminIsLoggedIn')){
            break;
        }
    }
}

$__pnvFuncCandidates = [
    __DIR__ . '/functions.php',
    __DIR__ . '/../admin/functions.php',
];
foreach($__pnvFuncCandidates as $__pnvBootFile){
    if(is_file($__pnvBootFile)){
        require_once $__pnvBootFile;
        if(function_exists('pnvAdminInclude') || function_exists('getUserMobile')){
            break;
        }
    }
}

if(!function_exists('pnvJalaliToday')){
    foreach([
        __DIR__ . '/../pnv_date_bootstrap.php',
        dirname(__DIR__) . '/pnv_date_bootstrap.php',
    ] as $__pnvDateBoot){
        if(is_file($__pnvDateBoot)){
            require_once $__pnvDateBoot;
            break;
        }
    }
}

if(!function_exists('pnvAdminInclude')){
    function pnvAdminInclude($fileName){
        $name = ltrim((string)$fileName, '/');
        foreach([
            __DIR__ . '/' . $name,
            __DIR__ . '/../admin/' . $name,
        ] as $path){
            if(is_file($path)){
                extract($GLOBALS, EXTR_SKIP);
                include $path;
                return true;
            }
        }
        echo '<div class="box" style="padding:20px;color:#fecaca;background:#7f1d1d;border-radius:12px;margin:12px 0;">'
            . 'فایل «' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '» یافت نشد.'
            . '</div>';
        return false;
    }
}

if(!function_exists('pnvAdminIsLoggedIn')){
    if(session_status() === PHP_SESSION_NONE){
        session_start();
    }
    if(!defined('PNV_ADMIN_BASE')){
        define('PNV_ADMIN_BASE', '/bigjay_controller');
    }
    function pnvAdminIsLoggedIn(){
        $ok = !empty($_SESSION['pnv_admin']['user']) && !empty($_SESSION['pnv_admin']['token']);
        if($ok){
            $_SESSION['admin'] = true;
        }
        return $ok || !empty($_SESSION['admin']);
    }
    function pnvAdminLogout(){
        unset($_SESSION['pnv_admin'], $_SESSION['admin']);
    }
    function pnvAdminValidateLogin($u, $p){ return null; }
    function pnvAdminLogin($admin){ $_SESSION['admin'] = true; }
    function pnvAdminEntryUrl(){ return rtrim(PNV_ADMIN_BASE, '/') . '/'; }
    function pnvAdminUrl($path = 'index.php'){
        $base = rtrim(PNV_ADMIN_BASE, '/');
        if($path === '' || $path === 'index.php'){ return $base . '/'; }
        return $base . '/' . ltrim($path, '/');
    }
    function pnvAdminRequireAuth(){
        if(!pnvAdminIsLoggedIn()){
            header('Location: ' . pnvAdminEntryUrl());
            exit;
        }
    }
}

if(!function_exists('statusColor')){
    function statusColor($status){
        if($status=='تایید شد'){ return '#22c55e'; }
        if($status=='رد شد'){ return '#ef4444'; }
        return '#eab308';
    }
}

if(isset($_GET['logout'])){

pnvAdminLogout();

header("Location: " . pnvAdminEntryUrl());

exit;

}

if(!pnvAdminIsLoggedIn()){

if($_SERVER['REQUEST_METHOD']=="POST"){

$admin = pnvAdminValidateLogin(
trim($_POST['username'] ?? ''),
$_POST['password'] ?? ''
);

if($admin){

pnvAdminLogin($admin);

header("Location: " . pnvAdminEntryUrl());

exit;

}

$error="اطلاعات ورود اشتباه است";

}

?>

<!DOCTYPE html>

<html lang="fa">

<head>

<meta charset="UTF-8">

<meta name="viewport"
content="width=device-width, initial-scale=1.0, viewport-fit=cover, interactive-widget=resizes-content">

<title>

ورود مدیریت

</title>

<script src="<?php echo htmlspecialchars(pnvAdminUrl('sw-cleanup.js?v=1'), ENT_QUOTES, 'UTF-8'); ?>"></script>

<link rel="stylesheet" href="/fonts.css">

<style>

body{
background:#0f172a;
font-family:tahoma;
direction:rtl;
display:flex;
justify-content:center;
align-items:center;
height:100vh;
margin:0;
color:white;
}

.box{
width:90%;
max-width:400px;
background:#1e293b;
padding:30px;
border-radius:15px;
}

input,
button{
width:100%;
padding:12px;
margin-top:10px;
margin-bottom:15px;
border:none;
border-radius:8px;
box-sizing:border-box;
}

button{
background:#22c55e;
color:white;
cursor:pointer;
}

.error{
background:#dc2626;
padding:10px;
border-radius:8px;
margin-bottom:15px;
}

</style>

</head>

<body>

<div class="box">

<h2>

ورود مدیریت

</h2>

<?php if(isset($error)){ ?>

<div class="error">

<?php echo $error; ?>

</div>

<?php } ?>

<form method="POST">

<input
type="text"
name="username"
placeholder="نام کاربری"
required>

<input
type="password"
name="password"
placeholder="رمز عبور"
required>

<button type="submit">

ورود

</button>

</form>

</div>

<?php require_once __DIR__ . '/../form_validation_fa.php'; pnvFormValidationFaScript(); ?>

</body>

</html>

<?php

exit;

}

$page = $_GET['page'] ?? 'dashboard';
$pnvRootDir = dirname(__DIR__);

require_once __DIR__ . '/admin_nav.php';

if(!function_exists('adminPageEnd')){
    function adminPageEnd($options = []){
        if(function_exists('adminBottomNavStyles')){
            adminBottomNavStyles();
        }
        if(function_exists('adminBottomNav')){
            adminBottomNav($options);
        }
        if(function_exists('adminBottomNavScript')){
            adminBottomNavScript();
        }
    }
}

$supportActionResult = null;

if($page === 'support' && file_exists(__DIR__ . '/../support_lib.php')){

require_once __DIR__ . '/../support_lib.php';

if($_SERVER['REQUEST_METHOD'] === 'POST'){

$supportActionResult =
supportProcessAdminActions(
$pnvRootDir . '/db/support.json',
true
);

if($supportActionResult['redirect']){

header('Location: ' . $supportActionResult['redirect']);

exit;

}

}

}

$plansFile = $pnvRootDir . '/db/plans.json';
$cardsFile = $pnvRootDir . '/db/cards.json';
$usersFile = $pnvRootDir . '/db/users.json';
$paymentsFile = $pnvRootDir . '/invoices/payments.csv';

$plans = file_exists($plansFile)
? json_decode(file_get_contents($plansFile),true)
: [];

$cards = file_exists($cardsFile)
? json_decode(file_get_contents($cardsFile),true)
: [];

$users = file_exists($usersFile)
? json_decode(file_get_contents($usersFile),true)
: [];

if(!is_array($plans)){
$plans=[];
}

if(!is_array($cards)){
$cards=[];
}

if(!is_array($users)){
$users=[];
}

$payments=[];

if(file_exists($paymentsFile)){

$f=fopen($paymentsFile,'r');

while(($d=fgetcsv($f))!==FALSE){

$payments[]=$d;

}

fclose($f);

}

if(file_exists(__DIR__ . '/../instant_pay_lib.php')){
    require_once __DIR__ . '/../instant_pay_lib.php';
    if(function_exists('instantPayPurgeStaleAdminRows')){
        instantPayPurgeStaleAdminRows();
        $payments = [];
        if(file_exists($paymentsFile)){
            $f = fopen($paymentsFile, 'r');
            while(($d = fgetcsv($f)) !== false){
                $payments[] = $d;
            }
            fclose($f);
        }
    }
}

$supportFile =
$pnvRootDir . '/db/support.json';

$hasUnreadSupport = false;

if(file_exists($supportFile) && file_exists(__DIR__ . '/../support_lib.php')){

require_once __DIR__ . '/../support_lib.php';

$supportData = supportLoad($supportFile);

$hasUnreadSupport = supportAdminHasUnread($supportData);

$supportUnreadCount = supportAdminUnreadTotal($supportData);

}

$hasNewPayments = false;
$hasNewRenews = false;
$pendingPaymentsCount = 0;
$pendingRenewsCount = 0;

foreach($payments as $pay){

$status =
trim($pay[6] ?? '');

$type =
trim($pay[9] ?? '');

if(
($type == 'خرید' || $type == '')
&&
$status != 'تایید شد'
&&
$status != 'رد شد'
){

if(function_exists('instantPayAdminRowVisible') && !instantPayAdminRowVisible($pay)){
    continue;
}

$hasNewPayments = true;
$pendingPaymentsCount++;

}

if(
$type == 'تمدید'
&&
$status != 'تایید شد'
&&
$status != 'رد شد'
){

if(function_exists('instantPayAdminRowVisible') && !instantPayAdminRowVisible($pay)){
    continue;
}

$hasNewRenews = true;
$pendingRenewsCount++;

}

}

$supportUnreadCount = 0;

$todayUsers = 0;

foreach($users as $u){

if(isset($u['created_at']) && pnvIsTodayTehran($u['created_at'])){

$todayUsers++;

}

}

$totalUsers = count($users);

$totalPayments = 0;
$todayPayments = 0;

$totalRenews = 0;
$todayRenews = 0;

$todayShamsi = pnvJalaliToday('/');

foreach($payments as $pay){

    $type =
    trim($pay[9] ?? '');

    if($type == 'تمدید'){

        $totalRenews++;

        if(pnvPaymentRowIsToday($pay)){

            $todayRenews++;

        }

    }else{

        $totalPayments++;

        if(pnvPaymentRowIsToday($pay)){

            $todayPayments++;

        }

    }

}

$renewsCount = 0;

$renewFile =
$pnvRootDir . '/db/renews.json';

if(file_exists($renewFile)){

$renews =
json_decode(
file_get_contents($renewFile),
true
);

if(is_array($renews)){

$renewsCount =
count($renews);

}

}

$telegramEnabled = false;
$telegramConfigured = false;
$xuiEnabled = false;
$xuiConfigured = false;

if(file_exists(__DIR__ . '/../telegram_lib.php')){
    require_once __DIR__ . '/../telegram_lib.php';
    if(function_exists('telegramLoadConfig')){
        $tgConfig = telegramLoadConfig();
        $telegramConfigured = trim((string)($tgConfig['bot_token'] ?? '')) !== '';
        $telegramEnabled = !empty($tgConfig['enabled']) && $telegramConfigured;
    }
}

if(file_exists(__DIR__ . '/../xui_lib.php')){
    require_once __DIR__ . '/../xui_lib.php';
    if(function_exists('xuiLoadConfig')){
        $xuiConfig = xuiLoadConfig();
        $hasToken = false;
        foreach(($xuiConfig['servers'] ?? []) as $server){
            $token = trim((string)($server['api_token'] ?? ''));
            if($token !== '' && strpos($token, 'REPLACE_TOKEN_') !== 0){
                $hasToken = true;
                break;
            }
        }
        $xuiConfigured = $hasToken;
        $xuiEnabled = (function_exists('xuiIsEnabled') ? xuiIsEnabled($xuiConfig) : !empty($xuiConfig['enabled'])) && $hasToken;
    }
}

if(isset($_POST['add_plan'])){

$plans[]=[
'name'=>trim($_POST['plan_name']),
'price'=>trim($_POST['plan_price'])
];

file_put_contents(
$plansFile,
json_encode(
$plans,
JSON_UNESCAPED_UNICODE|
JSON_PRETTY_PRINT
)
);

header('Location: ' . pnvAdminUrl('plans.php'));

exit;

}

if($page === 'plans'){

header('Location: ' . pnvAdminUrl('plans.php'));

exit;

}

if(isset($_GET['deleteplan'])){

$id=intval($_GET['deleteplan']);

if(isset($plans[$id])){

unset($plans[$id]);

$plans=array_values($plans);

file_put_contents(
$plansFile,
json_encode(
$plans,
JSON_UNESCAPED_UNICODE|
JSON_PRETTY_PRINT
)
);

}

header('Location: ' . pnvAdminUrl('plans.php'));

exit;

}

if(isset($_POST['add_card'])){

$cards[]=[
'name'=>trim($_POST['card_name']),
'card'=>trim($_POST['card_number'])
];

file_put_contents(
$cardsFile,
json_encode(
$cards,
JSON_UNESCAPED_UNICODE|
JSON_PRETTY_PRINT
)
);

header('Location: ' . pnvAdminUrl('index.php?page=cards'));

exit;

}

if(isset($_GET['deletecard'])){

$id=intval($_GET['deletecard']);

if(isset($cards[$id])){

unset($cards[$id]);

$cards=array_values($cards);

file_put_contents(
$cardsFile,
json_encode(
$cards,
JSON_UNESCAPED_UNICODE|
JSON_PRETTY_PRINT
)
);

}

header('Location: ' . pnvAdminUrl('index.php?page=cards'));

exit;

}

if(isset($_POST['uploadcsv'])){

$server = $_POST['server'];

move_uploaded_file(
$_FILES['csv']['tmp_name'],
$pnvRootDir . '/db/' . $server . '.csv'
);

header('Location: ' . pnvAdminUrl('index.php?page=upload'));

exit;

}

?>

<!DOCTYPE html>

<html lang="fa">

<head>

<meta charset="UTF-8">

<meta name="viewport"
content="width=device-width, initial-scale=1.0, viewport-fit=cover, interactive-widget=resizes-content">

<title>

پنل مدیریت

</title>

<script src="<?php echo htmlspecialchars(pnvAdminUrl('sw-cleanup.js?v=1'), ENT_QUOTES, 'UTF-8'); ?>"></script>

<link rel="stylesheet" href="/fonts.css">

<style>

body{
margin:0;
font-family:tahoma;
background:#0f172a;
direction:rtl;
color:white;
}

.sidebar{
width:260px;
background:#111827;
position:fixed;
top:0;
right:0;
bottom:0;
overflow:auto;
padding:20px;
box-sizing:border-box;
z-index:40;
transition:transform .2s ease;
}

.sidebar a{
display:block;
background:#1e293b;
padding:14px;
border-radius:10px;
margin-bottom:10px;
text-decoration:none;
color:white;
position:relative;
}

.adminMenuBtn{
display:none;
position:fixed;
top:12px;
right:12px;
z-index:50;
width:44px;
height:44px;
border:0;
border-radius:12px;
background:#22c55e;
color:#fff;
font-size:22px;
cursor:pointer;
}

.adminSidebarOverlay{
display:none;
position:fixed;
inset:0;
background:rgba(0,0,0,.45);
z-index:35;
}

.content{
margin-right:280px;
padding:20px;
}

.box{
background:#1e293b;
padding:20px;
border-radius:15px;
margin-bottom:20px;
overflow:auto;
}

.card{
background:#0f172a;
padding:18px;
border-radius:15px;
margin-bottom:18px;
}

.card p{
line-height:30px;
word-break:break-all;
}

.status{
padding:5px 10px;
border-radius:8px;
font-size:13px;
display:inline-block;
}

input,
select,
button{
padding:12px;
border:none;
border-radius:8px;
margin:5px;
}

button{
background:#22c55e;
color:white;
cursor:pointer;
}

.red{
background:#ef4444;
padding:8px 12px;
border-radius:8px;
color:white;
text-decoration:none;
display:inline-block;
}

table:not(.payTable){
width:100%;
border-collapse:collapse;
}

table:not(.payTable) th,
table:not(.payTable) td{
padding:12px;
border-bottom:1px solid #334155;
text-align:center;
}

table:not(.payTable) th{
background:#334155;
}

.pagination{
margin-top:25px;
text-align:center;
}

.pagination a{
display:inline-block;
padding:10px 15px;
margin:5px;
background:#334155;
color:white;
border-radius:8px;
text-decoration:none;
}

.pagination .active{
background:#22c55e;
}

.supportMenu{
position:relative;
}

.notifDot{
position:absolute;
top:10px;
left:10px;
width:12px;
height:12px;
background:#ef4444;
border-radius:50%;
box-shadow:0 0 10px rgba(239,68,68,.7);
animation:pulse 1.5s infinite;
}

@keyframes pulse{

0%{
transform:scale(1);
opacity:1;
}

50%{
transform:scale(1.3);
opacity:.7;
}

100%{
transform:scale(1);
opacity:1;
}

}


.content-support{
margin-right:280px;
padding:0;
height:100vh;
overflow:hidden;
background:#0b1220;
}

@media(max-width:768px){

body.adminPageSupport{
overflow:hidden;
height:100dvh;
}

.adminMenuBtn{display:none}


.sidebar{
position:fixed;
width:min(86vw,280px);
height:100%;
transform:translateX(110%);
}

body.adminSidebarOpen .sidebar{
transform:translateX(0);
}

body.adminSidebarOpen .adminSidebarOverlay{
display:block;
}

.content{
margin-right:0;
padding-top:64px;
}

.content-support{
margin-right:0;
height:100%;
max-height:100dvh;
min-height:0;
padding-top:0;
}

.content-support input,
.content-support select,
.content-support button,
.content-support textarea{
width:auto !important;
max-width:none !important;
margin:0 !important;
}

input,
select,
button{
width:100%;
box-sizing:border-box;
}

}

</style>

<?php adminBottomNavStyles(); ?>

</head>

<body class="<?php echo $page === 'support' ? 'adminPageSupport' : 'adminHasBottomNav'; ?>">

<button type="button" class="adminMenuBtn" id="adminMenuBtn" aria-label="منو">☰</button>
<div class="adminSidebarOverlay" id="adminSidebarOverlay"></div>

<div class="sidebar" id="adminSidebar">

<h2>

مدیریت

</h2>

<a href="<?php echo htmlspecialchars(pnvAdminUrl(), ENT_QUOTES, 'UTF-8'); ?>">

داشبورد

</a>

<a href="<?php echo htmlspecialchars(pnvAdminUrl('index.php?page=support'), ENT_QUOTES, 'UTF-8'); ?>"
class="supportMenu"
id="adminSupportMenu">

<?php if($hasUnreadSupport){ ?>

<span class="notifDot"></span>

<?php } ?>

پیام‌های کاربران

</a>

<a href="<?php echo htmlspecialchars(pnvAdminUrl('users.php'), ENT_QUOTES, 'UTF-8'); ?>">

لیست کاربران

</a>

<a
href="<?php echo htmlspecialchars(pnvAdminUrl('index.php?page=payments'), ENT_QUOTES, 'UTF-8'); ?>"
class="supportMenu">

<?php if($hasNewPayments){ ?>

<span class="notifDot"></span>

<?php } ?>

لیست خرید های جدید

</a>

<a
href="<?php echo htmlspecialchars(pnvAdminUrl('index.php?page=renews'), ENT_QUOTES, 'UTF-8'); ?>"
class="supportMenu">

<?php if($hasNewRenews){ ?>

<span class="notifDot"></span>

<?php } ?>

لیست تمدید ها

</a>

<a href="<?php echo htmlspecialchars(pnvAdminUrl('plans.php'), ENT_QUOTES, 'UTF-8'); ?>">

مدیریت پلن ها

</a>

<a href="<?php echo htmlspecialchars(pnvAdminUrl('campaigns.php'), ENT_QUOTES, 'UTF-8'); ?>">

کمپین‌ها

</a>

<a href="<?php echo htmlspecialchars(pnvAdminUrl('index.php?page=cards'), ENT_QUOTES, 'UTF-8'); ?>">

مدیریت کارت ها

</a>

<a href="<?php echo htmlspecialchars(pnvAdminUrl('downloads.php'), ENT_QUOTES, 'UTF-8'); ?>">

مدیریت دانلودها

</a>

<a href="<?php echo htmlspecialchars(pnvAdminUrl('telegram.php'), ENT_QUOTES, 'UTF-8'); ?>">

تنظیمات بات تلگرام

</a>

<a href="<?php echo htmlspecialchars(pnvAdminUrl('bale.php'), ENT_QUOTES, 'UTF-8'); ?>">

بله — پرداخت آنی

</a>

<a href="<?php echo htmlspecialchars(pnvAdminUrl('xui-servers.php'), ENT_QUOTES, 'UTF-8'); ?>">

سرورهای 3x-ui

</a>

<a href="<?php echo htmlspecialchars(pnvAdminUrl('index.php?page=upload'), ENT_QUOTES, 'UTF-8'); ?>">

آپلود فایل کاربران سرورها

</a>

<a
href="<?php echo htmlspecialchars(pnvAdminUrl('index.php?logout=1'), ENT_QUOTES, 'UTF-8'); ?>"
class="red">

خروج

</a>

</div>

<div class="content <?php echo $page=='support' ? 'content-support' : ''; ?>">

<?php if($page=='dashboard'){ ?>

<?php pnvAdminInclude('dashboard.php'); ?>

<?php } ?>

<?php if($page=='support'){ ?>

<?php
$supportEmbedded = true;
pnvAdminInclude('support.php');
?>

<?php } ?>

<?php if($page=='payments'){ ?>

<?php pnvAdminInclude('payments.php'); ?>

<?php } ?>

<?php if($page=='renews'){ ?>

<?php pnvAdminInclude('renews.php'); ?>

<?php } ?>

<?php if($page=='cards'){ ?>

<div class="box">

<h2>

مدیریت کارت ها

</h2>

<form method="POST">

<input
type="text"
name="card_name"
placeholder="به نام"
required>

<input
type="text"
name="card_number"
placeholder="شماره کارت"
required>

<button
type="submit"
name="add_card">

افزودن کارت

</button>

</form>

</div>

<div class="box">

<table>

<tr>

<th>به نام</th>
<th>شماره کارت</th>
<th>حذف</th>

</tr>

<?php foreach($cards as $i=>$card){ ?>

<tr>

<td>

<?php echo $card['name']; ?>

</td>

<td>

<?php echo $card['card']; ?>

</td>

<td>

<a
href="<?php echo htmlspecialchars(pnvAdminUrl('index.php?page=cards&deletecard=' . $i), ENT_QUOTES, 'UTF-8'); ?>"
class="red">

حذف

</a>

</td>

</tr>

<?php } ?>

</table>

</div>

<?php } ?>

<?php if($page=='upload'){ ?>

<div class="box">

<h2>

آپلود فایل کاربران سرورها

</h2>

<form
method="POST"
enctype="multipart/form-data">

<select name="server">

<option value="vip">

vip.csv

</option>

<option value="vip2">

vip2.csv

</option>

<option value="vip3">

vip3.csv

</option>

</select>

<input
type="file"
name="csv"
required>

<button
type="submit"
name="uploadcsv">

آپلود فایل

</button>

</form>

</div>

<?php } ?>

</div>

<?php
$adminBottomActive = 'dashboard';
if(in_array($page, ['support', 'renews', 'payments'], true)){
    $adminBottomActive = $page;
}
adminPageEnd([
    'active' => $adminBottomActive,
    'more_mode' => 'sidebar',
    'badges' => [
        'support' => $supportUnreadCount,
        'renews' => $pendingRenewsCount,
        'payments' => $pendingPaymentsCount,
    ],
]);
?>

<script>
(function(){
    const menuBtn = document.getElementById('adminMenuBtn');
    const overlay = document.getElementById('adminSidebarOverlay');

    function closeMenu(){
        document.body.classList.remove('adminSidebarOpen');
    }

    function toggleMenu(){
        document.body.classList.toggle('adminSidebarOpen');
    }

    if(menuBtn){
        menuBtn.addEventListener('click', toggleMenu);
    }

    if(overlay){
        overlay.addEventListener('click', closeMenu);
    }

    const menuLink = document.getElementById('adminSupportMenu');
    if(!menuLink){
        return;
    }

    const pollUrl = <?php echo json_encode(pnvAdminUrl('support-api.php'), JSON_UNESCAPED_UNICODE); ?>;

    function setUnreadDot(hasUnread, unreadCount){
        let dot = menuLink.querySelector('.notifDot');

        if(hasUnread){
            if(!dot){
                dot = document.createElement('span');
                dot.className = 'notifDot';
                menuLink.insertBefore(dot, menuLink.firstChild);
            }
            if(typeof window.adminBottomNavSetBadge === 'function'){
                window.adminBottomNavSetBadge('support', unreadCount || 1);
            }
            return;
        }

        if(dot){
            dot.remove();
        }
        if(typeof window.adminBottomNavSetBadge === 'function'){
            window.adminBottomNavSetBadge('support', 0);
        }
    }

    function checkUnread(){
        fetch(pollUrl, {credentials:'same-origin'})
            .then(function(r){ return r.json(); })
            .then(function(data){
                setUnreadDot(!!data.has_unread, data.unread_count || 0);
            })
            .catch(function(){});
    }

    checkUnread();
    setInterval(checkUnread, 10000);
})();
</script>

<?php require_once __DIR__ . '/../form_validation_fa.php'; pnvFormValidationFaScript(); ?>

</body>

</html>