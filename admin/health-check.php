<?php
/**
 * Run on server: php /var/www/html/admin/health-check.php
 * Or: curl https://panel.ticketin.ir/bigjay_controller/health-check.php (if wrapper exists)
 */
header('Content-Type: text/plain; charset=utf-8');

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
    'admin/downloads.php',
    'bigjay_controller/index.php',
    'bigjay_controller/auth.php',
    'bigjay_controller/functions.php',
    'profile_lib.php',
    'db/admins.json',
];

foreach($files as $rel){
    $path = $root . '/' . $rel;
    $exists = is_file($path);
    $size = $exists ? filesize($path) : 0;
    hc("file: $rel", $exists && $size > 0, $exists ? "{$size} bytes" : 'missing');
}

foreach(['admin/index.php', 'admin/downloads.php', 'bigjay_controller/index.php'] as $rel){
    $path = $root . '/' . $rel;
    if(!is_file($path)){
        continue;
    }
    $out = [];
    $code = 0;
    exec('php -l ' . escapeshellarg($path) . ' 2>&1', $out, $code);
    hc("syntax: $rel", $code === 0, $code ? implode(' ', $out) : 'ok');
}

// Simulate boot (empty stub scenario)
$authStub = $root . '/bigjay_controller/auth.php';
if(is_file($authStub)){
    ob_start();
    try {
        require_once $authStub;
        hc('bigjay_controller/auth.php loads', function_exists('pnvAdminIsLoggedIn'), 'pnvAdminIsLoggedIn');
    } catch(Throwable $e) {
        hc('bigjay_controller/auth.php loads', false, $e->getMessage());
    }
    ob_end_clean();
}

$profileLib = $root . '/profile_lib.php';
if(is_file($profileLib)){
    ob_start();
    try {
        require_once $profileLib;
        hc('profile_lib.php loads', function_exists('profileGetAdminAvatar'), 'profileGetAdminAvatar');
    } catch(Throwable $e) {
        hc('profile_lib.php loads', false, $e->getMessage());
    }
    ob_end_clean();
}
else{
    hc('profile_lib.php loads', false, 'file missing');
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
