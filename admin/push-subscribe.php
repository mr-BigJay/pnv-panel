<?php

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/push_lib.php';

header('Content-Type: application/json; charset=utf-8');

if(!pnvAdminIsLoggedIn()){
    http_response_code(403);
    echo json_encode(['error' => 'forbidden']);
    exit;
}

if($_SERVER['REQUEST_METHOD'] !== 'POST'){
    http_response_code(405);
    echo json_encode(['error' => 'method_not_allowed']);
    exit;
}

$raw = file_get_contents('php://input');
$subscription = json_decode($raw, true);

if(!is_array($subscription)){
    http_response_code(400);
    echo json_encode(['error' => 'invalid_json']);
    exit;
}

$ok = pushSaveSubscription(pnvAdminUser(), $subscription);

echo json_encode([
    'ok' => $ok
], JSON_UNESCAPED_UNICODE);
