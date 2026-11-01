<?php
/**
 * Disposable-client-only probe for 3x-ui v3.4.2 Clients API.
 *
 * DRY RUN (no writes):
 *   php scripts/probe-xui342-update-test-client.php --email=pnv-expiry-test-001
 * APPLY (mutates only the disposable client):
 *   sudo php scripts/probe-xui342-update-test-client.php --email=pnv-expiry-test-001 --apply
 *
 * Does not update any customer, payment record, renewal or any historical
 * expiry candidate. Do not run on real subscriptions.
 */
if(PHP_SAPI !== 'cli'){
    http_response_code(403);
    exit;
}

function pnvProbe342Arg(array $args, $name, $fallback = ''){
    foreach($args as $arg){
        if(strpos($arg, '--' . $name . '=') === 0){
            return substr($arg, strlen($name) + 3);
        }
    }
    return $fallback;
}

function pnvProbe342Abort($why){
    throw new RuntimeException('STOP ' . $why);
}

function pnvProbe342Payload(array $c, $expiry){
    // Matches v3.4.2 frontend setEnable payload, preserving credential and
    // renewal settings. No generic PHP serializer or renew functions.
    return [
        'email' => (string)$c['email'],
        'id' => (string)$c['uuid'],
        'subId' => (string)$c['subId'],
        'password' => (string)($c['password'] ?? ''),
        'auth' => (string)($c['auth'] ?? ''),
        'flow' => (string)($c['flow'] ?? ''),
        'security' => (string)($c['security'] ?? 'auto'),
        'totalGB' => intval($c['totalGB']),
        'expiryTime' => intval($expiry),
        'enable' => true,
        'limitIp' => intval($c['limitIp'] ?? 0),
        'limitHwid' => intval($c['limitHwid'] ?? 0),
        'tgId' => intval($c['tgId'] ?? 0),
        'reset' => intval($c['reset'] ?? 0),
        'resetDay' => intval($c['resetDay'] ?? 0),
        'resetWeekday' => intval($c['resetWeekday'] ?? 0),
        'resetMax' => intval($c['resetMax'] ?? 0),
        'trafficReset' => (string)($c['trafficReset'] ?? 'never'),
        'trafficResetDay' => intval($c['trafficResetDay'] ?? 1),
        'group' => (string)($c['group'] ?? ''),
        'comment' => (string)($c['comment'] ?? ''),
    ] + (is_array($c['reverse'] ?? null) && !empty($c['reverse']['tag'])
        ? ['reverse' => ['tag' => $c['reverse']['tag']]]
        : []);
}

function pnvProbe342SafeOriginal(array $c, $email, $used){
    if(!preg_match('/^pnv-expiry-test-[a-z0-9-]{1,40}$/', $email)){
        pnvProbe342Abort('email must start pnv-expiry-test-');
    }
    if((string)($c['email'] ?? '') !== $email
        || strpos((string)($c['comment'] ?? ''), 'PNV_EXPIRY_TEST') !== 0){
        pnvProbe342Abort('test marker/comment or email mismatch');
    }
    if(empty($c['uuid']) || !preg_match('/^[0-9a-f-]{36}$/i', (string)$c['uuid'])
        || (string)($c['id'] ?? '') !== (string)$c['uuid']){
        pnvProbe342Abort('test UUID identity invalid');
    }
    if(empty($c['subId']) || intval($c['totalGB'] ?? 0) <= 0
        || intval($c['totalGB'] ?? 0) > 1073741824
        || intval($c['expiryTime'] ?? -1) !== 0
        || ($c['enable'] ?? null) !== true
        || intval($c['reset'] ?? 0) !== 0
        || intval($c['resetDay'] ?? 0) !== 0
        || intval($c['resetMax'] ?? 0) !== 0){
        pnvProbe342Abort('test client is not empty/unlimited or is auto-renewing');
    }
    if(intval($used) !== 0){
        pnvProbe342Abort('test client has already consumed traffic');
    }
}

function pnvProbe342Fetch($server, $email){
    $result = xuiApiRequest($server, 'GET', '/panel/api/clients/get/' . rawurlencode($email));
    if(empty($result['success']) || !is_array($result['obj'] ?? null)
        || !is_array($result['obj']['client'] ?? null)){
        pnvProbe342Abort('clients/get failed');
    }
    return $result['obj'];
}

function pnvProbe342Count($server, $email, $subId){
    $result = xuiApiRequest($server, 'GET', '/panel/api/clients/list');
    if(empty($result['success']) || !is_array($result['obj'] ?? null)){
        pnvProbe342Abort('clients/list failed');
    }
    $countEmail = 0; $countSub = 0;
    foreach($result['obj'] as $item){
        $row = is_array($item) ? ($item['client'] ?? $item) : [];
        if(!is_array($row)) continue;
        if(strcasecmp((string)($row['email'] ?? ''), $email) === 0) $countEmail++;
        if((string)($row['subId'] ?? '') === $subId) $countSub++;
    }
    return [$countEmail, $countSub];
}

function pnvProbe342Run($args){
    $root = realpath(pnvProbe342Arg($args, 'root', '/var/www/html'));
    $serverId = pnvProbe342Arg($args, 'server', 'vip3');
    $email = pnvProbe342Arg($args, 'email');
    $apply = in_array('--apply', $args, true);

    if($root === false || !is_file($root . '/xui_lib.php')){
        pnvProbe342Abort('invalid panel root');
    }
    if(!in_array($serverId, ['vip3', 'vip4'], true)){
        pnvProbe342Abort('server must be vip3 or vip4');
    }
    if(!preg_match('/^pnv-expiry-test-[a-z0-9-]{1,40}$/', $email)){
        pnvProbe342Abort('refusing non-test email');
    }
    if($apply && function_exists('posix_geteuid') && posix_geteuid() !== 0){
        pnvProbe342Abort('--apply needs root');
    }
    require_once $root . '/xui_lib.php';

    $server = xuiFindServerById($serverId, xuiLoadConfig());
    if(!is_array($server)){
        pnvProbe342Abort('server not found');
    }

    $info = xuiApiRequest($server, 'GET', '/panel/api/server/getPanelUpdateInfo');
    $version = (string)($info['obj']['currentVersion'] ?? $info['obj']['version'] ?? '');
    if(empty($info['success']) || $version !== '3.4.2'){
        pnvProbe342Abort('server version must be exactly 3.4.2');
    }

    $data = pnvProbe342Fetch($server, $email);
    $c = $data['client'];
    pnvProbe342SafeOriginal($c, $email, intval($data['usedTraffic'] ?? -1));
    $ids = $data['inboundIds'] ?? null;
    if(!is_array($ids) || count($ids) !== 1){
        pnvProbe342Abort('test client must be attached to exactly one inbound');
    }

    [$countEmail, $countSub] = pnvProbe342Count($server, $email, (string)$c['subId']);
    if($countEmail !== 1 || $countSub !== 1){
        pnvProbe342Abort('duplicate test client exists before update');
    }

    $target = (time() + 86400) * 1000;
    $out = [
        'mode' => $apply ? 'TEST_CLIENT_APPLY' : 'DRY_RUN',
        'server' => $serverId,
        'email' => $email,
        'version' => $version,
        'traffic_bytes_before' => 0,
        'client_counts_before' => ['email' => $countEmail, 'subId' => $countSub],
        'expiry_ms_before' => 0,
        'expiry_ms_target' => $target,
    ];
    if(!$apply){
        $out['result'] = 'TEST_CLIENT_DRY_RUN_PASS';
        return $out;
    }

    $dir = '/root/pnv-xui342-test-probe';
    if(is_link($dir)){
        pnvProbe342Abort('unsafe probe dir');
    }
    if(!is_dir($dir) && !mkdir($dir, 0700, true)){
        pnvProbe342Abort('unable to create probe dir');
    }
    chmod($dir, 0700);
    $marker = $dir . '/' . hash('sha256', $serverId . '|' . $email) . '.json';
    // Exclusive marker must exist before any POST. Repeats are blocked even
    // if a response was lost or a duplicate was created.
    $fp = @fopen($marker, 'x');
    if(!is_resource($fp)){
        pnvProbe342Abort('this disposable email was already attempted; do NOT replay');
    }
    chmod($marker, 0600);
    $saved = json_encode(['client' => $c, 'inboundIds' => $ids, 'usedTraffic' => 0,
        'email' => $email, 'server' => $serverId, 'started_at' => gmdate('c')],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if($saved === false || fwrite($fp, $saved) !== strlen($saved)){
        fclose($fp);
        pnvProbe342Abort('test snapshot save failed; aborting before POST');
    }
    fflush($fp);
    fclose($fp);

    // Single POST, no retries. Never use this endpoint on a customer.
    $payload = pnvProbe342Payload($c, $target);
    $result = xuiApiRequest($server, 'POST',
        '/panel/api/clients/update/' . rawurlencode($email), $payload);
    $out['http'] = intval($result['http'] ?? 0);
    $out['api_success'] = !empty($result['success']);

    try{
        $afterObj = pnvProbe342Fetch($server, $email);
        $after = $afterObj['client'];
        [$afterEmail, $afterSub] = pnvProbe342Count($server, $email, (string)$c['subId']);
        $out['client_counts_after'] = ['email' => $afterEmail, 'subId' => $afterSub];
        $out['uuid_preserved'] = (string)($after['uuid'] ?? '') === (string)$c['uuid'];
        $out['sub_id_preserved'] = (string)($after['subId'] ?? '') === (string)$c['subId'];
        $out['quota_preserved'] = intval($after['totalGB'] ?? -1) === intval($c['totalGB']);
        $out['traffic_preserved'] = intval($afterObj['usedTraffic'] ?? -1) === 0;
        $out['password_preserved'] = (string)($after['password'] ?? '') === (string)($c['password'] ?? '');
        $out['auth_preserved'] = (string)($after['auth'] ?? '') === (string)($c['auth'] ?? '');
        $out['reset_preserved'] = intval($after['reset'] ?? 0) === intval($c['reset'] ?? 0);
        $out['expiry_applied'] = intval($after['expiryTime'] ?? 0) === $target;
        $allGood = $afterEmail === 1 && $afterSub === 1
            && $out['uuid_preserved'] && $out['sub_id_preserved']
            && $out['quota_preserved'] && $out['traffic_preserved']
            && $out['password_preserved'] && $out['auth_preserved']
            && $out['reset_preserved'] && $out['expiry_applied'];
        $out['result'] = $allGood ? 'TEST_PASSED_NO_DUPLICATE'
            : 'TEST_FAILED_MANUAL_INSPECTION_REQUIRED';
    }
    catch(Throwable $ex){
        $out['result'] = 'TEST_READBACK_UNAVAILABLE_DO_NOT_RETRY';
    }
    $out['private_preimage'] = $marker;
    return $out;
}

if(realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__){
    try {
        $result = pnvProbe342Run($argv);
        echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n";
        if(!in_array($result['result'] ?? '', ['TEST_CLIENT_DRY_RUN_PASS', 'TEST_PASSED_NO_DUPLICATE'], true)){
            exit(3);
        }
    }
    catch(Throwable $e){
        fwrite(STDERR, $e->getMessage() . "\n");
        exit(2);
    }
}
