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
$sync = [];

foreach($data as $ticket){

    if(!supportUsernamesMatch($ticket['user'] ?? '', $user)){
        continue;
    }

    $status = $ticket['status'] ?? '';

    if(empty($ticket['messages'])){
        break;
    }

    $syncAll = !empty($_GET['sync']);
    $sync = [];

    foreach($ticket['messages'] as $msg){

        $timestamp = intval($msg['timestamp'] ?? 0);

        if($syncAll){
            $sync[] = supportMessageForApi($msg, ['isAdmin' => false]);
        }

        if($since > 0 && $timestamp <= $since){
            continue;
        }

        $messages[] = supportMessageForApi($msg, ['isAdmin' => false]);

    }

    break;

}

$payload = [
    'messages' => $messages,
    'status' => $status
];

if(!empty($_GET['sync'])){
    $payload['sync'] = $sync ?? [];
}

echo json_encode($payload, JSON_UNESCAPED_UNICODE);
