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

    function couponUserWasReferredBy($referredUser, $ownerUser){

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

    function couponRewardForCount($count){

        if($count >= 20){
            return ['percent' => 100, 'qty' => 3, 'label' => '3 عدد کد تخفیف 100 درصدی'];
        }

        if($count >= 10){
            return ['percent' => 100, 'qty' => 1, 'label' => 'کد تخفیف 100 درصدی'];
        }

        if($count >= 5){
            return ['percent' => 40, 'qty' => 1, 'label' => 'تخفیف 40 درصدی'];
        }

        if($count >= 3){
            return ['percent' => 20, 'qty' => 1, 'label' => 'تخفیف 20 درصدی'];
        }

        return [
            'percent' => 0,
            'qty' => 0,
            'label' => 'هنوز پاداشی فعال نشده'
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

        if(($reward['percent'] ?? 0) <= 0){
            return couponGetOwnerUnusedCoupons($coupons, $username);
        }

        $unused = couponGetOwnerUnusedCoupons($coupons, $username);
        $matching = [];

        foreach($unused as $coupon){

            if(intval($coupon['percent'] ?? 0) === intval($reward['percent'])){
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
                    'created_at' => time(),
                    'used' => false,
                    'used_at' => null,
                    'used_by' => null
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
            'code' => $coupon['code']
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

    function couponApplyDiscountThousands($priceThousands, $percent){

        $priceThousands = intval($priceThousands);
        $percent = max(0, min(100, intval($percent)));

        $discounted = (int)round($priceThousands * (100 - $percent) / 100);

        return max(0, $discounted);

    }

    function couponBuildPlanLabel($plan, $percent){

        $original = couponFormatPriceThousands($plan['price'] ?? 0);
        $discounted = couponApplyDiscountThousands($plan['price'] ?? 0, $percent);
        $finalText = couponFormatPriceThousands($discounted);

        if($percent <= 0){
            return ($plan['name'] ?? '') . ' - ' . $original;
        }

        return ($plan['name'] ?? '')
            . ' - '
            . $original
            . ' | تخفیف '
            . $percent
            . '% → '
            . $finalText;

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
        $original = intval($plan['price']);
        $final = couponApplyDiscountThousands($original, $percent);

        return [
            'ok' => true,
            'code' => $validation['code'],
            'percent' => $percent,
            'plan_name' => $plan['name'] ?? '',
            'original_text' => couponFormatPriceThousands($original),
            'final_text' => couponFormatPriceThousands($final),
            'original_amount' => $original,
            'final_amount' => $final,
            'plan_label' => couponBuildPlanLabel($plan, $percent)
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
