<?php
/**
 * تشخیص خطای 500 داشبورد — بعد از رفع مشکل این فایل را حذف کنید.
 * Usage: وارد پنل شوید، سپس باز کنید: https://panel.ticketin.ir/diag-dashboard.php
 */

declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');

$root = __DIR__;
$steps = [];

$record = static function(string $name, callable $fn) use (&$steps): void {
    try {
        $result = $fn();
        $steps[] = [
            'step' => $name,
            'ok' => true,
            'detail' => is_string($result) ? $result : (is_scalar($result) ? (string)$result : json_encode($result, JSON_UNESCAPED_UNICODE)),
        ];
    }
    catch(Throwable $e){
        $steps[] = [
            'step' => $name,
            'ok' => false,
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ];
    }
};

$record('php_version', static function(){
    return 'PHP ' . PHP_VERSION;
});

$record('session_start', static function(){
    if(session_status() !== PHP_SESSION_ACTIVE){
        session_start();
    }

    return 'session_id=' . session_id();
});

$record('session_user', static function(){
    $user = $_SESSION['user'] ?? '';

    if($user === ''){
        throw new RuntimeException('وارد نشده‌اید — اول login کنید بعد این صفحه را باز کنید');
    }

    return 'user=' . $user;
});

$files = [
    'dashboard.php',
    'dashboard_lib.php',
    'profile_lib.php',
    'subscription_lib.php',
    'support_lib.php',
    'pnv_date_bootstrap.php',
    'date_lib.php',
    'db/support.json',
    'db/users.json',
    'invoices/payments.csv',
];

foreach($files as $file){
    $record('file:' . $file, static function() use ($root, $file){
        $path = $root . '/' . $file;

        if(!is_file($path)){
            throw new RuntimeException('فایل نیست: ' . $path);
        }

        return 'size=' . filesize($path) . ' readable=' . (is_readable($path) ? 'yes' : 'no');
    });
}

$record('require profile_lib.php', static function() use ($root){
    require_once $root . '/profile_lib.php';
    return 'loaded';
});

$record('require subscription_lib.php', static function() use ($root){
    require_once $root . '/subscription_lib.php';
    return 'loaded';
});

$record('require support_lib.php', static function() use ($root){
    require_once $root . '/support_lib.php';
    return 'loaded';
});

$record('require dashboard_lib.php', static function() use ($root){
    require_once $root . '/dashboard_lib.php';
    return 'loaded';
});

$functions = [
    'profileGetUserAvatar',
    'pnvDashboardUserPaymentStats',
    'supportUserHasUnread',
    'pnvLoadUserActiveSubscriptions',
];

foreach($functions as $fn){
    $record('function:' . $fn, static function() use ($fn){
        if(!function_exists($fn)){
            throw new RuntimeException('تابع تعریف نشده — احتمالاً فایل lib ناقص deploy شده');
        }

        return 'exists';
    });
}

$record('call profileGetUserAvatar', static function(){
    $user = (string)($_SESSION['user'] ?? '');
    $url = profileGetUserAvatar($user);
    return $url === '' ? '(empty avatar url)' : $url;
});

$record('call supportUserHasUnread', static function(){
    $user = (string)($_SESSION['user'] ?? '');
    return supportUserHasUnread($user) ? 'unread=yes' : 'unread=no';
});

$record('call pnvDashboardUserPaymentStats', static function(){
    $user = (string)($_SESSION['user'] ?? '');
    $stats = pnvDashboardUserPaymentStats($user);
    return json_encode($stats, JSON_UNESCAPED_UNICODE);
});

$record('call pnvLoadUserActiveSubscriptions(false)', static function(){
    $user = (string)($_SESSION['user'] ?? '');
    $subs = pnvLoadUserActiveSubscriptions($user, false);
    return 'count=' . count($subs);
});

$record('include dashboard.php (dry run)', static function() use ($root){
    $path = $root . '/dashboard.php';
    $src = (string)file_get_contents($path);

    if($src === ''){
        throw new RuntimeException('dashboard.php خالی است');
    }

    if(strpos($src, 'pnvDashboardUserPaymentStats') !== false && !function_exists('pnvDashboardUserPaymentStats')){
        throw new RuntimeException('dashboard.php به pnvDashboardUserPaymentStats نیاز دارد ولی در subscription_lib.php نیست');
    }

    if(strpos($src, 'supportUserHasUnread') !== false && !function_exists('supportUserHasUnread')){
        throw new RuntimeException('dashboard.php به supportUserHasUnread نیاز دارد ولی در support_lib.php نیست');
    }

    return 'dashboard.php syntax/reference checks passed';
});

$failed = array_values(array_filter($steps, static function($step){
    return empty($step['ok']);
}));

echo "=== Dashboard diagnostics ===\n";
echo 'time: ' . date('c') . "\n\n";

foreach($steps as $step){
    $mark = !empty($step['ok']) ? 'OK ' : 'FAIL';
    echo "[{$mark}] {$step['step']}\n";

    if(!empty($step['ok'])){
        if(!empty($step['detail'])){
            echo "      {$step['detail']}\n";
        }
    }
    else{
        echo "      ERROR: {$step['error']}\n";
        echo "      at {$step['file']}:{$step['line']}\n";
    }
}

echo "\n";

if(count($failed) > 0){
    echo "RESULT: FAILED (" . count($failed) . " step(s))\n";
    echo "First failure is usually the root cause of HTTP 500.\n";
    http_response_code(500);
}
else{
    echo "RESULT: ALL CHECKS PASSED\n";
    echo "If dashboard still shows 500, check Apache/Nginx/PHP-FPM error log on the server.\n";
}
