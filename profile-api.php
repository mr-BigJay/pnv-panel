<?php

session_start();
header('Content-Type: application/json; charset=utf-8');

if(!isset($_SESSION['user'])){
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'دسترسی مجاز نیست'], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/profile_lib.php';
require_once __DIR__ . '/telegram_lib.php';
require_once __DIR__ . '/telegram_user_lib.php';

$username = (string)$_SESSION['user'];
$action = trim((string)($_POST['action'] ?? $_GET['action'] ?? ''));

if($action === 'telegram_status'){
    $status = tgUserGetTelegramStatus($username);
    $status['bot_enabled'] = !empty(telegramLoadConfig()['enabled']);
    $status['bot_username'] = tgUserGetBotUsername();
    echo json_encode(['ok' => true] + $status, JSON_UNESCAPED_UNICODE);
    exit;
}

if($action === 'telegram_link'){
    if($_SERVER['REQUEST_METHOD'] !== 'POST'){
        http_response_code(405);
        echo json_encode(['ok' => false, 'error' => 'درخواست نامعتبر است'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $config = telegramLoadConfig();

    if(empty($config['enabled']) || trim((string)($config['bot_token'] ?? '')) === ''){
        echo json_encode(['ok' => false, 'error' => 'ربات تلگرام در پنل فعال نیست'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $tokenResult = tgUserCreateLinkToken($username);

    if(empty($tokenResult['ok'])){
        echo json_encode($tokenResult, JSON_UNESCAPED_UNICODE);
        exit;
    }

    $url = tgUserBuildLinkUrl($tokenResult['token'], $config);

    if($url === ''){
        echo json_encode(['ok' => false, 'error' => 'ساخت لینک اتصال ناموفق بود. توکن ربات را بررسی کنید'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode([
        'ok' => true,
        'url' => $url,
        'expires_at' => intval($tokenResult['expires_at'] ?? 0),
        'bot_username' => tgUserGetBotUsername($config),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if($action === 'telegram_disconnect'){
    if($_SERVER['REQUEST_METHOD'] !== 'POST'){
        http_response_code(405);
        echo json_encode(['ok' => false, 'error' => 'درخواست نامعتبر است'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode(tgUserDisconnect($username), JSON_UNESCAPED_UNICODE);
    exit;
}

if($action === 'telegram_test'){
    if($_SERVER['REQUEST_METHOD'] !== 'POST'){
        http_response_code(405);
        echo json_encode(['ok' => false, 'error' => 'درخواست نامعتبر است'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $result = tgUserNotifyIfEnabled(
        $username,
        '',
        "پیام آزمایشی ارسال شد. ✅\n\nاگر این پیام را می‌بینید، اتصال تلگرام درست کار می‌کند."
    );

    if(empty($result['ok'])){
        echo json_encode([
            'ok' => false,
            'error' => $result['error'] ?? 'ارسال پیام آزمایشی ناموفق بود',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    exit;
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

http_response_code(400);
echo json_encode(['ok' => false, 'error' => 'عملیات نامعتبر است'], JSON_UNESCAPED_UNICODE);
