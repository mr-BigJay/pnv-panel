<?php

if(session_status() === PHP_SESSION_NONE){
    session_start();
}

if(!defined('PNV_ADMIN_BASE')){
    define('PNV_ADMIN_BASE', '/bigjay_controller');
}

function pnvAdminCredentialsPath(){

    return dirname(__DIR__) . '/db/admins.json';

}

function pnvAdminIsLoggedIn(){

    $ok = !empty($_SESSION['pnv_admin']['user'])
        && !empty($_SESSION['pnv_admin']['token']);

    if($ok){
        $_SESSION['admin'] = true;
    }
    elseif(!empty($_SESSION['admin']) && empty($_SESSION['pnv_admin'])){
        // سشن قدیمی بدون توکن امن پذیرفته نمی‌شود
        unset($_SESSION['admin']);
    }

    return $ok;

}

function pnvAdminUser(){

    return $_SESSION['pnv_admin']['user'] ?? '';

}

function pnvAdminValidateLogin($username, $password){

    $path = pnvAdminCredentialsPath();

    if(!file_exists($path)){
        return null;
    }

    $admins = json_decode(file_get_contents($path), true);

    if(!is_array($admins)){
        return null;
    }

    foreach($admins as $admin){

        if(
            ($admin['username'] ?? '') === $username
            &&
            ($admin['status'] ?? 'active') === 'active'
            &&
            ($admin['password'] ?? '') === $password
        ){
            return $admin;
        }

    }

    return null;

}

function pnvAdminLogin($admin){

    session_regenerate_id(true);

    $_SESSION['pnv_admin'] = [
        'user' => $admin['username'] ?? '',
        'role' => $admin['role'] ?? 'admin',
        'login_at' => time(),
        'token' => bin2hex(random_bytes(16))
    ];

    // سازگاری با صفحات قدیمی که هنوز $_SESSION['admin'] را چک می‌کنند
    $_SESSION['admin'] = true;

}

function pnvAdminLogout(){

    unset($_SESSION['pnv_admin'], $_SESSION['admin']);
    session_regenerate_id(true);

}

function pnvAdminRequireAuth(){

    if(pnvAdminIsLoggedIn()){
        // همگام‌سازی فلگ قدیمی
        $_SESSION['admin'] = true;
        return;
    }

    header('Location: ' . pnvAdminEntryUrl());
    exit;

}

function pnvAdminEntryUrl(){

    return rtrim(PNV_ADMIN_BASE, '/') . '/';

}

function pnvAdminUrl($path = 'index.php'){

    $base = rtrim(PNV_ADMIN_BASE, '/');

    if($path === '' || $path === 'index.php'){
        return $base . '/';
    }

    if(strpos($path, '?') !== false){
        [$file, $query] = explode('?', $path, 2);
        $file = ltrim($file, '/');
        return $base . '/' . $file . '?' . $query;
    }

    return $base . '/' . ltrim($path, '/');

}

function pnvGregorianToJalali($gy, $gm, $gd){

    $g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
    $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
    $days = 355666 + (365 * $gy) + intdiv($gy2 + 3, 4) - intdiv($gy2 + 99, 100) + intdiv($gy2 + 399, 400) + $gd + $g_d_m[$gm - 1];
    $jy = -1595 + (33 * intdiv($days, 12053));
    $days %= 12053;
    $jy += 4 * intdiv($days, 1461);
    $days %= 1461;

    if($days > 365){
        $jy += intdiv($days - 1, 365);
        $days = ($days - 1) % 365;
    }

    if($days < 186){
        $jm = 1 + intdiv($days, 31);
        $jd = 1 + ($days % 31);
    }
    else{
        $jm = 7 + intdiv($days - 186, 30);
        $jd = 1 + (($days - 186) % 30);
    }

    return [$jy, $jm, $jd];

}

function pnvJalaliToday($separator = '/'){

    [$jy, $jm, $jd] = pnvGregorianToJalali(
        intval(date('Y')),
        intval(date('n')),
        intval(date('j'))
    );

    return sprintf('%04d%s%02d%s%02d', $jy, $separator, $jm, $separator, $jd);

}
