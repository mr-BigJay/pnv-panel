<?php

if(!function_exists('pnvClearedSubsPath')){

    function pnvClearedSubsPath(){
        return __DIR__ . '/db/cleared_subscriptions.json';
    }

    function pnvPaymentsCsvPath(){
        return __DIR__ . '/invoices/payments.csv';
    }

    function pnvNormalizeSubLink($link){
        return strtolower(rtrim(trim((string)$link), '/'));
    }

    function pnvIsValidSubLink($value){
        $value = trim((string)$value);

        if($value === ''){
            return false;
        }

        $domains = [
            'vip.boozhaan.ir',
            'vip2.boozhaan.ir',
            'vip3.boozhaan.ir',
            'vip4.boozhaan.ir'
        ];

        foreach($domains as $domain){
            if(stripos($value, $domain) !== false){
                return true;
            }
        }

        return false;
    }

    function pnvLoadClearedSubs(){
        $file = pnvClearedSubsPath();

        if(!file_exists($file)){
            return [];
        }

        $data = json_decode(file_get_contents($file), true);
        return is_array($data) ? $data : [];
    }

    function pnvSaveClearedSubs($data){
        if(!is_dir(__DIR__ . '/db')){
            @mkdir(__DIR__ . '/db', 0755, true);
        }

        file_put_contents(
            pnvClearedSubsPath(),
            json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            LOCK_EX
        );
    }

    function pnvIsSubLinkCleared($username, $link){
        $username = strtolower(trim((string)$username));
        $link = pnvNormalizeSubLink($link);

        if($username === '' || $link === ''){
            return false;
        }

        $data = pnvLoadClearedSubs();
        $list = $data[$username] ?? [];

        if(!is_array($list)){
            return false;
        }

        foreach($list as $item){
            if(pnvNormalizeSubLink($item) === $link){
                return true;
            }
        }

        return false;
    }

    function pnvMarkSubLinkCleared($username, $link){
        $username = strtolower(trim((string)$username));
        $link = trim((string)$link);

        if($username === '' || !pnvIsValidSubLink($link)){
            return false;
        }

        $data = pnvLoadClearedSubs();

        if(!isset($data[$username]) || !is_array($data[$username])){
            $data[$username] = [];
        }

        $normalized = pnvNormalizeSubLink($link);
        $exists = false;

        foreach($data[$username] as $item){
            if(pnvNormalizeSubLink($item) === $normalized){
                $exists = true;
                break;
            }
        }

        if(!$exists){
            $data[$username][] = $link;
            pnvSaveClearedSubs($data);
        }

        return true;
    }

    function pnvClearUserSubscriptionLink($username, $tracking, $timestamp){
        $username = trim((string)$username);
        $tracking = trim((string)$tracking);
        $timestamp = intval($timestamp);

        if($username === '' || $tracking === ''){
            return ['ok' => false, 'error' => 'شناسه اشتراک ناقص است'];
        }

        $file = pnvPaymentsCsvPath();

        if(!file_exists($file)){
            return ['ok' => false, 'error' => 'فایل پرداخت‌ها پیدا نشد'];
        }

        $handle = fopen($file, 'c+');

        if($handle === false){
            return ['ok' => false, 'error' => 'باز کردن فایل پرداخت‌ها ممکن نیست'];
        }

        if(!flock($handle, LOCK_EX)){
            fclose($handle);
            return ['ok' => false, 'error' => 'قفل فایل پرداخت‌ها ممکن نیست'];
        }

        $rows = [];
        $found = false;
        $clearedLink = '';

        rewind($handle);

        while(($row = fgetcsv($handle)) !== false){
            $rows[] = $row;
        }

        foreach($rows as $i => $row){
            $rowUser = trim((string)($row[0] ?? ''));
            $rowTracking = trim((string)($row[3] ?? ''));
            $rowTime = intval($row[8] ?? 0);
            $type = trim((string)($row[9] ?? 'خرید'));
            $status = trim((string)($row[6] ?? ''));
            $link = trim((string)($row[7] ?? ''));

            if(strcasecmp($rowUser, $username) !== 0){
                continue;
            }

            if($type === 'تمدید'){
                continue;
            }

            if($rowTracking !== $tracking){
                continue;
            }

            if($timestamp > 0 && $rowTime > 0 && $rowTime !== $timestamp){
                continue;
            }

            if($status !== 'تایید شد' || !pnvIsValidSubLink($link)){
                continue;
            }

            $clearedLink = $link;
            $rows[$i][7] = '';
            $found = true;
            break;
        }

        if(!$found){
            flock($handle, LOCK_UN);
            fclose($handle);
            return ['ok' => false, 'error' => 'اشتراک تاییدشده با این مشخصات پیدا نشد'];
        }

        ftruncate($handle, 0);
        rewind($handle);

        foreach($rows as $row){
            fputcsv($handle, $row);
        }

        fflush($handle);
        flock($handle, LOCK_UN);
        fclose($handle);

        pnvMarkSubLinkCleared($username, $clearedLink);

        return [
            'ok' => true,
            'link' => $clearedLink,
            'message' => 'لینک اشتراک از پنل کاربر حذف شد'
        ];
    }
}
