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
background:linear-gradient(180deg,#08113a 0%,#0f172a 100%);
font-family:tahoma;
direction:rtl;
color:#fff;
min-height:100vh;
padding:12px;
display:flex;
justify-content:center;
align-items:center;
}

.container{
width:100%;
max-width:380px;
}

.box{
background:#1e293b;
border-radius:22px;
padding:24px 20px;
box-shadow:0 10px 30px rgba(0,0,0,.35);
}

.logo{
text-align:center;
font-size:34px;
margin-bottom:12px;
}

h2{
text-align:center;
font-size:22px;
margin-bottom:18px;
font-weight:700;
}

.error{
background:#7f1d1d;
border:1px solid #ef4444;
padding:12px;
border-radius:14px;
margin-bottom:16px;
line-height:24px;
text-align:center;
font-size:14px;
}

.inputGroup{
margin-bottom:14px;
}

.label{
display:block;
margin-bottom:6px;
font-size:14px;
font-weight:700;
color:#cbd5e1;
}

input{
width:100%;
height:46px;
border:none;
border-radius:14px;
padding:0 16px;
font-size:15px;
background:#0f172a;
color:#fff;
outline:none;
transition:.2s;
}

input:focus{
box-shadow:0 0 0 2px #2563eb;
}

.passwordWrap{
position:relative;
}

.passwordWrap input{
padding-left:46px;
}

.eye{
position:absolute;
left:16px;
top:11px;
font-size:20px;
cursor:pointer;
user-select:none;
color:#94a3b8;
}

.captchaBox{
height:54px;
background:#0f172a;
border-radius:14px;
display:flex;
justify-content:center;
align-items:center;
font-size:24px;
font-weight:bold;
letter-spacing:6px;
color:#facc15;
margin-bottom:12px;
user-select:none;
}

.refresh{
display:block;
text-align:center;
margin-bottom:16px;
text-decoration:none;
color:#38bdf8;
font-size:14px;
font-weight:700;
}

button{
width:100%;
height:46px;
border:none;
border-radius:14px;
background:#22c55e;
color:#fff;
font-size:17px;
font-weight:700;
cursor:pointer;
transition:.2s;
}

button:hover{
background:#16a34a;
}

.links{
margin-top:16px;
}

.links a{
display:flex;
justify-content:center;
align-items:center;
height:46px;
background:#334155;
border-radius:14px;
text-decoration:none;
color:#fff;
font-size:16px;
margin-top:10px;
transition:.2s;
}

.links a:hover{
background:#475569;
}

.footer{
text-align:center;
margin-top:18px;
font-size:12px;
color:#94a3b8;
line-height:20px;
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

<div class="captchaBox">

<?php echo $_SESSION['login_captcha']; ?>

</div>

<a
href="index.php?refreshcaptcha=1"
class="refresh">

تغییر کد امنیتی

</a>

<div class="inputGroup">

<input
type="text"
name="captcha"
placeholder="کد امنیتی"
required>

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

</body>

</html>
