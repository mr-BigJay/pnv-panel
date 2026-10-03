<?php

require_once __DIR__ . '/auth.php';

header('Content-Type: application/json; charset=utf-8');

if(!pnvAdminIsLoggedIn()){
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'login required'], JSON_UNESCAPED_UNICODE);
    exit;
}

$root = dirname(__DIR__);
$lib = $root . '/support_lib.php';

echo json_encode([
    'ok' => true,
    'support_lib' => [
        'path' => $lib,
        'exists' => is_file($lib),
        'mtime' => is_file($lib) ? date('c', filemtime($lib)) : null,
    ],
    'functions' => [
        'supportLoad' => function_exists('supportLoad'),
        'supportGetUserProfileSummary' => function_exists('supportGetUserProfileSummary'),
        'supportUsernamesMatch' => function_exists('supportUsernamesMatch'),
        'supportProcessAdminActions' => function_exists('supportProcessAdminActions'),
        'supportAdminFormAction' => function_exists('supportAdminFormAction'),
    ],
    'mbstring' => extension_loaded('mbstring'),
    'support_json' => [
        'path' => $root . '/db/support.json',
        'exists' => is_file($root . '/db/support.json'),
        'writable' => is_writable($root . '/db/support.json'),
    ],
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
