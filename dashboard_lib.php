<?php
/**
 * توابع ضروری داشبورد — خارج از guard فایل‌های lib تا با deploy جزئی 500 ندهد.
 */

if(!function_exists('dashLibPaymentCsvPath')){
    function dashLibPaymentCsvPath(){
        return __DIR__ . '/invoices/payments.csv';
    }
}

if(!function_exists('dashLibSupportFile')){
    function dashLibSupportFile(){
        return __DIR__ . '/db/support.json';
    }
}

if(!function_exists('dashLibUsernameMatches')){
    function dashLibUsernameMatches($csvUsername, $username){
        return strcasecmp(trim((string)$csvUsername), trim((string)$username)) === 0;
    }
}

if(!function_exists('dashLibRowIsBuy')){
    function dashLibRowIsBuy($type){
        $type = trim((string)$type);
        return $type === '' || $type === 'خرید';
    }
}

if(!function_exists('dashLibIsValidSubLink')){
    function dashLibIsValidSubLink($value){
        $value = trim((string)$value);

        if($value === ''){
            return false;
        }

        foreach(['vip.boozhaan.ir', 'vip2.boozhaan.ir', 'vip3.boozhaan.ir', 'vip4.boozhaan.ir'] as $domain){
            if(stripos($value, $domain) !== false){
                return true;
            }
        }

        return false;
    }
}

if(!function_exists('supportUserHasUnread')){
    function supportUserHasUnread($username, $file = null){
        $username = trim((string)$username);

        if($username === ''){
            return false;
        }

        if(function_exists('supportLoad')){
            $data = supportLoad($file ?: dashLibSupportFile());
        }
        else{
            $path = $file ?: dashLibSupportFile();

            if(!is_file($path)){
                return false;
            }

            $data = json_decode((string)@file_get_contents($path), true);
        }

        if(!is_array($data)){
            return false;
        }

        foreach($data as $ticket){
            if(!dashLibUsernameMatches($ticket['user'] ?? '', $username)){
                continue;
            }

            foreach(($ticket['messages'] ?? []) as $msg){
                if(($msg['sender'] ?? '') === 'admin' && empty($msg['seen_by_user'])){
                    return true;
                }
            }
        }

        return false;
    }
}

if(!function_exists('pnvDashboardUserPaymentStats')){
    function pnvDashboardUserPaymentStats($username){
        if(function_exists('pnvLoadUserActiveSubscriptions') && function_exists('pnvPaymentsCsvPath')){
            $username = trim((string)$username);
            $stats = [
                'approved_subs' => count(pnvLoadUserActiveSubscriptions($username, false)),
                'pending_buys' => 0,
                'pending_renews' => 0,
            ];
            $file = pnvPaymentsCsvPath();

            if($username === '' || !is_file($file)){
                return $stats;
            }

            $handle = @fopen($file, 'r');

            if($handle === false){
                return $stats;
            }

            while(($data = fgetcsv($handle)) !== false){
                $matchFn = function_exists('pnvPaymentUsernameMatches')
                    ? 'pnvPaymentUsernameMatches'
                    : 'dashLibUsernameMatches';

                if(!$matchFn($data[0] ?? '', $username)){
                    continue;
                }

                $status = trim((string)($data[6] ?? ''));
                $type = trim((string)($data[9] ?? ''));

                if($status === 'تایید شد' || $status === 'رد شد'){
                    continue;
                }

                if($type === 'تمدید'){
                    $stats['pending_renews']++;
                }
                elseif(function_exists('pnvPaymentRowIsBuy') ? pnvPaymentRowIsBuy($type) : dashLibRowIsBuy($type)){
                    $stats['pending_buys']++;
                }
            }

            fclose($handle);

            return $stats;
        }

        $username = trim((string)$username);
        $stats = [
            'approved_subs' => 0,
            'pending_buys' => 0,
            'pending_renews' => 0,
        ];
        $file = dashLibPaymentCsvPath();

        if($username === '' || !is_file($file)){
            return $stats;
        }

        $linkIndex = [];
        $handle = @fopen($file, 'r');

        if($handle === false){
            return $stats;
        }

        while(($data = fgetcsv($handle)) !== false){
            if(!dashLibUsernameMatches($data[0] ?? '', $username)){
                continue;
            }

            $status = trim((string)($data[6] ?? ''));
            $type = trim((string)($data[9] ?? ''));

            if($status !== 'تایید شد' && $status !== 'رد شد'){
                if($type === 'تمدید'){
                    $stats['pending_renews']++;
                }
                elseif(dashLibRowIsBuy($type)){
                    $stats['pending_buys']++;
                }

                continue;
            }

            if($status !== 'تایید شد'){
                continue;
            }

            $col1 = trim((string)($data[1] ?? ''));
            $link = trim((string)($data[7] ?? ''));

            if(dashLibRowIsBuy($type) && dashLibIsValidSubLink($link)){
                $linkIndex[strtolower($link)] = true;
            }

            if($type === 'تمدید' && dashLibIsValidSubLink($col1)){
                $linkIndex[strtolower($col1)] = true;
            }
        }

        fclose($handle);

        $stats['approved_subs'] = count($linkIndex);

        return $stats;
    }
}
