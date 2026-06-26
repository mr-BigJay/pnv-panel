<?php

ini_set('display_errors',1);
error_reporting(E_ALL);

session_start();

if(!isset($_SESSION['user'])){
header("Location: index.php");
exit;
}

$user = $_SESSION['user'];

require_once __DIR__ . '/chatwoot_lib.php';

$supportFile =
"db/support.json";

$hasUnreadSupport = false;

if(!chatwootEnabled() && file_exists($supportFile)){

$supportData =
json_decode(
file_get_contents($supportFile),
true
);

if(is_array($supportData)){

foreach($supportData as $ticket){

if(
isset($ticket['user'])
&&
$ticket['user'] == $user
){

if(isset($ticket['messages'])){

foreach($ticket['messages'] as $msg){

if(

isset($msg['sender'])
&&

$msg['sender'] == 'admin'

&&

empty($msg['seen_by_user'])

){

$hasUnreadSupport = true;
break 2;

}

}

}

}

}

}

}

?>

<!DOCTYPE html>

<html lang="fa">

<head>

<meta charset="UTF-8">

<meta name="viewport"
content="width=device-width, initial-scale=1.0">

<title>

داشبورد کاربر

</title>

<style>

*{
box-sizing:border-box;
}

html,
body{
width:100%;
overflow-x:hidden;
}

body{
margin:0;
padding:20px;
background:#0f172a;
font-family:tahoma;
direction:rtl;
color:white;
min-height:100vh;
display:flex;
justify-content:center;
align-items:center;
}

.container{
width:100%;
max-width:700px;
margin:auto;
overflow:hidden;
}

.box{
background:#1e293b;
padding:40px;
border-radius:24px;
width:100%;
}

h2{
margin-top:0;
margin-bottom:30px;
text-align:center;
font-size:34px;
}

.menu{
display:block;
background:#334155;
padding:20px;
border-radius:16px;
margin-bottom:18px;
text-decoration:none;
color:white;
text-align:center;
font-size:22px;
transition:0.3s;
position:relative;
overflow:hidden;
}

.menu:hover{
background:#475569;
}

.logout{
background:#dc2626;
}

.logout:hover{
background:#b91c1c;
}

.userbox{
background:#0f172a;
padding:24px;
border-radius:16px;
margin-bottom:30px;
text-align:center;
line-height:42px;
font-size:24px;
word-break:break-word;
}

.supportMenu{
position:relative;
}

.notifDot{
position:absolute;
top:12px;
left:12px;
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

@media(max-width:768px){

body{
padding:12px;
align-items:flex-start;
}

.container{
max-width:100%;
}

.box{
padding:26px;
border-radius:20px;
margin-top:18px;
}

h2{
font-size:28px;
margin-bottom:24px;
}

.menu{
font-size:18px;
padding:18px;
border-radius:14px;
margin-bottom:14px;
}

.userbox{
font-size:20px;
line-height:36px;
padding:20px;
}

}

</style>

</head>

<body>

<div class="container">

<div class="box">

<h2>

پنل کاربری

</h2>

<div class="userbox">

خوش آمدید

<br>

<?php echo htmlspecialchars($user); ?>

</div>

<a href="buy.php"
class="menu">

خرید اشتراک جدید

</a>

<a href="renew.php"
class="menu">

تمدید اشتراک

</a>

<a href="subscriptions.php"
class="menu">

لیست اشتراک ها

</a>

<a href="renew-list.php"
class="menu">

لیست تمدید ها

</a>

<a href="downloads.php"
class="menu">

دانلود نرم افزارها

</a>

<a href="coupon.php"
class="menu">

کوپن تخفیف

</a>

<a href="support.php"
class="menu supportMenu">

<?php if($hasUnreadSupport && !chatwootEnabled()){ ?>

<span class="notifDot"></span>

<?php } ?>

<?php echo chatwootEnabled() ? 'پشتیبانی آنلاین' : 'پيام به پشتیبانی'; ?>

</a>

<a href="logout.php"
class="menu logout">

خروج

</a>

</div>

</div>

<?php if(chatwootEnabled()){ chatwootRenderWidget($user); } ?>

</body>

</html>