<?php

if(!function_exists('baleConfigPath')){

    function baleConfigPath(){
        return __DIR__ . '/db/bale.json';
    }

    function baleDefaultConfig(){
        return [
            'enabled' => false,
            'bot_token' => '',
            'admin_chat_ids' => '',
            'webhook_secret' => '',
            'bot_username' => 'Jay24x7Pusbank_bot',
            'forward_hint' => 'پیام واریز @postbank_bot را به این بازو فوروارد کنید',
            'pay_window_seconds' => 600
        ];
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

    function baleExtractMessageText($message){
        if(!is_array($message)){
            return '';
        }

        foreach(['text', 'caption'] as $key){
            if(!empty($message[$key]) && is_string($message[$key])){
                return trim($message[$key]);
            }
        }

        return '';
    }

    /**
     * مبالغ را از متن واریز استخراج می‌کند و همه را به ریال برمی‌گرداند.
     */
    function baleExtractRialAmounts($text){
        $text = trim((string)$text);

        if($text === ''){
            return [];
        }

        $text = strtr($text, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '٬' => ',', '٫' => ',',
        ]);

        $amounts = [];

        if(preg_match_all('/(\d{1,3}(?:,\d{3})+|\d{4,12})\s*(ریال|تومان|تومن)?/u', $text, $matches, PREG_SET_ORDER)){
            foreach($matches as $m){
                $raw = str_replace(',', '', $m[1]);
                $n = intval($raw);

                if($n < 1000){
                    continue;
                }

                $unit = $m[2] ?? '';

                if($unit === 'تومان' || $unit === 'تومن'){
                    $n = $n * 10;
                }
                elseif($unit === ''){
                    // بدون واحد: اگر شبیه تومان کوچک است، به ریال تبدیل نکن مگر واضح باشد
                    // مبالغ کارت‌به‌کارت بانکی معمولاً ریال و >= 100000 هستند
                }

                if($n >= 10000){
                    $amounts[] = $n;
                }
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
        $text = trim((string)$text);

        if($text === ''){
            return false;
        }

        $needles = ['واریز', 'واریزی', 'بستانکار', 'deposit', 'مبلغ', 'حساب شما'];

        foreach($needles as $n){
            if(mb_stripos($text, $n) !== false){
                return true;
            }
        }

        return count(baleExtractRialAmounts($text)) > 0;
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
