<?php

// Production panel serves admin from /bigjay_controller/; keep one canonical renews page.
$adminRenews = __DIR__ . '/../admin/renews.php';

if(is_file($adminRenews)){
    require $adminRenews;
    return;
}

echo '<div style="padding:20px;color:#fecaca;background:#7f1d1d;border-radius:12px;margin:12px 0;">'
    . 'فایل renews.php یافت نشد.'
    . '</div>';
