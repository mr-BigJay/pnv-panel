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

    function xuiNormalizeSubLink($link){
        $link = trim((string)$link);

        if($link === ''){
            return '';
        }

        // لینک کامل داخل متن کثیف (فرم وب متن دکمه را هم می‌چسباند)
        if(preg_match('#https?://(?:vip\d*)\.boozhaan\.ir(?::\d+)?/sub/[A-Za-z0-9]+#i', $link, $m)){
            return $m[0];
        }

        if(preg_match('#https?://([^/\s:?]+)(?::(\d+))?/sub/([A-Za-z0-9]+)#i', $link, $m)){
            $port = $m[2] !== '' ? (':' . $m[2]) : '';
            return 'https://' . strtolower($m[1]) . $port . '/sub/' . $m[3];
        }

        if(preg_match('#(?:vip\d*)\.boozhaan\.ir(?::\d+)?/sub/[A-Za-z0-9]+#i', $link, $m)){
            return 'https://' . $m[0];
        }

        // vip3-subid یا فقط SubID
        if(preg_match('/^(vip\d*)-([A-Za-z0-9]{8,32})$/i', $link, $m)){
            return xuiBuildSubLink(strtolower($m[1]) . '.boozhaan.ir', $m[2]);
        }

        if(preg_match('/^[A-Za-z0-9]{8,32}$/', $link)){
            return $link;
        }

        if(preg_match('/\b([A-Za-z0-9]{8,32})\b/', $link, $m)){
            return $m[1];
        }

        return preg_split('/\s+/u', $link)[0] ?? '';
    }

    function xuiParseSubLink($link){
        $link = xuiNormalizeSubLink($link);

        if($link === ''){
            return null;
        }

        if(preg_match('#https?://([^/:]+)(?::(\d+))?/sub/([A-Za-z0-9]+)#i', $link, $m)){
            return [
                'host' => strtolower($m[1]),
                'port' => intval($m[2] ?: 2096),
                'sub_id' => $m[3]
            ];
        }

        // فقط SubID — host بعداً از probe عمومی پیدا می‌شود
        if(preg_match('/^[A-Za-z0-9]{8,32}$/', $link)){
            return [
                'host' => '',
                'port' => 2096,
                'sub_id' => $link
            ];
        }

        return null;
    }

    function xuiHttpGetBody($url){
        $url = trim((string)$url);

        if($url === '' || !preg_match('#^https?://#i', $url)){
            return '';
        }

        if(function_exists('curl_init')){
            $curl = curl_init($url);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 8);
            curl_setopt($curl, CURLOPT_TIMEOUT, 15);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
            $raw = curl_exec($curl);
            $code = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);

            if($raw === false || $code >= 400 || $raw === ''){
                return '';
            }

            return (string)$raw;
        }

        $context = stream_context_create([
            'http' => [
                'timeout' => 15,
                'ignore_errors' => true
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false
            ]
        ]);
        $raw = @file_get_contents($url, false, $context);

        return ($raw === false || $raw === '') ? '' : (string)$raw;
    }

    function xuiDecodeSubBody($raw){
        $raw = (string)$raw;

        if($raw === ''){
            return '';
        }

        $decoded = base64_decode($raw, true);

        if($decoded === false || $decoded === ''){
            return $raw;
        }

        return $decoded;
    }

    function xuiParseSubBodyMeta($raw){
        $decoded = xuiDecodeSubBody($raw);
        $uuid = '';
        $email = '';

        if(preg_match('#(?:vless|trojan|ss)://([0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12})@#', $decoded, $m)){
            $uuid = $m[1];
        }
        elseif(preg_match('#"id"\s*:\s*"([0-9a-fA-F-]{36})"#', $decoded, $m)){
            $uuid = $m[1];
        }

        $urls = preg_split('/\r\n|\r|\n/', $decoded) ?: [];
        $email = xuiExtractEmailFromSubUrls($urls);

        return [
            'uuid' => $uuid,
            'email' => $email,
            'decoded' => $decoded
        ];
    }

    function xuiProbePublicSubMeta($subId, $preferredLink = '', $config = null){
        if($config === null){
            $config = xuiLoadConfig();
        }

        $subId = trim((string)$subId);
        $candidates = [];
        $preferredLink = xuiNormalizeSubLink($preferredLink);

        if($preferredLink !== '' && preg_match('#^https?://#i', $preferredLink)){
            $candidates[] = $preferredLink;
        }

        $port = intval($config['sub_port'] ?? 2096);
        $hosts = [];

        foreach(($config['servers'] ?? []) as $server){
            $host = strtolower(trim((string)($server['host'] ?? '')));

            if($host !== ''){
                $hosts[$host] = true;
            }
        }

        // fallback hosts اگر کانفیگ ناقص بود
        foreach(['vip.boozhaan.ir', 'vip2.boozhaan.ir', 'vip3.boozhaan.ir', 'vip4.boozhaan.ir'] as $host){
            $hosts[$host] = true;
        }

        if($subId !== ''){
            foreach(array_keys($hosts) as $host){
                $candidates[] = xuiBuildSubLink($host, $subId, $config);
                if($port !== 2096){
                    $candidates[] = 'https://' . $host . ':2096/sub/' . rawurlencode($subId);
                }
            }
        }

        $seen = [];

        foreach($candidates as $url){
            $url = xuiNormalizeSubLink($url);

            if($url === '' || isset($seen[$url])){
                continue;
            }

            $seen[$url] = true;
            $raw = xuiHttpGetBody($url);

            if($raw === ''){
                continue;
            }

            $meta = xuiParseSubBodyMeta($raw);

            if($meta['uuid'] === '' && $meta['email'] === ''){
                continue;
            }

            $parsed = xuiParseSubLink($url);

            return [
                'link' => $url,
                'host' => $parsed['host'] ?? '',
                'sub_id' => $subId !== '' ? $subId : ($parsed['sub_id'] ?? ''),
                'uuid' => $meta['uuid'],
                'email' => $meta['email']
            ];
        }

        return [
            'link' => $preferredLink,
            'host' => '',
            'sub_id' => $subId,
            'uuid' => '',
            'email' => ''
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

    function xuiClientsListFromResponse($result){
        if(!is_array($result) || empty($result['success'])){
            return [];
        }

        $obj = $result['obj'] ?? null;

        if(!is_array($obj)){
            return [];
        }

        // بعضی نسخه‌ها obj را مستقیم آرایه کلاینت می‌دهند
        if(isset($obj[0]) || count($obj) === 0){
            return array_values($obj);
        }

        foreach(['items', 'list', 'clients', 'data', 'rows'] as $key){
            if(isset($obj[$key]) && is_array($obj[$key])){
                return array_values($obj[$key]);
            }
        }

        return [];
    }

    function xuiFindClientInClientsApi($server, $subId = '', $uuid = ''){
        $page = 1;
        $pageSize = 200;

        // اول با search مستقیم Sub ID (API جدید 3x-ui → obj.items)
        if($subId !== ''){
            $searched = xuiApiRequest(
                $server,
                'GET',
                '/panel/api/clients/list/paged?page=1&pageSize=' . $pageSize
                . '&search=' . rawurlencode($subId)
                . '&filter=&protocol=&sort=email&order=ascend'
            );

            foreach(xuiClientsListFromResponse($searched) as $item){
                $client = xuiNormalizeClientRecord($item['client'] ?? $item);

                if($client && xuiClientMatchesSubId($client, $subId)){
                    return xuiHydrateClientByEmail($server, $client);
                }
            }
        }

        // لیست کامل
        $full = xuiApiRequest($server, 'GET', '/panel/api/clients/list');
        $fullList = xuiClientsListFromResponse($full);

        if(count($fullList) === 0 && is_array($full['obj'] ?? null) && isset(($full['obj'])[0])){
            $fullList = array_values($full['obj']);
        }

        foreach($fullList as $item){
            $client = xuiNormalizeClientRecord($item['client'] ?? $item);

            if($client === null){
                continue;
            }

            if(($subId !== '' && xuiClientMatchesSubId($client, $subId))
                || ($uuid !== '' && xuiClientMatchesUuid($client, $uuid))){
                return xuiHydrateClientByEmail($server, $client);
            }
        }

        // صفحه‌بندی درست با pageSize (نه size) و کلید items
        while($page <= 100){
            $result = xuiApiRequest(
                $server,
                'GET',
                '/panel/api/clients/list/paged?page=' . $page
                . '&pageSize=' . $pageSize
                . '&search=&filter=&protocol=&sort=email&order=ascend'
            );

            $list = xuiClientsListFromResponse($result);

            if(count($list) === 0){
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

                return $fullClient;
            }
        }

        return $client;
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
            $fragment = html_entity_decode($fragment, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $fragment = ltrim($fragment, "- \t");
            // remarkهایی مثل s8274_8274|📊0.00B یا name | traffic
            $fragment = preg_split('/[|\s\/]+/u', $fragment)[0] ?? '';
            $fragment = trim($fragment, ".-_ \t");

            if($fragment !== '' && preg_match('/^[\w.@+\-]+$/u', $fragment)){
                return $fragment;
            }
        }

        return '';
    }

    function xuiFetchSubUuid($subLink){
        $subLink = xuiNormalizeSubLink($subLink);
        $raw = xuiHttpGetBody($subLink);

        if($raw === ''){
            return '';
        }

        return xuiParseSubBodyMeta($raw)['uuid'] ?? '';
    }

    function xuiFetchSubEmail($subLink){
        $subLink = xuiNormalizeSubLink($subLink);
        $raw = xuiHttpGetBody($subLink);

        if($raw === ''){
            return '';
        }

        return xuiParseSubBodyMeta($raw)['email'] ?? '';
    }

    function xuiFindClientByEmailLocal($server, $email){
        $email = trim((string)$email);

        if($email === ''){
            return null;
        }

        $full = xuiApiRequest($server, 'GET', '/panel/api/clients/get/' . rawurlencode($email));

        if(!empty($full['success']) && is_array($full['obj'] ?? null)){
            $client = xuiNormalizeClientRecord($full['obj']['client'] ?? $full['obj']);

            if($client){
                return $client;
            }
        }

        foreach(xuiFetchInbounds($server) as $inbound){
            foreach(xuiParseInboundClients($inbound) as $item){
                if(strcasecmp((string)($item['email'] ?? ''), $email) === 0){
                    return $item;
                }
            }
        }

        return null;
    }

    function xuiFindClientBySubId($server, $subId, $subLink = '', $meta = null){
        $subId = trim((string)$subId);
        $subLink = xuiNormalizeSubLink($subLink);

        if($subId === '' && $subLink === ''){
            return null;
        }

        if(!is_array($meta)){
            $meta = [
                'uuid' => '',
                'email' => '',
                'link' => $subLink
            ];
        }

        if(($meta['link'] ?? '') !== ''){
            $subLink = xuiNormalizeSubLink($meta['link']);
        }

        // 1) Clients API با search/pageSize درست
        if($subId !== ''){
            $client = xuiFindClientInClientsApi($server, $subId);

            if($client){
                return $client;
            }
        }

        // 2) inbound clientStats + settings.clients
        if($subId !== ''){
            $client = xuiFindClientInInbounds($server, $subId);

            if($client){
                return xuiHydrateClientByEmail($server, $client);
            }
        }

        // 3) endpoint رسمی subLinks در Clients API
        if($subId !== ''){
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
                    $client = xuiFindClientByEmailLocal($server, $email);

                    if($client){
                        if(($client['subId'] ?? '') === ''){
                            $client['subId'] = $subId;
                        }

                        return $client;
                    }

                    return xuiNormalizeClientRecord([
                        'email' => $email,
                        'subId' => $subId
                    ]);
                }
            }
        }

        // 4) UUID / email از لینک اشتراک عمومی (یا meta از قبل probe شده)
        $uuid = trim((string)($meta['uuid'] ?? ''));
        $subEmail = trim((string)($meta['email'] ?? ''));

        if(($uuid === '' || $subEmail === '') && $subLink !== ''){
            $raw = xuiHttpGetBody($subLink);

            if($raw !== ''){
                $parsedMeta = xuiParseSubBodyMeta($raw);

                if($uuid === ''){
                    $uuid = $parsedMeta['uuid'];
                }

                if($subEmail === ''){
                    $subEmail = $parsedMeta['email'];
                }
            }
        }

        if($subEmail !== ''){
            $client = xuiFindClientByEmailLocal($server, $subEmail);

            if($client){
                if(($client['subId'] ?? '') === '' && $subId !== ''){
                    $client['subId'] = $subId;
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
                if(($client['subId'] ?? '') === '' && $subId !== ''){
                    $client['subId'] = $subId;
                }

                return $client;
            }

            $client = xuiFindClientInInbounds($server, '', $uuid);

            if($client){
                if(($client['subId'] ?? '') === '' && $subId !== ''){
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

        $rawSubLink = trim((string)($paymentRow[1] ?? ''));
        $subLink = xuiNormalizeSubLink($rawSubLink);
        $planText = trim((string)($paymentRow[2] ?? ''));
        $gb = xuiParsePlanGb($planText);

        if($gb <= 0){
            return ['ok' => false, 'error' => 'حجم پلن قابل تشخیص نیست: ' . $planText];
        }

        $parsed = xuiParseSubLink($subLink);

        if(!$parsed || ($parsed['sub_id'] ?? '') === ''){
            return ['ok' => false, 'error' => 'لینک اشتراک تمدید معتبر نیست'];
        }

        $subId = $parsed['sub_id'];

        // لینک عمومی واقعی را پیدا کن (لینک کثیف / هاست اشتباه را اصلاح می‌کند)
        $meta = xuiProbePublicSubMeta($subId, $subLink, $config);

        if(($meta['link'] ?? '') !== ''){
            $subLink = $meta['link'];
            $probed = xuiParseSubLink($subLink);

            if($probed && ($probed['host'] ?? '') !== ''){
                $parsed['host'] = $probed['host'];
                $parsed['port'] = $probed['port'] ?? $parsed['port'];
            }
        }

        $allowed = $config['renew_server_ids'] ?? [];
        $serversToTry = [];
        $seenServer = [];

        $pushServer = function($server) use (&$serversToTry, &$seenServer, $allowed){
            if(!is_array($server)){
                return;
            }

            $id = (string)($server['id'] ?? '');

            if($id !== '' && isset($seenServer[$id])){
                return;
            }

            if(is_array($allowed) && count($allowed) > 0 && $id !== '' && !in_array($id, $allowed, true)){
                return;
            }

            if($id !== ''){
                $seenServer[$id] = true;
            }

            $serversToTry[] = $server;
        };

        if(($parsed['host'] ?? '') !== ''){
            $pushServer(xuiFindServerByHost($parsed['host'], $config));
        }

        if(is_array($allowed) && count($allowed) > 0){
            foreach($allowed as $serverId){
                $pushServer(xuiFindServerById($serverId, $config));
            }
        }
        else{
            foreach(($config['servers'] ?? []) as $server){
                $pushServer($server);
            }
        }

        if(count($serversToTry) === 0){
            return ['ok' => false, 'error' => 'سرور مربوط به لینک پیدا نشد: ' . ($parsed['host'] ?: $subId)];
        }

        $client = null;
        $server = null;

        foreach($serversToTry as $candidate){
            $found = xuiFindClientBySubId($candidate, $subId, $subLink, $meta);

            if($found && trim((string)($found['email'] ?? '')) !== ''){
                $client = $found;
                $server = $candidate;
                break;
            }
        }

        if(!$client || !$server){
            $hint = $subId;

            if(($meta['email'] ?? '') !== ''){
                $hint .= ' / email=' . $meta['email'];
            }

            if(($meta['uuid'] ?? '') !== ''){
                $hint .= ' / uuid=' . $meta['uuid'];
            }

            return ['ok' => false, 'error' => 'کاربر با Sub ID پیدا نشد: ' . $hint];
        }

        $email = trim((string)($client['email'] ?? ''));
        $adjusted = xuiAdjustClientTraffic($server, $email, $gb, $client);

        if(empty($adjusted['ok'])){
            return $adjusted;
        }

        $host = trim((string)($server['host'] ?? ($parsed['host'] ?? '')));

        if($host === '' && ($meta['host'] ?? '') !== ''){
            $host = $meta['host'];
        }

        return [
            'ok' => true,
            'link' => xuiBuildSubLink($host, $subId, $config),
            'email' => $email,
            'sub_id' => $subId,
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
