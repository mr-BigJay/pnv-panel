<?php

/**
 * Isolated E2E: server3 test client only — reserve, due, activate, no duplicate apply.
 *
 * Writes only under PNV_E2E_WORKDIR. Reads XUI config source read-only.
 */

if(PHP_SAPI !== 'cli'){
    fwrite(STDERR, "CLI only\n");
    exit(1);
}

$repoRoot = realpath(__DIR__ . '/../..') ?: (__DIR__ . '/../..');
require_once $repoRoot . '/xui_lib.php';
require_once $repoRoot . '/renew_reserve_lib.php';

const E2E_SERVER_ID = 'server3';

$step = 'help';

foreach(array_slice($argv, 1) as $arg){
    if(strpos($arg, '--step=') === 0){
        $step = trim(substr($arg, 7));
    }
}

function e2eOut($payload){
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
}

function e2eFail($msg, $code = 1, $extra = []){
    e2eOut(array_merge(['ok' => false, 'error' => $msg], $extra));
    exit($code);
}

function e2eEnv($key, $default = ''){
    $v = getenv($key);

    return is_string($v) && trim($v) !== '' ? trim($v) : $default;
}

function e2eWorkdir(){
    $dir = e2eEnv('PNV_E2E_WORKDIR', '/tmp/pnv-e2e-server3');

    if(!is_dir($dir)){
        @mkdir($dir, 0750, true);
    }

    return rtrim($dir, '/');
}

function e2eStatePath(){
    return e2eWorkdir() . '/e2e_state.json';
}

function e2eLoadState(){
    $path = e2eStatePath();

    if(!is_file($path)){
        return [];
    }

    $data = json_decode((string)file_get_contents($path), true);

    return is_array($data) ? $data : [];
}

function e2eSaveState(array $state){
    file_put_contents(e2eStatePath(), json_encode($state, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
}

function e2eBindIsolatedEnv(){
    $wd = e2eWorkdir();
    $locks = $wd . '/locks';
    @mkdir($locks, 0750, true);
    putenv('PNV_E2E_WORKDIR=' . $wd);
    putenv('PNV_RENEW_RESERVE_PATH=' . $wd . '/renew_reservations.json');
    putenv('PNV_LOCKS_DIR=' . $locks);
    putenv('PNV_PAYMENTS_CSV_PATH=' . $wd . '/payments.csv');
    putenv('PNV_XUI_CONFIG_PATH=' . $wd . '/xui_servers.server3.json');
    putenv('PNV_PLANS_CATALOG_PATH=' . $wd . '/plans.json');

    if(!is_file($wd . '/plans.json')){
        file_put_contents($wd . '/plans.json', '[]');
    }
}

function e2eExtractServer3Config(){
    $source = e2eEnv('PNV_XUI_CONFIG_SOURCE');

    if($source === '' || !is_readable($source)){
        e2eFail('PNV_XUI_CONFIG_SOURCE must point to readable xui_servers.json (read-only)');
    }

    $raw = json_decode((string)file_get_contents($source), true);

    if(!is_array($raw)){
        e2eFail('invalid XUI config JSON at source');
    }

    $picked = null;

    foreach(($raw['servers'] ?? []) as $server){
        if(($server['id'] ?? '') === E2E_SERVER_ID){
            $picked = $server;
            break;
        }
    }

    if(!is_array($picked)){
        e2eFail('server id not found in source config', 1, ['server_id' => E2E_SERVER_ID]);
    }

    $out = $raw;
    $out['servers'] = [$picked];
    $out['renew_server_ids'] = [E2E_SERVER_ID];
    $out['buy_server_ids'] = [E2E_SERVER_ID];
    $dest = e2eWorkdir() . '/xui_servers.server3.json';
    file_put_contents($dest, json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);

    return $picked;
}

function e2eRequireTestTarget(){
    $subLink = e2eEnv('PNV_E2E_SUB_LINK');
    $allowedSub = e2eEnv('PNV_E2E_ALLOWED_SUB_ID');
    $allowedEmail = e2eEnv('PNV_E2E_ALLOWED_EMAIL');
    $username = e2eEnv('PNV_E2E_USERNAME', 'pnv_e2e_user');

    if($subLink === '' || $allowedSub === '' || $allowedEmail === ''){
        e2eFail('set PNV_E2E_SUB_LINK, PNV_E2E_ALLOWED_SUB_ID, PNV_E2E_ALLOWED_EMAIL');
    }

    $parsed = xuiParseSubLink($subLink);

    if(!is_array($parsed)){
        e2eFail('invalid PNV_E2E_SUB_LINK');
    }

    if(trim((string)($parsed['sub_id'] ?? '')) !== $allowedSub){
        e2eFail('sub_id mismatch (refusing non-test client)', 1, [
            'parsed_sub_id' => $parsed['sub_id'] ?? '',
            'allowed' => $allowedSub,
        ]);
    }

    $server = xuiFindServerByHost($parsed['host'] ?? '', xuiLoadConfig());

    if(!$server || ($server['id'] ?? '') !== E2E_SERVER_ID){
        e2eFail('sub link host is not server3', 1, ['host' => $parsed['host'] ?? '']);
    }

    $lookup = xuiRenewLookupClient($server, $parsed['sub_id'], $subLink);

    if(($lookup['status'] ?? '') !== 'found' || !is_array($lookup['client'] ?? null)){
        e2eFail('test client not found on server3 XUI', 1, ['lookup' => $lookup['status'] ?? '']);
    }

    $email = trim((string)($lookup['client']['email'] ?? ''));

    if($email !== $allowedEmail){
        e2eFail('client email mismatch (refusing non-test client)', 1, [
            'xui_email' => $email,
            'allowed_email' => $allowedEmail,
        ]);
    }

    return [
        'username' => $username,
        'sub_link' => $subLink,
        'parsed' => $parsed,
        'server' => $server,
        'client' => $lookup['client'],
    ];
}

function e2eClientFingerprint($client){
    $snap = xuiRenewClientSnapshot($client);

    return [
        'total_bytes' => intval($snap['total_bytes'] ?? 0),
        'used_bytes' => intval($snap['used_bytes'] ?? 0),
        'expiry_ms' => intval($snap['expiry_ms'] ?? 0),
    ];
}

function e2eWriteOwnershipCsv($username, $subLink){
    $path = e2eWorkdir() . '/payments.csv';
    $hist = [
        $username,
        $subLink,
        'E2E history',
        'E2E-HIST',
        '',
        '',
        'تایید شد',
        $subLink,
        time() - 86400,
        'تمدید',
        '',
        '',
        '0',
    ];
    $fh = fopen($path, 'w');

    if($fh === false){
        e2eFail('cannot write isolated payments.csv');
    }

    fputcsv($fh, $hist);
    fclose($fh);
}

function e2eStepInit(){
    e2eBindIsolatedEnv();
    $server = e2eExtractServer3Config();
    $target = e2eRequireTestTarget();
    $state = array_merge(e2eLoadState(), [
        'server_id' => E2E_SERVER_ID,
        'host' => $server['host'] ?? '',
        'sub_link' => $target['sub_link'],
        'sub_id' => $target['parsed']['sub_id'] ?? '',
        'username' => $target['username'],
        'allowed_email' => e2eEnv('PNV_E2E_ALLOWED_EMAIL'),
        'initialized_at' => date('c'),
    ]);
    e2eSaveState($state);
    e2eOut([
        'ok' => true,
        'step' => 'init',
        'workdir' => e2eWorkdir(),
        'client' => e2eClientFingerprint($target['client']),
        'runtime' => xuiRenewAnalyzeRuntimeState($target['server'], $target['client'], $target['sub_link']),
    ]);
}

function e2ePlanGbDays(){
    $gb = max(1, intval(e2eEnv('PNV_E2E_PLAN_GB', '2')));
    $days = max(1, intval(e2eEnv('PNV_E2E_PLAN_DAYS', '7')));

    return [$gb, $days];
}

function e2eStepReserve(){
    e2eBindIsolatedEnv();

    if(!is_file(e2eWorkdir() . '/xui_servers.server3.json')){
        e2eExtractServer3Config();
    }

    $target = e2eRequireTestTarget();
    [$gb, $days] = e2ePlanGbDays();
    e2eWriteOwnershipCsv($target['username'], $target['sub_link']);

    @unlink(getenv('PNV_RENEW_RESERVE_PATH') ?: '');
    $tracking = 'E2E-' . date('YmdHis');
    $row = [
        $target['username'],
        $target['sub_link'],
        $gb . ' گیگ - ' . $days . ' روز - E2E',
        $tracking,
        '',
        '',
        'درحال بررسی',
        '',
        time(),
        'تمدید',
        '',
        '',
        '0',
    ];

    $provisionKey = xuiPaymentRowProvisionKey($row);
    @unlink(xuiRenewProvisionStorePath($provisionKey));

    $before = e2eClientFingerprint($target['client']);
    $provision = xuiProvisionRenew($row);

    if(empty($provision['ok']) || ($provision['renew_outcome'] ?? '') !== 'reserved'){
        e2eFail('expected renew reserve via xuiProvisionRenew', 1, ['provision' => $provision]);
    }

    $reserveId = trim((string)($provision['renew_reserve_id'] ?? ''));
    $cronSkip = renewReserveProcessQueue(5);

    $targetAfter = e2eRequireTestTarget();
    $after = e2eClientFingerprint($targetAfter['client']);

    if($before['total_bytes'] !== $after['total_bytes'] || $before['expiry_ms'] !== $after['expiry_ms']){
        e2eFail('XUI must not change on reserve + pre-due cron', 1, ['before' => $before, 'after' => $after]);
    }

    if(($cronSkip['activated'] ?? 0) > 0){
        e2eFail('cron must not activate before due', 1, ['summary' => $cronSkip]);
    }

    $state = array_merge(e2eLoadState(), [
        'reserve_id' => $reserveId,
        'provision_key' => $provisionKey,
        'tracking' => $tracking,
        'plan_gb' => $gb,
        'plan_days' => $days,
        'baseline_before_reserve' => $before,
        'reserved_at' => date('c'),
    ]);
    e2eSaveState($state);

    e2eOut([
        'ok' => true,
        'step' => 'reserve',
        'reserve_id' => $reserveId,
        'renew_outcome' => 'reserved',
        'cron_summary' => $cronSkip,
        'runtime' => xuiRenewAnalyzeRuntimeState($targetAfter['server'], $targetAfter['client'], $target['sub_link']),
        'next' => 'Exhaust test client volume (or wait for expiry), then: --step=activate',
    ]);
}

function e2eStepActivate(){
    e2eBindIsolatedEnv();
    $state = e2eLoadState();

    if(trim((string)($state['reserve_id'] ?? '')) === ''){
        e2eFail('run --step=reserve first');
    }

    $target = e2eRequireTestTarget();
    $runtime = xuiRenewAnalyzeRuntimeState($target['server'], $target['client'], $target['sub_link']);

    if(empty($runtime['should_activate_now'])){
        e2eFail('activation not due yet on live XUI', 2, [
            'runtime' => $runtime,
            'hint' => 'consume test client traffic or wait until expiry',
        ]);
    }

    $gb = intval($state['plan_gb'] ?? e2ePlanGbDays()[0]);
    $days = intval($state['plan_days'] ?? e2ePlanGbDays()[1]);
    $pre = e2eClientFingerprint($target['client']);

    $s1 = renewReserveProcessQueue(5);

    if(($s1['activated'] ?? 0) < 1){
        e2eFail('cron did not activate reserved plan', 1, ['summary' => $s1]);
    }

    $mid = e2eRequireTestTarget();
    $post = e2eClientFingerprint($mid['client']);
    $anchorMs = renewReserveNowMs();

    if(!xuiRenewVerifyAbsolutePlan($mid['client'], $gb, $days, $anchorMs)){
        $storedAnchor = 0;
        $items = renewReserveLoad();

        if(is_array($items)){
            foreach($items as $item){
                if(trim((string)($item['id'] ?? '')) === trim((string)($state['reserve_id'] ?? ''))){
                    $storedAnchor = intval($item['activation_anchor_ms'] ?? 0);
                    break;
                }
            }
        }

        if($storedAnchor > 0 && !xuiRenewVerifyAbsolutePlan($mid['client'], $gb, $days, $storedAnchor)){
            e2eFail('3x-ui client does not match reserved plan after activation', 1, [
                'plan_gb' => $gb,
                'plan_days' => $days,
                'post' => $post,
            ]);
        }
    }

    $s2 = renewReserveProcessQueue(5);
    $s3 = renewReserveProcessQueue(5);
    $final = e2eRequireTestTarget();
    $afterIdempotent = e2eClientFingerprint($final['client']);

    if($post['total_bytes'] !== $afterIdempotent['total_bytes'] || $post['expiry_ms'] !== $afterIdempotent['expiry_ms']){
        e2eFail('duplicate cron runs changed XUI client again', 1, [
            'after_activate' => $post,
            'after_idempotent' => $afterIdempotent,
            'summaries' => [$s2, $s3],
        ]);
    }

    $items = renewReserveLoad();
    $st = '';

    if(is_array($items)){
        foreach($items as $item){
            if(trim((string)($item['id'] ?? '')) === trim((string)($state['reserve_id'] ?? ''))){
                $st = trim((string)($item['status'] ?? ''));
                break;
            }
        }
    }

    if($st !== 'activated'){
        e2eFail('reservation status is not activated', 1, ['status' => $st]);
    }

    $state['activated_at'] = date('c');
    $state['pre_activate'] = $pre;
    $state['post_activate'] = $post;
    e2eSaveState($state);

    e2eOut([
        'ok' => true,
        'step' => 'activate',
        'cron_first' => $s1,
        'cron_repeat' => [$s2, $s3],
        'reserve_status' => $st,
        'pre' => $pre,
        'post' => $post,
        'changed' => [
            'total_bytes' => $post['total_bytes'] !== $pre['total_bytes'],
            'expiry_ms' => $post['expiry_ms'] !== $pre['expiry_ms'],
        ],
    ]);
}

function e2eStepStatus(){
    e2eBindIsolatedEnv();
    $target = e2eRequireTestTarget();
    e2eOut([
        'ok' => true,
        'step' => 'status',
        'workdir' => e2eWorkdir(),
        'state' => e2eLoadState(),
        'client' => e2eClientFingerprint($target['client']),
        'runtime' => xuiRenewAnalyzeRuntimeState($target['server'], $target['client'], $target['sub_link']),
        'reservations' => renewReserveLoad(),
    ]);
}

function e2eStepClean(){
    $wd = e2eWorkdir();
    foreach(['renew_reservations.json', 'payments.csv', 'e2e_state.json'] as $f){
        @unlink($wd . '/' . $f);
    }

    e2eOut(['ok' => true, 'step' => 'clean', 'workdir' => $wd]);
}

switch($step){
    case 'init':
        e2eStepInit();
        break;
    case 'reserve':
        e2eStepReserve();
        break;
    case 'activate':
        e2eStepActivate();
        break;
    case 'status':
        e2eStepStatus();
        break;
    case 'clean':
        e2eStepClean();
        break;
    default:
        e2eFail('usage: --step=init|reserve|activate|status|clean', 1);
}
