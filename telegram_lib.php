<?php

if(!function_exists('telegramConfigPath')){
    function telegramConfigPath(){
        return __DIR__ . '/db/telegram.json';
    }

    function telegramSessionsPath(){
        return __DIR__ . '/db/telegram_sessions.json';
    }

    function telegramSupportPath(){
        return __DIR__ . '/db/support.json';
    }

    function telegramLoadConfig(){
        $defaults = [
            'enabled' => false,
            'bot_token' => '',
            'admin_chat_ids' => '',
            'local_proxy_urls' => [],
            'xray_vless_uris' => []
        ];

        $file = telegramConfigPath();

        if(!file_exists($file)){
            return $defaults;
        }

        $config = json_decode(file_get_contents($file), true);

        if(!is_array($config)){
            return $defaults;
        }

        $config = array_merge($defaults, $config);

        foreach(['local_proxy_urls', 'xray_vless_uris'] as $key){
            if(!is_array($config[$key])){
                $config[$key] = [];
            }
        }

        return $config;
    }

    function telegramSaveConfig($config){
        file_put_contents(
            telegramConfigPath(),
            json_encode($config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            LOCK_EX
        );
    }

    function telegramLinesToArray($value){
        $items = preg_split('/\r\n|\r|\n/', trim((string)$value));
        $items = array_map('trim', $items ?: []);
        return array_values(array_unique(array_filter($items)));
    }

    function telegramAdminChatIds($config = null){
        if($config === null){
            $config = telegramLoadConfig();
        }

        $ids = preg_split('/[\s,]+/', trim((string)($config['admin_chat_ids'] ?? '')));
        $ids = array_map('trim', $ids ?: []);
        return array_values(array_unique(array_filter($ids, function($id){
            return preg_match('/^-?\d+$/', $id);
        })));
    }

    function telegramCanUseBot($chatId, $config = null){
        return in_array((string)$chatId, telegramAdminChatIds($config), true);
    }

    function telegramProxyUrl($config){
        $proxies = $config['local_proxy_urls'] ?? [];

        if(!is_array($proxies) || count($proxies) === 0){
            return '';
        }

        $index = abs(crc32((string)microtime(true))) % count($proxies);
        return trim((string)$proxies[$index]);
    }

    function telegramLimitText($text, $length){
        $text = (string)$text;

        if(function_exists('mb_substr')){
            return mb_substr($text, 0, $length);
        }

        return substr($text, 0, $length);
    }

    function telegramMainKeyboard(){
        return json_encode([
            'keyboard' => [[['text' => 'پیام کاربران']]],
            'resize_keyboard' => true
        ], JSON_UNESCAPED_UNICODE);
    }

    function telegramApiRequest($method, $params = [], $files = [], $config = null){
        if($config === null){
            $config = telegramLoadConfig();
        }

        $token = trim((string)($config['bot_token'] ?? ''));

        if($token === ''){
            return ['ok' => false, 'description' => 'توکن بات ثبت نشده است'];
        }

        $url = 'https://api.telegram.org/bot' . rawurlencode($token) . '/' . $method;
        $proxy = telegramProxyUrl($config);

        if(function_exists('curl_init')){
            $payload = $params;

            foreach($files as $key => $path){
                if(is_file($path)){
                    $payload[$key] = new CURLFile($path);
                }
            }

            $curlTimeout = 25;
            if(isset($params['timeout'])){
                $curlTimeout = max(25, intval($params['timeout']) + 10);
            }

            $curl = curl_init($url);
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($curl, CURLOPT_TIMEOUT, $curlTimeout);

            if($proxy !== ''){
                $proxyType = CURLPROXY_SOCKS5_HOSTNAME;

                if(preg_match('#^https?://#i', $proxy)){
                    $proxyType = CURLPROXY_HTTP;
                }

                $proxyAddress = preg_replace('#^(socks5h?|https?)://#i', '', $proxy);
                curl_setopt($curl, CURLOPT_PROXY, $proxyAddress);
                curl_setopt($curl, CURLOPT_PROXYTYPE, $proxyType);
            }

            $body = curl_exec($curl);
            $error = curl_error($curl);
            $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);

            if($body === false || $error !== ''){
                return ['ok' => false, 'description' => 'خطا در ارتباط با تلگرام: ' . $error];
            }

            $result = json_decode($body, true);

            if(!is_array($result)){
                return ['ok' => false, 'description' => 'پاسخ نامعتبر از تلگرام (HTTP ' . $status . ')'];
            }

            return $result;
        }

        if(count($files) > 0){
            return ['ok' => false, 'description' => 'افزونه cURL برای ارسال تصویر لازم است'];
        }

        $payload = http_build_query($params);
        $options = [
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => $payload,
                'timeout' => 25,
                'ignore_errors' => true
            ]
        ];

        if($proxy !== ''){
            $options['http']['proxy'] = $proxy;
            $options['http']['request_fulluri'] = true;
        }

        $body = @file_get_contents($url, false, stream_context_create($options));
        $result = json_decode((string)$body, true);

        return is_array($result)
            ? $result
            : ['ok' => false, 'description' => 'ارتباط با تلگرام برقرار نشد'];
    }

    function telegramSendMessage($chatId, $text, $extra = [], $config = null){
        $params = array_merge([
            'chat_id' => $chatId,
            'text' => telegramLimitText($text, 4096)
        ], $extra);

        return telegramApiRequest('sendMessage', $params, [], $config);
    }

    function telegramEditMessage($chatId, $messageId, $text, $extra = [], $config = null){
        $params = array_merge([
            'chat_id' => $chatId,
            'message_id' => intval($messageId),
            'text' => telegramLimitText($text, 4096)
        ], $extra);

        return telegramApiRequest('editMessageText', $params, [], $config);
    }

    function telegramAnswerCallback($callbackId, $text = '', $config = null){
        return telegramApiRequest('answerCallbackQuery', [
            'callback_query_id' => $callbackId,
            'text' => telegramLimitText($text, 200),
            'show_alert' => false
        ], [], $config);
    }

    function telegramHistoryKeyboard($username){
        return json_encode([
            'inline_keyboard' => [
                [['text' => 'بازگشت', 'callback_data' => 'back']],
                [['text' => 'پاسخ', 'callback_data' => 'reply:' . $username]]
            ]
        ], JSON_UNESCAPED_UNICODE);
    }

    function telegramBuildUnreadCard($item){
        $preview = ($item['text'] ?? '') !== '' ? $item['text'] : 'یک تصویر ارسال شده است';
        $body = "📨 پیام خوانده‌نشده\n\n";
        $body .= 'کاربر: ' . ($item['username'] ?? '-') . "\n";

        if(!empty($item['mobile'])){
            $body .= 'موبایل: ' . $item['mobile'] . "\n";
        }

        $body .= 'زمان: ' . trim(($item['date'] ?? '') . ' ' . ($item['time'] ?? '')) . "\n\n";
        $body .= $preview;

        return $body;
    }

    function telegramSendToAdmins($text, $extra = [], $files = [], $config = null){
        if($config === null){
            $config = telegramLoadConfig();
        }

        if(empty($config['enabled']) || trim((string)($config['bot_token'] ?? '')) === ''){
            return [];
        }

        $results = [];

        foreach(telegramAdminChatIds($config) as $chatId){
            $params = array_merge(['chat_id' => $chatId], $extra);

            $method = count($files) > 0 ? 'sendPhoto' : 'sendMessage';

            if($method === 'sendPhoto'){
                $params['caption'] = telegramLimitText($text, 1024);
            }
            else{
                $params['text'] = telegramLimitText($text, 4096);
            }

            $results[] = telegramApiRequest($method, $params, $files, $config);
        }

        return $results;
    }

    function telegramLoadSessions(){
        $file = telegramSessionsPath();

        if(!file_exists($file)){
            return [];
        }

        $data = json_decode(file_get_contents($file), true);
        return is_array($data) ? $data : [];
    }

    function telegramSaveSessions($sessions){
        file_put_contents(
            telegramSessionsPath(),
            json_encode($sessions, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            LOCK_EX
        );
    }

    function telegramGetSession($chatId){
        $sessions = telegramLoadSessions();
        return is_array($sessions[(string)$chatId] ?? null) ? $sessions[(string)$chatId] : null;
    }

    function telegramSetSession($chatId, $session){
        $sessions = telegramLoadSessions();
        $sessions[(string)$chatId] = $session;
        telegramSaveSessions($sessions);
    }

    function telegramClearSession($chatId){
        $sessions = telegramLoadSessions();
        unset($sessions[(string)$chatId]);
        telegramSaveSessions($sessions);
    }

    function telegramLoadSupport(){
        $file = telegramSupportPath();

        if(!file_exists($file)){
            return [];
        }

        $data = json_decode(file_get_contents($file), true);
        return is_array($data) ? $data : [];
    }

    function telegramSaveSupport($data){
        file_put_contents(
            telegramSupportPath(),
            json_encode(array_values($data), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            LOCK_EX
        );
    }

    function telegramFindTicketIndex($data, $username){
        foreach($data as $i => $ticket){
            if(($ticket['user'] ?? '') === $username){
                return $i;
            }
        }

        return -1;
    }

    function telegramGetUserMobile($username){
        $usersFile = __DIR__ . '/db/users.json';

        if(!file_exists($usersFile)){
            return '';
        }

        $users = json_decode(file_get_contents($usersFile), true);

        if(!is_array($users)){
            return '';
        }

        foreach($users as $user){
            if(($user['username'] ?? '') === $username){
                return trim((string)($user['mobile'] ?? ''));
            }
        }

        return '';
    }

    function telegramTicketActionKeyboard($username){
        return json_encode([
            'inline_keyboard' => [[
                ['text' => 'مشاهده گفتگو', 'callback_data' => 'hist:' . $username],
                ['text' => 'پاسخ', 'callback_data' => 'reply:' . $username]
            ]]
        ], JSON_UNESCAPED_UNICODE);
    }

    function telegramCancelKeyboard(){
        return json_encode([
            'keyboard' => [[['text' => 'انصراف']], [['text' => 'پیام کاربران']]],
            'resize_keyboard' => true
        ], JSON_UNESCAPED_UNICODE);
    }

    function telegramFormatHistory($username, $limit = 20, $pageMode = false){
        $data = telegramLoadSupport();
        $index = telegramFindTicketIndex($data, $username);

        if($index < 0){
            return $pageMode
                ? "گفتگویی پیدا نشد."
                : "گفتگویی برای کاربر «{$username}» پیدا نشد.";
        }

        $messages = $data[$index]['messages'] ?? [];

        if(!is_array($messages) || count($messages) === 0){
            return $pageMode
                ? "هنوز پیامی در این گفتگو نیست."
                : "گفتگوی کاربر «{$username}» خالی است.";
        }

        $slice = array_slice($messages, -$limit);
        $lines = $pageMode ? [] : ["🗂 گفتگو با {$username}", ''];

        foreach($slice as $msg){
            $who = (($msg['sender'] ?? '') === 'admin') ? 'شما' : 'کاربر';
            $text = trim((string)($msg['text'] ?? ''));

            if($text === '' && !empty($msg['image'])){
                $text = '[تصویر]';
            }

            if(!empty($msg['edited'])){
                $text .= ' (ویرایش‌شده)';
            }

            if($pageMode){
                $lines[] = $who . ': ' . ($text !== '' ? $text : '-');
            }
            else{
                $time = trim(($msg['date'] ?? '') . ' ' . ($msg['time'] ?? ''));
                $lines[] = $who . ($time !== '' ? " ({$time})" : '') . ':';
                $lines[] = $text !== '' ? $text : '-';
                $lines[] = '';
            }
        }

        return telegramLimitText(trim(implode("\n", $lines)), 4000);
    }

    function telegramMarkSeenByAdmin($username){
        $data = telegramLoadSupport();
        $index = telegramFindTicketIndex($data, $username);

        if($index < 0){
            return false;
        }

        $changed = false;

        foreach($data[$index]['messages'] as $j => $msg){
            if(($msg['sender'] ?? '') === 'user' && empty($msg['seen_by_admin'])){
                $data[$index]['messages'][$j]['seen_by_admin'] = true;
                $changed = true;
            }
        }

        if($changed){
            telegramSaveSupport($data);
        }

        return $changed;
    }

    function telegramAddAdminReply($username, $text){
        $text = trim((string)$text);

        if($username === '' || $text === ''){
            return ['ok' => false, 'error' => 'متن پاسخ خالی است'];
        }

        $data = telegramLoadSupport();
        $index = telegramFindTicketIndex($data, $username);

        if($index < 0){
            return ['ok' => false, 'error' => 'تیکت کاربر پیدا نشد'];
        }

        $now = time();
        $newmsg = [
            'id' => uniqid(),
            'sender' => 'admin',
            'text' => $text,
            'image' => '',
            'date' => date('Y/m/d', $now),
            'time' => date('H:i', $now),
            'timestamp' => $now,
            'seen_by_user' => false
        ];

        $data[$index]['messages'][] = $newmsg;
        $data[$index]['status'] = 'answered';

        foreach($data[$index]['messages'] as $j => $msg){
            if(($msg['sender'] ?? '') === 'user'){
                $data[$index]['messages'][$j]['seen_by_admin'] = true;
            }
        }

        telegramSaveSupport($data);

        return ['ok' => true, 'message' => $newmsg];
    }

    function telegramTicketItemFromLast($username, $last, $unread = false){
        return [
            'username' => $username,
            'mobile' => telegramGetUserMobile($username),
            'text' => trim((string)($last['text'] ?? '')),
            'image' => trim((string)($last['image'] ?? '')),
            'date' => $last['date'] ?? '',
            'time' => $last['time'] ?? '',
            'timestamp' => intval($last['timestamp'] ?? 0),
            'unread' => $unread,
            'last_sender' => $last['sender'] ?? ''
        ];
    }

    function telegramUnreadTickets($limit = 15){
        $data = telegramLoadSupport();
        $items = [];

        foreach($data as $ticket){
            $username = trim((string)($ticket['user'] ?? ''));
            $messages = $ticket['messages'] ?? [];

            if($username === '' || !is_array($messages) || count($messages) === 0){
                continue;
            }

            $last = end($messages);

            if(!is_array($last) || ($last['sender'] ?? '') !== 'user'){
                continue;
            }

            if(!empty($last['seen_by_admin'])){
                continue;
            }

            $items[] = telegramTicketItemFromLast($username, $last, true);
        }

        usort($items, function($a, $b){
            return ($b['timestamp'] <=> $a['timestamp']);
        });

        return array_slice($items, 0, $limit);
    }

    function telegramRecentTickets($limit = 10){
        $data = telegramLoadSupport();
        $items = [];

        foreach($data as $ticket){
            $username = trim((string)($ticket['user'] ?? ''));
            $messages = $ticket['messages'] ?? [];

            if($username === '' || !is_array($messages) || count($messages) === 0){
                continue;
            }

            $last = end($messages);

            if(!is_array($last)){
                continue;
            }

            $unread = (($last['sender'] ?? '') === 'user' && empty($last['seen_by_admin']));
            $items[] = telegramTicketItemFromLast($username, $last, $unread);
        }

        usort($items, function($a, $b){
            return ($b['timestamp'] <=> $a['timestamp']);
        });

        return array_slice($items, 0, $limit);
    }

    function telegramSupportSummary(){
        $items = telegramUnreadTickets(20);

        if(count($items) === 0){
            return '';
        }

        $lines = ['📨 پیام کاربران (' . count($items) . ')', ''];

        foreach($items as $item){
            $preview = $item['text'] !== '' ? $item['text'] : 'تصویر';
            $lines[] = '• ' . $item['username'] . ': ' . telegramLimitText($preview, 120);
        }

        return implode("\n", $lines);
    }

    function telegramBuildTicketCard($item){
        $preview = ($item['text'] ?? '') !== '' ? $item['text'] : 'یک تصویر ارسال شده است';
        $title = !empty($item['unread']) ? '📨 پیام خوانده‌نشده' : '💬 گفتگوی اخیر';
        $body = $title . "\n\n";
        $body .= 'کاربر: ' . ($item['username'] ?? '-') . "\n";

        if(!empty($item['mobile'])){
            $body .= 'موبایل: ' . $item['mobile'] . "\n";
        }

        $body .= 'زمان: ' . trim(($item['date'] ?? '') . ' ' . ($item['time'] ?? '')) . "\n";

        if(($item['last_sender'] ?? '') === 'admin'){
            $body .= "آخرین پیام: پشتیبانی\n\n";
        }
        else{
            $body .= "آخرین پیام: کاربر\n\n";
        }

        $body .= $preview;
        return $body;
    }

    function telegramBuildUnreadCard($item){
        return telegramBuildTicketCard($item);
    }

    function telegramSendUnreadTickets($chatId, $config = null){
        $items = telegramUnreadTickets(10);
        $source = 'unread';

        if(count($items) === 0){
            $items = telegramRecentTickets(8);
            $source = 'recent';
        }

        if(count($items) === 0){
            telegramSendMessage(
                $chatId,
                "هنوز گفتگویی برای نمایش نیست.",
                ['reply_markup' => telegramMainKeyboard()],
                $config
            );
            return 0;
        }

        if($source === 'unread'){
            telegramSendMessage(
                $chatId,
                '📨 ' . count($items) . ' پیام خوانده‌نشده:',
                ['reply_markup' => telegramMainKeyboard()],
                $config
            );
        }
        else{
            telegramSendMessage(
                $chatId,
                "پیام خوانده‌نشده‌ای نیست.\nآخرین گفتگوها:",
                ['reply_markup' => telegramMainKeyboard()],
                $config
            );
        }

        foreach($items as $item){
            telegramSendMessage($chatId, telegramBuildTicketCard($item), [
                'reply_markup' => telegramTicketActionKeyboard($item['username'])
            ], $config);
        }

        return count($items);
    }

    function telegramSendSupportNotification($username, $message, $mobile = ''){
        $text = trim((string)($message['text'] ?? ''));
        $image = trim((string)($message['image'] ?? ''));
        $history = telegramFormatHistory($username, 8);

        $header = "🔔 پیام جدید کاربر\n\n";
        $header .= 'کاربر: ' . $username . "\n";

        if($mobile !== '' && $mobile !== '-'){
            $header .= 'موبایل: ' . $mobile . "\n";
        }

        $header .= 'زمان: ' . ($message['date'] ?? '') . ' ' . ($message['time'] ?? '') . "\n\n";
        $header .= "—— پیام جدید ——\n";
        $header .= $text !== '' ? $text : 'یک تصویر ارسال شده است';

        $body = $header . "\n\n—— گفتگوی اخیر ——\n" . $history;
        $extra = [
            'reply_markup' => telegramTicketActionKeyboard($username)
        ];
        $file = __DIR__ . '/' . ltrim($image, '/');
        $results = [];

        if($image !== '' && is_file($file)){
            $results = telegramSendToAdmins($header, [], ['photo' => $file]);
            $results = array_merge($results, telegramSendToAdmins($body, $extra));
            return $results;
        }

        return telegramSendToAdmins($body, $extra);
    }

    function telegramSetCommands($config = null){
        return telegramApiRequest('setMyCommands', [
            'commands' => json_encode([
                ['command' => 'start', 'description' => 'نمایش منو'],
                ['command' => 'messages', 'description' => 'پیام کاربران'],
                ['command' => 'cancel', 'description' => 'لغو پاسخ']
            ], JSON_UNESCAPED_UNICODE)
        ], [], $config);
    }

    function telegramHandleCallback($callback, $config = null){
        $callbackId = $callback['id'] ?? '';
        $chatId = (string)($callback['message']['chat']['id'] ?? '');
        $messageId = intval($callback['message']['message_id'] ?? 0);
        $prevText = trim((string)($callback['message']['text'] ?? ($callback['message']['caption'] ?? '')));
        $data = trim((string)($callback['data'] ?? ''));

        if($chatId === '' || !telegramCanUseBot($chatId, $config)){
            telegramAnswerCallback($callbackId, 'دسترسی ندارید', $config);
            return;
        }

        // فوراً پاسخ تلگرام را ببند تا دکمه گیر نکند
        telegramAnswerCallback($callbackId, '', $config);

        if($data === 'cancel'){
            telegramClearSession($chatId);
            telegramSendMessage($chatId, 'حالت پاسخ لغو شد.', [
                'reply_markup' => telegramMainKeyboard()
            ], $config);
            return;
        }

        if($data === 'back'){
            $session = telegramGetSession($chatId);
            $username = trim((string)($session['username'] ?? ''));
            $restoreText = trim((string)($session['prev_text'] ?? ''));
            $restoreMessageId = intval($session['message_id'] ?? $messageId);

            if($restoreText === '' && $username !== ''){
                $items = telegramUnreadTickets(50);
                foreach($items as $item){
                    if(($item['username'] ?? '') === $username){
                        $restoreText = telegramBuildUnreadCard($item);
                        break;
                    }
                }
            }

            if($restoreText === ''){
                $restoreText = $username !== ''
                    ? ("📨 کاربر: " . $username)
                    : 'به پیام قبلی برگشتید.';
            }

            $markup = $username !== ''
                ? telegramTicketActionKeyboard($username)
                : json_encode(['inline_keyboard' => []], JSON_UNESCAPED_UNICODE);

            $result = telegramEditMessage($chatId, $restoreMessageId, $restoreText, [
                'reply_markup' => $markup
            ], $config);

            if(empty($result['ok'])){
                telegramSendMessage($chatId, $restoreText, [
                    'reply_markup' => $markup
                ], $config);
            }

            telegramClearSession($chatId);
            return;
        }

        if(strpos($data, 'hist:') === 0){
            $username = substr($data, 5);
            $history = telegramFormatHistory($username, 30, true);

            telegramSetSession($chatId, [
                'mode' => 'history',
                'username' => $username,
                'message_id' => $messageId,
                'prev_text' => $prevText,
                'updated_at' => time()
            ]);

            $pageText = $history !== '' ? $history : 'پیامی برای نمایش نیست.';
            $result = telegramEditMessage($chatId, $messageId, $pageText, [
                'reply_markup' => telegramHistoryKeyboard($username)
            ], $config);

            if(empty($result['ok'])){
                $sent = telegramSendMessage($chatId, $pageText, [
                    'reply_markup' => telegramHistoryKeyboard($username)
                ], $config);

                if(!empty($sent['result']['message_id'])){
                    telegramSetSession($chatId, [
                        'mode' => 'history',
                        'username' => $username,
                        'message_id' => intval($sent['result']['message_id']),
                        'prev_text' => $prevText,
                        'updated_at' => time()
                    ]);
                }
            }

            return;
        }

        if(strpos($data, 'reply:') === 0){
            $username = substr($data, 6);
            $session = telegramGetSession($chatId);

            telegramSetSession($chatId, [
                'mode' => 'reply',
                'username' => $username,
                'message_id' => $messageId,
                'prev_text' => $prevText !== '' ? $prevText : ($session['prev_text'] ?? ''),
                'updated_at' => time()
            ]);

            telegramSendMessage(
                $chatId,
                "✍️ پاسخ به «{$username}»\nمتن پاسخ را بنویسید.\nانصراف: /cancel",
                ['reply_markup' => telegramCancelKeyboard()],
                $config
            );
            return;
        }
    }

    function telegramHandleAdminText($chatId, $text, $config = null){
        $text = trim((string)$text);

        if($text === '/start'){
            telegramClearSession($chatId);
            telegramSendMessage(
                $chatId,
                "بات پنل فعال است.\nوقتی کاربر پیام جدید بفرستد، اینجا اطلاع داده می‌شود.\nمی‌توانید گفتگو را ببینید و از همین‌جا پاسخ دهید.",
                ['reply_markup' => telegramMainKeyboard()],
                $config
            );
            return;
        }

        if($text === '/cancel' || $text === 'انصراف'){
            telegramClearSession($chatId);
            telegramSendMessage($chatId, 'حالت پاسخ لغو شد.', [
                'reply_markup' => telegramMainKeyboard()
            ], $config);
            return;
        }

        if($text === '/messages' || $text === 'پیام کاربران'){
            telegramSendUnreadTickets($chatId, $config);
            return;
        }

        $session = telegramGetSession($chatId);

        if(is_array($session) && ($session['mode'] ?? '') === 'reply'){
            $username = trim((string)($session['username'] ?? ''));
            $result = telegramAddAdminReply($username, $text);
            telegramClearSession($chatId);

            if(empty($result['ok'])){
                telegramSendMessage(
                    $chatId,
                    'ثبت پاسخ ناموفق بود: ' . ($result['error'] ?? 'خطای نامشخص'),
                    ['reply_markup' => telegramMainKeyboard()],
                    $config
                );
                return;
            }

            telegramSendMessage(
                $chatId,
                "✅ پاسخ برای کاربر «{$username}» ثبت شد و در پنل کاربر نمایش داده می‌شود.",
                ['reply_markup' => telegramMainKeyboard()],
                $config
            );
        }
    }
}
