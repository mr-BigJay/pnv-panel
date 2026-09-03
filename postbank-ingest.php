<?php

/**
 * دریافت خودکار متن واریز از listener اکانت بله (بدون فوروارد دستی)
 * Header: X-Postbank-Ingest-Secret: <secret>
 * Body JSON: { "text": "...", "source": "userbot" }
 */

require_once __DIR__ . '/bale_lib.php';

header('Content-Type: application/json; charset=utf-8');

function postbankEnsureInstantPayLib(){
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

if(($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET'){
    echo json_encode([
        'ok' => true,
        'service' => 'postbank-ingest',
        'parser' => function_exists('baleParserVersion') ? baleParserVersion() : 'unknown',
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
    baleWebhookLog('INGEST_AUTH_FAIL');
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

if(!postbankEnsureInstantPayLib()){
    baleWebhookLog('INGEST_FAIL instant_pay_lib missing source=' . $source);
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'instant pay unavailable'], JSON_UNESCAPED_UNICODE);
    exit;
}

$preview = function_exists('mb_substr') ? mb_substr($text, 0, 220) : substr($text, 0, 220);
baleWebhookLog('INGEST source=' . $source . ' text=' . str_replace("\n", ' | ', $preview));

$nowMeta = function_exists('pnvNowParts') ? pnvNowParts() : ['date' => date('Y/m/d'), 'time' => date('H:i')];

try{
    $result = instantPayHandleDepositText($text, [
        'date' => $nowMeta['date'] ?? '',
        'time' => $nowMeta['time'] ?? '',
    ]);
}
catch(Throwable $e){
    baleWebhookLog('INGEST_EXCEPTION ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'handler exception', 'detail' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}

if(!empty($result['ok'])){
    $item = $result['item'] ?? [];
    $paidId = $item['id'] ?? '';

    baleWebhookLog('INGEST_PAID id=' . $paidId . ' amount=' . ($result['matched_amount'] ?? ''));

    echo json_encode([
        'ok' => true,
        'paid' => true,
        'id' => $paidId,
        'matched_amount' => $result['matched_amount'] ?? null,
        'parser' => baleParserVersion(),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if(!empty($result['ignored'])){
    echo json_encode([
        'ok' => true,
        'ignored' => true,
        'error' => $result['error'] ?? '',
        'parser' => baleParserVersion(),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$err = (string)($result['error'] ?? 'no match');
$parsed = $result['amounts'] ?? ($result['parsed_amounts'] ?? []);

baleWebhookLog('INGEST_NO_MATCH err=' . $err . ' amounts=' . json_encode($parsed, JSON_UNESCAPED_UNICODE));

echo json_encode([
    'ok' => false,
    'error' => $err,
    'amounts' => $parsed,
    'open_orders' => $result['open_orders'] ?? [],
    'matched_amount' => $result['matched_amount'] ?? null,
    'parser' => baleParserVersion(),
], JSON_UNESCAPED_UNICODE);
