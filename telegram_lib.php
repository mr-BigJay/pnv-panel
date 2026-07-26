<?php

if(!function_exists('telegramConfigPath')){
    function telegramConfigPath(){
        return __DIR__ . '/db/telegram.json';
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
            $payload = $params;

            foreach($files as $key => $path){
                if(is_file($path)){
                    $payload[$key] = new CURLFile($path);
                }
            }

            $curl = curl_init($url);
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($curl, CURLOPT_TIMEOUT, 25);

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

    function telegramSendSupportNotification($username, $message, $mobile = ''){
        $text = trim((string)($message['text'] ?? ''));
        $image = trim((string)($message['image'] ?? ''));
        $body = "🔔 پیام جدید کاربر\n\n";
        $body .= "کاربر: " . $username . "\n";

        if($mobile !== '' && $mobile !== '-'){
            $body .= "موبایل: " . $mobile . "\n";
        }

        $body .= "زمان: " . ($message['date'] ?? '') . ' ' . ($message['time'] ?? '') . "\n\n";
        $body .= $text !== '' ? $text : 'یک تصویر ارسال شده است';

        $file = __DIR__ . '/' . ltrim($image, '/');

        if($image !== '' && is_file($file)){
            return telegramSendToAdmins($body, [], ['photo' => $file]);
        }

        return telegramSendToAdmins($body);
    }

    function telegramSetCommands($config = null){
        return telegramApiRequest('setMyCommands', [
            'commands' => json_encode([
                ['command' => 'start', 'description' => 'نمایش منو'],
                ['command' => 'messages', 'description' => 'پیام کاربران']
            ], JSON_UNESCAPED_UNICODE)
        ], [], $config);
    }

    function telegramSupportSummary(){
        $file = __DIR__ . '/db/support.json';

        if(!file_exists($file)){
            return '';
        }

        $tickets = json_decode(file_get_contents($file), true);

        if(!is_array($tickets)){
            return '';
        }

        $items = [];

        foreach($tickets as $ticket){
            $messages = $ticket['messages'] ?? [];
            $last = end($messages);

            if(!is_array($last) || ($last['sender'] ?? '') !== 'user'){
                continue;
            }

            if(!empty($last['seen_by_admin'])){
                continue;
            }

            $items[] = '• ' . ($ticket['user'] ?? '-') . ': ' . telegramLimitText(trim((string)($last['text'] ?? 'تصویر')), 120);
        }

        if(count($items) === 0){
            return '';
        }

        return "📨 پیام کاربران (" . count($items) . ")\n\n" . implode("\n", array_slice($items, 0, 20));
    }
}
