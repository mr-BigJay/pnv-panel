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
            'webhook_secret_pending' => '',
            'webhook_secret_enforced' => false,
            'webhook_secret_required' => false,
            'webhook_registered_at' => 0,
            'webhook_last_validated_at' => 0,
            'postbank_forward_bot_id' => '',
            'bot_username' => 'Jay24x7Pusbank_bot',
            'forward_hint' => 'پیام واریز @postbank_bot را به این بازو فوروارد کنید',
            'pay_window_seconds' => 1800,
            'match_grace_seconds' => 0
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
            return false;
        }

        return in_array($chatId, $ids, true);
    }

    function baleEnsureWebhookSecret(array &$config){
        $secret = trim((string)($config['webhook_secret'] ?? ''));

        if($secret !== ''){
            return $secret;
        }

        try{
            $secret = bin2hex(random_bytes(16));
        }
        catch(Throwable $e){
            $secret = sha1(uniqid('bale_wh_', true));
        }

        $config['webhook_secret'] = $secret;

        return $secret;
    }

    function baleWebhookActiveSecret(array $config){
        $pending = trim((string)($config['webhook_secret_pending'] ?? ''));
        $secret = trim((string)($config['webhook_secret'] ?? ''));

        return $pending !== '' ? $pending : $secret;
    }

    function baleWebhookSecretHeaderValue(){
        foreach([
            'HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN',
            'HTTP_X_BALE_WEBHOOK_SECRET',
        ] as $headerKey){
            if(!empty($_SERVER[$headerKey]) && is_string($_SERVER[$headerKey])){
                return trim($_SERVER[$headerKey]);
            }
        }

        return '';
    }

    function baleVerifyWebhookRequest($config = null){
        if($config === null){
            $config = baleLoadConfig();
        }

        $enforced = !empty($config['webhook_secret_enforced']) || !empty($config['webhook_secret_required']);
        $secret = baleWebhookActiveSecret($config);

        if(!$enforced){
            return [
                'ok' => true,
                'migration' => true,
                'warning' => 'secret هنوز enforce نشده؛ تأیید پرداخت فقط از مسیر baleCanProcessPaymentDeposit',
            ];
        }

        if($secret === ''){
            return [
                'ok' => false,
                'code' => 'webhook_secret_missing',
                'error' => 'webhook_secret برای enforce تنظیم نشده است',
            ];
        }

        $provided = baleWebhookSecretHeaderValue();

        if($provided === '' || !hash_equals($secret, $provided)){
            return [
                'ok' => false,
                'code' => 'webhook_unauthorized',
                'error' => 'منشأ Webhook معتبر نیست',
            ];
        }

        return ['ok' => true, 'enforced' => true];
    }

    function baleMessageForwardSenderId(array $message){
        if(!empty($message['forward_from']) && is_array($message['forward_from'])){
            return trim((string)($message['forward_from']['id'] ?? ''));
        }

        if(!empty($message['forward_origin']) && is_array($message['forward_origin'])){
            $origin = $message['forward_origin'];

            if(!empty($origin['sender_user']) && is_array($origin['sender_user'])){
                return trim((string)($origin['sender_user']['id'] ?? ''));
            }
        }

        return '';
    }

    function baleMessageForwardSenderIsBot(array $message){
        if(!empty($message['forward_from']) && is_array($message['forward_from'])){
            if(array_key_exists('is_bot', $message['forward_from'])){
                return !empty($message['forward_from']['is_bot']);
            }
        }

        if(!empty($message['forward_origin']) && is_array($message['forward_origin'])){
            $user = $message['forward_origin']['sender_user'] ?? null;

            if(is_array($user) && array_key_exists('is_bot', $user)){
                return !empty($user['is_bot']);
            }
        }

        return false;
    }

    function baleCanProcessPaymentDeposit(array $config, array $message, $chatId){
        $chatId = (string)$chatId;

        if(count(baleAdminChatIds($config)) === 0){
            return [
                'ok' => false,
                'code' => 'admin_not_configured',
                'error' => 'admin_chat_ids تنظیم نشده است',
            ];
        }

        if(!baleIsAdminChat($chatId, $config)){
            return [
                'ok' => false,
                'code' => 'unauthorized_chat',
                'error' => 'chat مجاز نیست',
            ];
        }

        if(!baleMessageIsTrustedPostbankDeposit($message, $config)){
            return [
                'ok' => false,
                'code' => 'untrusted_deposit_source',
                'error' => 'فوروارد معتبر postbank نیست',
            ];
        }

        return ['ok' => true];
    }

    function baleAppendAdminChatId($chatId, $config = null){
        $chatId = trim((string)$chatId);

        if($chatId === '' || !preg_match('/^-?\d+$/', $chatId)){
            return false;
        }

        if($config === null){
            $config = baleLoadConfig();
        }

        $ids = baleAdminChatIds($config);

        if(in_array($chatId, $ids, true)){
            return true;
        }

        $ids[] = $chatId;
        $config['admin_chat_ids'] = implode(',', $ids);
        baleSaveConfig($config);

        return true;
    }

    function baleNormalizeBotUsername($username){
        return strtolower(ltrim(trim((string)$username), '@'));
    }

    function baleTrustedPostbankBotUsernames(){
        return ['postbank_bot'];
    }

    function baleMessageForwardUsername(array $message){
        if(!empty($message['forward_origin']) && is_array($message['forward_origin'])){
            $origin = $message['forward_origin'];
            $type = strtolower(trim((string)($origin['type'] ?? '')));

            if($type === 'user' && !empty($origin['sender_user']) && is_array($origin['sender_user'])){
                return (string)($origin['sender_user']['username'] ?? '');
            }

            if($type === 'chat' && !empty($origin['sender_chat']) && is_array($origin['sender_chat'])){
                return (string)($origin['sender_chat']['username'] ?? '');
            }
        }

        if(!empty($message['forward_from']) && is_array($message['forward_from'])){
            return (string)($message['forward_from']['username'] ?? '');
        }

        if(!empty($message['forward_from_chat']) && is_array($message['forward_from_chat'])){
            return (string)($message['forward_from_chat']['username'] ?? '');
        }

        return '';
    }

    function baleMessageIsForward(array $message){
        if(!empty($message['forward_date'])){
            return true;
        }

        foreach(['forward_from', 'forward_origin', 'forward_from_message', 'forward_from_chat'] as $key){
            if(!empty($message[$key])){
                return true;
            }
        }

        return false;
    }

    function baleMessageIsTrustedPostbankDeposit(array $message, $config = null){
        if($config === null){
            $config = baleLoadConfig();
        }

        if(empty($message['forward_date'])){
            return false;
        }

        if(!baleMessageIsForward($message)){
            return false;
        }

        $forwardUser = baleNormalizeBotUsername(baleMessageForwardUsername($message));

        if($forwardUser === '' || !in_array($forwardUser, baleTrustedPostbankBotUsernames(), true)){
            return false;
        }

        $pinnedId = trim((string)($config['postbank_forward_bot_id'] ?? ''));

        if($pinnedId !== ''){
            $senderId = baleMessageForwardSenderId($message);

            return $senderId !== '' && $senderId === $pinnedId;
        }

        return baleMessageForwardSenderIsBot($message);
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
        if($config === null){
            $config = baleLoadConfig();
        }

        $params = ['url' => $url];
        $secret = baleWebhookActiveSecret($config);

        if($secret !== ''){
            $params['secret_token'] = $secret;
        }

        return baleApiRequest('setWebhook', $params, $config);
    }

    function baleGetWebhookInfo($config = null){
        return baleApiRequest('getWebhookInfo', [], $config);
    }

    function balePrepareWebhookSecret(array &$config){
        try{
            $secret = bin2hex(random_bytes(16));
        }
        catch(Throwable $e){
            $secret = sha1(uniqid('bale_wh_', true));
        }

        $config['webhook_secret_pending'] = $secret;
        $config['webhook_secret_enforced'] = false;
        $config['webhook_secret_required'] = false;

        return $secret;
    }

    function baleActivateWebhookSecretEnforcement(array &$config){
        $pending = trim((string)($config['webhook_secret_pending'] ?? ''));

        if($pending !== ''){
            $config['webhook_secret'] = $pending;
            $config['webhook_secret_pending'] = '';
        }

        if(trim((string)($config['webhook_secret'] ?? '')) === ''){
            return false;
        }

        $config['webhook_secret_enforced'] = true;
        $config['webhook_secret_required'] = true;
        $config['webhook_last_validated_at'] = time();

        return true;
    }

    function baleRollbackWebhookSecretEnforcement(array &$config){
        $config['webhook_secret_enforced'] = false;
        $config['webhook_secret_required'] = false;

        return true;
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
    /**
     * اثر انگشت پایدار واریز پست‌بانک (مستقل از message_id فوروارد).
     *
     * @return array{ok:bool,key?:string,code?:string,amount?:int,card?:string,date?:string,time?:string}
     */
    function baleExtractPostbankBankReference($text){
        $text = baleNormalizeBankText($text);

        if($text === ''){
            return '';
        }

        $patterns = [
            '/(?:شماره\s*پیگیری|کد\s*پیگیری|کد\s*مرجع|شماره\s*مرجع|reference)\s*[:：]?\s*(\d{6,20})/iu',
            '/(?:rrn|ref)\s*[:：]?\s*(\d{6,20})/iu',
        ];

        foreach($patterns as $pattern){
            if(preg_match($pattern, $text, $m)){
                $ref = preg_replace('/\D+/', '', (string)($m[1] ?? ''));

                if($ref !== ''){
                    return $ref;
                }
            }
        }

        return '';
    }

    function baleExtractPostbankBalanceRial($text){
        $text = baleNormalizeBankText($text);

        if($text === '' || !preg_match('/^\s*مانده\s*:\s*(\d{1,3}(?:,\d{3})+|\d{4,12})/imu', $text, $m)){
            return 0;
        }

        return baleParseAmountToken($m[1] ?? '');
    }

    function baleBuildPostbankDepositUsageKey($text, $amountRial = 0){
        $text = baleNormalizeBankText($text);
        $amountRial = intval($amountRial);

        if($amountRial <= 0){
            $amounts = baleExtractRialAmounts($text);
            $amountRial = intval($amounts[0] ?? 0);
        }

        if(!baleLooksLikePostBankNotice($text) || $amountRial < 1000){
            return ['ok' => false, 'code' => 'not_postbank'];
        }

        $bankRef = baleExtractPostbankBankReference($text);

        if($bankRef !== ''){
            return [
                'ok' => true,
                'key' => 'bale_bank_ref|post|' . $bankRef,
                'bank_reference' => $bankRef,
                'amount' => $amountRial,
            ];
        }

        $card = baleExtractDestinationCardDigits($text);
        $date = '';
        $time = '';
        $timeHasSeconds = false;

        if(preg_match('/(\d{4}\/\d{1,2}\/\d{1,2})/u', $text, $dateMatch)){
            $date = trim((string)($dateMatch[1] ?? ''));
        }

        if(preg_match('/^\s*(\d{1,2}:\d{2}:\d{2})\s*$/mu', $text, $timeMatchSec)){
            $time = trim((string)($timeMatchSec[1] ?? ''));
            $timeHasSeconds = true;
        }
        elseif(preg_match('/^\s*(\d{1,2}:\d{2})\s*$/mu', $text, $timeMatch)){
            $time = trim((string)($timeMatch[1] ?? ''));
        }

        $balance = baleExtractPostbankBalanceRial($text);

        if($card === '' || $date === '' || $time === ''){
            return ['ok' => false, 'code' => 'ambiguous_bank_fingerprint'];
        }

        if(!$timeHasSeconds && $balance <= 0){
            return ['ok' => false, 'code' => 'ambiguous_bank_fingerprint'];
        }

        $key = 'bale_bank|post|'
            . $card
            . '|'
            . $amountRial
            . '|'
            . $date
            . '|'
            . $time;

        if($balance > 0){
            $key .= '|bal:' . $balance;
        }

        return [
            'ok' => true,
            'key' => $key,
            'amount' => $amountRial,
            'card' => $card,
            'date' => $date,
            'time' => $time,
            'balance' => $balance,
        ];
    }

    function baleExtractDestinationCardDigits($text){
        $text = baleNormalizeBankText($text);

        if($text === ''){
            return '';
        }

        if(preg_match('/واری[زز]\s*به\s*کارت\s*:\s*(\d{4,16})/u', $text, $m)){
            return preg_replace('/\D+/', '', (string)($m[1] ?? ''));
        }

        return '';
    }

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
