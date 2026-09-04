<?php

require_once __DIR__ . '/auth.php';

header('Content-Type: text/plain; charset=utf-8');

if(!pnvAdminIsLoggedIn()){
    http_response_code(403);
    echo "forbidden\n";
    exit;
}

$root = dirname(__DIR__);
$checks = [
    'support_lib.php' => $root . '/support_lib.php',
    'admin/support-api.php' => __DIR__ . '/support-api.php',
    'admin/support-v2.php' => __DIR__ . '/support-v2.php',
    'db/support.json' => $root . '/db/support.json',
    'assets/support/admin/support-admin.js' => $root . '/assets/support/admin/support-admin.js',
    'bigjay_controller/support-api.php' => $root . '/bigjay_controller/support-api.php',
];

echo "=== support v2 diagnostics ===\n";
echo 'PHP ' . PHP_VERSION . "\n";
echo 'Time ' . date('c') . "\n\n";

foreach($checks as $label => $path){
    $ok = is_file($path);
    $size = $ok ? filesize($path) : 0;
    echo ($ok ? 'OK' : 'MISSING') . "  {$label}  ({$size} bytes)\n";
    if($ok && $label === 'support_lib.php'){
        $src = file_get_contents($path);
        echo '  has supportTicketsListForApi: '
            . (strpos($src, 'function supportTicketsListForApi') !== false ? 'yes' : 'NO') . "\n";
    }
}

echo "\n";

require_once $root . '/support_lib.php';

$fnOk = function_exists('supportTicketsListForApi');
echo 'function supportTicketsListForApi: ' . ($fnOk ? 'yes' : 'NO') . "\n";

if($fnOk){
    $data = supportLoad($root . '/db/support.json');
    $list = supportTicketsListForApi($data);
    echo 'tickets count: ' . count($list['tickets'] ?? []) . "\n";
    $json = json_encode($list, JSON_UNESCAPED_UNICODE);
    echo 'json_encode: ' . ($json === false ? ('FAIL ' . json_last_error_msg()) : ('OK ' . strlen($json) . ' bytes')) . "\n";
}

echo "\nOpen in browser (no login needed):\n";
echo "  /bigjay_controller/support-api.php?action=ping\n";
