<?php

session_start();

if(!isset($_SESSION['user'])){
    header("Location: index.php");
    exit;
}

require_once __DIR__ . '/coupon_lib.php';
require_once __DIR__ . '/telegram_lib.php';
require_once __DIR__ . '/plan_ui_lib.php';
require_once __DIR__ . '/bank_lib.php';

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
$cardsUi = pnvCardsForUi($cards);

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
<link rel="stylesheet" href="plan_step_ui.css?v=15">
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
<input type="text" id="subnameInput" placeholder="مثلاً myconfig1" required minlength="5" maxlength="20" pattern="[A-Za-z0-9._-]+" title="فقط حروف لاتین، عدد و . _ - (۵ تا ۲۰ کاراکتر)">

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

<div class="destCardSection">
<div class="destCardTitle">
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="5" width="20" height="14" rx="2.5"/><path d="M2 10h20"/></svg>
<span>کارت مقصد</span>
</div>
<div class="cardTabs" id="cardTabs" role="tablist" aria-label="انتخاب کارت"></div>
<input type="hidden" id="selectedCard" value="">
<input type="hidden" id="selectedCardName" value="">

<div class="payCardBox" id="payCardBox" hidden>
<div class="payCardHead">
<img class="payCardIcon" id="payCardIcon" src="" alt="" hidden>
<div class="payCardMeta">
<div class="payCardBank" id="payCardBank">—</div>
<div class="payCardOwner" id="payCardOwner">—</div>
</div>
</div>
<div class="payCardNumberRow">
<div class="payCardNumber" id="payCardNumber">—</div>
<button type="button" class="iconCopyBtn" id="copyCardBtn" title="کپی شماره کارت" aria-label="کپی شماره کارت">
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15V5a2 2 0 0 1 2-2h10"/></svg>
</button>
</div>
</div>
</div>

<div class="payCreating" id="payCreating">در حال ایجاد مبلغ پرداخت…</div>

<div class="instantPay" id="instantPay" hidden>
<div class="instantPayTop">
<div class="instantPayHead" id="instantPayHead">مهلت پرداخت</div>
<div class="instantTimer" id="instantTimer">۳۰:۰۰</div>
</div>

<div class="instantAmountLabel">مبلغ قابل پرداخت</div>
<div class="instantAmountRow">
<div class="instantAmount" id="instantAmount">—</div>
<button type="button" class="iconCopyBtn" id="copyAmountBtn" title="کپی مبلغ" aria-label="کپی مبلغ">
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15V5a2 2 0 0 1 2-2h10"/></svg>
</button>
</div>
<div class="instantAmountToman" id="instantAmountToman"></div>
<div class="instantExactHint">دقیقاً همین مبلغ را واریز کنید</div>

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

<div class="catLockModal" id="payGuideModal" aria-hidden="true">
<div class="catLockBackdrop" id="payGuideBackdrop"></div>
<div class="catLockCard" role="dialog" aria-modal="true" aria-labelledby="payGuideText">
<p id="payGuideText"></p>
<button type="button" class="catLockClose is-primary" id="payGuideBtn">ادامه</button>
</div>
</div>

<script>
const plansData = <?php echo json_encode($plansUi, JSON_UNESCAPED_UNICODE); ?>;
const cardsData = <?php echo json_encode($cardsUi, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
const mode = 'buy';

const planSelect = document.getElementById('planSelect');
const planGrid = document.getElementById('planGrid');
const planEmpty = document.getElementById('planEmpty');
const planListTitle = document.getElementById('planListTitle');
const planSummary = document.getElementById('planSummary');
const toStep2Btn = document.getElementById('toStep2');
const toStep3Btn = document.getElementById('toStep3');
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
const cardTabs = document.getElementById('cardTabs');
const selectedCardInput = document.getElementById('selectedCard');
const selectedCardNameInput = document.getElementById('selectedCardName');
const payCardBox = document.getElementById('payCardBox');
const payCardIcon = document.getElementById('payCardIcon');
const payCardBank = document.getElementById('payCardBank');
const payCardOwner = document.getElementById('payCardOwner');
const payCardNumber = document.getElementById('payCardNumber');
let selectedCardMeta = null;
const payCreating = document.getElementById('payCreating');
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
let payCreateInFlight = false;

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
        ensureInstantPay();
    }
}

function formatCardDisplay(num){
    num = String(num || '').replace(/\D+/g, '');
    return num.replace(/(\d{4})(?=\d)/g, '$1 ').trim();
}

function selectCardMeta(card){
    selectedCardMeta = card || null;
    selectedCardInput.value = (card && card.card) || '';
    selectedCardNameInput.value = (card && card.name) || '';
    syncCardBox();
}

function renderCardTabs(){
    if(!cardTabs) return;
    cardTabs.innerHTML = '';
    const list = Array.isArray(cardsData) ? cardsData : [];
    if(list.length === 0){
        cardTabs.innerHTML = '<div class="cardTabsEmpty">کارتی تعریف نشده است. از پنل ادمین کارت اضافه کنید.</div>';
        return;
    }
    let preferred = 0;
    list.forEach(function(c, idx){
        if((c.bank || '') === 'post' || /پست/.test(c.bank_label || c.name || '')) preferred = idx;
    });
    list.forEach(function(card, idx){
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'cardTab' + (idx === preferred ? ' is-active' : '');
        btn.setAttribute('role', 'tab');
        btn.setAttribute('data-idx', String(idx));
        if(card.icon){
            const icon = document.createElement('img');
            icon.className = 'cardTabIcon';
            icon.src = card.icon;
            icon.alt = '';
            icon.loading = 'lazy';
            btn.appendChild(icon);
        }
        const tabLabel = document.createElement('span');
        tabLabel.className = 'cardTabLabel';
        tabLabel.textContent = card.bank_label || card.name || 'کارت';
        btn.appendChild(tabLabel);
        btn.addEventListener('click', function(){
            cardTabs.querySelectorAll('.cardTab').forEach(function(el){ el.classList.remove('is-active'); });
            btn.classList.add('is-active');
            selectCardMeta(card);
            ensureInstantPay(true);
        });
        cardTabs.appendChild(btn);
    });
    selectCardMeta(list[preferred] || list[0]);
}

function syncCardBox(){
    const card = selectedCardMeta;
    const number = (card && card.card) || (selectedCardInput && selectedCardInput.value) || '';
    if(!number){
        payCardBox.hidden = true;
        return;
    }
    payCardBox.hidden = false;
    if(payCardBank){
        payCardBank.textContent = (card && (card.bank_label || card.name)) || 'بانک';
    }
    if(payCardOwner){
        payCardOwner.textContent = (card && (card.holder || card.name)) || '—';
    }
    if(payCardIcon){
        if(card && card.icon){
            payCardIcon.src = card.icon;
            payCardIcon.alt = (card.bank_label || '') + '';
            payCardIcon.hidden = false;
        } else {
            payCardIcon.removeAttribute('src');
            payCardIcon.hidden = true;
        }
    }
    payCardNumber.textContent = formatCardDisplay(number);
}

renderCardTabs();
syncCardBox();

toStep2Btn.addEventListener('click', function(){
    const subname = document.getElementById('subnameInput');
    if(subname && !subname.checkValidity()){ subname.reportValidity(); return; }
    if(!planSelect.value){ alert('لطفا پلن را انتخاب کنید'); return; }
    showStep(2);
    window.scrollTo({ top: 0, behavior: 'smooth' });
});

function cancelPayBeacon(id){
    id = String(id || '').trim();
    if(!id) return;
    const body = new URLSearchParams();
    body.set('action', 'cancel');
    body.set('id', id);
    const payload = body.toString();
    try{
        if(navigator.sendBeacon){
            const blob = new Blob([payload], { type: 'application/x-www-form-urlencoded;charset=UTF-8' });
            if(navigator.sendBeacon('instant-pay-api.php', blob)) return;
        }
    }catch(e){}
    fetch('instant-pay-api.php', {
        method: 'POST',
        credentials: 'same-origin',
        keepalive: true,
        headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
        body: payload
    }).catch(function(){});
}

const userBackLink = document.querySelector('.userBack');
if(userBackLink){
    userBackLink.addEventListener('click', function(){
        // مبلغ کدگذاری‌شده بلافاصله منقضی/لغو شود
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
    if(step2 && step2.classList.contains('is-active')) ensureInstantPay(true);
});
couponCodeInput.addEventListener('input', function(){
    clearTimeout(couponTimer);
    couponTimer = setTimeout(function(){
        validateCoupon();
        if(step2 && step2.classList.contains('is-active')) ensureInstantPay(true);
    }, 450);
});
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
    payCreateInFlight = false;
    if(payCreating) payCreating.classList.remove('is-visible');
    if(instantPay){ instantPay.hidden = true; }
    if(instantApproved){ instantApproved.hidden = true; }
    if(instantStatus){ instantStatus.hidden = true; instantStatus.textContent = ''; }
    if(instantAmount){ instantAmount.textContent = '—'; }
    const amountTomanEl = document.getElementById('instantAmountToman');
    if(amountTomanEl){ amountTomanEl.textContent = ''; }
    if(instantTimer){ instantTimer.textContent = '۳۰:۰۰'; }
    if(instantPayHead){ instantPayHead.textContent = 'مهلت پرداخت'; }
    const restartBtn = document.getElementById('restartPayBtn');
    if(restartBtn) restartBtn.hidden = true;
    if(cancelId){
        cancelPayBeacon(cancelId);
    }
}

function renderPay(item){
    currentPay = item;
    if(payCreating) payCreating.classList.remove('is-visible');
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
        fillResult(item);
        showStep(3);
        window.scrollTo({ top: 0, behavior: 'smooth' });
        return;
    }

    if(item.status === 'expired'){
        // تایمر UI تمام شده؛ تا ۱۰ دقیقهٔ grace هنوز مچ می‌شود
        if(payTickTimer){ clearInterval(payTickTimer); payTickTimer = null; }
        instantTimer.textContent = '۰۰:۰۰';
        instantPayHead.textContent = 'مهلت تمام شد';
        instantStatus.hidden = false;
        instantStatus.textContent = 'مهلت ۳۰ دقیقه‌ای تمام شد. اگر همین الان واریز کرده‌اید تا ۱۰ دقیقه دیگر بررسی می‌شود؛ در غیر این صورت مبلغ جدید بسازید.';
        instantApproved.hidden = true;
        let restartBtn = document.getElementById('restartPayBtn');
        if(!restartBtn && instantPay){
            restartBtn = document.createElement('button');
            restartBtn.type = 'button';
            restartBtn.id = 'restartPayBtn';
            restartBtn.className = 'btnNext';
            restartBtn.style.marginTop = '12px';
            restartBtn.textContent = 'ساخت مبلغ جدید';
            restartBtn.addEventListener('click', function(){ ensureInstantPay(true); });
            instantPay.appendChild(restartBtn);
        }
        if(restartBtn) restartBtn.hidden = false;
        if(!payPollTimer && item.id){
            payPollTimer = setInterval(function(){ pollPayStatus(item.id); }, 2000);
        }
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
    const restartBtn = document.getElementById('restartPayBtn');
    if(restartBtn) restartBtn.hidden = true;
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
    pollPayStatus(id);
    document.addEventListener('visibilitychange', function onVis(){
        if(!document.hidden && currentPay && currentPay.id === id && currentPay.status !== 'paid'){
            pollPayStatus(id);
        }
    });
}

function showPayCreateError(msg){
    if(payCreating){
        payCreating.classList.add('is-visible', 'is-error');
        payCreating.textContent = msg || 'ساخت مبلغ ناموفق بود';
    }
    if(instantPay) instantPay.hidden = true;
}

function ensureInstantPay(forceRestart){
    const card = selectedCardInput ? selectedCardInput.value.trim() : '';
    const cardName = selectedCardNameInput ? selectedCardNameInput.value.trim() : '';
    const subname = document.getElementById('subnameInput').value.trim();

    if(!planSelect.value){
        showPayCreateError('ابتدا پلن را انتخاب کنید');
        return;
    }
    if(!card){
        showPayCreateError('کارت مقصد را از تب بانک‌ها انتخاب کنید');
        return;
    }
    if(subname.length < 5 || subname.length > 20 || !/^[A-Za-z0-9._-]+$/.test(subname)){
        showPayCreateError('نام کانفیگ باید ۵ تا ۲۰ کاراکتر لاتین/عدد باشد');
        return;
    }
    if(!forceRestart && currentPay && (currentPay.status === 'waiting' || currentPay.status === 'processing')){
        if(String(currentPay.card || '') === card){
            return;
        }
    }
    if(payCreateInFlight) return;
    payCreateInFlight = true;

    if(payCreating){
        payCreating.classList.add('is-visible');
        payCreating.classList.remove('is-error');
        payCreating.textContent = 'در حال ایجاد مبلغ پرداخت…';
    }
    // هنگام ساخت، باکس مبلغ/تایمر را با حالت لودینگ نشان بده
    if(instantPay){
        instantPay.hidden = false;
        if(instantAmount) instantAmount.textContent = '…';
        if(instantTimer) instantTimer.textContent = '۳۰:۰۰';
        if(instantPayHead) instantPayHead.textContent = 'مهلت پرداخت';
        const amountTomanEl = document.getElementById('instantAmountToman');
        if(amountTomanEl) amountTomanEl.textContent = '';
        if(instantStatus){ instantStatus.hidden = true; instantStatus.textContent = ''; }
        if(instantApproved) instantApproved.hidden = true;
    }

    const prevId = currentPay && currentPay.id ? currentPay.id : '';
    stopPayWatchers();
    currentPay = null;

    const startCreate = function(){
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
        .then(function(r){
            return r.text().then(function(t){
                var data = null;
                try{ data = JSON.parse(t); }catch(e){ data = null; }
                if(!r.ok){
                    throw new Error((data && data.error) || ('خطای سرور ' + r.status));
                }
                return data;
            });
        })
        .then(function(data){
            payCreateInFlight = false;
            if(!data || !data.ok || !data.item){
                showPayCreateError((data && data.error) || 'ساخت مبلغ ناموفق بود');
                return;
            }
            if(payCreating){
                payCreating.classList.remove('is-visible', 'is-error');
                payCreating.textContent = 'در حال ایجاد مبلغ پرداخت…';
            }
            renderPay(data.item);
            startPayWatchers(data.item.id);
        })
        .catch(function(err){
            payCreateInFlight = false;
            showPayCreateError((err && err.message) || 'خطا در ارتباط با سرور');
        });
    };

    if(prevId){
        // اول سفارش قبلی را لغو کن، بعد مبلغ جدید بساز (جلوگیری از تداخل)
        const body = new URLSearchParams();
        body.set('action', 'cancel');
        body.set('id', prevId);
        fetch('instant-pay-api.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
            body: body.toString()
        }).finally(startCreate);
    } else {
        startCreate();
    }
}

function copyText(t, msg, opts){
    if(!t) return Promise.resolve(false);
    opts = opts || {};
    return navigator.clipboard.writeText(String(t)).then(function(){
        if(msg && !opts.silent){ alert(msg); }
        return true;
    }).catch(function(){ return false; });
}

const PAY_GUIDE_KEY = 'pnv_pay_guide_seen_v1';
const payGuideModal = document.getElementById('payGuideModal');
const payGuideText = document.getElementById('payGuideText');
const payGuideBtn = document.getElementById('payGuideBtn');
let payGuideStep = 1;
const payGuidePages = [
    'روند خرید و تمدید را <b>خودکار</b> کردیم؛ شما کار خاصی لازم نیست انجام دهید. فقط مبلغ را <b>دقیقاً مطابق همین عدد</b> پرداخت کنید. چند ثانیه بعد پرداختتان تأیید می‌شود و اشتراک به‌صورت خودکار تمدید یا ایجاد می‌شود.',
    'حتی اگر صفحه را ببندید مشکلی نیست؛ خرید یا تمدید تأیید می‌شود. می‌توانید از داشبورد، <b>اشتراک‌های من</b> را بزنید و نتیجه را ببینید.'
];

function openPayGuide(){
    if(!payGuideModal || !payGuideText || !payGuideBtn) return;
    payGuideStep = 1;
    renderPayGuideStep();
    payGuideModal.classList.add('is-open');
    payGuideModal.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
}

function renderPayGuideStep(){
    if(!payGuideText || !payGuideBtn) return;
    payGuideText.innerHTML = payGuidePages[payGuideStep - 1] || '';
    if(payGuideStep === 1){
        payGuideBtn.textContent = 'ادامه';
        payGuideBtn.classList.add('is-primary');
    } else {
        payGuideBtn.textContent = 'متوجه شدم';
        payGuideBtn.classList.remove('is-primary');
    }
}

function closePayGuide(){
    if(!payGuideModal) return;
    payGuideModal.classList.remove('is-open');
    payGuideModal.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
    try{ localStorage.setItem(PAY_GUIDE_KEY, '1'); }catch(e){}
}

if(payGuideBtn){
    payGuideBtn.addEventListener('click', function(){
        if(payGuideStep === 1){
            payGuideStep = 2;
            renderPayGuideStep();
            return;
        }
        closePayGuide();
    });
}

document.getElementById('copyCardBtn').addEventListener('click', function(){
    const raw = (selectedCardInput && selectedCardInput.value) || payCardNumber.textContent.replace(/\s+/g, '');
    copyText(String(raw).replace(/\D+/g, ''), 'شماره کارت کپی شد');
});
document.getElementById('copyAmountBtn').addEventListener('click', function(){
    if(!currentPay) return;
    var seen = false;
    try{ seen = localStorage.getItem(PAY_GUIDE_KEY) === '1'; }catch(e){}
    copyText(currentPay.amount, seen ? 'مبلغ کپی شد' : '', { silent: !seen }).then(function(){
        if(!seen) openPayGuide();
    });
});
document.getElementById('copyLinkBtn').addEventListener('click', function(){ copyText(resultLink.textContent.trim(), 'لینک کپی شد'); });
</script>
</body>
</html>
