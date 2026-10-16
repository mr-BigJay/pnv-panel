<?php
if (PHP_SAPI !== 'cli') {
    exit(1);
}
$link = $argv[1] ?? '';
if ($link === '') {
    fwrite(STDERR, "Usage: php xui-trace-sub-readonly.php \"https://host:2096/sub/ID\"\n");
    exit(1);
}

$root = getenv('PNV_ROOT') ?: '/var/www/html';
require $root . '/xui_lib.php';
require $root . '/subscription_lib.php';

function xuiTraceMaskBaseUrl($url){
    $url = (string)$url;
    return preg_replace('#/[^/]+/$#', '/***MASKED***/', rtrim($url, '/') . '/');
}

$config = xuiLoadConfig();
$parsed = xuiParseSubLink($link);
$out = ['link' => $link, 'parsed' => $parsed];

if (!$parsed) {
    echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
    exit(0);
}

$server = xuiFindServerByHost($parsed['host'], $config);
$renewIds = $config['renew_server_ids'] ?? [];
$out['renew_server_ids'] = is_array($renewIds) ? $renewIds : [];
$out['server'] = null;
$out['renew_allowed'] = false;

if ($server) {
    $out['server'] = [
        'id' => $server['id'] ?? '',
        'host' => $server['host'] ?? '',
        'base_url' => xuiTraceMaskBaseUrl($server['base_url'] ?? ''),
        'has_token' => function_exists('xuiServerHasToken') ? xuiServerHasToken($server) : null,
        'has_session_creds' => function_exists('xuiServerHasSessionCredentials') ? xuiServerHasSessionCredentials($server) : null,
    ];
    $out['renew_allowed'] = in_array($server['id'] ?? '', $out['renew_server_ids'], true);
}

if ($server) {
    $sid = $parsed['sub_id'];
    $probe = function ($label, $method, $path, $body = null) use ($server) {
        $r = xuiApiRequest($server, $method, $path, $body);
        $obj = $r['obj'] ?? null;
        $summary = null;
        if (is_array($obj)) {
            if (isset($obj['list']) && is_array($obj['list'])) {
                $summary = ['list_count' => count($obj['list'])];
            } elseif (isset($obj[0])) {
                $summary = ['array_count' => count($obj)];
            } else {
                $summary = ['keys' => array_keys($obj)];
            }
        }
        return [
            'step' => $label,
            'success' => !empty($r['success']),
            'msg' => $r['msg'] ?? null,
            'http' => $r['http'] ?? null,
            'obj_summary' => $summary,
        ];
    };
    $searchPath = '/panel/api/clients/list/paged?page=1&pageSize=50&search=' . rawurlencode($sid)
        . '&filter=&protocol=&sort=email&order=ascend';
    $out['api_probes'] = [
        'clients_search' => $probe('clients_search', 'GET', $searchPath),
        'subLinks' => $probe('subLinks', 'GET', '/panel/api/clients/subLinks/' . rawurlencode($sid)),
        'inbounds_list' => $probe('inbounds_list', 'GET', '/panel/api/inbounds/list'),
    ];
    $found = xuiFindClientBySubId($server, $sid, $link);
    $out['findClientBySubId'] = $found ? 'FOUND' : 'NOT_FOUND';
    if ($found) {
        $out['findClientBySubId_client'] = [
            'email' => $found['email'] ?? '',
            'subId' => $found['subId'] ?? '',
            'id' => $found['id'] ?? '',
        ];
    }
    $out['public_sub'] = [
        'uuid_from_sub' => xuiFetchSubUuid($link) ?: null,
        'email_from_sub' => xuiFetchSubEmail($link) ?: null,
    ];
    $httpProbe = function_exists('curl_init') ? (function () use ($link) {
        $curl = curl_init($link);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_NOBODY, true);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($curl, CURLOPT_TIMEOUT, 15);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_exec($curl);
        $code = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        return $code;
    })() : null;
    $out['public_sub_http_head'] = $httpProbe;
}

$out['cross_server_scan'] = [];
foreach (($config['servers'] ?? []) as $s) {
    if (!is_array($s)) {
        continue;
    }
    $c = xuiFindClientBySubId($s, $parsed['sub_id'], '');
    if ($c) {
        $out['cross_server_scan'][] = [
            'server_id' => $s['id'] ?? '',
            'host' => $s['host'] ?? '',
            'email' => $c['email'] ?? '',
            'subId' => $c['subId'] ?? '',
        ];
    }
}

echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
