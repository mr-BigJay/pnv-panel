<?php
/**
 * Signed bank transaction intake (no browser login, no bank password).
 * Requires an authorized trusted read-only source, configured by an admin.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok'=>false,'code'=>'method_not_allowed']);
    exit;
}
$raw = file_get_contents('php://input', false, null, 0, 131073);
if (!is_string($raw) || strlen($raw) > 131072) {
    http_response_code(413);
    echo json_encode(['ok'=>false,'code'=>'too_large']);
    exit;
}
require_once __DIR__ . '/bank_connection_lib.php';
$result = pnvBankHandleSignedDeposit(
    $raw,
    $_SERVER['HTTP_X_PNV_TIMESTAMP'] ?? '',
    $_SERVER['HTTP_X_PNV_SIGNATURE'] ?? ''
);
if (empty($result['ok'])) {
    $code = $result['code'] ?? 'invalid_request';
    http_response_code($code === 'unauthorized' ? 401 : ($code === 'disabled' || $code === 'storage_unavailable' ? 503 : 422));
}
echo json_encode($result, JSON_UNESCAPED_UNICODE);
