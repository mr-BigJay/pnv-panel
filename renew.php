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
require_once __DIR__ . '/instant_pay_lib.php';
require_once __DIR__ . '/bank_lib.php';

$plans = function_exists('pnvLoadPlans') ? pnvLoadPlans() : [];

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
$cardsUi = pnvCardsForUi($cards);
$payWindowSeconds = instantPayWindowSeconds();

function renewIsValidSubLink($value){
    return pnvIsValidSubLink($value);
}

function renewNormalizeSubLink($value){
    return pnvNormalizeSubLinkValue($value);
}

function renewLoadUserSubscriptions($username){

    $entries = pnvLoadUserActiveSubscriptions($username);

    foreach($entries as &$entry){
        $entry['time_category'] = pnvResolveSubTimeCategory(
            $entry['link'] ?? '',
            $entry['plan_text'] ?? '',
            $username
        );
        unset($entry['plan_text'], $entry['tracking'], $entry['date'], $entry['time']);
    }
    unset($entry);

    return $entries;
}

$userSubscriptions = renewLoadUserSubscriptions($_SESSION['user']);

$message = "";
$error = "";

// ثبت تمدید از طریق instant-pay-api.php انجام می‌شود (پرداخت آنی بله)

$h = static function($v){
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
};

?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>تمدید اشتراک</title>
<link rel="stylesheet" href="/fonts.css">
<link rel="stylesheet" href="user_nav.css?v=1">
<link rel="stylesheet" href="plan_step_ui.css?v=25">
<style>
.topBar .brand{
font-size:24px;
letter-spacing:.2px;
}
.sectionTitle{
text-align:center;
}
.catCard.is-locked{
opacity:.45;
filter:grayscale(.85);
cursor:not-allowed;
background:#1e293b;
border-color:#475569;
box-shadow:none;
}
.catCard.is-locked.is-active{
border-color:#475569;
background:#1e293b;
box-shadow:none;
}
.catCard.is-locked .catCheck{display:none !important}
.planChip.is-locked{
opacity:.4;
filter:grayscale(.9);
cursor:not-allowed;
background:#1e293b;
border-color:#475569;
}
.planChip.is-locked.is-active{
border-color:#475569;
background:#1e293b;
}
.planChip.is-locked .planCheck{display:none !important}
.subPickedLink{
margin:0 0 18px;
padding:10px 12px;
border-radius:12px;
background:#052e16;
border:1px solid rgba(34,197,94,.35);
color:#bbf7d0;
font-size:12px;
line-height:1.7;
word-break:break-all;
direction:ltr;
text-align:left;
}
.subPickedLink[hidden]{display:none !important}
#subSelect.has-picked{margin-bottom:8px}
#subInput.is-hidden-input{
position:absolute !important;
width:1px !important;
height:1px !important;
padding:0 !important;
margin:-1px !important;
overflow:hidden !important;
clip:rect(0,0,0,0) !important;
border:0 !important;
opacity:0 !important;
pointer-events:none !important;
}
@media(max-width:768px){
.topBar .brand{font-size:20px !important}
}
</style>
</head>
<body>
<div class="box">

<div class="topBar">
<a class="userBack" href="dashboard.php">بازگشت</a>
<div class="brand">تمدید اشتراک</div>
<span class="userBackSpacer" aria-hidden="true"></span>
</div>

<h2>تمدید اشتراک</h2>

<div class="stepper" id="stepper">
<div class="stepItem is-active" id="stepTab1"><div class="stepNum">1</div><div class="stepLabel">انتخاب پلن</div></div>
<div class="stepLine" id="stepLine1"></div>
<div class="stepItem" id="stepTab2"><div class="stepNum">2</div><div class="stepLabel">پرداخت</div></div>
<div class="stepLine" id="stepLine2"></div>
<div class="stepItem" id="stepTab3"><div class="stepNum">3</div><div class="stepLabel">تمدید اشتراک</div></div>
</div>

<form id="renewForm" onsubmit="return false;">

<div class="formStep is-active" id="step1">
<div class="subSection">
<div class="fieldLabel">لینک اشتراک</div>
<?php if(count($userSubscriptions) > 0){ ?>
<select id="subSelect">
<option value="">انتخاب از اشتراک‌های من</option>
<?php foreach($userSubscriptions as $item){
    $linkVal = trim((string)($item['link'] ?? ''));
?>
<option
    value="<?php echo $h($linkVal); ?>"
    data-link="<?php echo $h($linkVal); ?>"
    data-time-category="<?php echo $h($item['time_category'] ?? 'unknown'); ?>"
><?php echo $h($item['name']); ?></option>
<?php } ?>
<option value="__other__">لینک دیگر</option>
</select>
<div class="subPickedLink" id="subPickedLink" hidden></div>
<?php } ?>
<input type="text" id="subInput" name="sub" placeholder="لینک اشتراک را وارد کنید" required>
</div>

<div class="couponSection">
<label class="couponToggle">
<input type="checkbox" id="hasCouponCheck" value="1">
<span>کد تخفیف دارید؟</span>
</label>
<div class="couponBox" id="couponBox">
<div class="couponRow">
<input type="text" id="couponCode" placeholder="کد را وارد کنید" autocomplete="off">
<button type="button" class="couponApplyBtn" id="couponApplyBtn">ثبت</button>
</div>
<div class="couponResult" id="couponResult"></div>
</div>
</div>

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

<div class="formStep" id="step2">
<div class="planSummary" id="planSummary"></div>

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
<div class="instantExactHint">
<span class="instantExactHintLine">دقیقاً همین مبلغ را کارت به کارت کنید</span>
<span class="instantExactHintLine instantExactHintSub">تا اتوماتیک تایید شده و اشتراکتان آنی تمدید گردد</span>
</div>

<div class="instantStatus" id="instantStatus" hidden></div>
<div class="instantApproved" id="instantApproved" hidden>
<div class="instantDoneTitle">پرداخت شما تأیید شد ✅</div>
<div class="instantStatus">تمدید آماده است. برای مشاهده روی ادامه بزنید.</div>
<button type="button" class="btnNext" id="toStep3">ادامه ←</button>
</div>
</div>
</div>

<div class="formStep" id="step3">
<div class="resultScreen">
<div class="resultSuccessBanner">
<span class="resultSuccessTick" aria-hidden="true">✅</span>
<div class="resultSuccessText">
<strong>تمدید با موفقیت انجام شد</strong>
<span>لینک و QR آماده است</span>
</div>
</div>
<div class="resultPlanSummary planSummary is-visible">
<div class="planSummaryCard">
<div class="planSummaryBody">
<div class="planSummaryLine1" id="resultPlanLine1">پلن: —</div>
<div class="planSummaryLine2" id="resultPlanLine2">اکانت: —</div>
</div>
<div class="planSummaryIcon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" aria-hidden="true"><path d="M12 3 4 7.5v9L12 21l8-4.5v-9L12 3z"/><path d="M12 12 4 7.5M12 12l8-4.5M12 12v9"/></svg></div>
</div>
</div>
<div class="resultLinkWrap">
<div class="fieldLabel">لینک اشتراک تمدیدشده</div>
<div class="resultLink" id="resultLink">—</div>
<button type="button" class="copybtn" id="copyLinkBtn">کپی لینک</button>
</div>
<div class="resultQrWrap" id="resultQrWrap">
<div class="fieldLabel">اسکن QR Code</div>
<div class="resultQrFrame">
<img id="resultQrImg" src="" alt="QR Code لینک اشتراک">
</div>
<div class="resultQrHint">QR را با اپ VPN اسکن کنید</div>
</div>
<div class="resultActions">
<a class="btnResultPrimary" href="subscriptions.php">اشتراک‌های من</a>
</div>
</div>
</div>

</form>
</div>

<div class="catLockModal" id="catLockModal" aria-hidden="true">
<div class="catLockBackdrop" data-close="1"></div>
<div class="catLockCard" role="dialog" aria-modal="true" aria-labelledby="catLockText">
<p id="catLockText"></p>
<button type="button" class="catLockClose" data-close="1">متوجه شدم</button>
</div>
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
const userSubscriptions = <?php echo json_encode($userSubscriptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
const payWindowSeconds = <?php echo intval($payWindowSeconds); ?>;
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
const couponApplyBtn = document.getElementById('couponApplyBtn');
const hasCouponCheck = document.getElementById('hasCouponCheck');
const cardTabs = document.getElementById('cardTabs');
const selectedCardInput = document.getElementById('selectedCard');
const selectedCardNameInput = document.getElementById('selectedCardName');
const payCardBox = document.getElementById('payCardBox');
const payCardIcon = document.getElementById('payCardIcon');
const payCardBank = document.getElementById('payCardBank');
const payCardOwner = document.getElementById('payCardOwner');
const payCardNumber = document.getElementById('payCardNumber');
const payCreating = document.getElementById('payCreating');
let selectedCardMeta = null;
const instantPay = document.getElementById('instantPay');
const instantPayHead = document.getElementById('instantPayHead');
const instantTimer = document.getElementById('instantTimer');
const instantAmount = document.getElementById('instantAmount');
const instantStatus = document.getElementById('instantStatus');
const instantApproved = document.getElementById('instantApproved');
const resultPlanLine1 = document.getElementById('resultPlanLine1');
const resultPlanLine2 = document.getElementById('resultPlanLine2');
const resultLink = document.getElementById('resultLink');
const resultQrWrap = document.getElementById('resultQrWrap');
const resultQrImg = document.getElementById('resultQrImg');

function showResultQr(link){
    link = String(link || '').trim();
    if(!resultQrWrap || !resultQrImg) return;
    if(!link || link === '—' || link.indexOf('/sub/') === -1){
        resultQrWrap.classList.remove('is-visible');
        resultQrImg.removeAttribute('src');
        return;
    }
    resultQrImg.onerror = function(){
        resultQrWrap.classList.add('is-visible');
    };
    resultQrImg.src = 'sub-qr.php?link=' + encodeURIComponent(link) + '&t=' + Date.now();
    resultQrWrap.classList.add('is-visible');
}

function resolveSubDisplayName(link){
    link = String(link || '').trim();
    if(!link) return '—';

    const list = Array.isArray(userSubscriptions) ? userSubscriptions : [];
    for(let i = 0; i < list.length; i++){
        const item = list[i] || {};
        if(String(item.link || '').trim() === link){
            const name = String(item.name || '').trim();
            if(name && name.indexOf('/sub/') === -1) return name;
        }
    }

    const sel = document.getElementById('subSelect');
    if(sel){
        for(let j = 0; j < sel.options.length; j++){
            const opt = sel.options[j];
            if(String(opt.value || '').trim() === link){
                const label = String(opt.textContent || '').trim();
                if(label && label !== 'انتخاب از اشتراک‌های من' && label !== 'لینک دیگر') return label;
            }
        }
    }

    return shortSubLabel(link);
}

let couponTimer = null;
let selectedCategory = '';
let selectedPlan = null;
let payPollTimer = null;
let payTickTimer = null;
let currentPay = null;
let payCreateInFlight = false;
let subTimeCategory = 'unknown'; // unlimited | limited | unknown
let couponState = { applied: false, percent: 0, type: 'percent', value: 0 };

function fmtPrice(thousands){
    thousands = Math.round(+thousands || 0);
    if(thousands <= 0) return '۰ تومان';
    if(thousands < 1000){
        return thousands.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',') + ' هزار تومان';
    }
    var m = (thousands / 1000).toFixed(3).replace(/\.?0+$/, '');
    return m.replace(/\B(?=(\d{3})+(?!\d))/g, ',') + ' میلیون تومان';
}

function discountedPrice(original){
    if(!couponState.applied) return original;
    if(couponState.type === 'fixed'){
        return Math.max(0, original - couponState.value);
    }
    return Math.round(original * (100 - couponState.percent) / 100);
}
const catLockModal = document.getElementById('catLockModal');
const catLockText = document.getElementById('catLockText');
const subMetaByLink = <?php
$metaMap = [];
foreach($userSubscriptions as $item){
    $metaMap[strtolower((string)($item['link'] ?? ''))] = [
        'time_category' => $item['time_category'] ?? 'unknown',
        'name' => $item['name'] ?? ''
    ];
}
echo json_encode($metaMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
?>;

function lockMessageFor(cat){
    if(subTimeCategory !== 'limited' && cat === 'limited'){
        return 'این اشتراک <b>نامحدود زمانی</b> است و نمی‌توان آن را با پلن <b>زمان‌دار</b> تمدید کرد. در صورت نیاز <a href="buy.php">خرید اشتراک جدید</a> را بزنید.';
    }
    if(subTimeCategory === 'limited' && cat === 'unlimited'){
        return 'این اشتراک <b>زمان‌دار</b> است و نمی‌توان آن را با پلن <b>نامحدود زمانی</b> تمدید کرد. در صورت نیاز <a href="buy.php">خرید اشتراک جدید</a> را بزنید.';
    }
    return 'این نوع پلن برای تمدید این اشتراک قابل انتخاب نیست. در صورت نیاز <a href="buy.php">خرید اشتراک جدید</a> را بزنید.';
}

function showCatLockHint(cat){
    if(!catLockModal || !catLockText) return;
    catLockText.innerHTML = lockMessageFor(cat);
    catLockModal.classList.add('is-open');
    catLockModal.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
}

function hideCatLockHint(){
    if(!catLockModal) return;
    catLockModal.classList.remove('is-open');
    catLockModal.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
}

if(catLockModal){
    catLockModal.addEventListener('click', function(e){
        if(e.target && e.target.getAttribute('data-close') === '1'){
            hideCatLockHint();
        }
    });
}
document.addEventListener('keydown', function(e){
    if(e.key === 'Escape') hideCatLockHint();
});

function resolveSubTimeCategory(link){
    link = String(link || '').trim().toLowerCase();
    if(!link) return 'unknown';
    if(subMetaByLink[link] && subMetaByLink[link].time_category){
        return subMetaByLink[link].time_category;
    }
    // match by sub id suffix
    var keys = Object.keys(subMetaByLink || {});
    for(var i = 0; i < keys.length; i++){
        var k = keys[i];
        if(k && (link.indexOf(k) !== -1 || k.indexOf(link) !== -1)){
            return subMetaByLink[k].time_category || 'unknown';
        }
        try{
            var a = new URL(k);
            var b = new URL(link, window.location.origin);
            var pa = (a.pathname || '').split('/').pop();
            var pb = (b.pathname || '').split('/').pop();
            if(pa && pb && pa === pb) return subMetaByLink[k].time_category || 'unknown';
        }catch(err){}
    }
    return 'unknown';
}

function syncCategoryLocks(){
    document.querySelectorAll('.catCard').forEach(function(card){
        var cat = card.getAttribute('data-cat');
        var locked = false;
        if(subTimeCategory !== 'limited' && cat === 'limited') locked = true;
        if(subTimeCategory === 'limited' && cat === 'unlimited') locked = true;
        card.classList.toggle('is-locked', locked);
        if(locked && card.classList.contains('is-active')){
            card.classList.remove('is-active');
            if(selectedCategory === cat){
                selectedCategory = '';
                selectedPlan = null;
                if(planSelect) planSelect.value = '';
            }
        }
    });
    if(selectedCategory){
        var activeCard = document.querySelector('.catCard[data-cat="' + selectedCategory + '"]');
        if(activeCard && activeCard.classList.contains('is-locked')){
            selectedCategory = '';
            selectedPlan = null;
            if(planSelect) planSelect.value = '';
            hideCatLockHint();
        }
    }
    // اگر نوع مشخص است، دسته سازگار را خودکار باز کن
    if(subTimeCategory === 'unlimited' && !selectedCategory){
        selectedCategory = 'unlimited';
        document.querySelectorAll('.catCard').forEach(function(el){
            el.classList.toggle('is-active', el.getAttribute('data-cat') === selectedCategory);
        });
    }
    if(subTimeCategory === 'limited' && !selectedCategory){
        selectedCategory = 'limited';
        document.querySelectorAll('.catCard').forEach(function(el){
            el.classList.toggle('is-active', el.getAttribute('data-cat') === selectedCategory);
        });
    }
    try{ renderPlans(); }catch(err){ console && console.warn && console.warn(err); }
    try{ updateContinueState(); }catch(err){}
}

function setSubInputMode(mode, link){
    // mode: 'picked' | 'manual' | 'empty'
    const box = document.getElementById('subPickedLink');
    const select = document.getElementById('subSelect');
    const input = document.getElementById('subInput');
    link = String(link || '').trim();

    if(box){
        if(mode === 'picked' && link){
            box.hidden = false;
            box.textContent = link;
        } else {
            box.hidden = true;
            box.textContent = '';
        }
    }

    if(select){
        select.classList.toggle('has-picked', mode === 'picked');
    }

    if(input){
        // وقتی از لیست انتخاب شده فقط یک نمایشگر لینک کافی است
        input.classList.toggle('is-hidden-input', mode === 'picked');
        if(mode === 'picked'){
            input.value = link;
        } else if(mode === 'manual' && link === ''){
            // لینک دیگر: فیلد خالی برای ورود دستی
        }
    }
}

function pickSubscription(){
    const select = document.getElementById('subSelect');
    const input = document.getElementById('subInput');
    if(!select || !input) return;

    const opt = select.options[select.selectedIndex];
    const value = String((opt && (opt.getAttribute('data-link') || opt.value)) || select.value || '').trim();

    if(value === '' || value === '__other__'){
        if(value === '__other__'){
            input.value = '';
            setSubInputMode('manual', '');
            input.focus();
            subTimeCategory = 'unknown';
        } else {
            // ریست انتخاب از لیست — اگر قبلاً دستی بوده همان بماند
            const current = String(input.value || '').trim();
            setSubInputMode(current ? 'manual' : 'empty', current);
            subTimeCategory = resolveSubTimeCategory(current);
        }
        hideCatLockHint();
        syncCategoryLocks();
        return;
    }

    setSubInputMode('picked', value);
    subTimeCategory = (opt && opt.getAttribute('data-time-category')) || resolveSubTimeCategory(value) || 'unknown';
    hideCatLockHint();
    selectedPlan = null;
    if(planSelect) planSelect.value = '';
    syncCategoryLocks();
}
window.pickSubscription = pickSubscription;

function escapeHtml(s){
    return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

const planSummaryCubeSvg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" aria-hidden="true"><path d="M12 3 4 7.5v9L12 21l8-4.5v-9L12 3z"/><path d="M12 12 4 7.5M12 12l8-4.5M12 12v9"/></svg>';

function renderPlanSummaryHtml(plan, category, extraHtml){
    extraHtml = extraHtml || '';
    const typeLabel = category === 'unlimited' ? 'نامحدود زمانی' : 'محدود زمانی';
    return '<div class="planSummaryCard">' +
        '<div class="planSummaryBody">' +
        '<div class="planSummaryLine1">پلن: <span class="planSummaryHighlight">' + escapeHtml(plan.name) + '</span> — ' + escapeHtml(plan.price_text) + '</div>' +
        '<div class="planSummaryLine2">نوع: ' + typeLabel + '</div>' +
        extraHtml +
        '</div>' +
        '<div class="planSummaryIcon">' + planSummaryCubeSvg + '</div>' +
        '</div>';
}

function updateContinueState(){
    if(!toStep2Btn) return;
    const locked = selectedCategory && (
        (subTimeCategory !== 'limited' && selectedCategory === 'limited') ||
        (subTimeCategory === 'limited' && selectedCategory === 'unlimited')
    );
    const hasPlan = !!(selectedCategory && selectedPlan && planSelect && planSelect.value);
    toStep2Btn.disabled = !!(locked || !hasPlan);
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
    const categoryLocked = (
        (subTimeCategory !== 'limited' && selectedCategory === 'limited') ||
        (subTimeCategory === 'limited' && selectedCategory === 'unlimited')
    );
    if(planBlock) planBlock.classList.add('is-visible');
    const list = (plansData || []).filter(function(p){ return p.category === selectedCategory; });
    const isLimited = selectedCategory === 'limited';
    if(planListTitle) planListTitle.textContent = isLimited ? 'حجم و مدت را انتخاب کنید' : 'حجم را انتخاب کنید';
    if(list.length === 0){
        planEmpty.classList.add('is-visible');
        selectedPlan = null; planSelect.value = '';
        updateContinueState(); return;
    }
    list.forEach(function(plan){
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'planChip' + (isLimited ? ' planChip--limited' : '')
            + (selectedPlan && selectedPlan.value === plan.value ? ' is-active' : '')
            + (categoryLocked ? ' is-locked' : '');
        const disc = couponState.applied ? discountedPrice(plan.price) : 0;
        let priceHtml;
        if(couponState.applied && disc < plan.price){
            const badgeText = couponState.type === 'fixed'
                ? 'تخفیف ویژه'
                : ('٪' + couponState.percent + ' تخفیف');
            priceHtml = '<span class="planPriceWrap">' +
                        '<span class="planPrice planPrice--orig">' + escapeHtml(plan.price_text) + '</span>' +
                        '<span class="planPrice planPrice--disc">' + escapeHtml(fmtPrice(disc)) + '</span>' +
                        '</span>' +
                        '<span class="planDiscBadge">' + badgeText + '</span>';
        } else {
            priceHtml = '<span class="planPrice">' + escapeHtml(plan.price_text) + '</span>';
        }
        btn.innerHTML = '<span class="planCheck">✓</span><span class="planName"></span>' + priceHtml + (isLimited ? '<span class="planDays"></span>' : '');
        btn.querySelector('.planName').textContent = plan.name;
        if(isLimited){
            const d = btn.querySelector('.planDays');
            if(d) d.textContent = 'مدت: ' + (plan.days_label || '—');
        }
        btn.addEventListener('click', function(){
            if(categoryLocked){
                showCatLockHint(selectedCategory);
                return;
            }
            hideCatLockHint();
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
        const cat = card.getAttribute('data-cat');
        const locked = (
            (subTimeCategory !== 'limited' && cat === 'limited') ||
            (subTimeCategory === 'limited' && cat === 'unlimited')
        );
        if(locked){
            showCatLockHint(cat);
            return;
        }
        hideCatLockHint();
        selectedCategory = cat;
        document.querySelectorAll('.catCard').forEach(function(el){ el.classList.remove('is-active'); });
        card.classList.add('is-active');
        selectedPlan = null; planSelect.value = '';
        planSelect.dispatchEvent(new Event('change'));
        renderPlans();
    });
});

const subSelectEl = document.getElementById('subSelect');
if(subSelectEl){
    subSelectEl.addEventListener('change', pickSubscription);
    subSelectEl.addEventListener('input', pickSubscription);
}

const subInputEl = document.getElementById('subInput');
if(subInputEl){
    subInputEl.addEventListener('change', function(){
        if(subInputEl.classList.contains('is-hidden-input')) return;
        subTimeCategory = resolveSubTimeCategory(subInputEl.value);
        hideCatLockHint();
        syncCategoryLocks();
    });
    subInputEl.addEventListener('blur', function(){
        if(subInputEl.classList.contains('is-hidden-input')) return;
        subTimeCategory = resolveSubTimeCategory(subInputEl.value);
        syncCategoryLocks();
    });
}

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
        let extraHtml = '';
        if(selectedCategory === 'limited'){
            extraHtml += '<div class="planSummaryExtra">مدت: <b>' + escapeHtml(selectedPlan.days_label || '—') + '</b></div>';
        }
        const sub = document.getElementById('subInput').value.trim();
        if(sub){
            extraHtml += '<div class="planSummaryExtra">اکانت: <b>' + escapeHtml(resolveSubDisplayName(sub)) + '</b></div>';
        }
        if(couponState.applied){
            const disc = discountedPrice(selectedPlan.price);
            if(disc < selectedPlan.price){
                extraHtml += '<div class="planSummaryExtra">قیمت با تخفیف: <b>' + escapeHtml(fmtPrice(disc)) + '</b> <small style="text-decoration:line-through;opacity:.7">' + escapeHtml(selectedPlan.price_text) + '</small></div>';
            }
        }
        planSummary.innerHTML = renderPlanSummaryHtml(selectedPlan, selectedCategory, extraHtml);
        syncCardBox();
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
    cardTabs.classList.remove('is-hidden');
    const list = Array.isArray(cardsData) ? cardsData : [];
    if(list.length === 0){
        cardTabs.innerHTML = '<div class="cardTabsEmpty">کارتی تعریف نشده است. از پنل ادمین کارت اضافه کنید.</div>';
        selectCardMeta(null);
        return;
    }
    if(list.length === 1){
        cardTabs.classList.add('is-hidden');
        selectCardMeta(list[0]);
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
    const subInput = document.getElementById('subInput');
    if(subInput && !subInput.checkValidity()){ subInput.reportValidity(); return; }
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
function parsePlanForSummary(planText){
    const plan = String(planText || '—').trim();
    if(plan.indexOf(' - ') >= 0){
        const parts = plan.split(' - ', 2);
        return { size: parts[0], price: parts[1], raw: plan };
    }
    return { size: plan, price: '', raw: plan };
}

function shortSubLabel(value){
    const text = String(value || '').trim();
    const match = text.match(/\/sub\/([A-Za-z0-9]+)/i);
    return match ? match[1] : (text || '—');
}

function fillResult(item){
    const sub = document.getElementById('subInput').value.trim();
    const planParts = parsePlanForSummary(item.plan);
    if(planParts.price){
        resultPlanLine1.innerHTML = 'پلن: <span class="planSummaryHighlight">' + escapeHtml(planParts.size) + '</span> — ' + escapeHtml(planParts.price);
    }else{
        resultPlanLine1.innerHTML = 'پلن: <span class="planSummaryHighlight">' + escapeHtml(planParts.raw) + '</span>';
    }
    const link = item.link || sub || '—';
    const displayName = resolveSubDisplayName(link);
    resultPlanLine2.innerHTML = 'اکانت: <span class="planSummaryHighlight">' + escapeHtml(displayName) + '</span>';
    resultLink.textContent = link;
    showResultQr(link);
}

toStep3Btn.addEventListener('click', function(){
    if(!currentPay || currentPay.status !== 'paid') return;
    fillResult(currentPay);
    showStep(3);
    window.scrollTo({ top: 0, behavior: 'smooth' });
});

function resetCouponResult(){
    couponResult.className = 'couponResult';
    couponResult.textContent = '';
    couponState = { applied: false, percent: 0, type: 'percent', value: 0 };
}
function applyCouponResult(data){
    if(!data || !data.ok){
        couponResult.className = 'couponResult is-error';
        couponResult.textContent = (data && data.error) || 'کد تخفیف معتبر نیست';
        couponState = { applied: false, percent: 0, type: 'percent', value: 0 };
        renderPlans();
        return;
    }
    couponState = {
        applied: true,
        percent: data.percent || 0,
        type: data.type || 'percent',
        value: data.value || 0
    };
    couponResult.className = 'couponResult is-ok';
    if(data.type === 'fixed'){
        couponResult.textContent = 'کد تخفیف اعمال شد — پس از انتخاب پلن قیمت‌ها به‌روز می‌شود';
    } else {
        couponResult.textContent = 'کد تخفیف ' + (data.percent || 0) + '٪ تایید شد — پس از انتخاب پلن قیمت‌ها به‌روز می‌شود';
    }
    if(planSelect.value !== ''){
        if(data.type === 'fixed'){
            couponResult.textContent = 'کد تخفیف اعمال شد — قیمت‌ها به‌روز شد';
        } else {
            couponResult.textContent = 'کد تخفیف ' + (data.percent || 0) + '٪ اعمال شد — قیمت‌ها به‌روز شد';
        }
    }
    renderPlans();
}

function validateCoupon(){
    const code = couponCodeInput.value.trim();
    if(!hasCouponCheck.checked){
        resetCouponResult();
        renderPlans();
        return;
    }
    if(code === ''){
        resetCouponResult();
        renderPlans();
        return;
    }
    const planVal = planSelect.value;
    let url = '';
    if(planVal !== ''){
        url = 'coupon-api.php?plan=' + encodeURIComponent(planVal) + '&code=' + encodeURIComponent(code);
    } else if(selectedCategory){
        url = 'coupon-api.php?preview=1&code=' + encodeURIComponent(code);
    } else {
        url = 'coupon-api.php?base=1&code=' + encodeURIComponent(code);
    }
    fetch(url, { credentials: 'same-origin' })
    .then(function(r){ return r.json(); })
    .then(applyCouponResult)
    .catch(function(){
        couponResult.className = 'couponResult is-error';
        couponResult.textContent = 'خطا در بررسی کد';
    });
}
hasCouponCheck.addEventListener('change', function(){
    if(this.checked){ couponBox.classList.add('is-open'); couponCodeInput.focus(); }
    else { couponBox.classList.remove('is-open'); couponCodeInput.value = ''; resetCouponResult(); renderPlans(); }
});
if(couponApplyBtn){
    couponApplyBtn.addEventListener('click', function(){ validateCoupon(); });
}
couponCodeInput.addEventListener('keydown', function(e){
    if(e.key === 'Enter'){
        e.preventDefault();
        validateCoupon();
    }
});
planSelect.addEventListener('change', function(){
    if(hasCouponCheck.checked && couponCodeInput.value.trim() !== ''){
        validateCoupon();
    }
});

function formatRemain(sec){ sec = Math.max(0, parseInt(sec,10)||0); return String(Math.floor(sec/60)).padStart(2,'0') + ':' + String(sec%60).padStart(2,'0'); }
function defaultPayTimerText(){ return formatRemain(payWindowSeconds); }
function stopPayWatchers(){ if(payPollTimer){clearInterval(payPollTimer);payPollTimer=null;} if(payTickTimer){clearInterval(payTickTimer);payTickTimer=null;} }

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
    if(instantTimer){ instantTimer.textContent = defaultPayTimerText(); }
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
    instantTimer.textContent = formatRemain(item.remaining);
    if(item.status === 'processing'){
        instantPayHead.textContent = 'در حال آماده‌سازی';
        instantStatus.hidden = false;
        instantStatus.textContent = 'واریز دیده شد؛ در حال تمدید اشتراک…';
        instantApproved.hidden = true; return;
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
        instantStatus.textContent = item.message || 'خطا در تمدید';
        instantApproved.hidden = true; return;
    }
    instantPayHead.textContent = 'مهلت پرداخت';
    instantStatus.hidden = true;
    instantStatus.textContent = '';
    instantApproved.hidden = true;
    const restartBtn = document.getElementById('restartPayBtn');
    if(restartBtn) restartBtn.hidden = true;
}

function pollPayStatus(id){
    fetch('instant-pay-api.php?action=status&id=' + encodeURIComponent(id), { credentials:'same-origin' })
    .then(function(r){return r.json();})
    .then(function(data){ if(data && data.ok && data.item) renderPay(data.item); })
    .catch(function(){});
}

function startPayWatchers(id){
    stopPayWatchers();
    payTickTimer = setInterval(function(){
        if(!currentPay || currentPay.status !== 'waiting') return;
        currentPay.remaining = Math.max(0, (currentPay.remaining||0)-1);
        instantTimer.textContent = formatRemain(currentPay.remaining);
    }, 1000);
    payPollTimer = setInterval(function(){ pollPayStatus(id); }, 2000);
    pollPayStatus(id);
    document.addEventListener('visibilitychange', function(){
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
    const sub = document.getElementById('subInput').value.trim();

    if(!planSelect.value){
        showPayCreateError('ابتدا پلن را انتخاب کنید');
        return;
    }
    if(!card){
        showPayCreateError('کارت مقصد تعریف نشده است');
        return;
    }
    if(!sub){
        showPayCreateError('لینک اشتراک را وارد کنید');
        return;
    }
    if(!forceRestart && currentPay && (currentPay.status === 'waiting' || currentPay.status === 'processing')){
        const sameCard = String(currentPay.card || '') === card;
        const selectedPlan = planSelect.value.trim();
        const orderPlan = String(currentPay.plan_value || currentPay.plan || '').trim();
        if(sameCard && orderPlan === selectedPlan){
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
    if(instantPay){
        instantPay.hidden = false;
        if(instantAmount) instantAmount.textContent = '…';
        if(instantTimer) instantTimer.textContent = defaultPayTimerText();
        if(instantPayHead) instantPayHead.textContent = 'مهلت پرداخت';
        if(instantStatus){ instantStatus.hidden = true; instantStatus.textContent = ''; }
        if(instantApproved) instantApproved.hidden = true;
    }

    const prevId = currentPay && currentPay.id ? currentPay.id : '';
    stopPayWatchers();
    currentPay = null;

    const startCreate = function(){
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

const payGuideModal = document.getElementById('payGuideModal');
const payGuideText = document.getElementById('payGuideText');
const payGuideBtn = document.getElementById('payGuideBtn');
let payGuideStep = 1;
const payGuidePages = [
    '<b>مبلغ کپی شد</b><br><br>' +
    'توجه کنید مبلغ واریزی دقیقاً همین عدد ریالی باشد. نه کمتر، نه بیشتر، و آن را گرد نکنید.<br><br>' +
    'بعد از واریز، معمولاً ظرف چند ثانیه پرداخت تأیید می‌شود و اشتراک خودکار ساخته یا تمدید می‌شود.',
    'لازم نیست تا پایان فرآیند در این صفحه منتظر بمانید؛ اگر صفحه را ببندید هم مشکلی پیش نخواهد آمد و پرداخت شما تأیید خواهد شد.<br><br>' +
    'برای دیدن وضعیت خرید یا تمدید اشتراک، از داشبورد وارد بخش <b>اشتراک‌های من</b> شوید.'
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
    copyText(currentPay.amount, '', { silent: true }).then(function(){
        openPayGuide();
    });
});
document.getElementById('copyLinkBtn').addEventListener('click', function(){ copyText(resultLink.textContent.trim(), 'لینک کپی شد'); });

// Prefill from subscriptions list: renew.php?sub=LINK&name=CONFIG
(function prefillSubFromQuery(){
    var params = new URLSearchParams(window.location.search || '');
    var sub = (params.get('sub') || params.get('link') || '').trim();
    var name = (params.get('name') || '').trim();
    if(!sub && !name){
        syncCategoryLocks();
        return;
    }

    var select = document.getElementById('subSelect');
    var input = document.getElementById('subInput');
    if(!input) return;

    if(select && select.options && select.options.length){
        var found = -1;
        for(var i = 0; i < select.options.length; i++){
            var opt = select.options[i];
            var val = (opt.value || '').trim();
            var label = (opt.textContent || '').trim();
            if(sub && val && val.toLowerCase() === sub.toLowerCase()){ found = i; break; }
            if(name && label && label === name){ found = i; break; }
        }
        if(found >= 0){
            select.selectedIndex = found;
            pickSubscription();
            return;
        }
    }

    if(sub){
        input.value = sub;
        var matched = false;
        if(select){
            for(var j = 0; j < select.options.length; j++){
                var ov = (select.options[j].getAttribute('data-link') || select.options[j].value || '').trim();
                if(ov && ov.toLowerCase() === sub.toLowerCase()){
                    select.selectedIndex = j;
                    matched = true;
                    break;
                }
            }
            if(!matched){
                for(var k = 0; k < select.options.length; k++){
                    if(select.options[k].value === '__other__'){ select.selectedIndex = k; break; }
                }
            }
        }
        setSubInputMode(matched ? 'picked' : 'manual', sub);
        subTimeCategory = resolveSubTimeCategory(sub);
        syncCategoryLocks();
    }
})();
</script>
</body>
</html>
