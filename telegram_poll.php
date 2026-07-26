<?php

if(PHP_SAPI !== 'cli'){
    http_response_code(403);
    exit('CLI only');
}

require_once __DIR__ . '/telegram_lib.php';

$config = telegramLoadConfig();

if(empty($config['enabled']) || trim((string)($config['bot_token'] ?? '')) === ''){
    exit("Telegram bot is disabled or not configured.\n");
}

$lockFile = __DIR__ . '/db/telegram_poll.lock';
$lock = fopen($lockFile, 'c');

if($lock === false || !flock($lock, LOCK_EX | LOCK_NB)){
    exit("Telegram poll already running.\n");
}

$stateFile = __DIR__ . '/db/telegram_updates.json';
$state = file_exists($stateFile) ? json_decode(file_get_contents($stateFile), true) : [];

if(!is_array($state)){
    $state = [];
}

$offset = intval($state['offset'] ?? 0);
$updates = telegramApiRequest('getUpdates', [
    'offset' => $offset,
    'timeout' => 20,
    'allowed_updates' => json_encode(['message', 'callback_query'])
], [], $config);

if(empty($updates['ok']) || !is_array($updates['result'] ?? null)){
    flock($lock, LOCK_UN);
    fclose($lock);
    fwrite(STDERR, ($updates['description'] ?? 'Unable to fetch Telegram updates') . PHP_EOL);
    exit(1);
}

foreach($updates['result'] as $update){
    $updateId = intval($update['update_id'] ?? 0);

    if($updateId > 0){
        $state['offset'] = $updateId + 1;
    }

    if(isset($update['callback_query'])){
        telegramHandleCallback($update['callback_query'], $config);
        continue;
    }

    $message = $update['message'] ?? [];
    $chatId = (string)($message['chat']['id'] ?? '');
    $text = trim((string)($message['text'] ?? ''));

    if($chatId === '' || $text === '' || !telegramCanUseBot($chatId, $config)){
        continue;
    }

    telegramHandleAdminText($chatId, $text, $config);
}

file_put_contents(
    $stateFile,
    json_encode($state, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
    LOCK_EX
);

flock($lock, LOCK_UN);
fclose($lock);
