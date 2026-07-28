<?php

session_start();

header('Content-Type: application/json; charset=utf-8');

if(!isset($_SESSION['user'])){
    http_response_code(403);
    echo json_encode(['error' => 'forbidden']);
    exit;
}

require_once __DIR__ . '/support_lib.php';

$file = __DIR__ . '/db/support.json';
$user = $_SESSION['user'];
$since = intval($_GET['since'] ?? 0);

$data = supportLoad($file);
$messages = [];
$status = '';

foreach($data as $ticket){

    if(($ticket['user'] ?? '') !== $user){
        continue;
    }

    $status = $ticket['status'] ?? '';

    if(empty($ticket['messages'])){
        break;
    }

    foreach($ticket['messages'] as $msg){

        $timestamp = intval($msg['timestamp'] ?? 0);

        if($since > 0 && $timestamp <= $since){
            continue;
        }

        $messages[] = supportMessageForApi($msg);

    }

    break;

}

echo json_encode([
    'messages' => $messages,
    'status' => $status
], JSON_UNESCAPED_UNICODE);
