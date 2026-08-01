<?php

session_start();

if(!isset($_SESSION['user'])){
    header("Location: index.php");
    exit;
}

require_once __DIR__ . '/coupon_lib.php';
require_once __DIR__ . '/telegram_lib.php';
require_once __DIR__ . '/plan_ui_lib.php';

$plans = [];
if(file_exists('db/plans.json')){
    $plans = json_decode(file_get_contents('db/plans.json'), true);
}
if(!is_array($plans)){ $plans = []; }
$plansUi = pnvPlansForStepUi($plans);

$cards = [];
if(file_exists('db/cards.json')){
    $cards = json_decode(file_get_contents('db/cards.json'), true);
}
if(!is_array($cards)){ $cards = []; }

$h = static function($v){
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
};

?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>خرید اشتراک جدید</title>
<link rel="stylesheet" href="/fonts.css">
<link rel="stylesheet" href="user_nav.css?v=1">
<link rel="stylesheet" href="plan_step_ui.css?v=8">
</head>
<body>
<div class="box">

<div class="topBar">
<a class="userBack" href="dashboard.php">بازگشت</a>
<div class="brand">خرید اشتراک جدید</div>
<span class="userBackSpacer" aria-hidden="true"></span>
</div>

<h2>خرید اشتراک جدید</h2>

<div class="stepper" id="stepper">
<div class="stepItem is-active" id="stepTab1"><div class="stepNum">1</div><div class="stepLabel">انتخاب پلن</div></div>
<div class="stepLine" id="stepLine1"></div>
<div class="stepItem" id="stepTab2"><div class="stepNum">2</div><div class="stepLabel">پرداخت</div></div>
<div class="stepLine" id="stepLine2"></div>
<div class="stepItem" id="stepTab3"><div class="stepNum">3</div><div class="stepLabel">دریافت اشتراک</div></div>
</div>

<form id="buyForm" onsubmit="return false;">

<!-- STEP 1 -->
<div class="formStep is-active" id="step1">
<div class="fieldLabel">نام کانفیگ</div>
<input type="text" id="subnameInput" placeholder="نام دلخواه برای کانفیگ" required>

<div class="sectionTitle">نوع پلن را انتخاب کنید</div>
<div class="catGrid">
<button type="button" class="catCard" data-cat="unlimited">
<span class="catCheck">✓</span><span class="catIcon">∞</span>
<span class="catTitle">نامحدود زمانی</span>
<span class="catDesc">بدون محدودیت در زمان استفاده</span>
</button>
<button type="button" class="catCard" data-cat="limited">
<span class="catCheck">✓</span><span class="catIcon">⏱</span>
<span class="catTitle">محدود زمانی</span>
<span class="catDesc">مدت مشخص (روز / ماه)</span>
</button>
</div>

<div class="planBlock" id="planBlock">
<div class="sectionTitle" id="planListTitle">حجم را انتخاب کنید</div>
<div class="planEmpty" id="planEmpty">در این دسته پلنی تعریف نشده است</div>
<div class="planGrid" id="planGrid"></div>
</div>

<input type="hidden" id="planSelect" value="">
<button type="button" class="btnNext" id="toStep2" disabled>ادامه ←</button>
</div>

<!-- STEP 2 -->
<div class="formStep" id="step2">
<div class="planSummary" id="planSummary"></div>

<div class="couponSection">
<label class="couponToggle">
<input type="checkbox" id="hasCouponCheck" value="1">
<span>کد تخفیف دارید؟</span>
</label>
<div class="couponBox" id="couponBox">
<input type="text" id="couponCode" placeholder="کد را وارد کنید" autocomplete="off">
<div class="couponResult" id="couponResult"></div>
</div>
</div>

<div class="sectionTitle">کارت مقصد</div>
<select id="cardSelect">
<option value="">انتخاب کارت</option>
<?php foreach($cards as $i => $card){
    $name = (string)($card['name'] ?? '');
    $num = (string)($card['card'] ?? '');
    $selected = (stripos($name, 'پست') !== false) ? ' selected' : '';
?>
<option value="<?php echo $h($num); ?>" data-name="<?php echo $h($name); ?>"<?php echo $selected; ?>>
<?php echo $h($name); ?>
</option>
<?php } ?>
</select>

<div class="payCardBox" id="payCardBox" hidden>
<div class="payCardOwner" id="payCardOwner">—</div>
<div class="payCardNumber" id="payCardNumber">—</div>
<button type="button" class="copybtn" id="copyCardBtn">کپی شماره کارت</button>
</div>

<button type="button" class="btnGhost" id="backStep1">بازگشت به انتخاب پلن</button>
<button type="button" id="startPayBtn">شروع مهلت پرداخت (۱۰ دقیقه)</button>

<div class="instantPay" id="instantPay" hidden>
<div class="instantPayHead" id="instantPayHead">مهلت پرداخت</div>
<div class="instantTimer" id="instantTimer">۱۰:۰۰</div>

<div class="instantAmountLabel">مبلغ قابل پرداخت</div>
<div class="instantAmount" id="instantAmount">—</div>
<div class="instantAmountToman" id="instantAmountToman"></div>
<div class="instantExactHint">دقیقاً همین مبلغ را واریز کنید</div>

<div class="instantActions">
<button type="button" class="copybtn" id="copyAmountBtn">کپی مبلغ</button>
<button type="button" class="copybtn" id="copyCardBtn2">کپی کارت</button>
</div>

<div class="instantStatus" id="instantStatus" hidden></div>

<div class="instantApproved" id="instantApproved" hidden>
<div class="instantDoneTitle">پرداخت شما تأیید شد ✅</div>
<div class="instantStatus">اشتراک آماده است. برای مشاهده روی ادامه بزنید.</div>
<button type="button" class="btnNext" id="toStep3">ادامه ←</button>
</div>
</div>
</div>

<!-- STEP 3 -->
<div class="formStep" id="step3">
<div class="resultCard">
<div class="resultTitle">اشتراک شما آماده است</div>
<div class="resultMeta" id="resultMeta"></div>
<div class="resultLinkWrap">
<div class="fieldLabel">لینک اشتراک</div>
<div class="resultLink" id="resultLink">—</div>
<button type="button" class="copybtn" id="copyLinkBtn">کپی لینک</button>
</div>
<div class="resultQrWrap" id="resultQrWrap">
<div class="fieldLabel">اسکن QR Code</div>
<div class="resultQrFrame">
<img id="resultQrImg" src="" alt="QR Code لینک اشتراک">
</div>
<div class="resultQrHint">با اسکن این کد، لینک اشتراک وارد اپ می‌شود</div>
</div>
<a class="btnGhost" href="subscriptions.php">اشتراک‌های من</a>
</div>
</div>

</form>
</div>

<script>
const plansData = <?php echo json_encode($plansUi, JSON_UNESCAPED_UNICODE); ?>;
const mode = 'buy';

const planSelect = document.getElementById('planSelect');
const planGrid = document.getElementById('planGrid');
const planEmpty = document.getElementById('planEmpty');
const planListTitle = document.getElementById('planListTitle');
const planSummary = document.getElementById('planSummary');
const toStep2Btn = document.getElementById('toStep2');
const toStep3Btn = document.getElementById('toStep3');
const backStep1Btn = document.getElementById('backStep1');
const step1 = document.getElementById('step1');
const step2 = document.getElementById('step2');
const step3 = document.getElementById('step3');
const stepTab1 = document.getElementById('stepTab1');
const stepTab2 = document.getElementById('stepTab2');
const stepTab3 = document.getElementById('stepTab3');
const stepLine1 = document.getElementById('stepLine1');
const stepLine2 = document.getElementById('stepLine2');
const couponBox = document.getElementById('couponBox');
const couponResult = document.getElementById('couponResult');
const couponCodeInput = document.getElementById('couponCode');
const hasCouponCheck = document.getElementById('hasCouponCheck');
const cardSelect = document.getElementById('cardSelect');
const payCardBox = document.getElementById('payCardBox');
const payCardOwner = document.getElementById('payCardOwner');
const payCardNumber = document.getElementById('payCardNumber');
const startPayBtn = document.getElementById('startPayBtn');
const instantPay = document.getElementById('instantPay');
const instantPayHead = document.getElementById('instantPayHead');
const instantTimer = document.getElementById('instantTimer');
const instantAmount = document.getElementById('instantAmount');
const instantStatus = document.getElementById('instantStatus');
const instantApproved = document.getElementById('instantApproved');
const resultMeta = document.getElementById('resultMeta');
const resultLink = document.getElementById('resultLink');
const resultQrWrap = document.getElementById('resultQrWrap');
const resultQrImg = document.getElementById('resultQrImg');

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
        if(planBlock) planBlock.classList.remove('is-visible');
        updateContinueState();
        return;
    }
    if(planBlock) planBlock.classList.add('is-visible');
    const list = (plansData || []).filter(function(p){ return p.category === selectedCategory; });
    const isLimited = selectedCategory === 'limited';
    if(planListTitle){
        planListTitle.textContent = isLimited ? 'حجم و مدت را انتخاب کنید' : 'حجم را انتخاب کنید';
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
        btn.className = 'planChip' + (isLimited ? ' planChip--limited' : '') + (selectedPlan && selectedPlan.value === plan.value ? ' is-active' : '');
        btn.innerHTML = '<span class="planCheck">✓</span><span class="planName"></span><span class="planPrice"></span>' + (isLimited ? '<span class="planDays"></span>' : '');
        btn.querySelector('.planName').textContent = plan.name;
        btn.querySelector('.planPrice').textContent = plan.price_text;
        if(isLimited){
            const d = btn.querySelector('.planDays');
            if(d) d.textContent = 'مدت: ' + (plan.days_label || '—');
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
    step1.classList.toggle('is-active', step === 1);
    step2.classList.toggle('is-active', step === 2);
    step3.classList.toggle('is-active', step === 3);
    stepTab1.classList.toggle('is-active', step === 1);
    stepTab2.classList.toggle('is-active', step === 2);
    stepTab3.classList.toggle('is-active', step === 3);
    stepTab1.classList.toggle('is-done', step > 1);
    stepTab2.classList.toggle('is-done', step > 2);
    if(stepLine1) stepLine1.classList.toggle('is-active', step > 1);
    if(stepLine2) stepLine2.classList.toggle('is-active', step > 2);

    if(step === 2 && selectedPlan){
        planSummary.classList.add('is-visible');
        let html = 'پلن: <b>' + selectedPlan.name + '</b> — ' + selectedPlan.price_text;
        html += '<br>نوع: ' + (selectedCategory === 'unlimited' ? 'نامحدود زمانی' : 'محدود زمانی');
        if(selectedCategory === 'limited') html += '<br>مدت: <b>' + (selectedPlan.days_label || '—') + '</b>';
        planSummary.innerHTML = html;
        syncCardBox();
        validateCoupon();
    }
}

function syncCardBox(){
    const opt = cardSelect.options[cardSelect.selectedIndex];
    const card = cardSelect.value;
    const name = opt ? (opt.getAttribute('data-name') || opt.textContent || '') : '';
    if(!card){
        payCardBox.hidden = true;
        return;
    }
    payCardBox.hidden = false;
    payCardOwner.textContent = name || 'کارت انتخاب‌شده';
    payCardNumber.textContent = card;
}

cardSelect.addEventListener('change', syncCardBox);
syncCardBox();

toStep2Btn.addEventListener('click', function(){
    const subname = document.getElementById('subnameInput');
    if(subname && !subname.checkValidity()){ subname.reportValidity(); return; }
    if(!planSelect.value){ alert('لطفا پلن را انتخاب کنید'); return; }
    showStep(2);
    window.scrollTo({ top: 0, behavior: 'smooth' });
});

backStep1Btn.addEventListener('click', function(){
    resetPaySession();
    showStep(1);
    window.scrollTo({ top: 0, behavior: 'smooth' });
});

const userBackLink = document.querySelector('.userBack');
if(userBackLink){
    userBackLink.addEventListener('click', function(){
        resetPaySession();
    });
}
toStep3Btn.addEventListener('click', function(){
    if(!currentPay || currentPay.status !== 'paid'){ return; }
    fillResult(currentPay);
    showStep(3);
    window.scrollTo({ top: 0, behavior: 'smooth' });
});

function showResultQr(link){
    link = String(link || '').trim();
    if(!resultQrWrap || !resultQrImg) return;
    if(!link || link === '—' || link.indexOf('/sub/') === -1){
        resultQrWrap.classList.remove('is-visible');
        resultQrImg.removeAttribute('src');
        return;
    }
    resultQrImg.src = 'sub-qr.php?u=' + encodeURIComponent(link) + '&t=' + Date.now();
    resultQrWrap.classList.add('is-visible');
}

function fillResult(item){
    const name = document.getElementById('subnameInput').value.trim();
    resultMeta.innerHTML = 'نام کانفیگ: <b>' + name + '</b><br>پلن: <b>' + (item.plan || '—') + '</b>';
    const link = item.link || '—';
    resultLink.textContent = link;
    showResultQr(link);
}

function resetCouponResult(){
    couponResult.className = 'couponResult';
    couponResult.textContent = '';
}

function validateCoupon(){
    const plan = planSelect.value;
    const code = couponCodeInput.value.trim();
    if(!hasCouponCheck.checked){ resetCouponResult(); return; }
    if(plan === ''){ couponResult.className = 'couponResult is-error'; couponResult.textContent = 'ابتدا پلن را انتخاب کنید'; return; }
    if(code === ''){ resetCouponResult(); return; }
    fetch('coupon-api.php?plan=' + encodeURIComponent(plan) + '&code=' + encodeURIComponent(code), { credentials: 'same-origin' })
    .then(function(r){ return r.json(); })
    .then(function(data){
        if(!data.ok){ couponResult.className = 'couponResult is-error'; couponResult.textContent = data.error || 'کد تخفیف معتبر نیست'; return; }
        couponResult.className = 'couponResult is-ok';
        couponResult.innerHTML = 'تخفیف ' + data.percent + '٪<br>مبلغ پلن: ' + data.original_text + '<br><b>قابل پرداخت تقریبی: ' + data.final_text + '</b>';
    })
    .catch(function(){ couponResult.className = 'couponResult is-error'; couponResult.textContent = 'خطا در بررسی کد'; });
}

hasCouponCheck.addEventListener('change', function(){
    if(this.checked){ couponBox.classList.add('is-open'); couponCodeInput.focus(); validateCoupon(); }
    else { couponBox.classList.remove('is-open'); couponCodeInput.value = ''; resetCouponResult(); }
});
couponCodeInput.addEventListener('input', function(){ clearTimeout(couponTimer); couponTimer = setTimeout(validateCoupon, 400); });
planSelect.addEventListener('change', validateCoupon);

function formatRemain(sec){
    sec = Math.max(0, parseInt(sec, 10) || 0);
    return String(Math.floor(sec / 60)).padStart(2, '0') + ':' + String(sec % 60).padStart(2, '0');
}
function stopPayWatchers(){
    if(payPollTimer){ clearInterval(payPollTimer); payPollTimer = null; }
    if(payTickTimer){ clearInterval(payTickTimer); payTickTimer = null; }
}

function resetPaySession(){
    stopPayWatchers();
    const cancelId = currentPay && currentPay.id ? currentPay.id : '';
    currentPay = null;
    if(instantPay){ instantPay.hidden = true; }
    if(instantApproved){ instantApproved.hidden = true; }
    if(instantStatus){ instantStatus.hidden = true; instantStatus.textContent = ''; }
    if(instantAmount){ instantAmount.textContent = '—'; }
    const amountTomanEl = document.getElementById('instantAmountToman');
    if(amountTomanEl){ amountTomanEl.textContent = ''; }
    if(instantTimer){ instantTimer.textContent = '۱۰:۰۰'; }
    if(instantPayHead){ instantPayHead.textContent = 'مهلت پرداخت'; }
    if(startPayBtn){
        startPayBtn.disabled = false;
        startPayBtn.textContent = 'شروع مهلت پرداخت (۱۰ دقیقه)';
    }
    if(cancelId){
        const body = new URLSearchParams();
        body.set('action', 'cancel');
        body.set('id', cancelId);
        fetch('instant-pay-api.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
            body: body.toString()
        }).catch(function(){});
    }
}

function renderPay(item){
    currentPay = item;
    instantPay.hidden = false;
    instantAmount.textContent = item.amount_text || '—';
    const amountTomanEl = document.getElementById('instantAmountToman');
    if(amountTomanEl){
        amountTomanEl.textContent = item.amount_toman_text || '';
    }
    instantTimer.textContent = formatRemain(item.remaining);

    if(item.status === 'processing'){
        instantPayHead.textContent = 'در حال آماده‌سازی';
        instantStatus.hidden = false;
        instantStatus.textContent = 'واریز دیده شد؛ در حال ساخت اشتراک…';
        instantApproved.hidden = true;
        return;
    }

    if(item.status === 'paid'){
        stopPayWatchers();
        instantPayHead.textContent = 'پرداخت تأیید شد';
        instantTimer.textContent = '✓';
        instantStatus.hidden = true;
        instantApproved.hidden = false;
        startPayBtn.disabled = true;
        startPayBtn.textContent = 'پرداخت تأیید شد';
        fillResult(item);
        // برو صفحه بعد (دریافت اشتراک)
        showStep(3);
        window.scrollTo({ top: 0, behavior: 'smooth' });
        return;
    }

    if(item.status === 'expired'){
        stopPayWatchers();
        instantPayHead.textContent = 'مهلت تمام شد';
        instantStatus.hidden = false;
        instantStatus.textContent = 'مهلت پرداخت تمام شد. دوباره شروع کنید.';
        instantApproved.hidden = true;
        startPayBtn.disabled = false;
        startPayBtn.textContent = 'شروع مهلت جدید';
        return;
    }

    if(item.status === 'failed'){
        stopPayWatchers();
        instantStatus.hidden = false;
        instantStatus.textContent = item.message || 'خطا در آماده‌سازی اشتراک';
        instantApproved.hidden = true;
        return;
    }

    instantPayHead.textContent = 'مهلت پرداخت';
    instantStatus.hidden = true;
    instantStatus.textContent = '';
    instantApproved.hidden = true;
}

function pollPayStatus(id){
    fetch('instant-pay-api.php?action=status&id=' + encodeURIComponent(id), { credentials: 'same-origin' })
    .then(function(r){ return r.json(); })
    .then(function(data){ if(data && data.ok && data.item) renderPay(data.item); })
    .catch(function(){});
}

function startPayWatchers(id){
    stopPayWatchers();
    payTickTimer = setInterval(function(){
        if(!currentPay || currentPay.status !== 'waiting') return;
        currentPay.remaining = Math.max(0, (currentPay.remaining || 0) - 1);
        instantTimer.textContent = formatRemain(currentPay.remaining);
    }, 1000);
    payPollTimer = setInterval(function(){ pollPayStatus(id); }, 2000);
    // بلافاصله یک‌بار هم چک کن
    pollPayStatus(id);
    document.addEventListener('visibilitychange', function onVis(){
        if(!document.hidden && currentPay && currentPay.id === id && currentPay.status !== 'paid'){
            pollPayStatus(id);
        }
    });
}

startPayBtn.addEventListener('click', function(){
    const card = cardSelect.value;
    const opt = cardSelect.options[cardSelect.selectedIndex];
    const cardName = opt ? (opt.getAttribute('data-name') || opt.textContent || '') : '';
    const subname = document.getElementById('subnameInput').value.trim();
    if(!planSelect.value){ alert('پلن را انتخاب کنید'); return; }
    if(!card){ alert('کارت را انتخاب کنید'); return; }
    if(subname.length < 5){ alert('نام کانفیگ حداقل ۵ کاراکتر'); return; }

    startPayBtn.disabled = true;
    startPayBtn.textContent = 'در حال ساخت مبلغ…';

    const body = new URLSearchParams();
    body.set('action', 'create');
    body.set('type', 'خرید');
    body.set('plan', planSelect.value);
    body.set('subname', subname);
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
            startPayBtn.textContent = 'شروع مهلت پرداخت (۱۰ دقیقه)';
            alert((data && data.error) || 'ساخت سفارش ناموفق بود');
            return;
        }
        startPayBtn.textContent = 'پرداخت در جریان…';
        renderPay(data.item);
        startPayWatchers(data.item.id);
        instantPay.scrollIntoView({ behavior: 'smooth', block: 'start' });
    })
    .catch(function(){
        startPayBtn.disabled = false;
        startPayBtn.textContent = 'شروع مهلت پرداخت (۱۰ دقیقه)';
        alert('خطا در ارتباط با سرور');
    });
});

function copyText(t, msg){
    if(!t) return;
    navigator.clipboard.writeText(String(t)).then(function(){ alert(msg || 'کپی شد'); });
}
document.getElementById('copyCardBtn').addEventListener('click', function(){ copyText(payCardNumber.textContent.trim(), 'شماره کارت کپی شد'); });
document.getElementById('copyCardBtn2').addEventListener('click', function(){ copyText(payCardNumber.textContent.trim(), 'شماره کارت کپی شد'); });
document.getElementById('copyAmountBtn').addEventListener('click', function(){
    if(!currentPay) return;
    copyText(currentPay.amount, 'مبلغ کپی شد');
});
document.getElementById('copyLinkBtn').addEventListener('click', function(){ copyText(resultLink.textContent.trim(), 'لینک کپی شد'); });
</script>
</body>
</html>
