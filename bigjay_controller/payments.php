<?php

/**
 * /bigjay_controller/index.php?page=payments includes this file.
 * Prefer shared admin/payments.php; if missing, stop with a clear error
 * instead of a blank content area.
 */
$adminPayments = dirname(__DIR__) . '/admin/payments.php';

if(is_file($adminPayments)){
    require $adminPayments;
    return;
}

http_response_code(500);
echo '<div style="padding:20px;color:#fecaca;background:#7f1d1d;border-radius:12px;margin:12px 0;">'
    . 'فایل admin/payments.php پیدا نشد. آن را در /var/www/html/admin/payments.php قرار دهید.'
    . '</div>';
