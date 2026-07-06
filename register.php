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

<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>ثبت نام</title>
<link rel="stylesheet" href="user_panel.css?v=1">
</head>

<body class="userPanel userPanel--auth">

<div class="userPanelWrap">

<div class="userPanelBox">

<h2 class="userPanelTitle">ثبت نام</h2>

<?php if($error!=""){ ?>
<div class="userPanelAlert userPanelAlert--error"><?php echo $error; ?></div>
<?php } ?>

<?php if($success!=""){ ?>
<div class="userPanelAlert userPanelAlert--success"><?php echo $success; ?></div>
<?php } ?>

<?php if($refFromLink!=""){ ?>
<div class="userPanelRefbox">ثبت نام از طریق لینک دعوت انجام شده است</div>
<?php } ?>

<form method="POST">

<div class="userPanelField">
<input class="userPanelInput" type="text" name="username" placeholder="نام کاربری" required>
<div class="userPanelHelper">نام کاربری باید بین 6 تا 20 کارکتر باشد</div>
</div>

<div class="userPanelField">
<div class="userPanelPassword">
<input class="userPanelInput" type="password" name="password" id="password" placeholder="رمز عبور" required>
<span class="userPanelEye" onclick="togglePassword()">👁</span>
</div>
<div class="userPanelHelper">رمز عبور باید شامل حروف انگلیسی و عدد باشد</div>
</div>

<div class="userPanelField">
<input class="userPanelInput" type="text" name="mobile" placeholder="شماره موبایل" required>
</div>

<?php if($refFromLink==""){ ?>
<div class="userPanelField">
<input class="userPanelInput" type="text" name="referrer" placeholder="کد یا شماره معرف (اختیاری)">
</div>
<?php } ?>

<div class="userPanelCaptcha"><?php echo $_SESSION['register_captcha']; ?></div>

<a href="register.php?refreshcaptcha=1" class="userPanelRefresh">تغییر کد امنیتی</a>

<div class="userPanelField">
<input class="userPanelInput" type="text" name="captcha" placeholder="کد امنیتی" required>
</div>

<button class="userPanelBtn" type="submit">ثبت نام</button>

</form>

<a href="index.php" class="userPanelLink">بازگشت</a>

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