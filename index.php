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
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ورود به پنل کاربری</title>
<link rel="stylesheet" href="/fonts.css">
<link rel="stylesheet" href="/assets/css/auth.css?v=1">
</head>
<body class="auth-page">

<div class="auth-shell">
<div class="auth-card auth-card--login">

<div class="auth-icon" aria-hidden="true">🔐</div>

<h1 class="auth-title">ورود به پنل کاربری</h1>

<?php if($error!=""){ ?>
<div class="auth-alert auth-alert--error" role="alert"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
<?php } ?>

<form method="POST" class="auth-form-stack">

<div class="auth-field">
<label class="auth-label" for="username">نام کاربری</label>
<input class="auth-input" type="text" name="username" id="username" required autocomplete="username">
</div>

<div class="auth-field">
<label class="auth-label" for="password">رمز عبور</label>
<div class="auth-password">
<input class="auth-input" type="password" name="password" id="password" required autocomplete="current-password">
<span class="auth-eye" onclick="togglePassword()" role="button" tabindex="0" aria-label="نمایش رمز عبور" onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();togglePassword();}">👁</span>
</div>
</div>

<div class="auth-captcha" aria-hidden="true"><?php echo htmlspecialchars($_SESSION['login_captcha'], ENT_QUOTES, 'UTF-8'); ?></div>

<a href="index.php?refreshcaptcha=1" class="auth-captcha-refresh">تغییر کد امنیتی</a>

<div class="auth-field">
<label class="auth-label" for="captcha">کد امنیتی</label>
<input class="auth-input" type="text" name="captcha" id="captcha" placeholder="کد امنیتی" required autocomplete="off">
</div>

<div class="auth-actions">
<button type="submit" class="auth-btn auth-btn--primary">ورود</button>
</div>

</form>

<div class="auth-links">
<a href="register.php" class="auth-btn auth-btn--secondary">ساخت حساب کاربری</a>
</div>

<div class="auth-footer">Ticketin User Panel</div>

</div>
</div>

<script>
function togglePassword(){
let p = document.getElementById('password');
if(p.type=='password'){
p.type='text';
}else{
p.type='password';
}
}
</script>

</body>
</html>
