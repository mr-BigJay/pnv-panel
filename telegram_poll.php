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

$stateFile = __DIR__ . '/db/telegram_updates.json';
$state = file_exists($stateFile) ? json_decode(file_get_contents($stateFile), true) : [];

if(!is_array($state)){
    $state = [];
}

$offset = intval($state['offset'] ?? 0);
$updates = telegramApiRequest('getUpdates', [
    'offset' => $offset,
    'timeout' => 20,
    'allowed_updates' => json_encode(['message'])
], [], $config);

if(empty($updates['ok']) || !is_array($updates['result'] ?? null)){
    fwrite(STDERR, ($updates['description'] ?? 'Unable to fetch Telegram updates') . PHP_EOL);
    exit(1);
}

foreach($updates['result'] as $update){
    $updateId = intval($update['update_id'] ?? 0);
    $message = $update['message'] ?? [];
    $chatId = (string)($message['chat']['id'] ?? '');
    $text = trim((string)($message['text'] ?? ''));

    if($updateId > 0){
        $state['offset'] = $updateId + 1;
    }

    if($chatId === '' || !telegramCanUseBot($chatId, $config)){
        continue;
    }

    if($text === '/start' || $text === '/messages' || $text === 'پیام کاربران'){
        telegramApiRequest('sendMessage', [
            'chat_id' => $chatId,
            'text' => telegramSupportSummary(),
            'reply_markup' => json_encode([
                'keyboard' => [[['text' => 'پیام کاربران']]],
                'resize_keyboard' => true
            ], JSON_UNESCAPED_UNICODE)
        ], [], $config);
    }
}

file_put_contents(
    $stateFile,
    json_encode($state, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
    LOCK_EX
);
