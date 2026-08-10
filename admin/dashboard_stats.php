<?php

if(!function_exists('dashboardLoadStats')){

    function dashboardLoadStats(){
        $root = dirname(__DIR__);

        foreach([
            __DIR__ . '/auth.php',
            __DIR__ . '/../admin/auth.php',
        ] as $boot){
            if(is_file($boot)){
                require_once $boot;
                break;
            }
        }

        foreach([
            __DIR__ . '/../pnv_date_bootstrap.php',
            dirname(__DIR__) . '/pnv_date_bootstrap.php',
        ] as $dateBoot){
            if(is_file($dateBoot)){
                require_once $dateBoot;
                break;
            }
        }

        $instantPayLib = $root . '/instant_pay_lib.php';
        if(is_file($instantPayLib)){
            require_once $instantPayLib;
        }

        $usersFile = $root . '/db/users.json';
        $paymentsFile = $root . '/invoices/payments.csv';

        $users = [];

        if(file_exists($usersFile)){
            $users = json_decode(file_get_contents($usersFile), true);
        }

        if(!is_array($users)){
            $users = [];
        }

        $payments = [];

        if(function_exists('instantPayPurgeAndReloadPaymentsCsv')){
            $payments = instantPayPurgeAndReloadPaymentsCsv($paymentsFile);
        }
        elseif(file_exists($paymentsFile)){
            $handle = fopen($paymentsFile, 'r');

            if($handle){
                while(($row = fgetcsv($handle)) !== false){
                    $payments[] = $row;
                }

                fclose($handle);
            }
        }

        $todayUsers = 0;

        foreach($users as $user){
            if(isset($user['created_at']) && function_exists('pnvIsTodayTehran') && pnvIsTodayTehran($user['created_at'])){
                $todayUsers++;
            }
        }

        $totalUsers = count($users);
        $totalPayments = 0;
        $todayPayments = 0;
        $totalRenews = 0;
        $todayRenews = 0;

        foreach($payments as $pay){
            $type = trim($pay[9] ?? '');

            if($type === 'تمدید'){
                $totalRenews++;

                if(function_exists('pnvPaymentRowIsToday') && pnvPaymentRowIsToday($pay)){
                    $todayRenews++;
                }
            }
            else{
                $totalPayments++;

                if(function_exists('pnvPaymentRowIsToday') && pnvPaymentRowIsToday($pay)){
                    $todayPayments++;
                }
            }
        }

        $telegramEnabled = false;
        $telegramConfigured = false;
        $telegramLib = $root . '/telegram_lib.php';

        if(file_exists($telegramLib)){
            require_once $telegramLib;

            if(function_exists('telegramLoadConfig')){
                $tgConfig = telegramLoadConfig();
                $telegramConfigured = trim((string)($tgConfig['bot_token'] ?? '')) !== '';
                $telegramEnabled = !empty($tgConfig['enabled']) && $telegramConfigured;
            }
        }

        $baleEnabled = false;
        $baleConfigured = false;
        $baleLib = $root . '/bale_lib.php';

        if(file_exists($baleLib)){
            require_once $baleLib;

            if(function_exists('baleLoadConfig')){
                $baleConfig = baleLoadConfig();
                $baleConfigured = trim((string)($baleConfig['bot_token'] ?? '')) !== '';
                $baleEnabled = !empty($baleConfig['enabled']) && $baleConfigured;
            }
        }

        $fmt = static function($n){
            return number_format((int)$n);
        };

        return [
            'stats' => [
                [
                    'key' => 'total_users',
                    'title' => 'تعداد کل کاربران',
                    'value' => $fmt($totalUsers),
                    'short' => 'کل کاربران',
                    'group' => 'users',
                    'icon' => '👥',
                ],
                [
                    'key' => 'today_users',
                    'title' => 'ثبت نام های امروز',
                    'value' => $fmt($todayUsers),
                    'short' => 'ثبت‌نام امروز',
                    'group' => 'users',
                    'icon' => '✦',
                ],
                [
                    'key' => 'total_payments',
                    'title' => 'تعداد کل خریدهای اشتراک',
                    'value' => $fmt($totalPayments),
                    'short' => 'کل خریدها',
                    'group' => 'payments',
                    'icon' => '🛒',
                ],
                [
                    'key' => 'today_payments',
                    'title' => 'تعداد خریدهای اشتراک امروز',
                    'value' => $fmt($todayPayments),
                    'short' => 'خرید امروز',
                    'group' => 'payments',
                    'icon' => '+',
                ],
                [
                    'key' => 'total_renews',
                    'title' => 'تعداد کل تمدیدهای اشتراک',
                    'value' => $fmt($totalRenews),
                    'short' => 'کل تمدیدها',
                    'group' => 'renews',
                    'icon' => '↻',
                ],
                [
                    'key' => 'today_renews',
                    'title' => 'تعداد تمدیدهای اشتراک امروز',
                    'value' => $fmt($todayRenews),
                    'short' => 'تمدید امروز',
                    'group' => 'renews',
                    'icon' => '◎',
                ],
            ],
            'setups' => [
                [
                    'title' => 'بات تلگرام',
                    'desc' => 'اعلان خرید/تمدید و منوی مدیریت در تلگرام',
                    'action' => 'تنظیمات تلگرام ←',
                    'href' => function_exists('pnvAdminUrl') ? pnvAdminUrl('telegram.php') : 'telegram.php',
                    'badge' => dashboardSetupBadge($telegramEnabled, $telegramConfigured),
                ],
                [
                    'title' => 'بازوی بله',
                    'desc' => 'پرداخت آنی کارت‌به‌کارت با فوروارد واریز پست‌بانک',
                    'action' => 'تنظیمات بله ←',
                    'href' => function_exists('pnvAdminUrl') ? pnvAdminUrl('bale.php') : 'bale.php',
                    'badge' => dashboardSetupBadge($baleEnabled, $baleConfigured),
                ],
            ],
            'raw' => [
                'totalUsers' => $totalUsers,
                'todayUsers' => $todayUsers,
                'totalPayments' => $totalPayments,
                'todayPayments' => $todayPayments,
                'totalRenews' => $totalRenews,
                'todayRenews' => $todayRenews,
            ],
        ];
    }

    function dashboardSetupBadge($enabled, $configured){
        if($enabled){
            return ['text' => 'فعال', 'class' => 'is-on'];
        }

        if($configured){
            return ['text' => 'پیکربندی شده', 'class' => 'is-warn'];
        }

        return ['text' => 'نیاز به ستاپ', 'class' => ''];
    }

    function dashboardStatByKey($stats, $key){
        foreach($stats as $stat){
            if(($stat['key'] ?? '') === $key){
                return $stat;
            }
        }

        return ['title' => '', 'value' => '0', 'short' => ''];
    }

}
