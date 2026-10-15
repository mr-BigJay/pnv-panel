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

$refreshTarget = 'register.php';
$refForRefresh = trim($_GET['ref'] ?? '');

if($refForRefresh !== ''){
$refreshTarget .= '?ref=' . rawurlencode($refForRefresh);
}

header("Location: " . $refreshTarget);
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

$success =
"ثبت نام با موفقیت انجام شد";

unset($_SESSION['register_captcha']);

}

}

$h = static function($v){
return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
};

$registerCaptchaDisplay = (string)($_SESSION['register_captcha'] ?? '');
$registerCaptchaSpaced = $registerCaptchaDisplay !== ''
? implode(' ', str_split($registerCaptchaDisplay))
: '';

$registerCaptchaRefreshUrl = 'register.php?refreshcaptcha=1';

if($refFromLink !== ''){
$registerCaptchaRefreshUrl .= '&ref=' . rawurlencode($refFromLink);
}

?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ثبت نام</title>
<link rel="stylesheet" href="/fonts.css">
<link rel="stylesheet" href="/assets/css/auth.css?v=3">
</head>
<body class="auth-page">

<div class="auth-shell">
<div class="auth-card auth-card--register">

<h1 class="auth-title">ثبت نام</h1>

<?php if($error!=""){ ?>
<div class="auth-alert auth-alert--error" role="alert"><?php echo $h($error); ?></div>
<?php } ?>

<?php if($success!=""){ ?>
<div class="auth-alert auth-alert--success" role="status"><?php echo $h($success); ?></div>
<?php } ?>

<?php if($refFromLink!=""){ ?>
<div class="auth-refbox">ثبت نام از طریق لینک دعوت انجام شده است</div>
<?php } ?>

<form method="POST" class="auth-form-stack">

<div class="auth-field">
<div class="auth-label-row">
<label class="auth-label" for="username">نام کاربری</label>
<button type="button" class="auth-hint-trigger" aria-expanded="false" aria-controls="username-hint" aria-label="راهنمای نام کاربری"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 10v6M12 7h.01"/></svg></button>
</div>
<p id="username-hint" class="auth-hint">نام کاربری بین ۶ تا ۲۰ کاراکتر؛ حروف لاتین، عدد و . _ -</p>
<input class="auth-input" type="text" name="username" id="username" required autocomplete="username" aria-describedby="username-hint">
</div>

<div class="auth-field">
<div class="auth-label-row">
<label class="auth-label" for="password">رمز عبور</label>
<button type="button" class="auth-hint-trigger" aria-expanded="false" aria-controls="password-hint" aria-label="راهنمای رمز عبور"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 10v6M12 7h.01"/></svg></button>
</div>
<p id="password-hint" class="auth-hint">حداقل ۸ کاراکتر؛ شامل حروف انگلیسی و عدد</p>
<div class="auth-password">
<input class="auth-input" type="password" name="password" id="password" required autocomplete="new-password" aria-describedby="password-hint">
<span class="auth-eye" onclick="togglePassword()" role="button" tabindex="0" aria-label="نمایش رمز عبور" onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();togglePassword();}">👁</span>
</div>
</div>

<div class="auth-field">
<label class="auth-label" for="mobile">شماره موبایل</label>
<input class="auth-input" type="text" name="mobile" id="mobile" required autocomplete="tel" inputmode="numeric">
</div>

<?php if($refFromLink==""){ ?>
<div class="auth-field">
<label class="auth-label" for="referrer">کد یا شماره معرف (اختیاری)</label>
<input class="auth-input" type="text" name="referrer" id="referrer">
</div>
<?php } ?>

<div class="auth-field auth-field--captcha">
<label class="auth-label" for="captcha">کد امنیتی</label>
<div class="auth-captcha-row">
<div class="auth-captcha" id="register-captcha-display" role="group" aria-labelledby="register-captcha-desc">
<p id="register-captcha-desc" class="auth-sr-only">کد امنیتی نمایشی: <?php echo $h($registerCaptchaSpaced); ?></p>
<span class="auth-captcha-visual" aria-hidden="true"><?php echo $h($registerCaptchaDisplay); ?></span>
</div>
<a href="<?php echo $h($registerCaptchaRefreshUrl); ?>" class="auth-captcha-refresh" aria-label="تغییر کد امنیتی" title="تغییر کد امنیتی"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12a9 9 0 1 1-2.64-6.36"/><path d="M21 3v6h-6"/></svg></a>
</div>
<input class="auth-input" type="text" name="captcha" id="captcha" required autocomplete="off" aria-describedby="register-captcha-desc">
</div>

<div class="auth-actions">
<button type="submit" class="auth-btn auth-btn--primary">ثبت نام</button>
</div>

</form>

<a href="index.php" class="auth-btn auth-btn--secondary">بازگشت</a>

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

document.querySelectorAll('.auth-hint-trigger').forEach(function(btn){
btn.addEventListener('click', function(){
var field = btn.closest('.auth-field');
if(!field) return;
var open = field.classList.toggle('is-hint-open');
btn.setAttribute('aria-expanded', open ? 'true' : 'false');
});
});
</script>

</body>
</html>
