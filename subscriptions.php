<?php

session_start();

require_once "phpqrcode/qrlib.php";
require_once __DIR__ . '/subscription_lib.php';
require_once __DIR__ . '/sub_usage_lib.php';
require_once __DIR__ . '/plan_ui_lib.php';

if(!isset($_SESSION['user'])){
    header("Location: index.php");
    exit;
}

$user = $_SESSION['user'];

if(!file_exists("temp")){
    mkdir("temp", 0755, true);
}

$h = static function($v){
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
};

$activeSubs = pnvLoadUserActiveSubscriptions($user);
$items = [];
$i = 0;

foreach($activeSubs as $sub){
    $link = trim((string)($sub['link'] ?? ''));

    if($link === '' || !pnvIsValidSubLink($link)){
        continue;
    }

    $i++;
    $qrfile = 'temp/qr' . $i . '.png';
    QRcode::png($link, $qrfile, QR_ECLEVEL_L, 8);

    $items[] = [
        'i' => $i,
        'name' => trim((string)($sub['name'] ?? '')) !== '' ? trim((string)$sub['name']) : ('اشتراک ' . $i),
        'plan' => trim((string)($sub['plan_text'] ?? '')),
        'tracking' => trim((string)($sub['tracking'] ?? '')),
        'date' => trim((string)($sub['date'] ?? '')),
        'time' => trim((string)($sub['time'] ?? '')),
        'status' => 'تایید شد',
        'state' => 'ok',
        'link' => $link,
        'link_ok' => true,
        'link_cleared' => false,
        'qr' => $qrfile,
        'usage_key' => subUsageCacheKey($link),
    ];
}

$usageItems = [];
foreach($items as $item){
    $usageItems[] = [
        'link' => $item['link'],
        'plan' => $item['plan'],
        'date' => $item['date'],
        'time' => $item['time'],
    ];
}

$usageBundle = count($usageItems) > 0
    ? subUsageGetForItems($usageItems, max(4, min(12, count($usageItems))), true)
    : ['items' => []];
$usageMap = is_array($usageBundle['items'] ?? null) ? $usageBundle['items'] : [];

foreach($items as &$item){
    $key = $item['usage_key'] ?? subUsageCacheKey($item['link']);
    $item['usage'] = $usageMap[$key] ?? null;
    $hintEmail = is_array($item['usage']) ? trim((string)($item['usage']['email'] ?? '')) : '';
    $item['name'] = pnvEnsureSubDisplayName($user, $item['link'], $item['name'] ?? '', $hintEmail);
}
unset($item);

$firstOkOpen = true;

?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>اشتراک من</title>
<link rel="stylesheet" href="/fonts.css">
<link rel="stylesheet" href="user_bg.css?v=5">
<link rel="stylesheet" href="user_nav.css?v=1">
<link rel="stylesheet" href="subscriptions_ui.css?v=13">
</head>
<body>
<div class="box">

<div class="topBar">
<a class="userBack" href="dashboard.php">بازگشت</a>
<div class="brand">اشتراک من</div>
<div class="pageIcon" aria-hidden="true">
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
<path d="M4 7h16v12a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7z"/>
<path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
<path d="M9 12h6"/>
</svg>
</div>
</div>

<div class="subUsageNotice" role="note">
حجم و زمان باقیمانده هر <span class="subUsageNoticeNum">۱</span> دقیقه به‌روزرسانی می‌شود.
</div>

<div class="filters" role="tablist" aria-label="فیلتر وضعیت">
<button type="button" class="filterChip is-active" data-filter="main">همه</button>
<button type="button" class="filterChip" data-filter="active">فعال</button>
<button type="button" class="filterChip" data-filter="expired">منقضی</button>
</div>

<?php
$visibleItems = array_values(array_filter($items, static function($it){
    return ($it['state'] ?? '') === 'ok' && !empty($it['link_ok']);
}));
?>

<?php if(count($visibleItems) === 0){ ?>
<div class="empty">اشتراک فعالی برای نمایش نیست</div>
<?php } else { ?>
<div class="subList" id="subList">
<?php foreach($visibleItems as $item){
    $open = ($firstOkOpen);
    if($open){
        $firstOkOpen = false;
    }
    $chipClass = 'subChip is-ok';
    if($open){
        $chipClass .= ' is-open';
    }
    $usage = is_array($item['usage'] ?? null) ? $item['usage'] : null;
    $usageReady = is_array($usage) && !empty($usage['ok']);
    $usageVolLabel = $usageReady ? (string)(($usage['volume']['label'] ?? '') ?: '—') : 'در حال دریافت…';
    $usageTimeLabel = $usageReady ? (string)(($usage['time']['label'] ?? '') ?: '—') : 'در حال دریافت…';
    $usageVolPct = 0;
    $usageTimePct = 0;
    $usageVolLow = false;
    $usageTimeLow = false;
    $usageVolUnlimited = false;
    $usageTimeUnlimited = false;
    $usageExpired = false;
    $usageBoxClass = 'subUsage';

    if($usageReady){
        $usageBoxClass .= ' is-ready';
        $vol = is_array($usage['volume'] ?? null) ? $usage['volume'] : [];
        $time = is_array($usage['time'] ?? null) ? $usage['time'] : [];
        $usageVolUnlimited = !empty($vol['unlimited']);
        $usageTimeUnlimited = !empty($time['unlimited']);
        $usageVolPct = $usageVolUnlimited ? 100 : max(0, min(100, floatval($vol['remain_pct'] ?? 0)));
        $usageTimePct = $usageTimeUnlimited ? 100 : max(0, min(100, floatval($time['remain_pct'] ?? 0)));
        $usageVolLow = !$usageVolUnlimited && $usageVolPct <= 15;
        $usageTimeLow = !$usageTimeUnlimited && $usageTimePct <= 15;
        $usageExpired = subUsageIsDisplayExpired($usage);

        if(!$usageVolUnlimited && $usageVolPct <= 0.05 && ($usage['source'] ?? '') === 'panel'){
            $usageVolLabel = 'حجم تمام شده';
        }

        if(!$usageTimeUnlimited && $usageTimePct <= 0.05 && $usageVolPct <= 5 && ($usage['source'] ?? '') === 'panel'){
            $usageTimeLabel = 'زمان تمام شده';
        }
    }
    else{
        $usageBoxClass .= ' is-loading';
    }

    if($usageExpired){
        $chipClass .= ' is-expired';
    }

    $lifeState = $usageExpired ? 'expired' : 'active';
    $lifeTagHidden = $usageExpired ? '' : ' hidden';
    $lifeTagText = $usageExpired ? 'منقضی شده — با تمدید فعال می‌شود' : 'منقضی شده';
    $badgeText = $usageExpired ? '!' : '✓';
?>
<article class="<?php echo $h($chipClass); ?>" data-state="ok" data-life="<?php echo $h($lifeState); ?>" data-id="<?php echo (int)$item['i']; ?>" data-link="<?php echo $h($item['link']); ?>">
<button type="button" class="subHead" aria-expanded="<?php echo $open ? 'true' : 'false'; ?>">
<span class="subBadge" aria-hidden="true"><?php echo $h($badgeText); ?></span>
<span class="subMeta">
<span class="subName"><?php echo $h($item['name']); ?></span>
<span class="subLifeTag" data-life-tag<?php echo $lifeTagHidden; ?>><?php echo $h($lifeTagText); ?></span>
</span>
<svg class="subChevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
<path d="M6 9l6 6 6-6"/>
</svg>
</button>

<div class="<?php echo $h($usageBoxClass); ?>" data-usage-link="<?php echo $h($item['link']); ?>">
<div class="usageRow">
<div class="usageLabels">
<span class="usageKind usageKind--vol">حجم باقی‌مانده</span>
<span class="usageVal" data-usage-vol-label><?php echo $h($usageVolLabel); ?></span>
</div>
<div class="usageTrack" aria-hidden="true">
<div class="usageFill usageFill--vol<?php echo $usageVolLow ? ' is-low' : ''; ?>" data-usage-vol-fill style="width:<?php echo $h((string)$usageVolPct); ?>%"></div>
</div>
</div>
<div class="usageRow">
<div class="usageLabels">
<span class="usageKind usageKind--time">زمان باقی‌مانده</span>
<span class="usageVal" data-usage-time-label><?php echo $h($usageTimeLabel); ?></span>
</div>
<div class="usageTrack" aria-hidden="true">
<div class="usageFill usageFill--time<?php echo $usageTimeLow ? ' is-low' : ''; ?>" data-usage-time-fill style="width:<?php echo $h((string)$usageTimePct); ?>%"></div>
</div>
</div>
</div>

<div class="subBody">
<div class="subBodyInner">
<div class="subQrCol">
<button type="button" class="subQrBtn" data-qr="<?php echo $h($item['qr']); ?>" data-name="<?php echo $h($item['name']); ?>" aria-label="نمایش QR Code بزرگ">
<img src="<?php echo $h($item['qr']); ?>" alt="QR Code">
</button>
<div class="subQrHint">برای بزرگ‌نمایی بزنید</div>
</div>
<div class="subLinkCol">
<div class="subLinkTitle">لینک اشتراک</div>
<div class="subLinkHint">برای استفاده، لینک را کپی کنید</div>
<div class="subLinkHidden" id="sub<?php echo (int)$item['i']; ?>"><?php echo $h($item['link']); ?></div>
<button type="button" class="btnPrimary copyBtn" data-copy="sub<?php echo (int)$item['i']; ?>">
<svg class="btnIcon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15V5a2 2 0 0 1 2-2h10"/></svg>
کپی لینک
</button>
</div>
</div>
<a class="btnRenew" href="renew.php?sub=<?php echo rawurlencode($item['link']); ?>&amp;name=<?php echo rawurlencode($item['name']); ?>">
تمدید این اشتراک
</a>
<div class="subFoot">
<?php
$foot = [];
if($item['tracking'] !== '') $foot[] = 'پیگیری: ' . $item['tracking'];
if($item['time'] !== '') $foot[] = $item['time'];
echo $h(implode(' • ', $foot));
?>
</div>
</div>
</article>
<?php } ?>
</div>
<?php } ?>

</div>

<div class="toast" id="toast" role="status" aria-live="polite">لینک اشتراک کپی شد</div>

<div class="qrModal" id="qrModal" aria-hidden="true">
<div class="qrModalBackdrop" data-close="1"></div>
<div class="qrModalPanel" role="dialog" aria-modal="true" aria-labelledby="qrModalTitle">
<div class="qrModalTitle" id="qrModalTitle">اسکن QR Code</div>
<p class="qrModalName" id="qrModalName"></p>
<div class="qrModalFrame">
<img id="qrModalImg" src="" alt="QR Code بزرگ">
</div>
<button type="button" class="btnGhost qrModalClose" data-close="1">بستن</button>
</div>
</div>

<script>
window.__subUsageInitial = <?php echo json_encode($usageMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
(function(){
    var initialUsage = window.__subUsageInitial || {};
    var list = document.getElementById('subList');
    var toast = document.getElementById('toast');
    var modal = document.getElementById('qrModal');
    var modalImg = document.getElementById('qrModalImg');
    var modalName = document.getElementById('qrModalName');
    var toastTimer = null;

    function showToast(msg){
        if(!toast) return;
        toast.textContent = msg || 'لینک اشتراک کپی شد';
        toast.classList.add('is-show');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(function(){ toast.classList.remove('is-show'); }, 1800);
    }

    function openModal(src, name){
        if(!modal || !modalImg) return;
        modalImg.src = src;
        if(modalName) modalName.textContent = name || '';
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    }

    function closeModal(){
        if(!modal) return;
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }

    function currentFilter(){
        var active = document.querySelector('.filterChip.is-active');
        return active ? (active.getAttribute('data-filter') || 'main') : 'main';
    }

    function applyListFilter(){
        var f = currentFilter();
        document.querySelectorAll('.subChip').forEach(function(item){
            var life = item.getAttribute('data-life') || 'active';
            var show = true;
            if(f === 'active') show = life === 'active';
            else if(f === 'expired') show = life === 'expired';
            else show = (life === 'active' || life === 'expired'); // main: بدون ردشده
            item.hidden = !show;
        });
    }

    document.querySelectorAll('.filterChip').forEach(function(chip){
        chip.addEventListener('click', function(){
            document.querySelectorAll('.filterChip').forEach(function(c){ c.classList.remove('is-active'); });
            chip.classList.add('is-active');
            applyListFilter();
        });
    });
    applyListFilter();

    if(list){
        list.addEventListener('click', function(e){
            var head = e.target.closest('.subHead');
            if(head && list.contains(head)){
                var chip = head.closest('.subChip');
                if(!chip) return;
                var willOpen = !chip.classList.contains('is-open');
                list.querySelectorAll('.subChip.is-open').forEach(function(other){
                    other.classList.remove('is-open');
                    var h2 = other.querySelector('.subHead');
                    if(h2) h2.setAttribute('aria-expanded', 'false');
                });
                if(willOpen){
                    chip.classList.add('is-open');
                    head.setAttribute('aria-expanded', 'true');
                }
                return;
            }

            var qrBtn = e.target.closest('.subQrBtn');
            if(qrBtn && list.contains(qrBtn)){
                openModal(qrBtn.getAttribute('data-qr') || '', qrBtn.getAttribute('data-name') || '');
                return;
            }

            var copyBtn = e.target.closest('.copyBtn');
            if(copyBtn && list.contains(copyBtn)){
                var id = copyBtn.getAttribute('data-copy');
                var el = id ? document.getElementById(id) : null;
                var text = el ? (el.textContent || '').trim() : '';
                if(!text) return;
                if(navigator.clipboard && navigator.clipboard.writeText){
                    navigator.clipboard.writeText(text).then(function(){ showToast('لینک اشتراک کپی شد'); })
                        .catch(function(){ fallbackCopy(text); });
                } else {
                    fallbackCopy(text);
                }
            }
        });
    }

    function fallbackCopy(text){
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.setAttribute('readonly', '');
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.select();
        try{ document.execCommand('copy'); showToast('لینک اشتراک کپی شد'); }
        catch(err){ showToast('کپی نشد؛ لینک را دستی بردارید'); }
        document.body.removeChild(ta);
    }

    if(modal){
        modal.addEventListener('click', function(e){
            if(e.target && e.target.getAttribute('data-close') === '1') closeModal();
        });
    }
    document.addEventListener('keydown', function(e){
        if(e.key === 'Escape') closeModal();
    });

    function usageKeyFromLink(link){
        try{
            var u = new URL(link, window.location.origin);
            var host = (u.hostname || '').toLowerCase();
            var parts = (u.pathname || '').split('/');
            var subId = parts[parts.length - 1] || '';
            if(host && subId) return host + '|' + subId;
        }catch(err){}
        return '';
    }

    function setChipLife(chip, expired){
        if(!chip) return;
        chip.classList.toggle('is-expired', !!expired);
        chip.setAttribute('data-life', expired ? 'expired' : 'active');
        var tag = chip.querySelector('[data-life-tag]');
        if(tag){
            tag.hidden = !expired;
            tag.textContent = expired ? 'منقضی شده — با تمدید فعال می‌شود' : '';
        }
        var badge = chip.querySelector('.subBadge');
        if(badge) badge.textContent = expired ? '!' : '✓';
    }

    function applyUsage(box, row){
        if(!box) return;
        box.classList.remove('is-loading');

        var chip = box.closest('.subChip');
        var volFill = box.querySelector('[data-usage-vol-fill]');
        var volLabel = box.querySelector('[data-usage-vol-label]');
        var timeFill = box.querySelector('[data-usage-time-fill]');
        var timeLabel = box.querySelector('[data-usage-time-label]');

        if(!row || !row.ok){
            box.classList.add('is-error');
            if(volLabel) volLabel.textContent = 'نامشخص';
            if(timeLabel) timeLabel.textContent = 'نامشخص';
            if(volFill) volFill.style.width = '0%';
            if(timeFill) timeFill.style.width = '0%';
            // بدون داده، فعال فرض می‌شود تا از لیست حذف نشود
            setChipLife(chip, false);
            applyListFilter();
            return;
        }

        box.classList.remove('is-error');
        var vol = row.volume || {};
        var time = row.time || {};
        var volPct = vol.unlimited ? 100 : Math.max(0, Math.min(100, Number(vol.remain_pct || 0)));
        var timePct = time.unlimited ? 100 : Math.max(0, Math.min(100, Number(time.remain_pct || 0)));
        var volGone = !vol.unlimited && volPct <= 0.05;
        var timeCounts = !time.unlimited && !time.estimated;
        var timeGone = timeCounts && timePct <= 0.05;
        var fromPanel = row.source === 'panel';
        var expired = fromPanel && (volGone || (timeGone && volPct <= 5));

        if(volFill){
            volFill.style.width = volPct + '%';
            volFill.classList.toggle('is-low', !vol.unlimited && volPct <= 15);
        }
        if(timeFill){
            timeFill.style.width = timePct + '%';
            timeFill.classList.toggle('is-low', !time.unlimited && timePct <= 15);
        }
        if(volLabel) volLabel.textContent = vol.label || '—';
        if(timeLabel){
            if(timeGone && volPct <= 5) timeLabel.textContent = 'زمان تمام شده';
            else if(volGone && !time.unlimited) timeLabel.textContent = time.label || '—';
            else timeLabel.textContent = time.label || '—';
        }
        if(volGone && fromPanel && volLabel) volLabel.textContent = 'حجم تمام شده';

        box.classList.toggle('is-unlimited-time', !!time.unlimited);
        box.classList.toggle('is-unlimited-vol', !!vol.unlimited);
        setChipLife(chip, expired);
        applyListFilter();
    }

    function loadUsage(attempt, forceRefresh){
        attempt = attempt || 0;
        forceRefresh = !!forceRefresh;
        var boxes = Array.prototype.slice.call(document.querySelectorAll('[data-usage-link]'));
        if(!boxes.length) return;

        boxes.forEach(function(box){
            var link = box.getAttribute('data-usage-link') || '';
            var key = usageKeyFromLink(link);
            var row = (key && initialUsage[key]) ? initialUsage[key] : null;
            if(!row){
                Object.keys(initialUsage).forEach(function(k){
                    if(initialUsage[k] && initialUsage[k].link === link){
                        row = initialUsage[k];
                    }
                });
            }
            if(row && !row.pending){
                applyUsage(box, row);
            }
        });

        var links = boxes.map(function(b){ return b.getAttribute('data-usage-link'); }).filter(Boolean);

        var payload = {
            links: links,
            max_fresh: Math.min(Math.max(links.length, 1), 8)
        };
        if(forceRefresh){
            payload.force = true;
        }

        fetch('sub-usage-api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            credentials: 'same-origin',
            body: JSON.stringify(payload)
        }).then(function(r){ return r.json(); }).then(function(data){
            if(!data || !data.ok){
                boxes.forEach(function(b){ applyUsage(b, null); });
                return;
            }
            var map = data.items || {};
            boxes.forEach(function(box){
                var link = box.getAttribute('data-usage-link') || '';
                var key = usageKeyFromLink(link);
                var row = (key && map[key]) ? map[key] : null;
                if(!row){
                    // fallback: scan values
                    Object.keys(map).forEach(function(k){
                        if(map[k] && map[k].link === link) row = map[k];
                    });
                }
                if(row && row.pending){
                    return; // keep loading
                }
                applyUsage(box, row);
            });

            if((data.pending || 0) > 0 && attempt < 6){
                setTimeout(function(){ loadUsage(attempt + 1, forceRefresh); }, 900);
            }
        }).catch(function(){
            if(attempt < 2){
                setTimeout(function(){ loadUsage(attempt + 1, forceRefresh); }, 1200);
                return;
            }
            boxes.forEach(function(b){ applyUsage(b, null); });
        });
    }

    if(document.querySelector('[data-usage-link]')){
        loadUsage(0, true);
        setInterval(function(){
            if(document.hidden){
                return;
            }
            loadUsage(0, true);
        }, 60000);
    }
})();
</script>
</body>
</html>
