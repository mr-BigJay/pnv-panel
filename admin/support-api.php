<?php

require_once __DIR__ . '/auth.php';

if(session_status() === PHP_SESSION_NONE){
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

$supportApiLoggedIn = function_exists('pnvAdminIsLoggedIn') && pnvAdminIsLoggedIn();

if(!$supportApiLoggedIn && !empty($_SESSION['admin']) && !empty($_SESSION['pnv_admin']['user'])){
    $supportApiLoggedIn = true;
}

if(!$supportApiLoggedIn){
    http_response_code(403);
    echo json_encode(['error' => 'forbidden']);
    exit;
}

require_once __DIR__ . '/../support_lib.php';

$file = __DIR__ . '/../db/support.json';
$data = supportLoad($file);
$user = trim($_GET['user'] ?? '');
if($user !== '' && function_exists('supportResolveTicketUsername')){
    $resolvedUser = supportResolveTicketUsername($data, $user);
    if($resolvedUser !== ''){
        $user = $resolvedUser;
    }
}
$since = intval($_GET['since'] ?? 0);
$messages = [];
$messageStates = [];
$status = '';
$unreadUsers = [];

foreach($data as $i => $ticket){

    if(supportTicketHasUnreadForAdmin($ticket)){
        $unreadUsers[] = $ticket['user'] ?? '';
    }

    if($user === ''){
        continue;
    }

    if(
        function_exists('supportUsernamesMatch')
            ? !supportUsernamesMatch($ticket['user'] ?? '', $user)
            : ($ticket['user'] ?? '') !== $user
    ){
        continue;
    }

    $status = $ticket['status'] ?? '';

    if(supportMarkSeenByAdmin($data, $user)){
        supportSave($file, $data);
    }

    $messageStates = supportTicketMessageStates($data[$i] ?? $ticket);

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
    'message_states' => $messageStates,
    'status' => $status,
    'unreadUsers' => $unreadUsers,
    'has_unread' => count($unreadUsers) > 0,
    'unread_count' => supportAdminUnreadTotal($data)
], JSON_UNESCAPED_UNICODE);
