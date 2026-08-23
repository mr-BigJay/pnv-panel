<?php
$target = dirname(__DIR__) . '/admin/diag-admin-nav.php';
if(is_file($target)){
    require $target;
    return;
}
if(is_file(__DIR__ . '/../admin/diag-admin-nav.php')){
    require __DIR__ . '/../admin/diag-admin-nav.php';
    return;
}
http_response_code(404);
echo 'diag-admin-nav.php not found';
