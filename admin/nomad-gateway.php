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
require_once __DIR__ . '/../nomad_lib.php';
require_once __DIR__ . '/../instant_pay_lib.php';

$config = nomadLoadConfig();
$message = '';
$error = '';
$webhookUrl = nomadWebhookPublicUrl();
$headerName = trim((string)($config['header_name'] ?? 'X-Nomad-Token'));

if($headerName === ''){
    $headerName = 'X-Nomad-Token';
}

if(isset($_POST['save'])){
    $token = trim((string)($_POST['webhook_token'] ?? ''));

    if($token === '' && trim((string)($config['webhook_token'] ?? '')) !== ''){
        $token = $config['webhook_token'];
    }

    if($token === ''){
        $token = nomadGenerateToken();
    }

    $allowedRaw = trim((string)($_POST['allowed_senders'] ?? ''));
    $allowed = array_values(array_filter(array_map(static function($part){
        return preg_replace('/\D+/', '', trim($part));
    }, preg_split('/[\s,]+/u', $allowedRaw) ?: [])));

    $config = [
        'enabled' => isset($_POST['enabled']),
        'dry_run' => isset($_POST['dry_run']),
        'webhook_token' => $token,
        'allowed_senders' => $allowed,
        'accept_unknown_sender' => isset($_POST['accept_unknown_sender']),
        'max_inbox_entries' => max(50, intval($_POST['max_inbox_entries'] ?? 500)),
        'header_name' => trim((string)($_POST['header_name'] ?? 'X-Nomad-Token')),
    ];

    nomadSaveConfig($config);
    $message = 'تنظیمات Nomad Gateway ذخیره شد.';
    $headerName = trim((string)($config['header_name'] ?? 'X-Nomad-Token'));
}

if(isset($_POST['regenerate_token'])){
    $config['webhook_token'] = nomadGenerateToken();
    nomadSaveConfig($config);
    $message = 'توکن Webhook جدید ساخته شد.';
}

if(isset($_POST['apply_match'])){
    $inboxId = trim((string)($_POST['inbox_id'] ?? ''));
    $applyResult = nomadApplyInboxMatch($inboxId, $config);

    if(!empty($applyResult['ok'])){
        $message = 'تطبیق اعمال شد و مسیر پرداخت آنی اجرا شد.';
    }
    else{
        $error = $applyResult['error'] ?? 'اعمال ناموفق';
    }
}

$tokenMasked = trim((string)($config['webhook_token'] ?? ''));

if($tokenMasked !== ''){
    $tokenMasked = substr($tokenMasked, 0, 8) . str_repeat('•', 12) . substr($tokenMasked, -6);
}

$inbox = nomadLoadInbox();
$inbox = array_slice($inbox, 0, 80);

?>
<!DOCTYPE html>
<html lang="fa">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Nomad Gateway — پیامک پست‌بانک</title>
<style>
*{box-sizing:border-box}
body{margin:0;padding:20px;background:#0f172a;color:#fff;font-family:tahoma;direction:rtl}
.box{width:100%;max-width:980px;margin:auto;background:#1e293b;padding:30px;border-radius:20px}
h2{text-align:center;margin:0 0 12px;font-size:26px}
.sub{text-align:center;color:#94a3b8;margin:0 0 28px;line-height:1.8;font-size:14px}
label{display:block;margin:18px 0 8px;font-size:15px;color:#e2e8f0}
input,textarea{width:100%;border:0;border-radius:12px;padding:14px;background:#0f172a;color:#fff;font:inherit;font-size:15px}
input.ltr,textarea.ltr{direction:ltr;text-align:left}
.toggle{display:flex;align-items:center;gap:10px;cursor:pointer;margin:0 0 14px}
.toggle input{width:20px;height:20px;margin:0}
.hint{background:#172554;border-radius:12px;padding:14px;color:#cbd5e1;font-size:14px;line-height:2;margin-top:10px}
.hint code{direction:ltr;display:inline-block;color:#93c5fd;word-break:break-all}
.msg,.err{padding:14px;border-radius:12px;line-height:1.8;margin-bottom:18px}
.msg{background:#166534}.err{background:#991b1b}
button,.back{display:inline-block;border:0;border-radius:12px;padding:12px 18px;background:#22c55e;color:#fff;font:inherit;font-size:15px;cursor:pointer;text-align:center;text-decoration:none;margin:8px 0 0 8px}
.test{background:#2563eb}.warn{background:#b45309}.back{background:#334155}
table{width:100%;border-collapse:collapse;margin-top:24px;font-size:13px}
th,td{border-bottom:1px solid #334155;padding:10px 8px;vertical-align:top;text-align:right}
th{color:#94a3b8;font-weight:600}
.status{display:inline-block;padding:4px 10px;border-radius:999px;background:#334155;font-size:12px}
.status-ok{background:#166534}.status-warn{background:#92400e}.status-bad{background:#7f1d1d}
.sms-preview{max-width:280px;white-space:pre-wrap;color:#cbd5e1;font-size:12px;line-height:1.6}
@media(max-width:900px){table{font-size:11px} .sms-preview{max-width:160px}}
</style>
</head>
<body>
<?php adminQuickNavStyles(); adminQuickNav('nomad'); ?>

<div class="box">
<h2>Nomad Gateway — پیامک پست‌بانک</h2>
<p class="sub">دریافت خودکار SMS واریز از اندروید؛ تطبیق با سفارش — بدون فعال‌سازی خودکار اشتراک از روی پیامک</p>

<?php if($message !== ''){ ?><div class="msg"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div><?php } ?>
<?php if($error !== ''){ ?><div class="err"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div><?php } ?>

<div class="hint">
<b>Webhook:</b><br><code><?php echo htmlspecialchars($webhookUrl, ENT_QUOTES, 'UTF-8'); ?></code><br>
<b>Header:</b> <code><?php echo htmlspecialchars($headerName, ENT_QUOTES, 'UTF-8'); ?>: &lt;token&gt;</code> (یا <code>Authorization: Bearer &lt;token&gt;</code>)<br>
<b>JSON نمونه Nomad:</b> <code>{"from":"…","text":"…","sentStamp":…,"receivedStamp":…,"sim":1}</code>
</div>

<form method="post">
<label class="toggle"><input type="checkbox" name="enabled" <?php echo !empty($config['enabled']) ? 'checked' : ''; ?>> فعال‌سازی دریافت Webhook</label>
<label class="toggle"><input type="checkbox" name="dry_run" <?php echo !isset($config['dry_run']) || !empty($config['dry_run']) ? 'checked' : ''; ?>> حالت آزمایشی (فقط ثبت و تطبیق — بدون دکمه اعمال خودکار از Webhook)</label>
<label class="toggle"><input type="checkbox" name="accept_unknown_sender" <?php echo !isset($config['accept_unknown_sender']) || !empty($config['accept_unknown_sender']) ? 'checked' : ''; ?>> پذیرش فرستندهٔ نامشخص (لیست خالی)</label>

<label for="webhook_token">توکن محرمانه Webhook</label>
<input class="ltr" type="password" id="webhook_token" name="webhook_token" value="" placeholder="<?php echo $tokenMasked !== '' ? htmlspecialchars($tokenMasked, ENT_QUOTES, 'UTF-8') : 'خالی = تولید خودکار'; ?>" autocomplete="off">

<label for="header_name">نام هدر توکن</label>
<input class="ltr" type="text" id="header_name" name="header_name" value="<?php echo htmlspecialchars($headerName, ENT_QUOTES, 'UTF-8'); ?>">

<label for="allowed_senders">فرستنده‌های مجاز (اختیاری، با کاما)</label>
<input class="ltr" type="text" id="allowed_senders" name="allowed_senders" value="<?php echo htmlspecialchars(implode(', ', (array)($config['allowed_senders'] ?? [])), ENT_QUOTES, 'UTF-8'); ?>" placeholder="مثلاً شماره سرویس پست‌بانک">

<label for="max_inbox_entries">حداکثر رکورد inbox</label>
<input class="ltr" type="number" id="max_inbox_entries" name="max_inbox_entries" min="50" value="<?php echo intval($config['max_inbox_entries'] ?? 500); ?>">

<button type="submit" name="save">ذخیره</button>
<button type="submit" name="regenerate_token" class="warn" onclick="return confirm('توکن قبلی در گوشی باطل می‌شود. ادامه؟');">توکن جدید</button>
</form>

<h3 style="margin-top:36px;color:#e2e8f0">پیامک‌های دریافتی</h3>
<table>
<thead>
<tr>
<th>زمان</th>
<th>وضعیت</th>
<th>مبلغ / کارت</th>
<th>سفارش</th>
<th>متن</th>
<th></th>
</tr>
</thead>
<tbody>
<?php if(count($inbox) === 0){ ?>
<tr><td colspan="6" style="color:#94a3b8">هنوز پیامکی ثبت نشده است.</td></tr>
<?php } ?>
<?php foreach($inbox as $row){
    $st = (string)($row['match_status'] ?? '');
    $cls = in_array($st, ['matched', 'applied', 'dry_run'], true) ? 'status-ok' : (in_array($st, ['ambiguous', 'duplicate', 'stale'], true) ? 'status-warn' : 'status-bad');
    $ts = intval($row['received_at'] ?? 0);
    $when = $ts > 0 ? date('Y/m/d H:i', $ts) : '-';
    $parse = is_array($row['parse'] ?? null) ? $row['parse'] : [];
    ?>
<tr>
<td><?php echo htmlspecialchars($when, ENT_QUOTES, 'UTF-8'); ?></td>
<td><span class="status <?php echo $cls; ?>"><?php echo htmlspecialchars(nomadMatchStatusLabel($st), ENT_QUOTES, 'UTF-8'); ?></span></td>
<td><?php echo number_format(intval($row['match_amount'] ?? 0)); ?> ریال<br><span class="ltr"><?php echo htmlspecialchars((string)($parse['card_last4'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span></td>
<td><?php echo htmlspecialchars((string)($row['match_order_user'] ?? ''), ENT_QUOTES, 'UTF-8'); ?><br><span class="ltr" style="font-size:11px;color:#94a3b8"><?php echo htmlspecialchars((string)($row['match_order_id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span></td>
<td><div class="sms-preview"><?php echo htmlspecialchars(mb_substr((string)($row['text'] ?? ''), 0, 400), ENT_QUOTES, 'UTF-8'); ?></div></td>
<td>
<?php if(in_array($st, ['matched', 'dry_run'], true) && intval($row['applied_at'] ?? 0) === 0){ ?>
<form method="post" style="margin:0" onsubmit="return confirm('فعال‌سازی اشتراک از طریق پرداخت آنی (مثل بله) انجام می‌شود. ادامه؟');">
<input type="hidden" name="inbox_id" value="<?php echo htmlspecialchars((string)($row['id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
<button type="submit" name="apply_match" class="test" <?php echo !empty($config['dry_run']) ? 'disabled title="ابتدا حالت آزمایشی را خاموش کنید"' : ''; ?>>تأیید تطبیق</button>
</form>
<?php } ?>
</td>
</tr>
<?php } ?>
</tbody>
</table>

<a class="back" href="<?php echo htmlspecialchars(function_exists('pnvAdminUrl') ? pnvAdminUrl() : 'index.php', ENT_QUOTES, 'UTF-8'); ?>">بازگشت</a>
</div>
</body>
</html>
