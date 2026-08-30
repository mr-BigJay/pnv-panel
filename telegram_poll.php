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
    telegramPollLog('exit: bot disabled or token empty');
    exit("Telegram bot is disabled or not configured.\n");
}

$pollReady = telegramEnsurePollingMode($config);

if(!empty($pollReady['webhook_removed'])){
    telegramPollLog('removed webhook: ' . $pollReady['webhook_removed']);
}

if(empty($pollReady['ok'])){
    telegramPollLog('polling blocked: ' . ($pollReady['description'] ?? 'webhook conflict'));
    fwrite(STDERR, ($pollReady['description'] ?? 'Webhook conflict — delete webhook in admin Telegram settings') . PHP_EOL);
    exit(1);
}

$lockFile = __DIR__ . '/db/telegram_poll.lock';
$lock = fopen($lockFile, 'c');

if($lock === false || !flock($lock, LOCK_EX | LOCK_NB)){
    telegramPollLog('exit: poll already running');
    exit("Telegram poll already running.\n");
}

$stateFile = __DIR__ . '/db/telegram_updates.json';
telegramPollLog('started loop=' . ($loop ? '1' : '0'));

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
        'allowed_updates' => ['message', 'callback_query']
    ], [], $config);

    if(empty($updates['ok']) || !is_array($updates['result'] ?? null)){
        $desc = (string)($updates['description'] ?? 'Unable to fetch Telegram updates');
        telegramPollLog('getUpdates fail: ' . $desc);

        if(intval($updates['error_code'] ?? 0) === 409 || stripos($desc, 'webhook') !== false){
            $fix = telegramEnsurePollingMode($config);
            telegramPollLog('webhook fix: ' . json_encode($fix, JSON_UNESCAPED_UNICODE));
        }

        if(!$loop){
            flock($lock, LOCK_UN);
            fclose($lock);
            fwrite(STDERR, $desc . PHP_EOL);
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

        $chatId = (string)($message['chat']['id'] ?? '');
        $text = trim((string)($message['text'] ?? ''));
        telegramPollLog('msg chat=' . $chatId . ' text=' . telegramLimitText($text, 80));

        tgUserHandleMessage($message, $config);
    }

    telegramProcessPendingReminders($config);

} while($loop);

flock($lock, LOCK_UN);
fclose($lock);
