<?php

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/admin_nav.php';
require_once __DIR__ . '/campaign_ui.php';
require_once __DIR__ . '/../campaign_lib.php';

pnvAdminRequireAuth();

$codes = campaignDiscountCodesLoad();
$flash = '';
$editId = trim((string)($_GET['edit'] ?? ''));
$editRow = null;

foreach($codes as $row){
    if(($row['id'] ?? '') === $editId){
        $editRow = $row;
        break;
    }
}

$q = trim((string)($_GET['q'] ?? ''));
$statusFilter = trim((string)($_GET['status'] ?? ''));

if(isset($_POST['save_discount'])){
    $id = trim((string)($_POST['id'] ?? ''));
    $code = campaignNormalizeCode($_POST['code'] ?? '');
    $type = ($_POST['type'] ?? '') === 'fixed' ? 'fixed' : 'percent';
    $value = max(0, intval($_POST['value'] ?? 0));
    $maxUses = max(0, intval($_POST['max_uses'] ?? 0));
    $perUserLimit = max(0, intval($_POST['per_user_limit'] ?? 0));
    $minimumTomans = max(0, intval($_POST['minimum_purchase_tomans'] ?? 0));
    $minimum = $minimumTomans > 0 ? (int)ceil($minimumTomans / 1000) : 0;
    $status = !empty($_POST['status_active']) ? 'active' : 'inactive';
    $startsAt = campaignParseDateTime($_POST['starts_at'] ?? '');
    $expiresAt = campaignParseDateTime($_POST['expires_at'] ?? '');
    $description = trim((string)($_POST['description'] ?? ''));
    $planFilterRaw = trim((string)($_POST['plan_filter'] ?? ''));
    $planFilter = [];

    if($planFilterRaw !== ''){
        foreach(preg_split('/[\r\n,]+/', $planFilterRaw) as $part){
            $part = trim($part);
            if($part !== ''){
                $planFilter[] = $part;
            }
        }
    }

    if($type === 'fixed'){
        $value = $value > 0 ? (int)ceil($value / 1000) : 0;
    }

    if($code === ''){
        $flash = 'کد الزامی است';
    }
    else{
        $codes = campaignDiscountCodesLoad();
        $duplicate = false;

        foreach($codes as $row){
            if(campaignNormalizeCode($row['code'] ?? '') === $code && ($row['id'] ?? '') !== $id){
                $duplicate = true;
                break;
            }
        }

        if($duplicate){
            $flash = 'این کد قبلاً ثبت شده';
        }
        else{
            $now = campaignNow();
            $payload = [
                'code' => $code,
                'type' => $type,
                'value' => $value,
                'max_uses' => $maxUses,
                'per_user_limit' => $perUserLimit,
                'minimum_purchase_amount' => $minimum,
                'plan_filter' => $planFilter,
                'description' => $description,
                'starts_at' => $startsAt,
                'expires_at' => $expiresAt,
                'status' => $status,
                'updated_at' => $now,
            ];

            if($id === ''){
                $payload['id'] = campaignNewId('dc');
                $payload['used_count'] = 0;
                $payload['created_at'] = $now;
                $codes[] = $payload;
            }
            else{
                $found = false;
                foreach($codes as $i => $row){
                    if(($row['id'] ?? '') === $id){
                        $payload['id'] = $id;
                        $payload['used_count'] = intval($row['used_count'] ?? 0);
                        $payload['created_at'] = intval($row['created_at'] ?? $now);
                        $codes[$i] = $payload;
                        $found = true;
                        break;
                    }
                }
                if(!$found){
                    $flash = 'کد پیدا نشد';
                }
            }

            if($flash === ''){
                campaignDiscountCodesSave($codes);
                header('Location: ' . pnvAdminUrl('campaign-discounts.php'));
                exit;
            }
        }
    }
}

if(isset($_GET['toggle'])){
    $toggleId = trim((string)$_GET['toggle']);
    $codes = campaignDiscountCodesLoad();
    foreach($codes as $i => $row){
        if(($row['id'] ?? '') === $toggleId){
            $codes[$i]['status'] = ($row['status'] ?? '') === 'active' ? 'inactive' : 'active';
            $codes[$i]['updated_at'] = campaignNow();
            break;
        }
    }
    campaignDiscountCodesSave($codes);
    header('Location: ' . pnvAdminUrl('campaign-discounts.php'));
    exit;
}

if(isset($_GET['delete'])){
    $deleteId = trim((string)$_GET['delete']);
    $codes = array_values(array_filter(campaignDiscountCodesLoad(), function($row) use ($deleteId){
        return ($row['id'] ?? '') !== $deleteId;
    }));
    campaignDiscountCodesSave($codes);
    header('Location: ' . pnvAdminUrl('campaign-discounts.php'));
    exit;
}

$codes = campaignDiscountCodesLoad();

if($q !== ''){
    $codes = array_values(array_filter($codes, function($row) use ($q){
        return stripos($row['code'] ?? '', $q) !== false;
    }));
}

if($statusFilter === 'active' || $statusFilter === 'inactive'){
    $codes = array_values(array_filter($codes, function($row) use ($statusFilter){
        return ($row['status'] ?? '') === $statusFilter;
    }));
}

usort($codes, function($a, $b){
    return intval($b['created_at'] ?? 0) <=> intval($a['created_at'] ?? 0);
});

function campaignDiscountTypeLabel($row){
    if(($row['type'] ?? '') === 'fixed'){
        return 'ثابت ' . number_format(intval($row['value'] ?? 0) * 1000) . ' تومان';
    }
    return intval($row['value'] ?? 0) . '٪ درصدی';
}

function campaignDiscountValidityText($row){
    $start = intval($row['starts_at'] ?? 0);
    $end = intval($row['expires_at'] ?? 0);

    if($start <= 0 && $end <= 0){
        return 'بدون محدودیت زمانی';
    }

    $parts = [];

    if($start > 0){
        $parts[] = 'از ' . campaignFormatDateTime($start);
    }

    if($end > 0){
        $parts[] = 'تا ' . campaignFormatDateTime($end);
    }

    return implode(' ', $parts);
}

$editMinimumTomans = intval($editRow['minimum_purchase_amount'] ?? 0) * 1000;
$editValue = intval($editRow['value'] ?? 0);

if(($editRow['type'] ?? '') === 'fixed'){
    $editValue = $editValue * 1000;
}

$isActive = ($editRow['status'] ?? 'active') === 'active';

?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>کدهای تخفیف</title>
<?php campaignAdminStyles(); campaignJalaliDatePickerHead(); ?>
</head>
<body class="campaignAdmin">
<div class="campaignShell">

<?php campaignAdminNav('discounts'); ?>

<div class="campaignCard">
<div class="campaignCardHead">
<h2 class="campaignCardTitle"><?php echo $editRow ? 'ویرایش کد تخفیف' : 'ایجاد کد تخفیف'; ?></h2>
<span class="campaignCardIcon"><?php echo campaignIconTicket(); ?></span>
</div>

<?php if($flash !== ''){ ?><div class="campaignFlash"><?php echo htmlspecialchars($flash, ENT_QUOTES, 'UTF-8'); ?></div><?php } ?>

<form method="POST" id="discountForm">
<input type="hidden" name="save_discount" value="1">
<input type="hidden" name="id" value="<?php echo htmlspecialchars($editRow['id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">

<div class="campaignSection">
<p class="campaignSectionTitle">اطلاعات اصلی</p>
<div class="campaignField">
<label class="campaignLabel">کد تخفیف</label>
<div class="campaignInputWrap">
<?php echo campaignIconTicket(); ?>
<input class="campaignInput" name="code" placeholder="SUMMER30" value="<?php echo htmlspecialchars($editRow['code'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
</div>
</div>
<div class="campaignGrid2">
<div class="campaignField">
<label class="campaignLabel">نوع تخفیف</label>
<select class="campaignSelect" name="type" id="discountType">
<option value="percent" <?php echo (($editRow['type'] ?? 'percent') === 'percent') ? 'selected' : ''; ?>>درصدی</option>
<option value="fixed" <?php echo (($editRow['type'] ?? '') === 'fixed') ? 'selected' : ''; ?>>مبلغ ثابت</option>
</select>
</div>
<div class="campaignField">
<label class="campaignLabel">مقدار تخفیف</label>
<div class="campaignInputWrap hasSuffix" id="valueWrap">
<span class="campaignSuffix" id="valueSuffix"><?php echo (($editRow['type'] ?? 'percent') === 'fixed') ? 'تومان' : '%'; ?></span>
<input class="campaignInput" name="value" id="discountValue" type="number" min="0" placeholder="مثال: 30" value="<?php echo $editValue > 0 ? (int)$editValue : ''; ?>" required>
</div>
</div>
</div>
</div>

<div class="campaignSection">
<p class="campaignSectionTitle">محدودیت‌های استفاده</p>
<div class="campaignGrid2">
<div class="campaignField">
<label class="campaignLabel">سقف استفاده</label>
<div class="campaignInputWrap">
<?php echo campaignIconUser(); ?>
<input class="campaignInput" name="max_uses" type="number" min="0" placeholder="مثال: 100" value="<?php echo (int)($editRow['max_uses'] ?? 0) ?: ''; ?>">
</div>
</div>
<div class="campaignField">
<label class="campaignLabel">حداقل خرید (تومان)</label>
<div class="campaignInputWrap">
<?php echo campaignIconMoney(); ?>
<input class="campaignInput" name="minimum_purchase_tomans" type="number" min="0" placeholder="مثال: 100000" value="<?php echo $editMinimumTomans > 0 ? (int)$editMinimumTomans : ''; ?>">
</div>
</div>
</div>
<div class="campaignField">
<label class="campaignLabel">محدودیت هر کاربر</label>
<div class="campaignInputWrap">
<?php echo campaignIconUser(); ?>
<input class="campaignInput" name="per_user_limit" type="number" min="0" placeholder="مثال: 1" value="<?php echo (int)($editRow['per_user_limit'] ?? 0) ?: ''; ?>">
</div>
<p class="campaignHint">۰ یا خالی یعنی بدون محدودیت برای هر کاربر</p>
</div>
</div>

<div class="campaignSection">
<p class="campaignSectionTitle">محدوده اعتبار</p>
<div class="campaignGrid2">
<div class="campaignField">
<label class="campaignLabel">تاریخ شروع (اختیاری)</label>
<div class="campaignInputWrap">
<?php echo campaignIconCalendar(); ?>
<?php campaignJalaliDateTimeInput('starts_at', $editRow['starts_at'] ?? 0); ?>
</div>
</div>
<div class="campaignField">
<label class="campaignLabel">تاریخ پایان (اختیاری)</label>
<div class="campaignInputWrap">
<?php echo campaignIconCalendar(); ?>
<?php campaignJalaliDateTimeInput('expires_at', $editRow['expires_at'] ?? 0); ?>
</div>
</div>
</div>
</div>

<div class="campaignSection">
<p class="campaignSectionTitle">تنظیمات</p>
<div class="campaignToggleRow">
<div class="campaignToggleText">کد تخفیف در حال حاضر فعال است</div>
<label class="campaignToggle">
<input type="checkbox" name="status_active" value="1" <?php echo $isActive ? 'checked' : ''; ?>>
<span class="campaignToggleTrack"></span>
</label>
</div>
<div class="campaignField" style="margin-top:10px">
<label class="campaignLabel">توضیحات (اختیاری)</label>
<textarea class="campaignTextarea" name="description" placeholder="توضیحات داخلی برای مدیریت"><?php echo htmlspecialchars($editRow['description'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
</div>
<input type="hidden" name="plan_filter" value="<?php echo htmlspecialchars(implode("\n", (array)($editRow['plan_filter'] ?? [])), ENT_QUOTES, 'UTF-8'); ?>">
</div>

<button class="campaignSubmit" type="submit"><?php echo $editRow ? 'ذخیره تغییرات' : '+ ایجاد کد تخفیف'; ?></button>
<?php if($editRow){ ?>
<a class="campaignBack" href="<?php echo htmlspecialchars(pnvAdminUrl('campaign-discounts.php'), ENT_QUOTES, 'UTF-8'); ?>">انصراف از ویرایش</a>
<?php } ?>
</form>
</div>

<div class="campaignCard">
<div class="campaignCardHead">
<h2 class="campaignCardTitle">لیست کدهای تخفیف</h2>
<span class="campaignCardIcon"><?php echo campaignIconList(); ?></span>
</div>

<form class="campaignSearchRow" method="GET" id="filterForm">
<div class="campaignSearchWrap">
<?php echo campaignIconSearch(); ?>
<input name="q" placeholder="جستجو در کدها..." value="<?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>">
</div>
<button class="campaignFilterBtn" type="button" id="filterToggleBtn">فیلتر</button>
</form>

<div class="campaignGrid2 campaignHidden" id="filterPanel" style="margin-bottom:12px">
<select class="campaignSelect" name="status" form="filterForm">
<option value="">همه وضعیت‌ها</option>
<option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>فعال</option>
<option value="inactive" <?php echo $statusFilter === 'inactive' ? 'selected' : ''; ?>>غیرفعال</option>
</select>
<button class="campaignFilterBtn" type="submit" form="filterForm">اعمال فیلتر</button>
</div>

<div class="campaignList" id="discountList">
<?php
$index = 0;
foreach($codes as $row){
    $index++;
    $hiddenClass = $index > 3 ? ' campaignHidden campaignListExtra' : '';
    $counts = campaignDiscountUsageCounts($row['id']);
    $maxUses = intval($row['max_uses'] ?? 0);
    $used = (int)$counts['confirmed'];
    $percent = ($maxUses > 0) ? min(100, (int)round(($used / $maxUses) * 100)) : 0;
    $useLabel = $maxUses > 0 ? ($used . ' / ' . $maxUses) : ($used . ' / ∞');
    $minTomans = intval($row['minimum_purchase_amount'] ?? 0) * 1000;
    $isRowActive = ($row['status'] ?? '') === 'active';
    $rowId = urlencode($row['id'] ?? '');
?>
<div class="campaignListItem<?php echo $hiddenClass; ?>">
<div class="campaignMenu">
<button type="button" class="campaignMenuBtn" data-menu-btn aria-label="عملیات">⋯</button>
<div class="campaignMenuPanel">
<a href="<?php echo htmlspecialchars(pnvAdminUrl('campaign-discounts.php?edit=' . $rowId), ENT_QUOTES, 'UTF-8'); ?>">ویرایش</a>
<a href="<?php echo htmlspecialchars(pnvAdminUrl('campaign-discounts.php?toggle=' . $rowId), ENT_QUOTES, 'UTF-8'); ?>">تغییر وضعیت</a>
<a class="is-danger" href="<?php echo htmlspecialchars(pnvAdminUrl('campaign-discounts.php?delete=' . $rowId), ENT_QUOTES, 'UTF-8'); ?>" onclick="return confirm('حذف شود؟');">حذف</a>
</div>
</div>
<div>
<div class="campaignItemTop">
<div>
<div class="campaignItemCode"><?php echo htmlspecialchars($row['code'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
<span class="campaignBadge <?php echo $isRowActive ? 'is-active' : 'is-inactive'; ?>"><?php echo $isRowActive ? 'فعال' : 'غیرفعال'; ?></span>
</div>
</div>
<div class="campaignItemType"><?php echo htmlspecialchars(campaignDiscountTypeLabel($row), ENT_QUOTES, 'UTF-8'); ?></div>
<div class="campaignProgressWrap">
<div class="campaignProgressMeta"><span>مصرف</span><span><?php echo htmlspecialchars($useLabel, ENT_QUOTES, 'UTF-8'); ?><?php echo $maxUses > 0 ? ' (' . $percent . '%)' : ''; ?></span></div>
<div class="campaignProgress"><div class="campaignProgressBar" style="width:<?php echo $maxUses > 0 ? $percent : min(100, $used * 10); ?>%"></div></div>
</div>
<div class="campaignItemMeta">
<div><strong>اعتبار</strong><?php echo htmlspecialchars(campaignDiscountValidityText($row), ENT_QUOTES, 'UTF-8'); ?></div>
<div><strong>حداقل خرید</strong><?php echo $minTomans > 0 ? number_format($minTomans) . ' تومان' : 'بدون محدودیت'; ?></div>
</div>
</div>
</div>
<?php } ?>
</div>

<?php if(count($codes) > 3){ ?>
<button type="button" class="campaignMoreBtn" id="showMoreBtn">
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 9l6 6 6-6"/></svg>
مشاهده بیشتر
</button>
<?php } ?>
</div>

<a class="campaignBack" href="<?php echo htmlspecialchars(pnvAdminUrl(), ENT_QUOTES, 'UTF-8'); ?>">بازگشت به داشبورد</a>
</div>

<script>
(function(){
    const typeEl = document.getElementById('discountType');
    const suffixEl = document.getElementById('valueSuffix');
    const valueEl = document.getElementById('discountValue');
    function syncType(){
        if(!typeEl || !suffixEl || !valueEl) return;
        const isFixed = typeEl.value === 'fixed';
        suffixEl.textContent = isFixed ? 'تومان' : '%';
        valueEl.placeholder = isFixed ? 'مثال: 100000' : 'مثال: 30';
    }
    if(typeEl){ typeEl.addEventListener('change', syncType); syncType(); }

    document.querySelectorAll('[data-menu-btn]').forEach(function(btn){
        btn.addEventListener('click', function(e){
            e.stopPropagation();
            const panel = btn.parentElement.querySelector('.campaignMenuPanel');
            document.querySelectorAll('.campaignMenuPanel.is-open').forEach(function(open){
                if(open !== panel) open.classList.remove('is-open');
            });
            if(panel) panel.classList.toggle('is-open');
        });
    });
    document.addEventListener('click', function(){
        document.querySelectorAll('.campaignMenuPanel.is-open').forEach(function(panel){
            panel.classList.remove('is-open');
        });
    });

    const filterBtn = document.getElementById('filterToggleBtn');
    const filterPanel = document.getElementById('filterPanel');
    if(filterBtn && filterPanel){
        filterBtn.addEventListener('click', function(){
            filterPanel.classList.toggle('campaignHidden');
        });
        <?php if($statusFilter !== ''){ ?>filterPanel.classList.remove('campaignHidden');<?php } ?>
    }

    const showMoreBtn = document.getElementById('showMoreBtn');
    if(showMoreBtn){
        showMoreBtn.addEventListener('click', function(){
            document.querySelectorAll('.campaignListExtra').forEach(function(item){
                item.classList.remove('campaignHidden');
            });
            showMoreBtn.classList.add('campaignHidden');
        });
    }
})();
</script>
<?php campaignJalaliDatePickerFoot(); ?>
</body>
</html>
