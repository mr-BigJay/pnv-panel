<?php

if(!function_exists('profileUsersPath')){

    function profileUsersPath(){
        return __DIR__ . '/db/users.json';
    }

    function profileAvatarsDir(){
        return __DIR__ . '/uploads/avatars';
    }

    function profileLoadUsers(){
        $path = profileUsersPath();

        if(!file_exists($path)){
            return [];
        }

        $data = json_decode(file_get_contents($path), true);
        return is_array($data) ? $data : [];
    }

    function profileSaveUsers($users){
        file_put_contents(
            profileUsersPath(),
            json_encode(
                $users,
                JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
            ),
            LOCK_EX
        );
    }

    function profileFindUserIndex($users, $username){
        foreach($users as $i => $user){
            if(strcasecmp(trim($user['username'] ?? ''), trim($username)) === 0){
                return $i;
            }
        }

        return -1;
    }

    function profileValidateUsername($username){
        $username = trim((string)$username);

        if(strlen($username) < 6 || strlen($username) > 20){
            return 'نام کاربری باید بین 6 تا 20 کاراکتر باشد';
        }

        if(!preg_match('/^[a-zA-Z0-9._-]+$/', $username)){
            return 'نام کاربری فقط میتواند شامل حروف لاتین، عدد و . _ - باشد';
        }

        return '';
    }

    function profileGetUserAvatar($username){
        $users = profileLoadUsers();
        $index = profileFindUserIndex($users, $username);

        if($index < 0){
            return '';
        }

        $avatar = trim((string)($users[$index]['avatar'] ?? ''));

        if($avatar !== '' && file_exists(__DIR__ . '/' . ltrim($avatar, '/'))){
            return $avatar;
        }

        return '';
    }

    function profileUploadAvatar($username, $file){
        if(!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])){
            return ['ok' => false, 'error' => 'فایل ارسال نشد'];
        }

        if(($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK){
            return ['ok' => false, 'error' => 'خطا در آپلود فایل'];
        }

        if(($file['size'] ?? 0) > 2 * 1024 * 1024){
            return ['ok' => false, 'error' => 'حجم عکس باید حداکثر 2 مگابایت باشد'];
        }

        $ext = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];

        if(!in_array($ext, $allowed, true)){
            return ['ok' => false, 'error' => 'فقط فرمت‌های JPG، PNG و WEBP مجاز هستند'];
        }

        $info = @getimagesize($file['tmp_name']);

        if($info === false){
            return ['ok' => false, 'error' => 'فایل انتخاب‌شده یک تصویر معتبر نیست'];
        }

        $users = profileLoadUsers();
        $index = profileFindUserIndex($users, $username);

        if($index < 0){
            return ['ok' => false, 'error' => 'کاربر پیدا نشد'];
        }

        $dir = profileAvatarsDir();

        if(!is_dir($dir)){
            @mkdir($dir, 0755, true);
        }

        $safeBase = preg_replace('/[^a-zA-Z0-9._-]+/', '_', $username);
        $filename = $safeBase . '_' . time() . '.' . ($ext === 'jpeg' ? 'jpg' : $ext);
        $destAbs = $dir . '/' . $filename;
        $destRel = 'uploads/avatars/' . $filename;

        if(!move_uploaded_file($file['tmp_name'], $destAbs)){
            return ['ok' => false, 'error' => 'ذخیره عکس انجام نشد'];
        }

        $oldAvatar = trim((string)($users[$index]['avatar'] ?? ''));

        if($oldAvatar !== ''){
            $oldPath = __DIR__ . '/' . ltrim($oldAvatar, '/');

            if(is_file($oldPath)){
                @unlink($oldPath);
            }
        }

        $users[$index]['avatar'] = $destRel;
        profileSaveUsers($users);

        return [
            'ok' => true,
            'avatar' => $destRel
        ];
    }

    function profileUpdateCsvUsername($path, $oldUsername, $newUsername){
        if(!file_exists($path)){
            return;
        }

        $rows = [];
        $handle = fopen($path, 'r');

        if(!$handle){
            return;
        }

        while(($row = fgetcsv($handle)) !== false){
            if(isset($row[0]) && strcasecmp(trim($row[0]), $oldUsername) === 0){
                $row[0] = $newUsername;
            }

            $rows[] = $row;
        }

        fclose($handle);

        $handle = fopen($path, 'w');

        if(!$handle){
            return;
        }

        foreach($rows as $row){
            fputcsv($handle, $row);
        }

        fclose($handle);
    }

    function profileUpdateJsonUsername($path, $oldUsername, $newUsername, $field){
        if(!file_exists($path)){
            return;
        }

        $data = json_decode(file_get_contents($path), true);

        if(!is_array($data)){
            return;
        }

        $changed = false;

        foreach($data as &$item){
            if(!is_array($item)){
                continue;
            }

            if(strcasecmp(trim($item[$field] ?? ''), $oldUsername) === 0){
                $item[$field] = $newUsername;
                $changed = true;
            }
        }
        unset($item);

        if($changed){
            file_put_contents(
                $path,
                json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
                LOCK_EX
            );
        }
    }

    function profileUpdateCouponOwners($oldUsername, $newUsername){
        $path = __DIR__ . '/db/coupons.json';

        if(!file_exists($path)){
            return;
        }

        $data = json_decode(file_get_contents($path), true);

        if(!is_array($data)){
            return;
        }

        $changed = false;

        foreach($data as &$coupon){
            if(!is_array($coupon)){
                continue;
            }

            if(strcasecmp(trim($coupon['owner'] ?? ''), $oldUsername) === 0){
                $coupon['owner'] = $newUsername;
                $changed = true;
            }
        }
        unset($coupon);

        if($changed){
            file_put_contents(
                $path,
                json_encode(array_values($data), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
                LOCK_EX
            );
        }
    }

    function profileUpdateClearedSubsKey($oldUsername, $newUsername){
        $path = __DIR__ . '/db/cleared_subscriptions.json';

        if(!file_exists($path)){
            return;
        }

        $data = json_decode(file_get_contents($path), true);

        if(!is_array($data)){
            return;
        }

        $oldKey = strtolower(trim($oldUsername));
        $newKey = strtolower(trim($newUsername));

        if($oldKey !== '' && isset($data[$oldKey])){
            $data[$newKey] = $data[$oldKey];
            unset($data[$oldKey]);

            file_put_contents(
                $path,
                json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
                LOCK_EX
            );
        }
    }

    function profileChangeUsername($oldUsername, $newUsername){
        $oldUsername = trim((string)$oldUsername);
        $newUsername = trim((string)$newUsername);

        if($oldUsername === ''){
            return ['ok' => false, 'error' => 'کاربر پیدا نشد'];
        }

        if(strcasecmp($oldUsername, $newUsername) === 0){
            return ['ok' => true, 'username' => $oldUsername];
        }

        $validationError = profileValidateUsername($newUsername);

        if($validationError !== ''){
            return ['ok' => false, 'error' => $validationError];
        }

        $users = profileLoadUsers();
        $index = profileFindUserIndex($users, $oldUsername);

        if($index < 0){
            return ['ok' => false, 'error' => 'کاربر پیدا نشد'];
        }

        if(profileFindUserIndex($users, $newUsername) >= 0){
            return ['ok' => false, 'error' => 'این نام کاربری قبلاً استفاده شده است'];
        }

        $users[$index]['username'] = $newUsername;
        profileSaveUsers($users);

        profileUpdateCsvUsername(__DIR__ . '/invoices/payments.csv', $oldUsername, $newUsername);
        profileUpdateJsonUsername(__DIR__ . '/db/support.json', $oldUsername, $newUsername, 'user');
        profileUpdateJsonUsername(__DIR__ . '/db/instant_payments.json', $oldUsername, $newUsername, 'user');
        profileUpdateCouponOwners($oldUsername, $newUsername);
        profileUpdateClearedSubsKey($oldUsername, $newUsername);

        if(isset($_SESSION) && is_array($_SESSION)){
            $_SESSION['user'] = $newUsername;
        }

        return [
            'ok' => true,
            'username' => $newUsername
        ];
    }

}
