<?php

require_once __DIR__ . '/pnv_date_bootstrap.php';

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

$registerCount = 0;

foreach($users as $u){

if(isset($u['created_at']) && pnvIsTodayTehran($u['created_at'])){

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

"mobile_verified"=>false,

"referral_code"=>$referralCode,

"referrer"=>$finalReferrer,

"created_at"=>pnvNowParts()['datetime']

];

file_put_contents(
"db/users.json",
json_encode(
$users,
JSON_UNESCAPED_UNICODE|
JSON_PRETTY_PRINT
)
);

if(file_exists(__DIR__ . '/sms_lib.php')){
    require_once __DIR__ . '/sms_lib.php';
    if(function_exists('smsSendRegisterWelcome')){
        smsSendRegisterWelcome($mobile, $username);
    }
}

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
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ثبت نام</title>
<link rel="stylesheet" href="/fonts.css">
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{
background:linear-gradient(180deg,#08113a 0%,#0f172a 100%);
font-family:tahoma;
direction:rtl;
color:#fff;
min-height:100vh;
padding:12px 12px 24px;
display:flex;
justify-content:center;
align-items:flex-start;
padding-top:24px;
}
.container{width:100%;max-width:380px}
.box{
background:#1e293b;
border-radius:22px;
padding:24px 20px;
box-shadow:0 10px 30px rgba(0,0,0,.35);
}
.logo{text-align:center;font-size:34px;margin-bottom:12px}
h2{text-align:center;font-size:22px;margin-bottom:18px;font-weight:700}
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
.success{
background:#14532d;
border:1px solid #22c55e;
padding:12px;
border-radius:14px;
margin-bottom:16px;
line-height:24px;
text-align:center;
font-size:14px;
color:#bbf7d0;
}
.inputGroup{margin-bottom:14px}
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
input::placeholder{color:#64748b}
input:focus{box-shadow:0 0 0 2px #2563eb}
input.inputPrimary{
background:#fff;
color:#0f172a;
}
input.inputPrimary::placeholder{color:#64748b}
input.inputPrimary:focus{box-shadow:0 0 0 2px #2563eb}
.passwordWrap{position:relative}
.passwordWrap input{padding-left:46px}
.eye{
position:absolute;
left:16px;
top:11px;
font-size:20px;
cursor:pointer;
user-select:none;
color:#64748b;
}
.captchaBox{
height:54px;
background:#0f172a;
border-radius:14px;
display:flex;
justify-content:center;
align-items:center;
font-size:24px;
font-weight:700;
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
button:hover{background:#16a34a}
.links{margin-top:16px}
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
.links a:hover{background:#475569}
.helper{
font-size:12px;
color:#94a3b8;
margin:-6px 0 12px;
line-height:20px;
}
.refbox{
background:#0f172a;
padding:12px;
border-radius:14px;
margin-bottom:14px;
font-size:13px;
line-height:22px;
color:#cbd5e1;
text-align:center;
}
</style>
</head>
<body>

<div class="container">
<div class="box">

<div class="logo">📝</div>
<h2>ثبت نام</h2>

<?php if($error !== ""){ ?>
<div class="error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
<?php } ?>

<?php if($success !== ""){ ?>
<div class="success"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div>
<?php } ?>

<?php if($refFromLink !== ""){ ?>
<div class="refbox">ثبت نام از طریق لینک دعوت انجام شده است</div>
<?php } ?>

<form method="POST">

<input
type="text"
class="inputPrimary"
name="username"
placeholder="نام کاربری"
minlength="6"
maxlength="20"
pattern="[a-zA-Z0-9._-]+"
title="نام کاربری باید بین ۶ تا ۲۰ کاراکتر و فقط شامل حروف لاتین، عدد و . _ - باشد"
required>

<div class="helper">نام کاربری باید بین 6 تا 20 کارکتر باشد</div>

<div class="passwordWrap">
<input
type="password"
class="inputPrimary"
name="password"
id="password"
placeholder="رمز عبور"
minlength="8"
pattern="(?=.*[A-Za-z])(?=.*\d).+"
title="رمز عبور باید حداقل ۸ کاراکتر و شامل حروف انگلیسی و عدد باشد"
required>
<span class="eye" onclick="togglePassword()" aria-hidden="true">👁</span>
</div>

<div class="helper">رمز عبور باید شامل حروف انگلیسی و عدد باشد</div>

<input
type="tel"
name="mobile"
placeholder="شماره موبایل"
inputmode="tel"
autocomplete="tel"
pattern="09[0-9]{9}"
title="شماره موبایل باید با 09 شروع شود و 11 رقم باشد"
required>

<?php if($refFromLink === ""){ ?>
<input
type="text"
name="referrer"
placeholder="کد یا شماره معرف (اختیاری)">
<?php } ?>

<div class="captchaBox"><?php echo htmlspecialchars((string)$_SESSION['register_captcha'], ENT_QUOTES, 'UTF-8'); ?></div>

<a href="register.php?refreshcaptcha=1<?php echo $refFromLink !== '' ? '&ref=' . rawurlencode($refFromLink) : ''; ?>" class="refresh">تغییر کد امنیتی</a>

<input
type="text"
name="captcha"
placeholder="کد امنیتی"
autocomplete="off"
required>

<button type="submit">ثبت نام</button>

</form>

<div class="links">
<a href="index.php">بازگشت</a>
</div>

</div>
</div>

<script>
function togglePassword(){
var p = document.getElementById('password');
if(!p){ return; }
p.type = (p.type === 'password') ? 'text' : 'password';
}
</script>

</body>
</html>