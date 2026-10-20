<?php

if(!function_exists('xuiConfigPath')){

    function xuiConfigPath(){
        $override = getenv('PNV_XUI_CONFIG_PATH');

        if(is_string($override) && trim($override) !== ''){
            return trim($override);
        }

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
        $override = getenv('PNV_PLANS_CATALOG_PATH');
        $file = is_string($override) && $override !== ''
            ? $override
            : __DIR__ . '/db/plans.json';

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
    function xuiPlanDurationMeta($planText){
        $planText = trim((string)$planText);
        $meta = [
            'resolved' => false,
            'time_unlimited' => false,
            'days' => null,
        ];

        if($planText === ''){
            return $meta;
        }

        if(
            preg_match('/نامحدود\s*زمان/u', $planText)
            || preg_match('/\bunlimited\b/i', $planText)
            || preg_match('/زمان\s*نامحدود/u', $planText)
        ){
            $meta['resolved'] = true;
            $meta['time_unlimited'] = true;
            $meta['days'] = 0;

            return $meta;
        }

        if(preg_match('/(\d+)\s*ماه/u', $planText, $m)){
            $meta['resolved'] = true;
            $meta['days'] = max(0, intval($m[1]) * 30);

            return $meta;
        }

        if(preg_match('/(\d+)\s*روز/u', $planText, $m)){
            $meta['resolved'] = true;
            $meta['days'] = max(0, intval($m[1]));

            return $meta;
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

            if($days === 'نامحدود' || strcasecmp($days, 'unlimited') === 0){
                $meta['resolved'] = true;
                $meta['time_unlimited'] = true;
                $meta['days'] = 0;

                return $meta;
            }

            if($days !== '' && preg_match('/^\d+$/', $days)){
                $meta['resolved'] = true;
                $meta['days'] = max(0, intval($days));

                return $meta;
            }

            break;
        }

        return $meta;
    }

    function xuiParsePlanDays($planText){
        $meta = xuiPlanDurationMeta($planText);

        if($meta['time_unlimited']){
            return 0;
        }

        if($meta['resolved']){
            return max(0, intval($meta['days']));
        }

        return 0;
    }

    function xuiResolveRenewPlanDays($planText){
        $meta = xuiPlanDurationMeta($planText);

        if($meta['time_unlimited']){
            return [
                'ok' => true,
                'days' => 0,
                'time_unlimited' => true,
            ];
        }

        if($meta['resolved']){
            return [
                'ok' => true,
                'days' => max(0, intval($meta['days'])),
                'time_unlimited' => false,
            ];
        }

        return [
            'ok' => false,
            'error' => 'مدت پلن قابل تشخیص نیست؛ تمدید بدون مدت مشخص انجام نمی‌شود',
            'code' => 'invalid_plan_duration',
        ];
    }

    /**
     * expire از هدر subscription-userinfo
     * null = نامشخص | 0 = نامحدود | >0 = محدود (unix)
     */
    function xuiFetchSubUserinfoExpire($link){
        $link = trim((string)$link);

        if(array_key_exists('xuiTestSubUserinfoExpire', $GLOBALS)){
            $v = $GLOBALS['xuiTestSubUserinfoExpire'];

            return $v === null ? null : max(0, intval($v));
        }

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
        if(!empty($GLOBALS['xuiTestApiRequest']) && is_callable($GLOBALS['xuiTestApiRequest'])){
            return $GLOBALS['xuiTestApiRequest']($server, $method, $path, $body);
        }

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

    function xuiApiRequestRawForm($server, $method, $path, array $fields, $authMode){
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

        if(count($fields) > 0){
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
            curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($fields, '', '&', PHP_QUERY_RFC3986));
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
            return ['success' => false, 'msg' => 'پاسخ نامعتبر از 3x-ui (HTTP ' . $status . ')', 'http' => $status];
        }

        $json['http'] = $status;

        return $json;
    }

    function xuiApiRequestForm($server, $method, $path, array $fields){
        if(!empty($GLOBALS['xuiTestApiRequestForm']) && is_callable($GLOBALS['xuiTestApiRequestForm'])){
            return $GLOBALS['xuiTestApiRequestForm']($server, $method, $path, $fields);
        }

        if(!empty($GLOBALS['xuiTestApiRequest']) && is_callable($GLOBALS['xuiTestApiRequest'])){
            return $GLOBALS['xuiTestApiRequest']($server, $method, $path, $fields);
        }

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
            $last = xuiApiRequestRawForm($server, $method, $path, $fields, $mode);

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
                    $last = xuiApiRequestRawForm($server, $method, $path, $fields, 'session');

                    if(!empty($last['success'])){
                        return $last;
                    }
                }
            }
        }

        return $last;
    }

    function xuiApiAttemptDiag($apiPath, $result){
        $msg = trim((string)($result['msg'] ?? ''));

        if($msg !== '' && preg_match('/(token|password|secret|session|csrf|authorization)/iu', $msg)){
            $msg = 'API error (redacted)';
        }

        // Keep email/UUID out of admin messages and diagnostic logs.
        $safePath = preg_replace(
            '~(/panel/api/(?:clients/update|inbounds/updateClient)/)[^\\s()]+~',
            '$1{client}',
            (string)$apiPath
        );

        return [
            'api_path' => $safePath,
            'http_status' => intval($result['http'] ?? 0),
            'api_success' => !empty($result['success']),
            'api_msg' => $msg !== '' ? $msg : 'no message',
        ];
    }

    function xuiApplyRenewDiagSummary($applied){
        if(!is_array($applied)){
            return '';
        }

        $parts = [];

        if(!empty($applied['error'])){
            $parts[] = trim((string)$applied['error']);
        }

        foreach($applied['attempts'] ?? [] as $attempt){
            if(!is_array($attempt)){
                continue;
            }

            $parts[] = trim((string)($attempt['api_path'] ?? ''))
                . ' HTTP ' . intval($attempt['http_status'] ?? 0)
                . ': ' . trim((string)($attempt['api_msg'] ?? ''));
        }

        return implode(' | ', array_filter($parts, static fn($p) => $p !== ''));
    }

    function xuiInboundClientForAbsoluteUpdate(array $client, $targetBytes, $expiryMs){
        $id = trim((string)($client['id'] ?? $client['uuid'] ?? ''));

        if($id !== '' && ctype_digit($id) && !empty($client['uuid'])){
            $id = trim((string)$client['uuid']);
        }

        $out = [
            'id' => $id,
            'email' => trim((string)($client['email'] ?? '')),
            'enable' => true,
            'expiryTime' => intval($expiryMs),
            'totalGB' => intval($targetBytes),
            'limitIp' => intval($client['limitIp'] ?? 0),
            'subId' => trim((string)($client['subId'] ?? '')),
            'tgId' => $client['tgId'] ?? 0,
            'comment' => (string)($client['comment'] ?? ''),
            'flow' => (string)($client['flow'] ?? ''),
            'reset' => 1,
        ];

        if(isset($client['alterId'])){
            $out['alterId'] = intval($client['alterId']);
        }

        return $out;
    }

    function xuiModernClientUpdatePayload(array $client, $targetBytes, $expiryMs){
        $payload = [
            'email' => trim((string)($client['email'] ?? '')),
            'enable' => true,
            'expiryTime' => intval($expiryMs),
            'totalGB' => intval($targetBytes),
            'limitIp' => intval($client['limitIp'] ?? 0),
            'subId' => trim((string)($client['subId'] ?? '')),
            'tgId' => intval($client['tgId'] ?? 0),
            'comment' => (string)($client['comment'] ?? ''),
            'reset' => 1,
        ];

        foreach(['flow', 'security'] as $field){
            if(isset($client[$field]) && $client[$field] !== ''){
                $payload[$field] = $client[$field];
            }
        }

        return $payload;
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

    function xuiCreateClient($server, $email, $gb, $subId = ''){
        $email = xuiSanitizeClientEmail($email);

        if($email === ''){
            return ['ok' => false, 'error' => 'نام کلاینت (email) خالی است'];
        }

        if($subId === ''){
            $subId = xuiGenerateSubId(16);
        }

        $inboundId = intval($server['inbound_id'] ?? 1);
        $bytes = xuiGbToBytes($gb);
        $uuid = xuiGenerateUuid();

        $client = [
            'id' => $uuid,
            'email' => $email,
            'enable' => true,
            'expiryTime' => 0,
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

    /**
     * Fresh renew client: absolute plan volume and expiry from activation (no carry-over).
     */
    function xuiCreateClientForRenewPlanVerifyOrAmbiguous($server, $subId, $email, $gb, $days, $anchorMs = null){
        $probeLink = xuiBuildSubLink($server['host'] ?? '', $subId);
        $verify = xuiRenewLookupClient($server, $subId, $probeLink);

        if(($verify['status'] ?? '') === 'found' && is_array($verify['client'] ?? null)){
            if(xuiRenewVerifyRecreateClient($verify['client'], $gb, $days, $subId, $anchorMs)){
                return [
                    'ok' => true,
                    'email' => trim((string)($verify['client']['email'] ?? $email)),
                    'sub_id' => $subId,
                    'link' => $probeLink,
                    'server' => $server,
                    'method' => 'recreate_verify_after_transport',
                    'sub_id_preserved' => true,
                ];
            }

            return [
                'ok' => false,
                'error' => 'کلاینت موجود با پلن هدف تطابق قابل اثبات ندارد؛ وضعیت ساخت نامشخص است',
                'code' => 'ambiguous_provision',
            ];
        }

        if(($verify['status'] ?? '') === 'api_error'){
            return [
                'ok' => false,
                'error' => 'پس از قطع ارتباط، وضعیت ساخت اشتراک روی سرور نامشخص است',
                'code' => 'ambiguous_provision',
            ];
        }

        return [
            'ok' => false,
            'error' => 'پس از قطع ارتباط، وضعیت ساخت اشتراک روی سرور نامشخص است؛ برای جلوگیری از اشتراک تکراری دوباره ساخته نشد',
            'code' => 'ambiguous_provision',
        ];
    }

    function xuiCreateClientForRenewPlan($server, $email, $gb, $days, $subId = '', $anchorMs = null){
        if(!empty($GLOBALS['xuiTestCreateClientForRenewPlan']) && is_callable($GLOBALS['xuiTestCreateClientForRenewPlan'])){
            return $GLOBALS['xuiTestCreateClientForRenewPlan']($server, $email, $gb, $days, $subId, $anchorMs);
        }

        $email = xuiSanitizeClientEmail($email);

        if($email === ''){
            return ['ok' => false, 'error' => 'نام کلاینت (email) خالی است'];
        }

        if($subId === ''){
            $subId = xuiGenerateSubId(16);
        }

        $inboundId = intval($server['inbound_id'] ?? 1);
        $bytes = xuiGbToBytes($gb);
        $days = max(0, intval($days));
        $expiryMs = 0;

        if($days > 0){
            if($anchorMs === null){
                $anchorMs = function_exists('renewReserveNowMs')
                    ? renewReserveNowMs()
                    : (int)(microtime(true) * 1000);
            }

            $expiryMs = intval($anchorMs) + ($days * 86400000);
        }

        $uuid = xuiGenerateUuid();
        $client = [
            'id' => $uuid,
            'email' => $email,
            'enable' => true,
            'expiryTime' => $expiryMs,
            'totalGB' => $bytes,
            'limitIp' => 0,
            'subId' => $subId,
            'tgId' => 0,
            'comment' => 'pnv-panel-renew-recreate',
            'flow' => '',
            'reset' => 0,
        ];

        $errors = [];
        $result = xuiApiRequest($server, 'POST', '/panel/api/clients/add', [
            'client' => $client,
            'inboundIds' => [$inboundId],
        ]);

        if(!empty($result['success'])){
            return [
                'ok' => true,
                'email' => $email,
                'sub_id' => $subId,
                'link' => xuiBuildSubLink($server['host'] ?? '', $subId),
                'server' => $server,
                'raw' => $result,
                'sub_id_preserved' => true,
            ];
        }

        $errors[] = 'clients/add: ' . ($result['msg'] ?? 'ناموفق');
        $firstTransport = xuiApiFailureKind($result) === 'transport';

        if($firstTransport){
            return xuiCreateClientForRenewPlanVerifyOrAmbiguous($server, $subId, $email, $gb, $days, $anchorMs);
        }

        $legacy = xuiApiRequest($server, 'POST', '/panel/api/inbounds/addClient', [
            'id' => $inboundId,
            'settings' => json_encode(['clients' => [$client]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        if(!empty($legacy['success'])){
            return [
                'ok' => true,
                'email' => $email,
                'sub_id' => $subId,
                'link' => xuiBuildSubLink($server['host'] ?? '', $subId),
                'server' => $server,
                'raw' => $legacy,
                'sub_id_preserved' => true,
            ];
        }

        $errors[] = 'inbounds/addClient: ' . ($legacy['msg'] ?? 'ناموفق');

        if(xuiApiFailureKind($legacy) === 'transport'){
            return xuiCreateClientForRenewPlanVerifyOrAmbiguous($server, $subId, $email, $gb, $days, $anchorMs);
        }

        $lastApi = $legacy;

        return [
            'ok' => false,
            'error' => 'ساخت اشتراک جدید تمدید ناموفق بود: ' . implode(' | ', $errors),
            'code' => xuiApiSubIdConflict($lastApi) ? 'sub_id_conflict' : 'recreate_failed',
            'raw' => $lastApi,
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

    function xuiAdjustClientTrafficLegacy($server, $client, $addGb){
        $inboundId = intval($client['_inbound_id'] ?? ($server['inbound_id'] ?? 0));
        $normalized = xuiNormalizeClientRecord($client, $inboundId > 0 ? $inboundId : null);

        if(!is_array($normalized)){
            return ['ok' => false, 'error' => 'اطلاعات کلاینت برای تمدید قدیمی نامعتبر است'];
        }

        $email = trim((string)($normalized['email'] ?? ''));
        $clientId = trim((string)($normalized['id'] ?? ''));
        $inboundId = intval($normalized['_inbound_id'] ?? $inboundId);
        $addBytes = xuiGbToBytes($addGb);

        // clientStats.id is sometimes a numeric database row ID, not a 3x-ui UUID.
        // Refuse a mutating update until the actual client UUID is available.
        if($email === '' || $clientId === '' || ctype_digit($clientId) || $inboundId <= 0){
            return [
                'ok' => false,
                'error' => 'شناسه UUID یا inbound کلاینت برای updateClient معتبر نیست',
                'code' => 'invalid_client_identity',
            ];
        }

        $updated = $normalized;
        unset($updated['_inbound_id']);

        $updated['totalGB'] = xuiClientTotalBytes($normalized) + $addBytes;
        $updated['enable'] = true;
        $updated['reset'] = 1;

        $path = '/panel/api/inbounds/updateClient/' . rawurlencode($clientId);
        $settings = json_encode(['clients' => [$updated]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        // Legacy 3x-ui generally expects form-urlencoded id/settings.
        $formResult = xuiApiRequestForm($server, 'POST', $path, [
            'id' => (string)$inboundId,
            'settings' => $settings,
        ]);
        $attempts = [xuiApiAttemptDiag($path . ' (form)', $formResult)];

        if(!empty($formResult['success'])){
            return ['ok' => true, 'raw' => $formResult, 'method' => 'updateClient_form'];
        }

        // Never replay a mutation after a timeout/5xx/ambiguous response.
        $status = intval($formResult['http'] ?? 0);

        if(!in_array($status, [400, 404, 405, 415], true)){
            return [
                'ok' => false,
                'error' => 'updateClient form failed | ' . xuiApplyRenewDiagSummary(['attempts' => $attempts]),
                'attempts' => $attempts,
                'code' => 'ambiguous_update_response',
            ];
        }

        $jsonResult = xuiApiRequest($server, 'POST', $path, [
            'id' => $inboundId,
            'settings' => $settings,
        ]);
        $attempts[] = xuiApiAttemptDiag($path . ' (json)', $jsonResult);

        if(!empty($jsonResult['success'])){
            return ['ok' => true, 'raw' => $jsonResult, 'method' => 'updateClient_json'];
        }

        return [
            'ok' => false,
            'error' => 'updateClient failed | ' . xuiApplyRenewDiagSummary(['attempts' => $attempts]),
            'attempts' => $attempts,
            'code' => 'update_client_failed',
        ];
    }

    function xuiAdjustClientTraffic($server, $email, $addGb, $client = null, $addDays = 0){
        if(!empty($GLOBALS['xuiTestAdjustClientTraffic']) && is_callable($GLOBALS['xuiTestAdjustClientTraffic'])){
            return $GLOBALS['xuiTestAdjustClientTraffic']($server, $email, $addGb, $client, $addDays);
        }

        $bytes = xuiGbToBytes($addGb);
        $email = trim((string)$email);
        $errors = [];
        $addDays = max(0, intval($addDays));

        if($email !== ''){
            $result = xuiApiRequest($server, 'POST', '/panel/api/clients/bulkAdjust', [
                'emails' => [$email],
                'addDays' => $addDays,
                'addBytes' => $bytes
            ]);

            if(!empty($result['success'])){
                return [
                    'ok' => true,
                    'raw' => $result,
                    'method' => 'bulkAdjust',
                    'days_applied' => $addDays > 0,
                ];
            }

            if(xuiApiFailureKind($result) === 'transport'){
                return [
                    'ok' => false,
                    'error' => $result['msg'] ?? 'bulkAdjust transport error',
                    'code' => 'transport_error',
                    'method' => 'bulkAdjust',
                ];
            }

            $errors[] = 'bulkAdjust: ' . ($result['msg'] ?? 'ناموفق');
        }

        if(is_array($client)){
            if($addDays > 0){
                return [
                    'ok' => false,
                    'error' => 'افزایش حجم با API قدیمی برای پلن زمان‌دار مدت را اعمال نمی‌کند',
                    'code' => 'duration_not_applied',
                ];
            }

            $legacy = xuiAdjustClientTrafficLegacy($server, $client, $addGb);

            if(!empty($legacy['ok'])){
                return $legacy;
            }

            $errors[] = 'updateClient: ' . ($legacy['error'] ?? 'ناموفق');
        }

        return [
            'ok' => false,
            'error' => count($errors) > 0
                ? implode(' | ', $errors)
                : 'افزایش ترافیک ناموفق بود'
        ];
    }

    function xuiApplyRenewPlanAbsolute($server, $client, $gb, $days, $anchorMs = null){
        if(!empty($GLOBALS['xuiTestApplyRenewPlanAbsolute']) && is_callable($GLOBALS['xuiTestApplyRenewPlanAbsolute'])){
            return $GLOBALS['xuiTestApplyRenewPlanAbsolute']($server, $client, $gb, $days, $anchorMs);
        }

        if(!is_array($client)){
            return ['ok' => false, 'error' => 'کلاینت برای فعال‌سازی پلن نامعتبر است'];
        }

        $email = trim((string)($client['email'] ?? ''));
        $clientId = trim((string)($client['id'] ?? ''));
        $inboundId = intval($client['_inbound_id'] ?? ($server['inbound_id'] ?? 0));
        $targetBytes = xuiGbToBytes($gb);
        $days = max(0, intval($days));
        $expiryMs = 0;
        $attempts = [];

        if($days > 0){
            if($anchorMs === null){
                $anchorMs = function_exists('renewReserveNowMs')
                    ? renewReserveNowMs()
                    : (int)(microtime(true) * 1000);
            }

            $expiryMs = intval($anchorMs) + ($days * 86400000);
        }

        if($email === ''){
            return ['ok' => false, 'error' => 'email کلاینت برای فعال‌سازی مطلق خالی است', 'attempts' => $attempts];
        }

        $inboundClient = xuiInboundClientForAbsoluteUpdate($client, $targetBytes, $expiryMs);

        if($clientId === ''){
            $clientId = trim((string)($inboundClient['id'] ?? ''));
        }

        $last = ['success' => false, 'msg' => 'هیچ مسیر API برای فعال‌سازی مطلق در دسترس نبود', 'http' => 0];

        if($email !== ''){
            $modernPath = '/panel/api/clients/update/' . rawurlencode($email);
            $modernResult = xuiApiRequest($server, 'POST', $modernPath, xuiModernClientUpdatePayload($client, $targetBytes, $expiryMs));
            $attempts[] = xuiApiAttemptDiag($modernPath, $modernResult);
            $last = $modernResult;

            if(!empty($modernResult['success'])){
                return [
                    'ok' => true,
                    'method' => 'clients_update',
                    'attempts' => $attempts,
                ];
            }
        }

        if($clientId !== '' && $inboundId > 0){
            $legacyPath = '/panel/api/inbounds/updateClient/' . rawurlencode($clientId);
            $settingsJson = json_encode(['clients' => [$inboundClient]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $formResult = xuiApiRequestForm($server, 'POST', $legacyPath, [
                'id' => (string)$inboundId,
                'settings' => $settingsJson,
            ]);
            $attempts[] = xuiApiAttemptDiag($legacyPath, $formResult);
            $last = $formResult;

            if(!empty($formResult['success'])){
                return [
                    'ok' => true,
                    'method' => 'updateClient_absolute_form',
                    'attempts' => $attempts,
                ];
            }

            $jsonResult = xuiApiRequest($server, 'POST', $legacyPath, [
                'id' => $inboundId,
                'settings' => $settingsJson,
            ]);
            $attempts[] = xuiApiAttemptDiag($legacyPath . ' (json)', $jsonResult);
            $last = $jsonResult;

            if(!empty($jsonResult['success'])){
                return [
                    'ok' => true,
                    'method' => 'updateClient_absolute_json',
                    'attempts' => $attempts,
                ];
            }
        }
        elseif($email === ''){
            $last = ['success' => false, 'msg' => 'اطلاعات کلاینت برای فعال‌سازی مطلق ناقص است', 'http' => 0];
            $attempts[] = xuiApiAttemptDiag('inbounds/updateClient', $last);
        }

        $code = 'activation_failed';

        if(function_exists('xuiApiFailureKind') && xuiApiFailureKind($last) === 'transport'){
            $code = 'transport_error';
        }

        return [
            'ok' => false,
            'error' => 'فعال‌سازی مطلق پلن در 3x-ui ناموفق بود | ' . xuiApplyRenewDiagSummary(['attempts' => $attempts]),
            'code' => $code,
            'attempts' => $attempts,
        ];
    }

    function xuiRenewClientSnapshot($client){
        if(!is_array($client)){
            return [
                'total_bytes' => 0,
                'used_bytes' => 0,
                'expiry_ms' => 0,
                'email' => '',
                'sub_id' => '',
            ];
        }

        return [
            'total_bytes' => xuiClientTotalBytes($client),
            'used_bytes' => xuiClientUsedBytes($client),
            'expiry_ms' => xuiClientExpiryMs($client),
            'email' => trim((string)($client['email'] ?? '')),
            'sub_id' => trim((string)($client['subId'] ?? '')),
        ];
    }

    function xuiRenewVerifyIncrementalAdjust(array $baseline, $client, $addGb, $addDays, $anchorMs = null){
        $snap = xuiRenewClientSnapshot($client);
        $expectedMinTotal = intval($baseline['total_bytes'] ?? 0) + xuiGbToBytes($addGb);
        $addDays = max(0, intval($addDays));

        if($snap['total_bytes'] + 1048576 < $expectedMinTotal){
            return false;
        }

        if($addDays <= 0){
            return true;
        }

        $baseExpiry = intval($baseline['expiry_ms'] ?? 0);
        $newExpiry = intval($snap['expiry_ms'] ?? 0);
        $toleranceMs = 180000;

        if($newExpiry <= 0){
            return false;
        }

        if($anchorMs !== null && intval($anchorMs) > 0){
            $expectedFromAnchor = intval($anchorMs) + ($addDays * 86400000);

            if($baseExpiry <= 0){
                return abs($newExpiry - $expectedFromAnchor) <= $toleranceMs;
            }
        }

        if($baseExpiry > 0){
            $expectedExtended = $baseExpiry + ($addDays * 86400000);

            return $newExpiry + $toleranceMs >= $expectedExtended;
        }

        return false;
    }

    function xuiRenewVerifyAbsolutePlan($client, $gb, $days, $anchorMs = null){
        $snap = xuiRenewClientSnapshot($client);
        $targetBytes = xuiGbToBytes($gb);
        $tolerance = 16 * 1024 * 1024;

        if(abs($snap['total_bytes'] - $targetBytes) > $tolerance){
            return false;
        }

        $days = max(0, intval($days));

        if($days <= 0){
            return intval($snap['expiry_ms'] ?? 0) === 0;
        }

        if($anchorMs === null){
            $anchorMs = function_exists('renewReserveNowMs') ? renewReserveNowMs() : (int)(microtime(true) * 1000);
        }

        $expectedExpiry = $anchorMs + ($days * 86400000);
        $actualExpiry = intval($snap['expiry_ms'] ?? 0);

        if($actualExpiry <= 0){
            return false;
        }

        return abs($actualExpiry - $expectedExpiry) <= 180000;
    }

    function xuiRenewVerifyRecreateClient($client, $gb, $days, $subIdWanted, $anchorMs = null){
        $snap = xuiRenewClientSnapshot($client);

        if($subIdWanted !== '' && $snap['sub_id'] !== '' && $snap['sub_id'] !== $subIdWanted){
            return false;
        }

        if($anchorMs === null){
            $anchorMs = function_exists('renewReserveNowMs') ? renewReserveNowMs() : (int)(microtime(true) * 1000);
        }

        return xuiRenewVerifyAbsolutePlan($client, $gb, $days, $anchorMs);
    }

    function xuiRenewLog($event, array $context = []){
        $safe = [];

        foreach($context as $key => $value){
            $k = strtolower((string)$key);

            if(strpos($k, 'pass') !== false || strpos($k, 'token') !== false || strpos($k, 'secret') !== false){
                continue;
            }

            $safe[$key] = is_scalar($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        error_log('xui_renew_recreate ' . $event . ' ' . json_encode($safe, JSON_UNESCAPED_UNICODE));
    }

    function xuiRenewUserMessageRenewed(){
        return 'اشتراک شما با موفقیت تمدید شد.';
    }

    function xuiRenewUserMessageRecreated(){
        return 'اشتراک شما مطابق پلن انتخابی مجدداً ساخته و فعال شد.';
    }

    function xuiLocksDir(){
        $override = getenv('PNV_LOCKS_DIR');

        if(is_string($override) && trim($override) !== ''){
            $dir = trim($override);
        }
        else{
            $dir = __DIR__ . '/db/locks';
        }

        if(!is_dir($dir)){
            @mkdir($dir, 0755, true);
        }

        return $dir;
    }

    function xuiApiFailureKind($result){
        if(!is_array($result)){
            return 'transport';
        }

        if(!empty($result['success'])){
            return 'ok';
        }

        $msg = function_exists('mb_strtolower') ? mb_strtolower((string)($result['msg'] ?? '')) : strtolower((string)($result['msg'] ?? ''));

        if(
            strpos($msg, 'خطا در ارتباط') !== false
            || strpos($msg, 'timeout') !== false
            || strpos($msg, 'timed out') !== false
            || strpos($msg, 'پاسخ نامعتبر') !== false
        ){
            return 'transport';
        }

        return 'api';
    }

    function xuiApiSubIdConflict($result){
        if(!is_array($result)){
            return false;
        }

        $msg = function_exists('mb_strtolower') ? mb_strtolower((string)($result['msg'] ?? '')) : strtolower((string)($result['msg'] ?? ''));

        return (
            strpos($msg, 'sub') !== false
            || strpos($msg, 'subid') !== false
            || strpos($msg, 'تکراری') !== false
            || strpos($msg, 'duplicate') !== false
            || strpos($msg, 'exists') !== false
        );
    }

    function xuiRenewLinksSameServer($linkA, $linkB){
        $parsedA = xuiParseSubLink(trim((string)$linkA));
        $parsedB = xuiParseSubLink(trim((string)$linkB));

        if(!is_array($parsedA) || !is_array($parsedB)){
            return false;
        }

        $hostA = strtolower(trim((string)($parsedA['host'] ?? '')));
        $hostB = strtolower(trim((string)($parsedB['host'] ?? '')));

        return $hostA !== '' && $hostA === $hostB;
    }

    function xuiPaymentRowProvisionKey(array $row){
        $user = strtolower(trim((string)($row[0] ?? '')));
        $tracking = trim((string)($row[3] ?? ''));
        $created = intval($row[8] ?? 0);
        $amount = intval($row[12] ?? 0);
        $type = trim((string)($row[9] ?? ''));

        return hash('sha256', $user . '|' . $tracking . '|' . $created . '|' . $amount . '|' . $type);
    }

    function xuiPaymentRowTransactionId(array $row){
        return xuiPaymentRowProvisionKey($row);
    }

    function xuiRenewProvisionStorePath($key){
        return xuiLocksDir() . '/renew_provision_' . preg_replace('/[^a-f0-9]/', '', (string)$key) . '.json';
    }

    function xuiRenewProvisionStoreRead($key){
        $path = xuiRenewProvisionStorePath($key);

        if(!is_file($path)){
            return null;
        }

        $data = json_decode((string)file_get_contents($path), true);

        return is_array($data) ? $data : null;
    }

    function xuiRenewProvisionStorePatch($key, array $patch){
        $existing = xuiRenewProvisionStoreRead($key);

        if(!is_array($existing)){
            $existing = [];
        }

        return xuiRenewProvisionStoreWrite($key, array_merge($existing, $patch));
    }

    function xuiRenewProvisionStoreWrite($key, array $payload){
        if(
            !empty($GLOBALS['xuiTestRenewProvisionStoreWrite'])
            && is_callable($GLOBALS['xuiTestRenewProvisionStoreWrite'])
        ){
            $decision = $GLOBALS['xuiTestRenewProvisionStoreWrite']($key, $payload);

            if($decision === false){
                return false;
            }
        }

        $path = xuiRenewProvisionStorePath($key);
        $dir = dirname($path);

        if(!is_dir($dir)){
            @mkdir($dir, 0755, true);
        }

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        if($json === false){
            return false;
        }

        $tmp = $path . '.tmp.' . getmypid();
        $written = file_put_contents($tmp, $json, LOCK_EX);

        if($written === false || $written < strlen($json)){
            @unlink($tmp);

            return false;
        }

        if(!@rename($tmp, $path)){
            @unlink($tmp);

            return false;
        }

        return true;
    }

    function xuiRenewProvisionStoreMarkComplete($paymentRow, array $result){
        $provisionKey = xuiPaymentRowProvisionKey($paymentRow);
        $existing = xuiRenewProvisionStoreRead($provisionKey) ?? [];

        return xuiRenewProvisionStoreWrite($provisionKey, array_merge($existing, [
            'state' => 'processing',
            'phase' => 'provision_complete',
            'result' => $result,
            'provision_completed_at' => time(),
            'transaction_id' => xuiPaymentRowTransactionId($paymentRow),
        ]));
    }

    function xuiRenewRecoverProcessingRenew(array $paymentRow, array $stored){
        $startedAt = intval($stored['started_at'] ?? 0);
        $phase = trim((string)($stored['phase'] ?? ''));
        $operation = trim((string)($stored['operation'] ?? ''));

        if($startedAt > 0 && (time() - $startedAt) < 90 && empty($stored['result'])){
            return [
                'action' => 'in_progress',
                'error' => 'فعال‌سازی همین پرداخت در حال انجام است؛ چند لحظه بعد دوباره تلاش کنید',
                'code' => 'provision_in_progress',
            ];
        }

        if(!function_exists('renewReserveFindByProvisionKey')){
            require_once __DIR__ . '/renew_reserve_lib.php';
        }

        $provisionKey = xuiPaymentRowProvisionKey($paymentRow);
        $planText = trim((string)($paymentRow[2] ?? ''));
        $gb = intval($stored['plan_gb'] ?? 0);

        if($gb <= 0){
            $gb = xuiParsePlanGb($planText);
        }

        $days = intval($stored['plan_days'] ?? 0);

        if($days <= 0 && function_exists('xuiResolveRenewPlanDays')){
            $duration = xuiResolveRenewPlanDays($planText);
            $days = !empty($duration['ok']) ? intval($duration['days'] ?? 0) : 0;
        }

        $subLink = xuiResolveRenewPaymentRowLink($paymentRow);

        if($subLink === ''){
            $subLink = trim((string)($paymentRow[1] ?? ''));
        }

        $parsed = xuiParseSubLink($subLink);
        $config = xuiLoadConfig();

        if(
            $phase === 'renew_reserve_started'
            || $operation === 'reserve'
            || ($phase === 'provision_complete' && ($stored['result']['renew_outcome'] ?? '') === 'reserved')
        ){
            $reservation = renewReserveFindByProvisionKey($provisionKey);

            if(is_array($reservation) && trim((string)($reservation['id'] ?? '')) !== ''){
                $link = xuiBuildSubLink($parsed['host'] ?? '', $parsed['sub_id'] ?? ($reservation['sub_id'] ?? ''), $config);

                return [
                    'action' => 'finalize',
                    'result' => renewReserveBuildPaymentSuccess($reservation, $link),
                ];
            }

            return [
                'action' => 'ambiguous',
                'error' => 'رزرو ثبت‌شده برای این پرداخت پیدا نشد',
                'code' => 'ambiguous_provision',
            ];
        }

        if(!$parsed || $gb <= 0){
            return [
                'action' => 'ambiguous',
                'error' => 'بازیابی تمدید ناموفق: اطلاعات پلن یا لینک ناقص است',
                'code' => 'ambiguous_provision',
            ];
        }

        $server = xuiFindServerByHost($parsed['host'], $config);

        if(!$server){
            return [
                'action' => 'ambiguous',
                'error' => 'بازیابی تمدید ناموفق: سرور پیدا نشد',
                'code' => 'ambiguous_provision',
            ];
        }

        $lookup = xuiRenewLookupClient($server, $parsed['sub_id'], $subLink);

        if(($lookup['status'] ?? '') === 'api_error'){
            return [
                'action' => 'ambiguous',
                'error' => 'اتصال به سرور برای بررسی وضعیت تمدید برقرار نیست',
                'code' => 'ambiguous_provision',
            ];
        }

        if(($lookup['status'] ?? '') === 'not_found'){
            return [
                'action' => 'ambiguous',
                'error' => 'کلاینت تمدید پیدا نشد؛ وضعیت عملیات نامشخص است',
                'code' => 'ambiguous_provision',
            ];
        }

        if(($lookup['status'] ?? '') !== 'found' || !is_array($lookup['client'] ?? null)){
            return [
                'action' => 'ambiguous',
                'error' => 'وضعیت فعال‌سازی تمدید نامشخص است',
                'code' => 'ambiguous_provision',
            ];
        }

        $client = $lookup['client'];
        $email = trim((string)($client['email'] ?? ''));

        if($email === ''){
            return [
                'action' => 'ambiguous',
                'error' => 'کلاینت بدون email؛ نیاز به بررسی دستی',
                'code' => 'ambiguous_provision',
            ];
        }

        $baseline = is_array($stored['baseline'] ?? null) ? $stored['baseline'] : null;
        $verified = false;
        $outcome = 'renewed';
        $method = 'recover_processing_renew';

        if($operation === 'incremental' || $phase === 'renew_adjust_started'){
            if(!is_array($baseline)){
                return [
                    'action' => 'ambiguous',
                    'error' => 'وضعیت پایه قبل از تمدید ذخیره نشده است',
                    'code' => 'ambiguous_provision',
                ];
            }

            $anchorMs = intval($stored['activation_anchor_ms'] ?? 0);
            $verified = xuiRenewVerifyIncrementalAdjust(
                $baseline,
                $client,
                $gb,
                $days,
                $anchorMs > 0 ? $anchorMs : null
            );
            $method = 'recover_incremental';
        }
        elseif($operation === 'absolute' || $phase === 'renew_absolute_started'){
            $anchorMs = intval($stored['activation_anchor_ms'] ?? 0);
            $verified = xuiRenewVerifyAbsolutePlan($client, $gb, $days, $anchorMs > 0 ? $anchorMs : null);
            $method = 'recover_absolute';
        }
        elseif($operation === 'recreate' || $phase === 'renew_recreate_started'){
            $anchorMs = intval($stored['activation_anchor_ms'] ?? 0);
            $subWanted = trim((string)($parsed['sub_id'] ?? ''));

            if($subWanted === ''){
                $subWanted = trim((string)($stored['target_sub_id'] ?? ''));
            }

            $verified = xuiRenewVerifyRecreateClient(
                $client,
                $gb,
                $days,
                $subWanted,
                $anchorMs > 0 ? $anchorMs : null
            );
            $outcome = 'recreated';
            $method = 'recover_recreate';
        }
        else{
            return [
                'action' => 'ambiguous',
                'error' => 'مرحله عملیات تمدید برای بازیابی مشخص نیست',
                'code' => 'ambiguous_provision',
            ];
        }

        if(!$verified){
            return [
                'action' => 'ambiguous',
                'error' => 'شواهد کافی برای اثبات نتیجه تمدید وجود ندارد',
                'code' => 'ambiguous_provision',
            ];
        }

        $finalSubId = trim((string)($parsed['sub_id'] ?? ''));

        if($outcome === 'recreated' && $finalSubId === ''){
            $finalSubId = trim((string)($client['subId'] ?? ''));
        }

        $userMessage = $outcome === 'recreated'
            ? xuiRenewUserMessageRecreated()
            : xuiRenewUserMessageRenewed();

        return [
            'action' => 'finalize',
            'result' => [
                'ok' => true,
                'link' => xuiBuildSubLink($parsed['host'], $finalSubId, $config),
                'email' => $email,
                'sub_id' => $finalSubId,
                'server_id' => $server['id'] ?? '',
                'gb' => $gb,
                'renew_outcome' => $outcome,
                'user_renew_message' => $userMessage,
                'method' => $method,
            ],
        ];
    }

    /**
     * CSV «تایید شد» ولی provision ناقص/نامشخص — بدون اعمال تکراری روی 3x-ui در صورت امکان.
     *
     * @return array|null null = ادامه مسیر provision عادی
     */
    function xuiRenewResolveAlreadyConfirmedRenew($index, array $row, $provisionKey){
        $type = trim((string)($row[9] ?? ''));

        if($type !== 'تمدید'){
            return [
                'ok' => true,
                'already' => true,
                'link' => $row[7] ?? '',
            ];
        }

        if(!function_exists('renewReserveFindByProvisionKey')){
            require_once __DIR__ . '/renew_reserve_lib.php';
        }

        $stored = xuiRenewProvisionStoreRead($provisionKey);

        if(is_array($stored) && ($stored['state'] ?? '') === 'done' && is_array($stored['result'] ?? null)){
            return array_merge($stored['result'], [
                'ok' => true,
                'already' => true,
            ]);
        }

        if(is_array($stored) && ($stored['state'] ?? '') === 'processing'){
            if(is_array($stored['result'] ?? null)){
                return xuiRenewApproveFinalizeFromResult($index, $row, $provisionKey, $stored['result'], true);
            }

            $recover = xuiRenewRecoverProcessingRenew($row, $stored);

            if(($recover['action'] ?? '') === 'finalize'){
                return xuiRenewApproveFinalizeFromResult($index, $row, $provisionKey, $recover['result'], true);
            }

            if(($recover['action'] ?? '') === 'in_progress'){
                return [
                    'ok' => false,
                    'error' => $recover['error'] ?? 'فعال‌سازی در حال انجام است',
                    'code' => $recover['code'] ?? 'provision_in_progress',
                ];
            }

            if(($recover['action'] ?? '') === 'ambiguous'){
                return [
                    'ok' => false,
                    'error' => $recover['error'] ?? 'وضعیت فعال‌سازی نامشخص است',
                    'code' => 'ambiguous_provision',
                    'recoverable' => true,
                ];
            }
        }

        $reservation = renewReserveFindByProvisionKey($provisionKey);

        if(is_array($reservation)){
            $subLink = xuiResolveRenewPaymentRowLink($row);

            if($subLink === ''){
                $subLink = trim((string)($row[7] ?? ''));
            }

            return array_merge(renewReserveBuildPaymentSuccess($reservation, $subLink), [
                'ok' => true,
                'already' => true,
            ]);
        }

        if(is_array($stored) && in_array($stored['state'] ?? '', ['failed', 'ambiguous_provision'], true)){
            return null;
        }

        if(!is_array($stored) || trim((string)($stored['state'] ?? '')) === ''){
            return null;
        }

        return [
            'ok' => true,
            'already' => true,
            'link' => $row[7] ?? '',
        ];
    }

    function xuiRenewApproveFinalizeFromResult($index, array $row, $provisionKey, array $result, $already = true){
        $payments = xuiLoadPayments();

        if(!isset($payments[$index])){
            return ['ok' => false, 'error' => 'پرداخت پیدا نشد'];
        }

        $existingStore = xuiRenewProvisionStoreRead($provisionKey);

        if(!is_array($existingStore)){
            $existingStore = [];
        }

        if(!xuiRenewProvisionStoreWrite($provisionKey, array_merge($existingStore, [
            'state' => 'done',
            'done_at' => time(),
            'result' => $result,
            'transaction_id' => xuiPaymentRowTransactionId($row),
        ]))){
            return [
                'ok' => false,
                'error' => 'ذخیره نتیجه نهایی provision ناموفق بود',
                'code' => 'persist_failed',
                'recoverable' => true,
                'link' => $result['link'] ?? '',
            ];
        }

        if(trim((string)($payments[$index][6] ?? '')) === 'تایید شد'){
            return array_merge($result, ['ok' => true, 'already' => true]);
        }

        $payments[$index][6] = 'تایید شد';
        $payments[$index][7] = $result['link'] ?? '';

        if(!xuiSavePayments($payments)){
            return [
                'ok' => false,
                'error' => 'ذخیره وضعیت پرداخت در فایل ناموفق بود',
                'code' => 'persist_failed',
                'recoverable' => true,
                'link' => $result['link'] ?? '',
            ];
        }

        return array_merge($result, ['ok' => true, 'already' => $already]);
    }

    function xuiRenewApiProbe($server){
        if(!empty($GLOBALS['xuiTestRenewApiProbe']) && is_callable($GLOBALS['xuiTestRenewApiProbe'])){
            return $GLOBALS['xuiTestRenewApiProbe']($server);
        }

        if(!is_array($server)){
            return ['ok' => false, 'reason' => 'api_error', 'error' => 'سرور نامعتبر است'];
        }

        $probe = xuiApiRequest($server, 'GET', '/panel/api/inbounds/options');

        if(empty($probe['success'])){
            return [
                'ok' => false,
                'reason' => 'api_error',
                'error' => trim((string)($probe['msg'] ?? 'اتصال به 3x-ui برقرار نیست')),
            ];
        }

        return ['ok' => true];
    }

    function xuiRenewLookupClient($server, $subId, $subLink = ''){
        if(!empty($GLOBALS['xuiTestRenewLookupClient']) && is_callable($GLOBALS['xuiTestRenewLookupClient'])){
            return $GLOBALS['xuiTestRenewLookupClient']($server, $subId, $subLink);
        }

        $probe = xuiRenewApiProbe($server);

        if(empty($probe['ok'])){
            return [
                'status' => 'api_error',
                'error' => $probe['error'] ?? 'اتصال به 3x-ui برقرار نیست',
            ];
        }

        $client = xuiFindClientBySubId($server, $subId, $subLink);

        if($client){
            return ['status' => 'found', 'client' => $client];
        }

        return ['status' => 'not_found'];
    }

    function xuiRenewSubIdFromLink($link){
        $parsed = xuiParseSubLink(trim((string)$link));

        return is_array($parsed) ? trim((string)($parsed['sub_id'] ?? '')) : '';
    }

    function xuiRenewSubOwnedByUser($username, $subLink){
        $username = trim((string)$username);
        $subLink = trim((string)$subLink);
        $targetSubId = xuiRenewSubIdFromLink($subLink);

        if($username === '' || ($subLink === '' && $targetSubId === '')){
            return false;
        }

        if(!function_exists('pnvLoadUserActiveSubscriptions') && is_file(__DIR__ . '/subscription_lib.php')){
            require_once __DIR__ . '/subscription_lib.php';
        }

        if(!function_exists('pnvNormalizeSubLinkValue')){
            return false;
        }

        $targetNorm = strtolower(pnvNormalizeSubLinkValue($subLink));

        if(function_exists('pnvLoadUserActiveSubscriptions')){
            foreach(pnvLoadUserActiveSubscriptions($username, false) as $entry){
                $link = strtolower(pnvNormalizeSubLinkValue((string)($entry['link'] ?? '')));

                if($link !== '' && $link === $targetNorm){
                    return true;
                }

                if(
                    $targetSubId !== ''
                    && xuiRenewSubIdFromLink((string)($entry['link'] ?? '')) === $targetSubId
                    && xuiRenewLinksSameServer((string)($entry['link'] ?? ''), $subLink)
                ){
                    return true;
                }
            }
        }

        $file = xuiPaymentsPath();

        if(!is_file($file)){
            return false;
        }

        $handle = fopen($file, 'r');

        while(($data = fgetcsv($handle)) !== false){
            if(!function_exists('pnvPaymentUsernameMatches') || !pnvPaymentUsernameMatches($data[0] ?? '', $username)){
                continue;
            }

            if(trim((string)($data[6] ?? '')) !== 'تایید شد'){
                continue;
            }

            foreach([1, 7] as $col){
                $candidate = pnvNormalizeSubLinkValue(trim((string)($data[$col] ?? '')));

                if($candidate === ''){
                    continue;
                }

                if(strtolower($candidate) === $targetNorm){
                    fclose($handle);
                    return true;
                }

                if(
                    $targetSubId !== ''
                    && xuiRenewSubIdFromLink($candidate) === $targetSubId
                    && xuiRenewLinksSameServer($candidate, $subLink)
                ){
                    fclose($handle);
                    return true;
                }
            }
        }

        fclose($handle);

        return false;
    }

    function xuiResolveRenewPaymentRowLink(array $paymentRow){
        $candidates = [
            trim((string)($paymentRow[1] ?? '')),
            trim((string)($paymentRow[7] ?? '')),
        ];

        if(!function_exists('instantPayFindJsonByTracking') && is_file(__DIR__ . '/instant_pay_lib.php')){
            require_once __DIR__ . '/instant_pay_lib.php';
        }

        $tracking = trim((string)($paymentRow[3] ?? ''));

        if(strpos($tracking, 'AUTO-') === 0 && function_exists('instantPayFindJsonByTracking')){
            $item = instantPayFindJsonByTracking(trim((string)($paymentRow[0] ?? '')), $tracking);

            if(is_array($item)){
                $candidates[] = trim((string)($item['sub'] ?? ''));
                $candidates[] = trim((string)($item['link'] ?? ''));
            }
        }

        if(!function_exists('pnvNormalizeSubLinkValue') && is_file(__DIR__ . '/subscription_lib.php')){
            require_once __DIR__ . '/subscription_lib.php';
        }

        foreach($candidates as $candidate){
            $candidate = trim((string)$candidate);

            if($candidate === ''){
                continue;
            }

            if(function_exists('pnvNormalizeSubLinkValue')){
                $normalized = trim((string)pnvNormalizeSubLinkValue($candidate));

                if($normalized !== ''){
                    $candidate = $normalized;
                }
            }

            $parsed = xuiParseSubLink($candidate);

            if(is_array($parsed)){
                return xuiBuildSubLink($parsed['host'], $parsed['sub_id'], xuiLoadConfig());
            }
        }

        return '';
    }

    function xuiProvisionRenewRecreate($server, array $parsed, array $paymentRow, $gb, $days, $config){
        $username = trim((string)($paymentRow[0] ?? ''));
        $subIdWanted = trim((string)($parsed['sub_id'] ?? ''));
        $subLink = xuiBuildSubLink($parsed['host'], $subIdWanted, $config);
        $provisionKey = xuiPaymentRowProvisionKey($paymentRow);
        $stored = xuiRenewProvisionStoreRead($provisionKey) ?? [];
        $anchorMs = intval($stored['activation_anchor_ms'] ?? 0);

        if($anchorMs <= 0){
            $anchorMs = function_exists('renewReserveNowMs') ? renewReserveNowMs() : (int)(microtime(true) * 1000);
        }

        if(!xuiRenewSubOwnedByUser($username, $subLink)){
            return [
                'ok' => false,
                'error' => 'این اشتراک به حساب کاربر تعلق ندارد و قابل بازیابی نیست',
                'code' => 'ownership_denied',
            ];
        }

        $lookup = xuiRenewLookupClient($server, $subIdWanted, $subLink);

        if(($lookup['status'] ?? '') === 'api_error'){
            return [
                'ok' => false,
                'error' => $lookup['error'] ?? 'اتصال به 3x-ui برقرار نیست',
                'code' => 'api_error',
            ];
        }

        if(($lookup['status'] ?? '') === 'found' && is_array($lookup['client'] ?? null)){
            $client = $lookup['client'];
            $email = trim((string)($client['email'] ?? ''));

            if($email === ''){
                return ['ok' => false, 'error' => 'ایمیل کلاینت خالی است', 'code' => 'client_email_missing'];
            }

            if(xuiRenewVerifyRecreateClient($client, $gb, $days, $subIdWanted, $anchorMs)){
                $idempotentResult = [
                    'ok' => true,
                    'link' => xuiBuildSubLink($parsed['host'], $subIdWanted, $config),
                    'email' => $email,
                    'sub_id' => $subIdWanted,
                    'server_id' => $server['id'] ?? '',
                    'gb' => $gb,
                    'renew_outcome' => 'recreated',
                    'user_renew_message' => xuiRenewUserMessageRecreated(),
                    'method' => 'recreate_idempotent',
                ];

                if(!xuiRenewProvisionStoreMarkComplete($paymentRow, $idempotentResult)){
                    return [
                        'ok' => false,
                        'error' => 'اشتراک تأیید شد اما ذخیره نتیجه ناموفق بود',
                        'code' => 'persist_failed',
                        'recoverable' => true,
                        'link' => $idempotentResult['link'],
                    ];
                }

                return $idempotentResult;
            }

            return [
                'ok' => false,
                'error' => 'کلاینت موجود با پلن هدف تطابق قابل اثبات ندارد؛ برای جلوگیری از گزارش نادرست، بازیابی متوقف شد',
                'code' => 'ambiguous_provision',
            ];
        }

        if(trim((string)($stored['operation'] ?? '')) !== 'recreate'){
            if(!xuiRenewProvisionStoreWrite($provisionKey, array_merge($stored, [
                'state' => 'processing',
                'phase' => 'renew_recreate_started',
                'operation' => 'recreate',
                'started_at' => intval($stored['started_at'] ?? time()),
                'transaction_id' => xuiPaymentRowTransactionId($paymentRow),
                'plan_gb' => $gb,
                'plan_days' => $days,
                'target_sub_id' => $subIdWanted,
                'activation_anchor_ms' => $anchorMs,
            ]))){
                return [
                    'ok' => false,
                    'error' => 'ذخیره وضعیت قبل از ساخت مجدد ناموفق بود',
                    'code' => 'persist_failed',
                    'recoverable' => true,
                ];
            }
        }

        $configName = $username !== '' ? $username : ('user' . xuiGenerateSubId(6));
        $mobile = xuiGetUserMobile($username);
        $email = xuiBuildClientEmail($configName, $mobile);
        $exists = xuiApiRequest($server, 'GET', '/panel/api/clients/get/' . rawurlencode($email));

        if(!empty($exists['success'])){
            $email .= '_' . substr(xuiGenerateSubId(6), 0, 4);
        }

        $created = xuiCreateClientForRenewPlan($server, $email, $gb, $days, $subIdWanted, $anchorMs);
        $subIdPreserved = $subIdWanted !== '';

        if(
            empty($created['ok'])
            && $subIdWanted !== ''
            && ($created['code'] ?? '') === 'sub_id_conflict'
        ){
            xuiRenewLog('recreate_subid_retry', [
                'user' => $username,
                'sub_id' => $subIdWanted,
                'server_id' => $server['id'] ?? '',
            ]);
            $created = xuiCreateClientForRenewPlan($server, $email, $gb, $days, '', $anchorMs);
            $subIdPreserved = false;
        }

        if(empty($created['ok'])){
            xuiRenewLog('recreate_failed', [
                'user' => $username,
                'sub_id' => $subIdWanted,
                'server_id' => $server['id'] ?? '',
                'error' => $created['error'] ?? '',
            ]);

            return $created;
        }

        $finalSubId = trim((string)($created['sub_id'] ?? $subIdWanted));
        $link = xuiBuildSubLink($parsed['host'], $finalSubId, $config);

        xuiRenewLog('recreate_ok', [
            'user' => $username,
            'sub_id' => $finalSubId,
            'server_id' => $server['id'] ?? '',
            'sub_id_preserved' => $subIdPreserved ? '1' : '0',
        ]);

        $recreateResult = [
            'ok' => true,
            'link' => $link,
            'email' => $created['email'] ?? $email,
            'sub_id' => $finalSubId,
            'server_id' => $server['id'] ?? '',
            'gb' => $gb,
            'renew_outcome' => 'recreated',
            'user_renew_message' => xuiRenewUserMessageRecreated(),
            'sub_id_preserved' => $subIdPreserved,
            'method' => 'recreate',
        ];

        if(!xuiRenewProvisionStoreMarkComplete($paymentRow, $recreateResult)){
            return [
                'ok' => false,
                'error' => 'ساخت اشتراک انجام شد اما ذخیره نتیجه ناموفق بود',
                'code' => 'persist_failed',
                'recoverable' => true,
                'link' => $link,
            ];
        }

        return $recreateResult;
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

        if(!is_array($paymentRow)){
            return ['ok' => false, 'error' => 'رد پرداخت نامعتبر است'];
        }

        $planText = trim((string)($paymentRow[2] ?? ''));
        $gb = xuiParsePlanGb($planText);
        $duration = xuiResolveRenewPlanDays($planText);

        if($gb <= 0){
            return ['ok' => false, 'error' => 'حجم پلن قابل تشخیص نیست: ' . $planText];
        }

        if(empty($duration['ok'])){
            return [
                'ok' => false,
                'error' => $duration['error'] ?? 'مدت پلن قابل تشخیص نیست',
                'code' => $duration['code'] ?? 'invalid_plan_duration',
            ];
        }

        $days = intval($duration['days'] ?? 0);

        $subLink = xuiResolveRenewPaymentRowLink($paymentRow);

        if($subLink === ''){
            $subLink = trim((string)($paymentRow[1] ?? ''));
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
            return ['ok' => false, 'error' => 'این سرور برای تمدید مجاز نیست', 'code' => 'server_not_allowed'];
        }

        $username = trim((string)($paymentRow[0] ?? ''));

        if(!xuiRenewSubOwnedByUser($username, $subLink)){
            return [
                'ok' => false,
                'error' => 'این اشتراک به حساب کاربر تعلق ندارد',
                'code' => 'ownership_denied',
            ];
        }

        $lookup = xuiRenewLookupClient($server, $parsed['sub_id'], $subLink);

        if(($lookup['status'] ?? '') === 'api_error'){
            return [
                'ok' => false,
                'error' => $lookup['error'] ?? 'اتصال به 3x-ui برقرار نیست',
                'code' => 'api_error',
            ];
        }

        if(($lookup['status'] ?? '') === 'found' && is_array($lookup['client'] ?? null)){
            if(!function_exists('renewReserveDecideAction')){
                require_once __DIR__ . '/renew_reserve_lib.php';
            }

            $client = $lookup['client'];
            $email = trim((string)($client['email'] ?? ''));

            if($email === ''){
                return ['ok' => false, 'error' => 'ایمیل کلاینت خالی است'];
            }

            $runtime = xuiRenewAnalyzeRuntimeState($server, $client, $subLink);
            $action = renewReserveDecideAction($runtime);

            if(empty($duration['time_unlimited']) && $days > 0 && !empty($runtime['time_expired'])){
                $action = 'activate_now';
            }

            if($action === 'ambiguous'){
                return [
                    'ok' => false,
                    'error' => 'وضعیت واقعی اشتراک (زمان/حجم) نامشخص است؛ فعال‌سازی خودکار انجام نشد',
                    'code' => 'ambiguous_provision',
                ];
            }

            $link = xuiBuildSubLink($parsed['host'], $parsed['sub_id'], $config);
            $provisionKey = xuiPaymentRowProvisionKey($paymentRow);

            if($action === 'reserve'){
                $existingReserve = renewReserveFindByProvisionKey($provisionKey);

                if(is_array($existingReserve)){
                    return renewReserveBuildPaymentSuccess($existingReserve, $link);
                }

                $stored = xuiRenewProvisionStoreRead($provisionKey) ?? [];

                if(!xuiRenewProvisionStoreWrite($provisionKey, array_merge($stored, [
                    'state' => 'processing',
                    'phase' => 'renew_reserve_started',
                    'operation' => 'reserve',
                    'started_at' => intval($stored['started_at'] ?? time()),
                    'transaction_id' => xuiPaymentRowTransactionId($paymentRow),
                    'baseline' => xuiRenewClientSnapshot($client),
                    'plan_gb' => $gb,
                    'plan_days' => $days,
                ]))){
                    return [
                        'ok' => false,
                        'error' => 'ذخیره وضعیت رزرو ناموفق بود',
                        'code' => 'persist_failed',
                        'recoverable' => true,
                    ];
                }

                $reservation = renewReserveCreateFromPayment($paymentRow, $parsed, $server, $gb, $days, $planText);

                if(!is_array($reservation)){
                    return [
                        'ok' => false,
                        'error' => 'ثبت رزرو در صف ناموفق بود',
                        'code' => 'persist_failed',
                        'recoverable' => true,
                    ];
                }

                $renewResult = renewReserveBuildPaymentSuccess($reservation, $link);

                if(!xuiRenewProvisionStoreMarkComplete($paymentRow, $renewResult)){
                    return [
                        'ok' => false,
                        'error' => 'رزرو ثبت شد اما ذخیره نتیجه نهایی ناموفق بود',
                        'code' => 'persist_failed',
                        'recoverable' => true,
                        'renew_outcome' => 'reserved',
                    ];
                }

                return $renewResult;
            }

            if($action === 'immediate_unlimited'){
                $stored = xuiRenewProvisionStoreRead($provisionKey) ?? [];
                $anchorMs = intval($stored['activation_anchor_ms'] ?? 0);

                if($anchorMs <= 0){
                    $anchorMs = function_exists('renewReserveNowMs') ? renewReserveNowMs() : (int)(microtime(true) * 1000);
                }

                if(!xuiRenewProvisionStoreWrite($provisionKey, array_merge($stored, [
                    'state' => 'processing',
                    'phase' => 'renew_unlimited_add_started',
                    'operation' => 'incremental',
                    'started_at' => intval($stored['started_at'] ?? time()),
                    'transaction_id' => xuiPaymentRowTransactionId($paymentRow),
                    'baseline' => xuiRenewClientSnapshot($client),
                    'plan_gb' => $gb,
                    'plan_days' => 0,
                    'activation_anchor_ms' => $anchorMs,
                ]))){
                    return [
                        'ok' => false,
                        'error' => 'ذخیره وضعیت قبل از تمدید نامحدود زمانی ناموفق بود',
                        'code' => 'persist_failed',
                        'recoverable' => true,
                    ];
                }

                if(!empty($runtime['volume_exhausted']) && $days <= 0 && is_array($client)){
                    $adjusted = xuiAdjustClientTrafficLegacy($server, $client, $gb);
                }
                else{
                    $adjusted = xuiAdjustClientTraffic($server, $email, $gb, $client, $days);
                }

                if(empty($adjusted['ok'])){
                    $adjustCode = trim((string)($adjusted['code'] ?? ''));

                    if($adjustCode === 'transport_error' && $days > 0){
                        $verifyLookup = xuiRenewLookupClient($server, $parsed['sub_id'], $link);
                        $baselineSnap = xuiRenewClientSnapshot($client);

                        if(
                            ($verifyLookup['status'] ?? '') === 'found'
                            && is_array($verifyLookup['client'] ?? null)
                            && xuiRenewVerifyIncrementalAdjust($baselineSnap, $verifyLookup['client'], $gb, $days, $anchorMs)
                        ){
                            $adjusted = ['ok' => true, 'method' => 'verify_after_transport'];
                        }
                    }

                    if(empty($adjusted['ok'])){
                        return $adjusted;
                    }
                }

                $verifyLookup = xuiRenewLookupClient($server, $parsed['sub_id'], $link);
                $baselineSnap = xuiRenewClientSnapshot($client);

                if(
                    ($verifyLookup['status'] ?? '') !== 'found'
                    || !is_array($verifyLookup['client'] ?? null)
                    || !xuiRenewVerifyIncrementalAdjust($baselineSnap, $verifyLookup['client'], $gb, $days, $anchorMs)
                ){
                    return [
                        'ok' => false,
                        'error' => 'حجم یا مدت تمدید روی سرور با پلن خریداری‌شده تطابق ندارد',
                        'code' => 'ambiguous_provision',
                    ];
                }

                $renewResult = [
                    'ok' => true,
                    'link' => $link,
                    'email' => $email,
                    'sub_id' => $parsed['sub_id'],
                    'server_id' => $server['id'] ?? '',
                    'gb' => $gb,
                    'renew_outcome' => 'renewed',
                    'user_renew_message' => xuiRenewUserMessageRenewed(),
                    'method' => $adjusted['method'] ?? 'incremental_unlimited',
                ];

                if(!xuiRenewProvisionStoreMarkComplete($paymentRow, $renewResult)){
                    return [
                        'ok' => false,
                        'error' => 'تمدید انجام شد اما ذخیره نتیجه ناموفق بود',
                        'code' => 'persist_failed',
                        'recoverable' => true,
                        'link' => $link,
                    ];
                }

                return $renewResult;
            }

            if($action === 'activate_now'){
                $stored = xuiRenewProvisionStoreRead($provisionKey) ?? [];
                $anchorMs = intval($stored['activation_anchor_ms'] ?? 0);

                if($anchorMs <= 0){
                    $anchorMs = function_exists('renewReserveNowMs') ? renewReserveNowMs() : (int)(microtime(true) * 1000);
                }

                if(!xuiRenewProvisionStoreWrite($provisionKey, array_merge($stored, [
                    'state' => 'processing',
                    'phase' => 'renew_absolute_started',
                    'operation' => 'absolute',
                    'started_at' => intval($stored['started_at'] ?? time()),
                    'transaction_id' => xuiPaymentRowTransactionId($paymentRow),
                    'baseline' => xuiRenewClientSnapshot($client),
                    'plan_gb' => $gb,
                    'plan_days' => $days,
                    'activation_anchor_ms' => $anchorMs,
                ]))){
                    return [
                        'ok' => false,
                        'error' => 'ذخیره وضعیت قبل از فعال‌سازی ناموفق بود',
                        'code' => 'persist_failed',
                        'recoverable' => true,
                    ];
                }

                $applied = xuiApplyRenewPlanAbsolute($server, $client, $gb, $days, $anchorMs);

                if(empty($applied['ok'])){
                    $failCode = trim((string)($applied['code'] ?? ''));

                    if($failCode === 'transport_error'){
                        $verifyLookup = xuiRenewLookupClient($server, $parsed['sub_id'], $subLink);

                        if(
                            ($verifyLookup['status'] ?? '') === 'found'
                            && is_array($verifyLookup['client'] ?? null)
                            && xuiRenewVerifyAbsolutePlan($verifyLookup['client'], $gb, $days, $anchorMs)
                        ){
                            $applied = ['ok' => true, 'method' => 'verify_after_transport'];
                        }
                        else{
                            return $applied;
                        }
                    }
                    else{
                        return $applied;
                    }
                }

                $verifyLookup = xuiRenewLookupClient($server, $parsed['sub_id'], $subLink);

                if(
                    ($verifyLookup['status'] ?? '') !== 'found'
                    || !is_array($verifyLookup['client'] ?? null)
                    || !xuiRenewVerifyAbsolutePlan($verifyLookup['client'], $gb, $days, $anchorMs)
                ){
                    return [
                        'ok' => false,
                        'error' => 'پس از فعال‌سازی، حجم یا تاریخ انقضا روی 3x-ui با پلن تطابق ندارد',
                        'code' => 'ambiguous_provision',
                    ];
                }

                $renewResult = [
                    'ok' => true,
                    'link' => $link,
                    'email' => $email,
                    'sub_id' => $parsed['sub_id'],
                    'server_id' => $server['id'] ?? '',
                    'gb' => $gb,
                    'renew_outcome' => 'renewed',
                    'user_renew_message' => xuiRenewUserMessageRenewed(),
                    'method' => $applied['method'] ?? 'absolute',
                ];

                if(!xuiRenewProvisionStoreMarkComplete($paymentRow, $renewResult)){
                    return [
                        'ok' => false,
                        'error' => 'فعال‌سازی انجام شد اما ذخیره نتیجه ناموفق بود',
                        'code' => 'persist_failed',
                        'recoverable' => true,
                        'link' => $link,
                    ];
                }

                return $renewResult;
            }

            $stored = xuiRenewProvisionStoreRead($provisionKey) ?? [];
            $anchorMs = intval($stored['activation_anchor_ms'] ?? 0);

            if($anchorMs <= 0){
                $anchorMs = function_exists('renewReserveNowMs') ? renewReserveNowMs() : (int)(microtime(true) * 1000);
            }

            if(!xuiRenewProvisionStoreWrite($provisionKey, array_merge($stored, [
                'state' => 'processing',
                'phase' => 'renew_adjust_started',
                'operation' => 'incremental',
                'started_at' => intval($stored['started_at'] ?? time()),
                'transaction_id' => xuiPaymentRowTransactionId($paymentRow),
                'baseline' => xuiRenewClientSnapshot($client),
                'plan_gb' => $gb,
                'plan_days' => $days,
                'activation_anchor_ms' => $anchorMs,
            ]))){
                return [
                    'ok' => false,
                    'error' => 'ذخیره وضعیت قبل از تمدید ناموفق بود',
                    'code' => 'persist_failed',
                    'recoverable' => true,
                ];
            }

            $adjusted = xuiAdjustClientTraffic($server, $email, $gb, $client, $days);

            if(empty($adjusted['ok'])){
                $adjustCode = trim((string)($adjusted['code'] ?? ''));

                if($adjustCode === 'transport_error' && $days > 0){
                    $verifyLookup = xuiRenewLookupClient($server, $parsed['sub_id'], $link);
                    $baselineSnap = xuiRenewClientSnapshot($client);

                    if(
                        ($verifyLookup['status'] ?? '') === 'found'
                        && is_array($verifyLookup['client'] ?? null)
                        && xuiRenewVerifyIncrementalAdjust($baselineSnap, $verifyLookup['client'], $gb, $days, $anchorMs)
                    ){
                        $adjusted = ['ok' => true, 'method' => 'verify_after_transport'];
                    }
                }

                if(empty($adjusted['ok'])){
                    return $adjusted;
                }
            }

            if($days > 0){
                $verifyLookup = xuiRenewLookupClient($server, $parsed['sub_id'], $link);
                $baselineSnap = xuiRenewClientSnapshot($client);

                if(
                    ($verifyLookup['status'] ?? '') !== 'found'
                    || !is_array($verifyLookup['client'] ?? null)
                    || !xuiRenewVerifyIncrementalAdjust($baselineSnap, $verifyLookup['client'], $gb, $days, $anchorMs)
                ){
                    return [
                        'ok' => false,
                        'error' => 'حجم یا مدت تمدید روی سرور با پلن خریداری‌شده تطابق ندارد',
                        'code' => 'ambiguous_provision',
                    ];
                }
            }

            $renewResult = [
                'ok' => true,
                'link' => $link,
                'email' => $email,
                'sub_id' => $parsed['sub_id'],
                'server_id' => $server['id'] ?? '',
                'gb' => $gb,
                'renew_outcome' => 'renewed',
                'user_renew_message' => xuiRenewUserMessageRenewed(),
                'method' => $adjusted['method'] ?? '',
            ];

            if(!xuiRenewProvisionStoreMarkComplete($paymentRow, $renewResult)){
                return [
                    'ok' => false,
                    'error' => 'تمدید انجام شد اما ذخیره نتیجه ناموفق بود',
                    'code' => 'persist_failed',
                    'recoverable' => true,
                    'link' => $link,
                ];
            }

            return $renewResult;
        }

        return xuiProvisionRenewRecreate($server, $parsed, $paymentRow, $gb, $days, $config);
    }

    function xuiPaymentsPath(){
        if(function_exists('pnvPaymentsCsvPath')){
            return pnvPaymentsCsvPath();
        }

        $override = getenv('PNV_PAYMENTS_CSV_PATH');

        if(is_string($override) && trim($override) !== ''){
            return trim($override);
        }

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
        if(
            !empty($GLOBALS['xuiTestSavePaymentsInterceptor'])
            && is_callable($GLOBALS['xuiTestSavePaymentsInterceptor'])
        ){
            $decision = $GLOBALS['xuiTestSavePaymentsInterceptor']($rows);

            if($decision === false){
                return false;
            }
        }

        $handle = fopen(xuiPaymentsPath(), 'w');

        if($handle === false){
            return false;
        }

        foreach($rows as $row){
            fputcsv($handle, $row);
        }

        return fclose($handle);
    }

    function xuiApprovePaymentIndex($index, $typeHint = ''){
        $index = intval($index);
        $payments = xuiLoadPayments();

        if(!isset($payments[$index])){
            return ['ok' => false, 'error' => 'پرداخت پیدا نشد'];
        }

        $txnId = xuiPaymentRowTransactionId($payments[$index]);
        $lockPath = xuiLocksDir() . '/payment_approve_' . $txnId . '.lock';
        $lockFp = fopen($lockPath, 'c');

        if($lockFp === false){
            return ['ok' => false, 'error' => 'قفل تأیید پرداخت در دسترس نیست'];
        }

        if(!flock($lockFp, LOCK_EX)){
            fclose($lockFp);

            return ['ok' => false, 'error' => 'تأیید پرداخت در حال انجام است؛ چند ثانیه بعد دوباره تلاش کنید'];
        }

        try{
            return xuiApprovePaymentIndexLocked($index, $typeHint);
        }
        finally{
            flock($lockFp, LOCK_UN);
            fclose($lockFp);
        }
    }

    function xuiApprovePaymentIndexLocked($index, $typeHint = ''){
        $payments = xuiLoadPayments();

        if(!isset($payments[$index])){
            return ['ok' => false, 'error' => 'پرداخت پیدا نشد'];
        }

        $row = $payments[$index];
        $type = trim((string)($row[9] ?? $typeHint));
        $status = trim((string)($row[6] ?? ''));

        $provisionKey = xuiPaymentRowProvisionKey($row);

        if($status === 'تایید شد'){
            $confirmed = xuiRenewResolveAlreadyConfirmedRenew($index, $row, $provisionKey);

            if($confirmed !== null){
                return $confirmed;
            }
        }

        $stored = xuiRenewProvisionStoreRead($provisionKey);

        if(is_array($stored) && ($stored['state'] ?? '') === 'ambiguous_provision'){
            return [
                'ok' => false,
                'error' => $stored['error'] ?? 'وضعیت فعال‌سازی اشتراک نامشخص است؛ برای جلوگیری از اشتراک تکراری دوباره ساخته نشد',
                'code' => 'ambiguous_provision',
                'recoverable' => true,
            ];
        }

        if(is_array($stored) && ($stored['state'] ?? '') === 'done' && is_array($stored['result'] ?? null)){
            return xuiRenewApproveFinalizeFromResult($index, $row, $provisionKey, $stored['result'], true);
        }

        if(is_array($stored) && ($stored['state'] ?? '') === 'processing'){
            if(is_array($stored['result'] ?? null)){
                return xuiRenewApproveFinalizeFromResult($index, $row, $provisionKey, $stored['result'], true);
            }

            if($type === 'تمدید'){
                $recover = xuiRenewRecoverProcessingRenew($row, $stored);

                if(($recover['action'] ?? '') === 'finalize'){
                    return xuiRenewApproveFinalizeFromResult($index, $row, $provisionKey, $recover['result'], true);
                }

                if(($recover['action'] ?? '') === 'in_progress'){
                    return [
                        'ok' => false,
                        'error' => $recover['error'] ?? 'فعال‌سازی در حال انجام است',
                        'code' => $recover['code'] ?? 'provision_in_progress',
                    ];
                }

                if(($recover['action'] ?? '') === 'ambiguous'){
                    xuiRenewProvisionStorePatch($provisionKey, [
                        'state' => 'ambiguous_provision',
                        'failed_at' => time(),
                        'error' => $recover['error'] ?? 'ambiguous',
                        'code' => 'ambiguous_provision',
                    ]);

                    return [
                        'ok' => false,
                        'error' => $recover['error'] ?? 'وضعیت فعال‌سازی نامشخص است',
                        'code' => 'ambiguous_provision',
                        'recoverable' => true,
                    ];
                }
            }
            else{
                return [
                    'ok' => false,
                    'error' => 'فعال‌سازی قبلی ناتمام مانده است؛ برای جلوگیری از تکرار، بررسی دستی لازم است',
                    'code' => 'ambiguous_provision',
                    'recoverable' => true,
                ];
            }
        }

        if(!is_array($stored) || ($stored['state'] ?? '') !== 'processing'){
            if(!xuiRenewProvisionStoreWrite($provisionKey, [
                'state' => 'processing',
                'phase' => 'approve_started',
                'started_at' => time(),
                'transaction_id' => xuiPaymentRowTransactionId($row),
            ])){
                return [
                    'ok' => false,
                    'error' => 'ذخیره وضعیت شروع تأیید ناموفق بود',
                    'code' => 'persist_failed',
                    'recoverable' => true,
                ];
            }
        }

        if($type === 'تمدید'){
            $resolvedLink = xuiResolveRenewPaymentRowLink($row);

            if($resolvedLink !== '' && trim((string)($payments[$index][1] ?? '')) !== $resolvedLink){
                $payments[$index][1] = $resolvedLink;
                xuiSavePayments($payments);
                $row = $payments[$index];
            }

            $result = xuiProvisionRenew($row);
        }
        else{
            $result = xuiProvisionBuy($row);
        }

        if(empty($result['ok'])){
            if(($result['code'] ?? '') === 'persist_failed' && !empty($result['recoverable'])){
                xuiRenewProvisionStorePatch($provisionKey, [
                    'last_error' => $result['error'] ?? '',
                    'last_fail_code' => 'persist_failed',
                    'last_fail_at' => time(),
                ]);

                return $result;
            }

            $failState = ($result['code'] ?? '') === 'ambiguous_provision' ? 'ambiguous_provision' : 'failed';
            xuiRenewProvisionStorePatch($provisionKey, [
                'state' => $failState,
                'failed_at' => time(),
                'error' => $result['error'] ?? 'provision failed',
                'code' => $result['code'] ?? '',
            ]);
            xuiRenewLog('provision_failed', [
                'user' => $row[0] ?? '',
                'tracking' => $row[3] ?? '',
                'type' => $type,
                'code' => $result['code'] ?? '',
            ]);

            return $result;
        }

        $payments = xuiLoadPayments();

        if(!isset($payments[$index])){
            return ['ok' => false, 'error' => 'پرداخت پیدا نشد'];
        }

        if(trim((string)($payments[$index][6] ?? '')) === 'تایید شد'){
            return [
                'ok' => true,
                'already' => true,
                'link' => $payments[$index][7] ?? ($result['link'] ?? ''),
            ];
        }

        $finalized = xuiRenewApproveFinalizeFromResult($index, $row, $provisionKey, $result, false);

        if(empty($finalized['ok'])){
            return $finalized;
        }

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

        return $finalized;
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
