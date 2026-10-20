<?php

if(file_exists(__DIR__ . '/auth.php')){
    require_once __DIR__ . '/auth.php';
    pnvAdminRequireAuth();
}
else{
    session_start();

    if(!isset($_SESSION['admin'])){
        header('Location: index.php');
        exit;
    }
}

require_once __DIR__ . '/admin_nav.php';
require_once __DIR__ . '/../tejarat_auto_verify_lib.php';
require_once __DIR__ . '/../tejarat_worker_lib.php';

$config = tejaratVerifyLoadConfig();
$message = '';
$error = '';

if(isset($_POST['save'])){
    $cardsRaw = trim((string)($_POST['destination_cards'] ?? ''));
    $cards = preg_split('/[\s,]+/', $cardsRaw);
    $cards = array_values(array_unique(array_filter(array_map(static function($c){
        return preg_replace('/\D+/', '', (string)$c);
    }, $cards ?: []), static fn($c) => $c !== '')));

    $provider = strtolower(trim((string)($_POST['provider'] ?? 'disabled')));
    $allowed = ['disabled', 'mock', 'api_stub', 'browser_worker', 'arc_open_banking'];

    if(!in_array($provider, $allowed, true)){
        $provider = 'disabled';
    }

    $newSecret = trim((string)($_POST['worker_ingest_secret'] ?? ''));
    $ingestSecret = $newSecret !== '' ? $newSecret : tejaratWorkerIngestSecret($config);

    $config = array_merge($config, [
        'enabled' => isset($_POST['enabled']),
        'dry_run' => isset($_POST['dry_run']),
        'provider' => $provider,
        'poll_interval_seconds' => max(15, intval($_POST['poll_interval_seconds'] ?? 60)),
        'max_deposit_age_seconds' => max(3600, intval($_POST['max_deposit_age_seconds'] ?? 86400)),
        'destination_cards' => $cards,
        'worker_heartbeat_max_age_seconds' => max(60, intval($_POST['worker_heartbeat_max_age_seconds'] ?? 300)),
        'worker_ingest_secret' => $ingestSecret,
        'worker_ingest_url' => trim((string)($_POST['worker_ingest_url'] ?? ($config['worker_ingest_url'] ?? ''))),
    ]);

    if(tejaratVerifySaveConfig($config)){
        $message = 'تنظیمات تجارت ذخیره شد.';
    }
    else{
        $error = 'ذخیره تنظیمات ناموفق بود.';
    }
}

if(isset($_POST['poll_once'])){
    $result = tejaratVerifyPollOnce();
    $message = !empty($result['skipped'])
        ? 'ماژول غیرفعال است.'
        : ('Polling: tx=' . intval($result['transactions'] ?? 0) . ' dry_run=' . (!empty($result['dry_run']) ? '1' : '0'));
}

$summary = tejaratVerifyAdminSummary();
$worker = is_array($summary['worker'] ?? null) ? $summary['worker'] : null;
$ingestUrl = trim((string)($config['worker_ingest_url'] ?? ''));

if($ingestUrl === ''){
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $ingestUrl = $scheme . '://' . $host . '/tejarat-worker-ingest.php';
}

$conn = (string)($summary['connection_status'] ?? 'disabled');
$connLabel = match($conn){
    'ok' => 'متصل',
    'session_expired' => 'نشست منقضی',
    'error' => 'خطا',
    default => 'غیرفعال',
};

function tejaratAdminFmt($ts){
    return function_exists('tejaratVerifyFormatTimestamp') ? tejaratVerifyFormatTimestamp($ts) : '—';
}

?>
<!DOCTYPE html>
<html lang="fa">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>بانک تجارت — تأیید خودکار</title>
<style>
*{box-sizing:border-box}
body{margin:0;padding:20px;background:#0f172a;color:#fff;font-family:tahoma;direction:rtl}
.box{max-width:820px;margin:auto;background:#1e293b;padding:28px;border-radius:18px}
h2{margin:0 0 8px;text-align:center}
.sub{text-align:center;color:#94a3b8;margin:0 0 22px;line-height:1.8;font-size:14px}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin:0 0 20px}
.card{background:#0f172a;border:1px solid #334155;border-radius:12px;padding:14px;line-height:1.7;font-size:14px}
.card b{display:block;font-size:12px;color:#94a3b8;margin-bottom:4px}
label{display:block;margin:14px 0 6px}
input,select,textarea{width:100%;border:0;border-radius:10px;padding:12px;background:#0f172a;color:#fff;font:inherit}
textarea.ltr{direction:ltr;text-align:left;min-height:72px}
.toggle{display:flex;align-items:center;gap:8px;margin:8px 0}
.toggle input{width:18px;height:18px}
.hint{font-size:13px;color:#94a3b8;line-height:1.9;margin:8px 0}
.msg,.err{padding:12px;border-radius:10px;margin-bottom:14px;line-height:1.8}
.msg{background:#166534}.err{background:#991b1b}
button,.back{width:100%;border:0;border-radius:12px;padding:14px;background:#22c55e;color:#fff;font:inherit;font-size:16px;cursor:pointer;margin-top:10px}
.secondary{background:#2563eb}.back{background:#334155;text-align:center;text-decoration:none;display:block}
code{color:#93c5fd;direction:ltr;word-break:break-all}
</style>
</head>
<body>
<?php adminQuickNavStyles(); adminQuickNav('tejarat'); ?>

<div class="box">
<h2>بانک تجارت — تأیید خودکار</h2>
<p class="sub">Worker فقط‌خواندن + تطبیق از مسیر Instant Pay (بدون دست‌کاری مستقیم CSV/JSON)</p>

<?php if($message !== ''){ ?><div class="msg"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div><?php } ?>
<?php if($error !== ''){ ?><div class="err"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div><?php } ?>

<div class="grid">
<div class="card"><b>وضعیت اتصال</b><?php echo htmlspecialchars($connLabel, ENT_QUOTES, 'UTF-8'); ?></div>
<div class="card"><b>آخرین دریافت موفق</b><?php echo htmlspecialchars(tejaratAdminFmt($summary['last_success_at'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></div>
<div class="card"><b>تراکنش‌های بررسی‌شده</b><?php echo intval($summary['stats']['transactions_seen'] ?? 0); ?></div>
<div class="card"><b>تطبیق / تأیید</b><?php echo intval($summary['stats']['matches_confirmed'] ?? 0); ?></div>
<div class="card"><b>بررسی دستی</b><?php echo intval($summary['stats']['manual_review'] ?? 0); ?></div>
<div class="card"><b>خطاها</b><?php echo intval($summary['stats']['errors'] ?? 0); ?></div>
</div>

<?php if($worker){ ?>
<div class="hint">Worker: نشست <?php echo htmlspecialchars((string)($worker['session_status'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?>
· heartbeat <?php echo htmlspecialchars(tejaratAdminFmt($worker['heartbeat_at'] ?? 0), ENT_QUOTES, 'UTF-8'); ?>
· inbox <?php echo intval($worker['pending_inbox'] ?? 0); ?>
· <?php echo !empty($worker['heartbeat_fresh']) ? 'تازه' : 'منقضی/نامعتبر'; ?></div>
<?php } ?>

<?php if(trim((string)($summary['last_error'] ?? '')) !== ''){ ?>
<div class="err"><?php echo htmlspecialchars((string)$summary['last_error'], ENT_QUOTES, 'UTF-8'); ?></div>
<?php } ?>

<form method="post">
<label class="toggle"><input type="checkbox" name="enabled" <?php echo !empty($config['enabled']) ? 'checked' : ''; ?>> فعال</label>
<label class="toggle"><input type="checkbox" name="dry_run" <?php echo !empty($config['dry_run']) ? 'checked' : ''; ?>> Dry Run (بدون صدور/تمدید زنده)</label>

<label>Provider
<select name="provider">
<?php
$providers = [
    'disabled' => 'خاموش',
    'mock' => 'Mock',
    'browser_worker' => 'Worker مرورگر',
    'arc_open_banking' => 'آرک (قراردادی)',
    'api_stub' => 'API stub',
];
foreach($providers as $pk => $pl){
    $sel = (($config['provider'] ?? '') === $pk) ? 'selected' : '';
    echo '<option value="' . htmlspecialchars($pk, ENT_QUOTES, 'UTF-8') . '" ' . $sel . '>' . htmlspecialchars($pl, ENT_QUOTES, 'UTF-8') . '</option>';
}
?>
</select>
</label>

<label>کارت‌های مقصد (فقط رقم، با کاما)</label>
<textarea class="ltr" name="destination_cards"><?php echo htmlspecialchars(implode(', ', (array)($config['destination_cards'] ?? [])), ENT_QUOTES, 'UTF-8'); ?></textarea>

<label>بازه polling (ثانیه)</label>
<input type="number" min="15" max="3600" name="poll_interval_seconds" value="<?php echo intval($config['poll_interval_seconds'] ?? 60); ?>">

<label>حداکثر سن واریز (ثانیه)</label>
<input type="number" min="3600" name="max_deposit_age_seconds" value="<?php echo intval($config['max_deposit_age_seconds'] ?? 86400); ?>">

<label>حداکثر سن heartbeat Worker (ثانیه)</label>
<input type="number" min="60" name="worker_heartbeat_max_age_seconds" value="<?php echo intval($config['worker_heartbeat_max_age_seconds'] ?? 300); ?>">

<label>Worker ingest secret (خالی = نگه‌داشتن مقدار قبلی)</label>
<input class="ltr" type="password" name="worker_ingest_secret" value="" placeholder="<?php echo !empty($worker['ingest_configured']) ? '••••••••' : 'تولید و ذخیره کنید'; ?>" autocomplete="off">

<label>URL ingest (برای Worker)</label>
<input class="ltr" type="text" name="worker_ingest_url" value="<?php echo htmlspecialchars(trim((string)($config['worker_ingest_url'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>" placeholder="<?php echo htmlspecialchars($ingestUrl, ENT_QUOTES, 'UTF-8'); ?>">

<div class="hint">Endpoint: <code><?php echo htmlspecialchars($ingestUrl, ENT_QUOTES, 'UTF-8'); ?></code><br>
Header: <code>X-PNV-Tejarat-Worker-Signature</code> = HMAC-SHA256(body, secret)</div>

<button type="submit" name="save">ذخیره تنظیمات</button>
<button type="submit" name="poll_once" class="secondary">اجرای یک‌بار Poll (CLI/cron همان منطق)</button>
</form>

<a class="back" href="<?php echo htmlspecialchars(function_exists('pnvAdminUrl') ? pnvAdminUrl('index.php?page=payments') : 'index.php?page=payments', ENT_QUOTES, 'UTF-8'); ?>">بازگشت به خریدها</a>
</div>
</body>
</html>
