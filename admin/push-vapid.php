<?php

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/push_lib.php';

header('Content-Type: application/json; charset=utf-8');

if(!pnvAdminIsLoggedIn()){
    http_response_code(403);
    echo json_encode(['error' => 'forbidden']);
    exit;
}

echo json_encode([
    'publicKey' => pushPublicKey()
], JSON_UNESCAPED_UNICODE);
