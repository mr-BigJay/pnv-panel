<?php

require_once __DIR__ . '/auth.php';

if(!pnvAdminIsLoggedIn()){
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

header('Content-Type: text/plain; charset=utf-8');

require_once __DIR__ . '/../telegram_lib.php';

$config = telegramLoadConfig();
$file = telegramConfigPath();
$configured = trim((string)($config['bot_token'] ?? '')) !== '';
$enabled = !empty($config['enabled']) && $configured;

echo "Telegram status\n";
echo "===============\n\n";
echo "Config file: {$file}\n";
echo "File exists: " . (is_file($file) ? 'yes' : 'no') . "\n";
echo "File bytes: " . (is_file($file) ? filesize($file) : 0) . "\n";
echo "bot_token set: " . ($configured ? 'yes' : 'no') . "\n";
echo "enabled flag: " . (!empty($config['enabled']) ? 'true' : 'false') . "\n";
echo "admin_chat_ids: " . trim((string)($config['admin_chat_ids'] ?? '')) . "\n";
echo "\nDashboard would show: ";

if($enabled){
    echo "فعال\n";
}
elseif($configured){
    echo "پیکربندی شده\n";
}
else{
    echo "نیاز به ستاپ\n";
}

echo "\ndashboard.php loads telegram locally: ";
echo (is_file(__DIR__ . '/dashboard.php') && strpos((string)file_get_contents(__DIR__ . '/dashboard.php'), 'telegramLoadConfig') !== false)
    ? "yes\n"
    : "NO — deploy admin/dashboard.php\n";

echo "index.php sets telegram vars: ";
echo (is_file(__DIR__ . '/index.php') && strpos((string)file_get_contents(__DIR__ . '/index.php'), '$telegramEnabled') !== false)
    ? "yes\n"
    : "NO — deploy admin/index.php\n";
