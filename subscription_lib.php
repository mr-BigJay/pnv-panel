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

    function pnvNormalizeSubLinkValue($value){
        $value = trim((string)$value);

        if($value === ''){
            return '';
        }

        if(preg_match('~https?://[^/\s]+(?::\d+)?/sub/[A-Za-z0-9]+~i', $value, $m)){
            return $m[0];
        }

        if(preg_match('/^[A-Za-z0-9]{8,32}$/', $value)){
            return $value;
        }

        if(preg_match('/^\s*([A-Za-z0-9]{8,32})\b/u', $value, $m)){
            return $m[1];
        }

        return trim(preg_split('/\s+/u', $value)[0] ?? '');
    }

    function pnvLoadUserActiveSubscriptions($username, $resolveNames = true){
        $username = trim((string)$username);
        $linkIndex = [];
        $file = pnvPaymentsCsvPath();

        if($username === '' || !file_exists($file)){
            return [];
        }

        $handle = fopen($file, 'r');

        while(($data = fgetcsv($handle)) !== false){
            if(($data[0] ?? '') !== $username){
                continue;
            }

            if(trim((string)($data[6] ?? '')) !== 'تایید شد'){
                continue;
            }

            $col1 = trim((string)($data[1] ?? ''));
            $link = trim((string)($data[7] ?? ''));
            $type = trim((string)($data[9] ?? ''));
            $planText = trim((string)($data[2] ?? ''));
            $tracking = trim((string)($data[3] ?? ''));
            $date = trim((string)($data[4] ?? ''));
            $time = trim((string)($data[5] ?? ''));

            if($type === 'خرید' && pnvIsValidSubLink($link) && !pnvIsSubLinkCleared($username, $link)){
                $link = pnvNormalizeSubLinkValue($link);
                $key = strtolower($link);
                $linkIndex[$key] = [
                    'name' => $col1 !== '' ? $col1 : $link,
                    'link' => $link,
                    'plan_text' => $planText,
                    'tracking' => $tracking,
                    'date' => $date,
                    'time' => $time,
                ];
            }

            if($type === 'تمدید' && pnvIsValidSubLink($col1) && !pnvIsSubLinkCleared($username, $col1)){
                $col1 = pnvNormalizeSubLinkValue($col1);
                $key = strtolower($col1);

                if(!isset($linkIndex[$key])){
                    $name = $col1;

                    if(preg_match('/\/sub\/([^\/\?]+)/i', $col1, $matches)){
                        $name = $matches[1];
                    }

                    $linkIndex[$key] = [
                        'name' => $name,
                        'link' => $col1,
                        'plan_text' => $planText,
                        'tracking' => $tracking,
                        'date' => $date,
                        'time' => $time,
                    ];
                }
                else{
                    $linkIndex[$key]['plan_text'] = $planText;
                    $linkIndex[$key]['tracking'] = $tracking;
                    $linkIndex[$key]['date'] = $date;
                    $linkIndex[$key]['time'] = $time;
                }
            }
        }

        fclose($handle);

        if(function_exists('pnvFindSubLinkFromCsv') === false && is_file(__DIR__ . '/plan_ui_lib.php')){
            require_once __DIR__ . '/plan_ui_lib.php';
        }

        foreach($linkIndex as &$entry){
            $fullLink = pnvFindSubLinkFromCsv($username, $entry['link'] ?? '');
            $fullLink = pnvNormalizeSubLinkValue($fullLink !== '' ? $fullLink : ($entry['link'] ?? ''));
            $entry['link'] = $fullLink;

            if($resolveNames){
                $entry['name'] = pnvEnsureSubDisplayName(
                    $username,
                    $entry['link'],
                    $entry['name'] ?? ''
                );
            }
        }
        unset($entry);

        return array_values($linkIndex);
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
