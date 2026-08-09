<?php

require_once dirname(__DIR__) . '/date_lib.php';

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

    $changed = false;

    foreach($admins as $i => $admin){

        if(
            ($admin['username'] ?? '') !== $username
            ||
            ($admin['status'] ?? 'active') !== 'active'
        ){
            continue;
        }

        $stored = (string)($admin['password'] ?? '');
        $ok = false;

        if(strpos($stored, '$2y$') === 0 || strpos($stored, '$2a$') === 0 || strpos($stored, '$2b$') === 0){
            $ok = password_verify($password, $stored);
        }
        elseif(hash_equals($stored, $password)){
            $ok = true;
            // ارتقای نرم به هش
            $admins[$i]['password'] = password_hash($password, PASSWORD_DEFAULT);
            $changed = true;
            $admin = $admins[$i];
        }

        if($ok){
            if($changed){
                file_put_contents(
                    $path,
                    json_encode($admins, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
                    LOCK_EX
                );
            }

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
