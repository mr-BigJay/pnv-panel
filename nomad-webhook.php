<?php

/**
 * Webhook دریافت پیامک پست‌بانک از Android Nomad Gateway (HTTPS + توکن هدر).
 */

require_once __DIR__ . '/nomad_lib.php';

header('Content-Type: application/json; charset=utf-8');

$config = nomadLoadConfig();

if(!nomadAuthOk($config)){
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'unauthorized'], JSON_UNESCAPED_UNICODE);
    exit;
}

$raw = file_get_contents('php://input');
$payload = json_decode($raw ?: '[]', true);

if(!is_array($payload)){
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'bad json'], JSON_UNESCAPED_UNICODE);
    exit;
}

$gateway = nomadNormalizeGatewayPayload($payload);

if(!is_array($gateway)){
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'missing text'], JSON_UNESCAPED_UNICODE);
    exit;
}

$result = nomadProcessInboundSms($gateway, $config);
$http = intval($result['http'] ?? 200);

if($http >= 400){
    http_response_code($http);
}

unset($result['http']);

echo json_encode($result, JSON_UNESCAPED_UNICODE);
