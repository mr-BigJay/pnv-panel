<?php

$adminPayments = __DIR__ . '/../admin/payments.php';

if(is_file($adminPayments)){
    require $adminPayments;
    return;
}

echo '<div style="padding:20px;color:#fecaca;background:#7f1d1d;border-radius:12px;margin:12px 0;">'
    . 'فایل payments.php یافت نشد.'
    . '</div>';
