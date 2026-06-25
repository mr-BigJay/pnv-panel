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

.captchaBox{
height:78px;
background:#0f172a;
border-radius:22px;
display:flex;
justify-content:center;
align-items:center;
font-size:32px;
font-weight:bold;
letter-spacing:10px;
color:#facc15;
margin-bottom:18px;
user-select:none;
}

.refresh{
display:block;
text-align:center;
margin-bottom:24px;
text-decoration:none;
color:#38bdf8;
font-size:18px;
font-weight:bold;
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

</body>

</html>
