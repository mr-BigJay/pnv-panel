<?php

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/admin_nav.php';
require_once __DIR__ . '/../backup_lib.php';

pnvAdminRequireAuth();

$message = '';
$error = '';
$fileRows = pnvBackupCollectFiles(false);
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
    $includeOptional = !empty($_GET['optional']);
    $result = pnvBackupExportZip($includeOptional);

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

if(isset($_POST['import']) && isset($_FILES['backup_file'])){
    if(empty($_FILES['backup_file']['tmp_name']) || !is_uploaded_file($_FILES['backup_file']['tmp_name'])){
        $error = 'فایل بک‌آپ انتخاب نشده است.';
    }
    else{
        $ext = strtolower(pathinfo((string)($_FILES['backup_file']['name'] ?? ''), PATHINFO_EXTENSION));
        if($ext !== 'zip'){
            $error = 'فقط فایل ZIP مجاز است.';
        }
        else{
            $result = pnvBackupImportZip($_FILES['backup_file']['tmp_name']);
            if(empty($result['ok'])){
                $error = $result['error'] ?? 'ایمپورت ناموفق بود.';
            }
            else{
                $message = 'بازیابی انجام شد. ' . (int)($result['count'] ?? 0) . ' فایل بازگردانی شد.';
                if(!empty($result['snapshot'])){
                    $message .= ' بک‌آپ خودکار قبل از ایمپورت: ' . $result['snapshot'];
                }
                $fileRows = pnvBackupCollectFiles(false);
            }
        }
    }
}

$h = static function($value){
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
};

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
.box{width:100%;max-width:860px;margin:auto;background:#1e293b;padding:30px;border-radius:20px}
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
.hint{font-size:13px;color:#94a3b8;line-height:1.9;margin-top:10px}
.uploadRow{display:flex;flex-wrap:wrap;gap:10px;align-items:center}
input[type=file]{flex:1;min-width:220px;background:#1e293b;border:1px dashed #475569;border-radius:12px;padding:12px;color:#e2e8f0}
.back{display:block;width:100%;border:0;border-radius:12px;padding:15px;background:#334155;color:#fff;font:inherit;font-size:17px;cursor:pointer;text-align:center;text-decoration:none;margin-top:20px}
@media(max-width:600px){body{padding:10px}.box{padding:22px 16px}}
</style>
</head>
<body>
<?php adminQuickNavStyles(); adminQuickNav('backup'); ?>

<div class="box">
<h2>بک‌آپ و بازیابی دیتابیس</h2>
<p class="lead">اکسپورت و ایمپورت فایل‌های JSON و CSV پنل (کاربران، پلن‌ها، پرداخت‌ها، تنظیمات و …)</p>

<?php if($message !== ''){ ?><div class="msg"><?php echo $h($message); ?></div><?php } ?>
<?php if($error !== ''){ ?><div class="err"><?php echo $h($error); ?></div><?php } ?>

<div class="section">
<h3>اکسپورت (دانلود بک‌آپ)</h3>
<p class="hint">یک فایل ZIP شامل تمام فایل‌های دیتابیس و manifest امنیتی ساخته می‌شود.</p>
<div class="actions">
<a class="btn" href="<?php echo $h(pnvAdminUrl('backup.php?export=1')); ?>">دانلود بک‌آپ کامل</a>
<a class="btn secondary" href="<?php echo $h(pnvAdminUrl('backup.php?export=1&optional=1')); ?>">بک‌آپ + کش‌ها</a>
</div>
</div>

<div class="section">
<h3>ایمپورت (بازیابی)</h3>
<p class="hint">قبل از بازیابی، به‌صورت خودکار یک بک‌آپ از وضعیت فعلی در <code>db/backups/</code> ذخیره می‌شود.</p>
<form method="post" enctype="multipart/form-data">
<input type="hidden" name="import" value="1">
<div class="uploadRow">
<input type="file" name="backup_file" accept=".zip,application/zip" required>
<button type="submit" class="btn ghost">بازیابی از ZIP</button>
</div>
</form>
</div>

<div class="section">
<h3>فایل‌های شامل بک‌آپ</h3>
<table>
<thead>
<tr><th>مسیر</th><th>وضعیت</th><th>حجم</th></tr>
</thead>
<tbody>
<?php foreach($fileRows as $row){ ?>
<tr>
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

</body>
</html>
