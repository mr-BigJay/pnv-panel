<?php
/**
 * تشخیص خطای plans.php — بعد از رفع مشکل حذف کنید.
 * https://panel.ticketin.ir/bigjay_controller/diag-plans.php
 */

header('Content-Type: text/plain; charset=utf-8');

$adminDir = __DIR__;
$root = dirname($adminDir);

echo "=== plans.php diagnostics ===\n";
echo 'time: ' . date('c') . "\n";
echo 'php: ' . PHP_VERSION . "\n";
echo 'admin_dir: ' . $adminDir . "\n";
echo 'root: ' . $root . "\n";

$checks = [
    'admin/plans.php' => $adminDir . '/plans.php',
    'bigjay_controller/plans.php' => $root . '/bigjay_controller/plans.php',
    'admin/auth.php' => $adminDir . '/auth.php',
    'admin/admin_nav.php' => $adminDir . '/admin_nav.php',
    'admin/functions.php' => $adminDir . '/functions.php',
    'form_validation_fa.php' => $root . '/form_validation_fa.php',
    'db/plans.json' => $root . '/db/plans.json',
];

foreach($checks as $label => $path){
    echo $label . ': ' . (is_file($path) ? 'yes' : 'MISS') . ' (' . $path . ")\n";
}

$plansFile = $root . '/db/plans.json';
if(is_file($plansFile)){
    $raw = file_get_contents($plansFile);
    $plans = json_decode($raw, true);
    echo 'plans.json bytes: ' . strlen($raw) . "\n";
    echo 'plans decode: ' . (is_array($plans) ? 'array count=' . count($plans) : 'INVALID JSON') . "\n";
    if(is_array($plans) && count($plans) > 0){
        echo 'first keys: ' . implode(', ', array_keys($plans[0])) . "\n";
    }
}

echo "\nrequire auth + admin_nav + functions ...\n";

try{
    require_once $adminDir . '/auth.php';
    require_once $adminDir . '/admin_nav.php';
    require_once $adminDir . '/functions.php';
    echo "OK require core files\n";
}
catch(Throwable $e){
    echo "FAIL require: " . $e->getMessage() . "\n";
    echo $e->getFile() . ':' . $e->getLine() . "\n";
    http_response_code(500);
    exit;
}

$required = ['pnvAdminUrl', 'adminQuickNav', 'adminPageEnd', 'formatPrice', 'pnvFormValidationFaScript'];
foreach($required as $fn){
    echo (function_exists($fn) ? 'OK  ' : 'MISS ') . $fn . "\n";
}

echo "\nSimulate plans list render ...\n";

try{
    if(!is_array($plans ?? null)){
        $plans = [];
    }

    ob_start();
    foreach($plans as $i => $p){
        if(!is_array($p)){
            throw new RuntimeException('plan row ' . $i . ' is not array');
        }
        formatPrice($p['price'] ?? 0);
    }
    ob_end_clean();
    echo "OK foreach " . count($plans) . " plans\n";
}
catch(Throwable $e){
    if(ob_get_level() > 0){
        ob_end_clean();
    }
    echo "FAIL render: " . $e->getMessage() . "\n";
    http_response_code(500);
    exit;
}

echo "\nRESULT: OK\n";
