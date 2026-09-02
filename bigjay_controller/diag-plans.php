<?php
$target = dirname(__DIR__) . '/admin/diag-plans.php';
if(is_file($target)){
    require $target;
    return;
}
http_response_code(404);
echo 'diag-plans.php not found';
