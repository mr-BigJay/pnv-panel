<?php

/**
 * Webhook بازوی بله برای تشخیص واریز کارت‌به‌کارت (فوروارد @postbank_bot)
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
$tokenConfigured = trim((string)($config['bot_token'] ?? '')) !== '';

if(empty($config['enabled']) || !$tokenConfigured){
    baleLogWebhookEvent('ignored_disabled', [
        'enabled' => !empty($config['enabled']),
        'has_token' => $tokenConfigured,
    ]);
    echo json_encode(['ok' => false, 'error' => 'bale disabled']);
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

baleLogWebhookEvent('incoming', array_merge(
    baleMessageDebugSummary($message),
    ['via_listener' => !empty($_SERVER['HTTP_X_POSTBANK_LISTENER'])]
));

// bootstrap: اگر ادمین هنوز chat_id ندارد، از اولین پیام ذخیره کن
$adminIds = baleAdminChatIds($config);
$bootstrapped = false;

if(count($adminIds) === 0 && $chatId !== ''){
    $config = baleRememberAdminIds($config, $message);
    $adminIds = baleAdminChatIds($config);
    $bootstrapped = true;
    baleSendMessage($chatId, "✅ شناسه چت شما ذخیره شد: {$chatId}\nاز این به بعد پیام‌های واریز پست‌بانک را به همین بازو فوروارد کنید.", [], $config);
    // ادامه بده تا اگر همین پیام واریز بود، مچ شود
}

if(!baleIsAdminMessage($message, $config)){
    $allowed = baleAdminChatIds($config);
    $senderId = baleMessageSenderId($message);

    baleLogWebhookEvent('unauthorized_chat', [
        'chat_id' => $chatId,
        'from_id' => $senderId,
        'allowed' => $allowed,
        'forward' => baleForwardSourceLabel($message),
    ]);

    if($chatId !== ''){
        baleSendMessage(
            $chatId,
            "⛔️ این چت برای تأیید خودکار مجاز نیست.\n"
            . "شناسه چت: {$chatId}\n"
            . ($senderId !== '' ? "شناسه فرستنده: {$senderId}\n" : '')
            . "شناسه‌های مجاز در پنل: " . (count($allowed) ? implode(', ', $allowed) : '—') . "\n\n"
            . "در پنل ادمین → بله → «خواندن شناسه از بله» را بزنید یا همین اعداد را در «شناسه چت مدیر» ذخیره کنید.\n"
            . "اگر در گروه فوروارد می‌کنید، شناسه گروه یا شناسه کاربری خودتان باید در لیست باشد.",
            [],
            $config
        );
    }

    echo json_encode(['ok' => false, 'error' => 'unauthorized chat', 'chat_id' => $chatId, 'from_id' => $senderId]);
    exit;
}

if($text === '/start' || $text === 'start'){
    // همیشه chat_id را نشان بده تا ادمین بتواند در پنل ذخیره کند
    $config = baleRememberAdminIds($config, $message);
    $adminIds = baleAdminChatIds($config);
    $senderId = baleMessageSenderId($message);

    baleSendMessage(
        $chatId,
        "ربات پرداخت آنی فعال است.\n"
        . "شناسه چت: {$chatId}\n"
        . ($senderId !== '' && $senderId !== $chatId ? "شناسه کاربری شما: {$senderId}\n" : '')
        . "\nهمین عدد(ها) را در پنل ادمین → بله → «شناسه چت مدیر» ذخیره کنید.\n"
        . "بعد پیام‌های واریز @postbank_bot را به همین بازو فوروارد کنید.",
        [],
        $config
    );
    echo json_encode(['ok' => true, 'start' => true, 'chat_id' => $chatId, 'from_id' => $senderId, 'bootstrapped' => $bootstrapped]);
    exit;
}

if($text === ''){
    $forwardLabel = baleForwardSourceLabel($message);
    $hint = "⚠️ متن پیام واریز خوانده نشد.\n";

    if($forwardLabel !== ''){
        $hint .= "منبع فوروارد: {$forwardLabel}\n";
    }

    $hint .= "لطفاً پیام @postbank_bot را با «فوروارد» بفرستید.\n"
        . "اگر باز هم خالی بود، متن پیام را کپی و به‌صورت پیام عادی بفرستید.";

    baleSendMessage($chatId, $hint, [], $config);
    baleLogWebhookEvent('empty_text', [
        'chat_id' => $chatId,
        'forward' => $forwardLabel,
        'message_id' => $message['message_id'] ?? null,
    ]);
    echo json_encode(['ok' => true, 'ignored' => 'empty text', 'bootstrapped' => $bootstrapped]);
    exit;
}

baleLogWebhookEvent('deposit_text', [
    'chat_id' => $chatId,
    'forward' => baleForwardSourceLabel($message),
    'preview' => function_exists('mb_substr') ? mb_substr($text, 0, 120) : substr($text, 0, 120),
]);

try{
    $result = instantPayHandleDepositText($text, pnvNowParts());
}
catch(Throwable $e){
    baleLogWebhookEvent('handler_exception', [
        'chat_id' => $chatId,
        'error' => $e->getMessage(),
    ]);

    baleSendMessage(
        $chatId,
        "⚠️ خطای داخلی هنگام پردازش واریز.\n" . $e->getMessage(),
        [],
        $config
    );

    echo json_encode(['ok' => false, 'error' => 'handler exception']);
    exit;
}

if(!empty($result['ok'])){
    $item = $result['item'] ?? [];
    $paidId = $item['id'] ?? '';
    $raw = $paidId !== '' ? instantPayGet($paidId) : null;
    $userLabel = is_array($raw) ? ($raw['user'] ?? '-') : '-';

    baleLogWebhookEvent('deposit_paid', [
        'chat_id' => $chatId,
        'id' => $paidId,
        'amount' => $result['matched_amount'] ?? null,
        'via' => $result['matched_via'] ?? '',
    ]);

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
    $forwardLabel = baleForwardSourceLabel($message);
    $isPostBank = function_exists('baleLooksLikePostBankForward') && baleLooksLikePostBankForward($message);

    if($isPostBank || $forwardLabel !== ''){
        baleSendMessage(
            $chatId,
            "ℹ️ پیام فوروارد دریافت شد ولی شبیه واریز تشخیص داده نشد.\n"
            . ($forwardLabel !== '' ? "منبع: {$forwardLabel}\n" : '')
            . "لطفاً پیام @postbank_bot را با «فوروارد» (نه کپی) بفرستید.\n"
            . "اگر متن دیده نمی‌شود، متن پیام را کپی و به‌صورت پیام عادی ارسال کنید.",
            [],
            $config
        );
        baleLogWebhookEvent('ignored_not_deposit', [
            'chat_id' => $chatId,
            'forward' => $forwardLabel,
            'preview' => function_exists('mb_substr') ? mb_substr($text, 0, 120) : substr($text, 0, 120),
        ]);
    }

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
$openOrders = is_array($result['open_orders'] ?? null) ? $result['open_orders'] : [];
$openText = '';

if($openOrders){
    $openText = "\nسفارش‌های قابل مچ:\n";

    foreach(array_slice($openOrders, 0, 6) as $order){
        $openText .= '• ' . ($order['amount_text'] ?? number_format(intval($order['amount'] ?? 0))) . ' ریال'
            . ' | ' . ($order['user'] ?? '-')
            . ' | کد ' . ($order['code'] ?? '-')
            . "\n";
    }
}

baleLogWebhookEvent('deposit_no_match', [
    'chat_id' => $chatId,
    'error' => $err,
    'amounts' => $parsed,
    'waiting' => intval($debug['waiting'] ?? 0),
    'matchable' => intval($debug['matchable'] ?? 0),
]);

if($debug){
    $debugText = "\n"
        . 'سفارش waiting: ' . intval($debug['waiting'] ?? 0) . ' | '
        . 'قابل مچ: ' . intval($debug['matchable'] ?? 0) . ' | '
        . 'CSV در انتظار: ' . intval($debug['csv_pending'] ?? 0);
}

// اگر مچ شده ولی صدور اشتراک شکست خورده، واضح بگو (نه «مچ نشد»)
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
        . 'مبالغ خوانده‌شده: ' . $parsedText . $debugText . $openText . "\n"
        . "نکته: کاربر باید دقیقاً همان مبلغ صفحه را کارت‌به‌کارت کند و پیام @postbank_bot را فوروارد کنید.",
        [],
        $config
    );
}

echo json_encode([
    'ok' => false,
    'error' => $err,
    'amounts' => $result['amounts'] ?? [],
    'candidates' => $result['candidates'] ?? [],
    'bootstrapped' => $bootstrapped
]);
