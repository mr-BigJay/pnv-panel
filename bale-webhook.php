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

if(empty($config['enabled']) || trim((string)($config['bot_token'] ?? '')) === ''){
    echo json_encode(['ok' => false, 'error' => 'bale disabled']);
    exit;
}

$message = $update['message'] ?? ($update['edited_message'] ?? null);

if(!is_array($message)){
    echo json_encode(['ok' => true, 'ignored' => 'no message']);
    exit;
}

$chatId = (string)($message['chat']['id'] ?? '');
$text = baleExtractMessageText($message);

// bootstrap: اگر ادمین هنوز chat_id ندارد، از اولین پیام ذخیره کن
$adminIds = baleAdminChatIds($config);

if(count($adminIds) === 0 && $chatId !== ''){
    $config['admin_chat_ids'] = $chatId;
    baleSaveConfig($config);
    baleSendMessage($chatId, "✅ شناسه چت شما ذخیره شد: {$chatId}\nاز این به بعد پیام‌های واریز پست‌بانک را به همین بازو فوروارد کنید.", [], $config);
    echo json_encode(['ok' => true, 'bootstrapped' => true]);
    exit;
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
    echo json_encode(['ok' => true, 'start' => true, 'chat_id' => $chatId]);
    exit;
}

if($text === ''){
    echo json_encode(['ok' => true, 'ignored' => 'empty text']);
    exit;
}

$result = instantPayHandleDepositText($text, [
    'date' => date('Y/m/d'),
    'time' => date('H:i')
]);

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

    baleSendMessage($chatId, $msg, [], $config);
    echo json_encode(['ok' => true, 'paid' => true, 'id' => $paidId]);
    exit;
}

if(!empty($result['ignored'])){
    echo json_encode(['ok' => true, 'ignored' => true]);
    exit;
}

baleSendMessage(
    $chatId,
    "⚠️ پیام دریافت شد ولی سفارش مچ نشد.\n" . ($result['error'] ?? ''),
    [],
    $config
);

echo json_encode(['ok' => false, 'error' => $result['error'] ?? 'no match', 'amounts' => $result['amounts'] ?? []]);
