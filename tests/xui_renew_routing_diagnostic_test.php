<?php
/**
 * Diagnose XUI routes test: fixture subprocess; no network and no real credentials.
 */
$failed = 0;
function routingAssert($desc, $ok){
    global $failed;
    echo ($ok ? 'PASS ' : 'FAIL ') . $desc . "\n";
    if(!$ok) $failed++;
}
$script = __DIR__ . '/../scripts/diagnose-xui-renew-routing.php';
$lintOut = [];
$lintCode = 0;
exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($script) . ' 2>&1', $lintOut, $lintCode);
routingAssert('routing CLI PHP syntax valid', $lintCode === 0);

$tmp = sys_get_temp_dir() . '/pnv_xui_diag_' . getmypid() . '_' . bin2hex(random_bytes(4));
mkdir($tmp . '/db', 0700, true);
$secret = 'SHOULD_NOT_LEAK_PASSWORD';
$cfg = [
    'servers' => [[
        'id' => 'mock',
        'base_url' => 'https://example.test/panel',
        'api_token' => 'SHOULD_NOT_LEAK_TOKEN',
        'password' => $secret,
        'username' => 'internal',
    ]],
];
file_put_contents($tmp . '/db/xui_servers.json', json_encode($cfg));
$stub = <<<'PHP'
<?php
function xuiLoadConfig(){
    return json_decode(file_get_contents(getenv('PNV_XUI_CONFIG_PATH')), true);
}
function xuiServerHasAuth($server){
    return !empty($server['api_token']);
}
function xuiApiRequest($server, $method, $path){
    if($method !== 'GET') throw new RuntimeException('MUTATING REQUEST');
    if(!in_array($path, ['/panel/api/inbounds/list', '/panel/api/inbounds/options'], true)) throw new RuntimeException('INVALID ROUTE');
    return ['success' => true, 'http' => 200];
}
PHP;
file_put_contents($tmp . '/xui_lib.php', $stub);
$command = 'PNV_PANEL_ROOT=' . escapeshellarg($tmp) .
    ' PNV_XUI_CONFIG_PATH=' . escapeshellarg($tmp . '/db/xui_servers.json') .
    ' ' . escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script) . ' 2>&1';
$output = [];
$status = 0;
exec($command, $output, $status);
$text = implode("\n", $output);
$header = json_decode($output[0] ?? '', true);
$row = json_decode($output[1] ?? '', true);
routingAssert('fixture diagnostic exits successfully', $status === 0);
routingAssert('only read-only mode announced', ($header['mode'] ?? '') === 'READ_ONLY_GET_DIAGNOSTIC');
routingAssert('panel-containing base path flagged', !empty($row['base_path_contains_panel']));
routingAssert('both GET routes probed', ($row['get_inbounds_list']['http'] ?? 0) === 200 && ($row['get_inbounds_options']['http'] ?? 0) === 200);
routingAssert('no credentials or base URL disclosed', strpos($text, 'SHOULD_NOT_LEAK') === false && strpos($text, 'example.test') === false);

@unlink($tmp . '/xui_lib.php');
@unlink($tmp . '/db/xui_servers.json');
@rmdir($tmp . '/db');
@rmdir($tmp);
if($failed){
    fwrite(STDERR, "$failed routing diagnostic test(s) failed\n");
    exit(1);
}
echo "XUI routing diagnostic tests passed\n";
