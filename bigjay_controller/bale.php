<?php
$target = dirname(__DIR__) . '/admin/bale.php';
if(is_file($target)){
    require $target;
    return;
}
if(is_file(__DIR__ . '/../admin/bale.php')){
    require __DIR__ . '/../admin/bale.php';
    return;
}
http_response_code(404);
echo 'bale.php not found';
