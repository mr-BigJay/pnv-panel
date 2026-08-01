<?php

/**
 * Webhook بازوی بله برای تشخیص واریز کارت‌به‌کارت (فوروارد @postbank_bot)
 */

require_once __DIR__ . '/bale_lib.php';
require_once __DIR__ . '/instant_pay_lib.php';

header('Content-Type: application/json; charset=utf-8');

// health / version check (GET)
if(($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET'){
    echo json_encode([
        'ok' => true,
        'service' => 'bale-webhook',
        'parser' => function_exists('baleParserVersion') ? baleParserVersion() : 'unknown'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

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

$message = $update['message'] ?? ($update['edited_message'] ?? ($update['channel_post'] ?? null));

if(!is_array($message)){
    echo json_encode(['ok' => true, 'ignored' => 'no message']);
    exit;
}

$chatId = (string)($message['chat']['id'] ?? '');
$text = baleExtractMessageText($message);

// bootstrap: اگر ادمین هنوز chat_id ندارد، از اولین پیام ذخیره کن
$adminIds = baleAdminChatIds($config);
$bootstrapped = false;

if(count($adminIds) === 0 && $chatId !== ''){
    $config['admin_chat_ids'] = $chatId;
    baleSaveConfig($config);
    $adminIds = baleAdminChatIds($config);
    $bootstrapped = true;
    baleSendMessage($chatId, "✅ شناسه چت شما ذخیره شد: {$chatId}\nاز این به بعد پیام‌های واریز پست‌بانک را به همین بازو فوروارد کنید.", [], $config);
}

if(!baleIsAdminChat($chatId, $config)){
    if(function_exists('baleWebhookLog')){
        baleWebhookLog('unauthorized chat=' . $chatId . ' text=' . mb_substr($text, 0, 80));
    }
    echo json_encode(['ok' => false, 'error' => 'unauthorized chat']);
    exit;
}

if($text === '/start' || $text === 'start'){
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
        . "شناسه چت شما: {$chatId}\n"
        . 'parser: ' . (function_exists('baleParserVersion') ? baleParserVersion() : '?') . "\n\n"
        . "همین عدد را در پنل ادمین → بله → «شناسه چت مدیر» ذخیره کنید.\n"
        . "بعد پیام‌های واریز @postbank_bot را به همین بازو فوروارد کنید.",
        [],
        $config
    );
    echo json_encode(['ok' => true, 'start' => true, 'chat_id' => $chatId, 'bootstrapped' => $bootstrapped]);
    exit;
}

if($text === ''){
    if(function_exists('baleWebhookLog')){
        baleWebhookLog('empty text chat=' . $chatId . ' keys=' . implode(',', array_keys($message)));
    }
    baleSendMessage(
        $chatId,
        "⚠️ پیام بدون متن دریافت شد.\nاگر پست‌بانک عکس فرستاده، متن/کپشن را کپی کرده و برای بازو بفرستید.",
        [],
        $config
    );
    echo json_encode(['ok' => true, 'ignored' => 'empty text', 'bootstrapped' => $bootstrapped]);
    exit;
}

if(function_exists('baleWebhookLog')){
    baleWebhookLog('recv chat=' . $chatId . ' text=' . str_replace("\n", ' | ', mb_substr($text, 0, 200)));
}

$result = instantPayHandleDepositText($text, [
    'date' => date('Y/m/d'),
    'time' => date('H:i')
]);

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

    if(function_exists('baleWebhookLog')){
        baleWebhookLog('PAID id=' . $paidId . ' amount=' . ($result['matched_amount'] ?? '') . ' user=' . $userLabel);
    }

    baleSendMessage($chatId, $msg, [], $config);
    echo json_encode(['ok' => true, 'paid' => true, 'id' => $paidId, 'parser' => baleParserVersion()]);
    exit;
}

if(!empty($result['ignored'])){
    if(function_exists('baleWebhookLog')){
        baleWebhookLog('ignored: ' . ($result['error'] ?? ''));
    }
    echo json_encode(['ok' => true, 'ignored' => true]);
    exit;
}

$err = (string)($result['error'] ?? 'no match');
$parsed = $result['amounts'] ?? [];
$parsedText = is_array($parsed) && count($parsed) ? implode('، ', array_map(static function($n){
    return number_format(intval($n)) . ' ریال';
}, $parsed)) : '—';

if(function_exists('baleWebhookLog')){
    baleWebhookLog('NO_MATCH err=' . $err . ' amounts=' . json_encode($parsed, JSON_UNESCAPED_UNICODE));
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
    $open = $result['open_orders'] ?? [];
    $openLines = '';

    if(is_array($open) && count($open) > 0){
        $openLines = "\nسفارش‌های قابل مچ:\n";
        foreach(array_slice($open, 0, 8) as $o){
            $openLines .= '• ' . ($o['amount_text'] ?? '')
                . ' | ' . ($o['user'] ?? '-')
                . ' | ' . ($o['status'] ?? '-')
                . ' | کد ' . ($o['code'] ?? '-') . "\n";
        }
    }
    else{
        $openLines = "\nالان هیچ سفارش waiting/قابل‌مچی نیست.\n";
    }

    baleSendMessage(
        $chatId,
        "⚠️ پیام دریافت شد ولی سفارش مچ نشد.\n"
        . $err . "\n"
        . 'مبالغ خوانده‌شده: ' . $parsedText . "\n"
        . 'parser: ' . baleParserVersion() . "\n"
        . $openLines
        . "نکته: پیام باید به همین بازو فوروارد شود؛ دیدن در کانال پست‌بانک کافی نیست.",
        [],
        $config
    );
}

echo json_encode([
    'ok' => false,
    'error' => $err,
    'amounts' => $result['amounts'] ?? [],
    'candidates' => $result['candidates'] ?? [],
    'bootstrapped' => $bootstrapped,
    'parser' => baleParserVersion()
], JSON_UNESCAPED_UNICODE);
