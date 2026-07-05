<?php

if(!function_exists('pushB64urlEncode')){

    function pushB64urlEncode($data){

        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');

    }

    function pushB64urlDecode($data){

        $remainder = strlen($data) % 4;

        if($remainder){
            $data .= str_repeat('=', 4 - $remainder);
        }

        return base64_decode(strtr($data, '-_', '+/'));

    }

    function pushVapidPath(){

        return dirname(__DIR__) . '/db/push_vapid.json';

    }

    function pushSubsPath(){

        return dirname(__DIR__) . '/db/push_subscriptions.json';

    }

    function pushLoadVapid(){

        $path = pushVapidPath();

        if(file_exists($path)){
            $data = json_decode(file_get_contents($path), true);

            if(
                is_array($data)
                && !empty($data['publicKey'])
                && !empty($data['privatePem'])
            ){
                return $data;
            }
        }

        $key = openssl_pkey_new([
            'curve_name' => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC
        ]);

        if(!$key){
            return null;
        }

        $privatePem = '';
        openssl_pkey_export($key, $privatePem);
        $details = openssl_pkey_get_details($key);

        if(empty($details['ec'])){
            return null;
        }

        $x = str_pad($details['ec']['x'], 32, "\0", STR_PAD_LEFT);
        $y = str_pad($details['ec']['y'], 32, "\0", STR_PAD_LEFT);

        $data = [
            'subject' => 'mailto:admin@ticketin.ir',
            'publicKey' => pushB64urlEncode("\x04" . $x . $y),
            'privatePem' => $privatePem
        ];

        $dir = dirname($path);

        if(!is_dir($dir)){
            mkdir($dir, 0755, true);
        }

        file_put_contents(
            $path,
            json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );

        return $data;

    }

    function pushPublicKey(){

        $vapid = pushLoadVapid();

        return $vapid['publicKey'] ?? '';

    }

    function pushLoadSubscriptions(){

        $path = pushSubsPath();

        if(!file_exists($path)){
            return [];
        }

        $data = json_decode(file_get_contents($path), true);

        return is_array($data) ? $data : [];

    }

    function pushSaveSubscription($admin, array $subscription){

        $endpoint = trim($subscription['endpoint'] ?? '');

        if($endpoint === ''){
            return false;
        }

        $keys = $subscription['keys'] ?? [];
        $p256dh = $keys['p256dh'] ?? '';
        $auth = $keys['auth'] ?? '';

        if($p256dh === '' || $auth === ''){
            return false;
        }

        $items = pushLoadSubscriptions();
        $found = false;

        foreach($items as $i => $item){

            if(($item['endpoint'] ?? '') === $endpoint){
                $items[$i] = [
                    'admin' => $admin,
                    'endpoint' => $endpoint,
                    'keys' => [
                        'p256dh' => $p256dh,
                        'auth' => $auth
                    ],
                    'updated_at' => time()
                ];
                $found = true;
                break;
            }

        }

        if(!$found){
            $items[] = [
                'admin' => $admin,
                'endpoint' => $endpoint,
                'keys' => [
                    'p256dh' => $p256dh,
                    'auth' => $auth
                ],
                'created_at' => time()
            ];
        }

        $path = pushSubsPath();
        $dir = dirname($path);

        if(!is_dir($dir)){
            mkdir($dir, 0755, true);
        }

        return file_put_contents(
            $path,
            json_encode(array_values($items), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        ) !== false;

    }

    function pushDerToJwtSignature($der, $partLength = 32){

        $pos = 0;

        if(ord($der[$pos++]) !== 0x30){
            return false;
        }

        $seqLen = ord($der[$pos++]);

        if($seqLen & 0x80){
            $bytes = $seqLen & 0x7f;
            $seqLen = 0;

            for($i = 0; $i < $bytes; $i++){
                $seqLen = ($seqLen << 8) + ord($der[$pos++]);
            }
        }

        if(ord($der[$pos++]) !== 0x02){
            return false;
        }

        $rLen = ord($der[$pos++]);
        $r = substr($der, $pos, $rLen);
        $pos += $rLen;

        if(ord($der[$pos++]) !== 0x02){
            return false;
        }

        $sLen = ord($der[$pos++]);
        $s = substr($der, $pos, $sLen);

        $r = ltrim($r, "\x00");
        $s = ltrim($s, "\x00");

        return str_pad($r, $partLength, "\x00", STR_PAD_LEFT)
            . str_pad($s, $partLength, "\x00", STR_PAD_LEFT);

    }

    function pushCreateVapidJwt($audience, $subject, $privatePem){

        $header = pushB64urlEncode(json_encode([
            'typ' => 'JWT',
            'alg' => 'ES256'
        ], JSON_UNESCAPED_SLASHES));

        $claims = pushB64urlEncode(json_encode([
            'aud' => $audience,
            'exp' => time() + 43200,
            'sub' => $subject
        ], JSON_UNESCAPED_SLASHES));

        $input = $header . '.' . $claims;
        $key = openssl_pkey_get_private($privatePem);

        if(!$key){
            return '';
        }

        $derSig = '';
        openssl_sign($input, $derSig, $key, OPENSSL_ALGO_SHA256);

        $rawSig = pushDerToJwtSignature($derSig);

        if($rawSig === false){
            return '';
        }

        return $input . '.' . pushB64urlEncode($rawSig);

    }

    function pushPublicKeyPem($publicKeyBin){

        $publicKeyBin = str_pad(substr($publicKeyBin, 0, 65), 65, "\0", STR_PAD_RIGHT);
        $bitString = "\x00" . $publicKeyBin;
        $bitStringSeq = "\x03" . chr(strlen($bitString)) . $bitString;
        $oid = hex2bin('06082a8648ce3d030107');
        $sequence = $oid . $bitStringSeq;
        $der = "\x30" . chr(strlen($sequence)) . $sequence;

        return "-----BEGIN PUBLIC KEY-----\n"
            . chunk_split(base64_encode($der), 64, "\n")
            . "-----END PUBLIC KEY-----\n";

    }

    function pushHkdf($salt, $ikm, $info, $length){

        $prk = hash_hmac('sha256', $ikm, $salt, true);

        return substr(hash_hmac('sha256', $info . chr(1), $prk, true), 0, $length);

    }

    function pushEncryptPayload($payload, $userPublicKey, $userAuth){

        $userPublicKey = pushB64urlDecode($userPublicKey);
        $userAuth = pushB64urlDecode($userAuth);

        $localKey = openssl_pkey_new([
            'curve_name' => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC
        ]);

        $localDetails = openssl_pkey_get_details($localKey);
        $lx = str_pad($localDetails['ec']['x'], 32, "\0", STR_PAD_LEFT);
        $ly = str_pad($localDetails['ec']['y'], 32, "\0", STR_PAD_LEFT);
        $localPublicKey = "\x04" . $lx . $ly;

        $userPem = pushPublicKeyPem($userPublicKey);
        $sharedSecret = openssl_pkey_derive($userPem, $localKey);

        if($sharedSecret === false){
            return null;
        }

        $sharedSecret = str_pad($sharedSecret, 32, "\0", STR_PAD_LEFT);
        $salt = random_bytes(16);
        $ikm = pushHkdf($userAuth, $sharedSecret, 'WebPush: info' . chr(0) . $userPublicKey . $localPublicKey, 32);
        $cek = pushHkdf($salt, $ikm, 'Content-Encoding: aes128gcm' . chr(0), 16);
        $nonce = pushHkdf($salt, $ikm, 'Content-Encoding: nonce' . chr(0), 12);
        $padded = $payload . chr(2);
        $tag = '';
        $cipherText = openssl_encrypt($padded, 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag);

        if($cipherText === false){
            return null;
        }

        $record = $salt
            . pack('N', 4096)
            . chr(strlen($localPublicKey))
            . $localPublicKey
            . $cipherText
            . $tag;

        return $record;

    }

    function pushSendToSubscription(array $subscription, array $payload, array $vapid){

        $endpoint = $subscription['endpoint'] ?? '';
        $keys = $subscription['keys'] ?? [];

        if($endpoint === '' || empty($keys['p256dh']) || empty($keys['auth'])){
            return false;
        }

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $content = pushEncryptPayload($json, $keys['p256dh'], $keys['auth']);

        if($content === null){
            return false;
        }

        $audience = parse_url($endpoint, PHP_URL_SCHEME) . '://' . parse_url($endpoint, PHP_URL_HOST);
        $publicKey = pushB64urlDecode($vapid['publicKey']);
        $jwt = pushCreateVapidJwt($audience, $vapid['subject'], $vapid['privatePem']);

        if($jwt === ''){
            return false;
        }

        $headers = [
            'Content-Type: application/octet-stream',
            'Content-Encoding: aes128gcm',
            'Content-Length: ' . strlen($content),
            'TTL: 2419200',
            'Urgency: high',
            'Authorization: vapid t=' . $jwt . ', k=' . pushB64urlEncode($publicKey)
        ];

        $ch = curl_init($endpoint);

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $content,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15
        ]);

        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $status >= 200 && $status < 300;

    }

    function pushNotifyAdmins($title, $body, $url = ''){

        if(!function_exists('curl_init')){
            return 0;
        }

        $vapid = pushLoadVapid();

        if(!$vapid){
            return 0;
        }

        $subscriptions = pushLoadSubscriptions();

        if(empty($subscriptions)){
            return 0;
        }

        $payload = [
            'title' => $title,
            'body' => $body,
            'url' => $url !== '' ? $url : '/bigjay_controller/?page=support',
            'tag' => 'support-push-' . time()
        ];

        $sent = 0;

        foreach($subscriptions as $subscription){

            if(pushSendToSubscription($subscription, $payload, $vapid)){
                $sent++;
            }

        }

        return $sent;

    }

}
