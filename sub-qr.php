<?php

session_start();

if(!isset($_SESSION['user'])){
    http_response_code(401);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'ورود لازم است';
    exit;
}

require_once __DIR__ . '/phpqrcode/qrlib.php';

$link = trim((string)($_GET['u'] ?? $_GET['link'] ?? ''));

if($link === ''){
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'لینک خالی است';
    exit;
}

$allowed = false;
$hosts = [
    'vip.boozhaan.ir',
    'vip2.boozhaan.ir',
    'vip3.boozhaan.ir',
    'vip4.boozhaan.ir',
];

foreach($hosts as $host){
    if(preg_match('#^https?://' . preg_quote($host, '#') . '(?::\d+)?/sub/[A-Za-z0-9]+$#i', $link)){
        $allowed = true;
        break;
    }
}

if(!$allowed){
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'لینک نامعتبر است';
    exit;
}

header('Content-Type: image/png');
header('Cache-Control: private, max-age=300');
QRcode::png($link, false, QR_ECLEVEL_L, 8, 2);
