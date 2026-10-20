<?php

/**
 * Webhook بازوی بله برای تشخیص واریز کارت‌به‌کارت (فوروارد @postbank_bot)
 *
 * mode=pay (پیش‌فرض): admin_chat_ids + فوروارد postbank؛ secret اگر تنظیم شده اجباری است
 * mode=setup: ثبت chat_id ادمین با secret (بدون تأیید پرداخت)
 */

require_once __DIR__ . '/bale_lib.php';
require_once __DIR__ . '/instant_pay_lib.php';

header('Content-Type: application/json; charset=utf-8');

$raw = file_get_contents('php://input');
$update = json_decode($raw ?: '[]', true);

if(!is_array($update)){
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'bad json']);
    exit;
}

$config = baleLoadConfig();
$mode = strtolower(trim((string)($_GET['mode'] ?? 'pay')));

if($mode !== 'setup'){
    $mode = 'pay';
}

if(empty($config['enabled']) || trim((string)($config['bot_token'] ?? '')) === ''){
    echo json_encode(['ok' => false, 'error' => 'bale disabled']);
    exit;
}

$auth = baleVerifyWebhookRequest($config);

if(empty($auth['ok'])){
    $code = (string)($auth['code'] ?? 'webhook_unauthorized');
    http_response_code($code === 'webhook_secret_missing' ? 503 : 401);
    echo json_encode(['ok' => false, 'error' => $auth['error'] ?? 'unauthorized', 'code' => $code]);
    exit;
}

$message = $update['message']
    ?? ($update['edited_message'] ?? ($update['channel_post'] ?? ($update['edited_channel_post'] ?? null)));

if(!is_array($message)){
    echo json_encode(['ok' => true, 'ignored' => 'no message']);
    exit;
}

$chatId = (string)($message['chat']['id'] ?? '');
$text = baleExtractMessageText($message);

if($mode === 'setup'){
    if($text === '/start' || $text === 'start'){
        if($chatId !== ''){
            baleAppendAdminChatId($chatId, $config);
        }

        baleSendMessage(
            $chatId,
            "حالت setup فعال است.\n"
            . "شناسه چت: {$chatId}\n"
            . "در پنل ادمین → بله نیز می‌توانید همین عدد را ذخیره کنید.\n"
            . "برای پرداخت از Webhook با mode=pay و فوروارد @postbank_bot استفاده کنید.",
            [],
            $config
        );
        echo json_encode(['ok' => true, 'setup' => true, 'chat_id' => $chatId]);
        exit;
    }

    echo json_encode(['ok' => true, 'setup' => true, 'ignored' => 'send /start to register chat id']);
    exit;
}

if(count(baleAdminChatIds($config)) === 0){
    http_response_code(503);
    echo json_encode(['ok' => false, 'code' => 'admin_not_configured', 'error' => 'admin_chat_ids تنظیم نشده است']);
    exit;
}

if(!baleIsAdminChat($chatId, $config)){
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'unauthorized chat']);
    exit;
}

if($text === '/start' || $text === 'start'){
    baleSendMessage(
        $chatId,
        "ربات پرداخت آنی فعال است.\n"
        . "شناسه چت شما: {$chatId}\n\n"
        . "پیام واریز @postbank_bot را فقط با «فوروارد» (نه کپی متن) به همین بازو بفرستید.",
        [],
        $config
    );
    echo json_encode(['ok' => true, 'start' => true, 'chat_id' => $chatId]);
    exit;
}

if($text === ''){
    baleSendMessage(
        $chatId,
        "⚠️ متن پیام خوانده نشد.\n"
        . "لطفاً پیام واریز @postbank_bot را با گزینه «فوروارد» (نه کپی) به همین بازو بفرستید.",
        [],
        $config
    );
    echo json_encode(['ok' => true, 'ignored' => 'empty text']);
    exit;
}

if(!baleMessageIsTrustedPostbankDeposit($message)){
    baleSendMessage(
        $chatId,
        "⚠️ این پیام به‌عنوان فوروارد معتبر @postbank_bot پذیرفته نشد.\n"
        . "کپی متن یا پیام دستی برای تأیید خودکار کافی نیست.",
        [],
        $config
    );
    echo json_encode(['ok' => false, 'code' => 'untrusted_deposit_source', 'error' => 'forward from postbank required']);
    exit;
}

$result = instantPayHandleDepositText($text, array_merge(pnvNowParts(), [
    'source' => 'bale',
    'bale_chat_id' => $chatId,
    'bale_message_id' => (string)($message['message_id'] ?? ''),
    'bale_update_id' => (string)($update['update_id'] ?? ''),
]));

if(!empty($result['ok'])){
    $item = $result['item'] ?? [];
    $paidId = $item['id'] ?? '';
    $rawItem = $paidId !== '' ? instantPayGet($paidId) : null;
    $userLabel = is_array($rawItem) ? ($rawItem['user'] ?? '-') : '-';

    $msg = "✅ پرداخت تأیید شد\n"
        . 'کاربر: ' . $userLabel . "\n"
        . 'مبلغ: ' . ($item['amount_text'] ?? '-') . "\n"
        . 'پلن: ' . ($item['plan'] ?? '-') . "\n"
        . 'کد: ' . ($item['code'] ?? '-');

    if(!empty($item['link'])){
        $msg .= "\nلینک:\n" . $item['link'];
    }

    if(!empty($result['matched_via'])){
        $msg .= "\nمسیر: " . $result['matched_via'];
    }

    baleSendMessage($chatId, $msg, [], $config);
    echo json_encode(['ok' => true, 'paid' => true, 'id' => $paidId]);
    exit;
}

if(!empty($result['ignored'])){
    echo json_encode(['ok' => true, 'ignored' => true]);
    exit;
}

$err = (string)($result['error'] ?? 'no match');
$parsed = $result['amounts'] ?? $result['parsed_amounts'] ?? [];
$parsedText = is_array($parsed) && count($parsed) ? implode('، ', array_map(static function($n){
    return number_format(intval($n)) . ' ریال';
}, $parsed)) : '—';
$debug = is_array($result['debug'] ?? null) ? $result['debug'] : [];
$debugText = '';

if($debug){
    $debugText = "\n"
        . 'سفارش waiting: ' . intval($debug['waiting'] ?? 0) . ' | '
        . 'قابل مچ: ' . intval($debug['matchable'] ?? 0) . ' | '
        . 'CSV در انتظار: ' . intval($debug['csv_pending'] ?? 0);
}

if(!empty($result['matched_amount']) && empty($result['ok'])){
    baleSendMessage(
        $chatId,
        "⚠️ واریز دیده شد ولی آماده‌سازی اشتراک ناموفق بود.\n"
        . $err . "\n"
        . 'مبلغ مچ‌شده: ' . number_format(intval($result['matched_amount'])) . " ریال\n"
        . 'مبالغ خوانده‌شده: ' . $parsedText,
        [],
        $config
    );
}
else{
    baleSendMessage(
        $chatId,
        "⚠️ پیام دریافت شد ولی سفارش مچ نشد.\n"
        . $err . "\n"
        . 'مبالغ خوانده‌شده: ' . $parsedText . $debugText . "\n"
        . "نکته: کاربر باید دقیقاً همان مبلغ صفحه را کارت‌به‌کارت کند و پیام @postbank_bot را فوروارد کنید.",
        [],
        $config
    );
}

echo json_encode([
    'ok' => false,
    'error' => $err,
    'code' => $result['code'] ?? '',
    'amounts' => $result['amounts'] ?? [],
    'candidates' => $result['candidates'] ?? [],
]);
