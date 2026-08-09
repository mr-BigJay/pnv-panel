<?php

/**
 * دریافت خودکار متن واریز از listener اکانت بله (بدون فوروارد دستی)
 * Header: X-Postbank-Ingest-Secret: <secret>
 * Body JSON: { "text": "...", "source": "userbot" }
 */

require_once __DIR__ . '/bale_lib.php';
require_once __DIR__ . '/instant_pay_lib.php';

if(file_exists(__DIR__ . '/time_lib.php')){
    require_once __DIR__ . '/time_lib.php';
}

header('Content-Type: application/json; charset=utf-8');

if(($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET'){
    echo json_encode([
        'ok' => true,
        'service' => 'postbank-ingest',
        'parser' => baleParserVersion()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$config = baleLoadConfig();

if(empty($config['enabled'])){
    http_response_code(503);
    echo json_encode(['ok' => false, 'error' => 'bale disabled'], JSON_UNESCAPED_UNICODE);
    exit;
}

$secret = baleEnsureIngestSecret($config);
$provided = '';

if(isset($_SERVER['HTTP_X_POSTBANK_INGEST_SECRET'])){
    $provided = trim((string)$_SERVER['HTTP_X_POSTBANK_INGEST_SECRET']);
}
elseif(isset($_GET['secret'])){
    $provided = trim((string)$_GET['secret']);
}

if($provided === '' || !hash_equals($secret, $provided)){
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'unauthorized'], JSON_UNESCAPED_UNICODE);
    exit;
}

$raw = file_get_contents('php://input');
$payload = json_decode($raw ?: '[]', true);

if(!is_array($payload)){
    $payload = $_POST;
}

$text = trim((string)($payload['text'] ?? ''));
$source = trim((string)($payload['source'] ?? 'userbot'));

if($text === ''){
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'empty text'], JSON_UNESCAPED_UNICODE);
    exit;
}

if(function_exists('baleWebhookLog')){
    baleWebhookLog('INGEST source=' . $source . ' text=' . str_replace("\n", ' | ', mb_substr($text, 0, 220)));
}

$nowMeta = function_exists('pnvNowJalaliMeta')
    ? pnvNowJalaliMeta()
    : ['date' => date('Y/m/d'), 'time' => date('H:i')];

$result = instantPayHandleDepositText($text, [
    'date' => $nowMeta['date'],
    'time' => $nowMeta['time']
]);

$adminIds = baleAdminChatIds($config);
$notifyChat = $adminIds[0] ?? '';

if(!empty($result['ok'])){
    $item = $result['item'] ?? [];
    $paidId = $item['id'] ?? '';
    $rawItem = $paidId !== '' ? instantPayGet($paidId) : null;
    $userLabel = is_array($rawItem) ? ($rawItem['user'] ?? '-') : '-';

    $msg = "✅ پرداخت تأیید شد (اتوماتیک)\n"
        . 'کاربر: ' . $userLabel . "\n"
        . 'مبلغ: ' . ($item['amount_text'] ?? '-') . "\n"
        . 'پلن: ' . ($item['plan'] ?? '-') . "\n"
        . 'کد: ' . ($item['code'] ?? '-');

    if(!empty($item['link'])){
        $msg .= "\nلینک:\n" . $item['link'];
    }

    if($notifyChat !== ''){
        baleSendMessage($notifyChat, $msg, [], $config);
    }

    if(function_exists('baleWebhookLog')){
        baleWebhookLog('INGEST_PAID id=' . $paidId . ' amount=' . ($result['matched_amount'] ?? ''));
    }

    echo json_encode([
        'ok' => true,
        'paid' => true,
        'id' => $paidId,
        'matched_amount' => $result['matched_amount'] ?? null,
        'parser' => baleParserVersion()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if(!empty($result['ignored'])){
    echo json_encode([
        'ok' => true,
        'ignored' => true,
        'error' => $result['error'] ?? '',
        'parser' => baleParserVersion()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$err = (string)($result['error'] ?? 'no match');
$parsed = $result['amounts'] ?? ($result['parsed_amounts'] ?? []);
$parsedText = is_array($parsed) && count($parsed)
    ? implode('، ', array_map(static function($n){ return number_format(intval($n)) . ' ریال'; }, $parsed))
    : '—';

if($notifyChat !== '' && empty($result['ignored'])){
    $open = $result['open_orders'] ?? [];
    $openLines = '';
    if(is_array($open) && count($open) > 0){
        $openLines = "\nسفارش‌های قابل مچ:\n";
        foreach(array_slice($open, 0, 6) as $o){
            $openLines .= '• ' . ($o['amount_text'] ?? '') . ' | ' . ($o['user'] ?? '-') . "\n";
        }
    }

    baleSendMessage(
        $notifyChat,
        "⚠️ واریز اتوماتیک خوانده شد ولی مچ نشد.\n"
        . $err . "\n"
        . 'مبالغ: ' . $parsedText
        . $openLines,
        [],
        $config
    );
}

if(function_exists('baleWebhookLog')){
    baleWebhookLog('INGEST_NO_MATCH err=' . $err . ' amounts=' . json_encode($parsed, JSON_UNESCAPED_UNICODE));
}

echo json_encode([
    'ok' => false,
    'error' => $err,
    'amounts' => $parsed,
    'open_orders' => $result['open_orders'] ?? [],
    'matched_amount' => $result['matched_amount'] ?? null,
    'parser' => baleParserVersion()
], JSON_UNESCAPED_UNICODE);
