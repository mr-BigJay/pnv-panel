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
require_once __DIR__ . '/../telegram_lib.php';
require_once __DIR__ . '/../telegram_user_lib.php';

$config = telegramLoadConfig();
$message = '';
$error = '';

if(isset($_POST['save'])){

    $oldConfig = telegramLoadConfig();
    $config = [
        'enabled' => isset($_POST['enabled']),
        'bot_token' => trim($_POST['bot_token'] ?? ''),
        'bot_username' => trim(ltrim((string)($_POST['bot_username'] ?? ''), '@')),
        'admin_chat_ids' => trim($_POST['admin_chat_ids'] ?? ''),
        'panel_url' => rtrim(trim((string)($_POST['panel_url'] ?? '')), '/'),
        'notify_expire_days' => max(1, intval($_POST['notify_expire_days'] ?? 3)),
        'notify_traffic_pct' => max(1, min(100, intval($_POST['notify_traffic_pct'] ?? 20))),
        'local_proxy_urls' => telegramLinesToArray($_POST['local_proxy_urls'] ?? ''),
        'xray_vless_uris' => telegramLinesToArray($_POST['xray_vless_uris'] ?? '')
    ];

    $config = tgUserHandleBotConfigChange($oldConfig, $config);
    telegramSaveConfig($config);
    $message = 'تنظیمات بات تلگرام ذخیره شد.';

    if(
        trim((string)($oldConfig['bot_token'] ?? '')) !== trim((string)($config['bot_token'] ?? ''))
        || trim(ltrim((string)($oldConfig['bot_username'] ?? ''), '@')) !== trim(ltrim((string)($config['bot_username'] ?? ''), '@'))
    ){
        $message .= ' لینک‌های اتصال قبلی باطل شدند؛ کاربران باید دوباره «اتصال به تلگرام» را بزنند.';
    }
}

if(isset($_POST['test'])){

    $config = telegramLoadConfig();
    $result = telegramSetCommands($config);

    if(empty($result['ok'])){
        $error = $result['description'] ?? 'ارسال پیام آزمایشی ناموفق بود';
    }
    else{
        $sent = [];

        foreach(telegramAdminChatIds($config) as $chatId){
            telegramShowHome($chatId, $config, null);
            $sent[] = ['ok' => true];
        }

        $failed = false;
        $details = [];

        foreach($sent as $item){
            if(empty($item['ok'])){
                $failed = true;
                $details[] = $item['description'] ?? 'خطای نامشخص';
            }
        }

        if($failed || count($sent) === 0){
            $error = 'فرمان‌های بات ثبت شد، اما پیام آزمایشی ارسال نشد.';
            if(count($details) > 0){
                $error .= ' ' . implode(' | ', $details);
            }
            else{
                $error .= ' شناسه چت را بررسی کنید و حتماً یک‌بار /start را در بات بزنید.';
            }
        }
        else{
            $message = 'فرمان‌ها ثبت و پیام آزمایشی ارسال شد.';
        }
    }
}

function telegramTextAreaValue($items){
    return htmlspecialchars(implode("\n", is_array($items) ? $items : []), ENT_QUOTES, 'UTF-8');
}

$linkedStats = function_exists('tgUserAdminLinkedStats') ? tgUserAdminLinkedStats() : [
    'total_users' => 0,
    'linked_count' => 0,
    'linked_users' => [],
];

?>

<!DOCTYPE html>
<html lang="fa">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>تنظیمات بات تلگرام</title>
<style>
*{box-sizing:border-box}
body{margin:0;padding:20px;background:#0f172a;color:#fff;font-family:tahoma;direction:rtl}
.box{width:100%;max-width:760px;margin:auto;background:#1e293b;padding:30px;border-radius:20px}
h2{text-align:center;margin:0 0 28px;font-size:26px}
label{display:block;margin:18px 0 8px;font-size:15px;color:#e2e8f0}
input,textarea{width:100%;border:0;border-radius:12px;padding:14px;background:#0f172a;color:#fff;font-family:inherit;font-size:15px;line-height:1.8}
textarea{min-height:100px;resize:vertical;direction:ltr;text-align:left}
.tokenRow{display:flex;gap:10px;align-items:stretch}
.tokenRow input{flex:1;direction:ltr;text-align:left}
.tokenRow button{width:auto;margin:0;padding:0 18px;background:#475569;font-size:14px;white-space:nowrap}
.toggle{display:flex;align-items:center;gap:10px;cursor:pointer;margin:0 0 22px}
.toggle input{width:20px;height:20px;margin:0}
.hint{background:#172554;border-radius:12px;padding:14px;color:#cbd5e1;font-size:14px;line-height:2;margin-top:10px}
.hint code{direction:ltr;display:inline-block;color:#93c5fd;word-break:break-all}
.msg,.err{padding:14px;border-radius:12px;line-height:1.8;margin-bottom:18px}
.msg{background:#166534}.err{background:#991b1b}
button,.back{display:block;width:100%;border:0;border-radius:12px;padding:15px;background:#22c55e;color:#fff;font:inherit;font-size:17px;cursor:pointer;text-align:center;text-decoration:none;margin-top:14px}
.test{background:#2563eb}.back{background:#334155;margin-top:20px}
.statsGrid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin:0 0 22px}
.statCard{background:#0f172a;border:1px solid #334155;border-radius:14px;padding:16px;text-align:center}
.statNum{font-size:32px;font-weight:700;color:#4ade80;line-height:1.2}
.statLabel{font-size:13px;color:#94a3b8;margin-top:6px}
.linkedBox{background:#0f172a;border:1px solid #334155;border-radius:14px;padding:14px;margin-bottom:22px}
.linkedBox h3{margin:0 0 12px;font-size:16px;color:#e2e8f0}
.linkedTable{width:100%;border-collapse:collapse;font-size:13px}
.linkedTable th,.linkedTable td{padding:10px 8px;border-bottom:1px solid #1e293b;text-align:right}
.linkedTable th{color:#94a3b8;font-weight:600}
.linkedTable tr:last-child td{border-bottom:0}
.linkedEmpty{color:#94a3b8;font-size:14px;line-height:1.8;padding:8px 0}
@media(max-width:600px){body{padding:10px}.box{padding:22px 16px;border-radius:16px}h2{font-size:22px}.tokenRow{flex-direction:column}.tokenRow button{width:100%;padding:12px}.statsGrid{grid-template-columns:1fr}}
</style>
</head>
<body>
<?php adminQuickNavStyles(); adminQuickNav('telegram'); ?>

<div class="box">
<h2>تنظیمات بات تلگرام</h2>

<?php if($message !== ''){ ?><div class="msg"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div><?php } ?>
<?php if($error !== ''){ ?><div class="err"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div><?php } ?>

<div class="statsGrid">
<div class="statCard">
<div class="statNum"><?php echo (int)$linkedStats['linked_count']; ?></div>
<div class="statLabel">کاربر متصل به ربات</div>
</div>
<div class="statCard">
<div class="statNum"><?php echo (int)$linkedStats['total_users']; ?></div>
<div class="statLabel">کل کاربران پنل</div>
</div>
</div>

<div class="linkedBox">
<h3>کاربران متصل به ربات اطلاع‌رسانی</h3>
<?php if(count($linkedStats['linked_users'] ?? []) === 0){ ?>
<div class="linkedEmpty">هنوز کاربری از داشبورد «اتصال تلگرام» را فعال نکرده است.</div>
<?php } else { ?>
<div style="overflow-x:auto">
<table class="linkedTable">
<thead>
<tr>
<th>نام کاربری</th>
<th>تلگرام</th>
<th>تاریخ اتصال</th>
</tr>
</thead>
<tbody>
<?php foreach($linkedStats['linked_users'] as $row){
    $tgUser = trim((string)($row['telegram_username'] ?? ''));
    $tgLabel = $tgUser !== '' ? '@' . $tgUser : '—';
?>
<tr>
<td><?php echo htmlspecialchars($row['username'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
<td><?php echo htmlspecialchars($tgLabel, ENT_QUOTES, 'UTF-8'); ?></td>
<td><?php echo htmlspecialchars($row['linked_at'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></td>
</tr>
<?php } ?>
</tbody>
</table>
</div>
<?php } ?>
</div>

<form method="post">
<label class="toggle">
<input type="checkbox" name="enabled" <?php echo !empty($config['enabled']) ? 'checked' : ''; ?>>
<span>فعال‌سازی اعلان‌های تلگرام</span>
</label>

<label for="bot_token">توکن بات (BotFather)</label>
<div class="tokenRow">
<input type="text" id="bot_token" name="bot_token" autocomplete="off" spellcheck="false" value="<?php echo htmlspecialchars($config['bot_token'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="123456789:AAHxxxxxxxx">
<button type="button" id="toggleTokenBtn" onclick="toggleTokenVisibility()">مخفی کردن</button>
</div>
<div class="hint">توکن ذخیره‌شده اینجا نمایش داده می‌شود و می‌توانید آن را ویرایش کنید.</div>

<label for="admin_chat_ids">شناسه چت مدیران</label>
<input type="text" id="admin_chat_ids" name="admin_chat_ids" value="<?php echo htmlspecialchars($config['admin_chat_ids'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="مثال: 123456789 یا -1001234567890">
<div class="hint">برای چند مدیر یا گروه، شناسه‌ها را با ویرگول جدا کنید. فقط همین شناسه‌ها منوی مدیریت را می‌بینند. کاربران عادی با اتصال از داشبورد، منوی جداگانه دریافت می‌کنند.</div>

<label for="bot_username">نام کاربری بات (اختیاری)</label>
<input type="text" id="bot_username" name="bot_username" value="<?php echo htmlspecialchars($config['bot_username'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="YourPanelBot">
<div class="hint">برای لینک اتصال کاربران استفاده می‌شود. بعد از تغییر توکن یا نام ربات، حتماً ذخیره کنید. اگر خالی باشد از API تلگرام خوانده می‌شود.</div>

<label for="panel_url">آدرس پنل کاربری</label>
<input type="text" id="panel_url" name="panel_url" value="<?php echo htmlspecialchars($config['panel_url'] ?? 'https://panel.ticketin.ir', ENT_QUOTES, 'UTF-8'); ?>" placeholder="https://panel.ticketin.ir">

<label for="notify_expire_days">هشدار انقضا (چند روز قبل)</label>
<input type="number" id="notify_expire_days" name="notify_expire_days" min="1" max="30" value="<?php echo (int)($config['notify_expire_days'] ?? 3); ?>">

<label for="notify_traffic_pct">هشدار حجم (درصد باقیمانده)</label>
<input type="number" id="notify_traffic_pct" name="notify_traffic_pct" min="1" max="100" value="<?php echo (int)($config['notify_traffic_pct'] ?? 20); ?>">
<div class="hint">وقتی حجم باقیمانده به این درصد یا کمتر برسد، اعلان ارسال می‌شود. cron روزانه: <code>php scripts/telegram_notify_expiry.php</code></div>

<label for="local_proxy_urls">آدرس پراکسی محلی Xray (هر خط یک مورد)</label>
<textarea id="local_proxy_urls" name="local_proxy_urls" placeholder="socks5h://127.0.0.1:10808"><?php echo telegramTextAreaValue($config['local_proxy_urls'] ?? []); ?></textarea>
<div class="hint">PHP نمی‌تواند لینک VLESS را مستقیماً به تلگرام وصل کند. باید Xray روی همین سرور یک SOCKS محلی بسازد؛ نمونه: <code>socks5h://127.0.0.1:10808</code>. این پراکسی فقط در درخواست‌های بات تلگرام استفاده می‌شود.</div>

<label for="xray_vless_uris">لینک‌های VLESS پراکسی Xray (هر خط یک مورد)</label>
<textarea id="xray_vless_uris" name="xray_vless_uris" placeholder="vless://..."><?php echo telegramTextAreaValue($config['xray_vless_uris'] ?? []); ?></textarea>
<div class="hint">این لینک‌ها برای ثبت و مدیریت پراکسی‌های اختصاصی بات هستند. راه‌اندازی سرویس Xray محلی از آن‌ها باید یک‌بار روی سرور انجام شود؛ صرف ذخیره لینک، پراکسی را فعال نمی‌کند.</div>

<button type="submit" name="save">ذخیره تنظیمات</button>
<button type="submit" name="test" class="test">ارسال پیام آزمایشی و ثبت منوی بات</button>
</form>

<a class="back" href="<?php echo htmlspecialchars(function_exists('pnvAdminUrl') ? pnvAdminUrl() : 'index.php', ENT_QUOTES, 'UTF-8'); ?>">بازگشت به مدیریت</a>
</div>

<script>
function toggleTokenVisibility(){
    const input = document.getElementById('bot_token');
    const btn = document.getElementById('toggleTokenBtn');
    if(input.type === 'text'){
        input.type = 'password';
        btn.textContent = 'نمایش';
    } else {
        input.type = 'text';
        btn.textContent = 'مخفی کردن';
    }
}
</script>
</body>
</html>
