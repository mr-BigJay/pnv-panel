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
            $curlTimeout = 25;
            if(isset($params['timeout'])){
                $curlTimeout = max(25, intval($params['timeout']) + 10);
            }

            $curl = curl_init($url);
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($curl, CURLOPT_TIMEOUT, $curlTimeout);

            if(count($files) > 0){
                $payload = $params;

                if(isset($payload['reply_markup']) && is_array($payload['reply_markup'])){
                    $payload['reply_markup'] = json_encode(
                        $payload['reply_markup'],
                        JSON_UNESCAPED_UNICODE
                    );
                }

                foreach($files as $key => $path){
                    if(is_file($path)){
                        $payload[$key] = new CURLFile($path);
                    }
                }

                curl_setopt($curl, CURLOPT_POSTFIELDS, $payload);
            }
            else{
                $payload = $params;

                // اگر کیبورد به‌صورت JSON string آمده، به آرایه برگردان
                if(isset($payload['reply_markup']) && is_string($payload['reply_markup'])){
                    $decodedMarkup = json_decode($payload['reply_markup'], true);
                    if(is_array($decodedMarkup)){
                        $payload['reply_markup'] = $decodedMarkup;
                    }
                }

                if(isset($payload['allowed_updates']) && is_string($payload['allowed_updates'])){
                    $decodedUpdates = json_decode($payload['allowed_updates'], true);
                    if(is_array($decodedUpdates)){
                        $payload['allowed_updates'] = $decodedUpdates;
                    }
                }

                curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                curl_setopt(
                    $curl,
                    CURLOPT_POSTFIELDS,
                    json_encode($payload, JSON_UNESCAPED_UNICODE)
                );
            }

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

    function telegramInline(array $rows){
        return json_encode(['inline_keyboard' => $rows], JSON_UNESCAPED_UNICODE);
    }

    function telegramPaymentsPath(){
        return __DIR__ . '/invoices/payments.csv';
    }

    function telegramIsPendingStatus($status){
        $status = trim((string)$status);
        return $status !== 'تایید شد' && $status !== 'رد شد';
    }

    function telegramLoadPendingPayments($kind = 'خرید', $limit = 20){
        $file = telegramPaymentsPath();
        $items = [];

        if(!file_exists($file)){
            return [];
        }

        $handle = fopen($file, 'r');

        while(($row = fgetcsv($handle)) !== false){
            $type = trim((string)($row[9] ?? ''));
            $status = trim((string)($row[6] ?? 'درحال بررسی'));

            if(!telegramIsPendingStatus($status)){
                continue;
            }

            if($kind === 'خرید'){
                if($type !== 'خرید' && $type !== ''){
                    continue;
                }
            }
            elseif($type !== $kind){
                continue;
            }

            $username = trim((string)($row[0] ?? ''));

            $items[] = [
                'username' => $username,
                'mobile' => telegramGetUserMobile($username),
                'target' => trim((string)($row[1] ?? '')),
                'plan' => trim((string)($row[2] ?? '')),
                'tracking' => trim((string)($row[3] ?? '')),
                'date' => trim((string)($row[4] ?? '')),
                'time' => trim((string)($row[5] ?? '')),
                'status' => $status !== '' ? $status : 'درحال بررسی',
                'created' => intval($row[8] ?? 0),
                'type' => $type !== '' ? $type : 'خرید',
                'coupon' => trim((string)($row[10] ?? '')),
                'discount' => trim((string)($row[11] ?? ''))
            ];
        }

        fclose($handle);

        usort($items, function($a, $b){
            return ($b['created'] <=> $a['created']);
        });

        return array_slice($items, 0, $limit);
    }

    function telegramPaymentTitle($kind){
        return $kind === 'تمدید' ? 'تمدیدهای جدید' : 'خریدهای جدید';
    }

    function telegramPaymentEmoji($kind){
        return $kind === 'تمدید' ? '♻️' : '🛒';
    }

    function telegramFormatPaymentDetail($item, $kind){
        $title = telegramPaymentEmoji($kind) . ' ' . telegramPaymentTitle($kind);
        $label = $kind === 'تمدید' ? 'لینک اشتراک' : 'نام کانفیگ';
        $lines = [
            $title,
            '',
            'کاربر: ' . ($item['username'] ?? '-'),
        ];

        if(!empty($item['mobile'])){
            $lines[] = 'موبایل: ' . $item['mobile'];
        }

        $lines[] = $label . ': ' . ($item['target'] ?? '-');
        $lines[] = 'پلن: ' . ($item['plan'] ?? '-');
        $lines[] = 'پیگیری: ' . ($item['tracking'] ?? '-');
        $lines[] = 'تاریخ: ' . trim(($item['date'] ?? '') . ' ' . ($item['time'] ?? ''));
        $lines[] = 'وضعیت: ' . ($item['status'] ?? 'درحال بررسی');

        if(!empty($item['coupon'])){
            $lines[] = 'کد تخفیف: ' . $item['coupon'];
            if($item['discount'] !== ''){
                $lines[] = 'درصد تخفیف: ' . $item['discount'] . '٪';
            }
        }

        $lines[] = '';
        $lines[] = 'فعلاً فقط اطلاع‌رسانی است.';

        return implode("\n", $lines);
    }

    function telegramHomeText(){
        $buyCount = count(telegramLoadPendingPayments('خرید', 50));
        $renewCount = count(telegramLoadPendingPayments('تمدید', 50));
        $msgCount = count(telegramUnreadTickets(50));

        $lines = [
            '🏠 منوی اصلی',
            '',
            'یکی از بخش‌ها را انتخاب کنید:',
            '',
            'پیام کاربران: ' . $msgCount,
            'خریدهای جدید: ' . $buyCount,
            'تمدیدهای جدید: ' . $renewCount
        ];

        return implode("\n", $lines);
    }

    function telegramHomeKeyboard(){
        $buyCount = count(telegramLoadPendingPayments('خرید', 50));
        $renewCount = count(telegramLoadPendingPayments('تمدید', 50));

        return telegramInline([
            [['text' => 'پیام کاربران', 'callback_data' => 'menu:messages']],
            [['text' => 'خریدهای جدید' . ($buyCount ? " ({$buyCount})" : ''), 'callback_data' => 'menu:buys']],
            [['text' => 'تمدیدهای جدید' . ($renewCount ? " ({$renewCount})" : ''), 'callback_data' => 'menu:renews']]
        ]);
    }

    function telegramMessagesText($items, $source){
        if(count($items) === 0){
            return "📨 پیام کاربران\n\nهنوز گفتگویی برای نمایش نیست.";
        }

        if($source === 'unread'){
            return "📨 پیام کاربران\n\n" . count($items) . " پیام خوانده‌نشده\nکاربر را انتخاب کنید:";
        }

        return "📨 پیام کاربران\n\nپیام خوانده‌نشده‌ای نیست.\nآخرین گفتگوها:";
    }

    function telegramBackRow($callback){
        return [['text' => 'بازگشت', 'callback_data' => $callback]];
    }

    function telegramMessagesKeyboard($items){
        $rows = [
            telegramBackRow('menu:home')
        ];

        foreach($items as $item){
            $name = $item['username'] ?? '-';
            $label = (!empty($item['unread']) ? '● ' : '') . $name;
            $rows[] = [['text' => telegramLimitText($label, 60), 'callback_data' => 'chat:' . $name]];
        }

        // اگر لیست طولانی بود، بازگشت پایین هم باشد
        if(count($items) > 4){
            $rows[] = telegramBackRow('menu:home');
        }

        return telegramInline($rows);
    }

    function telegramChatKeyboard($username){
        return telegramInline([
            telegramBackRow('menu:messages'),
            [['text' => 'پاسخ', 'callback_data' => 'reply:' . $username]],
            telegramBackRow('menu:messages')
        ]);
    }

    function telegramReplyPageKeyboard($username){
        return telegramInline([
            telegramBackRow('chat:' . $username),
            [['text' => 'انصراف', 'callback_data' => 'menu:messages']]
        ]);
    }

    function telegramTicketActionKeyboard($username){
        return telegramInline([
            telegramBackRow('menu:home'),
            [
                ['text' => 'مشاهده گفتگو', 'callback_data' => 'chat:' . $username],
                ['text' => 'پاسخ', 'callback_data' => 'reply:' . $username]
            ]
        ]);
    }

    function telegramUpdateSessionScreen($chatId, $patch){
        $session = telegramGetSession($chatId);

        if(!is_array($session)){
            $session = [];
        }

        telegramSetSession($chatId, array_merge($session, $patch, ['updated_at' => time()]));
    }

    function telegramShowPage($chatId, $text, $keyboard, $config = null, $messageId = null){
        $extra = ['reply_markup' => $keyboard];

        if($messageId){
            $edited = telegramEditMessage($chatId, $messageId, $text, $extra, $config);

            if(!empty($edited['ok'])){
                // اطمینان از ست شدن دکمه‌ها
                telegramApiRequest('editMessageReplyMarkup', [
                    'chat_id' => $chatId,
                    'message_id' => intval($messageId),
                    'reply_markup' => $keyboard
                ], [], $config);

                telegramUpdateSessionScreen($chatId, [
                    'screen_message_id' => intval($messageId)
                ]);
                return intval($messageId);
            }
        }

        // حذف کیبورد قدیمی پایین صفحه
        telegramSendMessage($chatId, ".", [
            'reply_markup' => json_encode(['remove_keyboard' => true])
        ], $config);

        $sent = telegramSendMessage($chatId, $text, $extra, $config);
        $newId = intval($sent['result']['message_id'] ?? 0);

        if($newId > 0){
            telegramUpdateSessionScreen($chatId, [
                'screen_message_id' => $newId
            ]);

            // اگر به هر دلیل دکمه ننشست، دوباره ست کن
            telegramApiRequest('editMessageReplyMarkup', [
                'chat_id' => $chatId,
                'message_id' => $newId,
                'reply_markup' => $keyboard
            ], [], $config);
        }

        return $newId;
    }

    function telegramPaymentsListText($kind, $items){
        $title = telegramPaymentEmoji($kind) . ' ' . telegramPaymentTitle($kind);

        if(count($items) === 0){
            return $title . "\n\nمورد جدیدی برای بررسی نیست.";
        }

        return $title . "\n\n" . count($items) . " مورد در انتظار بررسی\nیکی را انتخاب کنید:";
    }

    function telegramPaymentsListKeyboard($kind, $items){
        $prefix = $kind === 'تمدید' ? 'renew:' : 'buy:';
        $rows = [telegramBackRow('menu:home')];

        foreach($items as $i => $item){
            $label = ($item['username'] ?? '-') . ' | ' . telegramLimitText($item['plan'] ?? '', 24);
            $rows[] = [[
                'text' => telegramLimitText($label, 60),
                'callback_data' => $prefix . $i
            ]];
        }

        if(count($items) > 4){
            $rows[] = telegramBackRow('menu:home');
        }

        return telegramInline($rows);
    }

    function telegramPaymentDetailKeyboard($kind){
        $back = $kind === 'تمدید' ? 'menu:renews' : 'menu:buys';
        return telegramInline([
            telegramBackRow($back)
        ]);
    }

    function telegramShowPayments($chatId, $kind, $config = null, $messageId = null){
        $items = telegramLoadPendingPayments($kind, 15);
        $session = telegramGetSession($chatId);

        if(!$messageId && is_array($session)){
            $messageId = intval($session['screen_message_id'] ?? 0) ?: null;
        }

        $id = telegramShowPage(
            $chatId,
            telegramPaymentsListText($kind, $items),
            telegramPaymentsListKeyboard($kind, $items),
            $config,
            $messageId
        );

        telegramSetSession($chatId, [
            'screen' => $kind === 'تمدید' ? 'renews' : 'buys',
            'screen_message_id' => $id,
            'payment_kind' => $kind,
            'payment_items' => $items,
            'updated_at' => time()
        ]);

        return $id;
    }

    function telegramShowPaymentDetail($chatId, $kind, $index, $config = null, $messageId = null){
        $session = telegramGetSession($chatId);
        $items = [];

        if(is_array($session) && ($session['payment_kind'] ?? '') === $kind && is_array($session['payment_items'] ?? null)){
            $items = $session['payment_items'];
        }
        else{
            $items = telegramLoadPendingPayments($kind, 15);
        }

        if(!$messageId && is_array($session)){
            $messageId = intval($session['screen_message_id'] ?? 0) ?: null;
        }

        if(!isset($items[$index])){
            return telegramShowPayments($chatId, $kind, $config, $messageId);
        }

        $id = telegramShowPage(
            $chatId,
            telegramFormatPaymentDetail($items[$index], $kind),
            telegramPaymentDetailKeyboard($kind),
            $config,
            $messageId
        );

        telegramSetSession($chatId, [
            'screen' => 'payment_detail',
            'screen_message_id' => $id,
            'payment_kind' => $kind,
            'payment_items' => $items,
            'updated_at' => time()
        ]);

        return $id;
    }

    function telegramNotifyNewPayment($kind, $row){
        $username = trim((string)($row[0] ?? ''));
        $item = [
            'username' => $username,
            'mobile' => telegramGetUserMobile($username),
            'target' => trim((string)($row[1] ?? '')),
            'plan' => trim((string)($row[2] ?? '')),
            'tracking' => trim((string)($row[3] ?? '')),
            'date' => trim((string)($row[4] ?? '')),
            'time' => trim((string)($row[5] ?? '')),
            'status' => trim((string)($row[6] ?? 'درحال بررسی')),
            'created' => intval($row[8] ?? time()),
            'type' => $kind,
            'coupon' => trim((string)($row[10] ?? '')),
            'discount' => trim((string)($row[11] ?? ''))
        ];

        $text = "🔔 " . telegramFormatPaymentDetail($item, $kind);
        $backMenu = $kind === 'تمدید' ? 'menu:renews' : 'menu:buys';

        return telegramSendToAdmins($text, [
            'reply_markup' => telegramInline([
                telegramBackRow('menu:home'),
                [['text' => 'مشاهده لیست', 'callback_data' => $backMenu]]
            ])
        ]);
    }

    function telegramShowHome($chatId, $config = null, $messageId = null){
        $session = telegramGetSession($chatId);

        if(!$messageId && is_array($session)){
            $messageId = intval($session['screen_message_id'] ?? 0) ?: null;
        }

        $id = telegramShowPage(
            $chatId,
            telegramHomeText(),
            telegramHomeKeyboard(),
            $config,
            $messageId
        );

        telegramSetSession($chatId, [
            'screen' => 'home',
            'screen_message_id' => $id,
            'updated_at' => time()
        ]);

        return $id;
    }

    function telegramShowMessages($chatId, $config = null, $messageId = null){
        $items = telegramUnreadTickets(12);
        $source = 'unread';

        if(count($items) === 0){
            $items = telegramRecentTickets(10);
            $source = 'recent';
        }

        $session = telegramGetSession($chatId);

        if(!$messageId && is_array($session)){
            $messageId = intval($session['screen_message_id'] ?? 0) ?: null;
        }

        $id = telegramShowPage(
            $chatId,
            telegramMessagesText($items, $source),
            telegramMessagesKeyboard($items),
            $config,
            $messageId
        );

        telegramSetSession($chatId, [
            'screen' => 'messages',
            'screen_message_id' => $id,
            'updated_at' => time()
        ]);

        return $id;
    }

    function telegramShowChat($chatId, $username, $config = null, $messageId = null){
        $history = telegramFormatHistory($username, 30, true);
        $pageText = $history !== '' ? $history : 'پیامی برای نمایش نیست.';
        $session = telegramGetSession($chatId);

        if(!$messageId && is_array($session)){
            $messageId = intval($session['screen_message_id'] ?? 0) ?: null;
        }

        $id = telegramShowPage(
            $chatId,
            $pageText,
            telegramChatKeyboard($username),
            $config,
            $messageId
        );

        telegramSetSession($chatId, [
            'screen' => 'chat',
            'username' => $username,
            'screen_message_id' => $id,
            'updated_at' => time()
        ]);

        return $id;
    }

    function telegramShowReply($chatId, $username, $config = null, $messageId = null){
        $session = telegramGetSession($chatId);

        if(!$messageId && is_array($session)){
            $messageId = intval($session['screen_message_id'] ?? 0) ?: null;
        }

        $id = telegramShowPage(
            $chatId,
            "✍️ پاسخ به {$username}\n\nمتن پاسخ را همین‌جا بنویسید.",
            telegramReplyPageKeyboard($username),
            $config,
            $messageId
        );

        telegramSetSession($chatId, [
            'screen' => 'reply',
            'mode' => 'reply',
            'username' => $username,
            'screen_message_id' => $id,
            'updated_at' => time()
        ]);

        return $id;
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

    function telegramSendSupportNotification($username, $message, $mobile = ''){
        $text = trim((string)($message['text'] ?? ''));
        $image = trim((string)($message['image'] ?? ''));

        $header = "🔔 پیام جدید\n\n";
        $header .= 'کاربر: ' . $username . "\n";

        if($mobile !== '' && $mobile !== '-'){
            $header .= 'موبایل: ' . $mobile . "\n";
        }

        $header .= 'زمان: ' . ($message['date'] ?? '') . ' ' . ($message['time'] ?? '') . "\n\n";
        $header .= $text !== '' ? $text : 'یک تصویر ارسال شده است';

        $extra = [
            'reply_markup' => telegramTicketActionKeyboard($username)
        ];

        $file = __DIR__ . '/' . ltrim($image, '/');

        if($image !== '' && is_file($file)){
            return telegramSendToAdmins($header, $extra, ['photo' => $file]);
        }

        return telegramSendToAdmins($header, $extra);
    }

    function telegramSetCommands($config = null){
        // منوی بصری؛ فقط استارت برای باز کردن صفحه اصلی
        return telegramApiRequest('setMyCommands', [
            'commands' => json_encode([
                ['command' => 'start', 'description' => 'منوی اصلی']
            ], JSON_UNESCAPED_UNICODE)
        ], [], $config);
    }

    function telegramHandleCallback($callback, $config = null){
        $callbackId = $callback['id'] ?? '';
        $chatId = (string)($callback['message']['chat']['id'] ?? '');
        $messageId = intval($callback['message']['message_id'] ?? 0);
        $data = trim((string)($callback['data'] ?? ''));

        if($chatId === '' || !telegramCanUseBot($chatId, $config)){
            telegramAnswerCallback($callbackId, 'دسترسی ندارید', $config);
            return;
        }

        telegramAnswerCallback($callbackId, '', $config);

        if($data === 'menu:home' || $data === 'home'){
            telegramShowHome($chatId, $config, $messageId);
            return;
        }

        if($data === 'menu:messages'){
            telegramShowMessages($chatId, $config, $messageId);
            return;
        }

        if($data === 'menu:buys'){
            telegramShowPayments($chatId, 'خرید', $config, $messageId);
            return;
        }

        if($data === 'menu:renews'){
            telegramShowPayments($chatId, 'تمدید', $config, $messageId);
            return;
        }

        if(strpos($data, 'buy:') === 0){
            $index = intval(substr($data, 4));
            telegramShowPaymentDetail($chatId, 'خرید', $index, $config, $messageId);
            return;
        }

        if(strpos($data, 'renew:') === 0){
            $index = intval(substr($data, 6));
            telegramShowPaymentDetail($chatId, 'تمدید', $index, $config, $messageId);
            return;
        }

        if(strpos($data, 'chat:') === 0 || strpos($data, 'hist:') === 0){
            $username = substr($data, strpos($data, ':') + 1);
            telegramShowChat($chatId, $username, $config, $messageId);
            return;
        }

        if(strpos($data, 'reply:') === 0){
            $username = substr($data, 6);
            telegramShowReply($chatId, $username, $config, $messageId);
            return;
        }

        if($data === 'cancel' || $data === 'back'){
            telegramShowHome($chatId, $config, $messageId);
        }
    }

    function telegramHandleAdminText($chatId, $text, $config = null){
        $text = trim((string)$text);

        if($text === '/start'){
            telegramShowHome($chatId, $config, null);
            return;
        }

        // سازگاری با کیبورد قدیمی
        if($text === '/messages' || $text === 'پیام کاربران'){
            $session = telegramGetSession($chatId);
            $messageId = is_array($session) ? (intval($session['screen_message_id'] ?? 0) ?: null) : null;
            telegramShowMessages($chatId, $config, $messageId);
            return;
        }

        if($text === '/cancel' || $text === 'انصراف'){
            $session = telegramGetSession($chatId);
            $messageId = is_array($session) ? (intval($session['screen_message_id'] ?? 0) ?: null) : null;
            telegramShowMessages($chatId, $config, $messageId);
            return;
        }

        $session = telegramGetSession($chatId);

        if(is_array($session) && (($session['mode'] ?? '') === 'reply' || ($session['screen'] ?? '') === 'reply')){
            $username = trim((string)($session['username'] ?? ''));
            $messageId = intval($session['screen_message_id'] ?? 0) ?: null;
            $result = telegramAddAdminReply($username, $text);

            if(empty($result['ok'])){
                telegramShowPage(
                    $chatId,
                    'ثبت پاسخ ناموفق بود: ' . ($result['error'] ?? 'خطای نامشخص'),
                    telegramReplyPageKeyboard($username),
                    $config,
                    $messageId
                );
                return;
            }

            telegramShowPage(
                $chatId,
                "✅ پاسخ برای {$username} ثبت شد.",
                telegramChatKeyboard($username),
                $config,
                $messageId
            );

            telegramSetSession($chatId, [
                'screen' => 'chat',
                'username' => $username,
                'screen_message_id' => $messageId,
                'updated_at' => time()
            ]);
            return;
        }

        // متن‌های آزاد خارج از حالت پاسخ نادیده گرفته می‌شوند
    }
}
