<?php

session_start();

header('Content-Type: application/json; charset=utf-8');

if(!isset($_SESSION['admin'])){
    http_response_code(403);
    echo json_encode(['error' => 'forbidden']);
    exit;
}

require_once __DIR__ . '/../support_lib.php';

$file = __DIR__ . '/../db/support.json';
$user = trim($_GET['user'] ?? '');
$since = intval($_GET['since'] ?? 0);

$data = supportLoad($file);
$messages = [];
$status = '';
$unreadUsers = [];

foreach($data as $ticket){

    if(supportTicketHasUnreadForAdmin($ticket)){
        $unreadUsers[] = $ticket['user'] ?? '';
    }

    if($user === '' || ($ticket['user'] ?? '') !== $user){
        continue;
    }

    $status = $ticket['status'] ?? '';

    if(empty($ticket['messages'])){
        continue;
    }

    foreach($ticket['messages'] as $msg){

        $timestamp = intval($msg['timestamp'] ?? 0);

        if($since > 0 && $timestamp <= $since){
            continue;
        }

        $image = $msg['image'] ?? '';

        if($image !== ''){
            $image = '/' . ltrim($image, '/');
        }

        $messages[] = [
            'id' => $msg['id'] ?? '',
            'sender' => $msg['sender'] ?? '',
            'text' => $msg['text'] ?? '',
            'image' => $image,
            'date' => $msg['date'] ?? '',
            'time' => $msg['time'] ?? '',
            'timestamp' => $timestamp,
            'edited' => !empty($msg['edited'])
        ];

    }

}

echo json_encode([
    'messages' => $messages,
    'status' => $status,
    'unreadUsers' => $unreadUsers
], JSON_UNESCAPED_UNICODE);
