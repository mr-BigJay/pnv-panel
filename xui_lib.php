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

    function xuiApiRequest($server, $method, $path, $body = null){
        $base = rtrim((string)($server['base_url'] ?? ''), '/');
        $token = trim((string)($server['api_token'] ?? ''));

        if($base === '' || $token === ''){
            return ['success' => false, 'msg' => 'تنظیمات سرور ناقص است'];
        }

        $url = $base . '/' . ltrim($path, '/');
        $headers = [
            'Accept: application/json',
            'Authorization: Bearer ' . $token
        ];

        if(!function_exists('curl_init')){
            return ['success' => false, 'msg' => 'افزونه cURL فعال نیست'];
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

        if($email === '' && $id === '' && $subId === ''){
            return null;
        }

        $client['email'] = $email;
        $client['subId'] = $subId;
        $client['id'] = $id;

        if($inboundId !== null){
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

        $id = trim((string)($client['id'] ?? $client['uuid'] ?? ''));
        return $id !== '' && strcasecmp($id, $uuid) === 0;
    }

    function xuiParseInboundClients($inbound){
        if(!is_array($inbound)){
            return [];
        }

        $inboundId = intval($inbound['id'] ?? 0);
        $settings = $inbound['settings'] ?? [];

        if(is_string($settings)){
            $decoded = json_decode($settings, true);
            $settings = is_array($decoded) ? $decoded : [];
        }

        $clients = $settings['clients'] ?? [];

        if(!is_array($clients)){
            return [];
        }

        $out = [];

        foreach($clients as $client){
            $normalized = xuiNormalizeClientRecord($client, $inboundId);

            if($normalized !== null){
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
        $size = 200;
        $checkedFullList = false;

        while($page <= 50){
            $result = xuiApiRequest(
                $server,
                'GET',
                '/panel/api/clients/list/paged?page=' . $page . '&size=' . $size
            );

            if(empty($result['success'])){
                if($page === 1 && !$checkedFullList){
                    $checkedFullList = true;
                    $result = xuiApiRequest($server, 'GET', '/panel/api/clients/list');

                    if(empty($result['success'])){
                        break;
                    }

                    $list = $result['obj'] ?? [];
                }
                else{
                    break;
                }
            }
            else{
                $obj = $result['obj'] ?? [];
                $list = $obj['list'] ?? $obj['clients'] ?? $obj;
            }

            if(!is_array($list)){
                break;
            }

            foreach($list as $item){
                $client = xuiNormalizeClientRecord($item['client'] ?? $item);

                if($client === null){
                    continue;
                }

                if(($subId !== '' && xuiClientMatchesSubId($client, $subId))
                    || ($uuid !== '' && xuiClientMatchesUuid($client, $uuid))){
                    $email = $client['email'] ?? '';

                    if($email !== ''){
                        $full = xuiApiRequest($server, 'GET', '/panel/api/clients/get/' . rawurlencode($email));

                        if(!empty($full['success']) && is_array($full['obj'] ?? null)){
                            $fullClient = xuiNormalizeClientRecord($full['obj']['client'] ?? $full['obj']);

                            if($fullClient !== null){
                                return $fullClient;
                            }
                        }
                    }

                    return $client;
                }
            }

            if($checkedFullList || count($list) < $size){
                break;
            }

            $page++;
        }

        return null;
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

    function xuiFindClientBySubId($server, $subId, $subLink = ''){
        $subId = trim((string)$subId);

        if($subId === ''){
            return null;
        }

        // 1) Clients API (نسخه‌های جدید)
        $client = xuiFindClientInClientsApi($server, $subId);

        if($client){
            return $client;
        }

        // 2) جستجو داخل inbound.settings.clients (کلاینت‌های قدیمی‌تر)
        $client = xuiFindClientInInbounds($server, $subId);

        if($client){
            return $client;
        }

        // 3) اگر Sub ID در API نبود، از محتوای لینک اشتراک UUID را بگیر و دوباره جستجو کن
        $uuid = $subLink !== '' ? xuiFetchSubUuid($subLink) : '';

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

                return $client;
            }
        }

        // 4) بعضی نسخه‌ها endpoint مستقیم ساب‌لینک دارند
        $subLinks = xuiApiRequest($server, 'GET', '/panel/api/inbounds/getSubLinks/' . rawurlencode($subId));

        if(!empty($subLinks['success']) && is_array($subLinks['obj'] ?? null)){
            $obj = $subLinks['obj'];
            $email = trim((string)($obj['email'] ?? $obj['clientEmail'] ?? ''));

            if($email !== ''){
                $full = xuiApiRequest($server, 'GET', '/panel/api/clients/get/' . rawurlencode($email));

                if(!empty($full['success']) && is_array($full['obj'] ?? null)){
                    $client = xuiNormalizeClientRecord($full['obj']['client'] ?? $full['obj']);

                    if($client){
                        return $client;
                    }
                }

                return xuiNormalizeClientRecord([
                    'email' => $email,
                    'subId' => $subId,
                    'id' => $obj['id'] ?? ($obj['clientId'] ?? '')
                ]);
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

    function xuiClientTotalBytes($client){
        if(!is_array($client)){
            return 0;
        }

        // نسخه‌های مختلف: totalGB گاهی بایت است، گاهی گیگ
        if(isset($client['totalGB'])){
            $value = intval($client['totalGB']);

            // اگر خیلی کوچک بود، احتمالاً گیگ است
            if($value > 0 && $value < 1024){
                return xuiGbToBytes($value);
            }

            return max(0, $value);
        }

        if(isset($client['total'])){
            return max(0, intval($client['total']));
        }

        return 0;
    }

    function xuiAdjustClientTrafficLegacy($server, $client, $addGb){
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

    function xuiAdjustClientTraffic($server, $email, $addGb, $client = null){
        $bytes = xuiGbToBytes($addGb);
        $email = trim((string)$email);
        $errors = [];

        if($email !== ''){
            $result = xuiApiRequest($server, 'POST', '/panel/api/clients/bulkAdjust', [
                'emails' => [$email],
                'addDays' => 0,
                'addBytes' => $bytes
            ]);

            if(!empty($result['success'])){
                return [
                    'ok' => true,
                    'raw' => $result,
                    'method' => 'bulkAdjust'
                ];
            }

            $errors[] = 'bulkAdjust: ' . ($result['msg'] ?? 'ناموفق');
        }

        if(is_array($client)){
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
            return ['ok' => false, 'error' => 'کاربر با Sub ID پیدا نشد: ' . $parsed['sub_id']];
        }

        $email = trim((string)($client['email'] ?? ''));

        if($email === ''){
            return ['ok' => false, 'error' => 'ایمیل کلاینت خالی است'];
        }

        $adjusted = xuiAdjustClientTraffic($server, $email, $gb, $client);

        if(empty($adjusted['ok'])){
            return $adjusted;
        }

        return [
            'ok' => true,
            'link' => xuiBuildSubLink($parsed['host'], $parsed['sub_id'], $config),
            'email' => $email,
            'sub_id' => $parsed['sub_id'],
            'server_id' => $server['id'] ?? '',
            'gb' => $gb
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

    function xuiApprovePaymentIndex($index, $typeHint = ''){
        $payments = xuiLoadPayments();

        if(!isset($payments[$index])){
            return ['ok' => false, 'error' => 'پرداخت پیدا نشد'];
        }

        $row = $payments[$index];
        $type = trim((string)($row[9] ?? $typeHint));
        $status = trim((string)($row[6] ?? ''));

        if($status === 'تایید شد'){
            return [
                'ok' => true,
                'already' => true,
                'link' => $row[7] ?? ''
            ];
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
}
