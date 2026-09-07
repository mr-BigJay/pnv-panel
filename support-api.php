<?php

session_start();

header('Content-Type: application/json; charset=utf-8');

if(!isset($_SESSION['user'])){
    http_response_code(403);
    echo json_encode(['error' => 'forbidden'], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/support_lib.php';

$file = __DIR__ . '/db/support.json';
$user = $_SESSION['user'];
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$action = trim((string)($_GET['action'] ?? ''));

try{

    if($method === 'POST'){
        if(!function_exists('supportUserApiHandlePost')){
            http_response_code(500);
            echo json_encode([
                'error' => 'support_lib.php روی سرور قدیمی است — deploy را دوباره اجرا کنید',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        supportUserApiHandlePost($file, $user);
    }

    if($action === 'bootstrap'){
        if(!function_exists('supportUserApiBootstrap')){
            http_response_code(500);
            echo json_encode(['error' => 'support_lib.php قدیمی است'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        echo json_encode(supportUserApiBootstrap(), JSON_UNESCAPED_UNICODE);
        exit;
    }

    $since = intval($_GET['since'] ?? 0);
    $syncAll = !empty($_GET['sync']);

    if($action === 'messages' || $action === ''){
        if(!function_exists('supportUserApiMessages')){
            http_response_code(500);
            echo json_encode(['error' => 'support_lib.php قدیمی است'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        echo json_encode(
            supportUserApiMessages($file, $user, $since, $syncAll),
            JSON_UNESCAPED_UNICODE
        );
        exit;
    }

    http_response_code(400);
    echo json_encode(['error' => 'action نامعتبر است'], JSON_UNESCAPED_UNICODE);

}
catch(Throwable $e){

    http_response_code(500);
    echo json_encode([
        'error' => 'server error',
        'detail' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);

}
