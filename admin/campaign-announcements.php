<?php

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/admin_nav.php';
require_once __DIR__ . '/../campaign_lib.php';

pnvAdminRequireAuth();

$rows = campaignAnnouncementsLoad();
$flash = '';
$editId = trim((string)($_GET['edit'] ?? ''));
$editRow = $editId !== '' ? null : null;
foreach($rows as $row){
    if(($row['id'] ?? '') === $editId){
        $editRow = $row;
        break;
    }
}

if(isset($_POST['save_announcement'])){
    $id = trim((string)($_POST['id'] ?? ''));
    $title = trim((string)($_POST['title'] ?? ''));
    $message = trim((string)($_POST['message'] ?? ''));
    $type = trim((string)($_POST['type'] ?? 'info'));
    $priority = max(0, intval($_POST['priority'] ?? 100));
    $status = ($_POST['status'] ?? '') === 'inactive' ? 'inactive' : 'active';
    $startsAt = campaignParseDateTime($_POST['starts_at'] ?? '');
    $expiresAt = campaignParseDateTime($_POST['expires_at'] ?? '');

    if(!in_array($type, ['info', 'success', 'warning', 'special'], true)){
        $type = 'info';
    }

    if($title === '' || $message === ''){
        $flash = 'عنوان و متن پیام الزامی است';
    }
    else{
        $rows = campaignAnnouncementsLoad();
        $now = campaignNow();
        $payload = [
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'priority' => $priority,
            'starts_at' => $startsAt,
            'expires_at' => $expiresAt,
            'status' => $status,
            'updated_at' => $now,
        ];

        if($id === ''){
            $payload['id'] = campaignNewId('da');
            $payload['created_at'] = $now;
            $rows[] = $payload;
        }
        else{
            $found = false;
            foreach($rows as $i => $row){
                if(($row['id'] ?? '') === $id){
                    $payload['id'] = $id;
                    $payload['created_at'] = intval($row['created_at'] ?? $now);
                    $rows[$i] = $payload;
                    $found = true;
                    break;
                }
            }
            if(!$found){
                $flash = 'پیام پیدا نشد';
            }
        }

        if($flash === ''){
            campaignAnnouncementsSave($rows);
            header('Location: ' . pnvAdminUrl('campaign-announcements.php'));
            exit;
        }
    }
}

if(isset($_GET['toggle'])){
    $toggleId = trim((string)$_GET['toggle']);
    $rows = campaignAnnouncementsLoad();
    foreach($rows as $i => $row){
        if(($row['id'] ?? '') === $toggleId){
            $rows[$i]['status'] = ($row['status'] ?? '') === 'active' ? 'inactive' : 'active';
            $rows[$i]['updated_at'] = campaignNow();
            break;
        }
    }
    campaignAnnouncementsSave($rows);
    header('Location: ' . pnvAdminUrl('campaign-announcements.php'));
    exit;
}

if(isset($_GET['delete'])){
    $deleteId = trim((string)$_GET['delete']);
    $rows = array_values(array_filter(campaignAnnouncementsLoad(), function($row) use ($deleteId){
        return ($row['id'] ?? '') !== $deleteId;
    }));
    campaignAnnouncementsSave($rows);
    header('Location: ' . pnvAdminUrl('campaign-announcements.php'));
    exit;
}

$rows = campaignAnnouncementsLoad();
usort($rows, function($a, $b){
    return intval($b['created_at'] ?? 0) <=> intval($a['created_at'] ?? 0);
});

function campaignInputDateTimeLocal($ts){
    $ts = intval($ts);
    if($ts <= 0){ return ''; }
    return date('Y-m-d\TH:i', $ts);
}

function campaignAdminSharedStyles(){
    echo '<style>
*{box-sizing:border-box}body{margin:0;padding:20px;background:#0f172a;font-family:tahoma;direction:rtl;color:#fff}
.container{max-width:1100px;margin:auto}.box{background:#1e293b;padding:20px;border-radius:20px;margin-bottom:20px}
h2{margin-top:0;margin-bottom:16px;font-size:24px}.campNav{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:16px}
.campNav a{display:inline-flex;padding:8px 12px;border-radius:10px;background:#334155;color:#fff;text-decoration:none;font-size:13px}
.campNav a.is-active{background:#22c55e;color:#052e16;font-weight:700}
.formgrid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
input,select,textarea{width:100%;padding:12px;border:none;border-radius:12px;background:#0f172a;color:#fff;font-family:tahoma;font-size:14px}
textarea{min-height:120px;resize:vertical}button,.btn{display:inline-flex;align-items:center;justify-content:center;padding:12px 16px;border:none;border-radius:12px;background:#22c55e;color:#052e16;font-family:tahoma;font-size:14px;cursor:pointer;text-decoration:none}
.btn--muted{background:#334155;color:#fff}.btn--danger{background:#dc2626;color:#fff}
.tablebox{overflow-x:auto}table{width:100%;border-collapse:collapse;min-width:760px}th,td{padding:12px;text-align:center;font-size:13px;border-top:1px solid #334155}
th{background:#334155}.badge{display:inline-flex;padding:4px 10px;border-radius:999px;font-size:11px;background:#334155}
.badge.is-on{background:#14532d;color:#bbf7d0}.badge.is-off{background:#475569}
.previewBox{margin-top:12px;padding:14px;border-radius:14px;background:#0f172a;border:1px solid #334155;text-align:right;line-height:1.9}
.previewBox.is-info{border-color:#38bdf8}.previewBox.is-success{border-color:#22c55e}.previewBox.is-warning{border-color:#f59e0b}.previewBox.is-special{border-color:#a855f7}
.back{display:block;margin-top:20px;background:#334155;padding:14px;border-radius:14px;text-align:center;color:#fff;text-decoration:none}
@media(max-width:768px){body{padding:10px}.formgrid{grid-template-columns:1fr}}
</style>';
}

$previewType = $editRow['type'] ?? 'info';

?>
<!DOCTYPE html>
<html lang="fa">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>پیام‌های داشبورد</title>
<?php campaignAdminSharedStyles(); ?>
</head>
<body>
<div class="container">

<nav class="campNav">
<a href="<?php echo htmlspecialchars(pnvAdminUrl('campaigns.php'), ENT_QUOTES, 'UTF-8'); ?>">نمای کلی</a>
<a href="<?php echo htmlspecialchars(pnvAdminUrl('campaign-discounts.php'), ENT_QUOTES, 'UTF-8'); ?>">کدهای تخفیف</a>
<a class="is-active" href="<?php echo htmlspecialchars(pnvAdminUrl('campaign-announcements.php'), ENT_QUOTES, 'UTF-8'); ?>">پیام‌های داشبورد</a>
</nav>

<div class="box">
<h2><?php echo $editRow ? 'ویرایش پیام' : 'ایجاد پیام داشبورد'; ?></h2>
<?php if($flash !== ''){ ?><div class="flash"><?php echo htmlspecialchars($flash, ENT_QUOTES, 'UTF-8'); ?></div><?php } ?>
<form method="POST" id="announcementForm">
<input type="hidden" name="save_announcement" value="1">
<input type="hidden" name="id" value="<?php echo htmlspecialchars($editRow['id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
<div class="formgrid">
<input name="title" id="annTitle" placeholder="عنوان" value="<?php echo htmlspecialchars($editRow['title'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
<select name="type" id="annType">
<option value="info" <?php echo ($previewType === 'info') ? 'selected' : ''; ?>>اطلاع‌رسانی</option>
<option value="success" <?php echo ($previewType === 'success') ? 'selected' : ''; ?>>موفقیت</option>
<option value="warning" <?php echo ($previewType === 'warning') ? 'selected' : ''; ?>>هشدار</option>
<option value="special" <?php echo ($previewType === 'special') ? 'selected' : ''; ?>>ویژه</option>
</select>
<input name="priority" type="number" min="0" placeholder="اولویت (100=بالاتر)" value="<?php echo (int)($editRow['priority'] ?? 100); ?>">
<select name="status">
<option value="active" <?php echo (($editRow['status'] ?? 'active') === 'active') ? 'selected' : ''; ?>>فعال</option>
<option value="inactive" <?php echo (($editRow['status'] ?? '') === 'inactive') ? 'selected' : ''; ?>>غیرفعال</option>
</select>
<input name="starts_at" type="datetime-local" value="<?php echo htmlspecialchars(campaignInputDateTimeLocal($editRow['starts_at'] ?? 0), ENT_QUOTES, 'UTF-8'); ?>">
<input name="expires_at" type="datetime-local" value="<?php echo htmlspecialchars(campaignInputDateTimeLocal($editRow['expires_at'] ?? 0), ENT_QUOTES, 'UTF-8'); ?>">
</div>
<textarea name="message" id="annMessage" placeholder="متن پیام" required><?php echo htmlspecialchars($editRow['message'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
<div class="previewBox is-<?php echo htmlspecialchars($previewType, ENT_QUOTES, 'UTF-8'); ?>" id="annPreview">
<strong id="annPreviewTitle"><?php echo htmlspecialchars($editRow['title'] ?? 'عنوان پیام', ENT_QUOTES, 'UTF-8'); ?></strong>
<div id="annPreviewMessage"><?php echo nl2br(htmlspecialchars($editRow['message'] ?? 'متن پیام', ENT_QUOTES, 'UTF-8')); ?></div>
</div>
<div style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap">
<button type="submit"><?php echo $editRow ? 'ذخیره' : '+ ایجاد پیام'; ?></button>
<?php if($editRow){ ?><a class="btn btn--muted" href="<?php echo htmlspecialchars(pnvAdminUrl('campaign-announcements.php'), ENT_QUOTES, 'UTF-8'); ?>">انصراف</a><?php } ?>
</div>
</form>
</div>

<div class="box">
<h2>لیست پیام‌ها</h2>
<div class="tablebox">
<table>
<tr><th>عنوان</th><th>نوع</th><th>وضعیت</th><th>شروع</th><th>پایان</th><th>عملیات</th></tr>
<?php foreach($rows as $row){ ?>
<tr>
<td><?php echo htmlspecialchars($row['title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
<td><?php echo htmlspecialchars(campaignAnnouncementTypeLabel($row['type'] ?? 'info'), ENT_QUOTES, 'UTF-8'); ?></td>
<td><span class="badge <?php echo ($row['status'] ?? '') === 'active' ? 'is-on' : 'is-off'; ?>"><?php echo ($row['status'] ?? '') === 'active' ? 'فعال' : 'غیرفعال'; ?></span></td>
<td><?php echo htmlspecialchars(campaignFormatDateTime($row['starts_at'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></td>
<td><?php echo htmlspecialchars(campaignFormatDateTime($row['expires_at'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></td>
<td>
<a class="btn btn--muted" href="<?php echo htmlspecialchars(pnvAdminUrl('campaign-announcements.php?edit=' . urlencode($row['id'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>">ویرایش</a>
<a class="btn btn--muted" href="<?php echo htmlspecialchars(pnvAdminUrl('campaign-announcements.php?toggle=' . urlencode($row['id'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>">تغییر وضعیت</a>
<a class="btn btn--danger" href="<?php echo htmlspecialchars(pnvAdminUrl('campaign-announcements.php?delete=' . urlencode($row['id'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>" onclick="return confirm('حذف شود؟');">حذف</a>
</td>
</tr>
<?php } ?>
</table>
</div>
</div>

<a class="back" href="<?php echo htmlspecialchars(pnvAdminUrl('campaigns.php'), ENT_QUOTES, 'UTF-8'); ?>">بازگشت</a>
</div>

<script>
(function(){
    const title = document.getElementById('annTitle');
    const message = document.getElementById('annMessage');
    const type = document.getElementById('annType');
    const preview = document.getElementById('annPreview');
    const previewTitle = document.getElementById('annPreviewTitle');
    const previewMessage = document.getElementById('annPreviewMessage');
    function sync(){
        previewTitle.textContent = title.value || 'عنوان پیام';
        previewMessage.textContent = message.value || 'متن پیام';
        preview.className = 'previewBox is-' + (type.value || 'info');
    }
    title.addEventListener('input', sync);
    message.addEventListener('input', sync);
    type.addEventListener('change', sync);
})();
</script>
</body>
</html>
