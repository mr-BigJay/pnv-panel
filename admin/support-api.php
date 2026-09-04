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
$user = supportNormalizeUsername($_GET['user'] ?? '');
$since = intval($_GET['since'] ?? 0);

$data = supportLoad($file);
$user = supportResolveTicketUsername($data, $user);
$messages = [];
$status = '';
$unreadUsers = [];
$sync = [];

foreach($data as $ticket){

    if(supportTicketHasUnreadForAdmin($ticket)){
        $unreadUsers[] = $ticket['user'] ?? '';
    }

    if($user === '' || !supportUsernamesMatch($ticket['user'] ?? '', $user)){
        continue;
    }

    $status = $ticket['status'] ?? '';

    if(empty($ticket['messages'])){
        continue;
    }

    $syncAll = !empty($_GET['sync']);
    $sync = [];

    foreach($ticket['messages'] as $msg){

        $timestamp = intval($msg['timestamp'] ?? 0);

        if($syncAll){
            $sync[] = supportMessageForApi($msg, ['isAdmin' => true]);
        }

        if($since > 0 && $timestamp <= $since){
            continue;
        }

        $messages[] = supportMessageForApi($msg, ['isAdmin' => true]);

    }

}

$payload = [
    'messages' => $messages,
    'status' => $status,
    'unreadUsers' => $unreadUsers,
    'has_unread' => count($unreadUsers) > 0,
    'unread_count' => supportAdminUnreadTotal($data)
];

if(!empty($_GET['sync'])){
    $payload['sync'] = $sync ?? [];
}

echo json_encode($payload, JSON_UNESCAPED_UNICODE);
