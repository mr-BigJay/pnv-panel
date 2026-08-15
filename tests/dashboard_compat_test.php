<?php

require_once __DIR__ . '/../subscription_lib.php';
require_once __DIR__ . '/../support_lib.php';

if(!function_exists('pnvDashboardUserPaymentStats')){
    fwrite(STDERR, "missing pnvDashboardUserPaymentStats\n");
    exit(1);
}

if(!function_exists('supportUserHasUnread')){
    fwrite(STDERR, "missing supportUserHasUnread\n");
    exit(1);
}

$stats = pnvDashboardUserPaymentStats('__nobody__');
supportUserHasUnread('__nobody__');

echo "ok\n";
