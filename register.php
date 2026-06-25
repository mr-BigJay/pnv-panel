<?php

session_start();

if(!file_exists("db/users.json")){
file_put_contents("db/users.json","[]");
}

$users = json_decode(
file_get_contents("db/users.json"),
true
);

if(!is_array($users)){
$users = [];
}

date_default_timezone_set("Asia/Tehran");

function generateReferralCode($length = 6){

$chars =
'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

$code = '';

for($i=0;$i<$length;$i++){

$code .=
$chars[rand(0,strlen($chars)-1)];

}

return $code;

}

if(!isset($_SESSION['register_captcha'])){

$chars =
'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

$captcha = '';

for($i=0;$i<5;$i++){

$captcha .=
$chars[rand(0,strlen($chars)-1)];

}

$_SESSION['register_captcha'] = $captcha;
}

if(isset($_GET['refreshcaptcha'])){

$chars =
'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

$captcha = '';

for($i=0;$i<5;$i++){

$captcha .=
$chars[rand(0,strlen($chars)-1)];

}

$_SESSION['register_captcha'] = $captcha;

header("Location: register.php");
exit;
}

$error = "";
$success = "";

$refFromLink =
trim($_GET['ref'] ?? "");

if($_SERVER['REQUEST_METHOD']=="POST"){

$username =
trim($_POST['username']);

$password =
trim($_POST['password']);

$mobile =
trim($_POST['mobile']);

$manualReferrer =
trim($_POST['referrer'] ?? "");

$captcha =
trim($_POST['captcha']);

$today =
date("Y-m-d");

$registerCount = 0;

foreach($users as $u){

if(
isset($u['created_at']) &&
substr($u['created_at'],0,10)
== $today
){

$registerCount++;

}

}

$finalReferrer = "";

if($refFromLink != ""){

$finalReferrer = strtoupper($refFromLink);

}else{

$finalReferrer = $manualReferrer;

}

if($registerCount >= 50){

$error =
"محدودیت ثبت نام روزانه تکمیل شده است";

}

elseif(
strtoupper($captcha)
!=
strtoupper($_SESSION['register_captcha'])
){

$error = "کد امنیتی صحیح نیست";

}

elseif(
strlen($username) < 6 ||
strlen($username) > 20
){

$error =
"نام کاربری باید بین 6 تا 20 کارکتر باشد";

}

elseif(
!preg_match(
'/^[a-zA-Z0-9._-]+$/',
$username
)
){

$error =
"نام کاربری فقط میتواند شامل حروف لاتین، عدد و . _ - باشد";

}

elseif(
strlen($password) < 8
){

$error =
"رمز عبور باید حداقل 8 کارکتر باشد";

}

elseif(
!preg_match('/[a-zA-Z]/',$password)
||
!preg_match('/[0-9]/',$password)
){

$error =
"رمز عبور باید شامل حروف انگلیسی و عدد باشد";

}

elseif(
!preg_match('/^09[0-9]{9}$/',$mobile)
){

$error =
"شماره موبایل صحیح نیست";

}

else{

foreach($users as $u){

if(
strtolower(trim($u['username']))
==
strtolower(trim($username))
||
trim($u['mobile'])
==
trim($mobile)
){

$error =
"شما قبلا ثبت نام انجام داده اید";

break;
}

}

}

if($error == ""){

$referrerFound = false;

if($finalReferrer != ""){

foreach($users as $u){

if(

(isset($u['referral_code']) &&
strtoupper($u['referral_code'])
==
strtoupper($finalReferrer))

||

(trim($u['mobile'])
==
trim($finalReferrer))

){

$referrerFound = true;

break;

}

}

if(!$referrerFound){

$error =
"معرف وارد شده معتبر نیست";

}

}

}

if($error == ""){

$referralCode = "";

do{

$referralCode =
generateReferralCode();

$exists = false;

foreach($users as $u){

if(
isset($u['referral_code'])
&&
$u['referral_code']
==
$referralCode
){

$exists = true;
break;

}

}

}while($exists);

$users[] = [

"username"=>$username,

"password"=>password_hash(
$password,
PASSWORD_DEFAULT
),

"mobile"=>$mobile,

"referral_code"=>$referralCode,

"referrer"=>$finalReferrer,

"created_at"=>date("Y-m-d H:i:s")

];

file_put_contents(
"db/users.json",
json_encode(
$users,
JSON_UNESCAPED_UNICODE|
JSON_PRETTY_PRINT
)
);

$success =
"ثبت نام با موفقیت انجام شد";

unset($_SESSION['register_captcha']);

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

ثبت نام

</title>

<style>

*{
box-sizing:border-box;
}

body{
margin:0;
padding:16px;
background:#0f172a;
font-family:tahoma;
direction:rtl;
color:white;
min-height:100vh;
display:flex;
justify-content:center;
align-items:center;
}

.box{
width:100%;
max-width:720px;
background:#1e293b;
padding:44px 28px;
border-radius:28px;
}

h2{
font-size:32px;
margin-top:0;
margin-bottom:30px;
text-align:center;
}

input{
width:100%;
padding:16px;
margin-top:10px;
margin-bottom:18px;
border:none;
border-radius:14px;
box-sizing:border-box;
font-size:18px;
background:#0f172a;
color:white;
}

.passwordWrap{
position:relative;
}

.passwordWrap input{
padding-left:55px;
}

.eye{
position:absolute;
left:18px;
top:25px;
font-size:22px;
cursor:pointer;
user-select:none;
color:#94a3b8;
}

button{
width:100%;
padding:16px;
background:#22c55e;
border:none;
border-radius:14px;
color:white;
font-size:22px;
cursor:pointer;
}

.back{
display:block;
margin-top:18px;
text-align:center;
background:#334155;
padding:16px;
border-radius:14px;
color:white;
text-decoration:none;
font-size:20px;
}

.error{
background:#dc2626;
padding:16px;
border-radius:14px;
margin-bottom:18px;
line-height:32px;
font-size:18px;
}

.success{
background:#16a34a;
padding:16px;
border-radius:14px;
margin-bottom:18px;
line-height:32px;
font-size:18px;
}

.captchaBox{
background:#0f172a;
padding:20px;
border-radius:14px;
margin-bottom:18px;
text-align:center;
font-size:34px;
font-weight:bold;
letter-spacing:6px;
color:#facc15;
user-select:none;
}

.refresh{
display:block;
margin-top:-4px;
margin-bottom:18px;
text-align:center;
color:#38bdf8;
text-decoration:none;
font-size:18px;
}

.helper{
font-size:15px;
color:#94a3b8;
margin-top:-8px;
margin-bottom:18px;
line-height:28px;
}

.refbox{
background:#0f172a;
padding:14px;
border-radius:12px;
margin-bottom:18px;
font-size:15px;
line-height:28px;
color:#cbd5e1;
}

@media(max-width:768px){

body{
padding:10px;
align-items:flex-start;
}

.box{
max-width:100%;
padding:30px 20px;
border-radius:24px;
margin-top:12px;
}

h2{
font-size:28px;
margin-bottom:24px;
}

input{
font-size:16px;
padding:14px;
}

button{
font-size:20px;
padding:14px;
}

.back{
font-size:18px;
padding:14px;
}

.error,
.success{
font-size:16px;
line-height:28px;
}

.captchaBox{
font-size:28px;
padding:18px;
}

.refresh{
font-size:16px;
}

.helper{
font-size:14px;
line-height:24px;
}

.refbox{
font-size:14px;
line-height:24px;
}

.eye{
top:22px;
font-size:20px;
}

}

</style>

</head>

<body>

<div class="box">

<h2>

ثبت نام

</h2>

<?php if($error!=""){ ?>

<div class="error">

<?php echo $error; ?>

</div>

<?php } ?>

<?php if($success!=""){ ?>

<div class="success">

<?php echo $success; ?>

</div>

<?php } ?>

<?php if($refFromLink!=""){ ?>

<div class="refbox">

ثبت نام از طریق لینک دعوت انجام شده است

</div>

<?php } ?>

<form method="POST">

<input
type="text"
name="username"
placeholder="نام کاربری"
required>

<div class="helper">

نام کاربری باید بین 6 تا 20 کارکتر باشد

</div>

<div class="passwordWrap">

<input
type="password"
name="password"
id="password"
placeholder="رمز عبور"
required>

<span
class="eye"
onclick="togglePassword()">

👁

</span>

</div>

<div class="helper">

رمز عبور باید شامل حروف انگلیسی و عدد باشد

</div>

<input
type="text"
name="mobile"
placeholder="شماره موبایل"
required>

<?php if($refFromLink==""){ ?>

<input
type="text"
name="referrer"
placeholder="کد یا شماره معرف (اختیاری)">

<?php } ?>

<div class="captchaBox">

<?php echo $_SESSION['register_captcha']; ?>

</div>

<a
href="register.php?refreshcaptcha=1"
class="refresh">

تغییر کد امنیتی

</a>

<input
type="text"
name="captcha"
placeholder="کد امنیتی"
required>

<button type="submit">

ثبت نام

</button>

</form>

<a
href="index.php"
class="back">

بازگشت

</a>

</div>

<script>

function togglePassword(){

let p =
document.getElementById('password');

if(p.type=='password'){

p.type='text';

}else{

p.type='password';

}

}

</script>

</body>

</html>