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
require_once __DIR__ . '/../bale_lib.php';

$config = baleLoadConfig();
$message = '';
$error = '';
$webhookUrl = function_exists('baleWebhookPublicUrl') ? baleWebhookPublicUrl() : 'https://panel.ticketin.ir/bale-webhook.php';
$webhookInfo = null;

if(isset($_POST['save'])){
    $token = trim((string)($_POST['bot_token'] ?? ''));

    // اگر فیلد خالی ارسال شد ولی قبلاً توکن داشته، توکن قبلی را نگه دار
    if($token === '' && trim((string)($config['bot_token'] ?? '')) !== ''){
        $token = $config['bot_token'];
    }

    $config = [
        'enabled' => isset($_POST['enabled']),
        'bot_token' => $token,
        'admin_chat_ids' => trim((string)($_POST['admin_chat_ids'] ?? '')),
        'bot_username' => trim((string)($_POST['bot_username'] ?? 'Jay24x7Pusbank_bot')),
        'pay_window_seconds' => max(60, intval($_POST['pay_window_seconds'] ?? 1800)),
        'match_grace_seconds' => max(0, intval($_POST['match_grace_seconds'] ?? 0)),
        'webhook_secret' => $config['webhook_secret'] ?? '',
        'forward_hint' => $config['forward_hint'] ?? ''
    ];

    baleSaveConfig($config);
    $message = 'تنظیمات بله ذخیره شد.';
}

if(isset($_POST['set_webhook'])){
    $config = baleLoadConfig();
    $result = baleSetWebhook($webhookUrl, $config);

    if(!empty($result['ok'])){
        $message = 'Webhook با موفقیت روی بله ثبت شد.';
    }
    else{
        $error = $result['description'] ?? 'ثبت Webhook ناموفق بود';
    }
}

if(isset($_POST['check_webhook'])){
    $config = baleLoadConfig();
    $webhookInfo = baleGetWebhookInfo($config);

    if(empty($webhookInfo['ok'])){
        $error = $webhookInfo['description'] ?? 'دریافت وضعیت Webhook ناموفق بود';
    }
    else{
        $info = $webhookInfo['result'] ?? [];
        $message = 'Webhook فعلی: ' . ($info['url'] ?? '—');
    }
}

if(isset($_POST['fetch_chat'])){
    $config = baleLoadConfig();
    $updates = baleApiRequest('getUpdates', [], $config);

    if(empty($updates['ok'])){
        $error = $updates['description'] ?? 'دریافت آپدیت از بله ناموفق بود';
    }
    else{
        $found = [];
        foreach(($updates['result'] ?? []) as $upd){
            $msg = $upd['message'] ?? ($upd['edited_message'] ?? null);
            if(!is_array($msg)){ continue; }
            $cid = (string)($msg['chat']['id'] ?? '');
            if($cid !== ''){
                $found[$cid] = true;
            }
        }

        if(count($found) === 0){
            $error = 'هنوز پیامی پیدا نشد. یک‌بار در بازو /start بزنید و دوباره همین دکمه را بزنید.';
        }
        else{
            $ids = array_keys($found);
            $config['admin_chat_ids'] = implode(',', $ids);
            baleSaveConfig($config);
            $message = 'شناسه چت ذخیره شد: ' . implode(', ', $ids);
        }
    }
}

if(isset($_POST['test'])){
    $config = baleLoadConfig();
    $me = baleGetMe($config);

    if(empty($me['ok'])){
        $error = $me['description'] ?? 'ارتباط با بله برقرار نشد';
    }
    else{
        $ids = baleAdminChatIds($config);

        if(count($ids) === 0){
            $error = 'شناسه چت خالی است. یک‌بار در بازو /start بزنید تا خودکار ذخیره شود، یا دستی وارد کنید.';
        }
        else{
            $failed = false;
            foreach($ids as $chatId){
                $sent = baleSendMessage($chatId, "✅ تست بازوی پرداخت آنی\nربات آنلاین است.", [], $config);
                if(empty($sent['ok'])){
                    $failed = true;
                    $error = $sent['description'] ?? 'ارسال پیام آزمایشی ناموفق';
                }
            }

            if(!$failed){
                $uname = $me['result']['username'] ?? '';
                $message = 'ارتباط برقرار شد' . ($uname !== '' ? (' (@' . $uname . ')') : '') . ' و پیام آزمایشی ارسال شد.';
            }
        }
    }
}

$parsePreview = null;

if(isset($_POST['parse_test'])){
    require_once __DIR__ . '/../instant_pay_lib.php';
    $sample = trim((string)($_POST['parse_sample'] ?? ''));

    if($sample === ''){
        $error = 'متن نمونه واریز را وارد کنید.';
    }
    else{
        $amounts = baleExtractRialAmounts($sample);
        $looksDeposit = baleLooksLikeDeposit($sample);
        $looksPostBank = baleLooksLikePostBankNotice($sample);
        $matchResult = instantPayHandleDepositText($sample, pnvNowParts());

        $parsePreview = [
            'amounts' => $amounts,
            'looks_deposit' => $looksDeposit,
            'looks_postbank' => $looksPostBank,
            'match' => $matchResult,
        ];
    }
}

$webhookLogTail = function_exists('baleReadWebhookLogTail') ? baleReadWebhookLogTail(25) : [];

$tokenMasked = trim((string)($config['bot_token'] ?? ''));
if($tokenMasked !== ''){
    $tokenMasked = substr($tokenMasked, 0, 6) . str_repeat('•', max(8, strlen($tokenMasked) - 10)) . substr($tokenMasked, -4);
}

?>
<!DOCTYPE html>
<html lang="fa">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>تنظیمات بله — پرداخت آنی</title>
<style>
*{box-sizing:border-box}
body{margin:0;padding:20px;background:#0f172a;color:#fff;font-family:tahoma;direction:rtl}
.box{width:100%;max-width:760px;margin:auto;background:#1e293b;padding:30px;border-radius:20px}
h2{text-align:center;margin:0 0 12px;font-size:26px}
.sub{text-align:center;color:#94a3b8;margin:0 0 28px;line-height:1.8;font-size:14px}
label{display:block;margin:18px 0 8px;font-size:15px;color:#e2e8f0}
input{width:100%;border:0;border-radius:12px;padding:14px;background:#0f172a;color:#fff;font:inherit;font-size:15px}
input.ltr{direction:ltr;text-align:left}
.toggle{display:flex;align-items:center;gap:10px;cursor:pointer;margin:0 0 22px}
.toggle input{width:20px;height:20px;margin:0}
.hint{background:#172554;border-radius:12px;padding:14px;color:#cbd5e1;font-size:14px;line-height:2;margin-top:10px}
.hint code{direction:ltr;display:inline-block;color:#93c5fd;word-break:break-all}
.msg,.err{padding:14px;border-radius:12px;line-height:1.8;margin-bottom:18px}
.msg{background:#166534}.err{background:#991b1b}
button,.back{display:block;width:100%;border:0;border-radius:12px;padding:15px;background:#22c55e;color:#fff;font:inherit;font-size:17px;cursor:pointer;text-align:center;text-decoration:none;margin-top:14px}
.test{background:#2563eb}.hook{background:#7c3aed}.back{background:#334155;margin-top:20px}
.steps{background:#0f172a;border:1px solid #334155;border-radius:14px;padding:16px;line-height:2;color:#cbd5e1;font-size:14px;margin-bottom:18px}
.logbox{background:#0f172a;border:1px solid #334155;border-radius:12px;padding:12px;font-family:monospace;font-size:12px;line-height:1.7;direction:ltr;text-align:left;max-height:240px;overflow:auto;color:#cbd5e1;white-space:pre-wrap;word-break:break-word}
textarea{width:100%;min-height:120px;border:0;border-radius:12px;padding:14px;background:#0f172a;color:#fff;font:inherit;font-size:14px;direction:rtl}
.parse{background:#0ea5e9}
@media(max-width:600px){body{padding:10px}.box{padding:22px 16px}}
</style>
</head>
<body>
<?php adminQuickNavStyles(); adminQuickNav('bale'); ?>

<div class="box">
<h2>بله — پرداخت آنی</h2>
<p class="sub">کارت‌به‌کارت با کد ۴ رقمی + فوروارد پیام پست‌بانک</p>

<?php if($message !== ''){ ?><div class="msg"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div><?php } ?>
<?php if($error !== ''){ ?><div class="err"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div><?php } ?>

<div class="steps">
۱. در بله به بازو <b>@Jay24x7Pusbank_bot</b> بروید و <b>/start</b> بزنید<br>
۲. تنظیمات را ذخیره کنید، «فعال‌سازی» را تیک بزنید، و Webhook را ثبت کنید<br>
۳. هر پیام واریز <b>@postbank_bot</b> را به بازو <b>فوروارد</b> کنید (در چت خصوصی یا گروه)<br>
۴. اگر ۴ رقم آخر مبلغ با سفارش باز یکی باشد، پرداخت خودکار تأیید می‌شود
</div>

<?php if(is_array($parsePreview)){ ?>
<div class="hint">
<b>نتیجه تست پارس:</b><br>
مبالغ: <?php echo htmlspecialchars(json_encode($parsePreview['amounts'] ?? [], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?><br>
شبیه واریز: <?php echo !empty($parsePreview['looks_deposit']) ? 'بله' : 'خیر'; ?> |
پست‌بانک: <?php echo !empty($parsePreview['looks_postbank']) ? 'بله' : 'خیر'; ?><br>
مچ: <?php echo htmlspecialchars(json_encode($parsePreview['match'] ?? [], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>
</div>
<?php } ?>

<?php if(count($webhookLogTail) > 0){ ?>
<label>آخرین رویدادهای Webhook</label>
<div class="logbox"><?php echo htmlspecialchars(implode("\n", $webhookLogTail), ENT_QUOTES, 'UTF-8'); ?></div>
<?php } else { ?>
<div class="hint">لاگ Webhook خالی است. بعد از فوروارد پیام واریز، اینجا رویدادها دیده می‌شوند.</div>
<?php } ?>

<form method="post">
<label class="toggle">
<input type="checkbox" name="enabled" <?php echo !empty($config['enabled']) ? 'checked' : ''; ?>>
<span>فعال‌سازی پرداخت آنی بله</span>
</label>

<label for="bot_username">یوزرنیم بازو</label>
<input class="ltr" type="text" id="bot_username" name="bot_username" value="<?php echo htmlspecialchars($config['bot_username'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">

<label for="bot_token">توکن بازو</label>
<input class="ltr" type="password" id="bot_token" name="bot_token" value="" placeholder="<?php echo $tokenMasked !== '' ? htmlspecialchars($tokenMasked, ENT_QUOTES, 'UTF-8') : 'توکن را وارد کنید'; ?>" autocomplete="off">
<div class="hint">برای امنیت، توکن ذخیره‌شده اینجا کامل نشان داده نمی‌شود. اگر خالی بگذارید و ذخیره کنید، توکن قبلی حفظ می‌شود.</div>

<label for="admin_chat_ids">شناسه چت مدیر</label>
<input class="ltr" type="text" id="admin_chat_ids" name="admin_chat_ids" value="<?php echo htmlspecialchars($config['admin_chat_ids'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="با /start یا دکمه زیر پر می‌شود">
<div class="hint">اگر خالی است: در بله به بازو `/start` بزنید، بعد همین صفحه را رفرش کنید یا دکمه «خواندن شناسه از بله» را بزنید.</div>

<label for="pay_window_seconds">مهلت پرداخت (ثانیه)</label>
<input class="ltr" type="number" id="pay_window_seconds" name="pay_window_seconds" min="60" value="<?php echo intval($config['pay_window_seconds'] ?? 1800); ?>">
<div class="hint">پیش‌فرض ۱۸۰۰ ثانیه = ۳۰ دقیقه. بعد از این زمان سفارش «منقضی» می‌شود ولی هنوز برای مدتی با فوروارد پیام پست‌بانک قابل تأیید است.</div>

<label for="match_grace_seconds">مهلت اضافه برای مچ واریز (ثانیه)</label>
<input class="ltr" type="number" id="match_grace_seconds" name="match_grace_seconds" min="0" value="<?php echo intval($config['match_grace_seconds'] ?? 0); ?>">
<div class="hint">۰ = خودکار (حداقل ۳۰ دقیقه یا دوبرابر مهلت پرداخت). اگر کاربر دیر واریز کرد، در این بازه هنوز تأیید خودکار کار می‌کند.</div>

<div class="hint">آدرس Webhook:<br><code><?php echo htmlspecialchars($webhookUrl, ENT_QUOTES, 'UTF-8'); ?></code></div>

<?php
$liveWebhook = baleGetWebhookInfo($config);
if(!empty($liveWebhook['ok'])){
    $info = $liveWebhook['result'] ?? [];
    $liveUrl = trim((string)($info['url'] ?? ''));
    $pending = intval($info['pending_update_count'] ?? 0);
    $lastError = trim((string)($info['last_error_message'] ?? ''));
?>
<div class="hint">
وضعیت Webhook در بله:<br>
<code><?php echo htmlspecialchars($liveUrl !== '' ? $liveUrl : 'ثبت نشده', ENT_QUOTES, 'UTF-8'); ?></code><br>
<?php if($pending > 0){ ?>پیام‌های در صف: <?php echo $pending; ?><br><?php } ?>
<?php if($lastError !== ''){ ?><span style="color:#fecaca">آخرین خطا: <?php echo htmlspecialchars($lastError, ENT_QUOTES, 'UTF-8'); ?></span><?php } ?>
</div>
<?php } ?>

<button type="submit" name="save">ذخیره تنظیمات</button>
<button type="submit" name="fetch_chat" class="test">خواندن شناسه چت از بله</button>
<button type="submit" name="set_webhook" class="hook">ثبت Webhook در بله</button>
<button type="submit" name="check_webhook" class="hook">بررسی Webhook</button>
<button type="submit" name="test" class="test">تست ارتباط و ارسال پیام</button>
</form>

<form method="post" style="margin-top:18px">
<label for="parse_sample">تست پارس پیام واریز (کپی متن @postbank_bot)</label>
<textarea id="parse_sample" name="parse_sample" placeholder="متن پیام پست‌بانک را اینجا بچسبانید"><?php echo htmlspecialchars((string)($_POST['parse_sample'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
<button type="submit" name="parse_test" class="parse">تست پارس مبلغ</button>
</form>

<a class="back" href="<?php echo htmlspecialchars(function_exists('pnvAdminUrl') ? pnvAdminUrl() : 'index.php', ENT_QUOTES, 'UTF-8'); ?>">بازگشت به مدیریت</a>
</div>
<?php adminPageEnd(['active' => 'bale', 'more_mode' => 'sheet']); ?>
</body>
</html>
