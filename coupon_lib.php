<?php

if(!function_exists('couponLoadCoupons')){

    function couponCouponsPath(){
        return __DIR__ . '/db/coupons.json';
    }

    function couponUsersPath(){
        return __DIR__ . '/db/users.json';
    }

    function couponPaymentsPath(){
        return __DIR__ . '/invoices/payments.csv';
    }

    function couponReferralConfigPath(){
        return __DIR__ . '/db/referral_config.json';
    }

    function couponReferralEventsPath(){
        return __DIR__ . '/db/referral_events.json';
    }

    function couponWriteJsonAtomic($path, $data){

        $json = json_encode(
            $data,
            JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
        );

        if($json === false){
            return false;
        }

        $dir = dirname($path);

        if(!is_dir($dir) && !mkdir($dir, 0755, true)){
            return false;
        }

        $tmp = $path . '.tmp.' . getmypid() . '.' . bin2hex(random_bytes(4));
        $written = @file_put_contents($tmp, $json, LOCK_EX);

        if($written === false){
            @unlink($tmp);
            return false;
        }

        if(!@rename($tmp, $path)){
            @unlink($tmp);
            return false;
        }

        return true;

    }

    function couponReferralDefaultConfig(){

        return [
            'enabled' => true,
            'success_rule' => 'first_approved_purchase',
            'tiers' => [
                [
                    'id' => 'tier_3',
                    'referrals' => 3,
                    'percent' => 20,
                    'qty' => 1,
                    'max_discount_thousands' => 0,
                    'enabled' => true,
                ],
                [
                    'id' => 'tier_5',
                    'referrals' => 5,
                    'percent' => 40,
                    'qty' => 1,
                    'max_discount_thousands' => 0,
                    'enabled' => true,
                ],
                [
                    'id' => 'tier_10',
                    'referrals' => 10,
                    'percent' => 100,
                    'qty' => 1,
                    'max_discount_thousands' => 0,
                    'enabled' => true,
                ],
                [
                    'id' => 'tier_20',
                    'referrals' => 20,
                    'percent' => 100,
                    'qty' => 3,
                    'max_discount_thousands' => 0,
                    'enabled' => true,
                ],
            ],
            'new_user_reward' => [
                'enabled' => false,
                'first_purchase_percent' => 10,
                'max_discount_thousands' => 0,
            ],
            'referrer_purchase_reward' => [
                'enabled' => false,
                'percent' => 5,
                'monthly_cap_thousands' => 500,
            ],
        ];

    }

    function couponNormalizeReferralConfig($config){

        $defaults = couponReferralDefaultConfig();
        $config = is_array($config) ? $config : [];

        $out = [
            'enabled' => !empty($config['enabled']),
            'success_rule' => trim((string)($config['success_rule'] ?? $defaults['success_rule'])),
            'tiers' => [],
            'new_user_reward' => [],
            'referrer_purchase_reward' => [],
        ];

        if($out['success_rule'] === ''){
            $out['success_rule'] = 'first_approved_purchase';
        }

        $rawTiers = is_array($config['tiers'] ?? null) ? $config['tiers'] : $defaults['tiers'];
        $seenReferrals = [];

        foreach($rawTiers as $i => $tier){

            if(!is_array($tier)){
                continue;
            }

            $referrals = intval($tier['referrals'] ?? 0);
            $percent = intval($tier['percent'] ?? 0);
            $qty = intval($tier['qty'] ?? 1);
            $maxDiscount = intval($tier['max_discount_thousands'] ?? 0);

            if($referrals < 1 || $percent < 1 || $percent > 100 || $qty < 1 || $maxDiscount < 0){
                continue;
            }

            if(isset($seenReferrals[$referrals])){
                continue;
            }

            $seenReferrals[$referrals] = true;

            $id = trim((string)($tier['id'] ?? ''));

            if($id === ''){
                $id = 'tier_' . ($i + 1);
            }

            $out['tiers'][] = [
                'id' => $id,
                'referrals' => $referrals,
                'percent' => $percent,
                'qty' => max(1, $qty),
                'max_discount_thousands' => max(0, $maxDiscount),
                'enabled' => !isset($tier['enabled']) || !empty($tier['enabled']),
            ];

        }

        usort($out['tiers'], static function($a, $b){
            return intval($a['referrals']) <=> intval($b['referrals']);
        });

        if(empty($out['tiers'])){
            $out['tiers'] = $defaults['tiers'];
        }

        $newUser = is_array($config['new_user_reward'] ?? null)
            ? $config['new_user_reward']
            : $defaults['new_user_reward'];

        $out['new_user_reward'] = [
            'enabled' => false,
            'first_purchase_percent' => max(1, min(100, intval($newUser['first_purchase_percent'] ?? 10))),
            'max_discount_thousands' => max(0, intval($newUser['max_discount_thousands'] ?? 0)),
        ];

        $referrerPurchase = is_array($config['referrer_purchase_reward'] ?? null)
            ? $config['referrer_purchase_reward']
            : $defaults['referrer_purchase_reward'];

        $out['referrer_purchase_reward'] = [
            'enabled' => false,
            'percent' => max(1, min(100, intval($referrerPurchase['percent'] ?? 5))),
            'monthly_cap_thousands' => max(0, intval($referrerPurchase['monthly_cap_thousands'] ?? 500)),
        ];

        return $out;

    }

    function couponLoadReferralConfig(){

        $path = couponReferralConfigPath();

        if(!file_exists($path)){
            $config = couponReferralDefaultConfig();
            couponSaveReferralConfig($config);
            return $config;
        }

        $data = json_decode(file_get_contents($path), true);

        if(!is_array($data)){
            $config = couponReferralDefaultConfig();
            couponSaveReferralConfig($config);
            return $config;
        }

        return couponNormalizeReferralConfig($data);

    }

    function couponSaveReferralConfig($config){

        $normalized = couponNormalizeReferralConfig($config);

        if(!couponWriteJsonAtomic(couponReferralConfigPath(), $normalized)){
            return false;
        }

        return $normalized;

    }

    function couponBuildReferralConfigFromAdminPost($post){

        $tiers = [];
        $tierRows = is_array($post['tiers'] ?? null) ? $post['tiers'] : [];

        foreach($tierRows as $rowKey => $tier){

            if(!is_array($tier)){
                continue;
            }

            $tiers[] = [
                'id' => trim((string)($tier['id'] ?? $rowKey)),
                'referrals' => intval($tier['referrals'] ?? 0),
                'percent' => intval($tier['percent'] ?? 0),
                'qty' => intval($tier['qty'] ?? 1),
                'max_discount_thousands' => intval($tier['max_discount_thousands'] ?? 0),
                'enabled' => !empty($tier['enabled']),
            ];

        }

        return [
            'enabled' => !empty($post['referral_enabled']),
            'success_rule' => 'first_approved_purchase',
            'tiers' => $tiers,
            'new_user_reward' => [
                'enabled' => false,
                'first_purchase_percent' => intval($post['new_user_reward_percent'] ?? 10),
                'max_discount_thousands' => intval($post['new_user_reward_max'] ?? 0),
            ],
            'referrer_purchase_reward' => [
                'enabled' => false,
                'percent' => intval($post['referrer_purchase_percent'] ?? 5),
                'monthly_cap_thousands' => intval($post['referrer_purchase_cap'] ?? 500),
            ],
        ];

    }

    function couponReferralTiers($forUi = false){

        $config = couponLoadReferralConfig();
        $tiers = [];

        foreach($config['tiers'] as $tier){

            if(empty($tier['enabled'])){
                continue;
            }

            $need = intval($tier['referrals']);
            $percent = intval($tier['percent']);
            $qty = intval($tier['qty']);
            $maxDiscount = intval($tier['max_discount_thousands'] ?? 0);

            $desc = $qty > 1
                ? $qty . ' کد تخفیف ' . $percent . '٪'
                : 'یک کد تخفیف ' . $percent . '٪';

            if($maxDiscount > 0){
                $desc .= ' (حداکثر ' . couponFormatPriceThousands($maxDiscount) . ')';
            }

            $chip = $qty > 1 ? ($qty . '×' . $percent . '٪') : ($percent . '٪');

            $row = [
                'id' => $tier['id'],
                'need' => $need,
                'referrals' => $need,
                'percent' => $percent,
                'qty' => $qty,
                'max_discount_thousands' => $maxDiscount,
            ];

            if($forUi){
                $row['title'] = $need . ' دعوت موفق';
                $row['desc'] = $desc;
                $row['chip'] = $chip;
            }

            $tiers[] = $row;

        }

        return $tiers;

    }

    function couponRewardLabelForTier($tier){

        $percent = intval($tier['percent'] ?? 0);
        $qty = intval($tier['qty'] ?? 1);
        $maxDiscount = intval($tier['max_discount_thousands'] ?? 0);

        if($percent <= 0){
            return 'هنوز پاداشی فعال نشده';
        }

        if($qty > 1){
            $label = $qty . ' عدد کد تخفیف ' . $percent . ' درصدی';
        }
        elseif($percent === 100){
            $label = 'کد تخفیف 100 درصدی';
        }
        else{
            $label = 'تخفیف ' . $percent . ' درصدی';
        }

        if($maxDiscount > 0){
            $label .= ' (سقف ' . couponFormatPriceThousands($maxDiscount) . ')';
        }

        return $label;

    }

    function couponLoadCoupons(){

        $path = couponCouponsPath();

        if(!file_exists($path)){
            return [];
        }

        $data = json_decode(file_get_contents($path), true);

        return is_array($data) ? $data : [];

    }

    function couponSaveCoupons($coupons){

        file_put_contents(
            couponCouponsPath(),
            json_encode(
                array_values($coupons),
                JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
            )
        );

    }

    function couponLoadUsers(){

        $path = couponUsersPath();

        if(!file_exists($path)){
            return [];
        }

        $data = json_decode(file_get_contents($path), true);

        return is_array($data) ? $data : [];

    }

    function couponSaveUsers($users){

        file_put_contents(
            couponUsersPath(),
            json_encode(
                $users,
                JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
            )
        );

    }

    function couponFindUserIndex($users, $username){

        foreach($users as $i => $user){

            if(
                strtolower(trim($user['username'] ?? ''))
                === strtolower(trim($username))
            ){
                return $i;
            }

        }

        return -1;

    }

    function couponEnsureReferralCode(&$user){

        if(!empty($user['referral_code'])){
            return $user['referral_code'];
        }

        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $code = '';

        for($i = 0; $i < 6; $i++){
            $code .= $chars[random_int(0, strlen($chars) - 1)];
        }

        $user['referral_code'] = $code;

        return $code;

    }

    function couponGetResetAt($user){

        return intval($user['referral_reset_at'] ?? 0);

    }

    function couponReferralIsSelf($user, $indexes = null){

        $referred = trim($user['username'] ?? '');
        $refRaw = trim($user['referrer'] ?? '');

        if($referred === '' || $refRaw === ''){
            return false;
        }

        if(strcasecmp($refRaw, $referred) === 0){
            return true;
        }

        if($indexes === null){
            $indexes = couponBuildUserReferrerIndexes(couponLoadUsers());
        }

        $self = $indexes['by_username'][strtolower($referred)] ?? null;

        if(!$self){
            return false;
        }

        $refUpper = strtoupper($refRaw);
        $selfCode = strtoupper(trim($self['referral_code'] ?? ''));
        $selfMobile = trim($self['mobile'] ?? '');

        if($selfCode !== '' && $refUpper === $selfCode){
            return true;
        }

        if($selfMobile !== '' && $refRaw === $selfMobile){
            return true;
        }

        return false;

    }

    function couponUserWasReferredBy($referredUser, $ownerUser){

        if(couponReferralIsSelf($referredUser)){
            return false;
        }

        if(
            strcasecmp(
                trim($referredUser['username'] ?? ''),
                trim($ownerUser['username'] ?? '')
            ) === 0
        ){
            return false;
        }

        $referrer = strtoupper(trim($referredUser['referrer'] ?? ''));

        if($referrer === ''){
            return false;
        }

        $ownerCode = strtoupper(trim($ownerUser['referral_code'] ?? ''));
        $ownerMobile = trim($ownerUser['mobile'] ?? '');

        if($ownerCode !== '' && $referrer === $ownerCode){
            return true;
        }

        if($ownerMobile !== '' && $referrer === $ownerMobile){
            return true;
        }

        return false;

    }

    function couponLoadApprovedPaymentTimes(){

        $map = [];
        $path = couponPaymentsPath();

        if(!file_exists($path)){
            return $map;
        }

        $handle = fopen($path, 'r');

        if(!$handle){
            return $map;
        }

        while(($row = fgetcsv($handle)) !== false){

            $username = trim($row[0] ?? '');
            $status = trim($row[6] ?? '');
            $created = intval($row[8] ?? 0);

            if($username === '' || $status !== 'تایید شد'){
                continue;
            }

            $key = strtolower($username);

            if(!isset($map[$key]) || $created < $map[$key]){
                $map[$key] = $created > 0 ? $created : time();
            }

        }

        fclose($handle);

        return $map;

    }

    function couponCountSuccessfulReferrals($ownerUser, $users, $resetAt){

        $approvedMap = couponLoadApprovedPaymentTimes();
        $count = 0;

        foreach($users as $user){

            if(!couponUserWasReferredBy($user, $ownerUser)){
                continue;
            }

            $key = strtolower(trim($user['username'] ?? ''));

            if($key === '' || !isset($approvedMap[$key])){
                continue;
            }

            if($approvedMap[$key] > $resetAt){
                $count++;
            }

        }

        return $count;

    }

    function couponCountSuccessfulReferralsLifetime($ownerUser, $users){

        return couponCountSuccessfulReferrals($ownerUser, $users, 0);

    }

    function couponRewardForCount($count){

        $count = max(0, intval($count));
        $matched = null;

        foreach(couponReferralTiers() as $tier){

            if($count >= intval($tier['need'])){
                $matched = $tier;
            }

        }

        if(!$matched){
            return [
                'percent' => 0,
                'qty' => 0,
                'label' => 'هنوز پاداشی فعال نشده',
                'tier_id' => '',
                'need' => 0,
                'max_discount_thousands' => 0,
            ];
        }

        return [
            'percent' => intval($matched['percent']),
            'qty' => intval($matched['qty']),
            'label' => couponRewardLabelForTier($matched),
            'tier_id' => (string)($matched['id'] ?? ''),
            'need' => intval($matched['need']),
            'max_discount_thousands' => intval($matched['max_discount_thousands'] ?? 0),
        ];

    }

    function couponGenerateCode($length = 10){

        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $code = '';

        for($i = 0; $i < $length; $i++){
            $code .= $chars[random_int(0, strlen($chars) - 1)];
        }

        return $code;

    }

    function couponCodeExists($coupons, $code){

        $code = strtoupper(trim($code));

        foreach($coupons as $coupon){

            if(strtoupper($coupon['code'] ?? '') === $code){
                return true;
            }

        }

        return false;

    }

    function couponGetOwnerUnusedCoupons($coupons, $owner){

        $items = [];

        foreach($coupons as $coupon){

            if(
                ($coupon['owner'] ?? '') === $owner
                && empty($coupon['used'])
            ){
                $items[] = $coupon;
            }

        }

        return $items;

    }

    function couponEnsureCodesForUser($username){

        $users = couponLoadUsers();
        $index = couponFindUserIndex($users, $username);

        if($index < 0){
            return [];
        }

        couponEnsureReferralCode($users[$index]);
        couponSaveUsers($users);

        $owner = $users[$index];
        $resetAt = couponGetResetAt($owner);
        $count = couponCountSuccessfulReferrals($owner, $users, $resetAt);
        $reward = couponRewardForCount($count);
        $coupons = couponLoadCoupons();
        $referralConfig = couponLoadReferralConfig();

        if(empty($referralConfig['enabled']) || ($reward['percent'] ?? 0) <= 0){
            return couponGetOwnerUnusedCoupons($coupons, $username);
        }

        $unused = couponGetOwnerUnusedCoupons($coupons, $username);
        $matching = [];

        foreach($unused as $coupon){

            if(
                intval($coupon['percent'] ?? 0) === intval($reward['percent'])
                && intval($coupon['max_discount_thousands'] ?? 0) === intval($reward['max_discount_thousands'] ?? 0)
            ){
                $matching[] = $coupon;
            }

        }

        $need = intval($reward['qty']) - count($matching);

        if($need > 0){

            for($i = 0; $i < $need; $i++){

                do{
                    $code = couponGenerateCode(10);
                }
                while(couponCodeExists($coupons, $code));

                $coupons[] = [
                    'code' => $code,
                    'owner' => $username,
                    'percent' => intval($reward['percent']),
                    'tier_id' => (string)($reward['tier_id'] ?? ''),
                    'max_discount_thousands' => intval($reward['max_discount_thousands'] ?? 0),
                    'created_at' => time(),
                    'used' => false,
                    'used_at' => null,
                    'used_by' => null,
                ];

            }

            couponSaveCoupons($coupons);

        }

        $unusedLower = [];

        foreach($coupons as $coupon){

            if(
                ($coupon['owner'] ?? '') === $username
                && empty($coupon['used'])
                && intval($coupon['percent'] ?? 0) < intval($reward['percent'])
            ){
                $unusedLower[] = $coupon['code'];
            }

        }

        if(!empty($unusedLower)){

            foreach($coupons as $i => $coupon){

                if(
                    ($coupon['owner'] ?? '') === $username
                    && empty($coupon['used'])
                    && intval($coupon['percent'] ?? 0) < intval($reward['percent'])
                ){
                    $coupons[$i]['used'] = true;
                    $coupons[$i]['used_at'] = time();
                    $coupons[$i]['used_by'] = '_expired_tier';
                }

            }

            couponSaveCoupons($coupons);

        }

        return couponGetOwnerUnusedCoupons(couponLoadCoupons(), $username);

    }

    function couponGetUserSummary($username){

        $users = couponLoadUsers();
        $index = couponFindUserIndex($users, $username);

        if($index < 0){
            return null;
        }

        couponEnsureReferralCode($users[$index]);
        couponSaveUsers($users);

        $owner = $users[$index];
        $resetAt = couponGetResetAt($owner);
        $count = couponCountSuccessfulReferrals($owner, $users, $resetAt);
        $reward = couponRewardForCount($count);
        $activeCodes = couponEnsureCodesForUser($username);

        return [
            'user' => $owner,
            'referral_code' => $owner['referral_code'] ?? '',
            'successful_count' => $count,
            'reward' => $reward,
            'active_codes' => $activeCodes,
            'reset_at' => $resetAt
        ];

    }

    function couponFindByCode($coupons, $code){

        $code = strtoupper(trim($code));

        foreach($coupons as $coupon){

            if(strtoupper($coupon['code'] ?? '') === $code){
                return $coupon;
            }

        }

        return null;

    }

    function couponValidateForUser($username, $code){

        $code = strtoupper(trim($code));

        if($code === ''){
            return ['ok' => false, 'error' => 'کد تخفیف را وارد کنید'];
        }

        $coupons = couponLoadCoupons();
        $coupon = couponFindByCode($coupons, $code);

        if(!$coupon){
            return ['ok' => false, 'error' => 'کد تخفیف معتبر نیست'];
        }

        if(!empty($coupon['used'])){
            return ['ok' => false, 'error' => 'این کد قبلاً استفاده شده است'];
        }

        if(($coupon['owner'] ?? '') !== $username){
            return ['ok' => false, 'error' => 'این کد متعلق به شما نیست'];
        }

        return [
            'ok' => true,
            'percent' => intval($coupon['percent'] ?? 0),
            'max_discount_thousands' => intval($coupon['max_discount_thousands'] ?? 0),
            'tier_id' => (string)($coupon['tier_id'] ?? ''),
            'code' => $coupon['code'],
        ];

    }

    function couponFormatPriceThousands($price){

        $price = intval($price);

        if($price < 1000){
            return number_format($price) . ' هزار تومان';
        }

        $million = $price / 1000;
        $million = rtrim(rtrim(number_format($million, 3), '0'), '.');

        return $million . ' میلیون تومان';

    }

    function couponFindPlanByValue($planValue, $plans){

        $planValue = trim($planValue);

        foreach($plans as $plan){

            $value = trim(($plan['name'] ?? '') . ' - ' . couponFormatPriceThousands($plan['price'] ?? 0));

            if($value === $planValue){
                return $plan;
            }

        }

        return null;

    }

    function couponCalculateDiscountThousands($priceThousands, $percent, $maxDiscountThousands = 0){

        $priceThousands = max(0, intval($priceThousands));
        $percent = max(0, min(100, intval($percent)));
        $maxDiscountThousands = max(0, intval($maxDiscountThousands));

        $percentageDiscount = (int)round($priceThousands * $percent / 100);

        if($maxDiscountThousands > 0){
            $actualDiscount = min($percentageDiscount, $maxDiscountThousands);
        }
        else{
            $actualDiscount = $percentageDiscount;
        }

        return [
            'discount_amount' => $actualDiscount,
            'final_amount' => max(0, $priceThousands - $actualDiscount),
        ];

    }

    function couponApplyDiscountThousands($priceThousands, $percent){

        return couponCalculateDiscountThousands($priceThousands, $percent, 0)['final_amount'];

    }

    function couponBuildPlanLabel($plan, $percent, $maxDiscountThousands = 0){

        $originalAmount = intval($plan['price'] ?? 0);
        $original = couponFormatPriceThousands($originalAmount);
        $calc = couponCalculateDiscountThousands($originalAmount, $percent, $maxDiscountThousands);
        $finalText = couponFormatPriceThousands($calc['final_amount']);

        if($percent <= 0){
            return ($plan['name'] ?? '') . ' - ' . $original;
        }

        $label = ($plan['name'] ?? '')
            . ' - '
            . $original
            . ' | تخفیف '
            . $percent
            . '% → '
            . $finalText;

        if(intval($maxDiscountThousands) > 0){
            $label .= ' (سقف ' . couponFormatPriceThousands($maxDiscountThousands) . ')';
        }

        return $label;

    }

    function couponCalculateForPlan($username, $code, $planValue, $plans){

        $validation = couponValidateForUser($username, $code);

        if(!$validation['ok']){
            return $validation;
        }

        $plan = couponFindPlanByValue($planValue, $plans);

        if(!$plan){
            return ['ok' => false, 'error' => 'پلن انتخاب‌شده معتبر نیست'];
        }

        $percent = intval($validation['percent']);
        $maxDiscount = intval($validation['max_discount_thousands'] ?? 0);
        $original = intval($plan['price']);
        $calc = couponCalculateDiscountThousands($original, $percent, $maxDiscount);
        $final = intval($calc['final_amount']);

        return [
            'ok' => true,
            'source' => 'referral',
            'code' => $validation['code'],
            'tier_id' => (string)($validation['tier_id'] ?? ''),
            'percent' => $percent,
            'max_discount_thousands' => $maxDiscount,
            'plan_name' => $plan['name'] ?? '',
            'original_text' => couponFormatPriceThousands($original),
            'final_text' => couponFormatPriceThousands($final),
            'original_amount' => $original,
            'discount_amount' => intval($calc['discount_amount']),
            'final_amount' => $final,
            'final' => $final,
            'plan_label' => couponBuildPlanLabel($plan, $percent, $maxDiscount),
        ];

    }

    function couponResetOwnerCycle($username){

        $users = couponLoadUsers();
        $index = couponFindUserIndex($users, $username);

        if($index < 0){
            return;
        }

        $users[$index]['referral_reset_at'] = time();
        couponSaveUsers($users);

    }

    function couponInvalidateOwnerUnused($owner, $exceptCode = ''){

        $exceptCode = strtoupper(trim($exceptCode));
        $coupons = couponLoadCoupons();
        $changed = false;

        foreach($coupons as $i => $coupon){

            if(
                ($coupon['owner'] ?? '') === $owner
                && empty($coupon['used'])
                && strtoupper($coupon['code'] ?? '') !== $exceptCode
            ){
                $coupons[$i]['used'] = true;
                $coupons[$i]['used_at'] = time();
                $coupons[$i]['used_by'] = '_reset';
                $changed = true;
            }

        }

        if($changed){
            couponSaveCoupons($coupons);
        }

    }

    function couponNormalizeDigits($text){

        return str_replace(
            ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹', '٬', ','],
            ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '', ''],
            (string)$text
        );

    }

    function couponExtractPlanNameFromText($planText){

        $planText = trim((string)$planText);

        if($planText === ''){
            return '';
        }

        $parts = preg_split('/\s*-\s*/u', $planText, 2);

        return trim($parts[0] ?? $planText);

    }

    function couponLoadPlansLookup(){

        static $lookup = null;

        if($lookup !== null){
            return $lookup;
        }

        $lookup = [
            'by_label' => [],
            'by_name' => [],
        ];

        $path = __DIR__ . '/db/plans.json';

        if(!file_exists($path)){
            return $lookup;
        }

        $plans = json_decode(file_get_contents($path), true);

        if(!is_array($plans)){
            return $lookup;
        }

        foreach($plans as $plan){
            if(!is_array($plan)){
                continue;
            }

            $name = trim($plan['name'] ?? '');
            $price = intval($plan['price'] ?? 0);

            if($name !== ''){
                $lookup['by_name'][$name] = $price;
            }

            $label = trim($name . ' - ' . couponFormatPriceThousands($price));

            if($label !== ''){
                $lookup['by_label'][$label] = $price;
            }
        }

        return $lookup;

    }

    function couponPlanBaseThousandsFromPlanText($planText){

        $planText = trim((string)$planText);

        if($planText === ''){
            return 0;
        }

        $lookup = couponLoadPlansLookup();

        if(isset($lookup['by_label'][$planText])){
            return intval($lookup['by_label'][$planText]);
        }

        $name = couponExtractPlanNameFromText($planText);

        if($name !== '' && isset($lookup['by_name'][$name])){
            return intval($lookup['by_name'][$name]);
        }

        return 0;

    }

    function couponParsePlanTextPriceThousands($planText){

        $planText = trim((string)$planText);

        if($planText === ''){
            return 0;
        }

        $normalized = couponNormalizeDigits($planText);

        if(preg_match('/(\d+)\s*هزار\s*توم/u', $normalized, $m)){
            return intval($m[1]);
        }

        if(preg_match('/([\d.]+)\s*میلیون\s*توم/u', $normalized, $m)){
            return (int)round(floatval($m[1]) * 1000);
        }

        if(preg_match('/([\d.]+)\s*میلیون/u', $normalized, $m)){
            return (int)round(floatval($m[1]) * 1000);
        }

        if(preg_match('/(\d+)\s*تومان/u', $normalized, $m)){
            $toman = intval($m[1]);

            if($toman >= 1000){
                return max(1, (int)round($toman / 1000));
            }

            return 0;
        }

        return 0;

    }

    function couponCsvPaymentAmountRial($row){

        if(!is_array($row)){
            return 0;
        }

        if(function_exists('instantPayCsvRowAmountRial')){
            return intval(instantPayCsvRowAmountRial($row));
        }

        return intval($row[12] ?? 0);

    }

    function couponRialToThousandsToman($rial){

        $rial = intval($rial);

        if($rial <= 0){
            return 0;
        }

        return max(0, (int)round($rial / 10000));

    }

    function couponPaymentStableKey($row, $lineNo = 0){

        $username = strtolower(trim($row[0] ?? ''));
        $ts = intval($row[8] ?? 0);
        $type = trim($row[9] ?? '');

        return $username . '|' . $ts . '|' . $type . '|' . intval($lineNo);

    }

    /**
     * Approved payment amount in thousands of Toman (paid amount after discount when known).
     */
    function couponPaymentApprovedAmountThousands($row){

        $rial = couponCsvPaymentAmountRial($row);

        if($rial > 0){
            return couponRialToThousandsToman($rial);
        }

        $planText = trim($row[2] ?? '');
        $discountPercent = intval($row[11] ?? 0);
        $parsedExplicit = couponParsePlanTextPriceThousands($planText);
        $baseThousands = couponPlanBaseThousandsFromPlanText($planText);
        $amountBase = $parsedExplicit > 0 ? $parsedExplicit : $baseThousands;

        if($amountBase > 0 && $discountPercent > 0){
            return couponCalculateDiscountThousands(
                $amountBase,
                $discountPercent,
                0
            )['final_amount'];
        }

        if($amountBase > 0){
            return $amountBase;
        }

        return 0;

    }

    function couponResetPaymentsAnalyticsCache(){

        $GLOBALS['couponPaymentsAnalyticsCache'] = null;

    }

    function couponBuildPaymentsAnalyticsIndex($forceRebuild = false){

        if(!$forceRebuild && isset($GLOBALS['couponPaymentsAnalyticsCache'])){
            $cached = $GLOBALS['couponPaymentsAnalyticsCache'];

            if(is_array($cached)){
                return $cached;
            }
        }

        $cache = [
            'first_approved' => [],
            'all_approved' => [],
            'revenue_thousands' => [],
        ];

        $path = couponPaymentsPath();

        if(!file_exists($path)){
            return $cache;
        }

        $handle = fopen($path, 'r');

        if(!$handle){
            return $cache;
        }

        $lineNo = 0;

        while(($row = fgetcsv($handle)) !== false){

            $lineNo++;
            $username = trim($row[0] ?? '');
            $status = trim($row[6] ?? '');

            if($username === '' || $status !== 'تایید شد'){
                continue;
            }

            $key = strtolower($username);
            $ts = intval($row[8] ?? 0);

            if($ts <= 0){
                $ts = time();
            }

            $amount = couponPaymentApprovedAmountThousands($row);
            $paymentKey = couponPaymentStableKey($row, $lineNo);

            if(!isset($cache['all_approved'][$key])){
                $cache['all_approved'][$key] = [];
                $cache['revenue_thousands'][$key] = 0;
            }

            $cache['revenue_thousands'][$key] += $amount;
            $cache['all_approved'][$key][] = [
                'timestamp' => $ts,
                'amount_thousands' => $amount,
                'payment_key' => $paymentKey,
                'type' => trim($row[9] ?? ''),
            ];

            if(
                !isset($cache['first_approved'][$key])
                || $ts < $cache['first_approved'][$key]['timestamp']
            ){
                $cache['first_approved'][$key] = [
                    'timestamp' => $ts,
                    'amount_thousands' => $amount,
                    'payment_key' => $paymentKey,
                ];
            }

        }

        fclose($handle);

        $GLOBALS['couponPaymentsAnalyticsCache'] = $cache;

        return $cache;

    }

    function couponBuildUserReferrerIndexes($users){

        $byCode = [];
        $byMobile = [];
        $byUsername = [];

        foreach($users as $user){

            $username = trim($user['username'] ?? '');

            if($username === ''){
                continue;
            }

            $byUsername[strtolower($username)] = $user;

            $code = strtoupper(trim($user['referral_code'] ?? ''));

            if($code !== ''){
                $byCode[$code] = $user;
            }

            $mobile = trim($user['mobile'] ?? '');

            if($mobile !== ''){
                $byMobile[$mobile] = $user;
            }

        }

        return [
            'by_code' => $byCode,
            'by_mobile' => $byMobile,
            'by_username' => $byUsername,
        ];

    }

    function couponResolveReferrerUsername($referredUser, $indexes, $skipSelfCheck = false){

        $ref = strtoupper(trim($referredUser['referrer'] ?? ''));

        if($ref === ''){
            return '';
        }

        $referrer = '';

        if(isset($indexes['by_code'][$ref])){
            $referrer = trim($indexes['by_code'][$ref]['username'] ?? '');
        }

        if($referrer === ''){
            $mobileRef = trim($referredUser['referrer'] ?? '');

            if(isset($indexes['by_mobile'][$mobileRef])){
                $referrer = trim($indexes['by_mobile'][$mobileRef]['username'] ?? '');
            }
        }

        if($referrer === ''){
            foreach($indexes['by_username'] as $unameLower => $user){
                if(strtoupper($unameLower) === $ref){
                    $referrer = trim($user['username'] ?? '');
                    break;
                }
            }
        }

        if($referrer === ''){
            return '';
        }

        if(!$skipSelfCheck){
            $referred = trim($referredUser['username'] ?? '');

            if(
                $referred !== ''
                && strcasecmp($referrer, $referred) === 0
            ){
                return '';
            }
        }

        return $referrer;

    }

    function couponUserRegisteredTimestamp($user){

        $created = trim($user['created_at'] ?? '');

        if($created === ''){
            return 0;
        }

        if(function_exists('pnvTehranStrToUnix')){
            $ts = pnvTehranStrToUnix($created);

            if($ts > 0){
                return $ts;
            }
        }

        $ts = strtotime($created);

        return $ts > 0 ? $ts : 0;

    }

    function couponLoadReferralEvents(){

        $path = couponReferralEventsPath();

        if(!file_exists($path)){
            return [];
        }

        $data = json_decode(file_get_contents($path), true);

        return is_array($data) ? $data : [];

    }

    function couponSaveReferralEvents($events){

        couponWriteJsonAtomic(
            couponReferralEventsPath(),
            array_values($events)
        );

    }

    function couponReferralEventDedupeKey($event){

        $type = (string)($event['event'] ?? '');
        $referred = strtolower(trim($event['referred'] ?? ''));

        if($type === 'signup'){
            return 'signup:' . $referred;
        }

        if($type === 'first_approved_purchase'){
            return 'first_purchase:' . $referred;
        }

        return ($event['id'] ?? uniqid('refevt_', true));

    }

    function couponGenerateReferralEventsFromSources(){

        couponResetPaymentsAnalyticsCache();
        $users = couponLoadUsers();
        $indexes = couponBuildUserReferrerIndexes($users);
        $payments = couponBuildPaymentsAnalyticsIndex(true);
        $events = [];

        foreach($users as $user){

            $referred = trim($user['username'] ?? '');

            if($referred === ''){
                continue;
            }

            if(trim($user['referrer'] ?? '') === ''){
                continue;
            }

            if(couponReferralIsSelf($user, $indexes)){
                continue;
            }

            $referrer = couponResolveReferrerUsername($user, $indexes);

            if($referrer === ''){
                continue;
            }

            $signupKey = 'signup:' . strtolower($referred);

            $events[] = [
                'id' => 'refevt_' . substr(hash('sha256', $signupKey), 0, 16),
                'event' => 'signup',
                'referrer' => $referrer,
                'referred' => $referred,
                'referral_code' => trim($user['referrer'] ?? ''),
                'created_at' => couponUserRegisteredTimestamp($user) ?: time(),
            ];

            $referredKey = strtolower($referred);
            $first = $payments['first_approved'][$referredKey] ?? null;

            if(!$first){
                continue;
            }

            $purchaseKey = 'first_purchase:' . $referredKey;

            $events[] = [
                'id' => 'refevt_' . substr(hash('sha256', $purchaseKey), 0, 16),
                'event' => 'first_approved_purchase',
                'referrer' => $referrer,
                'referred' => $referred,
                'payment_key' => $first['payment_key'],
                'payment_amount' => intval($first['amount_thousands']),
                'created_at' => intval($first['timestamp']),
            ];

        }

        return $events;

    }

    function couponSyncReferralEvents(){

        $generated = couponGenerateReferralEventsFromSources();
        $events = couponLoadReferralEvents();
        $seen = [];
        $changed = false;

        foreach($events as $event){
            $seen[couponReferralEventDedupeKey($event)] = true;
        }

        foreach($generated as $event){
            $key = couponReferralEventDedupeKey($event);

            if(isset($seen[$key])){
                continue;
            }

            $events[] = $event;
            $seen[$key] = true;
            $changed = true;
        }

        if($changed){
            couponSaveReferralEvents($events);
        }

        return $events;

    }

    function couponRebuildReferralEvents(){

        $events = couponGenerateReferralEventsFromSources();
        couponSaveReferralEvents($events);

        return [
            'count' => count($events),
            'events' => $events,
        ];

    }

    function couponCollectUnresolvedReferrals($users = null, $indexes = null){

        if($users === null){
            $users = couponLoadUsers();
        }

        if($indexes === null){
            $indexes = couponBuildUserReferrerIndexes($users);
        }

        $rows = [];

        foreach($users as $user){

            $stored = trim($user['referrer'] ?? '');

            if($stored === ''){
                continue;
            }

            if(couponReferralIsSelf($user, $indexes)){
                continue;
            }

            $referrer = couponResolveReferrerUsername($user, $indexes);

            if($referrer !== ''){
                continue;
            }

            $rows[] = [
                'referred' => trim($user['username'] ?? ''),
                'referrer_value' => $stored,
                'reason' => 'owner not found',
            ];

        }

        return $rows;

    }

    function couponCollectInvalidSelfReferrals($users = null, $indexes = null){

        if($users === null){
            $users = couponLoadUsers();
        }

        if($indexes === null){
            $indexes = couponBuildUserReferrerIndexes($users);
        }

        $rows = [];

        foreach($users as $user){

            if(!couponReferralIsSelf($user, $indexes)){
                continue;
            }

            $rows[] = [
                'referred' => trim($user['username'] ?? ''),
                'referrer_value' => trim($user['referrer'] ?? ''),
                'reason' => 'self_referral',
            ];

        }

        return $rows;

    }

    function couponBuildCouponOwnerIndex($coupons){

        $index = [];

        foreach($coupons as $coupon){

            $owner = trim($coupon['owner'] ?? '');

            if($owner === ''){
                continue;
            }

            $key = strtolower($owner);

            if(!isset($index[$key])){
                $index[$key] = [
                    'issued' => 0,
                    'used' => 0,
                    'active' => 0,
                ];
            }

            $index[$key]['issued']++;

            if(!empty($coupon['used'])){
                $usedBy = (string)($coupon['used_by'] ?? '');

                if($usedBy !== '_reset' && $usedBy !== '_expired_tier'){
                    $index[$key]['used']++;
                }
            }
            else{
                $index[$key]['active']++;
            }

        }

        return $index;

    }

    function couponReferralAbuseFlags($referrerUser, $referredUsers, $paymentsIndex){

        $flags = [];
        $referrerMobile = trim($referrerUser['mobile'] ?? '');
        $referrerName = trim($referrerUser['username'] ?? '');
        $signupCount = count($referredUsers);

        if($signupCount >= 15){
            $flags[] = 'signup_spike';
        }

        foreach($referredUsers as $referred){

            $referredName = trim($referred['username'] ?? '');

            if(
                $referredName !== ''
                && strcasecmp($referredName, $referrerName) === 0
            ){
                $flags[] = 'self_referral';
            }

            $referredMobile = trim($referred['mobile'] ?? '');

            if(
                $referrerMobile !== ''
                && $referredMobile !== ''
                && $referrerMobile === $referredMobile
            ){
                $flags[] = 'same_mobile';
            }

            $key = strtolower($referredName);

            if($key !== '' && !isset($paymentsIndex['first_approved'][$key])){
                $flags[] = 'no_purchase';
            }

        }

        return array_values(array_unique($flags));

    }

    function couponAdminReferralReport($search = ''){

        couponSyncReferralEvents();

        $users = couponLoadUsers();
        $indexes = couponBuildUserReferrerIndexes($users);
        $payments = couponBuildPaymentsAnalyticsIndex();
        $coupons = couponLoadCoupons();
        $couponIndex = couponBuildCouponOwnerIndex($coupons);
        $config = couponLoadReferralConfig();

        $referredByReferrer = [];
        $totalSignups = 0;
        $successfulReferred = [];
        $unresolvedReferrals = couponCollectUnresolvedReferrals($users, $indexes);
        $invalidSelfReferrals = couponCollectInvalidSelfReferrals($users, $indexes);

        foreach($users as $user){

            if(trim($user['referrer'] ?? '') === ''){
                continue;
            }

            if(couponReferralIsSelf($user, $indexes)){
                continue;
            }

            $referrer = couponResolveReferrerUsername($user, $indexes);

            if($referrer === ''){
                continue;
            }

            $totalSignups++;

            if(!isset($referredByReferrer[$referrer])){
                $referredByReferrer[$referrer] = [];
            }

            $referredByReferrer[$referrer][] = $user;

            $referredKey = strtolower(trim($user['username'] ?? ''));

            if($referredKey !== '' && isset($payments['first_approved'][$referredKey])){
                $successfulReferred[$referredKey] = true;
            }

        }

        $successfulCount = count($successfulReferred);
        $activeReferrers = count($referredByReferrer);
        $convertingReferrers = 0;
        $referralRevenue = 0;
        $rows = [];

        foreach($referredByReferrer as $referrerUsername => $referredList){

            $referrerUser = $indexes['by_username'][strtolower($referrerUsername)] ?? null;

            if(!$referrerUser){
                continue;
            }

            $lifetimeSuccess = couponCountSuccessfulReferralsLifetime($referrerUser, $users);
            $cycleSuccess = couponCountSuccessfulReferrals(
                $referrerUser,
                $users,
                couponGetResetAt($referrerUser)
            );

            $signupCount = count($referredList);
            $successCount = 0;
            $revenue = 0;
            $lastActivity = 0;
            $referredDetails = [];

            foreach($referredList as $referred){

                $referredKey = strtolower(trim($referred['username'] ?? ''));
                $first = $payments['first_approved'][$referredKey] ?? null;
                $isSuccess = $first !== null;

                if($isSuccess){
                    $successCount++;
                }

                $userRevenue = intval($payments['revenue_thousands'][$referredKey] ?? 0);
                $revenue += $userRevenue;

                $registeredTs = couponUserRegisteredTimestamp($referred);

                if($first){
                    $lastActivity = max($lastActivity, intval($first['timestamp']));
                }

                if($registeredTs > 0){
                    $lastActivity = max($lastActivity, $registeredTs);
                }

                $purchaseCount = isset($payments['all_approved'][$referredKey])
                    ? count($payments['all_approved'][$referredKey])
                    : 0;

                $referredDetails[] = [
                    'username' => trim($referred['username'] ?? ''),
                    'registered_at' => $registeredTs,
                    'successful' => $isSuccess,
                    'first_purchase_at' => $first ? intval($first['timestamp']) : 0,
                    'purchase_count' => $purchaseCount,
                    'revenue_thousands' => $userRevenue,
                    'status_label' => $isSuccess ? 'خرید موفق' : 'فقط ثبت‌نام',
                ];

            }

            if($successCount > 0){
                $convertingReferrers++;
            }

            $referralRevenue += $revenue;

            $ownerKey = strtolower($referrerUsername);
            $couponStats = $couponIndex[$ownerKey] ?? [
                'issued' => 0,
                'used' => 0,
                'active' => 0,
            ];

            $conversionPct = $signupCount > 0
                ? round(($successCount / $signupCount) * 100, 1)
                : 0;

            $rows[] = [
                'username' => $referrerUsername,
                'referral_code' => trim($referrerUser['referral_code'] ?? ''),
                'mobile' => trim($referrerUser['mobile'] ?? ''),
                'signups' => $signupCount,
                'successful' => $successCount,
                'lifetime_successful' => $lifetimeSuccess,
                'cycle_successful' => $cycleSuccess,
                'conversion_pct' => $conversionPct,
                'revenue_thousands' => $revenue,
                'coupons_active' => intval($couponStats['active']),
                'coupons_used' => intval($couponStats['used']),
                'last_activity' => $lastActivity,
                'flags' => couponReferralAbuseFlags($referrerUser, $referredList, $payments),
                'referred' => $referredDetails,
            ];

        }

        usort($rows, static function($a, $b){
            return intval($b['successful']) <=> intval($a['successful']);
        });

        $search = trim($search);

        if($search !== ''){
            $q = mb_strtolower($search, 'UTF-8');

            $rows = array_values(array_filter($rows, static function($row) use ($q){
                $hay = mb_strtolower(
                    ($row['username'] ?? '')
                    . ' '
                    . ($row['referral_code'] ?? '')
                    . ' '
                    . ($row['mobile'] ?? ''),
                    'UTF-8'
                );

                return mb_strpos($hay, $q, 0, 'UTF-8') !== false;
            }));
        }

        $issuedTotal = 0;
        $usedTotal = 0;

        foreach($coupons as $coupon){
            if(trim($coupon['owner'] ?? '') === ''){
                continue;
            }

            $issuedTotal++;

            if(!empty($coupon['used'])){
                $usedBy = (string)($coupon['used_by'] ?? '');

                if($usedBy !== '_reset' && $usedBy !== '_expired_tier'){
                    $usedTotal++;
                }
            }
        }

        $conversionRate = $totalSignups > 0
            ? round(($successfulCount / $totalSignups) * 100, 1)
            : 0;

        return [
            'config' => $config,
            'kpis' => [
                'total_signups' => $totalSignups,
                'successful_referrals' => $successfulCount,
                'active_referrers' => $activeReferrers,
                'converting_referrers' => $convertingReferrers,
                'conversion_rate' => $conversionRate,
                'referral_revenue_thousands' => $referralRevenue,
                'coupons_issued' => $issuedTotal,
                'coupons_used' => $usedTotal,
                'discount_cost' => null,
                'discount_cost_note' => 'داده کافی برای محاسبه دقیق موجود نیست',
                'unresolved_referrals' => count($unresolvedReferrals),
                'invalid_self_referrals' => count($invalidSelfReferrals),
            ],
            'referrers' => $rows,
            'unresolved_referrals' => $unresolvedReferrals,
            'invalid_self_referrals' => $invalidSelfReferrals,
        ];

    }

    function couponAdminCsrfToken(){

        if(session_status() === PHP_SESSION_NONE){
            session_start();
        }

        if(empty($_SESSION['coupon_admin_csrf'])){
            $_SESSION['coupon_admin_csrf'] = bin2hex(random_bytes(16));
        }

        return $_SESSION['coupon_admin_csrf'];

    }

    function couponAdminCsrfValid($token){

        if(session_status() === PHP_SESSION_NONE){
            session_start();
        }

        $expected = $_SESSION['coupon_admin_csrf'] ?? '';

        return is_string($token)
            && $expected !== ''
            && hash_equals($expected, $token);

    }

    function couponMarkUsed($code, $usedBy){

        $code = strtoupper(trim($code));
        $coupons = couponLoadCoupons();
        $owner = '';

        foreach($coupons as $i => $coupon){

            if(strtoupper($coupon['code'] ?? '') !== $code){
                continue;
            }

            $owner = $coupon['owner'] ?? '';
            $coupons[$i]['used'] = true;
            $coupons[$i]['used_at'] = time();
            $coupons[$i]['used_by'] = $usedBy;
            couponSaveCoupons($coupons);

            if($owner !== ''){
                couponInvalidateOwnerUnused($owner, $code);
                couponResetOwnerCycle($owner);
            }

            return true;

        }

        return false;

    }

}
