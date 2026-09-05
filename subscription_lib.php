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

    function pnvPaymentUsernameMatches($csvUsername, $username){
        return strcasecmp(trim((string)$csvUsername), trim((string)$username)) === 0;
    }

    function pnvPaymentRowIsBuy($type){
        $type = trim((string)$type);
        return $type === '' || $type === 'خرید';
    }

    function pnvLoadClearedSubs(){
        static $cache = null;

        if(!empty($GLOBALS['__pnvClearedSubsCacheReset'])){
            $cache = null;
            unset($GLOBALS['__pnvClearedSubsCacheReset']);
        }

        if($cache !== null){
            return $cache;
        }

        $file = pnvClearedSubsPath();

        if(!file_exists($file)){
            $cache = [];
            return $cache;
        }

        $data = json_decode(file_get_contents($file), true);
        $cache = is_array($data) ? $data : [];

        return $cache;
    }

    function pnvUserClearedLinkLookup($username){
        static $cache = [];

        $username = strtolower(trim((string)$username));

        if($username === ''){
            return [];
        }

        if(!empty($GLOBALS['__pnvClearedSubsCacheReset'])){
            $cache = [];
            unset($GLOBALS['__pnvClearedSubsCacheReset']);
        }

        if(isset($cache[$username])){
            return $cache[$username];
        }

        $data = pnvLoadClearedSubs();
        $list = $data[$username] ?? [];
        $lookup = [];

        if(is_array($list)){
            foreach($list as $item){
                $normalized = pnvNormalizeSubLink($item);

                if($normalized !== ''){
                    $lookup[$normalized] = true;
                }
            }
        }

        $cache[$username] = $lookup;

        return $lookup;
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

        $GLOBALS['__pnvClearedSubsCacheReset'] = true;
    }

    function pnvIsSubLinkCleared($username, $link){
        $username = strtolower(trim((string)$username));
        $link = pnvNormalizeSubLink($link);

        if($username === '' || $link === ''){
            return false;
        }

        $lookup = pnvUserClearedLinkLookup($username);

        return !empty($lookup[$link]);
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

    function pnvSubscriptionActivityTs($entry){
        if(!is_array($entry)){
            return 0;
        }

        $ts = intval($entry['created_ts'] ?? 0);

        if($ts > 0){
            return $ts;
        }

        $date = trim((string)($entry['date'] ?? ''));
        $time = trim((string)($entry['time'] ?? ''));

        if($date === ''){
            return 0;
        }

        if(is_file(__DIR__ . '/pnv_date_bootstrap.php')){
            require_once __DIR__ . '/pnv_date_bootstrap.php';
        }

        if(function_exists('pnvParseDateTimeToTimestamp')){
            $parsed = pnvParseDateTimeToTimestamp(trim($date . ($time !== '' ? (' ' . $time) : '')));

            if($parsed > 0){
                return intval($parsed);
            }
        }

        $parsed = strtotime($date . ($time !== '' ? (' ' . $time) : ''));

        return ($parsed !== false && $parsed > 0) ? intval($parsed) : 0;
    }

    /**
     * @return array{approved_subs:int,pending_buys:int,pending_renews:int}
     */
    function pnvDashboardUserPaymentStats($username){
        $username = trim((string)$username);
        $stats = [
            'approved_subs' => 0,
            'pending_buys' => 0,
            'pending_renews' => 0,
        ];
        $file = pnvPaymentsCsvPath();

        if($username === '' || !file_exists($file)){
            return $stats;
        }

        $clearedLookup = pnvUserClearedLinkLookup($username);
        $linkIndex = [];
        $handle = fopen($file, 'r');

        if($handle === false){
            return $stats;
        }

        while(($data = fgetcsv($handle)) !== false){
            if(!pnvPaymentUsernameMatches($data[0] ?? '', $username)){
                continue;
            }

            $status = trim((string)($data[6] ?? ''));
            $type = trim((string)($data[9] ?? ''));

            if($status !== 'تایید شد' && $status !== 'رد شد'){
                if(function_exists('instantPayRowCountsAsPendingNotification') && !instantPayRowCountsAsPendingNotification($data)){
                    continue;
                }
                elseif(!function_exists('instantPayRowCountsAsPendingNotification')){
                    // fallback
                }
                if($type === 'تمدید'){
                    $stats['pending_renews']++;
                }
                elseif(pnvPaymentRowIsBuy($type)){
                    $stats['pending_buys']++;
                }

                continue;
            }

            if($status !== 'تایید شد'){
                continue;
            }

            $col1 = trim((string)($data[1] ?? ''));
            $link = trim((string)($data[7] ?? ''));

            if(pnvPaymentRowIsBuy($type) && pnvIsValidSubLink($link)){
                $link = pnvNormalizeSubLinkValue($link);
                $key = strtolower($link);

                if($key !== '' && empty($clearedLookup[pnvNormalizeSubLink($link)])){
                    $linkIndex[$key] = true;
                }
            }

            if($type === 'تمدید' && pnvIsValidSubLink($col1)){
                $col1 = pnvNormalizeSubLinkValue($col1);
                $key = strtolower($col1);

                if($key !== '' && empty($clearedLookup[pnvNormalizeSubLink($col1)])){
                    $linkIndex[$key] = true;
                }
            }
        }

        fclose($handle);

        $stats['approved_subs'] = count($linkIndex);

        return $stats;
    }

    function pnvLoadUserActiveSubscriptions($username, $resolveNames = true){
        $username = trim((string)$username);
        $linkIndex = [];
        $file = pnvPaymentsCsvPath();

        if($username === '' || !file_exists($file)){
            return [];
        }

        $handle = fopen($file, 'r');
        $clearedLookup = pnvUserClearedLinkLookup($username);

        while(($data = fgetcsv($handle)) !== false){
            if(!pnvPaymentUsernameMatches($data[0] ?? '', $username)){
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

            if(pnvPaymentRowIsBuy($type) && pnvIsValidSubLink($link) && empty($clearedLookup[pnvNormalizeSubLink($link)])){
                $link = pnvNormalizeSubLinkValue($link);
                $key = strtolower($link);
                $linkIndex[$key] = [
                    'name' => $col1 !== '' ? $col1 : $link,
                    'link' => $link,
                    'plan_text' => $planText,
                    'tracking' => $tracking,
                    'date' => $date,
                    'time' => $time,
                    'created_ts' => intval($data[8] ?? 0),
                ];
            }

            if($type === 'تمدید' && pnvIsValidSubLink($col1) && empty($clearedLookup[pnvNormalizeSubLink($col1)])){
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
                        'created_ts' => intval($data[8] ?? 0),
                    ];
                }
                else{
                    $linkIndex[$key]['plan_text'] = $planText;
                    $linkIndex[$key]['tracking'] = $tracking;
                    $linkIndex[$key]['date'] = $date;
                    $linkIndex[$key]['time'] = $time;
                    $linkIndex[$key]['created_ts'] = intval($data[8] ?? 0);
                }
            }
        }

        fclose($handle);

        if($resolveNames && function_exists('pnvFindSubLinkFromCsv') === false && is_file(__DIR__ . '/plan_ui_lib.php')){
            require_once __DIR__ . '/plan_ui_lib.php';
        }

        if($resolveNames){
            foreach($linkIndex as &$entry){
                $fullLink = pnvFindSubLinkFromCsv($username, $entry['link'] ?? '');
                $fullLink = pnvNormalizeSubLinkValue($fullLink !== '' ? $fullLink : ($entry['link'] ?? ''));
                $entry['link'] = $fullLink;
                $entry['name'] = pnvEnsureSubDisplayName(
                    $username,
                    $entry['link'],
                    $entry['name'] ?? ''
                );
            }
            unset($entry);
        }
        else{
            foreach($linkIndex as &$entry){
                $entry['link'] = pnvNormalizeSubLinkValue($entry['link'] ?? '');
            }
            unset($entry);
        }

        $list = array_values($linkIndex);

        usort($list, static function($a, $b){
            return pnvSubscriptionActivityTs($b) <=> pnvSubscriptionActivityTs($a);
        });

        return $list;
    }

    function pnvUserCanViewSubQr($username, $link){
        $username = trim((string)$username);
        $link = pnvNormalizeSubLinkValue(trim((string)$link));

        if($username === '' || $link === ''){
            return false;
        }

        foreach(pnvLoadUserActiveSubscriptions($username, false) as $sub){
            $subLink = pnvNormalizeSubLinkValue(trim((string)($sub['link'] ?? '')));

            if($subLink !== '' && strcasecmp($subLink, $link) === 0){
                return true;
            }
        }

        if(is_file(__DIR__ . '/instant_pay_lib.php')){
            require_once __DIR__ . '/instant_pay_lib.php';

            foreach(instantPayLoad() as $item){
                if(strcasecmp(trim((string)($item['user'] ?? '')), $username) !== 0){
                    continue;
                }

                if(!in_array((string)($item['status'] ?? ''), ['paid', 'processing'], true)){
                    continue;
                }

                $itemLink = trim((string)($item['link'] ?? $item['sub'] ?? ''));

                if($itemLink !== '' && strcasecmp(pnvNormalizeSubLinkValue($itemLink), $link) === 0){
                    return true;
                }
            }
        }

        $file = pnvPaymentsCsvPath();

        if(!file_exists($file)){
            return false;
        }

        $handle = fopen($file, 'r');

        while(($row = fgetcsv($handle)) !== false){
            if(!pnvPaymentUsernameMatches($row[0] ?? '', $username)){
                continue;
            }

            if(trim((string)($row[6] ?? '')) !== 'تایید شد'){
                continue;
            }

            foreach([trim((string)($row[1] ?? '')), trim((string)($row[7] ?? ''))] as $candidate){
                if($candidate === '' || !pnvIsValidSubLink($candidate)){
                    continue;
                }

                if(strcasecmp(pnvNormalizeSubLinkValue($candidate), $link) === 0){
                    fclose($handle);
                    return true;
                }
            }
        }

        fclose($handle);
        return false;
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
