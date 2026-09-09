<?php

require_once __DIR__ . '/xui_lib.php';
require_once __DIR__ . '/sub_usage_lib.php';

if(!function_exists('reservedRenewalsPath')){

    function reservedRenewalsPath(){
        return __DIR__ . '/db/reserved_renewals.json';
    }

    function reservedRenewalsLoad(){
        $path = reservedRenewalsPath();

        if(!file_exists($path)){
            return [];
        }

        $data = json_decode((string)file_get_contents($path), true);

        return is_array($data) ? $data : [];
    }

    function reservedRenewalsSave($items){
        if(!is_dir(__DIR__ . '/db')){
            @mkdir(__DIR__ . '/db', 0755, true);
        }

        file_put_contents(
            reservedRenewalsPath(),
            json_encode(array_values($items), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            LOCK_EX
        );
    }

    function reservedRenewalNewId(){
        return 'rr_' . bin2hex(random_bytes(8));
    }

    function reservedRenewalNormalizeLink($link){
        return function_exists('subUsageSanitizeLink')
            ? subUsageSanitizeLink($link)
            : strtolower(rtrim(trim((string)$link), '/'));
    }

    function reservedRenewalLinkKey($link){
        $link = reservedRenewalNormalizeLink($link);

        if($link === ''){
            return '';
        }

        if(function_exists('subUsageCacheKey')){
            return subUsageCacheKey($link);
        }

        return $link;
    }

    /**
     * رزرو فقط وقتی هم حجم و هم زمان باقی دارند (پلن محدود).
     */
    function subUsageLimitedRenewShouldReserve($usageView){
        if(!is_array($usageView) || empty($usageView['ok'])){
            return false;
        }

        if(subUsageViewLooksDepleted($usageView)){
            return false;
        }

        $vol = is_array($usageView['volume'] ?? null) ? $usageView['volume'] : [];
        $time = is_array($usageView['time'] ?? null) ? $usageView['time'] : [];

        if(!empty($vol['unlimited']) || !empty($time['unlimited'])){
            return false;
        }

        $volPct = floatval($vol['remain_pct'] ?? 0);
        $timePct = floatval($time['remain_pct'] ?? 0);

        return $volPct > 0.05 && $timePct > 0.05;
    }

    function reservedRenewalCancelForLink($user, $link, $items = null){
        if($items === null){
            $items = reservedRenewalsLoad();
        }

        $userKey = strtolower(trim((string)$user));
        $linkKey = reservedRenewalLinkKey($link);
        $changed = false;

        foreach($items as $i => $item){
            if(!is_array($item)){
                continue;
            }

            if(($item['status'] ?? '') !== 'waiting'){
                continue;
            }

            if(strtolower(trim((string)($item['user'] ?? ''))) !== $userKey){
                continue;
            }

            if(reservedRenewalLinkKey($item['sub_link'] ?? '') !== $linkKey){
                continue;
            }

            $items[$i]['status'] = 'cancelled';
            $items[$i]['cancelled_at'] = time();
            $items[$i]['message'] = 'جایگزین با رزرو جدید';
            $changed = true;
        }

        if($changed){
            reservedRenewalsSave($items);
        }

        return $items;
    }

    function reservedRenewalCreate($data){
        $user = trim((string)($data['user'] ?? ''));
        $subLink = trim((string)($data['sub_link'] ?? ''));
        $planText = trim((string)($data['plan_text'] ?? ''));
        $planValue = trim((string)($data['plan_value'] ?? ''));
        $csvIndex = intval($data['csv_index'] ?? -1);
        $instantId = trim((string)($data['instant_id'] ?? ''));

        if($user === '' || $subLink === '' || $planText === ''){
            return ['ok' => false, 'error' => 'اطلاعات رزرو ناقص است'];
        }

        $items = reservedRenewalCancelForLink($user, $subLink);
        $now = time();

        $item = [
            'id' => reservedRenewalNewId(),
            'user' => $user,
            'sub_link' => $subLink,
            'link_key' => reservedRenewalLinkKey($subLink),
            'plan_text' => $planText,
            'plan_value' => $planValue !== '' ? $planValue : $planText,
            'csv_index' => $csvIndex,
            'instant_id' => $instantId,
            'status' => 'waiting',
            'created_at' => $now,
            'paid_at' => intval($data['paid_at'] ?? $now),
            'activated_at' => 0,
            'message' => trim((string)($data['message'] ?? 'رزرو تمدید — فعال‌سازی با اتمام اشتراک فعلی')),
        ];

        $items[] = $item;
        reservedRenewalsSave($items);

        reservedRenewalNotifyReserved($item);

        return [
            'ok' => true,
            'item' => $item,
            'reserved' => true,
        ];
    }

    function reservedRenewalsForUser($username, $status = 'waiting'){
        $username = strtolower(trim((string)$username));
        $items = reservedRenewalsLoad();
        $out = [];

        foreach($items as $item){
            if(!is_array($item)){
                continue;
            }

            if(strtolower(trim((string)($item['user'] ?? ''))) !== $username){
                continue;
            }

            if($status !== null && ($item['status'] ?? '') !== $status){
                continue;
            }

            $out[] = $item;
        }

        return $out;
    }

    function reservedRenewalByLink($link, $status = 'waiting'){
        $linkKey = reservedRenewalLinkKey($link);
        $items = reservedRenewalsLoad();

        foreach($items as $item){
            if(!is_array($item)){
                continue;
            }

            if(($item['status'] ?? '') !== $status){
                continue;
            }

            if(reservedRenewalLinkKey($item['sub_link'] ?? '') !== $linkKey){
                continue;
            }

            return $item;
        }

        return null;
    }

    function reservedRenewalCountForUser($username, $status = 'waiting'){
        return count(reservedRenewalsForUser($username, $status));
    }

    function reservedRenewalMapForUser($username, $status = 'waiting'){
        $map = [];

        foreach(reservedRenewalsForUser($username, $status) as $item){
            $key = trim((string)($item['link_key'] ?? ''));

            if($key === ''){
                $key = reservedRenewalLinkKey($item['sub_link'] ?? '');
            }

            if($key !== ''){
                $map[$key] = $item;
            }
        }

        return $map;
    }

    function reservedRenewalShouldActivate($item){
        if(!is_array($item) || ($item['status'] ?? '') !== 'waiting'){
            return false;
        }

        $link = trim((string)($item['sub_link'] ?? ''));

        if($link === ''){
            return false;
        }

        $usage = subUsageRefreshOne($link, [
            'plan' => $item['plan_text'] ?? '',
        ], null, true);

        if(!is_array($usage) || empty($usage['ok'])){
            return false;
        }

        if(subUsageViewLooksDepleted($usage)){
            return true;
        }

        $time = is_array($usage['time'] ?? null) ? $usage['time'] : [];
        $remainSeconds = max(0, intval($time['remain_seconds'] ?? 0));

        if(empty($time['unlimited']) && $remainSeconds > 0 && $remainSeconds <= 60){
            return true;
        }

        return false;
    }

    function reservedRenewalActivateItem($item){
        if(!is_array($item) || ($item['status'] ?? '') !== 'waiting'){
            return ['ok' => false, 'error' => 'رزرو قابل فعال‌سازی نیست'];
        }

        $csvIndex = intval($item['csv_index'] ?? -1);
        $payments = xuiLoadPayments();

        if($csvIndex < 0 || !isset($payments[$csvIndex])){
            return ['ok' => false, 'error' => 'رد پرداخت رزرو پیدا نشد'];
        }

        $row = $payments[$csvIndex];
        $status = trim((string)($row[6] ?? ''));

        if($status === 'تایید شد' && intval($item['activated_at'] ?? 0) > 0){
            return ['ok' => true, 'already' => true, 'link' => trim((string)($row[7] ?? ''))];
        }

        $result = xuiResetClientForRenew($row);

        if(empty($result['ok'])){
            return $result;
        }

        $link = trim((string)($result['link'] ?? ($item['sub_link'] ?? '')));
        $payments[$csvIndex][6] = 'تایید شد';
        $payments[$csvIndex][7] = $link !== '' ? $link : trim((string)($row[7] ?? ''));
        xuiSavePayments($payments);

        if(function_exists('subUsageInvalidateLink')){
            subUsageInvalidateLink($item['sub_link'] ?? '');

            if($link !== ''){
                subUsageInvalidateLink($link);
            }
        }

        $items = reservedRenewalsLoad();
        $changed = false;

        foreach($items as $i => $rowItem){
            if(($rowItem['id'] ?? '') !== ($item['id'] ?? '')){
                continue;
            }

            $items[$i]['status'] = 'activated';
            $items[$i]['activated_at'] = time();
            $items[$i]['message'] = 'تمدید رزرو شده اعمال شد';
            $changed = true;
            $item = $items[$i];
            break;
        }

        if($changed){
            reservedRenewalsSave($items);
        }

        reservedRenewalNotifyActivated($item, $result);

        return [
            'ok' => true,
            'link' => $link,
            'provision' => $result,
            'item' => $item,
        ];
    }

    function reservedRenewalsActivateDue($limit = 30){
        $items = reservedRenewalsLoad();
        $activated = 0;
        $errors = [];

        foreach($items as $item){
            if(($item['status'] ?? '') !== 'waiting'){
                continue;
            }

            if(!reservedRenewalShouldActivate($item)){
                continue;
            }

            $result = reservedRenewalActivateItem($item);

            if(!empty($result['ok'])){
                $activated++;
            }
            else{
                $errors[] = ($item['id'] ?? '?') . ': ' . ($result['error'] ?? 'خطا');
            }

            if($activated >= $limit){
                break;
            }
        }

        return [
            'ok' => true,
            'activated' => $activated,
            'errors' => $errors,
        ];
    }

    function reservedRenewalsTryActivateForUser($username, $limit = 5){
        $username = trim((string)$username);

        if($username === ''){
            return ['ok' => true, 'activated' => 0];
        }

        $activated = 0;

        foreach(reservedRenewalsForUser($username, 'waiting') as $item){
            if(!reservedRenewalShouldActivate($item)){
                continue;
            }

            $result = reservedRenewalActivateItem($item);

            if(!empty($result['ok'])){
                $activated++;
            }

            if($activated >= $limit){
                break;
            }
        }

        return ['ok' => true, 'activated' => $activated];
    }

    function reservedRenewalNotifyReserved($item){
        if(!is_array($item)){
            return;
        }

        if(!is_file(__DIR__ . '/telegram_lib.php')){
            return;
        }

        require_once __DIR__ . '/telegram_lib.php';

        if(!function_exists('telegramNotifyUser')){
            return;
        }

        $user = trim((string)($item['user'] ?? ''));
        $plan = trim((string)($item['plan_text'] ?? ''));

        if($user === ''){
            return;
        }

        $text = "📌 تمدید شما رزرو شد\n\n";
        $text .= "پلن: {$plan}\n";
        $text .= "اشتراک فعلی شما هنوز فعال است.\n";
        $text .= "با اتمام اشتراک فعلی (کمتر از ۱ دقیقه)، پلن جدید به‌صورت خودکار اعمال می‌شود.\n\n";
        $text .= "وضعیت را در «اشتراک من» ببینید.";

        try{
            telegramNotifyUser($user, $text);
        }catch(Throwable $e){
            error_log('reserved renew notify failed: ' . $e->getMessage());
        }
    }

    function reservedRenewalNotifyActivated($item, $result = []){
        if(!is_array($item)){
            return;
        }

        if(!is_file(__DIR__ . '/telegram_lib.php')){
            return;
        }

        require_once __DIR__ . '/telegram_lib.php';

        if(!function_exists('telegramNotifyUser')){
            return;
        }

        $user = trim((string)($item['user'] ?? ''));
        $plan = trim((string)($item['plan_text'] ?? ''));

        if($user === ''){
            return;
        }

        $text = "✅ تمدید رزرو شده فعال شد\n\n";
        $text .= "پلن: {$plan}\n";
        $text .= "حجم و زمان اشتراک شما با پلن جدید به‌روزرسانی شد.\n\n";
        $text .= "از «اشتراک من» لینک را دریافت کنید.";

        try{
            telegramNotifyUser($user, $text);
        }catch(Throwable $e){
            error_log('reserved renew activated notify failed: ' . $e->getMessage());
        }
    }
}
