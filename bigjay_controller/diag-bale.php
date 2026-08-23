<?php
$target = dirname(__DIR__) . '/admin/diag-bale.php';
if(is_file($target)){
    require $target;
    return;
}
if(is_file(__DIR__ . '/../admin/diag-bale.php')){
    require __DIR__ . '/../admin/diag-bale.php';
    return;
}
http_response_code(404);
echo 'diag-bale.php not found';
