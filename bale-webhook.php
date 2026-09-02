<?php

/**
 * Webhook بازوی بله برای تشخیص واریز کارت‌به‌کارت (فوروارد @postbank_bot)
 */

require_once __DIR__ . '/bale_lib.php';

header('Content-Type: application/json; charset=utf-8');

function baleWebhookEnsureInstantPayLib(){
    if(function_exists('instantPayHandleDepositText')){
        return true;
    }

    $lib = __DIR__ . '/instant_pay_lib.php';

    if(!is_file($lib)){
        return false;
    }

    require_once $lib;

    return function_exists('instantPayHandleDepositText');
}

function baleWebhookJsonError($httpCode, $message, $extra = []){
    http_response_code($httpCode);
    echo json_encode(array_merge([
        'ok' => false,
        'error' => $message,
    ], $extra), JSON_UNESCAPED_UNICODE);
}

register_shutdown_function(static function(){
    $err = error_get_last();

    if($err === null){
        return;
    }

    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];

    if(!in_array($err['type'] ?? 0, $fatalTypes, true)){
        return;
    }

    if(!headers_sent()){
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
    }

    if(function_exists('baleWebhookLog')){
        baleWebhookLog('FATAL ' . ($err['message'] ?? '') . ' @ ' . ($err['file'] ?? '') . ':' . intval($err['line'] ?? 0));
    }

    echo json_encode([
        'ok' => false,
        'error' => 'internal error',
        'detail' => $err['message'] ?? '',
    ], JSON_UNESCAPED_UNICODE);
});

$raw = file_get_contents('php://input');
$update = json_decode($raw ?: '[]', true);

if(!is_array($update)){
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'bad json']);
    exit;
}

$config = baleLoadConfig();

if(empty($config['enabled']) || trim((string)($config['bot_token'] ?? '')) === ''){
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
    function_exists('baleMessageDebugSummary') ? baleMessageDebugSummary($message) : ['chat_id' => $chatId],
    ['via_listener' => !empty($_SERVER['HTTP_X_POSTBANK_LISTENER'])]
));

// bootstrap: اگر ادمین هنوز chat_id ندارد، از اولین پیام ذخیره کن
$adminIds = baleAdminChatIds($config);
$bootstrapped = false;

if(count($adminIds) === 0 && $chatId !== ''){
    $config['admin_chat_ids'] = $chatId;
    baleSaveConfig($config);
    $adminIds = baleAdminChatIds($config);
    $bootstrapped = true;
    baleSendMessage($chatId, "✅ شناسه چت شما ذخیره شد: {$chatId}\nاز این به بعد پیام‌های واریز پست‌بانک را به همین بازو فوروارد کنید.", [], $config);
    // ادامه بده تا اگر همین پیام واریز بود، مچ شود
}

if(!baleIsAdminChat($chatId, $config)){
    echo json_encode(['ok' => false, 'error' => 'unauthorized chat']);
    exit;
}

if($text === '/start' || $text === 'start'){
    // همیشه chat_id را نشان بده تا ادمین بتواند در پنل ذخیره کند
    if($chatId !== '' && !in_array($chatId, $adminIds, true)){
        $ids = $adminIds;
        $ids[] = $chatId;
        $config['admin_chat_ids'] = implode(',', array_values(array_unique($ids)));
        baleSaveConfig($config);
        $adminIds = baleAdminChatIds($config);
    }

    baleSendMessage(
        $chatId,
        "ربات پرداخت آنی فعال است.\n"
        . "شناسه چت شما: {$chatId}\n\n"
        . "همین عدد را در پنل ادمین → بله → «شناسه چت مدیر» ذخیره کنید.\n"
        . "بعد پیام‌های واریز @postbank_bot را به همین بازو فوروارد کنید.",
        [],
        $config
    );
    echo json_encode(['ok' => true, 'start' => true, 'chat_id' => $chatId, 'bootstrapped' => $bootstrapped]);
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
    echo json_encode(['ok' => true, 'ignored' => 'empty text', 'bootstrapped' => $bootstrapped]);
    exit;
}

baleLogWebhookEvent('deposit_text', [
    'chat_id' => $chatId,
    'forward' => baleForwardSourceLabel($message),
    'preview' => function_exists('mb_substr') ? mb_substr($text, 0, 120) : substr($text, 0, 120),
]);

try{
    if(!baleWebhookEnsureInstantPayLib()){
        throw new RuntimeException('instant_pay_lib در دسترس نیست');
    }

    $nowMeta = function_exists('pnvNowParts')
        ? pnvNowParts()
        : ['date' => date('Y/m/d'), 'time' => date('H:i')];

    $result = instantPayHandleDepositText($text, $nowMeta);
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
        . 'مبالغ خوانده‌شده: ' . $parsedText . $debugText . "\n"
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
