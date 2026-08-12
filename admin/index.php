<?php

require_once __DIR__ . '/auth.php';
require_once "functions.php";

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

</body>

</html>

<?php

exit;

}

$page = $_GET['page'] ?? 'dashboard';

foreach([__DIR__ . '/admin_nav.php', dirname(__DIR__) . '/admin/admin_nav.php'] as $__navFile){
    if(is_file($__navFile)){
        require_once $__navFile;
        break;
    }
}

if(!function_exists('adminBottomNavStyles')){
    function adminBottomNavStyles(){}
    function adminBottomNav($options = []){}
    function adminBottomNavScript(){}
}

require_once __DIR__ . '/../profile_lib.php';

$adminProfileUser = pnvAdminUser();
$adminProfileAvatar = profileGetAdminAvatar($adminProfileUser);
$adminProfileInitial = function_exists('mb_substr')
    ? mb_strtoupper(mb_substr($adminProfileUser, 0, 1, 'UTF-8'), 'UTF-8')
    : strtoupper(substr($adminProfileUser, 0, 1));
$adminProfileApiUrl = pnvAdminUrl('profile-api.php');

$supportActionResult = null;

if($page === 'support'){

require_once __DIR__ . '/../support_lib.php';

if($_SERVER['REQUEST_METHOD'] === 'POST'){

$supportActionResult =
supportProcessAdminActions(
__DIR__ . '/../db/support.json',
true
);

if($supportActionResult['redirect']){

header('Location: ' . $supportActionResult['redirect']);

exit;

}

}

}

$plansFile = '../db/plans.json';
$cardsFile = '../db/cards.json';
$usersFile = '../db/users.json';
$paymentsFile = '../invoices/payments.csv';

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

$supportFile =
__DIR__ . '/../db/support.json';

$hasUnreadSupport = false;
$supportUnreadCount = 0;

if(file_exists($supportFile)){

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

$hasNewRenews = true;
$pendingRenewsCount++;

}

}

$today =
date("Y-m-d");

$todayUsers = 0;

foreach($users as $u){

if(
isset($u['created_at'])
&&
substr($u['created_at'],0,10)
==
$today
){

$todayUsers++;

}

}

$totalUsers = count($users);

$totalPayments = 0;
$todayPayments = 0;

$totalRenews = 0;
$todayRenews = 0;

$todayShamsi = date('Y/m/d');

foreach($payments as $pay){

    $type =
    trim($pay[9] ?? '');

    $payDate =
    trim($pay[4] ?? '');

    if($type == 'تمدید'){

        $totalRenews++;

        if($payDate == $todayShamsi){

            $todayRenews++;

        }

    }else{

        $totalPayments++;

        if($payDate == $todayShamsi){

            $todayPayments++;

        }

    }

}

$renewsCount = 0;

$renewFile =
"../db/renews.json";

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
'../db/'.$server.'.csv'
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

.adminProfileCard{
background:#1e293b;
padding:14px;
border-radius:12px;
margin:18px 0 12px;
display:flex;
align-items:center;
gap:12px;
}

.adminProfileAvatar{
width:48px;
height:48px;
border-radius:50%;
background:linear-gradient(135deg,#22c55e,#16a34a);
display:flex;
align-items:center;
justify-content:center;
font-size:18px;
font-weight:bold;
flex-shrink:0;
overflow:hidden;
}

.adminProfileAvatar img{
width:100%;
height:100%;
object-fit:cover;
display:block;
}

.adminProfileMeta{
flex:1;
min-width:0;
}

.adminProfileName{
font-size:14px;
font-weight:bold;
margin-bottom:4px;
word-break:break-all;
}

.adminProfileBtn{
display:inline-block;
margin-top:6px;
padding:6px 10px;
border:none;
border-radius:8px;
background:#334155;
color:#fff;
font-family:tahoma;
font-size:12px;
cursor:pointer;
}

.adminProfileModal{
position:fixed;
inset:0;
background:rgba(0,0,0,.65);
display:none;
align-items:center;
justify-content:center;
z-index:9999;
padding:16px;
}

.adminProfileModal.is-open{
display:flex;
}

.adminProfileModalBox{
width:100%;
max-width:360px;
background:#1e293b;
border-radius:16px;
padding:20px;
color:#fff;
}

.adminProfileModalBox h3{
margin:0 0 12px;
font-size:17px;
}

.adminProfileModalPreview{
width:88px;
height:88px;
border-radius:50%;
margin:0 auto 14px;
background:linear-gradient(135deg,#22c55e,#16a34a);
display:flex;
align-items:center;
justify-content:center;
font-size:28px;
font-weight:bold;
overflow:hidden;
}

.adminProfileModalPreview img{
width:100%;
height:100%;
object-fit:cover;
display:block;
}

.adminProfileModalBox input[type=file]{
width:100%;
margin:10px 0;
color:#cbd5e1;
}

.adminProfileModalActions{
display:flex;
gap:8px;
flex-wrap:wrap;
}

.adminProfileModalActions button{
flex:1;
min-width:120px;
padding:10px 12px;
border:none;
border-radius:10px;
cursor:pointer;
font-family:tahoma;
}

.adminProfileSave{
background:#22c55e;
color:#052e16;
}

.adminProfileRemove{
background:#ef4444;
color:#fff;
}

.adminProfileClose{
background:#334155;
color:#fff;
}

.adminProfileFlash{
margin-top:10px;
font-size:13px;
color:#fca5a5;
min-height:18px;
}

.statsGrid{
display:grid;
grid-template-columns:repeat(2,1fr);
gap:16px;
margin-bottom:20px;
}

.statBox{
background:#1e293b;
padding:22px;
border-radius:18px;
text-align:center;
}

.statTitle{
font-size:15px;
color:#cbd5e1;
margin-bottom:12px;
}

.statValue{
font-size:30px;
font-weight:bold;
color:#22c55e;
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
padding-bottom:84px;
}

.content-support{
margin-right:0;
height:100%;
max-height:100dvh;
min-height:0;
padding-top:56px;
padding-bottom:0;
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

.statsGrid{
grid-template-columns:1fr;
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

<a href="<?php echo htmlspecialchars(pnvAdminUrl('index.php?page=cards'), ENT_QUOTES, 'UTF-8'); ?>">

مدیریت کارت ها

</a>

<a href="<?php echo htmlspecialchars(pnvAdminUrl('downloads.php'), ENT_QUOTES, 'UTF-8'); ?>">

مدیریت دانلودها

</a>

<a href="<?php echo htmlspecialchars(pnvAdminUrl('xui-servers.php'), ENT_QUOTES, 'UTF-8'); ?>">

سرورهای 3x-ui

</a>

<a href="<?php echo htmlspecialchars(pnvAdminUrl('index.php?page=upload'), ENT_QUOTES, 'UTF-8'); ?>">

آپلود فایل کاربران سرورها

</a>

<div class="adminProfileCard">
<div class="adminProfileAvatar" id="adminSidebarAvatar">
<?php if($adminProfileAvatar !== ''){ ?>
<img src="<?php echo htmlspecialchars('/' . ltrim($adminProfileAvatar, '/'), ENT_QUOTES, 'UTF-8'); ?>" alt="">
<?php } else { ?>
<?php echo htmlspecialchars($adminProfileInitial, ENT_QUOTES, 'UTF-8'); ?>
<?php } ?>
</div>
<div class="adminProfileMeta">
<div class="adminProfileName"><?php echo htmlspecialchars($adminProfileUser, ENT_QUOTES, 'UTF-8'); ?></div>
<button type="button" class="adminProfileBtn" id="adminProfileOpenBtn">تغییر عکس پروفایل</button>
</div>
</div>

<a
href="<?php echo htmlspecialchars(pnvAdminUrl('index.php?logout=1'), ENT_QUOTES, 'UTF-8'); ?>"
class="red">

خروج

</a>

</div>

<div class="content <?php echo $page=='support' ? 'content-support' : ''; ?>">

<?php if($page=='dashboard'){ ?>

<?php include "dashboard.php"; ?>

<?php } ?>

<?php if($page=='support'){ ?>

<?php
$supportEmbedded = true;
include "support.php";
?>

<?php } ?>

<?php if($page=='payments'){ ?>

<?php include "payments.php"; ?>

<?php } ?>

<?php if($page=='renews'){ ?>

<?php include "renews.php"; ?>

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

<div class="adminProfileModal" id="adminProfileModal">
<div class="adminProfileModalBox">
<h3>عکس پروفایل ادمین</h3>
<div class="adminProfileModalPreview" id="adminProfilePreview">
<?php if($adminProfileAvatar !== ''){ ?>
<img src="<?php echo htmlspecialchars('/' . ltrim($adminProfileAvatar, '/'), ENT_QUOTES, 'UTF-8'); ?>" alt="">
<?php } else { ?>
<?php echo htmlspecialchars($adminProfileInitial, ENT_QUOTES, 'UTF-8'); ?>
<?php } ?>
</div>
<form id="adminProfileForm" enctype="multipart/form-data">
<input type="file" name="avatar" id="adminProfileFile" accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp" required>
<div class="adminProfileModalActions">
<button type="submit" class="adminProfileSave">ذخیره عکس</button>
<?php if($adminProfileAvatar !== ''){ ?>
<button type="button" class="adminProfileRemove" id="adminProfileRemoveBtn">حذف عکس</button>
<?php } ?>
<button type="button" class="adminProfileClose" id="adminProfileCloseBtn">بستن</button>
</div>
<div class="adminProfileFlash" id="adminProfileFlash"></div>
</form>
</div>
</div>

<?php
$adminBottomActive = in_array($page, ['support', 'renews', 'payments'], true) ? $page : '';
adminBottomNav([
    'active' => $adminBottomActive,
    'more_mode' => 'sidebar',
    'badges' => [
        'support' => $supportUnreadCount,
        'renews' => $pendingRenewsCount,
        'payments' => $pendingPaymentsCount,
    ],
]);
adminBottomNavScript();
?>

<script>
(function(){
    const menuBtn = document.getElementById('adminMenuBtn');
    const overlay = document.getElementById('adminSidebarOverlay');

    function closeMenu(){
        document.body.classList.remove('adminSidebarOpen');
    }

    if(menuBtn){
        menuBtn.addEventListener('click', function(){
            document.body.classList.toggle('adminSidebarOpen');
        });
    }

    if(overlay){
        overlay.addEventListener('click', closeMenu);
    }
})();
</script>

<script>
(function(){
    const openBtn = document.getElementById('adminProfileOpenBtn');
    const modal = document.getElementById('adminProfileModal');
    const closeBtn = document.getElementById('adminProfileCloseBtn');
    const form = document.getElementById('adminProfileForm');
    const fileInput = document.getElementById('adminProfileFile');
    const preview = document.getElementById('adminProfilePreview');
    const sidebarAvatar = document.getElementById('adminSidebarAvatar');
    const flash = document.getElementById('adminProfileFlash');
    const removeBtn = document.getElementById('adminProfileRemoveBtn');
    const apiUrl = <?php echo json_encode($adminProfileApiUrl, JSON_UNESCAPED_UNICODE); ?>;
    const initial = <?php echo json_encode($adminProfileInitial, JSON_UNESCAPED_UNICODE); ?>;

    if(!openBtn || !modal || !form){
        return;
    }

    function setFlash(text){
        if(flash){
            flash.textContent = text || '';
        }
    }

    function renderAvatar(target, avatarUrl){
        if(!target){
            return;
        }

        if(avatarUrl){
            target.innerHTML = '<img src="' + avatarUrl + '" alt="">';
            return;
        }

        target.textContent = initial;
    }

    function openModal(){
        modal.classList.add('is-open');
        setFlash('');
    }

    function closeModal(){
        modal.classList.remove('is-open');
        setFlash('');
        form.reset();
    }

    openBtn.addEventListener('click', openModal);
    closeBtn.addEventListener('click', closeModal);
    modal.addEventListener('click', function(e){
        if(e.target === modal){
            closeModal();
        }
    });

    fileInput.addEventListener('change', function(){
        const file = fileInput.files && fileInput.files[0];

        if(!file){
            return;
        }

        const reader = new FileReader();
        reader.onload = function(){
            preview.innerHTML = '<img src="' + reader.result + '" alt="">';
        };
        reader.readAsDataURL(file);
    });

    form.addEventListener('submit', function(e){
        e.preventDefault();
        setFlash('');

        const file = fileInput.files && fileInput.files[0];

        if(!file){
            setFlash('لطفاً یک عکس انتخاب کنید');
            return;
        }

        const body = new FormData();
        body.append('setavatar', '1');
        body.append('avatar', file);

        fetch(apiUrl, {
            method: 'POST',
            body: body,
            credentials: 'same-origin'
        })
        .then(function(res){ return res.json(); })
        .then(function(data){
            if(!data || !data.ok){
                setFlash((data && data.error) ? data.error : 'ذخیره عکس انجام نشد');
                return;
            }

            const avatarUrl = '/' + String(data.avatar || '').replace(/^\/+/, '');
            renderAvatar(preview, avatarUrl);
            renderAvatar(sidebarAvatar, avatarUrl);
            closeModal();
            location.reload();
        })
        .catch(function(){
            setFlash('خطا در ارتباط با سرور');
        });
    });

    if(removeBtn){
        removeBtn.addEventListener('click', function(){
            if(!confirm('عکس پروفایل حذف شود؟')){
                return;
            }

            const body = new FormData();
            body.append('removeavatar', '1');

            fetch(apiUrl, {
                method: 'POST',
                body: body,
                credentials: 'same-origin'
            })
            .then(function(res){ return res.json(); })
            .then(function(data){
                if(!data || !data.ok){
                    setFlash((data && data.error) ? data.error : 'حذف عکس انجام نشد');
                    return;
                }

                location.reload();
            })
            .catch(function(){
                setFlash('خطا در ارتباط با سرور');
            });
        });
    }
})();
</script>

<script>
(function(){
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

</body>

</html>