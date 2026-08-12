<?php

if(!function_exists('profileUsersPath')){

    function profileUsersPath(){
        return __DIR__ . '/db/users.json';
    }

    function profileAvatarsDir(){
        return __DIR__ . '/uploads/avatars';
    }

    function profileEnsureAvatarsDir(){
        $uploadsDir = __DIR__ . '/uploads';
        $dir = profileAvatarsDir();

        if(!is_dir($uploadsDir)){
            @mkdir($uploadsDir, 0777, true);
        }

        if(!is_dir($dir)){
            @mkdir($dir, 0777, true);
        }

        if(is_dir($dir) && !is_writable($dir)){
            @chmod($dir, 0777);
        }

        return is_dir($dir) && is_writable($dir);
    }

    function profileUploadErrorMessage($code){
        switch((int)$code){
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return 'حجم عکس بیش از حد مجاز است';
            case UPLOAD_ERR_PARTIAL:
                return 'آپلود عکس ناقص بود';
            case UPLOAD_ERR_NO_FILE:
                return 'فایلی انتخاب نشده است';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'پوشه موقت سرور در دسترس نیست';
            case UPLOAD_ERR_CANT_WRITE:
                return 'سرور اجازه نوشتن فایل را ندارد';
            default:
                return 'خطا در آپلود فایل';
        }
    }

    function profileSaveUploadedImage($tmpPath, $destAbs){
        if(is_uploaded_file($tmpPath)){
            if(@move_uploaded_file($tmpPath, $destAbs)){
                return true;
            }
        }

        if(!is_readable($tmpPath)){
            return false;
        }

        if(@copy($tmpPath, $destAbs)){
            return true;
        }

        $contents = @file_get_contents($tmpPath);

        if($contents === false){
            return false;
        }

        return @file_put_contents($destAbs, $contents) !== false;
    }

    function profileLoadUsers(){
        static $cached = null;
        static $cachedMtime = null;

        $path = profileUsersPath();

        if(!file_exists($path)){
            return [];
        }

        $mtime = @filemtime($path);

        if(is_array($cached) && $cachedMtime === $mtime){
            return $cached;
        }

        $data = json_decode((string)file_get_contents($path), true);
        $cached = is_array($data) ? $data : [];
        $cachedMtime = $mtime;

        return $cached;
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
        $tmpName = (string)($file['tmp_name'] ?? '');
        $uploadError = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);

        if($tmpName === ''){
            return ['ok' => false, 'error' => profileUploadErrorMessage($uploadError)];
        }

        if($uploadError !== UPLOAD_ERR_OK){
            return ['ok' => false, 'error' => profileUploadErrorMessage($uploadError)];
        }

        if(($file['size'] ?? 0) > 5 * 1024 * 1024){
            return ['ok' => false, 'error' => 'حجم عکس باید حداکثر 5 مگابایت باشد'];
        }

        $mime = '';

        if(function_exists('finfo_open')){
            $finfo = finfo_open(FILEINFO_MIME_TYPE);

            if($finfo){
                $mime = (string)finfo_file($finfo, $tmpName);
                finfo_close($finfo);
            }
        }

        if($mime === '' && function_exists('mime_content_type')){
            $mime = (string)@mime_content_type($tmpName);
        }

        $ext = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];

        if($mime === 'image/jpeg' || $ext === 'jpg' || $ext === 'jpeg'){
            $ext = 'jpg';
        }
        elseif($mime === 'image/png' || $ext === 'png'){
            $ext = 'png';
        }
        elseif($mime === 'image/webp' || $ext === 'webp'){
            $ext = 'webp';
        }
        elseif(!in_array($ext, $allowed, true)){
            return ['ok' => false, 'error' => 'فقط فرمت‌های JPG، PNG و WEBP مجاز هستند'];
        }

        $info = @getimagesize($tmpName);

        if($info === false){
            return ['ok' => false, 'error' => 'فایل انتخاب‌شده یک تصویر معتبر نیست'];
        }

        $users = profileLoadUsers();
        $index = profileFindUserIndex($users, $username);

        if($index < 0){
            return ['ok' => false, 'error' => 'کاربر پیدا نشد'];
        }

        if(!profileEnsureAvatarsDir()){
            return ['ok' => false, 'error' => 'پوشه ذخیره عکس در دسترس نیست. دسترسی uploads/avatars را بررسی کنید.'];
        }

        $safeBase = preg_replace('/[^a-zA-Z0-9._-]+/', '_', $username);
        $filename = $safeBase . '_' . time() . '.' . ($ext === 'jpeg' ? 'jpg' : $ext);
        $destAbs = profileAvatarsDir() . '/' . $filename;
        $destRel = 'uploads/avatars/' . $filename;

        if(!profileSaveUploadedImage($tmpName, $destAbs)){
            return ['ok' => false, 'error' => 'ذخیره عکس انجام نشد. دسترسی پوشه uploads/avatars را بررسی کنید.'];
        }

        @chmod($destAbs, 0644);

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

    function profileAdminsPath(){
        return __DIR__ . '/db/admins.json';
    }

    function profileLoadAdmins(){
        $path = profileAdminsPath();

        if(!file_exists($path)){
            return [];
        }

        $data = json_decode(file_get_contents($path), true);
        return is_array($data) ? $data : [];
    }

    function profileSaveAdmins($admins){
        file_put_contents(
            profileAdminsPath(),
            json_encode(
                $admins,
                JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
            ),
            LOCK_EX
        );
    }

    function profileFindAdminIndex($admins, $username){
        foreach($admins as $i => $admin){
            if(strcasecmp(trim($admin['username'] ?? ''), trim($username)) === 0){
                return $i;
            }
        }

        return -1;
    }

    function profileResolveAvatarPath($avatar){
        $avatar = trim((string)$avatar);

        if($avatar === ''){
            return '';
        }

        $path = __DIR__ . '/' . ltrim($avatar, '/');

        if(file_exists($path)){
            return $avatar;
        }

        return '';
    }

    function profileGetAdminAvatar($username){
        $admins = profileLoadAdmins();
        $index = profileFindAdminIndex($admins, $username);

        if($index < 0){
            return '';
        }

        return profileResolveAvatarPath($admins[$index]['avatar'] ?? '');
    }

    function profileGetSupportAdminAvatar(){
        $admins = profileLoadAdmins();

        foreach($admins as $admin){
            $avatar = profileResolveAvatarPath($admin['avatar'] ?? '');

            if($avatar !== ''){
                return $avatar;
            }
        }

        return '';
    }

    function profileUploadAdminAvatar($username, $file){
        $tmpName = (string)($file['tmp_name'] ?? '');
        $uploadError = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);

        if($tmpName === ''){
            return ['ok' => false, 'error' => profileUploadErrorMessage($uploadError)];
        }

        if($uploadError !== UPLOAD_ERR_OK){
            return ['ok' => false, 'error' => profileUploadErrorMessage($uploadError)];
        }

        if(($file['size'] ?? 0) > 5 * 1024 * 1024){
            return ['ok' => false, 'error' => 'حجم عکس باید حداکثر 5 مگابایت باشد'];
        }

        $mime = '';

        if(function_exists('finfo_open')){
            $finfo = finfo_open(FILEINFO_MIME_TYPE);

            if($finfo){
                $mime = (string)finfo_file($finfo, $tmpName);
                finfo_close($finfo);
            }
        }

        if($mime === '' && function_exists('mime_content_type')){
            $mime = (string)@mime_content_type($tmpName);
        }

        $ext = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];

        if($mime === 'image/jpeg' || $ext === 'jpg' || $ext === 'jpeg'){
            $ext = 'jpg';
        }
        elseif($mime === 'image/png' || $ext === 'png'){
            $ext = 'png';
        }
        elseif($mime === 'image/webp' || $ext === 'webp'){
            $ext = 'webp';
        }
        elseif(!in_array($ext, $allowed, true)){
            return ['ok' => false, 'error' => 'فقط فرمت‌های JPG، PNG و WEBP مجاز هستند'];
        }

        $info = @getimagesize($tmpName);

        if($info === false){
            return ['ok' => false, 'error' => 'فایل انتخاب‌شده یک تصویر معتبر نیست'];
        }

        $admins = profileLoadAdmins();
        $index = profileFindAdminIndex($admins, $username);

        if($index < 0){
            return ['ok' => false, 'error' => 'ادمین پیدا نشد'];
        }

        if(!profileEnsureAvatarsDir()){
            return ['ok' => false, 'error' => 'پوشه ذخیره عکس در دسترس نیست. دسترسی uploads/avatars را بررسی کنید.'];
        }

        $safeBase = 'admin_' . preg_replace('/[^a-zA-Z0-9._-]+/', '_', $username);
        $filename = $safeBase . '_' . time() . '.' . ($ext === 'jpeg' ? 'jpg' : $ext);
        $destAbs = profileAvatarsDir() . '/' . $filename;
        $destRel = 'uploads/avatars/' . $filename;

        if(!profileSaveUploadedImage($tmpName, $destAbs)){
            return ['ok' => false, 'error' => 'ذخیره عکس انجام نشد. دسترسی پوشه uploads/avatars را بررسی کنید.'];
        }

        @chmod($destAbs, 0644);

        $oldAvatar = trim((string)($admins[$index]['avatar'] ?? ''));

        if($oldAvatar !== ''){
            $oldPath = __DIR__ . '/' . ltrim($oldAvatar, '/');

            if(is_file($oldPath)){
                @unlink($oldPath);
            }
        }

        $admins[$index]['avatar'] = $destRel;
        profileSaveAdmins($admins);

        return [
            'ok' => true,
            'avatar' => $destRel
        ];
    }

    function profileRemoveAdminAvatar($username){
        $admins = profileLoadAdmins();
        $index = profileFindAdminIndex($admins, $username);

        if($index < 0){
            return ['ok' => false, 'error' => 'ادمین پیدا نشد'];
        }

        $oldAvatar = trim((string)($admins[$index]['avatar'] ?? ''));

        if($oldAvatar !== ''){
            $oldPath = __DIR__ . '/' . ltrim($oldAvatar, '/');

            if(is_file($oldPath)){
                @unlink($oldPath);
            }
        }

        unset($admins[$index]['avatar']);
        profileSaveAdmins($admins);

        return ['ok' => true];
    }

}
