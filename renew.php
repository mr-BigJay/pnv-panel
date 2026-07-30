<?php

session_start();

if(!isset($_SESSION['user'])){
    header("Location: index.php");
    exit;
}

require_once __DIR__ . '/coupon_lib.php';
require_once __DIR__ . '/subscription_lib.php';
require_once __DIR__ . '/telegram_lib.php';
require_once __DIR__ . '/plan_ui_lib.php';

$plans = [];

if(file_exists('db/plans.json')){
    $plans = json_decode(file_get_contents('db/plans.json'), true);
}

if(!is_array($plans)){
    $plans = [];
}

$plansUi = pnvPlansForStepUi($plans);

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

.stepTabs{
display:flex;
gap:8px;
margin-bottom:22px;
}

.stepTab{
flex:1;
text-align:center;
padding:10px 8px;
border-radius:12px;
background:#0f172a;
color:#94a3b8;
font-size:14px;
font-weight:700;
}

.stepTab.is-active{
background:#14532d;
color:#bbf7d0;
}

.formStep{display:none}
.formStep.is-active{display:block}

.catGrid{
display:grid;
grid-template-columns:1fr 1fr;
gap:10px;
margin-bottom:18px;
}

.catCard{
width:100%;
padding:18px 12px;
border:1px solid #334155;
border-radius:16px;
background:#0f172a;
color:#e2e8f0;
font-size:16px;
font-weight:700;
cursor:pointer;
line-height:1.5;
}

.catCard.is-active{
border-color:#22c55e;
background:#14532d;
color:#fff;
box-shadow:inset 0 0 0 1px #22c55e;
}

.planGrid{
display:grid;
grid-template-columns:1fr 1fr;
gap:10px;
margin-bottom:18px;
}

.planChip{
width:100%;
padding:16px 12px;
border:1px solid #334155;
border-radius:16px;
background:#0f172a;
color:#fff;
cursor:pointer;
text-align:center;
}

.planChip .planName{
display:block;
font-size:18px;
font-weight:700;
margin-bottom:6px;
}

.planChip .planPrice{
display:block;
font-size:14px;
color:#86efac;
font-weight:700;
}

.planChip .planDays{
display:block;
margin-top:4px;
font-size:12px;
color:#94a3b8;
}

.planChip.is-active{
border-color:#22c55e;
background:#052e16;
}

.planEmpty{
display:none;
padding:16px;
border-radius:14px;
background:#0f172a;
color:#94a3b8;
text-align:center;
margin-bottom:18px;
font-size:15px;
}

.planEmpty.is-visible{display:block}

.planSummary{
display:none;
background:#0f172a;
border:1px solid #334155;
border-radius:14px;
padding:14px 16px;
margin-bottom:18px;
font-size:15px;
line-height:1.8;
color:#cbd5e1;
}

.planSummary.is-visible{display:block}
.planSummary b{color:#86efac}

.btnGhost{
width:100%;
padding:14px;
margin-bottom:12px;
background:#334155;
border:none;
border-radius:14px;
color:white;
font-size:18px;
cursor:pointer;
}

.btnNext:disabled{
opacity:.45;
cursor:not-allowed;
}

@media(max-width:768px){
.planGrid,
.catGrid{
grid-template-columns:1fr 1fr;
}
.catCard,
.planChip .planName{
font-size:15px;
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

<div class="stepTabs">
<div class="stepTab is-active" id="stepTab1">1 پلن</div>
<div class="stepTab" id="stepTab2">2 پرداخت</div>
</div>

<form method="POST" id="renewForm">

<div class="formStep is-active" id="step1">

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

<div class="infoText">نوع اشتراک را انتخاب کنید</div>

<div class="catGrid">
<button type="button" class="catCard" data-cat="unlimited">نامحدود زمانی</button>
<button type="button" class="catCard" data-cat="limited">محدود زمانی</button>
</div>

<div class="infoText" id="planListTitle" style="display:none">انتخاب پلن</div>
<div class="planEmpty" id="planEmpty">در این دسته پلنی تعریف نشده است</div>
<div class="planGrid" id="planGrid"></div>

<input type="hidden" name="plan" id="planSelect" value="" required>

<button type="button" class="btnNext" id="toStep2" disabled>ادامه</button>

</div>

<div class="formStep" id="step2">

<div class="planSummary" id="planSummary"></div>

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

<option value="<?php echo htmlspecialchars($card['card'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">

<?php echo htmlspecialchars($card['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>

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

<button type="button" class="btnGhost" id="backStep1">بازگشت به انتخاب پلن</button>

<button type="submit">

ثبت درخواست تمدید

</button>

</div>

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

const plansData = <?php echo json_encode($plansUi, JSON_UNESCAPED_UNICODE); ?>;
const planSelect = document.getElementById('planSelect');
const planGrid = document.getElementById('planGrid');
const planEmpty = document.getElementById('planEmpty');
const planListTitle = document.getElementById('planListTitle');
const planSummary = document.getElementById('planSummary');
const toStep2Btn = document.getElementById('toStep2');
const backStep1Btn = document.getElementById('backStep1');
const step1 = document.getElementById('step1');
const step2 = document.getElementById('step2');
const stepTab1 = document.getElementById('stepTab1');
const stepTab2 = document.getElementById('stepTab2');
const couponBox = document.getElementById('couponBox');
const couponResult = document.getElementById('couponResult');
const couponCodeInput = document.getElementById('couponCode');
const hasCouponCheck = document.getElementById('hasCouponCheck');
let couponTimer = null;
let selectedCategory = '';
let selectedPlan = null;

function updateContinueState(){
    toStep2Btn.disabled = !(selectedCategory && selectedPlan && planSelect.value);
}

function renderPlans(){
    planGrid.innerHTML = '';
    planEmpty.classList.remove('is-visible');

    if(!selectedCategory){
        planListTitle.style.display = 'none';
        updateContinueState();
        return;
    }

    planListTitle.style.display = 'block';
    const list = (plansData || []).filter(function(p){ return p.category === selectedCategory; });

    if(list.length === 0){
        planEmpty.classList.add('is-visible');
        selectedPlan = null;
        planSelect.value = '';
        updateContinueState();
        return;
    }

    list.forEach(function(plan){
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'planChip' + (selectedPlan && selectedPlan.value === plan.value ? ' is-active' : '');
        btn.innerHTML =
            '<span class="planName"></span>' +
            '<span class="planPrice"></span>' +
            '<span class="planDays"></span>';
        btn.querySelector('.planName').textContent = plan.name;
        btn.querySelector('.planPrice').textContent = plan.price_short;
        btn.querySelector('.planDays').textContent = plan.days_label;
        btn.addEventListener('click', function(){
            selectedPlan = plan;
            planSelect.value = plan.value;
            planSelect.dispatchEvent(new Event('change'));
            renderPlans();
            updateContinueState();
        });
        planGrid.appendChild(btn);
    });

    updateContinueState();
}

document.querySelectorAll('.catCard').forEach(function(card){
    card.addEventListener('click', function(){
        selectedCategory = card.getAttribute('data-cat');
        document.querySelectorAll('.catCard').forEach(function(el){ el.classList.remove('is-active'); });
        card.classList.add('is-active');
        selectedPlan = null;
        planSelect.value = '';
        planSelect.dispatchEvent(new Event('change'));
        renderPlans();
    });
});

function showStep(step){
    const isOne = step === 1;
    step1.classList.toggle('is-active', isOne);
    step2.classList.toggle('is-active', !isOne);
    stepTab1.classList.toggle('is-active', isOne);
    stepTab2.classList.toggle('is-active', !isOne);

    if(!isOne && selectedPlan){
        planSummary.classList.add('is-visible');
        planSummary.innerHTML = 'پلن انتخابی: <b>' + selectedPlan.name + '</b> — ' + selectedPlan.price_text +
            '<br>نوع: ' + (selectedCategory === 'unlimited' ? 'نامحدود زمانی' : 'محدود زمانی');
        validateCoupon();
    }
}

toStep2Btn.addEventListener('click', function(){
    const subInput = document.getElementById('subInput');
    if(subInput && !subInput.checkValidity()){
        subInput.reportValidity();
        return;
    }
    if(!planSelect.value){
        alert('لطفا پلن را انتخاب کنید');
        return;
    }
    showStep(2);
    window.scrollTo({ top: 0, behavior: 'smooth' });
});

backStep1Btn.addEventListener('click', function(){
    showStep(1);
    window.scrollTo({ top: 0, behavior: 'smooth' });
});

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

<?php if($error != ''){ ?>
showStep(2);
<?php } ?>

</script>

</body>

</html>
