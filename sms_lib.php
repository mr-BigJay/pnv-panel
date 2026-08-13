<?php

if(!function_exists('smsConfigPath')){

    function smsConfigPath(){
        return __DIR__ . '/db/sms.json';
    }

    function smsDefaultTemplates(){
        return [
            'verify_mobile' => [
                'enabled' => true,
                'template_id' => '',
                'text' => "کد تایید شما:\n#CODE#\n\nلطفاً برای ورود از آن استفاده کنید.\npanel.ticketin.ir",
            ],
            'buy_confirm' => [
                'enabled' => false,
                'template_id' => '',
                'text' => "#USERNAME# عزیز\nخرید اشتراک #PLAN# با موفقیت ثبت شد.\npanel.ticketin.ir",
            ],
            'renew_confirm' => [
                'enabled' => false,
                'template_id' => '',
                'text' => "#USERNAME# عزیز\nتمدید اشتراک #PLAN# با موفقیت ثبت شد.\npanel.ticketin.ir",
            ],
            'admin_reply' => [
                'enabled' => false,
                'template_id' => '',
                'text' => "پاسخ پشتیبانی تیکتین:\n#MESSAGE#\n\npanel.ticketin.ir",
            ],
        ];
    }

    function smsTemplateMenu(){
        return [
            'connection' => 'اتصال پنل',
            'verify_mobile' => 'تایید شماره تماس',
            'buy_confirm' => 'تایید خرید',
            'renew_confirm' => 'تایید تمدید',
            'admin_reply' => 'پاسخ ادمین',
        ];
    }

    function smsTemplateMeta(){
        return [
            'verify_mobile' => [
                'title' => 'تایید شماره تماس',
                'desc' => 'ارسال کد OTP برای ورود یا تایید موبایل',
                'placeholders' => ['#CODE#'],
            ],
            'buy_confirm' => [
                'title' => 'تایید خرید',
                'desc' => 'پیامک بعد از تایید پرداخت خرید اشتراک',
                'placeholders' => ['#USERNAME#', '#PLAN#', '#TRACKING#', '#AMOUNT#'],
            ],
            'renew_confirm' => [
                'title' => 'تایید تمدید',
                'desc' => 'پیامک بعد از تایید پرداخت تمدید اشتراک',
                'placeholders' => ['#USERNAME#', '#PLAN#', '#TRACKING#', '#AMOUNT#'],
            ],
            'admin_reply' => [
                'title' => 'پاسخ ادمین',
                'desc' => 'پیامک اطلاع‌رسانی پاسخ پشتیبانی به کاربر',
                'placeholders' => ['#USERNAME#', '#MESSAGE#'],
            ],
        ];
    }

    function smsMergeTemplates($templates){
        $defaults = smsDefaultTemplates();
        $merged = $defaults;

        if(!is_array($templates)){
            return $merged;
        }

        foreach($defaults as $key => $defaultRow){
            $row = is_array($templates[$key] ?? null) ? $templates[$key] : [];
            $merged[$key] = array_merge($defaultRow, $row);
        }

        return $merged;
    }

    function smsDefaultConfig(){
        return [
            'enabled' => false,
            'provider' => 'smsir',
            'api_key' => '',
            'username' => '',
            'password' => '',
            'sender' => '',
            'register_welcome' => false,
            'register_welcome_template' => 'ثبت‌نام شما در تیکتین با موفقیت انجام شد.',
            'test_mobile' => '',
            'templates' => smsDefaultTemplates(),
        ];
    }

    function smsProviderOptions(){
        return [
            'smsir' => 'SMS.ir — ایده‌پردازان',
            'kavenegar' => 'کاوه‌نگار (Kavenegar)',
            'melipayamak' => 'ملی‌پیامک (Melipayamak)',
            'ippanel' => 'آی‌پی‌پنل / فراز SMS (IPPanel)',
        ];
    }

    function smsLoadConfig(){
        $defaults = smsDefaultConfig();
        $file = smsConfigPath();

        if(!file_exists($file)){
            return $defaults;
        }

        $data = json_decode(file_get_contents($file), true);
        if(!is_array($data)){
            return $defaults;
        }

        $config = array_merge($defaults, $data);
        $config['templates'] = smsMergeTemplates($data['templates'] ?? null);

        return $config;
    }

    function smsSaveConfig($config){
        if(!is_dir(__DIR__ . '/db')){
            @mkdir(__DIR__ . '/db', 0755, true);
        }

        $config = array_merge(smsDefaultConfig(), is_array($config) ? $config : []);
        $config['templates'] = smsMergeTemplates($config['templates'] ?? null);
        $providers = smsProviderOptions();
        if(!isset($providers[$config['provider']])){
            $config['provider'] = 'smsir';
        }

        file_put_contents(
            smsConfigPath(),
            json_encode($config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            LOCK_EX
        );
    }

    function smsNormalizeMobile($mobile){
        $mobile = preg_replace('/\D+/', '', trim((string)$mobile));

        if(str_starts_with($mobile, '98') && strlen($mobile) === 12){
            $mobile = '0' . substr($mobile, 2);
        }

        if(str_starts_with($mobile, '9') && strlen($mobile) === 10){
            $mobile = '0' . $mobile;
        }

        if(!preg_match('/^09[0-9]{9}$/', $mobile)){
            return null;
        }

        return $mobile;
    }

    function smsIsConfigured($config = null){
        if($config === null){
            $config = smsLoadConfig();
        }

        $provider = (string)($config['provider'] ?? '');

        if($provider === 'smsir'){
            return trim((string)($config['api_key'] ?? '')) !== ''
                && smsParseLineNumber($config['sender'] ?? '') !== null;
        }

        if($provider === 'kavenegar'){
            return trim((string)($config['api_key'] ?? '')) !== '' && trim((string)($config['sender'] ?? '')) !== '';
        }

        if($provider === 'melipayamak'){
            return trim((string)($config['username'] ?? '')) !== ''
                && trim((string)($config['password'] ?? '')) !== ''
                && trim((string)($config['sender'] ?? '')) !== '';
        }

        if($provider === 'ippanel'){
            return trim((string)($config['api_key'] ?? '')) !== '' && trim((string)($config['sender'] ?? '')) !== '';
        }

        return false;
    }

    function smsParseLineNumber($value){
        $digits = preg_replace('/\D+/', '', trim((string)$value));

        if($digits === ''){
            return null;
        }

        if(strlen($digits) > 19){
            $digits = substr($digits, 0, 19);
        }

        return $digits;
    }

    function smsMobileForProvider($mobile, $provider){
        $mobile = smsNormalizeMobile($mobile);

        if($mobile === null){
            return null;
        }

        if($provider === 'smsir'){
            if(str_starts_with($mobile, '0')){
                return $mobile;
            }

            return '0' . $mobile;
        }

        return $mobile;
    }

    function smsExtractSmsIrError($json, $result){
        if(is_array($json)){
            $message = trim((string)($json['message'] ?? ''));
            if($message !== '' && $message !== 'موفق'){
                return $message;
            }
        }

        if(!empty($result['error'])){
            return (string)$result['error'];
        }

        $body = trim((string)($result['body'] ?? ''));
        if($body !== ''){
            return $body;
        }

        return 'ارسال ناموفق';
    }

    function smsSendViaSmsIr($mobile, $message, $config){
        $apiKey = trim((string)($config['api_key'] ?? ''));
        $lineNumber = smsParseLineNumber($config['sender'] ?? '');

        if($lineNumber === null){
            return ['ok' => false, 'provider' => 'smsir', 'error' => 'شماره خط ارسال SMS.ir نامعتبر است.'];
        }

        $payload = json_encode([
            'lineNumber' => (int)$lineNumber,
            'messageText' => $message,
            'mobiles' => [$mobile],
            'sendDateTime' => null,
        ], JSON_UNESCAPED_UNICODE);

        $result = smsHttpRequest('https://api.sms.ir/v1/send/bulk', [
            'method' => 'POST',
            'headers' => [
                'Content-Type: application/json',
                'Accept: application/json',
                'X-API-KEY: ' . $apiKey,
            ],
            'body' => $payload,
        ]);

        $json = json_decode((string)($result['body'] ?? ''), true);

        if(is_array($json) && (int)($json['status'] ?? 0) === 1){
            return [
                'ok' => true,
                'provider' => 'smsir',
                'pack_id' => $json['data']['packId'] ?? null,
            ];
        }

        return [
            'ok' => false,
            'provider' => 'smsir',
            'error' => smsExtractSmsIrError($json, $result),
        ];
    }

    function smsHttpRequest($url, $options = []){
        $method = strtoupper((string)($options['method'] ?? 'GET'));
        $headers = is_array($options['headers'] ?? null) ? $options['headers'] : [];
        $body = $options['body'] ?? null;
        $timeout = max(5, (int)($options['timeout'] ?? 20));

        if(function_exists('curl_init')){
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTPHEADER => $headers,
            ]);

            if($method === 'POST'){
                curl_setopt($ch, CURLOPT_POST, true);
                if($body !== null){
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
                }
            }

            $response = curl_exec($ch);
            $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            return [
                'ok' => ($response !== false && $status >= 200 && $status < 300),
                'status' => $status,
                'body' => is_string($response) ? $response : '',
                'error' => $error,
            ];
        }

        $headerLines = implode("\r\n", $headers);
        $contextOptions = [
            'http' => [
                'method' => $method,
                'timeout' => $timeout,
                'header' => $headerLines,
                'ignore_errors' => true,
            ],
        ];

        if($body !== null){
            $contextOptions['http']['content'] = $body;
        }

        $response = @file_get_contents($url, false, stream_context_create($contextOptions));

        return [
            'ok' => is_string($response) && $response !== '',
            'status' => 0,
            'body' => is_string($response) ? $response : '',
            'error' => is_string($response) ? '' : 'HTTP request failed',
        ];
    }

    function smsSendViaKavenegar($mobile, $message, $config){
        $apiKey = trim((string)($config['api_key'] ?? ''));
        $sender = trim((string)($config['sender'] ?? ''));

        $url = 'https://api.kavenegar.com/v1/' . rawurlencode($apiKey) . '/sms/send.json';
        $query = http_build_query([
            'receptor' => $mobile,
            'message' => $message,
            'sender' => $sender,
        ]);

        $result = smsHttpRequest($url . '?' . $query);
        $json = json_decode((string)($result['body'] ?? ''), true);

        if(!empty($json['return']['status']) && (int)$json['return']['status'] === 200){
            return ['ok' => true, 'provider' => 'kavenegar'];
        }

        return [
            'ok' => false,
            'provider' => 'kavenegar',
            'error' => $json['return']['message'] ?? ($result['error'] ?: 'ارسال ناموفق'),
        ];
    }

    function smsSendViaMelipayamak($mobile, $message, $config){
        $payload = json_encode([
            'username' => trim((string)($config['username'] ?? '')),
            'password' => trim((string)($config['password'] ?? '')),
            'to' => $mobile,
            'from' => trim((string)($config['sender'] ?? '')),
            'text' => $message,
            'isFlash' => false,
        ], JSON_UNESCAPED_UNICODE);

        $result = smsHttpRequest('https://rest.payamak-panel.com/api/SendSMS/SendSMS', [
            'method' => 'POST',
            'headers' => ['Content-Type: application/json', 'Accept: application/json'],
            'body' => $payload,
        ]);

        $json = json_decode((string)($result['body'] ?? ''), true);
        $value = $json['Value'] ?? $json['RetStatus'] ?? null;

        if(is_numeric($value) && (int)$value > 0){
            return ['ok' => true, 'provider' => 'melipayamak', 'message_id' => (string)$value];
        }

        $error = '';
        if(is_array($json)){
            $error = (string)($json['StrStatus'] ?? $json['Message'] ?? '');
        }
        if($error === ''){
            $error = $result['error'] ?: trim((string)($result['body'] ?? ''));
        }
        if($error === ''){
            $error = 'ارسال ناموفق';
        }

        return ['ok' => false, 'provider' => 'melipayamak', 'error' => $error];
    }

    function smsSendViaIppanel($mobile, $message, $config){
        $apiKey = trim((string)($config['api_key'] ?? ''));
        $sender = trim((string)($config['sender'] ?? ''));

        $payload = json_encode([
            'originator' => $sender,
            'recipient' => $mobile,
            'message' => $message,
        ], JSON_UNESCAPED_UNICODE);

        $result = smsHttpRequest('https://api2.ippanel.com/api/v1/sms/send/webservice/single', [
            'method' => 'POST',
            'headers' => [
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: ' . $apiKey,
            ],
            'body' => $payload,
        ]);

        $json = json_decode((string)($result['body'] ?? ''), true);

        if(!empty($json['status']) && strtolower((string)$json['status']) === 'ok'){
            return ['ok' => true, 'provider' => 'ippanel'];
        }

        $error = '';
        if(is_array($json)){
            $error = (string)($json['message'] ?? $json['error'] ?? '');
        }
        if($error === ''){
            $error = $result['error'] ?: trim((string)($result['body'] ?? ''));
        }
        if($error === ''){
            $error = 'ارسال ناموفق';
        }

        return ['ok' => false, 'provider' => 'ippanel', 'error' => $error];
    }

    function smsSend($mobile, $message, $config = null){
        if($config === null){
            $config = smsLoadConfig();
        }

        if(empty($config['enabled'])){
            return ['ok' => false, 'error' => 'سرویس پیامک غیرفعال است.'];
        }

        if(!smsIsConfigured($config)){
            return ['ok' => false, 'error' => 'تنظیمات پیامک کامل نیست.'];
        }

        $provider = (string)($config['provider'] ?? 'smsir');

        $mobile = smsMobileForProvider($mobile, $provider);
        if($mobile === null){
            return ['ok' => false, 'error' => 'شماره موبایل نامعتبر است.'];
        }

        $message = trim((string)$message);
        if($message === ''){
            return ['ok' => false, 'error' => 'متن پیامک خالی است.'];
        }

        if($provider === 'smsir'){
            return smsSendViaSmsIr($mobile, $message, $config);
        }

        if($provider === 'melipayamak'){
            return smsSendViaMelipayamak($mobile, $message, $config);
        }

        if($provider === 'ippanel'){
            return smsSendViaIppanel($mobile, $message, $config);
        }

        return smsSendViaKavenegar($mobile, $message, $config);
    }

    function smsSendRegisterWelcome($mobile, $username = '', $config = null){
        if($config === null){
            $config = smsLoadConfig();
        }

        if(empty($config['register_welcome'])){
            return ['ok' => false, 'skipped' => true];
        }

        $template = trim((string)($config['register_welcome_template'] ?? ''));
        if($template === ''){
            $template = smsDefaultConfig()['register_welcome_template'];
        }

        $message = strtr($template, [
            '{username}' => (string)$username,
            '{mobile}' => (string)$mobile,
        ]);

        return smsSend($mobile, $message, $config);
    }

    function smsNormalizeTemplateKey($key){
        $key = trim((string)$key);
        $allowed = smsDefaultTemplates();

        return isset($allowed[$key]) ? $key : null;
    }

    function smsRenderTemplateText($text, $vars = []){
        $text = (string)$text;

        if(!is_array($vars)){
            return $text;
        }

        foreach($vars as $name => $value){
            $name = trim((string)$name);
            if($name === ''){
                continue;
            }

            $upper = strtoupper($name);
            $text = str_replace('#' . $upper . '#', (string)$value, $text);
            $text = str_replace('#' . $name . '#', (string)$value, $text);
            $text = str_replace('{' . $name . '}', (string)$value, $text);
            $text = str_replace('{' . $upper . '}', (string)$value, $text);
        }

        return $text;
    }

    function smsGetTemplate($key, $config = null){
        if($config === null){
            $config = smsLoadConfig();
        }

        $key = smsNormalizeTemplateKey($key);
        if($key === null){
            return null;
        }

        $templates = smsMergeTemplates($config['templates'] ?? null);

        return $templates[$key] ?? null;
    }

    function smsBuildTemplateMessage($key, $vars = [], $config = null){
        $template = smsGetTemplate($key, $config);
        if($template === null){
            return null;
        }

        $text = trim((string)($template['text'] ?? ''));
        if($text === ''){
            return null;
        }

        return smsRenderTemplateText($text, $vars);
    }

    function smsSendTemplate($mobile, $key, $vars = [], $config = null){
        if($config === null){
            $config = smsLoadConfig();
        }

        $key = smsNormalizeTemplateKey($key);
        if($key === null){
            return ['ok' => false, 'error' => 'الگوی پیامک نامعتبر است.'];
        }

        $template = smsGetTemplate($key, $config);
        if(empty($template['enabled'])){
            return ['ok' => false, 'skipped' => true, 'error' => 'الگوی پیامک غیرفعال است.'];
        }

        $message = smsBuildTemplateMessage($key, $vars, $config);
        if($message === null || $message === ''){
            return ['ok' => false, 'error' => 'متن الگو خالی است.'];
        }

        return smsSend($mobile, $message, $config);
    }

    function smsSendVerifyCode($mobile, $code, $config = null){
        return smsSendTemplate($mobile, 'verify_mobile', [
            'CODE' => (string)$code,
        ], $config);
    }

    function smsParseTemplatesFromPost($post){
        $templates = smsDefaultTemplates();

        foreach($templates as $key => $defaultRow){
            $prefix = 'tpl_' . $key . '_';
            $templates[$key] = [
                'enabled' => !empty($post[$prefix . 'enabled']),
                'template_id' => trim((string)($post[$prefix . 'template_id'] ?? $defaultRow['template_id'])),
                'text' => trim((string)($post[$prefix . 'text'] ?? $defaultRow['text'])),
            ];
        }

        return $templates;
    }

    function smsSampleTemplateVars($key){
        $samples = [
            'verify_mobile' => ['CODE' => '123456'],
            'buy_confirm' => [
                'USERNAME' => 'demo_user',
                'PLAN' => '30 GB / 30 روز',
                'TRACKING' => 'PNV-12345',
                'AMOUNT' => '350000',
            ],
            'renew_confirm' => [
                'USERNAME' => 'demo_user',
                'PLAN' => '30 GB / 30 روز',
                'TRACKING' => 'PNV-67890',
                'AMOUNT' => '350000',
            ],
            'admin_reply' => [
                'USERNAME' => 'demo_user',
                'MESSAGE' => 'درخواست شما بررسی شد.',
            ],
        ];

        return $samples[$key] ?? [];
    }

    function smsResolveTestMobile($post, $config = null){
        if($config === null){
            $config = smsLoadConfig();
        }

        $mobile = trim((string)(is_array($post) ? ($post['test_mobile'] ?? '') : ''));
        if($mobile === ''){
            $mobile = trim((string)($config['test_mobile'] ?? ''));
        }

        return $mobile;
    }

    function smsRememberTestMobile($mobile, $config = null){
        $mobile = trim((string)$mobile);
        if($mobile === ''){
            return;
        }

        if($config === null){
            $config = smsLoadConfig();
        }

        if(trim((string)($config['test_mobile'] ?? '')) === $mobile){
            return;
        }

        $config['test_mobile'] = $mobile;
        smsSaveConfig($config);
    }

}
