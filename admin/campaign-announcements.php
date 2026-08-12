<?php

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/admin_nav.php';
require_once __DIR__ . '/campaign_ui.php';
require_once __DIR__ . '/../campaign_lib.php';

pnvAdminRequireAuth();

$rows = campaignAnnouncementsLoad();
$flash = '';
$editId = trim((string)($_GET['edit'] ?? ''));
$editRow = null;

foreach($rows as $row){
    if(($row['id'] ?? '') === $editId){
        $editRow = $row;
        break;
    }
}

$q = trim((string)($_GET['q'] ?? ''));
$statusFilter = trim((string)($_GET['status'] ?? ''));
$typeFilter = trim((string)($_GET['type'] ?? ''));

if(isset($_POST['save_announcement'])){
    $id = trim((string)($_POST['id'] ?? ''));
    $title = trim((string)($_POST['title'] ?? ''));
    $message = trim((string)($_POST['message'] ?? ''));
    $type = trim((string)($_POST['type'] ?? 'info'));
    $priority = max(0, intval($_POST['priority'] ?? 100));
    $status = !empty($_POST['status_active']) ? 'active' : 'inactive';
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

if($q !== ''){
    $rows = array_values(array_filter($rows, function($row) use ($q){
        $title = (string)($row['title'] ?? '');
        $message = (string)($row['message'] ?? '');
        return stripos($title, $q) !== false || stripos($message, $q) !== false;
    }));
}

if($statusFilter === 'active' || $statusFilter === 'inactive'){
    $rows = array_values(array_filter($rows, function($row) use ($statusFilter){
        return ($row['status'] ?? '') === $statusFilter;
    }));
}

if(in_array($typeFilter, ['info', 'success', 'warning', 'special'], true)){
    $rows = array_values(array_filter($rows, function($row) use ($typeFilter){
        return ($row['type'] ?? 'info') === $typeFilter;
    }));
}

usort($rows, function($a, $b){
    $priorityDiff = intval($b['priority'] ?? 0) <=> intval($a['priority'] ?? 0);

    if($priorityDiff !== 0){
        return $priorityDiff;
    }

    return intval($b['created_at'] ?? 0) <=> intval($a['created_at'] ?? 0);
});

function campaignAnnouncementValidityText($row){
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

function campaignAnnouncementMessageExcerpt($row, $limit = 120){
    $message = trim(preg_replace('/\s+/u', ' ', (string)($row['message'] ?? '')));

    if($message === ''){
        return 'بدون متن';
    }

    if(function_exists('mb_strlen') && mb_strlen($message, 'UTF-8') > $limit){
        return rtrim(mb_substr($message, 0, $limit, 'UTF-8')) . '…';
    }

    if(strlen($message) > $limit){
        return rtrim(substr($message, 0, $limit)) . '…';
    }

    return $message;
}

$previewType = $editRow['type'] ?? 'info';
$isActive = ($editRow['status'] ?? 'active') === 'active';

?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>پیام‌های داشبورد</title>
<?php campaignAdminStyles(); campaignJalaliDatePickerHead(); campaignAdminBottomNavHead(); ?>
</head>
<body class="campaignAdmin">
<div class="campaignShell">

<?php campaignAdminNav('announcements'); ?>

<div class="campaignCard">
<div class="campaignCardHead">
<h2 class="campaignCardTitle"><?php echo $editRow ? 'ویرایش پیام داشبورد' : 'ایجاد پیام داشبورد'; ?></h2>
<span class="campaignCardIcon"><?php echo campaignIconMessage(); ?></span>
</div>

<?php if($flash !== ''){ ?><div class="campaignFlash"><?php echo htmlspecialchars($flash, ENT_QUOTES, 'UTF-8'); ?></div><?php } ?>

<form method="POST" id="announcementForm">
<input type="hidden" name="save_announcement" value="1">
<input type="hidden" name="id" value="<?php echo htmlspecialchars($editRow['id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">

<div class="campaignSection">
<p class="campaignSectionTitle">اطلاعات اصلی</p>
<div class="campaignField">
<label class="campaignLabel">عنوان پیام</label>
<div class="campaignInputWrap">
<?php echo campaignIconMessage(); ?>
<input class="campaignInput" name="title" id="annTitle" placeholder="مثال: تخفیف ویژه آخر هفته" value="<?php echo htmlspecialchars($editRow['title'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
</div>
</div>
<div class="campaignGrid2">
<div class="campaignField">
<label class="campaignLabel">نوع پیام</label>
<select class="campaignSelect" name="type" id="annType">
<option value="info" <?php echo ($previewType === 'info') ? 'selected' : ''; ?>>اطلاع‌رسانی</option>
<option value="success" <?php echo ($previewType === 'success') ? 'selected' : ''; ?>>موفقیت</option>
<option value="warning" <?php echo ($previewType === 'warning') ? 'selected' : ''; ?>>هشدار</option>
<option value="special" <?php echo ($previewType === 'special') ? 'selected' : ''; ?>>ویژه</option>
</select>
</div>
<div class="campaignField">
<label class="campaignLabel">اولویت نمایش</label>
<div class="campaignInputWrap">
<?php echo campaignIconList(); ?>
<input class="campaignInput" name="priority" type="number" min="0" placeholder="مثال: 100" value="<?php echo (int)($editRow['priority'] ?? 100); ?>">
</div>
<p class="campaignHint">عدد بزرگ‌تر = نمایش بالاتر در داشبورد کاربر</p>
</div>
</div>
</div>

<div class="campaignSection">
<p class="campaignSectionTitle">محتوای پیام</p>
<div class="campaignField">
<label class="campaignLabel">متن پیام</label>
<textarea class="campaignTextarea" name="message" id="annMessage" placeholder="متن پیامی که کاربر در داشبورد می‌بیند..." required><?php echo htmlspecialchars($editRow['message'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
</div>
<div class="campaignPreviewBox is-<?php echo htmlspecialchars($previewType, ENT_QUOTES, 'UTF-8'); ?>" id="annPreview">
<strong id="annPreviewTitle"><?php echo htmlspecialchars($editRow['title'] ?? 'عنوان پیام', ENT_QUOTES, 'UTF-8'); ?></strong>
<div id="annPreviewMessage"><?php echo nl2br(htmlspecialchars($editRow['message'] ?? 'متن پیام در اینجا پیش‌نمایش می‌شود.', ENT_QUOTES, 'UTF-8')); ?></div>
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
<div class="campaignToggleText">پیام در حال حاضر فعال است</div>
<label class="campaignToggle">
<input type="checkbox" name="status_active" value="1" <?php echo $isActive ? 'checked' : ''; ?>>
<span class="campaignToggleTrack"></span>
</label>
</div>
</div>

<button class="campaignSubmit" type="submit"><?php echo $editRow ? 'ذخیره تغییرات' : '+ ایجاد پیام'; ?></button>
<?php if($editRow){ ?>
<a class="campaignBack" href="<?php echo htmlspecialchars(pnvAdminUrl('campaign-announcements.php'), ENT_QUOTES, 'UTF-8'); ?>">انصراف از ویرایش</a>
<?php } ?>
</form>
</div>

<div class="campaignCard">
<div class="campaignCardHead">
<h2 class="campaignCardTitle">لیست پیام‌ها</h2>
<span class="campaignCardIcon"><?php echo campaignIconList(); ?></span>
</div>

<form class="campaignSearchRow" method="GET" id="filterForm">
<div class="campaignSearchWrap">
<?php echo campaignIconSearch(); ?>
<input name="q" placeholder="جستجو در عنوان یا متن..." value="<?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>">
</div>
<button class="campaignFilterBtn" type="button" id="filterToggleBtn">فیلتر</button>
</form>

<div class="campaignGrid2 campaignHidden" id="filterPanel" style="margin-bottom:12px">
<select class="campaignSelect" name="status" form="filterForm">
<option value="">همه وضعیت‌ها</option>
<option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>فعال</option>
<option value="inactive" <?php echo $statusFilter === 'inactive' ? 'selected' : ''; ?>>غیرفعال</option>
</select>
<select class="campaignSelect" name="type" form="filterForm">
<option value="">همه انواع</option>
<option value="info" <?php echo $typeFilter === 'info' ? 'selected' : ''; ?>>اطلاع‌رسانی</option>
<option value="success" <?php echo $typeFilter === 'success' ? 'selected' : ''; ?>>موفقیت</option>
<option value="warning" <?php echo $typeFilter === 'warning' ? 'selected' : ''; ?>>هشدار</option>
<option value="special" <?php echo $typeFilter === 'special' ? 'selected' : ''; ?>>ویژه</option>
</select>
<button class="campaignFilterBtn" type="submit" form="filterForm">اعمال فیلتر</button>
</div>

<div class="campaignList" id="announcementList">
<?php
$index = 0;
foreach($rows as $row){
    $index++;
    $hiddenClass = $index > 3 ? ' campaignHidden campaignListExtra' : '';
    $rowType = $row['type'] ?? 'info';
    $isRowActive = ($row['status'] ?? '') === 'active';
    $rowId = urlencode($row['id'] ?? '');
?>
<div class="campaignListItem<?php echo $hiddenClass; ?>">
<div class="campaignMenu">
<button type="button" class="campaignMenuBtn" data-menu-btn aria-label="عملیات">⋯</button>
<div class="campaignMenuPanel">
<a href="<?php echo htmlspecialchars(pnvAdminUrl('campaign-announcements.php?edit=' . $rowId), ENT_QUOTES, 'UTF-8'); ?>">ویرایش</a>
<a href="<?php echo htmlspecialchars(pnvAdminUrl('campaign-announcements.php?toggle=' . $rowId), ENT_QUOTES, 'UTF-8'); ?>">تغییر وضعیت</a>
<a class="is-danger" href="<?php echo htmlspecialchars(pnvAdminUrl('campaign-announcements.php?delete=' . $rowId), ENT_QUOTES, 'UTF-8'); ?>" onclick="return confirm('حذف شود؟');">حذف</a>
</div>
</div>
<div>
<div class="campaignItemTop">
<div>
<div class="campaignItemCode"><?php echo htmlspecialchars($row['title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
<div class="campaignItemBadges">
<span class="campaignBadge is-<?php echo htmlspecialchars($rowType, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(campaignAnnouncementTypeLabel($rowType), ENT_QUOTES, 'UTF-8'); ?></span>
<span class="campaignBadge <?php echo $isRowActive ? 'is-active' : 'is-inactive'; ?>"><?php echo $isRowActive ? 'فعال' : 'غیرفعال'; ?></span>
</div>
</div>
</div>
<div class="campaignItemMessage"><?php echo htmlspecialchars(campaignAnnouncementMessageExcerpt($row), ENT_QUOTES, 'UTF-8'); ?></div>
<div class="campaignItemMeta">
<div><strong>اعتبار</strong><?php echo htmlspecialchars(campaignAnnouncementValidityText($row), ENT_QUOTES, 'UTF-8'); ?></div>
<div><strong>اولویت</strong><?php echo (int)($row['priority'] ?? 0); ?></div>
</div>
</div>
</div>
<?php } ?>
</div>

<?php if(count($rows) > 3){ ?>
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
    const title = document.getElementById('annTitle');
    const message = document.getElementById('annMessage');
    const type = document.getElementById('annType');
    const preview = document.getElementById('annPreview');
    const previewTitle = document.getElementById('annPreviewTitle');
    const previewMessage = document.getElementById('annPreviewMessage');

    function syncPreview(){
        if(!preview || !previewTitle || !previewMessage || !type) return;
        previewTitle.textContent = (title && title.value) ? title.value : 'عنوان پیام';
        previewMessage.textContent = (message && message.value) ? message.value : 'متن پیام در اینجا پیش‌نمایش می‌شود.';
        preview.className = 'campaignPreviewBox is-' + (type.value || 'info');
    }

    if(title){ title.addEventListener('input', syncPreview); }
    if(message){ message.addEventListener('input', syncPreview); }
    if(type){ type.addEventListener('change', syncPreview); }

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
        <?php if($statusFilter !== '' || $typeFilter !== ''){ ?>filterPanel.classList.remove('campaignHidden');<?php } ?>
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
<?php campaignJalaliDatePickerFoot(); campaignAdminBottomNavFoot(); ?>
</body>
</html>
