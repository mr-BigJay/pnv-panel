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
require_once __DIR__ . '/../instant_pay_lib.php';

$config = baleLoadConfig();
$message = '';
$error = '';
$depositTestResult = null;
$webhookUrl = 'https://panel.ticketin.ir/bale-webhook.php';
$parserVersion = function_exists('baleParserVersion') ? baleParserVersion() : 'unknown';

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
        'pay_window_seconds' => max(60, intval($_POST['pay_window_seconds'] ?? 600)),
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
                $sent = baleSendMessage($chatId, "✅ تست بازوی پرداخت آنی\nربات آنلاین است.\nparser: {$parserVersion}", [], $config);
                if(empty($sent['ok'])){
                    $failed = true;
                    $error = $sent['description'] ?? 'ارسال پیام آزمایشی ناموفق';
                }
            }

            if(!$failed){
                $uname = $me['result']['username'] ?? '';
                $message = 'ارتباط برقرار شد' . ($uname !== '' ? (' (@' . $uname . ')') : '') . ' و پیام آزمایشی ارسال شد. parser=' . $parserVersion;
            }
        }
    }
}

if(isset($_POST['test_deposit'])){
    $depositText = trim((string)($_POST['deposit_text'] ?? ''));
    $doConfirm = !empty($_POST['do_confirm']);

    if($depositText === ''){
        $error = 'متن پیام پست‌بانک را وارد کنید.';
    }
    else{
        $parsed = baleExtractRialAmounts($depositText);
        $waiting = [];
        if(function_exists('instantPayLoad')){
            foreach(instantPayExpireDue() as $it){
                if(($it['status'] ?? '') === 'waiting'){
                    $waiting[] = [
                        'user' => $it['user'] ?? '',
                        'amount' => intval($it['amount'] ?? 0),
                        'code' => $it['code'] ?? '',
                        'plan' => $it['plan'] ?? '',
                        'remaining' => max(0, intval($it['expires_at'] ?? 0) - time())
                    ];
                }
            }
        }

        if($doConfirm){
            $run = instantPayHandleDepositText($depositText, [
                'date' => date('Y/m/d'),
                'time' => date('H:i')
            ]);
            $depositTestResult = [
                'mode' => 'confirm',
                'parsed' => $parsed,
                'waiting' => $waiting,
                'result' => $run
            ];
            if(!empty($run['ok'])){
                $message = 'پرداخت مچ و تأیید شد.';
            }
            else{
                $error = $run['error'] ?? 'مچ نشد';
            }
        }
        else{
            $exactHits = [];
            foreach($parsed as $amt){
                if(function_exists('instantPayMatchAmountExact')){
                    $hit = instantPayMatchAmountExact($amt);
                    if($hit){
                        $exactHits[] = [
                            'parsed' => $amt,
                            'user' => $hit['user'] ?? '',
                            'amount' => intval($hit['amount'] ?? 0),
                            'code' => $hit['code'] ?? ''
                        ];
                    }
                }
            }
            $depositTestResult = [
                'mode' => 'dry',
                'parsed' => $parsed,
                'waiting' => $waiting,
                'exact_hits' => $exactHits,
                'is_postbank' => baleLooksLikePostBankNotice($depositText),
                'looks_deposit' => baleLooksLikeDeposit($depositText)
            ];
            $message = 'تست خواندن مبلغ انجام شد (تأیید واقعی زده نشد).';
        }
    }
}

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
textarea{width:100%;min-height:140px;border:0;border-radius:12px;padding:14px;background:#0f172a;color:#fff;font:inherit;font-size:14px;line-height:1.8;resize:vertical}
.pre{background:#0f172a;border:1px solid #334155;border-radius:12px;padding:12px;direction:ltr;text-align:left;white-space:pre-wrap;word-break:break-word;font-size:12px;line-height:1.7;color:#cbd5e1;margin-top:12px}
.ver{color:#86efac;font-size:13px;margin:0 0 16px;text-align:center}
@media(max-width:600px){body{padding:10px}.box{padding:22px 16px}}
</style>
</head>
<body>
<?php adminQuickNavStyles(); adminQuickNav('bale'); ?>

<div class="box">
<h2>بله — پرداخت آنی</h2>
<p class="sub">کارت‌به‌کارت با کد ۴ رقمی + فوروارد پیام پست‌بانک</p>
<p class="ver">parser: <?php echo htmlspecialchars($parserVersion, ENT_QUOTES, 'UTF-8'); ?></p>

<?php if($message !== ''){ ?><div class="msg"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div><?php } ?>
<?php if($error !== ''){ ?><div class="err"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div><?php } ?>

<div class="steps">
۱. در بله به بازو بروید و <b>/start</b> بزنید<br>
۲. تنظیمات را ذخیره و Webhook را ثبت کنید<br>
۳. هر پیام واریز <b>@postbank_bot</b> را به بازو <b>فوروارد</b> کنید<br>
۴. اگر ۴ رقم آخر مبلغ با سفارش باز یکی باشد، پرداخت خودکار تأیید می‌شود
</div>

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
<input class="ltr" type="number" id="pay_window_seconds" name="pay_window_seconds" min="60" value="<?php echo intval($config['pay_window_seconds'] ?? 600); ?>">

<div class="hint">آدرس Webhook:<br><code><?php echo htmlspecialchars($webhookUrl, ENT_QUOTES, 'UTF-8'); ?></code></div>

<button type="submit" name="save">ذخیره تنظیمات</button>
<button type="submit" name="fetch_chat" class="test">خواندن شناسه چت از بله</button>
<button type="submit" name="set_webhook" class="hook">ثبت Webhook در بله</button>
<button type="submit" name="test" class="test">تست ارتباط و ارسال پیام</button>
</form>

<form method="post" style="margin-top:28px;padding-top:18px;border-top:1px solid #334155">
<h2 style="font-size:20px;margin-bottom:8px">تست متن واریز پست‌بانک</h2>
<p class="sub" style="margin-bottom:14px">همان پیام را کپی کنید و ببینید مبلغ درست خوانده می‌شود یا نه</p>
<label for="deposit_text">متن پیام</label>
<textarea id="deposit_text" name="deposit_text" placeholder="پست بانک
واريز به کارت: 6156
+998,190
...
مانده: ... ريال"><?php echo htmlspecialchars((string)($_POST['deposit_text'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
<label class="toggle" style="margin-top:12px">
<input type="checkbox" name="do_confirm" value="1">
<span>اگر مچ شد، واقعاً تأیید کن (اشتراک بساز)</span>
</label>
<button type="submit" name="test_deposit" class="test">بررسی مبلغ / مچ</button>
</form>

<?php if(is_array($depositTestResult)){ ?>
<div class="pre"><?php echo htmlspecialchars(json_encode($depositTestResult, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), ENT_QUOTES, 'UTF-8'); ?></div>
<?php } ?>

<a class="back" href="<?php echo htmlspecialchars(function_exists('pnvAdminUrl') ? pnvAdminUrl() : 'index.php', ENT_QUOTES, 'UTF-8'); ?>">بازگشت به مدیریت</a>
</div>
</body>
</html>
