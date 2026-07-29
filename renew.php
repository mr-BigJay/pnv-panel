<?php

session_start();

if(!isset($_SESSION['user'])){
    header("Location: index.php");
    exit;
}

require_once __DIR__ . '/coupon_lib.php';
require_once __DIR__ . '/subscription_lib.php';
require_once __DIR__ . '/telegram_lib.php';

$plans = [];

if(file_exists('db/plans.json')){
    $plans = json_decode(file_get_contents('db/plans.json'), true);
}

if(!is_array($plans)){
    $plans = [];
}

$cards = [];

if(file_exists('db/cards.json')){
    $cards = json_decode(file_get_contents('db/cards.json'), true);
}

if(!is_array($cards)){
    $cards = [];
}

function renewIsValidSubLink($value){

    $value = renewNormalizeSubLink($value);

    if($value === ''){
        return false;
    }

    $validDomains = [
        'vip.boozhaan.ir',
        'vip2.boozhaan.ir',
        'vip3.boozhaan.ir',
        'vip4.boozhaan.ir'
    ];

    foreach($validDomains as $domain){
        if(stripos($value, $domain) !== false){
            return true;
        }
    }

    return false;
}

function renewNormalizeSubLink($value){
    $value = trim((string)$value);

    if($value === ''){
        return '';
    }

    // لینک کامل داخل متن کثیف
    if(preg_match('~https?://(?:vip\d*)\.boozhaan\.ir(?::\d+)?/sub/[A-Za-z0-9]+~i', $value, $m)){
        return $m[0];
    }

    // فقط SubID خام
    if(preg_match('/^[A-Za-z0-9]{8,32}$/', $value)){
        return $value;
    }

    // SubID در ابتدای متن خراب
    if(preg_match('/^\s*([A-Za-z0-9]{8,32})\b/u', $value, $m)){
        return $m[1];
    }

    return trim(preg_split('/\s+/u', $value)[0] ?? '');
}

function renewLoadUserSubscriptions($username){

    $linkIndex = [];
    $file = 'invoices/payments.csv';

    if(!file_exists($file)){
        return [];
    }

    $handle = fopen($file, 'r');

    while(($data = fgetcsv($handle)) !== false){

        if(($data[0] ?? '') !== $username){
            continue;
        }

        if(($data[6] ?? '') !== 'تایید شد'){
            continue;
        }

        $col1 = trim($data[1] ?? '');
        $link = trim($data[7] ?? '');
        $type = trim($data[9] ?? '');

        if($type === 'خرید' && renewIsValidSubLink($link) && !pnvIsSubLinkCleared($username, $link)){
            $link = renewNormalizeSubLink($link);
            $key = strtolower($link);
            $linkIndex[$key] = [
                'name' => $col1,
                'link' => $link
            ];
        }

        if($type === 'تمدید' && renewIsValidSubLink($col1) && !pnvIsSubLinkCleared($username, $col1)){
            $col1 = renewNormalizeSubLink($col1);
            $key = strtolower($col1);

            if(!isset($linkIndex[$key])){
                $name = $col1;

                if(preg_match('/\/sub\/([^\/\?]+)/i', $col1, $matches)){
                    $name = $matches[1];
                }

                $linkIndex[$key] = [
                    'name' => $name,
                    'link' => $col1
                ];
            }
        }
    }

    fclose($handle);

    return array_values($linkIndex);
}

$userSubscriptions = renewLoadUserSubscriptions($_SESSION['user']);

$message = "";
$error = "";

if($_SERVER['REQUEST_METHOD'] == "POST"){

    $sub = renewNormalizeSubLink($_POST['sub'] ?? '');
    $plan = trim($_POST['plan']);
    $tracking = trim($_POST['tracking']);
    $time = trim($_POST['time']);
    $date = trim($_POST['date']);
    $hasCoupon = isset($_POST['has_coupon']);
    $couponCode = trim($_POST['coupon_code'] ?? '');
    $discountPercent = 0;

    if(!renewIsValidSubLink($sub)){
        $error = "لینک اشتراک صحیح نیست";
    }

    elseif(!preg_match('/^(0[0-9]|1[0-9]|2[0-3]):([0-5][0-9])$/',$time)){
        $error = "ساعت وارد شده صحیح نیست";
    }

    elseif(!preg_match('/^140[5-7]\/(0[1-9]|1[0-2])\/(0[1-9]|[12][0-9]|3[01])$/',$date)){
        $error = "تاریخ وارد شده صحیح نیست";
    }

    else{

        if($hasCoupon){

            if($couponCode === ''){
                $error = 'کد تخفیف را وارد کنید';
            }
            else{
                $couponResult = couponCalculateForPlan(
                    $_SESSION['user'],
                    $couponCode,
                    $plan,
                    $plans
                );

                if(empty($couponResult['ok'])){
                    $error = $couponResult['error'] ?? 'کد تخفیف معتبر نیست';
                }
                else{
                    $plan = $couponResult['plan_label'];
                    $discountPercent = intval($couponResult['percent']);
                }
            }

        }

    }

    if($error == ""){

        $status = "درحال بررسی";
        $link = "";
        $created = time();

        $row = [
            $_SESSION['user'],
            $sub,
            $plan,
            $tracking,
            $date,
            $time,
            $status,
            $link,
            $created,
            "تمدید",
            $hasCoupon ? strtoupper($couponCode) : '',
            $discountPercent
        ];

        $file = fopen("invoices/payments.csv","a");
        fputcsv($file,$row);
        fclose($file);

        try{
            telegramNotifyNewPayment('تمدید', $row);
        }
        catch(Throwable $e){
            error_log('Telegram renew notification failed: ' . $e->getMessage());
        }

        if($hasCoupon && $couponCode !== ''){
            couponMarkUsed($couponCode, $_SESSION['user']);
        }

        $message = "درخواست تمدید ثبت شد و حداکثر تا یک ساعت آینده بررسی خواهد شد";
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

تمدید اشتراک

</title>

<style>

*{
box-sizing:border-box;
}

body{
background:#0f172a;
font-family:tahoma;
direction:rtl;
color:white;
padding:16px;
margin:0;
display:flex;
justify-content:center;
}

.box{
width:100%;
max-width:760px;
margin:auto;
background:#1e293b;
padding:46px 30px;
border-radius:28px;
}

h2{
font-size:32px;
margin-bottom:28px;
text-align:center;
}

input,select{
width:100%;
padding:16px;
margin-top:10px;
margin-bottom:20px;
border:none;
border-radius:14px;
box-sizing:border-box;
font-size:18px;
background:#0f172a;
color:white;
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
margin-top:20px;
text-align:center;
background:#334155;
padding:16px;
border-radius:14px;
color:white;
text-decoration:none;
font-size:20px;
}

.msg{
background:#16a34a;
padding:16px;
border-radius:14px;
margin-bottom:20px;
font-size:18px;
line-height:34px;
}

.err{
background:#dc2626;
padding:16px;
border-radius:14px;
margin-bottom:20px;
font-size:18px;
line-height:34px;
}

.cardbox{
display:none;
background:#0f172a;
padding:18px;
border-radius:16px;
margin-bottom:22px;
word-break:break-all;
font-size:18px;
line-height:36px;
}

.copybtn{
margin-top:14px;
background:#3b82f6;
font-size:18px;
}

.infoText{
margin-bottom:16px;
font-size:18px;
color:#cbd5e1;
line-height:34px;
}

.helper{
font-size:16px;
color:#94a3b8;
margin-bottom:20px;
line-height:30px;
}

.subSection{
background:#0f172a;
padding:16px;
border-radius:14px;
margin-bottom:20px;
}

.subSection .infoText{
margin-bottom:10px;
margin-top:0;
}

.subSection select,
.subSection input{
margin-top:0;
margin-bottom:12px;
}

.subSection input:last-child{
margin-bottom:0;
}

.couponSection{
background:#0f172a;
padding:16px;
border-radius:14px;
margin-bottom:20px;
}

.couponToggle{
display:flex;
align-items:center;
gap:10px;
font-size:16px;
margin-bottom:12px;
cursor:pointer;
}

.couponToggle input{
width:20px;
height:20px;
margin:0;
cursor:pointer;
}

.couponBox{
display:none;
margin-top:10px;
}

.couponBox.is-open{
display:block;
}

.couponRow input{
width:100%;
margin:0;
}

.couponResult{
margin-top:12px;
padding:12px;
border-radius:12px;
font-size:15px;
line-height:1.8;
display:none;
}

.couponResult.is-ok{
display:block;
background:#14532d;
}

.couponResult.is-error{
display:block;
background:#7f1d1d;
}

@media(max-width:768px){

body{
padding:10px;
}

.box{
max-width:100%;
padding:30px 20px;
border-radius:24px;
}

h2{
font-size:28px;
}

input,
select{
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

.cardbox{
font-size:16px;
line-height:30px;
}

.msg,
.err{
font-size:16px;
line-height:30px;
}

.infoText{
font-size:16px;
line-height:30px;
}

.helper{
font-size:14px;
line-height:26px;
}

}

</style>

</head>

<body>

<div class="box">

<h2>

تمدید اشتراک

</h2>

<?php if($message!=""){ ?>
<div class="msg"><?php echo $message; ?></div>
<?php } ?>

<?php if($error!=""){ ?>
<div class="err"><?php echo $error; ?></div>
<?php } ?>

<form method="POST">

<div class="subSection">
<div class="infoText">لینک اشتراک</div>

<?php if(count($userSubscriptions) > 0){ ?>
<select id="subSelect" onchange="pickSubscription()">
<option value="">انتخاب از اشتراک‌های من</option>
<?php foreach($userSubscriptions as $item){ ?>
<option value="<?php echo htmlspecialchars($item['link'], ENT_QUOTES, 'UTF-8'); ?>">
<?php echo htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8'); ?>
</option>
<?php } ?>
<option value="__other__">لینک دیگر</option>
</select>
<?php } ?>

<input
type="text"
name="sub"
id="subInput"
placeholder="لینک اشتراک را وارد کنید"
required>
</div>

<select name="plan" id="planSelect" required>

<option value="">
انتخاب پلن
</option>

<?php

function formatPrice($price){

$price = intval($price);

if($price < 1000){

return
number_format($price)
.
" هزار تومان";

}

$million =
$price / 1000;

$million =
rtrim(
rtrim(
number_format($million,3),
'0'
),
'.'
);

return
$million
.
" میلیون تومان";

}

foreach($plans as $plan){

$priceText =
formatPrice($plan['price']);

$value =
$plan['name']
.
" - "
.
$priceText;

?>

<option value="<?php echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>" data-price="<?php echo (int)$plan['price']; ?>">

<?php echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>

</option>

<?php } ?>

</select>

<div class="couponSection">
<label class="couponToggle">
<input type="checkbox" name="has_coupon" id="hasCouponCheck" value="1">
<span>کد تخفیف دارید؟</span>
</label>
<div class="couponBox" id="couponBox">
<div class="couponRow">
<input type="text" name="coupon_code" id="couponCode" placeholder="کد را وارد کنید" autocomplete="off">
</div>
<div class="couponResult" id="couponResult"></div>
</div>
</div>

<div class="infoText">

انتخاب شماره کارت جهت پرداخت

</div>

<select id="cardSelect"
onchange="showCard()">

<option value="">
انتخاب کارت
</option>

<?php foreach($cards as $card){ ?>

<option value="<?php echo $card['card']; ?>">

<?php echo $card['name']; ?>

</option>

<?php } ?>

</select>

<div id="cardBox"
class="cardbox">

<div id="cardNumber"></div>

<button type="button"
onclick="copyCard()"
class="copybtn">

کپی شماره کارت

</button>

</div>

<div class="infoText">

لطفا پس از پرداخت، اطلاعات پرداخت خود را ثبت کنيد

</div>

<input type="text"
name="tracking"
placeholder="شماره پیگیری"
required>

<input type="text"
id="time"
name="time"
placeholder="ساعت"
maxlength="5"
required>

<input type="text"
id="date"
name="date"
placeholder="1405/01/01"
maxlength="10"
required>

<div class="helper">

لطفا در ثبت اطلاعات پرداخت خود دقت فرمایید

</div>

<button type="submit">

ثبت درخواست تمدید

</button>

</form>

<a href="dashboard.php"
class="back">

بازگشت

</a>

</div>

<script>

function pickSubscription(){

const select = document.getElementById('subSelect');
const input = document.getElementById('subInput');

if(!select){
    return;
}

const value = select.value;

if(value === ''){
    input.value = '';
    return;
}

if(value === '__other__'){
    input.value = '';
    input.focus();
    return;
}

input.value = value;
}

function showCard(){

let select =
document.getElementById("cardSelect");

let value = select.value;

if(value == ""){

document.getElementById("cardBox").style.display = "none";

return;

}

document.getElementById("cardBox").style.display = "block";

document.getElementById("cardNumber").innerText =
value;

}

function copyCard(){

let text =
document.getElementById("cardNumber").innerText;

navigator.clipboard.writeText(text);

alert("شماره کارت کپی شد");

}

document.getElementById("time").addEventListener("input", function(e){

let v = e.target.value.replace(/\D/g,'');

if(v.length >= 1){

let h1 = parseInt(v.charAt(0));

if(h1 > 2){
v = "2";
}

}

if(v.length >= 2){

let hh = parseInt(v.substring(0,2));

if(hh > 23){
v = "23";
}

}

if(v.length >= 3){

let m1 = parseInt(v.charAt(2));

if(m1 > 5){

v = v.substring(0,2) + "5";

}

}

if(v.length >= 4){

let mm = parseInt(v.substring(2,4));

if(mm > 59){

v = v.substring(0,2) + "59";

}

}

if(v.length >= 3){

v = v.substring(0,2) + ":" + v.substring(2,4);

}

e.target.value = v.substring(0,5);

});

function setTehranTime(){

const now = new Date();

const tehran = new Date(
now.toLocaleString(
"en-US",
{
timeZone: "Asia/Tehran"
}
)
);

let hh = tehran.getHours()
.toString()
.padStart(2,'0');

let mm = tehran.getMinutes()
.toString()
.padStart(2,'0');

document.getElementById("time").value =
hh + ":" + mm;

}

setTehranTime();

function setPersianDate(){

const now = new Date();

const formatter =
new Intl.DateTimeFormat(
'en-CA-u-ca-persian',
{
year:'numeric',
month:'2-digit',
day:'2-digit'
}
);

const parts = formatter.formatToParts(now);

let year = '';
let month = '';
let day = '';

parts.forEach(p => {

if(p.type === 'year'){
year = p.value;
}

if(p.type === 'month'){
month = p.value;
}

if(p.type === 'day'){
day = p.value;
}

});

document.getElementById("date").value =
year + "/" + month + "/" + day;

}

setPersianDate();

const planSelect = document.getElementById('planSelect');
const couponBox = document.getElementById('couponBox');
const couponResult = document.getElementById('couponResult');
const couponCodeInput = document.getElementById('couponCode');
const hasCouponCheck = document.getElementById('hasCouponCheck');
let couponTimer = null;

function resetCouponResult(){
    couponResult.className = 'couponResult';
    couponResult.textContent = '';
}

function validateCoupon(){
    const plan = planSelect.value;
    const code = couponCodeInput.value.trim();

    if(!hasCouponCheck.checked){
        resetCouponResult();
        return;
    }

    if(plan === ''){
        couponResult.className = 'couponResult is-error';
        couponResult.textContent = 'ابتدا پلن را انتخاب کنید';
        return;
    }

    if(code === ''){
        resetCouponResult();
        return;
    }

    fetch('coupon-api.php?plan=' + encodeURIComponent(plan) + '&code=' + encodeURIComponent(code), {
        credentials: 'same-origin'
    })
    .then(function(r){ return r.json(); })
    .then(function(data){
        if(!data.ok){
            couponResult.className = 'couponResult is-error';
            couponResult.textContent = data.error || 'کد تخفیف معتبر نیست';
            return;
        }

        couponResult.className = 'couponResult is-ok';
        couponResult.innerHTML =
            'تخفیف ' + data.percent + '٪ اعمال شد<br>' +
            'مبلغ پلن: ' + data.original_text + '<br>' +
            '<b>اینقدر باید پرداخت کنید: ' + data.final_text + '</b>';
    })
    .catch(function(){
        couponResult.className = 'couponResult is-error';
        couponResult.textContent = 'خطا در بررسی کد تخفیف';
    });
}

hasCouponCheck.addEventListener('change', function(){
    if(this.checked){
        couponBox.classList.add('is-open');
        couponCodeInput.focus();
        validateCoupon();
    } else {
        couponBox.classList.remove('is-open');
        couponCodeInput.value = '';
        resetCouponResult();
    }
});

couponCodeInput.addEventListener('input', function(){
    clearTimeout(couponTimer);
    couponTimer = setTimeout(validateCoupon, 400);
});

planSelect.addEventListener('change', validateCoupon);

</script>

</body>

</html>
