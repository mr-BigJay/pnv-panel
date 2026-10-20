<?php

/**
 * Ingest داخلی Worker تجارت (HMAC) — فقط POST JSON.
 * رمز، OTP، کوکی یا متن حساس را لاگ نکنید.
 */

require_once __DIR__ . '/tejarat_auto_verify_lib.php';
require_once __DIR__ . '/tejarat_worker_lib.php';

header('Content-Type: application/json; charset=utf-8');

if(($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST'){
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method not allowed']);
    exit;
}

$config = tejaratVerifyLoadConfig();
$secret = tejaratWorkerIngestSecret($config);

if($secret === ''){
    http_response_code(503);
    echo json_encode(['ok' => false, 'code' => 'ingest_not_configured', 'error' => 'worker_ingest_secret تنظیم نشده']);
    exit;
}

$raw = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_PNV_TEJARAT_WORKER_SIGNATURE'] ?? '';

if(!tejaratWorkerVerifySignature($raw ?: '', $signature, $config)){
    http_response_code(401);
    echo json_encode(['ok' => false, 'code' => 'unauthorized', 'error' => 'امضای ingest نامعتبر است']);
    exit;
}

$payload = json_decode($raw ?: '[]', true);

if(!is_array($payload)){
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'bad json']);
    exit;
}

$result = tejaratWorkerHandleIngestPayload($payload, $config);

if(empty($result['ok'])){
    http_response_code(500);
}

echo json_encode($result, JSON_UNESCAPED_UNICODE);
