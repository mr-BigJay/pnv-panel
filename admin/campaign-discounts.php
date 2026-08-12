<?php

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/admin_nav.php';
require_once __DIR__ . '/../campaign_lib.php';

pnvAdminRequireAuth();

$codes = campaignDiscountCodesLoad();
$flash = '';
$editId = trim((string)($_GET['edit'] ?? ''));
$editRow = $editId !== '' ? campaignFindDiscountById($codes, $editId) : null;
$q = trim((string)($_GET['q'] ?? ''));
$statusFilter = trim((string)($_GET['status'] ?? ''));

if(isset($_POST['save_discount'])){
    $id = trim((string)($_POST['id'] ?? ''));
    $code = campaignNormalizeCode($_POST['code'] ?? '');
    $type = ($_POST['type'] ?? '') === 'fixed' ? 'fixed' : 'percent';
    $value = max(0, intval($_POST['value'] ?? 0));
    $maxUses = max(0, intval($_POST['max_uses'] ?? 0));
    $perUserLimit = max(0, intval($_POST['per_user_limit'] ?? 0));
    $minimum = max(0, intval($_POST['minimum_purchase_amount'] ?? 0));
    $status = ($_POST['status'] ?? '') === 'inactive' ? 'inactive' : 'active';
    $startsAt = campaignParseDateTime($_POST['starts_at'] ?? '');
    $expiresAt = campaignParseDateTime($_POST['expires_at'] ?? '');
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

function campaignAdminSharedStyles(){
    echo '<style>
*{box-sizing:border-box}body{margin:0;padding:20px;background:#0f172a;font-family:tahoma;direction:rtl;color:#fff}
.container{max-width:1100px;margin:auto}.box{background:#1e293b;padding:20px;border-radius:20px;margin-bottom:20px}
h2{margin-top:0;margin-bottom:16px;font-size:24px}.campNav{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:16px}
.campNav a{display:inline-flex;padding:8px 12px;border-radius:10px;background:#334155;color:#fff;text-decoration:none;font-size:13px}
.campNav a.is-active{background:#22c55e;color:#052e16;font-weight:700}
.formgrid{display:grid;grid-template-columns:1fr 1fr;gap:12px}.formgrid--3{grid-template-columns:repeat(3,minmax(0,1fr))}
input,select,textarea{width:100%;padding:12px;border:none;border-radius:12px;background:#0f172a;color:#fff;font-family:tahoma;font-size:14px}
textarea{min-height:80px;resize:vertical}button,.btn{display:inline-flex;align-items:center;justify-content:center;padding:12px 16px;border:none;border-radius:12px;background:#22c55e;color:#052e16;font-family:tahoma;font-size:14px;cursor:pointer;text-decoration:none}
.btn--muted{background:#334155;color:#fff}.btn--danger{background:#dc2626;color:#fff}
.tablebox{overflow-x:auto}table{width:100%;border-collapse:collapse;min-width:860px}th,td{padding:12px;text-align:center;font-size:13px;border-top:1px solid #334155}
th{background:#334155}.badge{display:inline-flex;padding:4px 10px;border-radius:999px;font-size:11px;background:#334155}
.badge.is-on{background:#14532d;color:#bbf7d0}.badge.is-off{background:#475569;color:#e2e8f0}
.flash{background:#713f12;color:#fde68a;padding:12px;border-radius:12px;margin-bottom:12px}.toolbar{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:12px}
.back{display:block;margin-top:20px;background:#334155;padding:14px;border-radius:14px;text-align:center;color:#fff;text-decoration:none}
@media(max-width:768px){body{padding:10px}.formgrid,.formgrid--3{grid-template-columns:1fr}table{min-width:760px}}
</style>';
}

function campaignInputDateTimeLocal($ts){
    $ts = intval($ts);
    if($ts <= 0){ return ''; }
    return date('Y-m-d\TH:i', $ts);
}

?>
<!DOCTYPE html>
<html lang="fa">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>کدهای تخفیف</title>
<?php campaignAdminSharedStyles(); ?>
</head>
<body>
<div class="container">

<nav class="campNav">
<a href="<?php echo htmlspecialchars(pnvAdminUrl('campaigns.php'), ENT_QUOTES, 'UTF-8'); ?>">نمای کلی</a>
<a class="is-active" href="<?php echo htmlspecialchars(pnvAdminUrl('campaign-discounts.php'), ENT_QUOTES, 'UTF-8'); ?>">کدهای تخفیف</a>
<a href="<?php echo htmlspecialchars(pnvAdminUrl('campaign-announcements.php'), ENT_QUOTES, 'UTF-8'); ?>">پیام‌های داشبورد</a>
</nav>

<div class="box">
<h2><?php echo $editRow ? 'ویرایش کد تخفیف' : 'ایجاد کد تخفیف'; ?></h2>
<?php if($flash !== ''){ ?><div class="flash"><?php echo htmlspecialchars($flash, ENT_QUOTES, 'UTF-8'); ?></div><?php } ?>
<form method="POST">
<input type="hidden" name="save_discount" value="1">
<input type="hidden" name="id" value="<?php echo htmlspecialchars($editRow['id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
<div class="formgrid">
<input name="code" placeholder="کد (مثلاً SUMMER30)" value="<?php echo htmlspecialchars($editRow['code'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
<select name="type">
<option value="percent" <?php echo (($editRow['type'] ?? 'percent') === 'percent') ? 'selected' : ''; ?>>درصدی</option>
<option value="fixed" <?php echo (($editRow['type'] ?? '') === 'fixed') ? 'selected' : ''; ?>>مبلغ ثابت (هزار تومان)</option>
</select>
<input name="value" type="number" min="0" placeholder="مقدار (30 یا 150)" value="<?php echo (int)($editRow['value'] ?? 0); ?>" required>
<input name="max_uses" type="number" min="0" placeholder="حداکثر استفاده (0=نامحدود)" value="<?php echo (int)($editRow['max_uses'] ?? 0); ?>">
<input name="per_user_limit" type="number" min="0" placeholder="هر کاربر (0=نامحدود)" value="<?php echo (int)($editRow['per_user_limit'] ?? 0); ?>">
<input name="minimum_purchase_amount" type="number" min="0" placeholder="حداقل مبلغ خرید (هزار تومان)" value="<?php echo (int)($editRow['minimum_purchase_amount'] ?? 0); ?>">
<select name="status">
<option value="active" <?php echo (($editRow['status'] ?? 'active') === 'active') ? 'selected' : ''; ?>>فعال</option>
<option value="inactive" <?php echo (($editRow['status'] ?? '') === 'inactive') ? 'selected' : ''; ?>>غیرفعال</option>
</select>
<input name="starts_at" type="datetime-local" value="<?php echo htmlspecialchars(campaignInputDateTimeLocal($editRow['starts_at'] ?? 0), ENT_QUOTES, 'UTF-8'); ?>">
<input name="expires_at" type="datetime-local" value="<?php echo htmlspecialchars(campaignInputDateTimeLocal($editRow['expires_at'] ?? 0), ENT_QUOTES, 'UTF-8'); ?>">
</div>
<textarea name="plan_filter" placeholder="محدودیت پلن (اختیاری) — هر خط یک نام پلن"><?php echo htmlspecialchars(implode("\n", (array)($editRow['plan_filter'] ?? [])), ENT_QUOTES, 'UTF-8'); ?></textarea>
<div style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap">
<button type="submit"><?php echo $editRow ? 'ذخیره تغییرات' : '+ ایجاد کد تخفیف'; ?></button>
<?php if($editRow){ ?><a class="btn btn--muted" href="<?php echo htmlspecialchars(pnvAdminUrl('campaign-discounts.php'), ENT_QUOTES, 'UTF-8'); ?>">انصراف</a><?php } ?>
</div>
</form>
</div>

<div class="box">
<h2>لیست کدهای تخفیف</h2>
<form class="toolbar" method="GET">
<input name="q" placeholder="جستجوی کد..." value="<?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>">
<select name="status">
<option value="">همه وضعیت‌ها</option>
<option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>فعال</option>
<option value="inactive" <?php echo $statusFilter === 'inactive' ? 'selected' : ''; ?>>غیرفعال</option>
</select>
<button type="submit">فیلتر</button>
</form>
<div class="tablebox">
<table>
<tr>
<th>کد</th><th>نوع</th><th>مقدار</th><th>استفاده</th><th>اعتبار</th><th>وضعیت</th><th>عملیات</th>
</tr>
<?php foreach($codes as $row){
    $counts = campaignDiscountUsageCounts($row['id']);
    $maxUses = intval($row['max_uses'] ?? 0);
    $useText = (int)$counts['confirmed'];
    if($maxUses > 0){ $useText .= ' / ' . $maxUses; }
    if($counts['pending'] > 0){ $useText .= ' (' . (int)$counts['pending'] . ' رزرو)'; }
    $valueText = (($row['type'] ?? '') === 'fixed') ? number_format((int)($row['value'] ?? 0)) . ' هزار' : (int)($row['value'] ?? 0) . '٪';
?>
<tr>
<td><strong><?php echo htmlspecialchars($row['code'] ?? '', ENT_QUOTES, 'UTF-8'); ?></strong></td>
<td><?php echo ($row['type'] ?? '') === 'fixed' ? 'ثابت' : 'درصدی'; ?></td>
<td><?php echo htmlspecialchars($valueText, ENT_QUOTES, 'UTF-8'); ?></td>
<td><?php echo htmlspecialchars($useText, ENT_QUOTES, 'UTF-8'); ?></td>
<td><?php echo htmlspecialchars(campaignFormatDateTime($row['starts_at'] ?? 0), ENT_QUOTES, 'UTF-8'); ?><br>تا <?php echo htmlspecialchars(campaignFormatDateTime($row['expires_at'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></td>
<td><span class="badge <?php echo ($row['status'] ?? '') === 'active' ? 'is-on' : 'is-off'; ?>"><?php echo ($row['status'] ?? '') === 'active' ? 'فعال' : 'غیرفعال'; ?></span></td>
<td>
<a class="btn btn--muted" href="<?php echo htmlspecialchars(pnvAdminUrl('campaign-discounts.php?edit=' . urlencode($row['id'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>">ویرایش</a>
<a class="btn btn--muted" href="<?php echo htmlspecialchars(pnvAdminUrl('campaign-discounts.php?toggle=' . urlencode($row['id'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>">تغییر وضعیت</a>
<a class="btn btn--danger" href="<?php echo htmlspecialchars(pnvAdminUrl('campaign-discounts.php?delete=' . urlencode($row['id'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>" onclick="return confirm('حذف شود؟');">حذف</a>
</td>
</tr>
<?php } ?>
</table>
</div>
</div>

<a class="back" href="<?php echo htmlspecialchars(pnvAdminUrl('campaigns.php'), ENT_QUOTES, 'UTF-8'); ?>">بازگشت</a>
</div>
</body>
</html>
