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

    if(!empty($_SESSION['admin']) && empty($_SESSION['pnv_admin'])){
        unset($_SESSION['admin']);
    }

    return !empty($_SESSION['pnv_admin']['user'])
        && !empty($_SESSION['pnv_admin']['token']);

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

    unset($_SESSION['admin']);

}

function pnvAdminLogout(){

    unset($_SESSION['pnv_admin'], $_SESSION['admin']);
    session_regenerate_id(true);

}

function pnvAdminRequireAuth(){

    if(pnvAdminIsLoggedIn()){
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
