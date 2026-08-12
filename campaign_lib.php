<?php

if(!function_exists('campaignDataPath')){

    function campaignDataPath($name){
        return __DIR__ . '/db/' . $name . '.json';
    }

    function campaignJsonRead($name){
        $path = campaignDataPath($name);

        if(!is_file($path)){
            return [];
        }

        $data = json_decode((string)file_get_contents($path), true);
        return is_array($data) ? $data : [];
    }

    function campaignJsonWrite($name, $data){
        $path = campaignDataPath($name);
        $dir = dirname($path);

        if(!is_dir($dir)){
            mkdir($dir, 0755, true);
        }

        file_put_contents(
            $path,
            json_encode(array_values($data), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            LOCK_EX
        );
    }

    function campaignJsonUpdateLocked($name, $mutator){
        $path = campaignDataPath($name);
        $dir = dirname($path);

        if(!is_dir($dir)){
            mkdir($dir, 0755, true);
        }

        $fp = fopen($path, 'c+');

        if(!$fp){
            return null;
        }

        flock($fp, LOCK_EX);
        $raw = stream_get_contents($fp);
        $data = json_decode($raw ?: '[]', true);

        if(!is_array($data)){
            $data = [];
        }

        $result = $mutator($data);

        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode(array_values($data), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);

        return $result;
    }

    function campaignNewId($prefix){
        return $prefix . '_' . bin2hex(random_bytes(6));
    }

    function campaignNormalizeCode($code){
        return strtoupper(trim((string)$code));
    }

    function campaignNow(){
        if(function_exists('pnvEnsureTehranTimezone')){
            pnvEnsureTehranTimezone();
        }

        return time();
    }

    function campaignParseDateTime($value){
        $value = trim((string)$value);

        if($value === ''){
            return 0;
        }

        if(function_exists('pnvParseDateTimeToTimestamp')){
            return pnvParseDateTimeToTimestamp($value);
        }

        $ts = strtotime($value);
        return $ts ? intval($ts) : 0;
    }

    function campaignFormatDateTime($ts){
        $ts = intval($ts);

        if($ts <= 0){
            return '-';
        }

        if(function_exists('pnvFormatJalaliDate') && function_exists('pnvFormatTehranTime')){
            return pnvFormatJalaliDate($ts, '/') . ' ' . pnvFormatTehranTime($ts, false);
        }

        return date('Y-m-d H:i', $ts);
    }

    function campaignDiscountCodesLoad(){
        return campaignJsonRead('discount_codes');
    }

    function campaignDiscountCodesSave($rows){
        campaignJsonWrite('discount_codes', $rows);
    }

    function campaignDiscountUsagesLoad(){
        return campaignJsonRead('discount_code_usages');
    }

    function campaignDiscountUsagesSave($rows){
        campaignJsonWrite('discount_code_usages', $rows);
    }

    function campaignFindDiscountById($rows, $id){
        foreach($rows as $row){
            if(($row['id'] ?? '') === $id){
                return $row;
            }
        }

        return null;
    }

    function campaignFindDiscountByCode($rows, $code){
        $code = campaignNormalizeCode($code);

        foreach($rows as $row){
            if(campaignNormalizeCode($row['code'] ?? '') === $code){
                return $row;
            }
        }

        return null;
    }

    function campaignDiscountUsageCounts($codeId){
        $rows = campaignDiscountUsagesLoad();
        $confirmed = 0;
        $pending = 0;
        $perUser = [];

        foreach($rows as $row){
            if(($row['discount_code_id'] ?? '') !== $codeId){
                continue;
            }

            $status = $row['status'] ?? 'confirmed';
            $user = $row['user'] ?? '';

            if($status === 'cancelled'){
                continue;
            }

            if($status === 'pending'){
                $pending++;
            }
            else{
                $confirmed++;
            }

            if($user !== ''){
                $perUser[$user] = ($perUser[$user] ?? 0) + 1;
            }
        }

        return [
            'confirmed' => $confirmed,
            'pending' => $pending,
            'total_active' => $confirmed + $pending,
            'per_user' => $perUser,
        ];
    }

    function campaignDiscountIsActiveRow($row, $now = null){
        $now = $now ?? campaignNow();

        if(($row['status'] ?? '') !== 'active'){
            return false;
        }

        $starts = intval($row['starts_at'] ?? 0);
        $expires = intval($row['expires_at'] ?? 0);

        if($starts > 0 && $now < $starts){
            return false;
        }

        if($expires > 0 && $now > $expires){
            return false;
        }

        return true;
    }

    function campaignDiscountPlanAllowed($row, $planValue, $plans){
        $filter = $row['plan_filter'] ?? [];

        if(!is_array($filter) || count($filter) === 0){
            return true;
        }

        $planValue = trim((string)$planValue);

        foreach($filter as $item){
            $item = trim((string)$item);

            if($item !== '' && stripos($planValue, $item) !== false){
                return true;
            }
        }

        if(function_exists('couponFindPlanByValue')){
            $plan = couponFindPlanByValue($planValue, $plans);

            if(is_array($plan) && in_array(trim((string)($plan['name'] ?? '')), $filter, true)){
                return true;
            }
        }

        return false;
    }

    function campaignDiscountValidate($username, $code, $planValue, $plans, $reserve = false, $orderId = ''){
        if(!function_exists('couponFindPlanByValue')){
            require_once __DIR__ . '/coupon_lib.php';
        }

        $code = campaignNormalizeCode($code);
        $username = trim((string)$username);

        if($code === ''){
            return ['ok' => false, 'error' => 'کد تخفیف را وارد کنید'];
        }

        if($username === ''){
            return ['ok' => false, 'error' => 'کاربر نامعتبر است'];
        }

        $codes = campaignDiscountCodesLoad();
        $row = campaignFindDiscountByCode($codes, $code);

        if(!$row){
            return ['ok' => false, 'error' => 'کد تخفیف معتبر نیست'];
        }

        if(!campaignDiscountIsActiveRow($row)){
            return ['ok' => false, 'error' => 'کد تخفیف فعال نیست یا منقضی شده'];
        }

        if(!campaignDiscountPlanAllowed($row, $planValue, $plans)){
            return ['ok' => false, 'error' => 'این کد برای پلن انتخاب‌شده مجاز نیست'];
        }

        $plan = function_exists('couponFindPlanByValue')
            ? couponFindPlanByValue($planValue, $plans)
            : null;

        if(!$plan){
            return ['ok' => false, 'error' => 'پلن انتخاب‌شده معتبر نیست'];
        }

        $original = intval($plan['price'] ?? 0);
        $minimum = intval($row['minimum_purchase_amount'] ?? 0);

        if($minimum > 0 && $original < $minimum){
            return ['ok' => false, 'error' => 'حداقل مبلغ خرید برای این کد رعایت نشده'];
        }

        $type = ($row['type'] ?? '') === 'fixed' ? 'fixed' : 'percent';
        $value = intval($row['value'] ?? 0);
        $final = $original;
        $percent = 0;

        if($type === 'fixed'){
            $final = max(0, $original - $value);
            $percent = $original > 0 ? (int)round((($original - $final) / $original) * 100) : 0;
        }
        else{
            $percent = max(0, min(100, $value));

            if(function_exists('couponApplyDiscountThousands')){
                $final = couponApplyDiscountThousands($original, $percent);
            }
            else{
                $final = (int)round($original * (100 - $percent) / 100);
            }
        }

        $result = [
            'ok' => true,
            'source' => 'admin_discount',
            'code' => $row['code'],
            'discount_code_id' => $row['id'],
            'type' => $type,
            'value' => $value,
            'percent' => $percent,
            'original' => $original,
            'final' => $final,
            'original_text' => function_exists('couponFormatPriceThousands') ? couponFormatPriceThousands($original) : (string)$original,
            'final_text' => function_exists('couponFormatPriceThousands') ? couponFormatPriceThousands($final) : (string)$final,
            'plan_label' => function_exists('couponBuildPlanLabel') ? couponBuildPlanLabel($plan, $percent) : $planValue,
            'discount_amount_thousands' => max(0, $original - $final),
        ];

        if(!$reserve){
            $counts = campaignDiscountUsageCounts($row['id']);
            $maxUses = intval($row['max_uses'] ?? 0);

            if($maxUses > 0 && $counts['total_active'] >= $maxUses){
                return ['ok' => false, 'error' => 'سقف استفاده از این کد تکمیل شده'];
            }

            $perUserLimit = intval($row['per_user_limit'] ?? 0);

            if($perUserLimit > 0 && intval($counts['per_user'][$username] ?? 0) >= $perUserLimit){
                return ['ok' => false, 'error' => 'شما قبلاً از این کد استفاده کرده‌اید'];
            }

            return $result;
        }

        if($orderId === ''){
            return ['ok' => false, 'error' => 'شناسه سفارش نامعتبر است'];
        }

        $locked = campaignJsonUpdateLocked('discount_code_usages', function(&$usages) use ($row, $username, $orderId, $result){
            foreach($usages as $usage){
                if(($usage['order_id'] ?? '') === $orderId && ($usage['status'] ?? '') === 'pending'){
                    return ['ok' => true, 'usage_id' => $usage['id'], 'reserved' => true] + $result;
                }
            }

            $counts = campaignDiscountUsageCounts($row['id']);
            $maxUses = intval($row['max_uses'] ?? 0);

            if($maxUses > 0 && $counts['total_active'] >= $maxUses){
                return ['ok' => false, 'error' => 'سقف استفاده از این کد تکمیل شده'];
            }

            $perUserLimit = intval($row['per_user_limit'] ?? 0);

            if($perUserLimit > 0 && intval($counts['per_user'][$username] ?? 0) >= $perUserLimit){
                return ['ok' => false, 'error' => 'شما قبلاً از این کد استفاده کرده‌اید'];
            }

            $usageId = campaignNewId('dcu');
            $usages[] = [
                'id' => $usageId,
                'discount_code_id' => $row['id'],
                'code' => campaignNormalizeCode($row['code'] ?? ''),
                'user' => $username,
                'order_id' => $orderId,
                'purchase_id' => '',
                'discount_amount_thousands' => intval($result['discount_amount_thousands']),
                'status' => 'pending',
                'used_at' => campaignNow(),
            ];

            return ['ok' => true, 'usage_id' => $usageId, 'reserved' => true] + $result;
        });

        return is_array($locked) ? $locked : ['ok' => false, 'error' => 'خطا در رزرو کد تخفیف'];
    }

    function campaignDiscountReleaseUsage($orderId){
        $orderId = trim((string)$orderId);

        if($orderId === ''){
            return;
        }

        campaignJsonUpdateLocked('discount_code_usages', function(&$usages) use ($orderId){
            foreach($usages as $i => $usage){
                if(($usage['order_id'] ?? '') === $orderId && ($usage['status'] ?? '') === 'pending'){
                    $usages[$i]['status'] = 'cancelled';
                    $usages[$i]['cancelled_at'] = campaignNow();
                }
            }

            return true;
        });
    }

    function campaignDiscountConfirmUsage($orderId, $purchaseId = ''){
        $orderId = trim((string)$orderId);

        if($orderId === ''){
            return ['ok' => false];
        }

        $confirmed = campaignJsonUpdateLocked('discount_code_usages', function(&$usages) use ($orderId, $purchaseId){
            $found = null;

            foreach($usages as $i => $usage){
                if(($usage['order_id'] ?? '') === $orderId){
                    $found = $i;
                    break;
                }
            }

            if($found === null){
                return ['ok' => false];
            }

            if(($usages[$found]['status'] ?? '') === 'confirmed'){
                return ['ok' => true, 'already' => true, 'code_id' => $usages[$found]['discount_code_id'] ?? ''];
            }

            if(($usages[$found]['status'] ?? '') !== 'pending'){
                return ['ok' => false];
            }

            $codeId = $usages[$found]['discount_code_id'] ?? '';
            $usages[$found]['status'] = 'confirmed';
            $usages[$found]['purchase_id'] = trim((string)$purchaseId);
            $usages[$found]['used_at'] = campaignNow();

            return ['ok' => true, 'code_id' => $codeId];
        });

        if(empty($confirmed['ok']) || empty($confirmed['code_id'])){
            return $confirmed;
        }

        campaignJsonUpdateLocked('discount_codes', function(&$codes) use ($confirmed){
            foreach($codes as $i => $code){
                if(($code['id'] ?? '') === $confirmed['code_id']){
                    $codes[$i]['used_count'] = intval($code['used_count'] ?? 0) + 1;
                    $codes[$i]['updated_at'] = campaignNow();
                    break;
                }
            }

            return true;
        });

        return $confirmed;
    }

    function checkoutCalculateDiscountCode($username, $code, $planValue, $plans){
        if(!function_exists('couponCalculateForPlan')){
            require_once __DIR__ . '/coupon_lib.php';
        }

        $admin = campaignDiscountValidate($username, $code, $planValue, $plans, false);

        if(!empty($admin['ok'])){
            return $admin;
        }

        return couponCalculateForPlan($username, $code, $planValue, $plans);
    }

    function checkoutPrepareDiscountForOrder($username, $code, $planValue, $plans, $orderId){
        if(!function_exists('couponCalculateForPlan')){
            require_once __DIR__ . '/coupon_lib.php';
        }

        $admin = campaignDiscountValidate($username, $code, $planValue, $plans, true, $orderId);

        if(!empty($admin['ok'])){
            return $admin;
        }

        return couponCalculateForPlan($username, $code, $planValue, $plans);
    }

    function checkoutMarkDiscountPaid($item){
        if(!is_array($item)){
            return;
        }

        $source = $item['discount_source'] ?? '';
        $orderId = $item['id'] ?? '';
        $purchaseId = (string)intval($item['csv_index'] ?? -1);

        if($source === 'admin_discount' && $orderId !== ''){
            campaignDiscountConfirmUsage($orderId, $purchaseId);
            return;
        }

        if(!empty($item['coupon_code']) && function_exists('couponMarkUsed')){
            couponMarkUsed($item['coupon_code'], $item['user'] ?? '');
        }
    }

    function checkoutReleaseDiscountOrder($orderId){
        campaignDiscountReleaseUsage($orderId);
    }

    function campaignAnnouncementsLoad(){
        return campaignJsonRead('dashboard_announcements');
    }

    function campaignAnnouncementsSave($rows){
        campaignJsonWrite('dashboard_announcements', $rows);
    }

    function campaignAnnouncementReadsLoad(){
        return campaignJsonRead('dashboard_announcement_reads');
    }

    function campaignAnnouncementReadsSave($rows){
        campaignJsonWrite('dashboard_announcement_reads', $rows);
    }

    function campaignAnnouncementIsActive($row, $now = null){
        if(($row['status'] ?? '') !== 'active'){
            return false;
        }

        return campaignDiscountIsActiveRow([
            'status' => 'active',
            'starts_at' => intval($row['starts_at'] ?? 0),
            'expires_at' => intval($row['expires_at'] ?? 0),
        ], $now);
    }

    function campaignAnnouncementTypeLabel($type){
        $map = [
            'info' => 'اطلاع‌رسانی',
            'success' => 'موفقیت',
            'warning' => 'هشدار',
            'special' => 'ویژه',
        ];

        return $map[$type] ?? 'اطلاع‌رسانی';
    }

    function campaignUserAnnouncements($username){
        $username = trim((string)$username);
        $now = campaignNow();
        $rows = campaignAnnouncementsLoad();
        $reads = campaignAnnouncementReadsLoad();
        $readMap = [];

        foreach($reads as $read){
            if(($read['user'] ?? '') === $username){
                $readMap[$read['announcement_id'] ?? ''] = $read;
            }
        }

        $active = [];

        foreach($rows as $row){
            if(!campaignAnnouncementIsActive($row, $now)){
                continue;
            }

            $row['is_read'] = !empty($readMap[$row['id'] ?? '']);
            $row['read_at'] = intval($readMap[$row['id'] ?? '']['read_at'] ?? 0);
            $active[] = $row;
        }

        usort($active, function($a, $b){
            $pa = intval($a['priority'] ?? 100);
            $pb = intval($b['priority'] ?? 100);

            if($pa === $pb){
                return intval($b['created_at'] ?? 0) <=> intval($a['created_at'] ?? 0);
            }

            return $pb <=> $pa;
        });

        return $active;
    }

    function campaignAnnouncementMarkRead($username, $announcementId){
        $username = trim((string)$username);
        $announcementId = trim((string)$announcementId);

        if($username === '' || $announcementId === ''){
            return false;
        }

        $reads = campaignAnnouncementReadsLoad();

        foreach($reads as $read){
            if(($read['user'] ?? '') === $username && ($read['announcement_id'] ?? '') === $announcementId){
                return true;
            }
        }

        $reads[] = [
            'id' => campaignNewId('dar'),
            'announcement_id' => $announcementId,
            'user' => $username,
            'read_at' => campaignNow(),
        ];

        campaignAnnouncementReadsSave($reads);
        return true;
    }

    function campaignOverviewStats(){
        $codes = campaignDiscountCodesLoad();
        $announcements = campaignAnnouncementsLoad();
        $usages = campaignDiscountUsagesLoad();
        $now = campaignNow();
        $activeCodes = 0;
        $expiredCodes = 0;
        $activeAnnouncements = 0;
        $todayUses = 0;
        $totalDiscountThousands = 0;

        foreach($codes as $code){
            if(campaignDiscountIsActiveRow($code, $now)){
                $activeCodes++;
            }
            elseif(intval($code['expires_at'] ?? 0) > 0 && $now > intval($code['expires_at'])){
                $expiredCodes++;
            }
        }

        foreach($announcements as $row){
            if(campaignAnnouncementIsActive($row, $now)){
                $activeAnnouncements++;
            }
        }

        $todayStart = strtotime('today', $now);

        foreach($usages as $usage){
            if(($usage['status'] ?? '') !== 'confirmed'){
                continue;
            }

            $usedAt = intval($usage['used_at'] ?? 0);
            $totalDiscountThousands += intval($usage['discount_amount_thousands'] ?? 0);

            if($usedAt >= $todayStart){
                $todayUses++;
            }
        }

        return [
            'active_codes' => $activeCodes,
            'expired_codes' => $expiredCodes,
            'active_announcements' => $activeAnnouncements,
            'today_uses' => $todayUses,
            'total_discount_thousands' => $totalDiscountThousands,
        ];
    }

}
