<?php
/**
 * Receive authenticated settled-credit events from an authorized Tejarat bank
 * transaction-data provider. Never accepts customer-submitted receipts.
 */
require_once __DIR__ . '/tejarat_bank_lib.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['ok'=>false,'code'=>'method_not_allowed']);
    exit;
}
if (stripos((string)($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json') !== 0) {
    http_response_code(415);
    echo json_encode(['ok'=>false,'code'=>'json_required']);
    exit;
}
$length = intval($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($length > 8192) {
    http_response_code(413);
    echo json_encode(['ok'=>false,'code'=>'payload_too_large']);
    exit;
}
$body = file_get_contents('php://input', false, null, 0, 8193);
$result = tejaratBankProcessSignedPayload(
    is_string($body) ? $body : '',
    $_SERVER['HTTP_X_PNV_TIMESTAMP'] ?? '',
    $_SERVER['HTTP_X_PNV_SIGNATURE'] ?? ''
);
if (empty($result['ok'])) {
    $code = $result['code'] ?? '';
    http_response_code(in_array($code, ['unauthorized','disabled'], true) ? 403 : 422);
} else {
    http_response_code(200);
}
echo json_encode($result, JSON_UNESCAPED_UNICODE);
