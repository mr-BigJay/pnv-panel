<?php

if(!function_exists('xuiConfigPath')){

    function xuiConfigPath(){
        return __DIR__ . '/db/xui_servers.json';
    }

    function xuiStatePath(){
        return __DIR__ . '/db/xui_state.json';
    }

    function xuiDefaultConfig(){
        return [
            'enabled' => false,
            'sub_port' => 2096,
            'buy_server_ids' => ['vip', 'vip3', 'vip4'],
            'renew_server_ids' => ['vip', 'vip2', 'vip3', 'vip4'],
            'servers' => [
                [
                    'id' => 'vip',
                    'name' => 'VIP 1',
                    'base_url' => 'https://vip.boozhaan.ir:2415/5MM166IBMn8D4nQtU9/',
                    'api_token' => '',
                    'inbound_id' => 1,
                    'host' => 'vip.boozhaan.ir',
                    'username' => '',
                    'password' => ''
                ],
                [
                    'id' => 'vip2',
                    'name' => 'VIP 2',
                    'base_url' => 'https://vip2.boozhaan.ir:2415/kYaeX9oDRpCzgGiNYF/',
                    'api_token' => '',
                    'inbound_id' => 1,
                    'host' => 'vip2.boozhaan.ir',
                    'username' => '',
                    'password' => ''
                ],
                [
                    'id' => 'vip3',
                    'name' => 'VIP 3',
                    'base_url' => 'https://vip3.boozhaan.ir:2415/UTbC7cdgPRAZKoddKz/',
                    'api_token' => '',
                    'inbound_id' => 1,
                    'host' => 'vip3.boozhaan.ir',
                    'username' => '',
                    'password' => ''
                ],
                [
                    'id' => 'vip4',
                    'name' => 'VIP 4',
                    'base_url' => 'https://vip4.boozhaan.ir:2415/ddc8JteTWcm4r5FsQ8/',
                    'api_token' => '',
                    'inbound_id' => 1,
                    'host' => 'vip4.boozhaan.ir',
                    'username' => '',
                    'password' => ''
                ]
            ]
        ];
    }

    function xuiLoadConfig(){
        $defaults = xuiDefaultConfig();
        $path = xuiConfigPath();

        if(!file_exists($path)){
            return $defaults;
        }

        $loaded = json_decode(file_get_contents($path), true);

        if(!is_array($loaded)){
            return $defaults;
        }

        $config = array_merge($defaults, $loaded);

        if(!isset($config['servers']) || !is_array($config['servers'])){
            $config['servers'] = $defaults['servers'];
        }

        return $config;
    }

    function xuiSaveConfig($config){
        file_put_contents(
            xuiConfigPath(),
            json_encode($config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            LOCK_EX
        );
    }

    function xuiLoadState(){
        $path = xuiStatePath();

        if(!file_exists($path)){
            return ['buy_rotate_index' => 0];
        }

        $state = json_decode(file_get_contents($path), true);
        return is_array($state) ? $state : ['buy_rotate_index' => 0];
    }

    function xuiSaveState($state){
        file_put_contents(
            xuiStatePath(),
            json_encode($state, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            LOCK_EX
        );
    }

    function xuiFindServerById($id, $config = null){
        if($config === null){
            $config = xuiLoadConfig();
        }

        foreach(($config['servers'] ?? []) as $server){
            if(($server['id'] ?? '') === $id){
                return $server;
            }
        }

        return null;
    }

    function xuiFindServerByHost($host, $config = null){
        if($config === null){
            $config = xuiLoadConfig();
        }

        $host = strtolower(trim((string)$host));

        foreach(($config['servers'] ?? []) as $server){
            if(strtolower($server['host'] ?? '') === $host){
                return $server;
            }
        }

        return null;
    }

    function xuiPickBuyServer($config = null){
        if($config === null){
            $config = xuiLoadConfig();
        }

        $ids = $config['buy_server_ids'] ?? ['vip', 'vip3', 'vip4'];
        $ids = array_values(array_filter($ids));

        if(count($ids) === 0){
            return null;
        }

        $state = xuiLoadState();
        $index = intval($state['buy_rotate_index'] ?? 0);

        if($index < 0 || $index >= count($ids)){
            $index = 0;
        }

        $serverId = $ids[$index];
        $state['buy_rotate_index'] = ($index + 1) % count($ids);
        xuiSaveState($state);

        return xuiFindServerById($serverId, $config);
    }

    function xuiParsePlanGb($planText){
        if(preg_match('/(\d+)\s*گیگ/u', (string)$planText, $m)){
            return intval($m[1]);
        }

        if(preg_match('/(\d+)\s*GB/i', (string)$planText, $m)){
            return intval($m[1]);
        }

        return 0;
    }

    function xuiLoadPlansCatalog(){
        $file = __DIR__ . '/db/plans.json';

        if(!file_exists($file)){
            return [];
        }

        $plans = json_decode((string)file_get_contents($file), true);
        return is_array($plans) ? $plans : [];
    }

    /**
     * تعداد روز پلن از متن فاکتور / کاتالوگ.
     * 0 = نامحدود زمانی
     */
    function xuiParsePlanDays($planText){
        $planText = trim((string)$planText);

        if($planText === ''){
            return 0;
        }

        $catalog = xuiLoadPlansCatalog();
        $strLen = function_exists('mb_strlen') ? 'mb_strlen' : 'strlen';
        $strIpos = function_exists('mb_stripos') ? 'mb_stripos' : 'stripos';
        usort($catalog, static function($a, $b) use ($strLen){
            return $strLen((string)($b['name'] ?? '')) <=> $strLen((string)($a['name'] ?? ''));
        });

        foreach($catalog as $plan){
            if(!is_array($plan)){
                continue;
            }

            $name = trim((string)($plan['name'] ?? ''));

            if($name === '' || $strIpos($planText, $name) === false){
                continue;
            }

            $days = trim((string)($plan['days'] ?? ''));

            if($days === '' || $days === 'نامحدود' || strcasecmp($days, 'unlimited') === 0){
                return 0;
            }

            if(preg_match('/^\d+$/', $days)){
                return max(0, intval($days));
            }
        }

        if(preg_match('/(\d+)\s*ماه/u', $planText, $m)){
            return max(0, intval($m[1]) * 30);
        }

        if(preg_match('/(\d+)\s*روز/u', $planText, $m)){
            return max(0, intval($m[1]));
        }

        return 0;
    }

    /**
     * expire از هدر subscription-userinfo
     * null = نامشخص | 0 = نامحدود | >0 = محدود (unix)
     */
    function xuiFetchSubUserinfoExpire($link){
        $link = trim((string)$link);

        if($link === '' || !preg_match('#^https?://#i', $link) || !function_exists('curl_init')){
            return null;
        }

        $modes = [
            ['nobody' => true],
            ['nobody' => false, 'range' => '0-0'],
            ['nobody' => false],
        ];

        foreach($modes as $mode){
            $curl = curl_init($link);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_HEADER, true);
            curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 5);
            curl_setopt($curl, CURLOPT_TIMEOUT, 12);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);

            if(!empty($mode['nobody'])){
                curl_setopt($curl, CURLOPT_NOBODY, true);
            }

            if(!empty($mode['range'])){
                curl_setopt($curl, CURLOPT_RANGE, $mode['range']);
            }

            $raw = curl_exec($curl);
            curl_close($curl);

            if($raw !== false && $raw !== '' && preg_match('/^subscription-userinfo:\s*(.+)$/im', $raw, $m)){
                $parts = preg_split('/\s*;\s*/', trim($m[1])) ?: [];

                foreach($parts as $part){
                    if(stripos($part, 'expire=') !== 0){
                        continue;
                    }

                    return max(0, intval(trim(substr($part, 7))));
                }

                return 0;
            }
        }

        return null;
    }

    function xuiGbToBytes($gb){
        return intval($gb) * 1024 * 1024 * 1024;
    }

    function xuiGenerateSubId($length = 16){
        $chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
        $out = '';

        for($i = 0; $i < $length; $i++){
            $out .= $chars[random_int(0, strlen($chars) - 1)];
        }

        return $out;
    }

    function xuiGenerateUuid(){
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    function xuiSanitizeClientEmail($value){
        $value = trim((string)$value);
        $value = preg_replace('/[^A-Za-z0-9._-]+/', '_', $value);
        $value = trim($value, '._-');

        if($value === ''){
            $value = 'client_' . xuiGenerateSubId(8);
        }

        // 3x-ui email/remark should stay reasonably short
        if(strlen($value) > 64){
            $value = substr($value, 0, 64);
        }

        return $value;
    }

    function xuiBuildClientEmail($configName, $mobile){
        $configName = xuiSanitizeClientEmail($configName);
        $mobile = preg_replace('/\D+/', '', (string)$mobile);
        $last4 = strlen($mobile) >= 4 ? substr($mobile, -4) : '0000';
        return xuiSanitizeClientEmail($configName . '_' . $last4);
    }

    function xuiIsEnabled($config = null){
        if($config === null){
            $config = xuiLoadConfig();
        }

        $value = $config['enabled'] ?? false;

        if(is_bool($value)){
            return $value;
        }

        if(is_int($value) || is_float($value)){
            return intval($value) === 1;
        }

        $value = strtolower(trim((string)$value));
        return in_array($value, ['1', 'true', 'yes', 'on'], true);
    }

    function xuiBuildSubLink($host, $subId, $config = null){
        if($config === null){
            $config = xuiLoadConfig();
        }

        $port = intval($config['sub_port'] ?? 2096);
        $host = trim((string)$host);
        $subId = trim((string)$subId);

        return 'https://' . $host . ':' . $port . '/sub/' . rawurlencode($subId);
    }

    function xuiParseSubLink($link){
        $link = trim((string)$link);

        if(!preg_match('#https?://([^/:]+)(?::(\d+))?/sub/([A-Za-z0-9]+)#i', $link, $m)){
            return null;
        }

        return [
            'host' => strtolower($m[1]),
            'port' => intval($m[2] ?: 2096),
            'sub_id' => $m[3]
        ];
    }

    function xuiGetUserMobile($username){
        $file = __DIR__ . '/db/users.json';

        if(!file_exists($file)){
            return '';
        }

        $users = json_decode(file_get_contents($file), true);

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

    function xuiServerHasToken($server){
        $token = trim((string)($server['api_token'] ?? ''));

        return $token !== '' && strpos($token, 'REPLACE_TOKEN_') !== 0;
    }

    function xuiServerHasSessionCredentials($server){
        return trim((string)($server['username'] ?? '')) !== ''
            && trim((string)($server['password'] ?? '')) !== '';
    }

    function xuiServerHasAuth($server){
        return xuiServerHasToken($server) || xuiServerHasSessionCredentials($server);
    }

    function xuiGetStoredSession($server){
        $id = trim((string)($server['id'] ?? ''));

        if($id === ''){
            return null;
        }

        $state = xuiLoadState();
        $session = $state['sessions'][$id] ?? null;

        if(!is_array($session)){
            return null;
        }

        if(intval($session['expires_at'] ?? 0) < time()){
            return null;
        }

        $cookie = trim((string)($session['cookie'] ?? ''));

        if($cookie === ''){
            return null;
        }

        return $session;
    }

    function xuiSaveStoredSession($server, $cookie, $csrf = ''){
        $id = trim((string)($server['id'] ?? ''));

        if($id === '' || trim((string)$cookie) === ''){
            return;
        }

        $state = xuiLoadState();

        if(!isset($state['sessions']) || !is_array($state['sessions'])){
            $state['sessions'] = [];
        }

        $state['sessions'][$id] = [
            'cookie' => trim((string)$cookie),
            'csrf' => trim((string)$csrf),
            'expires_at' => time() + 21600,
        ];

        xuiSaveState($state);
    }

    function xuiCookieHeaderFromJar($jarFile){
        if(!is_file($jarFile)){
            return '';
        }

        $parts = [];

        foreach(file($jarFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line){
            if($line === '' || $line[0] === '#'){
                continue;
            }

            $cols = explode("\t", $line);

            if(count($cols) >= 7){
                $parts[] = $cols[5] . '=' . $cols[6];
            }
        }

        return implode('; ', $parts);
    }

    function xuiLoginSession($server){
        $base = rtrim((string)($server['base_url'] ?? ''), '/');
        $username = trim((string)($server['username'] ?? ''));
        $password = trim((string)($server['password'] ?? ''));

        if($base === '' || $username === '' || $password === '' || !function_exists('curl_init')){
            return false;
        }

        $jar = tempnam(sys_get_temp_dir(), 'xui_cookie_');
        $csrf = '';

        $csrfCurl = curl_init($base . '/csrf-token');
        curl_setopt($csrfCurl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($csrfCurl, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($csrfCurl, CURLOPT_TIMEOUT, 20);
        curl_setopt($csrfCurl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($csrfCurl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($csrfCurl, CURLOPT_COOKIEJAR, $jar);
        curl_setopt($csrfCurl, CURLOPT_COOKIEFILE, $jar);
        $csrfRaw = curl_exec($csrfCurl);
        curl_close($csrfCurl);

        if(is_string($csrfRaw) && $csrfRaw !== ''){
            $csrfJson = json_decode($csrfRaw, true);

            if(is_array($csrfJson)){
                $csrf = trim((string)($csrfJson['obj'] ?? ''));
            }
        }

        $headers = [
            'Accept: application/json',
            'Content-Type: application/json',
        ];

        if($csrf !== ''){
            $headers[] = 'X-CSRF-Token: ' . $csrf;
        }

        $loginCurl = curl_init($base . '/login');
        curl_setopt($loginCurl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($loginCurl, CURLOPT_POST, true);
        curl_setopt($loginCurl, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($loginCurl, CURLOPT_TIMEOUT, 20);
        curl_setopt($loginCurl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($loginCurl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($loginCurl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($loginCurl, CURLOPT_POSTFIELDS, json_encode([
            'username' => $username,
            'password' => $password,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        curl_setopt($loginCurl, CURLOPT_COOKIEJAR, $jar);
        curl_setopt($loginCurl, CURLOPT_COOKIEFILE, $jar);
        $loginRaw = curl_exec($loginCurl);
        curl_close($loginCurl);

        $loginJson = is_string($loginRaw) ? json_decode($loginRaw, true) : null;
        $cookie = xuiCookieHeaderFromJar($jar);
        @unlink($jar);

        if(!is_array($loginJson) || empty($loginJson['success']) || $cookie === ''){
            return false;
        }

        xuiSaveStoredSession($server, $cookie, $csrf);
        return true;
    }

    function xuiEnsureSession($server){
        $session = xuiGetStoredSession($server);

        if(is_array($session)){
            return $session;
        }

        if(!xuiLoginSession($server)){
            return null;
        }

        return xuiGetStoredSession($server);
    }

    function xuiApiRequestRaw($server, $method, $path, $body, $authMode){
        $base = rtrim((string)($server['base_url'] ?? ''), '/');

        if($base === ''){
            return ['success' => false, 'msg' => 'آدرس سرور 3x-ui خالی است'];
        }

        if(!function_exists('curl_init')){
            return ['success' => false, 'msg' => 'افزونه cURL فعال نیست'];
        }

        $url = $base . '/' . ltrim($path, '/');
        $headers = ['Accept: application/json'];
        $session = null;

        if($authMode === 'token'){
            $token = trim((string)($server['api_token'] ?? ''));

            if($token === ''){
                return ['success' => false, 'msg' => 'API Token تنظیم نشده است'];
            }

            $headers[] = 'Authorization: Bearer ' . $token;
        }
        else{
            $session = xuiEnsureSession($server);

            if(!is_array($session)){
                return ['success' => false, 'msg' => 'ورود session به 3x-ui انجام نشد'];
            }

            $headers[] = 'Cookie: ' . $session['cookie'];

            if(
                strtoupper($method) !== 'GET'
                && trim((string)($session['csrf'] ?? '')) !== ''
            ){
                $headers[] = 'X-CSRF-Token: ' . $session['csrf'];
            }
        }

        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 15);
        curl_setopt($curl, CURLOPT_TIMEOUT, 45);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, strtoupper($method));

        if($body !== null){
            $headers[] = 'Content-Type: application/json';
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }

        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

        $raw = curl_exec($curl);
        $error = curl_error($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if($raw === false || $error !== ''){
            return ['success' => false, 'msg' => 'خطا در ارتباط با 3x-ui: ' . $error, 'http' => $status];
        }

        $json = json_decode($raw, true);

        if(!is_array($json)){
            return ['success' => false, 'msg' => 'پاسخ نامعتبر از 3x-ui (HTTP ' . $status . ')', 'http' => $status, 'raw' => $raw];
        }

        $json['http'] = $status;
        return $json;
    }

    function xuiApiRequest($server, $method, $path, $body = null){
        $modes = [];

        if(xuiServerHasToken($server)){
            $modes[] = 'token';
        }

        if(xuiServerHasSessionCredentials($server)){
            $modes[] = 'session';
        }

        if(count($modes) === 0){
            return ['success' => false, 'msg' => 'تنظیمات سرور ناقص است (Token یا نام کاربری/رمز)'];
        }

        $last = ['success' => false, 'msg' => 'اتصال به 3x-ui ناموفق بود'];

        foreach($modes as $mode){
            $last = xuiApiRequestRaw($server, $method, $path, $body, $mode);

            if(!empty($last['success'])){
                return $last;
            }

            if($mode === 'session'){
                $id = trim((string)($server['id'] ?? ''));

                if($id !== ''){
                    $state = xuiLoadState();

                    if(isset($state['sessions'][$id])){
                        unset($state['sessions'][$id]);
                        xuiSaveState($state);
                    }
                }

                if(xuiLoginSession($server)){
                    $last = xuiApiRequestRaw($server, $method, $path, $body, 'session');

                    if(!empty($last['success'])){
                        return $last;
                    }
                }
            }
        }

        return $last;
    }

    function xuiTestServer($server){
        $result = xuiApiRequest($server, 'GET', '/panel/api/inbounds/options');

        if(empty($result['success'])){
            return [
                'ok' => false,
                'error' => $result['msg'] ?? 'اتصال ناموفق'
            ];
        }

        return [
            'ok' => true,
            'inbounds' => $result['obj'] ?? []
        ];
    }

    function xuiNormalizeClientRecord($client, $inboundId = null){
        if(!is_array($client)){
            return null;
        }

        $email = trim((string)($client['email'] ?? ''));
        $subId = trim((string)($client['subId'] ?? $client['sub_id'] ?? ''));
        $id = trim((string)($client['id'] ?? $client['uuid'] ?? ''));

        // در clientStats فیلد id عددی است و uuid جداست
        if($id !== '' && ctype_digit($id) && !empty($client['uuid'])){
            $id = trim((string)$client['uuid']);
        }

        if($email === '' && $id === '' && $subId === ''){
            return null;
        }

        $client['email'] = $email;
        $client['subId'] = $subId;
        $client['id'] = $id;

        if(!isset($client['totalGB']) && isset($client['total'])){
            $client['totalGB'] = intval($client['total']);
        }

        if($inboundId === null && isset($client['inboundId'])){
            $inboundId = intval($client['inboundId']);
        }

        if($inboundId !== null && intval($inboundId) > 0){
            $client['_inbound_id'] = intval($inboundId);
        }

        return $client;
    }

    function xuiClientMatchesSubId($client, $subId){
        if(!is_array($client)){
            return false;
        }

        $subId = trim((string)$subId);
        $candidate = trim((string)($client['subId'] ?? $client['sub_id'] ?? ''));

        return $subId !== '' && strcasecmp($candidate, $subId) === 0;
    }

    function xuiClientMatchesUuid($client, $uuid){
        if(!is_array($client) || trim((string)$uuid) === ''){
            return false;
        }

        $candidates = [
            trim((string)($client['id'] ?? '')),
            trim((string)($client['uuid'] ?? ''))
        ];

        foreach($candidates as $candidate){
            if($candidate !== '' && strcasecmp($candidate, $uuid) === 0){
                return true;
            }
        }

        return false;
    }

    function xuiParseInboundClients($inbound){
        if(!is_array($inbound)){
            return [];
        }

        $inboundId = intval($inbound['id'] ?? 0);
        $out = [];
        $seen = [];

        // نسخه‌های جدید: clientStats شامل email/subId/uuid است و settings ممکن است null باشد
        $stats = $inbound['clientStats'] ?? [];

        if(is_array($stats)){
            foreach($stats as $stat){
                $normalized = xuiNormalizeClientRecord($stat, $inboundId);

                if($normalized === null){
                    continue;
                }

                $key = strtolower(($normalized['email'] ?? '') . '|' . ($normalized['subId'] ?? '') . '|' . ($normalized['id'] ?? ''));

                if(isset($seen[$key])){
                    continue;
                }

                $seen[$key] = true;
                $out[] = $normalized;
            }
        }

        $settings = $inbound['settings'] ?? [];

        if(is_string($settings)){
            $decoded = json_decode($settings, true);
            $settings = is_array($decoded) ? $decoded : [];
        }

        $clients = is_array($settings) ? ($settings['clients'] ?? []) : [];

        if(is_array($clients)){
            foreach($clients as $client){
                $normalized = xuiNormalizeClientRecord($client, $inboundId);

                if($normalized === null){
                    continue;
                }

                $key = strtolower(($normalized['email'] ?? '') . '|' . ($normalized['subId'] ?? '') . '|' . ($normalized['id'] ?? ''));

                if(isset($seen[$key])){
                    continue;
                }

                $seen[$key] = true;
                $out[] = $normalized;
            }
        }

        return $out;
    }

    function xuiFetchInbounds($server){
        $attempts = [
            ['GET', '/panel/api/inbounds/list'],
            ['POST', '/panel/api/inbounds/list'],
            ['GET', '/panel/api/inbounds/list/'],
            ['POST', '/panel/inbound/list'],
            ['GET', '/panel/inbound/list']
        ];

        foreach($attempts as $attempt){
            $result = xuiApiRequest($server, $attempt[0], $attempt[1]);

            if(empty($result['success'])){
                continue;
            }

            $obj = $result['obj'] ?? [];

            if(is_array($obj)){
                // بعضی نسخه‌ها obj را مستقیم آرایه inbound می‌دهند
                if(isset($obj[0]) || count($obj) === 0){
                    return $obj;
                }

                if(isset($obj['list']) && is_array($obj['list'])){
                    return $obj['list'];
                }
            }
        }

        return [];
    }

    function xuiFindClientInInbounds($server, $subId = '', $uuid = ''){
        $inbounds = xuiFetchInbounds($server);

        // اول inbound تنظیم‌شده را هم مستقیم بگیر
        $configuredId = intval($server['inbound_id'] ?? 0);

        if($configuredId > 0){
            $one = xuiApiRequest($server, 'GET', '/panel/api/inbounds/get/' . $configuredId);

            if(!empty($one['success']) && is_array($one['obj'] ?? null)){
                array_unshift($inbounds, $one['obj']);
            }
        }

        foreach($inbounds as $inbound){
            foreach(xuiParseInboundClients($inbound) as $client){
                if(($subId !== '' && xuiClientMatchesSubId($client, $subId))
                    || ($uuid !== '' && xuiClientMatchesUuid($client, $uuid))){
                    return $client;
                }
            }
        }

        return null;
    }

    function xuiFindClientInClientsApi($server, $subId = '', $uuid = ''){
        $page = 1;
        $pageSize = 200;

        // اول با search مستقیم Sub ID (API جدید 3x-ui)
        if($subId !== ''){
            $searched = xuiApiRequest(
                $server,
                'GET',
                '/panel/api/clients/list/paged?page=1&pageSize=' . $pageSize
                . '&search=' . rawurlencode($subId)
                . '&filter=&protocol=&sort=email&order=ascend'
            );

            if(!empty($searched['success'])){
                $obj = $searched['obj'] ?? [];
                $list = $obj['list'] ?? $obj['clients'] ?? [];

                if(is_array($list)){
                    foreach($list as $item){
                        $client = xuiNormalizeClientRecord($item['client'] ?? $item);

                        if($client && xuiClientMatchesSubId($client, $subId)){
                            return xuiHydrateClientByEmail($server, $client);
                        }
                    }
                }
            }
        }

        // لیست کامل
        $full = xuiApiRequest($server, 'GET', '/panel/api/clients/list');

        if(!empty($full['success']) && is_array($full['obj'] ?? null)){
            foreach($full['obj'] as $item){
                $client = xuiNormalizeClientRecord($item['client'] ?? $item);

                if($client === null){
                    continue;
                }

                if(($subId !== '' && xuiClientMatchesSubId($client, $subId))
                    || ($uuid !== '' && xuiClientMatchesUuid($client, $uuid))){
                    return xuiHydrateClientByEmail($server, $client);
                }
            }
        }

        // صفحه‌بندی درست با pageSize (نه size)
        while($page <= 100){
            $result = xuiApiRequest(
                $server,
                'GET',
                '/panel/api/clients/list/paged?page=' . $page
                . '&pageSize=' . $pageSize
                . '&search=&filter=&protocol=&sort=email&order=ascend'
            );

            if(empty($result['success'])){
                break;
            }

            $obj = $result['obj'] ?? [];
            $list = $obj['list'] ?? $obj['clients'] ?? [];

            if(!is_array($list) || count($list) === 0){
                break;
            }

            foreach($list as $item){
                $client = xuiNormalizeClientRecord($item['client'] ?? $item);

                if($client === null){
                    continue;
                }

                if(($subId !== '' && xuiClientMatchesSubId($client, $subId))
                    || ($uuid !== '' && xuiClientMatchesUuid($client, $uuid))){
                    return xuiHydrateClientByEmail($server, $client);
                }
            }

            if(count($list) < $pageSize){
                break;
            }

            $page++;
        }

        return null;
    }

    function xuiEnrichClientTrafficFromInbounds($server, $client, $subId = '', $subLink = ''){
        if(!is_array($client) || !is_array($server)){
            return $client;
        }

        $subId = trim((string)($subId !== '' ? $subId : ($client['subId'] ?? '')));
        $uuid = trim((string)($client['uuid'] ?? $client['id'] ?? ''));

        if($uuid !== '' && ctype_digit($uuid)){
            $uuid = '';
        }

        $statClient = xuiFindClientInInbounds($server, $subId, $uuid);

        if(!is_array($statClient) && $subLink !== ''){
            $fetchedUuid = xuiFetchSubUuid($subLink);

            if($fetchedUuid !== ''){
                $statClient = xuiFindClientInInbounds($server, $subId, $fetchedUuid);
            }
        }

        if(!is_array($statClient)){
            return $client;
        }

        foreach(['up', 'down', 'used', 'total', 'totalGB', 'expiryTime', 'enable'] as $field){
            if(!isset($statClient[$field]) || $statClient[$field] === '' || $statClient[$field] === null){
                continue;
            }

            if(in_array($field, ['up', 'down', 'used'], true)){
                $client[$field] = $statClient[$field];
                continue;
            }

            if(!isset($client[$field]) || $client[$field] === '' || $client[$field] === null || intval($client[$field]) === 0){
                $client[$field] = $statClient[$field];
            }
        }

        if(xuiClientTotalBytes($statClient) > xuiClientTotalBytes($client)){
            foreach(['total', 'totalGB'] as $field){
                if(isset($statClient[$field])){
                    $client[$field] = $statClient[$field];
                }
            }
        }

        if(xuiClientUsedBytes($statClient) > xuiClientUsedBytes($client)){
            foreach(['up', 'down', 'used'] as $field){
                if(isset($statClient[$field])){
                    $client[$field] = $statClient[$field];
                }
            }
        }

        if(empty($client['_inbound_id']) && !empty($statClient['_inbound_id'])){
            $client['_inbound_id'] = $statClient['_inbound_id'];
        }

        return $client;
    }

    function xuiHydrateClientByEmail($server, $client){
        $email = trim((string)($client['email'] ?? ''));

        if($email === ''){
            return $client;
        }

        $full = xuiApiRequest($server, 'GET', '/panel/api/clients/get/' . rawurlencode($email));

        if(!empty($full['success']) && is_array($full['obj'] ?? null)){
            $fullClient = xuiNormalizeClientRecord($full['obj']['client'] ?? $full['obj']);

            if($fullClient !== null){
                if(empty($fullClient['_inbound_id']) && !empty($client['_inbound_id'])){
                    $fullClient['_inbound_id'] = $client['_inbound_id'];
                }

                foreach(['up', 'down', 'total', 'totalGB', 'expiryTime', 'enable'] as $field){
                    if((!isset($fullClient[$field]) || $fullClient[$field] === '' || $fullClient[$field] === null)
                        && isset($client[$field]) && $client[$field] !== '' && $client[$field] !== null){
                        $fullClient[$field] = $client[$field];
                    }
                }

                if(xuiClientTotalBytes($client) > xuiClientTotalBytes($fullClient)){
                    foreach(['total', 'totalGB'] as $field){
                        if(isset($client[$field])){
                            $fullClient[$field] = $client[$field];
                        }
                    }
                }

                if(xuiClientUsedBytes($client) > xuiClientUsedBytes($fullClient)){
                    foreach(['up', 'down', 'used'] as $field){
                        if(isset($client[$field])){
                            $fullClient[$field] = $client[$field];
                        }
                    }
                }

                return xuiEnrichClientTrafficFromInbounds(
                    $server,
                    $fullClient,
                    (string)($fullClient['subId'] ?? $client['subId'] ?? ''),
                    ''
                );
            }
        }

        return xuiEnrichClientTrafficFromInbounds(
            $server,
            $client,
            (string)($client['subId'] ?? ''),
            ''
        );
    }

    function xuiExtractEmailFromSubUrls($urls){
        if(!is_array($urls)){
            return '';
        }

        foreach($urls as $url){
            $url = (string)$url;

            if(strpos($url, '#') === false){
                continue;
            }

            $fragment = rawurldecode(substr($url, strpos($url, '#') + 1));
            $fragment = ltrim($fragment, '-');
            $fragment = preg_split('/[|\s]/u', $fragment)[0] ?? '';
            $fragment = trim($fragment);

            if($fragment !== '' && preg_match('/^[\w.@+\-]+$/u', $fragment)){
                return $fragment;
            }
        }

        return '';
    }

    function xuiFetchSubUuid($subLink){
        $subLink = trim((string)$subLink);

        if($subLink === ''){
            return '';
        }

        $raw = false;

        if(function_exists('curl_init')){
            $curl = curl_init($subLink);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($curl, CURLOPT_TIMEOUT, 20);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
            $raw = curl_exec($curl);
            curl_close($curl);
        }
        else{
            $context = stream_context_create([
                'http' => [
                    'timeout' => 20,
                    'ignore_errors' => true
                ],
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false
                ]
            ]);
            $raw = @file_get_contents($subLink, false, $context);
        }

        if($raw === false || $raw === ''){
            return '';
        }

        $decoded = base64_decode($raw, true);

        if($decoded === false || $decoded === ''){
            $decoded = $raw;
        }

        if(preg_match('#(?:vless|trojan|ss)://([0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12})@#', $decoded, $m)){
            return $m[1];
        }

        if(preg_match('#"id"\s*:\s*"([0-9a-fA-F-]{36})"#', $decoded, $m)){
            return $m[1];
        }

        return '';
    }

    function xuiFetchSubEmail($subLink){
        $subLink = trim((string)$subLink);

        if($subLink === ''){
            return '';
        }

        $raw = false;

        if(function_exists('curl_init')){
            $curl = curl_init($subLink);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($curl, CURLOPT_TIMEOUT, 20);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
            $raw = curl_exec($curl);
            curl_close($curl);
        }

        if($raw === false || $raw === ''){
            return '';
        }

        $decoded = base64_decode($raw, true);

        if($decoded === false || $decoded === ''){
            $decoded = $raw;
        }

        $urls = preg_split('/\r\n|\r|\n/', $decoded) ?: [];
        return xuiExtractEmailFromSubUrls($urls);
    }

    function xuiFindClientBySubId($server, $subId, $subLink = ''){
        $subId = trim((string)$subId);

        if($subId === ''){
            return null;
        }

        // 1) Clients API با search/pageSize درست
        $client = xuiFindClientInClientsApi($server, $subId);

        if($client){
            return $client;
        }

        // 2) inbound clientStats + settings.clients
        $client = xuiFindClientInInbounds($server, $subId);

        if($client){
            return xuiHydrateClientByEmail($server, $client);
        }

        // 3) endpoint رسمی subLinks در Clients API
        $subLinks = xuiApiRequest($server, 'GET', '/panel/api/clients/subLinks/' . rawurlencode($subId));

        if(!empty($subLinks['success'])){
            $obj = $subLinks['obj'] ?? null;
            $urls = [];

            if(is_array($obj)){
                if(isset($obj[0]) || count($obj) === 0){
                    $urls = $obj;
                }
                elseif(isset($obj['links']) && is_array($obj['links'])){
                    $urls = $obj['links'];
                }
            }

            $email = xuiExtractEmailFromSubUrls($urls);

            if($email !== ''){
                $full = xuiApiRequest($server, 'GET', '/panel/api/clients/get/' . rawurlencode($email));

                if(!empty($full['success']) && is_array($full['obj'] ?? null)){
                    $client = xuiNormalizeClientRecord($full['obj']['client'] ?? $full['obj']);

                    if($client){
                        if(($client['subId'] ?? '') === ''){
                            $client['subId'] = $subId;
                        }

                        return $client;
                    }
                }

                return xuiNormalizeClientRecord([
                    'email' => $email,
                    'subId' => $subId
                ]);
            }
        }

        // 4) UUID / email از لینک اشتراک عمومی
        $uuid = '';
        $subEmail = '';

        if($subLink !== ''){
            $uuid = xuiFetchSubUuid($subLink);
            $subEmail = xuiFetchSubEmail($subLink);
        }

        if($subEmail !== ''){
            $full = xuiApiRequest($server, 'GET', '/panel/api/clients/get/' . rawurlencode($subEmail));

            if(!empty($full['success']) && is_array($full['obj'] ?? null)){
                $client = xuiNormalizeClientRecord($full['obj']['client'] ?? $full['obj']);

                if($client){
                    if(($client['subId'] ?? '') === ''){
                        $client['subId'] = $subId;
                    }

                    return $client;
                }
            }

            // حتی اگر در Clients API نبود، با email از clientStats پیدا می‌شود
            $client = xuiFindClientInInbounds($server, $subId, $uuid);

            if(!$client){
                // جستجو با email داخل inboundها
                foreach(xuiFetchInbounds($server) as $inbound){
                    foreach(xuiParseInboundClients($inbound) as $item){
                        if(strcasecmp((string)($item['email'] ?? ''), $subEmail) === 0){
                            $client = $item;
                            break 2;
                        }
                    }
                }
            }

            if($client){
                if(($client['subId'] ?? '') === ''){
                    $client['subId'] = $subId;
                }

                if(($client['email'] ?? '') === ''){
                    $client['email'] = $subEmail;
                }

                return $client;
            }

            // آخرین راه: همان email را برای bulkAdjust برگردان
            return xuiNormalizeClientRecord([
                'email' => $subEmail,
                'subId' => $subId,
                'id' => $uuid
            ]);
        }

        if($uuid !== ''){
            $client = xuiFindClientInClientsApi($server, '', $uuid);

            if($client){
                if(($client['subId'] ?? '') === ''){
                    $client['subId'] = $subId;
                }

                return $client;
            }

            $client = xuiFindClientInInbounds($server, '', $uuid);

            if($client){
                if(($client['subId'] ?? '') === ''){
                    $client['subId'] = $subId;
                }

                return xuiHydrateClientByEmail($server, $client);
            }
        }

        return null;
    }

    function xuiCreateClient($server, $email, $gb, $subId = '', $options = []){
        $email = xuiSanitizeClientEmail($email);

        if($email === ''){
            return ['ok' => false, 'error' => 'نام کلاینت (email) خالی است'];
        }

        if($subId === ''){
            $subId = xuiGenerateSubId(16);
        }

        $inboundId = intval($server['inbound_id'] ?? 1);
        $options = is_array($options) ? $options : [];

        if(isset($options['total_bytes']) && intval($options['total_bytes']) > 0){
            $bytes = intval($options['total_bytes']);
        }
        else{
            $bytes = xuiGbToBytes($gb);
        }

        $expiryTime = max(0, intval($options['expiry_ms'] ?? 0));
        $uuid = xuiGenerateUuid();

        $client = [
            'id' => $uuid,
            'email' => $email,
            'enable' => true,
            'expiryTime' => $expiryTime,
            'totalGB' => $bytes,
            'limitIp' => 0,
            'subId' => $subId,
            'tgId' => 0,
            'comment' => 'pnv-panel',
            'flow' => '',
            'reset' => 0
        ];

        $errors = [];

        // Modern Clients API
        $result = xuiApiRequest($server, 'POST', '/panel/api/clients/add', [
            'client' => $client,
            'inboundIds' => [$inboundId]
        ]);

        if(!empty($result['success'])){
            return [
                'ok' => true,
                'email' => $email,
                'sub_id' => $subId,
                'link' => xuiBuildSubLink($server['host'] ?? '', $subId),
                'server' => $server,
                'raw' => $result
            ];
        }

        $errors[] = 'clients/add: ' . ($result['msg'] ?? 'ناموفق');

        // Legacy Inbounds API (widely compatible)
        $legacy = xuiApiRequest($server, 'POST', '/panel/api/inbounds/addClient', [
            'id' => $inboundId,
            'settings' => json_encode([
                'clients' => [$client]
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        ]);

        if(!empty($legacy['success'])){
            return [
                'ok' => true,
                'email' => $email,
                'sub_id' => $subId,
                'link' => xuiBuildSubLink($server['host'] ?? '', $subId),
                'server' => $server,
                'raw' => $legacy
            ];
        }

        $errors[] = 'inbounds/addClient: ' . ($legacy['msg'] ?? 'ناموفق');

        return [
            'ok' => false,
            'error' => 'ساخت کاربر در 3x-ui ناموفق بود برای «' . $email . '». ' . implode(' | ', $errors)
        ];
    }

    function xuiClientTotalBytes($client){
        if(!is_array($client)){
            return 0;
        }

        $candidates = [];

        // نسخه‌های مختلف: totalGB گاهی بایت است، گاهی گیگ
        if(isset($client['totalGB'])){
            $value = intval($client['totalGB']);

            if($value > 0){
                if($value < 1024){
                    $candidates[] = xuiGbToBytes($value);
                }
                else{
                    $candidates[] = max(0, $value);
                }
            }
        }

        if(isset($client['total'])){
            $candidates[] = max(0, intval($client['total']));
        }

        return count($candidates) > 0 ? max($candidates) : 0;
    }

    function xuiClientUsedBytes($client){
        if(!is_array($client)){
            return 0;
        }

        if(isset($client['used'])){
            return max(0, floatval($client['used']));
        }

        $up = max(0, floatval($client['up'] ?? $client['upload'] ?? 0));
        $down = max(0, floatval($client['down'] ?? $client['download'] ?? 0));

        return $up + $down;
    }

    function xuiClientExpiryMs($client){
        if(!is_array($client)){
            return 0;
        }

        $expiry = intval($client['expiryTime'] ?? $client['expiry_time'] ?? 0);

        if($expiry <= 0){
            return 0;
        }

        // 3x-ui: expiryTime معمولاً میلی‌ثانیه است
        if($expiry < 10000000000){
            return $expiry * 1000;
        }

        return $expiry;
    }

    function xuiComputeRenewedExpiryMs($client, $addDays){
        $addDays = max(0, intval($addDays));

        if($addDays <= 0){
            return 0;
        }

        $nowMs = intval(microtime(true) * 1000);
        $currentMs = xuiClientExpiryMs($client);
        $baseMs = ($currentMs > $nowMs) ? $currentMs : $nowMs;

        return $baseMs + ($addDays * 86400000);
    }

    function xuiUpdateClientExpiry($server, $client, $addDays){
        $email = trim((string)($client['email'] ?? ''));
        $clientId = trim((string)($client['id'] ?? ''));
        $inboundId = intval($client['_inbound_id'] ?? ($server['inbound_id'] ?? 0));
        $addDays = max(0, intval($addDays));

        if($addDays <= 0){
            return ['ok' => true, 'skipped' => true];
        }

        if($email === '' || $clientId === '' || $inboundId <= 0){
            return [
                'ok' => false,
                'error' => 'اطلاعات کلاینت برای تمدید زمان ناقص است'
            ];
        }

        $newExpiryMs = xuiComputeRenewedExpiryMs($client, $addDays);

        if($newExpiryMs <= 0){
            return [
                'ok' => false,
                'error' => 'محاسبه تاریخ انقضای جدید ناموفق بود'
            ];
        }

        $updated = $client;
        unset($updated['_inbound_id']);
        $updated['expiryTime'] = $newExpiryMs;
        $updated['enable'] = true;

        if(($updated['subId'] ?? '') === '' && ($client['subId'] ?? '') !== ''){
            $updated['subId'] = $client['subId'];
        }

        $result = xuiApiRequest($server, 'POST', '/panel/api/inbounds/updateClient/' . rawurlencode($clientId), [
            'id' => $inboundId,
            'settings' => json_encode([
                'clients' => [$updated]
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        ]);

        if(empty($result['success'])){
            return [
                'ok' => false,
                'error' => $result['msg'] ?? 'تمدید زمان با updateClient ناموفق بود'
            ];
        }

        return [
            'ok' => true,
            'raw' => $result,
            'method' => 'updateClientExpiry',
            'expiryTime' => $newExpiryMs
        ];
    }

    function xuiResolvePlanDays($planText){
        $days = xuiParsePlanDays($planText);

        if($days > 0){
            return $days;
        }

        if(!function_exists('pnvFindPlanByValue')){
            $planUiLib = __DIR__ . '/plan_ui_lib.php';
            if(is_file($planUiLib)){
                require_once $planUiLib;
            }
        }

        if(function_exists('pnvFindPlanByValue') && function_exists('pnvPlanIsUnlimited')){
            $plan = pnvFindPlanByValue($planText, xuiLoadPlansCatalog());

            if(is_array($plan) && !pnvPlanIsUnlimited($plan)){
                return max(0, intval($plan['days'] ?? 0));
            }
        }

        return 0;
    }

    function xuiAdjustClientTrafficLegacy($server, $client, $addGb, $addDays = 0){
        $email = trim((string)($client['email'] ?? ''));
        $clientId = trim((string)($client['id'] ?? ''));
        $inboundId = intval($client['_inbound_id'] ?? ($server['inbound_id'] ?? 0));
        $addBytes = xuiGbToBytes($addGb);

        if($email === '' || $clientId === '' || $inboundId <= 0){
            return [
                'ok' => false,
                'error' => 'اطلاعات کلاینت برای تمدید قدیمی ناقص است'
            ];
        }

        $updated = $client;
        unset($updated['_inbound_id']);

        $currentTotal = xuiClientTotalBytes($client);
        $updated['totalGB'] = $currentTotal + $addBytes;
        $updated['enable'] = true;

        $addDays = max(0, intval($addDays));

        if($addDays > 0){
            $newExpiryMs = xuiComputeRenewedExpiryMs($client, $addDays);

            if($newExpiryMs > 0){
                $updated['expiryTime'] = $newExpiryMs;
            }
        }

        if(($updated['subId'] ?? '') === '' && ($client['subId'] ?? '') !== ''){
            $updated['subId'] = $client['subId'];
        }

        $result = xuiApiRequest($server, 'POST', '/panel/api/inbounds/updateClient/' . rawurlencode($clientId), [
            'id' => $inboundId,
            'settings' => json_encode([
                'clients' => [$updated]
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        ]);

        if(empty($result['success'])){
            return [
                'ok' => false,
                'error' => $result['msg'] ?? 'افزایش ترافیک با updateClient ناموفق بود'
            ];
        }

        return [
            'ok' => true,
            'raw' => $result,
            'method' => 'updateClient'
        ];
    }

    function xuiAdjustClientTraffic($server, $email, $addGb, $client = null, $addDays = 0){
        $bytes = xuiGbToBytes($addGb);
        $email = trim((string)$email);
        $errors = [];
        $addDays = max(0, intval($addDays));
        $bytesAdjusted = false;

        if($email !== ''){
            $result = xuiApiRequest($server, 'POST', '/panel/api/clients/bulkAdjust', [
                'emails' => [$email],
                'addDays' => 0,
                'addBytes' => $bytes
            ]);

            if(!empty($result['success'])){
                $bytesAdjusted = true;
            }
            else{
                $errors[] = 'bulkAdjust: ' . ($result['msg'] ?? 'ناموفق');
            }
        }

        if(!$bytesAdjusted && is_array($client)){
            $legacy = xuiAdjustClientTrafficLegacy($server, $client, $addGb, $addDays);

            if(!empty($legacy['ok'])){
                return $legacy;
            }

            $errors[] = 'updateClient: ' . ($legacy['error'] ?? 'ناموفق');
        }

        if($bytesAdjusted){
            if($addDays > 0 && is_array($client)){
                $extended = xuiUpdateClientExpiry($server, $client, $addDays);

                if(empty($extended['ok'])){
                    return [
                        'ok' => false,
                        'error' => 'حجم اضافه شد اما تمدید زمان ناموفق بود: '
                            . ($extended['error'] ?? 'نامشخص')
                    ];
                }

                return [
                    'ok' => true,
                    'raw' => $extended['raw'] ?? null,
                    'method' => 'bulkAdjust+updateClientExpiry',
                    'expiryTime' => $extended['expiryTime'] ?? 0
                ];
            }

            return [
                'ok' => true,
                'raw' => $result ?? null,
                'method' => 'bulkAdjust'
            ];
        }

        return [
            'ok' => false,
            'error' => count($errors) > 0
                ? implode(' | ', $errors)
                : 'افزایش ترافیک ناموفق بود'
        ];
    }

    function xuiRenewStatsFromLink($subLink){
        if(!function_exists('subUsageFetchFromSubUserinfo')){
            $subUsageLib = __DIR__ . '/sub_usage_lib.php';
            if(is_file($subUsageLib)){
                require_once $subUsageLib;
            }
        }

        if(!function_exists('subUsageFetchFromSubUserinfo')){
            return [
                'remaining_bytes' => 0,
                'expiry_ms' => 0,
            ];
        }

        $info = subUsageFetchFromSubUserinfo($subLink);

        if(!is_array($info)){
            return [
                'remaining_bytes' => 0,
                'expiry_ms' => 0,
            ];
        }

        $used = max(0, floatval($info['used'] ?? 0));
        $total = max(0, floatval($info['total'] ?? 0));

        return [
            'remaining_bytes' => max(0, intval($total - $used)),
            'expiry_ms' => max(0, intval($info['expiry_ms'] ?? 0)),
        ];
    }

    function xuiResolveRenewClientEmail($username, $subLink){
        $username = trim((string)$username);
        $subLink = trim((string)$subLink);

        if(!function_exists('pnvFindSubCachedClientEmail')){
            $planUiLib = __DIR__ . '/plan_ui_lib.php';
            if(is_file($planUiLib)){
                require_once $planUiLib;
            }
        }

        if(function_exists('pnvFindSubCachedClientEmail')){
            $email = trim((string)pnvFindSubCachedClientEmail($subLink));

            if($email !== ''){
                return $email;
            }
        }

        if(function_exists('pnvFindSubClientEmail')){
            $email = trim((string)pnvFindSubClientEmail($subLink));

            if($email !== ''){
                return $email;
            }
        }

        $configName = '';

        if(function_exists('pnvFindSubNameFromCsv')){
            $configName = trim((string)pnvFindSubNameFromCsv($username, $subLink));
        }

        if($configName === ''){
            $parsed = xuiParseSubLink($subLink);
            $configName = trim((string)($parsed['sub_id'] ?? ''));

            if($configName === ''){
                $configName = $username !== '' ? $username : ('sub' . xuiGenerateSubId(6));
            }
        }

        $mobile = xuiGetUserMobile($username);

        return xuiBuildClientEmail($configName, $mobile);
    }

    function xuiComputeRecreateRenewSpecs($subLink, $planGb, $planDays){
        $planGb = max(0, intval($planGb));
        $planDays = max(0, intval($planDays));
        $stats = xuiRenewStatsFromLink($subLink);
        $totalBytes = max(0, intval($stats['remaining_bytes'])) + xuiGbToBytes($planGb);
        $expiryMs = 0;

        if($planDays > 0){
            $nowMs = intval(microtime(true) * 1000);
            $currentMs = max(0, intval($stats['expiry_ms']));
            $baseMs = ($currentMs > $nowMs) ? $currentMs : $nowMs;
            $expiryMs = $baseMs + ($planDays * 86400000);
        }

        return [
            'total_bytes' => $totalBytes,
            'expiry_ms' => $expiryMs,
            'remaining_bytes' => max(0, intval($stats['remaining_bytes'])),
        ];
    }

    function xuiEnsureUniqueClientEmail($server, $email){
        $email = xuiSanitizeClientEmail($email);

        if($email === ''){
            return $email;
        }

        $exists = xuiApiRequest($server, 'GET', '/panel/api/clients/get/' . rawurlencode($email));

        if(empty($exists['success'])){
            return $email;
        }

        return xuiSanitizeClientEmail($email . '_r' . substr(xuiGenerateSubId(4), 0, 4));
    }

    function xuiRecreateRenewClient($server, $paymentRow, $parsed, $gb, $days){
        $username = trim((string)($paymentRow[0] ?? ''));
        $subLink = trim((string)($paymentRow[1] ?? ''));
        $subId = trim((string)($parsed['sub_id'] ?? ''));
        $email = xuiResolveRenewClientEmail($username, $subLink);
        $email = xuiEnsureUniqueClientEmail($server, $email);

        if($email === ''){
            return ['ok' => false, 'error' => 'نام کلاینت برای بازسازی اشتراک پیدا نشد'];
        }

        $specs = xuiComputeRecreateRenewSpecs($subLink, $gb, $days);
        $subIdsToTry = [];

        if($subId !== ''){
            $subIdsToTry[] = $subId;
        }

        $subIdsToTry[] = xuiGenerateSubId(16);
        $errors = [];

        foreach(array_values(array_unique($subIdsToTry)) as $candidateSubId){
            $created = xuiCreateClient($server, $email, 0, $candidateSubId, [
                'total_bytes' => $specs['total_bytes'],
                'expiry_ms' => $specs['expiry_ms'],
            ]);

            if(!empty($created['ok'])){
                return [
                    'ok' => true,
                    'link' => $created['link'],
                    'email' => $created['email'],
                    'sub_id' => $created['sub_id'],
                    'server_id' => $server['id'] ?? '',
                    'gb' => $gb,
                    'days' => $days,
                    'recreated' => true,
                    'remaining_bytes' => $specs['remaining_bytes'],
                    'total_bytes' => $specs['total_bytes'],
                    'expiry_ms' => $specs['expiry_ms'],
                ];
            }

            $errors[] = ($created['error'] ?? 'بازسازی ناموفق بود') . ' [subId=' . $candidateSubId . ']';
        }

        return [
            'ok' => false,
            'error' => 'بازسازی اشتراک حذف‌شده ناموفق بود. ' . implode(' | ', $errors)
        ];
    }

    function xuiProvisionBuy($paymentRow){
        $config = xuiLoadConfig();

        if(!xuiIsEnabled($config)){
            return ['ok' => false, 'error' => 'اتوماسیون 3x-ui غیرفعال است'];
        }

        $username = trim((string)($paymentRow[0] ?? ''));
        $configName = trim((string)($paymentRow[1] ?? ''));
        $planText = trim((string)($paymentRow[2] ?? ''));
        $gb = xuiParsePlanGb($planText);

        if($gb <= 0){
            return ['ok' => false, 'error' => 'حجم پلن قابل تشخیص نیست: ' . $planText];
        }

        if($configName === ''){
            $configName = $username !== '' ? $username : ('user' . xuiGenerateSubId(6));
        }

        $mobile = xuiGetUserMobile($username);
        $email = xuiBuildClientEmail($configName, $mobile);
        $server = xuiPickBuyServer($config);

        if(!$server){
            return ['ok' => false, 'error' => 'سرور خرید پیدا نشد'];
        }

        // ensure unique email if exists
        $exists = xuiApiRequest($server, 'GET', '/panel/api/clients/get/' . rawurlencode($email));

        if(!empty($exists['success'])){
            $email .= '_' . substr(xuiGenerateSubId(6), 0, 4);
        }

        $created = xuiCreateClient($server, $email, $gb);

        if(empty($created['ok'])){
            return $created;
        }

        return [
            'ok' => true,
            'link' => $created['link'],
            'email' => $created['email'],
            'sub_id' => $created['sub_id'],
            'server_id' => $server['id'] ?? '',
            'gb' => $gb
        ];
    }

    function xuiProvisionRenew($paymentRow){
        $config = xuiLoadConfig();

        if(!xuiIsEnabled($config)){
            return ['ok' => false, 'error' => 'اتوماسیون 3x-ui غیرفعال است'];
        }

        $subLink = trim((string)($paymentRow[1] ?? ''));
        $planText = trim((string)($paymentRow[2] ?? ''));
        $gb = xuiParsePlanGb($planText);
        $days = xuiResolvePlanDays($planText);

        if($gb <= 0){
            return ['ok' => false, 'error' => 'حجم پلن قابل تشخیص نیست: ' . $planText];
        }

        $parsed = xuiParseSubLink($subLink);

        if(!$parsed){
            return ['ok' => false, 'error' => 'لینک اشتراک تمدید معتبر نیست'];
        }

        $server = xuiFindServerByHost($parsed['host'], $config);

        if(!$server){
            return ['ok' => false, 'error' => 'سرور مربوط به لینک پیدا نشد: ' . $parsed['host']];
        }

        $allowed = $config['renew_server_ids'] ?? [];

        if(is_array($allowed) && count($allowed) > 0 && !in_array($server['id'] ?? '', $allowed, true)){
            return ['ok' => false, 'error' => 'این سرور برای تمدید مجاز نیست'];
        }

        $client = xuiFindClientBySubId($server, $parsed['sub_id'], $subLink);

        if(!$client){
            return xuiRecreateRenewClient($server, $paymentRow, $parsed, $gb, $days, $config);
        }

        $email = trim((string)($client['email'] ?? ''));

        if($email === ''){
            return ['ok' => false, 'error' => 'ایمیل کلاینت خالی است'];
        }

        $adjusted = xuiAdjustClientTraffic($server, $email, $gb, $client, $days);

        if(empty($adjusted['ok'])){
            return $adjusted;
        }

        return [
            'ok' => true,
            'link' => xuiBuildSubLink($parsed['host'], $parsed['sub_id'], $config),
            'email' => $email,
            'sub_id' => $parsed['sub_id'],
            'server_id' => $server['id'] ?? '',
            'gb' => $gb,
            'days' => $days
        ];
    }

    function xuiPaymentsPath(){
        return __DIR__ . '/invoices/payments.csv';
    }

    function xuiLoadPayments(){
        $file = xuiPaymentsPath();
        $rows = [];

        if(!file_exists($file)){
            return [];
        }

        $handle = fopen($file, 'r');

        while(($row = fgetcsv($handle)) !== false){
            $rows[] = $row;
        }

        fclose($handle);
        return $rows;
    }

    function xuiSavePayments($rows){
        $handle = fopen(xuiPaymentsPath(), 'w');

        foreach($rows as $row){
            fputcsv($handle, $row);
        }

        fclose($handle);
    }

    function xuiSyncAutoPayJsonRow($csvIndex, $row, $link){
        $tracking = trim((string)($row[3] ?? ''));

        if(strpos($tracking, 'AUTO-') !== 0){
            return false;
        }

        $path = __DIR__ . '/db/instant_payments.json';

        if(!is_file($path)){
            return false;
        }

        $items = json_decode((string)file_get_contents($path), true);

        if(!is_array($items)){
            return false;
        }

        $userKey = strtolower(trim((string)($row[0] ?? '')));
        $code = preg_replace('/^AUTO-/i', '', $tracking);
        $code = str_pad((string)intval($code), 4, '0', STR_PAD_LEFT);
        $trackingNorm = 'AUTO-' . $code;
        $link = trim((string)$link);
        $changed = false;

        foreach($items as $i => $item){
            if(!is_array($item)){
                continue;
            }

            $matches = intval($item['csv_index'] ?? -1) === intval($csvIndex);

            if(!$matches && $userKey !== ''){
                $itemCode = str_pad((string)intval($item['code'] ?? 0), 4, '0', STR_PAD_LEFT);
                $matches = strtolower(trim((string)($item['user'] ?? ''))) === $userKey
                    && ('AUTO-' . $itemCode) === $trackingNorm;
            }

            if(!$matches){
                continue;
            }

            if(($item['status'] ?? '') === 'paid'){
                return true;
            }

            $items[$i]['status'] = 'paid';
            $items[$i]['paid_at'] = time();
            $items[$i]['link'] = $link;
            $items[$i]['message'] = 'پرداخت تأیید شد';
            $items[$i]['csv_index'] = intval($csvIndex);
            $items[$i]['csv_purged'] = false;
            unset($items[$i]['processing_at']);
            $changed = true;
            break;
        }

        if($changed){
            file_put_contents(
                $path,
                json_encode(array_values($items), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
                LOCK_EX
            );
        }

        return $changed;
    }

    function xuiResolvePaymentType($row, $typeHint = ''){
        if(function_exists('telegramResolvePaymentKind')){
            return telegramResolvePaymentKind($row, $typeHint);
        }

        $type = trim((string)($row[9] ?? $typeHint));

        return ($type === 'تمدید') ? 'تمدید' : 'خرید';
    }

    function xuiTelegramNotifyApproved($row, $typeHint, $link, $opts = []){
        $results = [];

        if(!function_exists('telegramNotifyPaymentConfirmedRow') && is_file(__DIR__ . '/telegram_lib.php')){
            require_once __DIR__ . '/telegram_lib.php';
        }

        if(function_exists('telegramNotifyPaymentConfirmedRow')){
            try{
                $results['telegram'] = telegramNotifyPaymentConfirmedRow($row, $typeHint, array_merge($opts, [
                    'link' => $link,
                ]));
            }
            catch(Throwable $e){
                error_log('xui approve telegram admin notify failed: ' . $e->getMessage());
            }
        }

        if(!function_exists('baleNotifyPaymentConfirmedRow') && is_file(__DIR__ . '/bale_lib.php')){
            require_once __DIR__ . '/bale_lib.php';
        }

        if(function_exists('baleNotifyPaymentConfirmedRow')){
            try{
                $results['bale'] = baleNotifyPaymentConfirmedRow($row, $typeHint, array_merge($opts, [
                    'link' => $link,
                ]));
            }
            catch(Throwable $e){
                error_log('xui approve bale admin notify failed: ' . $e->getMessage());
            }
        }

        return $results;
    }

    function xuiApprovePaymentIndex($index, $typeHint = ''){
        $payments = xuiLoadPayments();

        if(!isset($payments[$index])){
            return ['ok' => false, 'error' => 'پرداخت پیدا نشد'];
        }

        $row = $payments[$index];
        $rowType = trim((string)($row[9] ?? ''));
        $hintType = trim((string)$typeHint);

        if($rowType !== '' && !in_array($rowType, ['خرید', 'تمدید'], true)){
            return ['ok' => false, 'error' => 'نوع پرداخت ذخیره‌شده نامعتبر است'];
        }

        if($hintType !== '' && !in_array($hintType, ['خرید', 'تمدید'], true)){
            return ['ok' => false, 'error' => 'نوع پرداخت درخواستی نامعتبر است'];
        }

        if($rowType !== '' && $hintType !== '' && $rowType !== $hintType){
            return ['ok' => false, 'error' => 'نوع پرداخت با سفارش مطابقت ندارد'];
        }

        $type = xuiResolvePaymentType($row, $hintType);
        $status = trim((string)($row[6] ?? ''));

        if($status === 'تایید شد'){
            $link = $row[7] ?? '';
            $result = [
                'ok' => true,
                'already' => true,
                'link' => $link
            ];

            xuiSyncAutoPayJsonRow($index, $row, $link);

            return $result;
        }

        if($type === 'تمدید'){
            $result = xuiProvisionRenew($row);
        }
        else{
            $result = xuiProvisionBuy($row);
        }

        if(empty($result['ok'])){
            return $result;
        }

        $payments[$index][6] = 'تایید شد';
        $payments[$index][7] = $result['link'];
        xuiSavePayments($payments);

        if(!function_exists('subUsageInvalidateLink') && is_file(__DIR__ . '/sub_usage_lib.php')){
            require_once __DIR__ . '/sub_usage_lib.php';
        }

        if(function_exists('subUsageInvalidateLink')){
            $renewLink = trim((string)($row[1] ?? ''));

            if($type === 'تمدید' && $renewLink !== ''){
                subUsageInvalidateLink($renewLink);
            }

            if(!empty($result['link'])){
                subUsageInvalidateLink($result['link']);
            }
        }

        if(!function_exists('tgUserNotifyPaymentApproved') && is_file(__DIR__ . '/telegram_user_lib.php')){
            require_once __DIR__ . '/telegram_user_lib.php';
        }

        if(function_exists('tgUserNotifyPaymentApproved')){
            $username = trim((string)($row[0] ?? ''));
            $configName = trim((string)($row[1] ?? ''));
            $planText = trim((string)($row[2] ?? ''));
            $subName = $configName;

            if($subName === '' && !empty($result['email'])){
                $subName = trim((string)$result['email']);
            }

            tgUserNotifyPaymentApproved($username, $subName, $planText, $type === 'تمدید');
        }

        if(!function_exists('telegramNotifyNewPayment') && is_file(__DIR__ . '/telegram_lib.php')){
            require_once __DIR__ . '/telegram_lib.php';
        }

        if(function_exists('telegramNotifyNewPayment')){
            try{
                $notifyRow = $payments[$index];
                $notifyRow[6] = 'تایید شد';
                $notifyRow[7] = $result['link'] ?? ($notifyRow[7] ?? '');

                if(trim((string)($notifyRow[4] ?? '')) === '' || trim((string)($notifyRow[5] ?? '')) === ''){
                    if(!function_exists('pnvNowParts') && is_file(__DIR__ . '/pnv_date_bootstrap.php')){
                        require_once __DIR__ . '/pnv_date_bootstrap.php';
                    }

                    if(function_exists('pnvNowParts')){
                        $nowParts = pnvNowParts();
                        $notifyRow[4] = $notifyRow[4] ?: ($nowParts['date'] ?? '');
                        $notifyRow[5] = $notifyRow[5] ?: ($nowParts['time'] ?? '');
                    }
                }

                $notifyKind = ($type === 'تمدید') ? 'تمدید' : 'خرید';
                telegramNotifyNewPayment($notifyKind, $notifyRow, ['confirmed' => true]);
            }
            catch(Throwable $e){
                error_log('xui approve telegram admin notify failed: ' . $e->getMessage());
            }
        }

        xuiSyncAutoPayJsonRow($index, $payments[$index], $result['link'] ?? '');

        return $result;
    }

    function xuiRejectPaymentIndex($index, $reason = 'رد شد'){
        $payments = xuiLoadPayments();

        if(!isset($payments[$index])){
            return ['ok' => false, 'error' => 'پرداخت پیدا نشد'];
        }

        $payments[$index][6] = 'رد شد';
        $payments[$index][7] = $reason;
        xuiSavePayments($payments);

        return ['ok' => true];
    }

    function xuiDeletePaymentIndexes($indexes){
        $payments = xuiLoadPayments();
        $indexes = array_values(array_unique(array_map('intval', (array)$indexes)));

        if(!$indexes){
            return ['ok' => true, 'deleted' => 0, 'payments' => $payments];
        }

        rsort($indexes);
        $deleted = 0;

        foreach($indexes as $index){
            if(!isset($payments[$index])){
                continue;
            }
            unset($payments[$index]);
            $deleted++;
        }

        if($deleted > 0){
            $payments = array_values($payments);
            xuiSavePayments($payments);
        }

        return ['ok' => true, 'deleted' => $deleted, 'payments' => $payments];
    }
}
