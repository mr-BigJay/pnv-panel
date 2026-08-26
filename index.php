<?php

session_start();

ini_set('display_errors',1);
error_reporting(E_ALL);

if(!file_exists("db/users.json")){

file_put_contents(
"db/users.json",
"[]"
);

}

if(!isset($_SESSION['login_captcha'])){

$chars =
'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

$captcha = '';

for($i=0;$i<5;$i++){

$captcha .=
$chars[rand(0,strlen($chars)-1)];

}

$_SESSION['login_captcha'] =
$captcha;

}

if(isset($_GET['refreshcaptcha'])){

$chars =
'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

$captcha = '';

for($i=0;$i<5;$i++){

$captcha .=
$chars[rand(0,strlen($chars)-1)];

}

$_SESSION['login_captcha'] =
$captcha;

header("Location: index.php");
exit;
}

$error = "";

$users = json_decode(
file_get_contents("db/users.json"),
true
);

if(!is_array($users)){
$users = [];
}

if($_SERVER['REQUEST_METHOD']=="POST"){

$username =
trim($_POST['username']);

$password =
trim($_POST['password']);

$captcha =
trim($_POST['captcha']);

if(

strtoupper($captcha)
!=
strtoupper($_SESSION['login_captcha'])

){

$error =
"کد امنیتی صحیح نیست";

}else{

$login = false;

foreach($users as $u){

if(

strtolower(trim($username))
==
strtolower(trim($u['username']))

){

$hash =
trim($u['password']);

if(
password_verify(
$password,
$hash
)
){

$_SESSION['user'] =
$u['username'];

if(file_exists(__DIR__ . '/campaign_lib.php')){
    require_once __DIR__ . '/campaign_lib.php';

    if(function_exists('campaignAnnouncementLoginSessionId')){
        campaignAnnouncementLoginSessionId(true);
    }
}

$login = true;

header("Location: dashboard.php");
exit;

}

}

}

if(!$login){

$error =
"نام کاربری یا رمز عبور اشتباه است";

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

ورود به پنل کاربری

</title>

<link rel="stylesheet" href="/fonts.css">

<style>

*{
margin:0;
padding:0;
box-sizing:border-box;
}

body{
background:
linear-gradient(
180deg,
#08113a 0%,
#0f172a 100%
);
font-family:tahoma;
direction:rtl;
color:white;
min-height:100vh;
padding:18px;
display:flex;
justify-content:center;
align-items:center;
}

.container{
width:100%;
max-width:720px;
}

.box{
background:#1e293b;
border-radius:36px;
padding:46px 34px;
box-shadow:
0 14px 40px rgba(0,0,0,0.40);
}

.logo{
text-align:center;
font-size:52px;
margin-bottom:22px;
}

h2{
text-align:center;
font-size:32px;
margin-bottom:32px;
font-weight:bold;
}

.error{
background:#7f1d1d;
border:1px solid #ef4444;
padding:16px;
border-radius:18px;
margin-bottom:24px;
line-height:32px;
text-align:center;
font-size:18px;
}

.inputGroup{
margin-bottom:22px;
}

.label{
display:block;
margin-bottom:10px;
font-size:18px;
font-weight:bold;
color:#cbd5e1;
}

input{
width:100%;
height:62px;
border:none;
border-radius:20px;
padding:0 20px;
font-size:18px;
background:#0f172a;
color:white;
outline:none;
}

.passwordWrap{
position:relative;
}

.passwordWrap input{
padding-left:64px;
}

.eye{
position:absolute;
left:20px;
top:18px;
font-size:24px;
cursor:pointer;
user-select:none;
color:#94a3b8;
}

.captchaSection{
margin:4px 0 14px;
padding:10px;
background:rgba(15,23,42,.45);
border:1px solid rgba(148,163,184,.14);
border-radius:14px;
}

.captchaRow{
display:flex;
align-items:center;
gap:8px;
direction:rtl;
}

.captchaInputWrap{
flex:1.7;
min-width:0;
}

.captchaInputWrap input{
width:100%;
height:40px;
font-size:14px;
padding:0 12px;
border-radius:10px;
}

.captchaMeta{
flex:1.3;
display:flex;
align-items:center;
gap:6px;
min-width:0;
}

.captchaCode{
flex:1;
min-width:0;
height:40px;
padding:0 6px;
display:flex;
align-items:center;
justify-content:center;
background:#0f172a;
border-radius:10px;
font-size:14px;
font-weight:700;
letter-spacing:1px;
color:#facc15;
user-select:none;
white-space:nowrap;
}

.captchaRefresh{
width:34px;
height:34px;
display:flex;
align-items:center;
justify-content:center;
border-radius:10px;
background:#334155;
color:#94a3b8;
text-decoration:none;
flex-shrink:0;
transition:.2s;
}

.captchaRefresh:hover{
background:#475569;
color:#38bdf8;
}

.captchaRefresh svg{
width:16px;
height:16px;
stroke:currentColor;
fill:none;
stroke-width:2;
stroke-linecap:round;
stroke-linejoin:round;
}

button{
width:100%;
height:62px;
border:none;
border-radius:20px;
background:#22c55e;
color:white;
font-size:22px;
font-weight:bold;
cursor:pointer;
}

.links{
margin-top:26px;
}

.links a{
display:flex;
justify-content:center;
align-items:center;
height:60px;
background:#334155;
border-radius:20px;
text-decoration:none;
color:white;
font-size:20px;
margin-top:14px;
}

.footer{
text-align:center;
margin-top:26px;
font-size:14px;
color:#94a3b8;
line-height:28px;
}

</style>

</head>

<body>

<div class="container">

<div class="box">

<div class="logo">

🔐

</div>

<h2>

ورود به پنل کاربری

</h2>

<?php if($error!=""){ ?>

<div class="error">

<?php echo $error; ?>

</div>

<?php } ?>

<form method="POST">

<div class="inputGroup">

<label class="label">

نام کاربری

</label>

<input
type="text"
name="username"
required>

</div>

<div class="inputGroup">

<label class="label">

رمز عبور

</label>

<div class="passwordWrap">

<input
type="password"
name="password"
id="password"
required>

<span
class="eye"
onclick="togglePassword()">

👁

</span>

</div>

</div>

<div class="captchaSection">

<div class="captchaRow">

<div class="captchaInputWrap">

<input
type="text"
name="captcha"
placeholder="کد را وارد کنید"
autocomplete="off"
required>

</div>

<div class="captchaMeta">

<div class="captchaCode">

<?php echo $_SESSION['login_captcha']; ?>

</div>

<a
href="index.php?refreshcaptcha=1"
class="captchaRefresh"
aria-label="تغییر کد امنیتی"
title="تغییر کد امنیتی">

<svg viewBox="0 0 24 24" aria-hidden="true">
<path d="M21 12a9 9 0 11-3-6.7"/>
<path d="M21 3v6h-6"/>
</svg>

</a>

</div>

</div>

</div>

<button type="submit">

ورود

</button>

</form>

<div class="links">

<a href="register.php">

ساخت حساب کاربری

</a>

</div>

<div class="footer">

Ticketin User Panel

</div>

</div>

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

<?php require_once __DIR__ . '/form_validation_fa.php'; pnvFormValidationFaScript(); ?>

</body>

</html>
