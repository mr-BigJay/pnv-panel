<?php

header('Content-Type: application/json; charset=utf-8');

register_shutdown_function(function(){

    $err = error_get_last();

    if(!$err || !in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)){
        return;
    }

    if(headers_sent()){
        return;
    }

    http_response_code(500);
    echo json_encode([
        'error' => 'PHP fatal',
        'detail' => $err['message'],
        'file' => basename($err['file'] ?? ''),
        'line' => intval($err['line'] ?? 0),
    ], JSON_UNESCAPED_UNICODE);

});

$action = trim((string)($_GET['action'] ?? ''));

if($action === 'ping'){

    $libPath = __DIR__ . '/../support_lib.php';
    $libSrc = is_file($libPath) ? file_get_contents($libPath) : '';

    echo json_encode([
        'ok' => true,
        'api_version' => 3,
        'php' => PHP_VERSION,
        'api_file' => __FILE__,
        'lib_exists' => is_file($libPath),
        'lib_size' => is_file($libPath) ? filesize($libPath) : 0,
        'lib_has_tickets_fn' => strpos($libSrc, 'function supportTicketsListForApi') !== false,
    ], JSON_UNESCAPED_UNICODE);
    exit;

}

require_once __DIR__ . '/auth.php';

if(!pnvAdminIsLoggedIn()){
    http_response_code(403);
    echo json_encode(['error' => 'forbidden'], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/../support_lib.php';

$file = __DIR__ . '/../db/support.json';
$embedded = !empty($_GET['embedded']) || (function_exists('supportIsEmbeddedRequest') && supportIsEmbeddedRequest());
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

try{

    if($method === 'POST'){
        supportAdminApiRequireLib('post');
        supportAdminApiHandlePost($file, $embedded);
    }

    if($action === 'bootstrap'){
        supportAdminApiRequireLib('bootstrap');
        supportApiRespond(supportAdminApiBootstrap($embedded));
    }

    if($action === 'tickets'){
        supportAdminApiRequireLib('tickets');
        $data = supportLoad($file);
        supportApiRespond(supportTicketsListForApi($data));
    }

    $user = supportNormalizeUsername($_GET['user'] ?? '');
    $since = intval($_GET['since'] ?? 0);
    $syncAll = !empty($_GET['sync']);

    if($action === 'messages' || ($action === '' && $user !== '')){
        supportAdminApiRequireLib('messages');
        supportApiRespond(supportAdminApiMessages($file, $user, $since, $syncAll));
    }

    if($action === 'messages' && $user === ''){
        supportApiRespond(['error' => 'user required'], 400);
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

    supportApiRespond($payload);

}
catch(Throwable $e){

    supportApiRespond([
        'error' => 'server error',
        'detail' => $e->getMessage(),
    ], 500);

}

function supportAdminApiRequireLib($feature){

    $map = [
        'bootstrap' => 'supportAdminApiBootstrap',
        'tickets' => 'supportTicketsListForApi',
        'messages' => 'supportAdminApiMessages',
        'post' => 'supportAdminApiHandlePost',
    ];

    $fn = $map[$feature] ?? '';

    if($fn !== '' && function_exists($fn)){
        return;
    }

    supportApiRespond([
        'error' => 'support_lib.php روی سرور قدیمی است — deploy-support-v2.sh را اجرا کنید',
        'missing' => $fn !== '' ? $fn : $feature,
        'lib_path' => dirname(__DIR__) . '/support_lib.php',
    ], 500);

}
