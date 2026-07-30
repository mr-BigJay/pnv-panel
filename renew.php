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

// ثبت تمدید از طریق instant-pay-api.php انجام می‌شود (پرداخت آنی بله)

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

<link rel="stylesheet" href="/fonts.css">
<link rel="stylesheet" href="user_nav.css?v=1">
<link rel="stylesheet" href="plan_step_ui.css?v=4">

</head>

<body>

<div class="box">

<div class="topBar">
<a class="userBack" href="dashboard.php">بازگشت</a>
<div class="brand">تمدید اشتراک</div>
<span class="userBackSpacer" aria-hidden="true"></span>
</div>

<h2>تمدید اشتراک</h2>


<?php if($message!=""){ ?>
<div class="msg"><?php echo $message; ?></div>
<?php } ?>

<?php if($error!=""){ ?>
<div class="err"><?php echo $error; ?></div>
<?php } ?>

<div class="stepper" id="stepper">
<div class="stepItem is-active" id="stepTab1">
<div class="stepNum">1</div>
<div class="stepLabel">پلن</div>
</div>
<div class="stepLine" id="stepLine"></div>
<div class="stepItem" id="stepTab2">
<div class="stepNum">2</div>
<div class="stepLabel">پرداخت</div>
</div>
</div>

<form method="POST" id="renewForm" onsubmit="return false;">

<div class="formStep is-active" id="step1">

<div class="subSection">
<div class="fieldLabel">لینک اشتراک</div>

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

<div class="sectionTitle">نوع پلن را انتخاب کنید</div>

<div class="catGrid">
<button type="button" class="catCard" data-cat="unlimited">
<span class="catCheck">✓</span>
<span class="catIcon">∞</span>
<span class="catTitle">نامحدود زمانی</span>
<span class="catDesc">بدون محدودیت در زمان استفاده</span>
</button>
<button type="button" class="catCard" data-cat="limited">
<span class="catCheck">✓</span>
<span class="catIcon">⏱</span>
<span class="catTitle">محدود زمانی</span>
<span class="catDesc">مدت مشخص (روز / ماه)</span>
</button>
</div>

<div class="planBlock" id="planBlock">
<div class="sectionTitle" id="planListTitle">حجم را انتخاب کنید</div>
<div class="planEmpty" id="planEmpty">در این دسته پلنی تعریف نشده است</div>
<div class="planGrid" id="planGrid"></div>
</div>

<input type="hidden" name="plan" id="planSelect" value="" required>

<button type="button" class="btnNext" id="toStep2" disabled>ادامه ←</button>

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

<div class="infoText">انتخاب شماره کارت جهت پرداخت</div>

<select id="cardSelect" onchange="showCard()">
<option value="">انتخاب کارت</option>
<?php foreach($cards as $card){ ?>
<option
    value="<?php echo htmlspecialchars($card['card'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
    data-name="<?php echo htmlspecialchars($card['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
>
<?php echo htmlspecialchars($card['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
</option>
<?php } ?>
</select>

<div id="cardBox" class="cardbox">
<div id="cardNumber"></div>
<button type="button" onclick="copyCard()" class="copybtn">کپی شماره کارت</button>
</div>

<div class="helper">پس از زدن دکمه زیر، مبلغ یکتا با ۴ رقم پایانی مخصوص شما ساخته می‌شود. همین مبلغ را کارت‌به‌کارت کنید.</div>

<button type="button" class="btnGhost" id="backStep1">بازگشت به انتخاب پلن</button>
<button type="button" id="startPayBtn">دریافت مبلغ و شروع پرداخت</button>

<div class="instantPay" id="instantPay" hidden>
<div class="instantPayHead">مهلت پرداخت</div>
<div class="instantTimer" id="instantTimer">۱۰:۰۰</div>
<div class="instantBaseRow" id="instantBaseRow" hidden>
<span>قیمت پلن:</span>
<b id="instantBase">—</b>
</div>
<div class="instantAmountLabel">مبلغ واریز (کمتر از قیمت پلن)</div>
<div class="instantAmount" id="instantAmount">—</div>
<div class="instantCodeHint">۴ رقم آخر مبلغ، کد شناسایی سفارش است — <span id="instantSavedHint"></span></div>
<div class="instantCodeHint">کد سفارش: <b id="instantCode">----</b></div>
<div class="instantActions">
<button type="button" class="copybtn" id="copyAmountBtn">کپی مبلغ</button>
<button type="button" class="copybtn" id="copyCardBtn2">کپی کارت</button>
</div>
<div class="instantStatus" id="instantStatus">در انتظار واریز… بعد از پرداخت معمولاً کمتر از یک دقیقه طول می‌کشد.</div>
<div class="instantDone" id="instantDone" hidden>
<div class="instantDoneTitle">پرداخت تأیید شد ✅</div>
<a class="instantLink" id="instantLink" href="#" target="_blank" rel="noopener">مشاهده لینک اشتراک</a>
<a class="btnGhost" href="renew-list.php">لیست تمدیدها</a>
</div>
</div>

</div>

</form>

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
const startPayBtn = document.getElementById('startPayBtn');
const instantPay = document.getElementById('instantPay');
const instantTimer = document.getElementById('instantTimer');
const instantAmount = document.getElementById('instantAmount');
const instantCode = document.getElementById('instantCode');
const instantStatus = document.getElementById('instantStatus');
const instantDone = document.getElementById('instantDone');
const instantLink = document.getElementById('instantLink');
let couponTimer = null;
let selectedCategory = '';
let selectedPlan = null;
let payPollTimer = null;
let payTickTimer = null;
let currentPay = null;

function updateContinueState(){
    toStep2Btn.disabled = !(selectedCategory && selectedPlan && planSelect.value);
}

function renderPlans(){
    planGrid.innerHTML = '';
    planEmpty.classList.remove('is-visible');
    const planBlock = document.getElementById('planBlock');

    if(!selectedCategory){
        if(planBlock){ planBlock.classList.remove('is-visible'); }
        updateContinueState();
        return;
    }

    if(planBlock){ planBlock.classList.add('is-visible'); }
    const list = (plansData || []).filter(function(p){ return p.category === selectedCategory; });
    const isLimited = selectedCategory === 'limited';

    if(planListTitle){
        planListTitle.textContent = isLimited
            ? 'حجم و مدت را انتخاب کنید'
            : 'حجم را انتخاب کنید';
    }

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
        btn.className = 'planChip'
            + (isLimited ? ' planChip--limited' : '')
            + (selectedPlan && selectedPlan.value === plan.value ? ' is-active' : '');
        btn.innerHTML =
            '<span class="planCheck">✓</span>' +
            '<span class="planName"></span>' +
            '<span class="planPrice"></span>' +
            (isLimited ? '<span class="planDays"></span>' : '');
        btn.querySelector('.planName').textContent = plan.name;
        btn.querySelector('.planPrice').textContent = plan.price_text;
        if(isLimited){
            const daysEl = btn.querySelector('.planDays');
            if(daysEl){
                daysEl.textContent = 'مدت: ' + (plan.days_label || '—');
            }
        }
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
    stepTab1.classList.toggle('is-done', !isOne);
    const stepLine = document.getElementById('stepLine');
    if(stepLine){ stepLine.classList.toggle('is-active', !isOne); }

    if(!isOne && selectedPlan){
        planSummary.classList.add('is-visible');
        let summaryHtml = 'پلن انتخابی: <b>' + selectedPlan.name + '</b> — ' + selectedPlan.price_text +
            '<br>نوع: ' + (selectedCategory === 'unlimited' ? 'نامحدود زمانی' : 'محدود زمانی');
        if(selectedCategory === 'limited'){
            summaryHtml += '<br>مدت: <b>' + (selectedPlan.days_label || '—') + '</b>';
        }
        planSummary.innerHTML = summaryHtml;
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

function formatRemain(sec){
    sec = Math.max(0, parseInt(sec, 10) || 0);
    const m = String(Math.floor(sec / 60)).padStart(2, '0');
    const s = String(sec % 60).padStart(2, '0');
    return m + ':' + s;
}

function stopPayWatchers(){
    if(payPollTimer){ clearInterval(payPollTimer); payPollTimer = null; }
    if(payTickTimer){ clearInterval(payTickTimer); payTickTimer = null; }
}

function renderPay(item){
    currentPay = item;
    instantPay.hidden = false;
    instantAmount.textContent = item.amount_text || (Number(item.amount||0).toLocaleString('en-US') + ' تومان');
    instantCode.textContent = item.code || '----';
    instantTimer.textContent = formatRemain(item.remaining);

    if(item.status === 'paid'){
        stopPayWatchers();
        instantStatus.textContent = 'پرداخت تأیید شد';
        instantDone.hidden = false;
        if(item.link){
            instantLink.href = item.link;
            instantLink.style.display = '';
        } else {
            instantLink.style.display = 'none';
        }
        startPayBtn.disabled = true;
        return;
    }

    if(item.status === 'expired'){
        stopPayWatchers();
        instantStatus.textContent = 'مهلت پرداخت تمام شد. دوباره مبلغ بگیرید.';
        startPayBtn.disabled = false;
        startPayBtn.textContent = 'دریافت مبلغ جدید';
        return;
    }

    if(item.status === 'failed'){
        stopPayWatchers();
        instantStatus.textContent = item.message || 'تأیید خودکار ناموفق بود. با پشتیبانی تماس بگیرید.';
        return;
    }

    instantStatus.textContent = 'در انتظار واریز… مبلغ را دقیق کارت‌به‌کارت کنید.';
}

function startPayWatchers(id){
    stopPayWatchers();

    payTickTimer = setInterval(function(){
        if(!currentPay || currentPay.status !== 'waiting'){ return; }
        currentPay.remaining = Math.max(0, (currentPay.remaining || 0) - 1);
        instantTimer.textContent = formatRemain(currentPay.remaining);
        if(currentPay.remaining <= 0){
            instantStatus.textContent = 'مهلت در حال انقضا…';
        }
    }, 1000);

    payPollTimer = setInterval(function(){
        fetch('instant-pay-api.php?action=status&id=' + encodeURIComponent(id), { credentials: 'same-origin' })
            .then(function(r){ return r.json(); })
            .then(function(data){
                if(data && data.ok && data.item){
                    renderPay(data.item);
                }
            })
            .catch(function(){});
    }, 4000);
}

startPayBtn.addEventListener('click', function(){
    const cardSelect = document.getElementById('cardSelect');
    const card = cardSelect.value;
    const opt = cardSelect.options[cardSelect.selectedIndex];
    const cardName = opt ? (opt.getAttribute('data-name') || opt.textContent || '') : '';
    const sub = document.getElementById('subInput').value.trim();

    if(!planSelect.value){
        alert('پلن را انتخاب کنید');
        return;
    }
    if(!card){
        alert('کارت را انتخاب کنید');
        return;
    }
    if(!sub){
        alert('لینک اشتراک را وارد کنید');
        return;
    }

    startPayBtn.disabled = true;
    startPayBtn.textContent = 'در حال ساخت مبلغ…';

    const body = new URLSearchParams();
    body.set('action', 'create');
    body.set('type', 'تمدید');
    body.set('plan', planSelect.value);
    body.set('sub', sub);
    body.set('card', card);
    body.set('card_name', cardName);
    if(hasCouponCheck.checked){
        body.set('has_coupon', '1');
        body.set('coupon_code', couponCodeInput.value.trim());
    }

    fetch('instant-pay-api.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
        body: body.toString()
    })
    .then(function(r){ return r.json(); })
    .then(function(data){
        if(!data || !data.ok || !data.item){
            startPayBtn.disabled = false;
            startPayBtn.textContent = 'دریافت مبلغ و شروع پرداخت';
            alert((data && data.error) ? data.error : 'ساخت سفارش ناموفق بود');
            return;
        }
        startPayBtn.textContent = 'پرداخت در جریان…';
        renderPay(data.item);
        startPayWatchers(data.item.id);
        instantPay.scrollIntoView({ behavior: 'smooth', block: 'start' });
    })
    .catch(function(){
        startPayBtn.disabled = false;
        startPayBtn.textContent = 'دریافت مبلغ و شروع پرداخت';
        alert('خطا در ارتباط با سرور');
    });
});

document.getElementById('copyAmountBtn').addEventListener('click', function(){
    if(!currentPay){ return; }
    navigator.clipboard.writeText(String(currentPay.amount || '')).then(function(){
        alert('مبلغ کپی شد');
    });
});

document.getElementById('copyCardBtn2').addEventListener('click', function(){
    const t = document.getElementById('cardNumber').innerText.trim();
    if(!t){ alert('ابتدا کارت را انتخاب کنید'); return; }
    navigator.clipboard.writeText(t).then(function(){ alert('شماره کارت کپی شد'); });
});

</script>

</body>

</html>
