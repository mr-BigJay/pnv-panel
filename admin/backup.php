<?php

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/admin_nav.php';
require_once __DIR__ . '/../backup_lib.php';

pnvAdminRequireAuth();

if(isset($_GET['cancel_preview'])){
    $old = $_SESSION['pnv_import_preview'] ?? null;
    if(is_array($old) && !empty($old['path']) && is_file($old['path'])){
        @unlink($old['path']);
    }
    unset($_SESSION['pnv_import_preview']);
    header('Location: ' . pnvAdminUrl('backup.php'));
    exit;
}

$message = '';
$error = '';
$preview = null;
$sectionLabels = pnvBackupSectionLabelsFa();
$allSections = pnvBackupSections();
$fileRows = pnvBackupCollectFiles(array_keys(array_diff_key($allSections, ['cache' => 1, 'qr_temp' => 1])));
$snapshotDir = pnvBackupDbDir() . '/backups';
$snapshots = [];

if(is_dir($snapshotDir)){
    foreach(glob($snapshotDir . '/pre-import-*.zip') ?: [] as $snap){
        $snapshots[] = [
            'name' => basename($snap),
            'size' => (int)filesize($snap),
            'mtime' => (int)filemtime($snap),
        ];
    }
    usort($snapshots, static function($a, $b){
        return ($b['mtime'] ?? 0) <=> ($a['mtime'] ?? 0);
    });
    $snapshots = array_slice($snapshots, 0, 5);
}

if(isset($_GET['export'])){
    $selected = [];

    if(!empty($_GET['sections']) && is_array($_GET['sections'])){
        $selected = array_values(array_intersect(array_keys($allSections), array_map('strval', $_GET['sections'])));
    }
    elseif(!empty($_GET['optional'])){
        $selected = array_keys($allSections);
    }
    else{
        $selected = array_keys(array_diff_key($allSections, ['cache' => 1, 'qr_temp' => 1]));
    }

    $result = pnvBackupExportZip($selected);

    if(empty($result['ok'])){
        http_response_code(500);
        header('Content-Type: text/plain; charset=UTF-8');
        echo $result['error'] ?? 'Export failed';
        exit;
    }

    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . ($result['filename'] ?? 'pnv-db-backup.zip') . '"');
    header('Content-Length: ' . (int)($result['size'] ?? 0));
    readfile($result['path']);
    @unlink($result['path']);
    exit;
}

if(isset($_POST['import_preview']) && isset($_FILES['backup_file'])){
    if(empty($_FILES['backup_file']['tmp_name']) || !is_uploaded_file($_FILES['backup_file']['tmp_name'])){
        $error = 'فایل بک‌آپ انتخاب نشده است.';
    }
    else{
        $ext = strtolower(pathinfo((string)($_FILES['backup_file']['name'] ?? ''), PATHINFO_EXTENSION));
        if($ext !== 'zip'){
            $error = 'فقط فایل ZIP مجاز است.';
        }
        else{
            $inspect = pnvBackupInspectZip($_FILES['backup_file']['tmp_name']);
            if(empty($inspect['ok'])){
                $error = $inspect['error'] ?? 'بررسی ZIP ناموفق بود.';
            }
            else{
                $previewDir = pnvBackupRootDir() . '/temp/import-preview';
                if(!is_dir($previewDir)){
                    @mkdir($previewDir, 0755, true);
                }

                $previewId = bin2hex(random_bytes(8));
                $previewPath = $previewDir . '/' . $previewId . '.zip';

                if(!@move_uploaded_file($_FILES['backup_file']['tmp_name'], $previewPath)){
                    if(!@copy($_FILES['backup_file']['tmp_name'], $previewPath)){
                        $error = 'ذخیره موقت فایل ZIP ناموفق بود.';
                    }
                }

                if($error === ''){
                    $_SESSION['pnv_import_preview'] = [
                        'id' => $previewId,
                        'path' => $previewPath,
                        'sections' => $inspect['sections'] ?? [],
                        'file_count' => (int)($inspect['file_count'] ?? 0),
                        'created_at' => (string)($inspect['created_at'] ?? ''),
                        'panel_version' => (string)($inspect['panel_version'] ?? ''),
                    ];
                    $preview = $_SESSION['pnv_import_preview'];
                }
            }
        }
    }
}

if(isset($_POST['import_confirm'])){
    $preview = is_array($_SESSION['pnv_import_preview'] ?? null) ? $_SESSION['pnv_import_preview'] : null;

    if(!$preview || empty($preview['path']) || !is_file($preview['path'])){
        $error = 'پیش‌نمایش ایمپورت منقضی شده — دوباره ZIP را انتخاب کنید.';
    }
    else{
        $selected = [];

        if(isset($_POST['sections']) && is_array($_POST['sections'])){
            $selected = array_values(array_intersect(array_keys($allSections), array_map('strval', $_POST['sections'])));
        }

        if(count($selected) === 0){
            $error = 'حداقل یک بخش برای ایمپورت انتخاب کنید.';
        }
        else{
            $result = pnvBackupImportZip($preview['path'], $selected);

            if(empty($result['ok'])){
                $error = $result['error'] ?? 'ایمپورت ناموفق بود.';
            }
            else{
                $message = 'بازیابی انجام شد. ' . (int)($result['count'] ?? 0) . ' فایل بازگردانی شد.';
                if(!empty($result['snapshot'])){
                    $message .= ' بک‌آپ خودکار قبل از ایمپورت: ' . $result['snapshot'];
                }

                @unlink($preview['path']);
                unset($_SESSION['pnv_import_preview']);
                $preview = null;
                $fileRows = pnvBackupCollectFiles(array_keys(array_diff_key($allSections, ['cache' => 1, 'qr_temp' => 1])));
            }
        }
    }
}

if($preview === null && is_array($_SESSION['pnv_import_preview'] ?? null)){
    $preview = $_SESSION['pnv_import_preview'];
}

$h = static function($value){
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
};

$defaultExportSections = array_keys(array_diff_key($allSections, ['cache' => 1, 'qr_temp' => 1]));

?>
<!DOCTYPE html>
<html lang="fa">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>بک‌آپ دیتابیس</title>
<style>
*{box-sizing:border-box}
body{margin:0;padding:20px;background:#0f172a;color:#fff;font-family:tahoma;direction:rtl}
.box{width:100%;max-width:920px;margin:auto;background:#1e293b;padding:30px;border-radius:20px}
h2{text-align:center;margin:0 0 10px;font-size:26px}
.lead{text-align:center;color:#94a3b8;line-height:1.9;margin:0 0 24px;font-size:14px}
.section{background:#0f172a;border:1px solid #334155;border-radius:16px;padding:18px;margin-bottom:16px}
.section h3{margin:0 0 12px;font-size:17px;color:#e2e8f0}
.msg,.err{padding:14px;border-radius:12px;line-height:1.8;margin-bottom:18px}
.msg{background:#166534}.err{background:#991b1b}
table{width:100%;border-collapse:collapse;font-size:13px}
th,td{padding:10px 8px;border-bottom:1px solid #334155;text-align:right}
th{color:#94a3b8;font-weight:600}
td code{direction:ltr;display:inline-block;color:#93c5fd;font-size:12px}
.missing{color:#64748b}
.actions{display:flex;flex-wrap:wrap;gap:10px;margin-top:14px}
button,.btn{display:inline-flex;align-items:center;justify-content:center;border:0;border-radius:12px;padding:13px 18px;background:#22c55e;color:#fff;font:inherit;font-size:15px;cursor:pointer;text-decoration:none}
.btn.secondary{background:#2563eb}
.btn.ghost{background:#334155}
.btn.warn{background:#dc2626}
.hint{font-size:13px;color:#94a3b8;line-height:1.9;margin-top:10px}
.uploadRow{display:flex;flex-wrap:wrap;gap:10px;align-items:center}
input[type=file]{flex:1;min-width:220px;background:#1e293b;border:1px dashed #475569;border-radius:12px;padding:12px;color:#e2e8f0}
.back{display:block;width:100%;border:0;border-radius:12px;padding:15px;background:#334155;color:#fff;font:inherit;font-size:17px;cursor:pointer;text-align:center;text-decoration:none;margin-top:20px}
.sectionGrid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:10px;margin-top:12px}
.sectionPick{display:flex;align-items:flex-start;gap:10px;padding:12px;border:1px solid #334155;border-radius:12px;background:#111827;line-height:1.6;font-size:13px}
.sectionPick input{margin-top:3px}
.sectionPick small{display:block;color:#64748b;margin-top:4px}
.previewMeta{font-size:13px;color:#cbd5e1;line-height:1.9;margin:0 0 12px}
@media(max-width:600px){body{padding:10px}.box{padding:22px 16px}}
</style>
</head>
<body>
<?php adminQuickNavStyles(); adminQuickNav('backup'); ?>

<div class="box">
<h2>بک‌آپ و بازیابی دیتابیس</h2>
<p class="lead">Export / Import ba entekhab bakhsh-ha — karbaran, kharid ha, payam ha, bot ha, 3x-ui va …</p>

<?php if($message !== ''){ ?><div class="msg"><?php echo $h($message); ?></div><?php } ?>
<?php if($error !== ''){ ?><div class="err"><?php echo $h($error); ?></div><?php } ?>

<div class="section">
<h3>اکسپورت (دانلود بک‌آپ)</h3>
<p class="hint">Bakhsh-haye mored nazar ro entekhab konid va ZIP begirid.</p>
<form method="get" action="<?php echo $h(pnvAdminUrl('backup.php')); ?>">
<input type="hidden" name="export" value="1">
<div class="sectionGrid">
<?php foreach($allSections as $key => $meta){
    $checked = in_array($key, $defaultExportSections, true) ? ' checked' : '';
    $count = 0;
    foreach($fileRows as $row){
        if(($row['section'] ?? '') === $key && !empty($row['exists'])){
            $count++;
        }
    }
?>
<label class="sectionPick">
<input type="checkbox" name="sections[]" value="<?php echo $h($key); ?>"<?php echo $checked; ?>>
<span>
<strong><?php echo $h($sectionLabels[$key] ?? $meta['label']); ?></strong>
<small><?php echo $count > 0 ? ($count . ' file') : 'khali'; ?></small>
</span>
</label>
<?php } ?>
</div>
<div class="actions">
<button type="submit" class="btn">دانلود ZIP</button>
</div>
</form>
</div>

<div class="section">
<h3>ایمپورت (بازیابی)</h3>
<?php if(!$preview){ ?>
<p class="hint">Avval ZIP ro upload kon — bad entekhab mikonim kodoom bakhsh ha import beshe.</p>
<form method="post" enctype="multipart/form-data">
<input type="hidden" name="import_preview" value="1">
<div class="uploadRow">
<input type="file" name="backup_file" accept=".zip,application/zip" required>
<button type="submit" class="btn secondary">بررسی ZIP</button>
</div>
</form>
<?php } else { ?>
<p class="previewMeta">
ZIP ok — <?php echo (int)($preview['file_count'] ?? 0); ?> file
<?php if(!empty($preview['panel_version'])){ ?> | panel: <?php echo $h($preview['panel_version']); ?><?php } ?>
<?php if(!empty($preview['created_at'])){ ?> | <?php echo $h($preview['created_at']); ?><?php } ?>
</p>
<form method="post">
<input type="hidden" name="import_confirm" value="1">
<p class="hint">Motmaeni? ghabl az import yek backup automatic az vaziat feli sakhte mishe.</p>
<div class="sectionGrid">
<?php
$available = is_array($preview['sections'] ?? null) ? $preview['sections'] : [];
foreach($allSections as $key => $meta){
    if(!isset($available[$key])){
        continue;
    }
?>
<label class="sectionPick">
<input type="checkbox" name="sections[]" value="<?php echo $h($key); ?>" checked>
<span>
<strong><?php echo $h($sectionLabels[$key] ?? $meta['label']); ?></strong>
<small><?php echo (int)$available[$key]; ?> file dar ZIP</small>
</span>
</label>
<?php } ?>
</div>
<div class="actions">
<button type="submit" class="btn warn">Import bakhsh-haye entekhab shode</button>
<a class="btn ghost" href="<?php echo $h(pnvAdminUrl('backup.php?cancel_preview=1')); ?>">انصراف</a>
</div>
</form>
<?php } ?>
</div>

<div class="section">
<h3>فایل‌های فعلی پنل</h3>
<table>
<thead>
<tr><th>بخش</th><th>مسیر</th><th>وضعیت</th><th>حجم</th></tr>
</thead>
<tbody>
<?php foreach($fileRows as $row){ ?>
<tr>
<td><?php echo $h($sectionLabels[$row['section'] ?? ''] ?? ($row['section'] ?? '')); ?></td>
<td><code><?php echo $h($row['relative']); ?></code></td>
<td><?php echo !empty($row['exists']) ? 'موجود' : '<span class="missing">—</span>'; ?></td>
<td><?php echo !empty($row['exists']) ? $h(pnvBackupFormatBytes($row['size'])) : '—'; ?></td>
</tr>
<?php } ?>
</tbody>
</table>
</div>

<?php if(count($snapshots) > 0){ ?>
<div class="section">
<h3>آخرین بک‌آپ‌های خودکار (قبل از ایمپورت)</h3>
<table>
<thead>
<tr><th>فایل</th><th>حجم</th><th>تاریخ</th></tr>
</thead>
<tbody>
<?php foreach($snapshots as $snap){ ?>
<tr>
<td><code><?php echo $h($snap['name']); ?></code></td>
<td><?php echo $h(pnvBackupFormatBytes($snap['size'])); ?></td>
<td><?php echo $h(date('Y-m-d H:i', $snap['mtime'])); ?></td>
</tr>
<?php } ?>
</tbody>
</table>
</div>
<?php } ?>

<a class="back" href="<?php echo $h(pnvAdminUrl()); ?>">بازگشت به داشبورد</a>
</div>

<?php require_once __DIR__ . '/../form_validation_fa.php'; pnvFormValidationFaScript(); ?>

</body>
</html>
