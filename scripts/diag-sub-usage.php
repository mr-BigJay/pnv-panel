<?php

if(PHP_SAPI !== 'cli'){
    fwrite(STDERR, "Run from CLI only.\n");
    exit(1);
}

$link = trim((string)($argv[1] ?? ''));

if($link === '' || stripos($link, 'لینک') !== false || !preg_match('#/sub/[A-Za-z0-9]+#', $link)){
    fwrite(STDERR, "Usage: php scripts/diag-sub-usage.php \"https://vip.boozhaan.ir:2096/sub/YOUR_SUB_ID\"\n");
    exit(1);
}

require_once dirname(__DIR__) . '/xui_lib.php';
require_once dirname(__DIR__) . '/plan_ui_lib.php';
require_once dirname(__DIR__) . '/sub_usage_lib.php';

$planText = trim((string)($argv[2] ?? '10 گیگ - 250 هزار تومان'));
$out = [
    'link' => subUsageSanitizeLink($link),
    'plan_text' => $planText,
];

$config = xuiLoadConfig();
$out['xui_enabled'] = xuiIsEnabled($config);

$parsed = xuiParseSubLink($out['link']);
$out['parsed'] = $parsed;

$server = is_array($parsed) ? xuiFindServerByHost($parsed['host'], $config) : null;
$out['server_id'] = is_array($server) ? ($server['id'] ?? '') : '';
$out['server_auth'] = is_array($server) && function_exists('xuiServerHasAuth') ? xuiServerHasAuth($server) : false;

if(is_array($server) && is_array($parsed)){
    $email = function_exists('pnvFetchSubPanelEmail') ? pnvFetchSubPanelEmail($out['link']) : '';
    $out['email'] = $email;

    if($email !== ''){
        $full = xuiApiRequest($server, 'GET', '/panel/api/clients/get/' . rawurlencode($email));
        $out['clients_get_ok'] = !empty($full['success']);
        $out['clients_get_msg'] = $full['msg'] ?? '';

        if(!empty($full['success']) && is_array($full['obj'] ?? null)){
            $client = xuiNormalizeClientRecord($full['obj']['client'] ?? $full['obj']);
            $out['clients_get_used_gb'] = round(xuiClientUsedBytes($client) / 1024 / 1024 / 1024, 2);
            $out['clients_get_total_gb'] = round(xuiClientTotalBytes($client) / 1024 / 1024 / 1024, 2);
        }
    }

    $stat = xuiFindClientInInbounds($server, $parsed['sub_id']);
    if(is_array($stat)){
        $out['inbound_used_gb'] = round(xuiClientUsedBytes($stat) / 1024 / 1024 / 1024, 2);
        $out['inbound_total_gb'] = round(xuiClientTotalBytes($stat) / 1024 / 1024 / 1024, 2);
        $out['inbound_email'] = $stat['email'] ?? '';
    }
}

$view = subUsageRefreshOne($out['link'], [
    'plan_text' => $planText,
    'plan' => $planText,
]);

$out['usage'] = [
    'source' => $view['source'] ?? '',
    'label' => $view['volume']['label'] ?? '',
    'used_gb' => round(floatval($view['volume']['used_bytes'] ?? 0) / 1024 / 1024 / 1024, 2),
    'total_gb' => round(floatval($view['volume']['total_bytes'] ?? 0) / 1024 / 1024 / 1024, 2),
    'remain_pct' => floatval($view['volume']['remain_pct'] ?? 0),
];

echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
