<?php

session_start();

if(!isset($_SESSION['user'])){
    http_response_code(403);
    exit;
}

require_once __DIR__ . '/subscription_lib.php';
require_once __DIR__ . '/phpqrcode/qrlib.php';

$user = (string)$_SESSION['user'];
$link = trim((string)($_GET['link'] ?? $_GET['u'] ?? ''));

if($link === '' || !pnvIsValidSubLink($link)){
    http_response_code(400);
    exit;
}

$link = pnvNormalizeSubLinkValue($link);

if(!pnvUserCanViewSubQr($user, $link)){
    http_response_code(404);
    exit;
}

header('Content-Type: image/png');
header('Cache-Control: private, max-age=604800');
QRcode::png($link, false, QR_ECLEVEL_L, 6);
