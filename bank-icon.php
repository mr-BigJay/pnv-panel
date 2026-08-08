<?php

/**
 * سرو لوگوی بانک — بدون وابستگی به پوشه /assets/ در وب‌سرور
 */
$bank = preg_replace('/[^a-z0-9\-]/', '', strtolower((string)($_GET['b'] ?? '')));

if($bank === ''){
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'bad bank id';
    exit;
}

$candidates = [
    __DIR__ . '/assets/bank-logos/' . $bank . '.svg',
    __DIR__ . '/banks/' . $bank . '.svg',
];

$path = '';
foreach($candidates as $candidate){
    if(is_file($candidate)){
        $path = $candidate;
        break;
    }
}

if($path === ''){
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'not found';
    exit;
}

header('Content-Type: image/svg+xml; charset=utf-8');
header('Cache-Control: public, max-age=604800');
header('X-Content-Type-Options: nosniff');
readfile($path);
