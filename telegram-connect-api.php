<?php

session_start();
header('Content-Type: application/json; charset=utf-8');

if(!isset($_SESSION['user'])){
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'ورود لازم است'], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/telegram_lib.php';

$username = (string)$_SESSION['user'];
$action = trim((string)($_GET['action'] ?? $_POST['action'] ?? 'status'));

$config = telegramLoadConfig();
$botEnabled = !empty($config['enabled']) && trim((string)($config['bot_token'] ?? '')) !== '';
$chatId = telegramGetUserChatId($username);
$connected = $chatId !== '';

if($action === 'status'){
    echo json_encode([
        'ok' => true,
        'connected' => $connected,
        'bot_enabled' => $botEnabled
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if($action === 'token'){
    if(!$botEnabled){
        echo json_encode(['ok' => false, 'error' => 'ربات تلگرام فعال نیست'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $token = telegramGenerateConnectToken($username);

    if($token === ''){
        echo json_encode(['ok' => false, 'error' => 'خطا در ساخت توکن'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $botUsername = trim((string)($config['bot_username'] ?? ''));
    $botLink = $botUsername !== ''
        ? 'https://t.me/' . rawurlencode(ltrim($botUsername, '@')) . '?start=' . $token
        : '';

    echo json_encode([
        'ok' => true,
        'token' => $token,
        'bot_link' => $botLink,
        'expires_in' => 600
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if($action === 'disconnect'){
    if(!$connected){
        echo json_encode(['ok' => true, 'connected' => false], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $ok = telegramRemoveUserChatId($username);

    echo json_encode([
        'ok' => $ok,
        'connected' => false,
        'error' => $ok ? null : 'خطا در قطع اتصال'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['ok' => false, 'error' => 'action نامعتبر است'], JSON_UNESCAPED_UNICODE);
