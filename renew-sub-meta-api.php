<?php

session_start();

header('Content-Type: application/json; charset=utf-8');

if(!isset($_SESSION['user'])){
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'unauthorized'], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/subscription_lib.php';
require_once __DIR__ . '/plan_ui_lib.php';

$link = trim((string)($_GET['link'] ?? ''));
$user = trim((string)$_SESSION['user']);

if($link === ''){
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'link required'], JSON_UNESCAPED_UNICODE);
    exit;
}

$planText = function_exists('pnvFindSubPlanTextFromCsv')
    ? trim((string)pnvFindSubPlanTextFromCsv($user, $link))
    : '';

$timeCategory = pnvResolveSubTimeCategory($link, $planText, $user);

echo json_encode([
    'ok' => true,
    'time_category' => $timeCategory,
    'plan_text' => $planText,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
