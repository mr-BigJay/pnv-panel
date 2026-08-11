<?php
/**
 * Run on server: php /var/www/html/admin/health-check.php
 * Lightweight — does not start PHP sessions (avoids lock/hang).
 */
if(PHP_SAPI !== 'cli'){
    header('Content-Type: text/plain; charset=utf-8');
}

$root = dirname(__DIR__);
$errors = [];
$ok = [];

function hc($label, $pass, $detail = ''){
    global $errors, $ok;
    if($pass){
        $ok[] = $label . ($detail ? " ($detail)" : '');
    }
    else{
        $errors[] = $label . ($detail ? " — $detail" : '');
    }
}

hc('PHP version >= 7.4', version_compare(PHP_VERSION, '7.4.0', '>='), PHP_VERSION);

$files = [
    'admin/index.php',
    'admin/auth.php',
    'admin/functions.php',
    'admin/admin_nav.php',
    'admin/downloads.php',
    'bigjay_controller/index.php',
    'bigjay_controller/auth.php',
    'bigjay_controller/functions.php',
    'profile_lib.php',
    'pnv_date_bootstrap.php',
    'date_lib.php',
    'db/admins.json',
];

foreach($files as $rel){
    $path = $root . '/' . $rel;
    $exists = is_file($path);
    $size = $exists ? filesize($path) : 0;
    hc("file: $rel", $exists && $size > 0, $exists ? "{$size} bytes" : 'missing');
}

foreach(['admin/index.php', 'admin/downloads.php', 'bigjay_controller/index.php', 'profile_lib.php'] as $rel){
    $path = $root . '/' . $rel;
    if(!is_file($path)){
        continue;
    }
    $out = [];
    $code = 0;
    exec('php -l ' . escapeshellarg($path) . ' 2>&1', $out, $code);
    hc("syntax: $rel", $code === 0, $code ? implode(' ', $out) : 'ok');
}

// Check date helpers load without touching sessions
$dateBoot = $root . '/pnv_date_bootstrap.php';
if(is_file($dateBoot)){
    require_once $dateBoot;
    hc('pnvIsTodayTehran available', function_exists('pnvIsTodayTehran'));
    hc('pnvPaymentRowIsToday available', function_exists('pnvPaymentRowIsToday'));
}
else{
    hc('pnvIsTodayTehran available', false, 'pnv_date_bootstrap.php missing');
}

if(is_file($root . '/profile_lib.php')){
    require_once $root . '/profile_lib.php';
    hc('profileGetAdminAvatar available', function_exists('profileGetAdminAvatar'));
}

echo "=== OK ===\n";
foreach($ok as $line){
    echo "[OK] $line\n";
}

if(count($errors) > 0){
    echo "\n=== ERRORS ===\n";
    foreach($errors as $line){
        echo "[FAIL] $line\n";
    }
    exit(1);
}

echo "\nAll checks passed.\n";
