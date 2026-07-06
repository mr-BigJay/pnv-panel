<?php

require_once __DIR__ . '/auth.php';

header('Content-Type: application/json; charset=utf-8');

if(!pnvAdminIsLoggedIn()){
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

        $messages[] = supportMessageForApi($msg);

    }

}

echo json_encode([
    'messages' => $messages,
    'status' => $status,
    'unreadUsers' => $unreadUsers,
    'has_unread' => count($unreadUsers) > 0,
    'unread_count' => supportAdminUnreadTotal($data)
], JSON_UNESCAPED_UNICODE);
