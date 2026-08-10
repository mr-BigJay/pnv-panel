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

    function baleExtractMessageText($message){
        if(!is_array($message)){
            return '';
        }

        $parts = [];

        foreach(['text', 'caption'] as $key){
            if(!empty($message[$key]) && is_string($message[$key])){
                $parts[] = trim($message[$key]);
            }
        }

        // بعضی فورواردها متن را داخل پیام اصلی می‌گذارند
        foreach(['forward_from_message', 'reply_to_message'] as $nestedKey){
            if(empty($message[$nestedKey]) || !is_array($message[$nestedKey])){
                continue;
            }

            foreach(['text', 'caption'] as $key){
                if(!empty($message[$nestedKey][$key]) && is_string($message[$nestedKey][$key])){
                    $parts[] = trim($message[$nestedKey][$key]);
                }
            }
        }

        // API بله: forward_from / forward_origin
        foreach(['forward_origin', 'forward_from'] as $forwardKey){
            if(empty($message[$forwardKey]) || !is_array($message[$forwardKey])){
                continue;
            }

            foreach(['text', 'caption', 'title', 'username'] as $key){
                if(!empty($message[$forwardKey][$key]) && is_string($message[$forwardKey][$key])){
                    $parts[] = trim($message[$forwardKey][$key]);
                }
            }
        }

        $parts = array_values(array_unique(array_filter($parts, static function($part){
            return $part !== '';
        })));

        return count($parts) > 0 ? implode("\n", $parts) : '';
    }

    /**
     * نرمال‌سازی متن پیام بانک (ارقام، ي عربی، واحدها).
     */
    function baleNormalizeBankText($text){
        $text = trim((string)$text);

        if($text === ''){
            return '';
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
