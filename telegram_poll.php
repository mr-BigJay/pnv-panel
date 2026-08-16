<?php

if(PHP_SAPI !== 'cli'){
    http_response_code(403);
    exit('CLI only');
}

require_once __DIR__ . '/telegram_lib.php';
require_once __DIR__ . '/telegram_user_lib.php';

$loop = in_array('--loop', $argv ?? [], true);
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

do {
    $config = telegramLoadConfig();

    if(empty($config['enabled']) || trim((string)($config['bot_token'] ?? '')) === ''){
        if(!$loop){
            break;
        }
        sleep(3);
        continue;
    }

    $state = file_exists($stateFile) ? json_decode(file_get_contents($stateFile), true) : [];

    if(!is_array($state)){
        $state = [];
    }

    $offset = intval($state['offset'] ?? 0);
    $updates = telegramApiRequest('getUpdates', [
        'offset' => $offset,
        'timeout' => $loop ? 25 : 5,
        'allowed_updates' => json_encode(['message', 'callback_query'])
    ], [], $config);

    if(empty($updates['ok']) || !is_array($updates['result'] ?? null)){
        if(!$loop){
            flock($lock, LOCK_UN);
            fclose($lock);
            fwrite(STDERR, ($updates['description'] ?? 'Unable to fetch Telegram updates') . PHP_EOL);
            exit(1);
        }

        telegramProcessPendingReminders($config);
        sleep(2);
        continue;
    }

    foreach($updates['result'] as $update){
        $updateId = intval($update['update_id'] ?? 0);

        if($updateId > 0){
            $state['offset'] = $updateId + 1;
        }

        file_put_contents(
            $stateFile,
            json_encode($state, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            LOCK_EX
        );

        if(isset($update['callback_query'])){
            $callbackChatId = (string)($update['callback_query']['message']['chat']['id'] ?? '');

            if($callbackChatId !== '' && telegramCanUseBot($callbackChatId, $config)){
                telegramHandleCallback($update['callback_query'], $config);
            }

            continue;
        }

        $message = $update['message'] ?? [];

        if(!is_array($message) || count($message) === 0){
            continue;
        }

        tgUserHandleMessage($message, $config);
    }

    telegramProcessPendingReminders($config);

} while($loop);

flock($lock, LOCK_UN);
fclose($lock);
