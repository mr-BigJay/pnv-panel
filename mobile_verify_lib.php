<?php

if(!function_exists('mobileVerifyOtpTtl')){

    function mobileVerifyOtpTtl(){
        return 120;
    }

    function mobileVerifyResendCooldown(){
        return 30;
    }

    function mobileVerifySessionKey(){
        return 'mobile_verify_otp';
    }

    function mobileVerifyUserNeedsVerification($userRow){
        if(!is_array($userRow)){
            return false;
        }

        return array_key_exists('mobile_verified', $userRow) && empty($userRow['mobile_verified']);
    }

    function mobileVerifyGetUserRow($username){
        require_once __DIR__ . '/profile_lib.php';

        $users = profileLoadUsers();
        $index = profileFindUserIndex($users, $username);

        if($index < 0){
            return [null, -1, $users];
        }

        return [$users[$index], $index, $users];
    }

    function mobileVerifyClearSession(){
        unset($_SESSION[mobileVerifySessionKey()]);
    }

    function mobileVerifyStoreSession($username, $mobile, $code){
        $_SESSION[mobileVerifySessionKey()] = [
            'username' => (string)$username,
            'mobile' => (string)$mobile,
            'code' => (string)$code,
            'expires_at' => time() + mobileVerifyOtpTtl(),
            'sent_at' => time(),
        ];
    }

    function mobileVerifyGetSession(){
        $data = $_SESSION[mobileVerifySessionKey()] ?? null;

        return is_array($data) ? $data : null;
    }

    function mobileVerifyGenerateCode(){
        return str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    function mobileVerifySendCode($username){
        if(session_status() !== PHP_SESSION_ACTIVE){
            session_start();
        }

        [$userRow, $index, $users] = mobileVerifyGetUserRow($username);

        if($index < 0){
            return ['ok' => false, 'error' => 'کاربر پیدا نشد.'];
        }

        if(!mobileVerifyUserNeedsVerification($userRow)){
            return ['ok' => false, 'error' => 'تایید شماره برای این حساب لازم نیست.'];
        }

        $mobile = trim((string)($userRow['mobile'] ?? ''));

        if(!preg_match('/^09[0-9]{9}$/', $mobile)){
            return ['ok' => false, 'error' => 'شماره موبایل حساب نامعتبر است.'];
        }

        $existing = mobileVerifyGetSession();

        if(
            is_array($existing)
            && ($existing['username'] ?? '') === $username
            && (int)($existing['sent_at'] ?? 0) + mobileVerifyResendCooldown() > time()
        ){
            $wait = (int)($existing['sent_at'] ?? 0) + mobileVerifyResendCooldown() - time();

            return [
                'ok' => false,
                'error' => 'لطفاً ' . max(1, $wait) . ' ثانیه دیگر دوباره تلاش کنید.',
                'retry_in' => max(1, $wait),
            ];
        }

        if(!file_exists(__DIR__ . '/sms_lib.php')){
            return ['ok' => false, 'error' => 'سرویس پیامک روی سرور فعال نیست.'];
        }

        require_once __DIR__ . '/sms_lib.php';

        $code = mobileVerifyGenerateCode();
        $result = smsSendVerifyCode($mobile, $code);

        if(empty($result['ok'])){
            return [
                'ok' => false,
                'error' => $result['error'] ?? 'ارسال پیامک انجام نشد.',
            ];
        }

        mobileVerifyStoreSession($username, $mobile, $code);

        return [
            'ok' => true,
            'expires_in' => mobileVerifyOtpTtl(),
            'mobile' => $mobile,
        ];
    }

    function mobileVerifyCheckCode($username, $code){
        if(session_status() !== PHP_SESSION_ACTIVE){
            session_start();
        }

        $code = preg_replace('/\D+/', '', trim((string)$code));

        if(strlen($code) !== 6){
            return ['ok' => false, 'error' => 'کد وارد اشتباه است', 'invalid_code' => true];
        }

        [$userRow, $index, $users] = mobileVerifyGetUserRow($username);

        if($index < 0){
            return ['ok' => false, 'error' => 'کاربر پیدا نشد.'];
        }

        if(!mobileVerifyUserNeedsVerification($userRow)){
            mobileVerifyClearSession();
            return ['ok' => true, 'already_verified' => true];
        }

        $session = mobileVerifyGetSession();

        if(!is_array($session) || ($session['username'] ?? '') !== $username){
            return ['ok' => false, 'error' => 'کد منقضی شده است. دوباره دریافت کنید.', 'expired' => true];
        }

        if((int)($session['expires_at'] ?? 0) < time()){
            mobileVerifyClearSession();
            return ['ok' => false, 'error' => 'کد منقضی شده است. دوباره دریافت کنید.', 'expired' => true];
        }

        if(!hash_equals((string)($session['code'] ?? ''), $code)){
            return ['ok' => false, 'error' => 'کد وارد اشتباه است', 'invalid_code' => true];
        }

        $users[$index]['mobile_verified'] = true;
        $users[$index]['mobile_verified_at'] = function_exists('pnvNowParts')
            ? pnvNowParts()['datetime']
            : date('Y-m-d H:i:s');

        profileSaveUsers($users);
        mobileVerifyClearSession();

        return ['ok' => true];
    }

    function mobileVerifyGuardRedirectIfNeeded($username){
        [$userRow] = mobileVerifyGetUserRow($username);

        if(!mobileVerifyUserNeedsVerification($userRow)){
            return;
        }

        $self = basename($_SERVER['SCRIPT_NAME'] ?? '');

        if($self === 'dashboard.php'){
            return;
        }

        header('Location: dashboard.php');
        exit;
    }

    function mobileVerifyGuardApiIfNeeded($username){
        [$userRow] = mobileVerifyGetUserRow($username);

        if(!mobileVerifyUserNeedsVerification($userRow)){
            return;
        }

        http_response_code(403);
        echo json_encode([
            'ok' => false,
            'error' => 'ابتدا شماره تماس خود را تایید کنید.',
            'mobile_verify_required' => true,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

}
