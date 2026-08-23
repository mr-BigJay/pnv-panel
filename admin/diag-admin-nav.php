<?php
/**
 * تشخیص خطای admin_nav — بعد از رفع مشکل حذف کنید.
 * https://panel.ticketin.ir/bigjay_controller/diag-admin-nav.php
 */

header('Content-Type: text/plain; charset=utf-8');

$root = __DIR__;
$navFile = $root . '/admin_nav.php';

echo "=== admin_nav diagnostics ===\n";
echo 'time: ' . date('c') . "\n";
echo 'php: ' . PHP_VERSION . "\n";
echo 'file: ' . $navFile . "\n";
echo 'exists: ' . (is_file($navFile) ? 'yes' : 'no') . "\n";

if(is_file($navFile)){
    echo 'size: ' . filesize($navFile) . "\n";
    echo 'marker: ';
    $head = (string)file_get_contents($navFile, false, null, 0, 120);
    echo (strpos($head, 'admin_nav') !== false ? 'readable' : 'unknown') . "\n";
}

$required = [
    'adminBottomNavStyles',
    'adminMgmtMenuItems',
    'adminMgmtDrawer',
    'adminBottomNav',
    'adminBottomNavScript',
    'adminPageEnd',
];

echo "\nrequire admin_nav.php ...\n";

try{
    require_once $navFile;
    echo "OK require\n";
}
catch(Throwable $e){
    echo "FAIL require: " . $e->getMessage() . "\n";
    echo $e->getFile() . ':' . $e->getLine() . "\n";
    http_response_code(500);
    exit;
}

foreach($required as $fn){
    echo (function_exists($fn) ? 'OK  ' : 'MISS ') . $fn . "\n";
}

if(function_exists('adminPageEnd')){
    ob_start();
    try{
        adminPageEnd(['active' => 'dashboard', 'badges' => []]);
        $out = ob_get_clean();
        echo "\nOK adminPageEnd output bytes: " . strlen($out) . "\n";
    }
    catch(Throwable $e){
        ob_end_clean();
        echo "\nFAIL adminPageEnd: " . $e->getMessage() . "\n";
        echo $e->getFile() . ':' . $e->getLine() . "\n";
        http_response_code(500);
        exit;
    }
}

echo "\nRESULT: OK\n";
