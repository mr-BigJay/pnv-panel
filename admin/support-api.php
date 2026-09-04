<?php

require_once __DIR__ . '/auth.php';

header('Content-Type: application/json; charset=utf-8');

if(!pnvAdminIsLoggedIn()){
    http_response_code(403);
    echo json_encode(['error' => 'forbidden'], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/../support_lib.php';

$file = __DIR__ . '/../db/support.json';
$embedded = !empty($_GET['embedded']) || supportIsEmbeddedRequest();
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$action = trim((string)($_GET['action'] ?? ''));

if($method === 'POST'){
    supportAdminApiHandlePost($file, $embedded);
}

if($action === 'bootstrap'){
    echo json_encode(supportAdminApiBootstrap($embedded), JSON_UNESCAPED_UNICODE);
    exit;
}

if($action === 'tickets'){
    $data = supportLoad($file);
    echo json_encode(supportTicketsListForApi($data), JSON_UNESCAPED_UNICODE);
    exit;
}

$user = supportNormalizeUsername($_GET['user'] ?? '');
$since = intval($_GET['since'] ?? 0);
$syncAll = !empty($_GET['sync']);

if($action === 'messages' || ($action === '' && $user !== '')){
    $payload = supportAdminApiMessages($file, $user, $since, $syncAll);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if($action === 'messages' && $user === ''){
    http_response_code(400);
    echo json_encode(['error' => 'user required'], JSON_UNESCAPED_UNICODE);
    exit;
}

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
    'unread_count' => supportAdminUnreadTotal($data),
];

if($syncAll){
    $payload['sync'] = $sync;
}

echo json_encode($payload, JSON_UNESCAPED_UNICODE);
