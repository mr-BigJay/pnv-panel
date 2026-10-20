<?php

/**
 * Isolated E2E: allowlisted test client — reserve, due, activate, no duplicate apply.
 *
 * Resolves XUI server uniquely from PNV_E2E_SUB_LINK host (vip / vip2 / vip3 / vip4).
 * Writes only under PNV_E2E_WORKDIR. Reads PNV_XUI_CONFIG_SOURCE read-only.
 */

if(PHP_SAPI !== 'cli'){
    fwrite(STDERR, "CLI only\n");
    exit(1);
}

$repoRoot = realpath(__DIR__ . '/../..') ?: (__DIR__ . '/../..');
require_once $repoRoot . '/xui_lib.php';
require_once $repoRoot . '/renew_reserve_lib.php';

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
    $dir = e2eEnv('PNV_E2E_WORKDIR', '/tmp/pnv-e2e-renew-reserve');

    if(!is_dir($dir)){
        @mkdir($dir, 0750, true);
    }

    return rtrim($dir, '/');
}

function e2eIsolatedConfigPath(){
    return e2eWorkdir() . '/xui_servers.e2e.json';
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

function e2eLoadSourceConfig(){
    $source = e2eEnv('PNV_XUI_CONFIG_SOURCE');

    if($source === '' || !is_readable($source)){
        e2eFail('PNV_XUI_CONFIG_SOURCE must point to readable xui_servers.json (read-only)');
    }

    $raw = json_decode((string)file_get_contents($source), true);

    if(!is_array($raw)){
        e2eFail('invalid XUI config JSON at source');
    }

    return $raw;
}

function e2eParseSubLinkOrFail(){
    $subLink = e2eEnv('PNV_E2E_SUB_LINK');
    $allowedSub = e2eEnv('PNV_E2E_ALLOWED_SUB_ID');

    if($subLink === '' || $allowedSub === ''){
        e2eFail('set PNV_E2E_SUB_LINK and PNV_E2E_ALLOWED_SUB_ID');
    }

    $parsed = xuiParseSubLink($subLink);

    if(!is_array($parsed)){
        e2eFail('invalid PNV_E2E_SUB_LINK');
    }

    $subId = trim((string)($parsed['sub_id'] ?? ''));

    if($subId === '' || $subId !== $allowedSub){
        e2eFail('sub_id mismatch (refusing non-test client)', 1, [
            'parsed_sub_id' => $subId,
            'allowed' => $allowedSub,
        ]);
    }

    $host = trim((string)($parsed['host'] ?? ''));

    if($host === ''){
        e2eFail('sub link host missing');
    }

    return [
        'sub_link' => $subLink,
        'parsed' => $parsed,
        'sub_id' => $subId,
        'host' => $host,
    ];
}

function e2eFindUniqueServerForHost(array $raw, $host){
    $hostNorm = strtolower(trim((string)$host));
    $matches = [];

    foreach(($raw['servers'] ?? []) as $server){
        if(!is_array($server)){
            continue;
        }

        if(strtolower(trim((string)($server['host'] ?? ''))) === $hostNorm){
            $matches[] = $server;
        }
    }

    if(count($matches) === 0){
        e2eFail('no XUI server matches sub link host', 1, ['host' => $host]);
    }

    if(count($matches) > 1){
        $ids = [];

        foreach($matches as $server){
            $ids[] = trim((string)($server['id'] ?? ''));
        }

        e2eFail('ambiguous XUI server for sub link host; fix config duplicates', 1, [
            'host' => $host,
            'server_ids' => $ids,
        ]);
    }

    return $matches[0];
}

function e2eExtractServerFromSubLink(){
    $linkInfo = e2eParseSubLinkOrFail();
    $raw = e2eLoadSourceConfig();
    $picked = e2eFindUniqueServerForHost($raw, $linkInfo['host']);
    $serverId = trim((string)($picked['id'] ?? ''));

    if($serverId === ''){
        e2eFail('matched server has empty id', 1, ['host' => $linkInfo['host']]);
    }

    $out = $raw;
    $out['servers'] = [$picked];
    $out['renew_server_ids'] = [$serverId];
    $out['buy_server_ids'] = [$serverId];
    $dest = e2eIsolatedConfigPath();
    file_put_contents($dest, json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);

    return [
        'server' => $picked,
        'server_id' => $serverId,
        'link' => $linkInfo,
    ];
}

function e2eBindIsolatedEnv(){
    $wd = e2eWorkdir();
    $locks = $wd . '/locks';
    @mkdir($locks, 0750, true);
    putenv('PNV_E2E_WORKDIR=' . $wd);
    putenv('PNV_RENEW_RESERVE_PATH=' . $wd . '/renew_reservations.json');
    putenv('PNV_LOCKS_DIR=' . $locks);
    putenv('PNV_PAYMENTS_CSV_PATH=' . $wd . '/payments.csv');
    putenv('PNV_XUI_CONFIG_PATH=' . e2eIsolatedConfigPath());
    putenv('PNV_PLANS_CATALOG_PATH=' . $wd . '/plans.json');

    if(!is_file($wd . '/plans.json')){
        file_put_contents($wd . '/plans.json', '[]');
    }
}

function e2eEnsureIsolatedConfig($writeIfMissing = true){
    $linkInfo = e2eParseSubLinkOrFail();
    $raw = e2eLoadSourceConfig();
    $picked = e2eFindUniqueServerForHost($raw, $linkInfo['host']);
    $serverId = trim((string)($picked['id'] ?? ''));
    $dest = e2eIsolatedConfigPath();

    if($writeIfMissing && !is_file($dest)){
        if($serverId === ''){
            e2eFail('matched server has empty id', 1, ['host' => $linkInfo['host']]);
        }

        $out = $raw;
        $out['servers'] = [$picked];
        $out['renew_server_ids'] = [$serverId];
        $out['buy_server_ids'] = [$serverId];
        file_put_contents($dest, json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
    }
    elseif(is_file($dest)){
        $cached = json_decode((string)file_get_contents($dest), true);
        $cachedId = trim((string)($cached['servers'][0]['id'] ?? ''));

        if($cachedId !== '' && $cachedId !== $serverId){
            e2eFail('sub link host resolves to different server than isolated config; run clean then init', 1, [
                'resolved_server_id' => $serverId,
                'cached_server_id' => $cachedId,
            ]);
        }
    }
    else{
        e2eFail('isolated XUI config missing; run --step=init first');
    }

    return [
        'server' => $picked,
        'server_id' => $serverId,
        'link' => $linkInfo,
    ];
}

function e2eRequireTestTarget(){
    $allowedEmail = e2eEnv('PNV_E2E_ALLOWED_EMAIL');
    $username = e2eEnv('PNV_E2E_USERNAME', 'pnv_e2e_user');

    if($allowedEmail === ''){
        e2eFail('set PNV_E2E_ALLOWED_EMAIL');
    }

    e2eBindIsolatedEnv();
    $resolved = e2eEnsureIsolatedConfig(false);
    $linkInfo = $resolved['link'];
    $subLink = $linkInfo['sub_link'];
    $parsed = $linkInfo['parsed'];

    $config = xuiLoadConfig();
    $servers = array_values(array_filter($config['servers'] ?? [], 'is_array'));

    if(count($servers) !== 1){
        e2eFail('isolated XUI config must contain exactly one server', 1, ['count' => count($servers)]);
    }

    $server = $servers[0];

    if(strtolower(trim((string)($server['host'] ?? ''))) !== strtolower($linkInfo['host'])){
        e2eFail('isolated server host does not match sub link', 1, [
            'link_host' => $linkInfo['host'],
            'server_host' => $server['host'] ?? '',
        ]);
    }

    $lookup = xuiRenewLookupClient($server, $linkInfo['sub_id'], $subLink);

    if(($lookup['status'] ?? '') !== 'found' || !is_array($lookup['client'] ?? null)){
        e2eFail('test client not found on resolved XUI server', 1, [
            'lookup' => $lookup['status'] ?? '',
            'server_id' => $server['id'] ?? '',
        ]);
    }

    $clientSub = trim((string)($lookup['client']['subId'] ?? ''));

    if($clientSub !== '' && $clientSub !== $linkInfo['sub_id']){
        e2eFail('XUI client subId mismatch', 1, [
            'xui_sub_id' => $clientSub,
            'allowed' => $linkInfo['sub_id'],
        ]);
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
        'server_id' => trim((string)($server['id'] ?? '')),
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
    @unlink(e2eIsolatedConfigPath());
    $extracted = e2eEnsureIsolatedConfig(true);
    $target = e2eRequireTestTarget();
    $state = array_merge(e2eLoadState(), [
        'server_id' => $target['server_id'],
        'host' => $target['server']['host'] ?? '',
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
        'server_id' => $target['server_id'],
        'host' => $target['server']['host'] ?? '',
        'client' => e2eClientFingerprint($target['client']),
        'runtime' => xuiRenewAnalyzeRuntimeState($target['server'], $target['client'], $target['sub_link']),
        'resolved_from' => $extracted['link']['host'] ?? '',
    ]);
}

function e2ePlanGbDays(){
    $gb = max(1, intval(e2eEnv('PNV_E2E_PLAN_GB', '2')));
    $days = max(1, intval(e2eEnv('PNV_E2E_PLAN_DAYS', '7')));

    return [$gb, $days];
}

function e2eStepReserve(){
    e2eBindIsolatedEnv();
    e2eEnsureIsolatedConfig(false);
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
        'server_id' => $target['server_id'],
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
        'server_id' => $target['server_id'],
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
        'server_id' => $target['server_id'],
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
        'server_id' => $target['server_id'],
        'state' => e2eLoadState(),
        'client' => e2eClientFingerprint($target['client']),
        'runtime' => xuiRenewAnalyzeRuntimeState($target['server'], $target['client'], $target['sub_link']),
        'reservations' => renewReserveLoad(),
    ]);
}

function e2eStepClean(){
    $wd = e2eWorkdir();
    foreach(['renew_reservations.json', 'payments.csv', 'e2e_state.json', 'xui_servers.e2e.json'] as $f){
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
