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

<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>ورود به پنل کاربری</title>
<link rel="stylesheet" href="user_panel.css?v=1">
</head>

<body class="userPanel userPanel--auth">

<div class="userPanelWrap">

<div class="userPanelBox">

<div class="userPanelLogo">🔐</div>

<h2 class="userPanelTitle">ورود به پنل کاربری</h2>

<?php if($error!=""){ ?>

<div class="userPanelAlert userPanelAlert--error"><?php echo $error; ?></div>

<?php } ?>

<form method="POST">

<div class="userPanelField">
<label class="userPanelLabel">نام کاربری</label>
<input class="userPanelInput" type="text" name="username" required>
</div>

<div class="userPanelField">
<label class="userPanelLabel">رمز عبور</label>
<div class="userPanelPassword">
<input class="userPanelInput" type="password" name="password" id="password" required>
<span class="userPanelEye" onclick="togglePassword()">👁</span>
</div>
</div>

<div class="userPanelCaptcha"><?php echo $_SESSION['login_captcha']; ?></div>

<a href="index.php?refreshcaptcha=1" class="userPanelRefresh">تغییر کد امنیتی</a>

<div class="userPanelField">
<input class="userPanelInput" type="text" name="captcha" placeholder="کد امنیتی" required>
</div>

<button class="userPanelBtn" type="submit">ورود</button>

</form>

<div class="userPanelLinks">
<a href="register.php" class="userPanelLink">ساخت حساب کاربری</a>
</div>

<div class="userPanelFooter">Ticketin User Panel</div>

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
