<?php

session_start();

if(!isset($_SESSION['user'])){
    header("Location: index.php");
    exit;
}

require_once __DIR__ . '/coupon_lib.php';

$plans = [];

if(file_exists('db/plans.json')){
    $plans = json_decode(file_get_contents('db/plans.json'), true);
}

if(!is_array($plans)){
    $plans = [];
}

$message = "";
$error = "";

if($_SERVER['REQUEST_METHOD'] == "POST"){

    $sub = trim($_POST['sub']);
    $plan = trim($_POST['plan']);
    $tracking = trim($_POST['tracking']);
    $time = trim($_POST['time']);
    $date = trim($_POST['date']);
    $hasCoupon = isset($_POST['has_coupon']);
    $couponCode = trim($_POST['coupon_code'] ?? '');
    $discountPercent = 0;

    $validDomains = [

        'vip.boozhaan.ir',
        'vip2.boozhaan.ir',
        'vip3.boozhaan.ir',
        'vip4.boozhaan.ir'

    ];

    $valid = false;

    foreach($validDomains as $d){

        if(
            stripos($sub,$d) !== false
        ){

            $valid = true;
            break;

        }

    }

    if(!$valid){

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

        if($hasCoupon && $couponCode !== ''){
            couponMarkUsed($couponCode, $_SESSION['user']);
        }

        $message = "درخواست تمدید ثبت شد و درحال بررسی است";
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
margin:0;
padding:14px;
background:#0f172a;
font-family:tahoma;
direction:rtl;
color:white;
}

.box{
width:100%;
max-width:520px;
margin:auto;
background:#1e293b;
padding:22px;
border-radius:18px;
}

h2{
margin-top:0;
margin-bottom:24px;
text-align:center;
font-size:24px;
}

input,
select{
width:100%;
padding:14px;
margin-top:10px;
margin-bottom:18px;
border:none;
border-radius:10px;
box-sizing:border-box;
font-size:14px;
}

button{
width:100%;
padding:14px;
background:#22c55e;
border:none;
border-radius:10px;
color:white;
font-size:15px;
cursor:pointer;
}

button:hover{
opacity:0.9;
}

.msg{
background:#16a34a;
padding:12px;
border-radius:10px;
margin-bottom:18px;
text-align:center;
line-height:28px;
}

.err{
background:#dc2626;
padding:12px;
border-radius:10px;
margin-bottom:18px;
text-align:center;
line-height:28px;
}

.back{
display:block;
margin-top:18px;
background:#334155;
padding:13px;
border-radius:10px;
color:white;
text-decoration:none;
text-align:center;
}

.couponSection{
background:#0f172a;
padding:14px;
border-radius:12px;
margin-bottom:16px;
}

.couponToggle{
display:flex;
align-items:center;
gap:10px;
font-size:14px;
margin-bottom:10px;
cursor:pointer;
}

.couponToggle input{
width:18px;
height:18px;
margin:0;
cursor:pointer;
}

.couponBox{display:none;}
.couponBox.is-open{display:block;}

.couponRow input{
width:100%;
margin:0;
}

.couponResult{
margin-top:10px;
padding:10px;
border-radius:10px;
font-size:14px;
line-height:1.8;
display:none;
}

.couponResult.is-ok{display:block;background:#14532d;}
.couponResult.is-error{display:block;background:#7f1d1d;}

@media(max-width:768px){

body{
padding:10px;
}

.box{
padding:18px;
border-radius:14px;
}

h2{
font-size:22px;
}

input,
select,
button{
font-size:16px;
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

<div class="msg">

<?php echo $message; ?>

</div>

<?php } ?>

<?php if($error!=""){ ?>

<div class="err">

<?php echo $error; ?>

</div>

<?php } ?>

<form method="POST">

<input
type="text"
name="sub"
placeholder="لینک اشتراک"
required>

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

<input
type="text"
name="tracking"
placeholder="شماره پیگیری"
required>

<input
type="text"
id="time"
name="time"
maxlength="5"
placeholder="13:45"
required>

<input
type="text"
id="date"
name="date"
maxlength="10"
placeholder="1405/01/01"
required>

<button type="submit">

ثبت درخواست تمدید

</button>

</form>

<a
href="dashboard.php"
class="back">

بازگشت

</a>

</div>

<script>

document
.getElementById("time")
.addEventListener("input",function(e){

let v =
e.target.value.replace(/\D/g,'');

if(v.length >= 3){

v =
v.substring(0,2)
+
":"
+
v.substring(2,4);

}

e.target.value = v;

});

document
.getElementById("date")
.addEventListener("input",function(e){

let v =
e.target.value.replace(/\D/g,'');

if(v.length >= 5){

v =
v.substring(0,4)
+
"/"
+
v.substring(4);

}

if(v.length >= 8){

v =
v.substring(0,7)
+
"/"
+
v.substring(7,9);

}

e.target.value = v;

});

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
