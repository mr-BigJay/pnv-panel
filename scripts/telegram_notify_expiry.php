<?php

if(PHP_SAPI !== 'cli'){
    http_response_code(403);
    exit('CLI only');
}

require_once __DIR__ . '/../telegram_lib.php';
require_once __DIR__ . '/../telegram_user_lib.php';

$config = telegramLoadConfig();

if(empty($config['enabled']) || trim((string)($config['bot_token'] ?? '')) === ''){
    exit("Telegram bot is disabled or not configured.\n");
}

tgUserProcessExpiryNotifications($config);
echo "Telegram expiry/traffic notifications processed.\n";
