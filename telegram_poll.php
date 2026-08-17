<?php

if(PHP_SAPI !== 'cli'){
    http_response_code(403);
    exit('CLI only');
}

require_once __DIR__ . '/telegram_lib.php';

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

        // offset را زود ذخیره کن تا در صورت خطا، آپدیت تکرار نشود
        file_put_contents(
            $stateFile,
            json_encode($state, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            LOCK_EX
        );

        if(isset($update['callback_query'])){
            telegramHandleCallback($update['callback_query'], $config);
            continue;
        }

        $message = $update['message'] ?? [];
        $chatId = (string)($message['chat']['id'] ?? '');
        $text = trim((string)($message['text'] ?? ''));

        if($chatId === '' || $text === ''){
            continue;
        }

        if(telegramCanUseBot($chatId, $config)){
            telegramHandleAdminText($chatId, $text, $config);
        } else {
            $fromInfo = [
                'tg_username' => trim((string)($message['from']['username'] ?? '')),
                'tg_name'     => trim((string)($message['from']['first_name'] ?? '')),
            ];
            telegramHandleUserText($chatId, $text, $fromInfo, $config);
        }
    }

    // یادآوری خرید/تمدید در انتظار تایید (حدود هر ۵ دقیقه، با حذف پیام قبلی)
    telegramProcessPendingReminders($config);

} while($loop);

flock($lock, LOCK_UN);
fclose($lock);
