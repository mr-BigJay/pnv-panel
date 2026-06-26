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
content="width=device-width, initial-scale=1.0">

<title>

ورود مدیریت

</title>

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

$supportActionResult = null;

if($page === 'support'){

require_once __DIR__ . '/../support_lib.php';

if($_SERVER['REQUEST_METHOD'] === 'POST'){

$supportActionResult =
supportProcessAdminActions(
'../db/support.json',
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
"../db/support.json";

$hasUnreadSupport = false;

if(file_exists($supportFile)){

require_once __DIR__ . '/../support_lib.php';

$supportData = supportLoad($supportFile);

$hasUnreadSupport = supportAdminHasUnread($supportData);

}

$hasNewPayments = false;
$hasNewRenews = false;

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

}

if(
$type == 'تمدید'
&&
$status != 'تایید شد'
&&
$status != 'رد شد'
){

$hasNewRenews = true;

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
content="width=device-width, initial-scale=1.0">

<title>

پنل مدیریت

</title>

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

table{
width:100%;
border-collapse:collapse;
}

th,
td{
padding:12px;
border-bottom:1px solid #334155;
text-align:center;
}

th{
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

.sidebar{
position:relative;
width:100%;
height:auto;
}

.content{
margin-right:0;
}

.content-support{
margin-right:0;
height:100%;
max-height:100dvh;
min-height:0;
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

</head>

<body class="<?php echo $page === 'support' ? 'adminPageSupport' : ''; ?>">

<div class="sidebar">

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

<script>
(function(){
    const menuLink = document.getElementById('adminSupportMenu');
    if(!menuLink){
        return;
    }

    const pollUrl = <?php echo json_encode(pnvAdminUrl('support-api.php'), JSON_UNESCAPED_UNICODE); ?>;

    function setUnreadDot(hasUnread){
        let dot = menuLink.querySelector('.notifDot');

        if(hasUnread){
            if(!dot){
                dot = document.createElement('span');
                dot.className = 'notifDot';
                menuLink.insertBefore(dot, menuLink.firstChild);
            }
            return;
        }

        if(dot){
            dot.remove();
        }
    }

    function checkUnread(){
        fetch(pollUrl, {credentials:'same-origin'})
            .then(function(r){ return r.json(); })
            .then(function(data){
                setUnreadDot(!!data.has_unread);
            })
            .catch(function(){});
    }

    checkUnread();
    setInterval(checkUnread, 10000);
})();
</script>

</body>

</html>