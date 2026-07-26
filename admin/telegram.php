<?php

session_start();

if(!isset($_SESSION['admin'])){
header("Location: index.php");
exit;
}

require_once __DIR__ . '/../telegram_lib.php';

$config = telegramLoadConfig();
$message = '';
$error = '';

if(isset($_POST['save'])){

    $token = trim($_POST['bot_token'] ?? '');

    if($token === ''){
        $token = $config['bot_token'] ?? '';
    }

    $config = [
        'enabled' => isset($_POST['enabled']),
        'bot_token' => $token,
        'admin_chat_ids' => trim($_POST['admin_chat_ids'] ?? ''),
        'local_proxy_urls' => telegramLinesToArray($_POST['local_proxy_urls'] ?? ''),
        'xray_vless_uris' => telegramLinesToArray($_POST['xray_vless_uris'] ?? '')
    ];

    telegramSaveConfig($config);
    $message = 'تنظیمات بات تلگرام ذخیره شد.';
}

if(isset($_POST['test'])){

    $config = telegramLoadConfig();
    $result = telegramSetCommands($config);

    if(empty($result['ok'])){
        $error = $result['description'] ?? 'ارسال پیام آزمایشی ناموفق بود';
    }
    else{
        $sent = telegramSendToAdmins(
            "✅ اتصال بات تلگرام برقرار است.\nدکمه «پیام کاربران» یا دستور /messages را برای مشاهده پیام‌های خوانده‌نشده بزنید.",
            [
                'reply_markup' => json_encode([
                    'keyboard' => [[['text' => 'پیام کاربران']]],
                    'resize_keyboard' => true
                ], JSON_UNESCAPED_UNICODE)
            ],
            [],
            $config
        );

        $failed = false;

        foreach($sent as $item){
            if(empty($item['ok'])){
                $failed = true;
            }
        }

        if($failed || count($sent) === 0){
            $error = 'فرمان‌های بات ثبت شد، اما پیام آزمایشی ارسال نشد. شناسه چت و پراکسی محلی را بررسی کنید.';
        }
        else{
            $message = 'فرمان‌ها ثبت و پیام آزمایشی ارسال شد.';
        }
    }
}

function telegramTextAreaValue($items){
    return htmlspecialchars(implode("\n", is_array($items) ? $items : []), ENT_QUOTES, 'UTF-8');
}

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
.toggle{display:flex;align-items:center;gap:10px;cursor:pointer;margin:0 0 22px}
.toggle input{width:20px;height:20px;margin:0}
.hint{background:#172554;border-radius:12px;padding:14px;color:#cbd5e1;font-size:14px;line-height:2;margin-top:10px}
.hint code{direction:ltr;display:inline-block;color:#93c5fd;word-break:break-all}
.msg,.err{padding:14px;border-radius:12px;line-height:1.8;margin-bottom:18px}
.msg{background:#166534}.err{background:#991b1b}
button,.back{display:block;width:100%;border:0;border-radius:12px;padding:15px;background:#22c55e;color:#fff;font:inherit;font-size:17px;cursor:pointer;text-align:center;text-decoration:none;margin-top:14px}
.test{background:#2563eb}.back{background:#334155;margin-top:20px}
@media(max-width:600px){body{padding:10px}.box{padding:22px 16px;border-radius:16px}h2{font-size:22px}}
</style>
</head>
<body>
<div class="box">
<h2>تنظیمات بات تلگرام</h2>

<?php if($message !== ''){ ?><div class="msg"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div><?php } ?>
<?php if($error !== ''){ ?><div class="err"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div><?php } ?>

<form method="post">
<label class="toggle">
<input type="checkbox" name="enabled" <?php echo !empty($config['enabled']) ? 'checked' : ''; ?>>
<span>فعال‌سازی اعلان‌های تلگرام</span>
</label>

<label for="bot_token">توکن بات (BotFather)</label>
<input type="password" id="bot_token" name="bot_token" autocomplete="new-password" placeholder="<?php echo !empty($config['bot_token']) ? 'برای حفظ توکن قبلی خالی بگذارید' : '123456:ABC...'; ?>">

<label for="admin_chat_ids">شناسه چت مدیران</label>
<input type="text" id="admin_chat_ids" name="admin_chat_ids" value="<?php echo htmlspecialchars($config['admin_chat_ids'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="مثال: 123456789 یا -1001234567890">
<div class="hint">برای چند مدیر یا گروه، شناسه‌ها را با ویرگول جدا کنید. فقط همین شناسه‌ها می‌توانند منوی بات را استفاده کنند.</div>

<label for="local_proxy_urls">آدرس پراکسی محلی Xray (هر خط یک مورد)</label>
<textarea id="local_proxy_urls" name="local_proxy_urls" placeholder="socks5h://127.0.0.1:10808"><?php echo telegramTextAreaValue($config['local_proxy_urls'] ?? []); ?></textarea>
<div class="hint">PHP نمی‌تواند لینک VLESS را مستقیماً به تلگرام وصل کند. باید Xray روی همین سرور یک SOCKS محلی بسازد؛ نمونه: <code>socks5h://127.0.0.1:10808</code>. این پراکسی فقط در درخواست‌های بات تلگرام استفاده می‌شود.</div>

<label for="xray_vless_uris">لینک‌های VLESS پراکسی Xray (هر خط یک مورد)</label>
<textarea id="xray_vless_uris" name="xray_vless_uris" placeholder="vless://..."><?php echo telegramTextAreaValue($config['xray_vless_uris'] ?? []); ?></textarea>
<div class="hint">این لینک‌ها برای ثبت و مدیریت پراکسی‌های اختصاصی بات هستند. راه‌اندازی سرویس Xray محلی از آن‌ها باید یک‌بار روی سرور انجام شود؛ صرف ذخیره لینک، پراکسی را فعال نمی‌کند.</div>

<button type="submit" name="save">ذخیره تنظیمات</button>
<button type="submit" name="test" class="test">ارسال پیام آزمایشی و ثبت منوی بات</button>
</form>

<a class="back" href="index.php">بازگشت به مدیریت</a>
</div>
</body>
</html>
