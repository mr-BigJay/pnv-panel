<?php

session_start();
header('Content-Type: application/json; charset=utf-8');

if(!isset($_SESSION['user'])){
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'دسترسی مجاز نیست'], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/profile_lib.php';

$username = (string)$_SESSION['user'];
$action = trim((string)($_POST['action'] ?? $_GET['action'] ?? ''));

$mobileVerifyActions = ['mobile_send_code', 'mobile_verify_code'];
if(!in_array($action, $mobileVerifyActions, true)){
    require_once __DIR__ . '/mobile_verify_lib.php';
    mobileVerifyGuardApiIfNeeded($username);
}

if($action === 'avatar'){
    if($_SERVER['REQUEST_METHOD'] !== 'POST'){
        http_response_code(405);
        echo json_encode(['ok' => false, 'error' => 'درخواست نامعتبر است'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $result = profileUploadAvatar($username, $_FILES['avatar'] ?? []);
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    exit;
}

if($action === 'username'){
    if($_SERVER['REQUEST_METHOD'] !== 'POST'){
        http_response_code(405);
        echo json_encode(['ok' => false, 'error' => 'درخواست نامعتبر است'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $newUsername = trim((string)($_POST['username'] ?? ''));
    $result = profileChangeUsername($username, $newUsername);
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    exit;
}

if($action === 'mobile_send_code'){
    if($_SERVER['REQUEST_METHOD'] !== 'POST'){
        http_response_code(405);
        echo json_encode(['ok' => false, 'error' => 'درخواست نامعتبر است'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    require_once __DIR__ . '/mobile_verify_lib.php';
    $result = mobileVerifySendCode($username);
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    exit;
}

if($action === 'mobile_verify_code'){
    if($_SERVER['REQUEST_METHOD'] !== 'POST'){
        http_response_code(405);
        echo json_encode(['ok' => false, 'error' => 'درخواست نامعتبر است'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    require_once __DIR__ . '/mobile_verify_lib.php';
    $code = trim((string)($_POST['code'] ?? ''));
    $result = mobileVerifyCheckCode($username, $code);
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code(400);
echo json_encode(['ok' => false, 'error' => 'عملیات نامعتبر است'], JSON_UNESCAPED_UNICODE);
