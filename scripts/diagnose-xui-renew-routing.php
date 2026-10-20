<?php
/**
 * Safe read-only 3x-ui routing diagnostic for manual renew HTTP 404.
 * Usage: PNV_PANEL_ROOT=/var/www/html php scripts/diagnose-xui-renew-routing.php
 *
 * Only GET routes are probed (no renewal, no payment state, no POST).
 * Does not print credentials, hosts, base paths, transaction IDs or clients.
 */
if(PHP_SAPI !== 'cli'){
    http_response_code(403);
    exit(1);
}

$root = getenv('PNV_PANEL_ROOT');
$root = is_string($root) && $root !== '' ? $root : dirname(__DIR__);
$root = realpath($root);
if($root === false || !is_file($root . '/xui_lib.php')){
    fwrite(STDERR, "ERROR: invalid panel root\n");
    exit(2);
}

$configPath = getenv('PNV_XUI_CONFIG_PATH');
if(!is_string($configPath) || $configPath === ''){
    $configPath = $root . '/db/xui_servers.json';
}
if(!is_file($configPath)){
    fwrite(STDERR, "ERROR: configured xui_servers.json is missing; refusing default examples\n");
    exit(2);
}

putenv('PNV_XUI_CONFIG_PATH=' . $configPath);
require_once $root . '/xui_lib.php';

$source = file_get_contents($root . '/xui_lib.php');
if(!is_string($source)){
    fwrite(STDERR, "ERROR: cannot inspect xui_lib.php\n");
    exit(2);
}
$features = [
    'form_request_available' => function_exists('xuiApiRequestForm'),
    'modern_client_update' => strpos($source, '/panel/api/clients/update/') !== false,
    'legacy_form_in_adjust' => strpos($source, "xuiAdjustClientTrafficLegacy") !== false
        && strpos($source, "xuiApiRequestForm(\$server, 'POST', \$path") !== false,
    'absolute_fallback' => strpos($source, 'updateClient_absolute_form') !== false,
];
$config = xuiLoadConfig();
$servers = $config['servers'] ?? [];

echo json_encode([
    'mode' => 'READ_ONLY_GET_DIAGNOSTIC',
    'source_features' => $features,
    'configured_servers' => count(is_array($servers) ? $servers : []),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";

if(!is_array($servers)){
    exit(2);
}

foreach($servers as $idx => $server){
    if(!is_array($server)){
        continue;
    }
    $label = trim((string)($server['id'] ?? 'server_' . ($idx + 1)));
    // Do not leak host, token, password, cookie or path.
    $base = trim((string)($server['base_url'] ?? ''));
    $parsed = parse_url($base);
    $path = is_array($parsed) ? trim((string)($parsed['path'] ?? ''), '/') : '';
    $baseSuspect = preg_match('~(?:^|/)panel(?:/|$)~i', $path) === 1;
    $hasAuth = xuiServerHasAuth($server);

    $out = [
        'server_id' => $label,
        'configured' => $base !== '',
        'auth_configured' => $hasAuth,
        'base_path_contains_panel' => $baseSuspect,
        'get_inbounds_list' => 'NOT_TESTED',
        'get_inbounds_options' => 'NOT_TESTED',
    ];

    if($base !== '' && $hasAuth){
        foreach([
            'get_inbounds_list' => '/panel/api/inbounds/list',
            'get_inbounds_options' => '/panel/api/inbounds/options',
        ] as $name => $urlPath){
            $result = xuiApiRequest($server, 'GET', $urlPath);
            $out[$name] = [
                'http' => intval($result['http'] ?? 0),
                'success' => !empty($result['success']),
            ];
        }
    }
    echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
}
