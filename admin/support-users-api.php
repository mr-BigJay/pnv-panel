<?php

require_once __DIR__ . '/auth.php';

header('Content-Type: application/json; charset=utf-8');

if(!pnvAdminIsLoggedIn()){
    http_response_code(403);
    echo json_encode(['error' => 'forbidden']);
    exit;
}

require_once __DIR__ . '/../support_lib.php';

$query = trim($_GET['q'] ?? '');
$limit = intval($_GET['limit'] ?? 10);

if($limit <= 0 || $limit > 20){
    $limit = 10;
}

$users = supportSearchUsers($query, $limit);

echo json_encode([
    'users' => $users
], JSON_UNESCAPED_UNICODE);
