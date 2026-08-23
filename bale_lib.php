<?php

if(!function_exists('baleConfigPath')){

    function baleParserVersion(){
        return 'postbank-plus-v7';
    }

    function baleConfigPath(){
        return __DIR__ . '/db/bale.json';
    }

    function baleDefaultConfig(){
        return [
            'enabled' => false,
            'bot_token' => '',
            'admin_chat_ids' => '',
            'webhook_secret' => '',
            'ingest_secret' => '',
            'bot_username' => 'Jay24x7Pusbank_bot',
            'forward_hint' => 'پیام واریز @postbank_bot را به این بازو فوروارد کنید',
            'pay_window_seconds' => 1800,
            'match_grace_seconds' => 0,
            'auto_listener_enabled' => false
        ];
    }

    function baleEnsureIngestSecret($config = null){
        if($config === null){
            $config = baleLoadConfig();
        }

        $secret = trim((string)($config['ingest_secret'] ?? ''));

        if($secret === ''){
            try{
                $secret = bin2hex(random_bytes(24));
            }catch(Throwable $e){
                $secret = sha1(uniqid('bale', true));
            }

            $config['ingest_secret'] = $secret;
            baleSaveConfig($config);
        }

        return $secret;
    }

    function baleLoadConfig(){
        $defaults = baleDefaultConfig();
        $file = baleConfigPath();

        if(!file_exists($file)){
            return $defaults;
        }

        $data = json_decode(file_get_contents($file), true);

        if(!is_array($data)){
            return $defaults;
        }

        return array_merge($defaults, $data);
    }

    function baleSaveConfig($config){
        if(!is_dir(__DIR__ . '/db')){
            @mkdir(__DIR__ . '/db', 0755, true);
        }

        $config = array_merge(baleDefaultConfig(), is_array($config) ? $config : []);

        file_put_contents(
            baleConfigPath(),
            json_encode($config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            LOCK_EX
        );
    }

    function baleAdminChatIds($config = null){
        if($config === null){
            $config = baleLoadConfig();
        }

        $ids = preg_split('/[\s,]+/', trim((string)($config['admin_chat_ids'] ?? '')));
        $ids = array_map('trim', $ids ?: []);

        return array_values(array_unique(array_filter($ids, static function($id){
            return preg_match('/^-?\d+$/', $id);
        })));
    }

    function baleIsAdminChat($chatId, $config = null){
        $chatId = (string)$chatId;
        $ids = baleAdminChatIds($config);

        if(count($ids) === 0){
            // تا وقتی chat_id ست نشده، اولین پیام‌ها برای bootstrap پذیرفته می‌شوند
            return true;
        }

        return in_array($chatId, $ids, true);
    }

    function baleMessageSenderId($message){
        if(!is_array($message)){
            return '';
        }

        foreach(['from', 'from_user'] as $key){
            if(empty($message[$key]) || !is_array($message[$key])){
                continue;
            }

            $id = trim((string)($message[$key]['id'] ?? ''));

            if($id !== ''){
                return $id;
            }
        }

        return '';
    }

    /**
     * ادمین می‌تواند در چت خصوصی یا گروه فوروارد کند؛ chat.id گروه با user.id فرق دارد.
     */
    function baleIsAdminMessage($message, $config = null){
        if(!is_array($message)){
            return false;
        }

        $chatId = (string)($message['chat']['id'] ?? '');
        $senderId = baleMessageSenderId($message);
        $ids = baleAdminChatIds($config);

        if(count($ids) === 0){
            return true;
        }

        if($chatId !== '' && in_array($chatId, $ids, true)){
            return true;
        }

        if($senderId !== '' && in_array($senderId, $ids, true)){
            return true;
        }

        return false;
    }

    function baleRememberAdminIds($config, $message){
        if(!is_array($message)){
            return $config;
        }

        $ids = baleAdminChatIds($config);
        $add = [];

        $chatId = trim((string)($message['chat']['id'] ?? ''));
        $senderId = baleMessageSenderId($message);

        if($chatId !== ''){
            $add[] = $chatId;
        }

        if($senderId !== '' && $senderId !== $chatId){
            $add[] = $senderId;
        }

        if(count($add) === 0){
            return $config;
        }

        foreach($add as $id){
            if(!in_array($id, $ids, true)){
                $ids[] = $id;
            }
        }

        $config['admin_chat_ids'] = implode(',', $ids);
        baleSaveConfig($config);

        return baleLoadConfig();
    }

    function baleApiRequest($method, $params = [], $config = null){
        if($config === null){
            $config = baleLoadConfig();
        }

        $token = trim((string)($config['bot_token'] ?? ''));

        if($token === ''){
            return ['ok' => false, 'description' => 'توکن بله تنظیم نشده'];
        }

        $url = 'https://tapi.bale.ai/bot' . rawurlencode($token) . '/' . $method;

        if(!function_exists('curl_init')){
            return ['ok' => false, 'description' => 'curl در PHP فعال نیست'];
        }

        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($curl, CURLOPT_TIMEOUT, 25);
        curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($params, JSON_UNESCAPED_UNICODE));

        $body = curl_exec($curl);
        $error = curl_error($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if($body === false){
            return ['ok' => false, 'description' => $error !== '' ? $error : 'خطای شبکه بله', 'http' => $status];
        }

        $data = json_decode($body, true);

        if(!is_array($data)){
            return ['ok' => false, 'description' => 'پاسخ نامعتبر بله', 'http' => $status, 'raw' => $body];
        }

        return $data;
    }

    function baleSendMessage($chatId, $text, $extra = [], $config = null){
        $params = array_merge([
            'chat_id' => $chatId,
            'text' => $text
        ], $extra);

        return baleApiRequest('sendMessage', $params, $config);
    }

    function baleSetWebhook($url, $config = null){
        return baleApiRequest('setWebhook', ['url' => $url], $config);
    }

    function baleDeleteWebhook($config = null){
        return baleApiRequest('setWebhook', ['url' => ''], $config);
    }

    function baleGetMe($config = null){
        return baleApiRequest('getMe', [], $config);
    }

    function baleContains($haystack, $needle){
        $haystack = (string)$haystack;
        $needle = (string)$needle;

        if($needle === '' || $haystack === ''){
            return false;
        }

        if(function_exists('mb_stripos')){
            return mb_stripos($haystack, $needle) !== false;
        }

        return (bool)preg_match('/' . preg_quote($needle, '/') . '/iu', $haystack);
    }

    function baleCollectMessageTextParts($message, $depth = 0){
        if(!is_array($message) || $depth > 5){
            return [];
        }

        $parts = [];

        foreach(['text', 'caption'] as $key){
            if(!empty($message[$key]) && is_string($message[$key])){
                $part = trim($message[$key]);

                if($part !== ''){
                    $parts[] = $part;
                }
            }
        }

        foreach(['reply_to_message', 'forward_from_message', 'external_reply', 'quote'] as $nestedKey){
            if(empty($message[$nestedKey]) || !is_array($message[$nestedKey])){
                continue;
            }

            $parts = array_merge($parts, baleCollectMessageTextParts($message[$nestedKey], $depth + 1));
        }

        // API جدیدتر (شبیه تلگرام): forward_origin.message
        if(!empty($message['forward_origin']) && is_array($message['forward_origin'])){
            $origin = $message['forward_origin'];

            if(!empty($origin['message']) && is_array($origin['message'])){
                $parts = array_merge($parts, baleCollectMessageTextParts($origin['message'], $depth + 1));
            }

            foreach(['text', 'caption'] as $key){
                if(!empty($origin[$key]) && is_string($origin[$key])){
                    $part = trim($origin[$key]);

                    if($part !== ''){
                        $parts[] = $part;
                    }
                }
            }
        }

        return $parts;
    }

    function baleMessageDebugSummary($message){
        if(!is_array($message)){
            return [];
        }

        $text = baleExtractMessageText($message);
        $preview = $text;

        if($preview !== '' && function_exists('mb_substr')){
            $preview = mb_substr($preview, 0, 80);
        }
        elseif($preview !== ''){
            $preview = substr($preview, 0, 80);
        }

        return [
            'message_id' => $message['message_id'] ?? null,
            'chat_id' => $message['chat']['id'] ?? null,
            'chat_type' => $message['chat']['type'] ?? null,
            'from_id' => baleMessageSenderId($message),
            'forward' => baleForwardSourceLabel($message),
            'has_text' => $text !== '',
            'text_len' => strlen($text),
            'preview' => $preview,
            'keys' => array_values(array_intersect(array_keys($message), [
                'text', 'caption', 'forward_from', 'forward_from_chat',
                'forward_from_message_id', 'forward_date', 'forward_origin',
                'reply_to_message', 'forward_from_message', 'external_reply', 'quote',
            ])),
        ];
    }

    function baleExtractMessageText($message){
        if(!is_array($message)){
            return '';
        }

        $parts = baleCollectMessageTextParts($message);
        $parts = array_values(array_unique(array_filter($parts, static function($part){
            return $part !== '';
        })));

        return count($parts) > 0 ? implode("\n", $parts) : '';
    }

    function baleForwardSourceLabel($message){
        if(!is_array($message)){
            return '';
        }

        $chat = $message['forward_from_chat'] ?? null;

        if(is_array($chat)){
            $username = trim((string)($chat['username'] ?? ''));

            if($username !== ''){
                return '@' . ltrim($username, '@');
            }

            $title = trim((string)($chat['title'] ?? ''));

            if($title !== ''){
                return $title;
            }
        }

        if(!empty($message['forward_origin']) && is_array($message['forward_origin'])){
            $origin = $message['forward_origin'];
            $originChat = $origin['chat'] ?? ($origin['sender_chat'] ?? null);

            if(is_array($originChat)){
                $username = trim((string)($originChat['username'] ?? ''));

                if($username !== ''){
                    return '@' . ltrim($username, '@');
                }

                $title = trim((string)($originChat['title'] ?? ''));

                if($title !== ''){
                    return $title;
                }
            }

            $originUser = $origin['sender_user'] ?? null;

            if(is_array($originUser)){
                $username = trim((string)($originUser['username'] ?? ''));

                if($username !== ''){
                    return '@' . ltrim($username, '@');
                }
            }
        }

        $from = $message['forward_from'] ?? null;

        if(is_array($from)){
            $username = trim((string)($from['username'] ?? ''));

            if($username !== ''){
                return '@' . ltrim($username, '@');
            }

            $name = trim((string)($from['first_name'] ?? ''));

            if($name !== ''){
                return $name;
            }
        }

        return '';
    }

    function baleLooksLikePostBankForward($message){
        if(!is_array($message)){
            return false;
        }

        $source = strtolower(baleForwardSourceLabel($message));

        if($source !== '' && (strpos($source, 'postbank') !== false || strpos($source, 'post') !== false)){
            return true;
        }

        $text = baleExtractMessageText($message);

        return $text !== '' && function_exists('baleLooksLikePostBankNotice') && baleLooksLikePostBankNotice($text);
    }

    function baleWebhookLogPath(){
        return __DIR__ . '/db/bale_webhook.log';
    }

    function baleLogWebhookEvent($event, $context = []){
        $line = date('c') . ' ' . $event;

        if(is_array($context) && count($context) > 0){
            $line .= ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        @file_put_contents(baleWebhookLogPath(), $line . "\n", FILE_APPEND | LOCK_EX);
        baleTrimWebhookLog();
    }

    /** سازگار با postbank-ingest.php و listener پایتون */
    function baleWebhookLog($line){
        $row = date('c') . ' ' . trim((string)$line) . "\n";
        @file_put_contents(baleWebhookLogPath(), $row, FILE_APPEND | LOCK_EX);
        baleTrimWebhookLog();
    }

    function baleTrimWebhookLog(){
        $path = baleWebhookLogPath();

        if(!is_file($path) || @filesize($path) <= 500000){
            return;
        }

        $lines = @file($path, FILE_IGNORE_NEW_LINES);

        if(is_array($lines) && count($lines) > 200){
            $keep = array_slice($lines, -200);
            @file_put_contents($path, implode("\n", $keep) . "\n", LOCK_EX);
        }
    }

    function baleReadWebhookLogTail($maxLines = 30){
        $path = baleWebhookLogPath();

        if(!is_file($path)){
            return [];
        }

        $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if(!is_array($lines)){
            return [];
        }

        $maxLines = max(1, intval($maxLines));

        return array_slice($lines, -$maxLines);
    }

    function baleGetWebhookInfo($config = null){
        return baleApiRequest('getWebhookInfo', [], $config);
    }

    /**
     * نرمال‌سازی متن پیام بانک (ارقام، ي عربی، واحدها).
     */
    function baleNormalizeBankText($text){
        $text = trim((string)$text);

        if($text === ''){
            return '';
        }

        // listener گاهی خطوط را با | جدا می‌کند
        if(strpos($text, '|') !== false && strpos($text, "\n") === false){
            $text = preg_replace('/\s*\|\s*/u', "\n", $text);
        }

        $text = strtr($text, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '٬' => ',', '٫' => '.',
            'ي' => 'ی', 'ى' => 'ی',
            'ك' => 'ک',
        ]);

        $text = str_replace(["\xE2\x80\x8C", "\xC2\xA0"], '', $text); // ZWNJ, NBSP
        $text = str_replace(['ريال', '﷼', 'Rial', 'rial', 'IRR'], 'ریال', $text);
        $text = str_replace(['تومان', 'تومن', 'Toman', 'toman'], 'تومان', $text);

        // جداکننده هزارگان با نقطه یا فاصله
        $text = preg_replace('/(?<=\d)[.\s](?=\d{3}(?:\D|$))/u', ',', $text);

        return $text;
    }

    /**
     * پارس یک عدد بانکی (با ویرگول) به int.
     */
    function baleParseAmountToken($raw){
        $raw = str_replace([',', ' '], '', (string)$raw);
        return intval($raw);
    }

    /**
     * مبالغ واریز را از پیام پست‌بانک/بانک استخراج می‌کند (ریال).
     * اولویت: مبلغ با + بعد از «واریز» — مانده حساب نادیده گرفته می‌شود.
     *
     * نمونه واقعی:
     * پست بانک
     * واريز به کارت: 6156
     * +998,190
     * 1405/05/10
     * 9:47
     * مانده: 44,108,899 ريال
     */
    function baleExtractRialAmounts($text){
        $text = baleNormalizeBankText($text);

        if($text === ''){
            return [];
        }

        // خط مانده را حذف کن تا موجودی به‌اشتباه مچ نشود
        $withoutBalance = preg_replace('/^\s*مانده\s*:.*$/umi', '', $text);
        if(!is_string($withoutBalance) || trim($withoutBalance) === ''){
            $withoutBalance = $text;
        }

        $preferred = [];

        // ۱) مبلغ با علامت + (فرمت پست‌بانک)
        if(preg_match_all('/\+\s*(\d{1,3}(?:,\d{3})+|\d{4,12})/u', $withoutBalance, $plusMatches)){
            foreach($plusMatches[1] as $token){
                $n = baleParseAmountToken($token);
                if($n >= 1000){
                    $preferred[] = $n;
                }
            }
        }

        // ۲) مبلغ بلافاصله بعد از «واریز...» (با واحد اختیاری)
        if(preg_match_all('/واریز[^0-9+]{0,40}\+?\s*(\d{1,3}(?:,\d{3})+|\d{4,12})\s*(ریال|تومان)?/u', $withoutBalance, $depMatches, PREG_SET_ORDER)){
            foreach($depMatches as $m){
                $n = baleParseAmountToken($m[1]);
                $unit = $m[2] ?? '';

                if($unit === 'تومان'){
                    $n = $n * 10;
                }

                // کارت مقصد مثل 6156 را رد کن
                if($n >= 10000){
                    $preferred[] = $n;
                }
            }
        }

        if(count($preferred) > 0){
            return array_values(array_unique($preferred));
        }

        // ۳) fallback عمومی (بدون خطوط مانده)
        $amounts = [];

        if(preg_match_all('/(\d{1,3}(?:,\d{3})+|\d{4,12})(?:\.(\d+))?\s*(ریال|تومان)?/u', $withoutBalance, $matches, PREG_SET_ORDER)){
            foreach($matches as $m){
                $n = baleParseAmountToken($m[1]);

                if($n < 10000){
                    continue;
                }

                $unit = $m[3] ?? '';

                if($unit === 'تومان'){
                    $n = $n * 10;
                }

                $amounts[] = $n;
            }
        }

        return array_values(array_unique($amounts));
    }

    function baleExtractTomanAmounts($text){
        $rials = baleExtractRialAmounts($text);
        $tomans = [];

        foreach($rials as $rial){
            $tomans[] = intdiv(intval($rial), 10);
        }

        return array_values(array_unique(array_filter($tomans)));
    }

    function baleLooksLikeDeposit($text){
        $text = baleNormalizeBankText($text);

        if($text === ''){
            return false;
        }

        $needles = ['واریز', 'واریزی', 'بستانکار', 'deposit', 'مبلغ', 'حساب شما', 'پست بانک', 'پست‌بانک'];

        foreach($needles as $n){
            if(baleContains($text, $n)){
                return true;
            }
        }

        // فرمت +مبلغ
        if(preg_match('/\+\s*\d{1,3}(?:,\d{3})+/u', $text)){
            return true;
        }

        return count(baleExtractRialAmounts($text)) > 0;
    }

    /**
     * آیا پیام شبیه اعلامیه پست‌بانک است؟ (مبالغ آن ریال‌اند، نه تومان)
     */
    function baleLooksLikePostBankNotice($text){
        $text = baleNormalizeBankText($text);

        if($text === ''){
            return false;
        }

        if(baleContains($text, 'پست بانک') || baleContains($text, 'پست‌بانک')){
            return true;
        }

        if(baleContains($text, 'واریز به کارت')){
            return true;
        }

        if(preg_match('/\+\s*\d{1,3}(?:,\d{3})+/u', $text) && baleContains($text, 'مانده')){
            return true;
        }

        return false;
    }

    function baleWebhookPublicUrl(){
        $host = $_SERVER['HTTP_HOST'] ?? 'panel.ticketin.ir';
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && (string)$_SERVER['SERVER_PORT'] === '443')
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

        $scheme = $https ? 'https' : 'https';
        return $scheme . '://' . $host . '/bale-webhook.php';
    }
}
