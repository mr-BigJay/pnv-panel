<?php

session_start();

if(!isset($_SESSION['user'])){
header("Location: index.php");
exit;
}

$username = $_SESSION['user'];

$users = [];

if(file_exists("db/users.json")){

$users =
json_decode(
file_get_contents("db/users.json"),
true
);

}

if(!is_array($users)){
$users = [];
}

$currentUser = null;

foreach($users as $u){

if(
isset($u['username']) &&
$u['username'] == $username
){

$currentUser = $u;
break;

}

}

if(!$currentUser){

die("کاربر یافت نشد");

}

if(!isset($currentUser['referral_code'])){

$chars =
'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

$code = '';

for($i=0;$i<6;$i++){

$code .=
$chars[rand(0,strlen($chars)-1)];

}

$currentUser['referral_code'] = $code;

foreach($users as $k => $u){

if($u['username'] == $username){

$users[$k] = $currentUser;

}

}

file_put_contents(
"db/users.json",
json_encode(
$users,
JSON_UNESCAPED_UNICODE|
JSON_PRETTY_PRINT
)
);

}

$myCode =
$currentUser['referral_code'];

$myLink =
"https://panel.ticketin.ir/register.php?ref=" .
$myCode;

$inviteCount = 0;

foreach($users as $u){

if(
isset($u['referrer']) &&
trim($u['referrer']) == $myCode
){

$inviteCount++;

}

}

$reward = "";

if($inviteCount >= 20){

$reward =
"3 عدد کد تخفیف 100 درصدی";

}

elseif($inviteCount >= 10){

$reward =
"کد تخفیف 100 درصدی";

}

elseif($inviteCount >= 5){

$reward =
"تخفیف 40 درصدی";

}

elseif($inviteCount >= 3){

$reward =
"تخفیف 20 درصدی";

}

else{

$reward =
"هنوز پاداشی فعال نشده";

}

?>

<!DOCTYPE html>

<html lang="fa">

<head>

<meta charset="UTF-8">

<meta name="viewport"
content="width=device-width, initial-scale=1.0">

<title>

کوپن تخفیف

</title>

<style>

*{
box-sizing:border-box;
}

body{
margin:0;
padding:10px;
background:#0f172a;
font-family:tahoma;
direction:rtl;
color:white;
}

.container{
width:100%;
max-width:760px;
margin:auto;
}

.box{
background:#1e293b;
border-radius:18px;
padding:18px;
margin-bottom:14px;
}

h2{
text-align:center;
font-size:24px;
margin-bottom:20px;
}

.title{
font-size:13px;
color:#94a3b8;
margin-bottom:8px;
}

.value{
background:#0f172a;
padding:14px;
border-radius:10px;
font-size:14px;
line-height:28px;
word-break:break-all;
}

.copybtn{
width:100%;
margin-top:10px;
padding:12px;
background:#22c55e;
border:none;
border-radius:10px;
color:white;
font-size:14px;
cursor:pointer;
}

.stats{
display:grid;
grid-template-columns:1fr 1fr;
gap:10px;
margin-top:10px;
}

.stat{
background:#0f172a;
padding:14px;
border-radius:10px;
text-align:center;
}

.statNumber{
font-size:24px;
font-weight:bold;
margin-bottom:6px;
}

.statLabel{
font-size:12px;
color:#94a3b8;
}

.reward{
background:#0f172a;
padding:16px;
border-radius:12px;
margin-top:14px;
line-height:28px;
font-size:14px;
}

.levels{
margin-top:14px;
background:#0f172a;
padding:16px;
border-radius:12px;
line-height:32px;
font-size:14px;
}

.back{
display:block;
margin-top:16px;
text-align:center;
background:#334155;
padding:12px;
border-radius:10px;
color:white;
text-decoration:none;
font-size:15px;
}

@media(min-width:768px){

body{
padding:18px;
}

.container{
max-width:920px;
}

.box{
padding:22px;
}

h2{
font-size:28px;
}

.value{
font-size:15px;
}

.copybtn{
width:auto;
padding:12px 20px;
font-size:14px;
}

.reward,
.levels{
font-size:15px;
}

}

</style>

</head>

<body>

<div class="container">

<div class="box">

<h2>

سیستم دعوت دوستان

</h2>

<div class="title">

کد دعوت شما

</div>

<div class="value"
id="refcode">

<?php echo $myCode; ?>

</div>

<button
class="copybtn"
onclick="copyText('refcode')">

کپی کد دعوت

</button>

</div>

<div class="box">

<div class="title">

لینک دعوت شما

</div>

<div class="value"
id="reflink">

<?php echo $myLink; ?>

</div>

<button
class="copybtn"
onclick="copyText('reflink')">

کپی لینک دعوت

</button>

<div class="stats">

<div class="stat">

<div class="statNumber">

<?php echo $inviteCount; ?>

</div>

<div class="statLabel">

تعداد دعوت موفق

</div>

</div>

<div class="stat">

<div class="statNumber">

<?php echo $myCode; ?>

</div>

<div class="statLabel">

کد معرف

</div>

</div>

</div>

<div class="reward">

<b>

پاداش فعال:

</b>

<br><br>

<?php echo $reward; ?>

</div>

<div class="levels">

<b>

سطوح پاداش:

</b>

<br><br>

3 دعوت → تخفیف 20 درصدی
<br>

5 دعوت → تخفیف 40 درصدی
<br>

10 دعوت → کد تخفیف 100 درصدی
<br>

20 دعوت → 3 کد تخفیف 100 درصدی

</div>

</div>

<a href="dashboard.php"
class="back">

بازگشت

</a>

</div>

<script>

function copyText(id){

const text =
document.getElementById(id).innerText;

navigator.clipboard.writeText(text);

alert("کپی شد");

}

</script>

</body>

</html>
