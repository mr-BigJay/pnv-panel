<?php

if(!function_exists('str_ends_with')){
    function str_ends_with($haystack, $needle){
        $haystack = (string)$haystack;
        $needle = (string)$needle;

        if($needle === ''){
            return true;
        }

        $len = strlen($needle);

        return $len === 0 || substr($haystack, -$len) === $needle;
    }
}

if(!function_exists('pnvBackupRootDir')){

    function pnvBackupRootDir(){
        return __DIR__;
    }

    function pnvBackupDbDir(){
        return pnvBackupRootDir() . '/db';
    }

    function pnvBackupInvoicesDir(){
        return pnvBackupRootDir() . '/invoices';
    }

    /**
     * Relative paths included in full database backup (under project root).
     */
    function pnvBackupDefaultIncludes(){
        return [
            'db/admins.json',
            'db/users.json',
            'db/plans.json',
            'db/cards.json',
            'db/coupons.json',
            'db/support.json',
            'db/discount_codes.json',
            'db/discount_code_usages.json',
            'db/dashboard_announcements.json',
            'db/dashboard_announcement_reads.json',
            'db/register_limit.json',
            'db/cleared_subscriptions.json',
            'db/renews.json',
            'db/bale.json',
            'db/telegram.json',
            'db/telegram_sessions.json',
            'db/telegram_reminders.json',
            'db/xui_servers.json',
            'db/xui_state.json',
            'db/instant_payments.json',
            'db/sms.json',
            'db/vip.csv',
            'db/vip2.csv',
            'db/vip3.csv',
            'invoices/payments.csv',
        ];
    }

    function pnvBackupOptionalIncludes(){
        return [
            'db/sub_usage_cache.json',
            'db/telegram_updates.json',
        ];
    }

    function pnvBackupResolvePath($relativePath){
        $relativePath = str_replace('\\', '/', trim((string)$relativePath));
        $relativePath = ltrim($relativePath, '/');

        if($relativePath === '' || strpos($relativePath, '..') !== false){
            return null;
        }

        $root = realpath(pnvBackupRootDir());
        if($root === false){
            return null;
        }

        $full = $root . '/' . $relativePath;
        $dir = dirname($full);
        if(!is_dir($dir)){
            @mkdir($dir, 0755, true);
        }

        $resolved = realpath($full);
        if($resolved === false){
            $resolved = $full;
        }

        if(strpos($resolved, $root) !== 0){
            return null;
        }

        return $resolved;
    }

    function pnvBackupCollectFiles($includeOptional = false){
        $includes = pnvBackupDefaultIncludes();
        if($includeOptional){
            $includes = array_merge($includes, pnvBackupOptionalIncludes());
        }

        $files = [];
        foreach($includes as $relative){
            $path = pnvBackupResolvePath($relative);
            if($path === null){
                continue;
            }

            $exists = is_file($path);
            $files[] = [
                'relative' => $relative,
                'path' => $path,
                'exists' => $exists,
                'size' => $exists ? (int)filesize($path) : 0,
                'mtime' => $exists ? (int)filemtime($path) : 0,
            ];
        }

        return $files;
    }

    function pnvBackupManifestVersion(){
        return 'pnv-backup-1';
    }

    function pnvBackupBuildManifest($files){
        $entries = [];
        foreach($files as $file){
            if(empty($file['exists'])){
                continue;
            }

            $entries[] = [
                'path' => $file['relative'],
                'size' => (int)$file['size'],
                'sha256' => hash_file('sha256', $file['path']),
            ];
        }

        return [
            'format' => pnvBackupManifestVersion(),
            'created_at' => gmdate('c'),
            'panel' => 'pnv-panel',
            'files' => $entries,
        ];
    }

    function pnvBackupExportZip($includeOptional = false){
        if(!class_exists('ZipArchive')){
            return ['ok' => false, 'error' => 'افزونه ZipArchive در PHP فعال نیست.'];
        }

        $files = pnvBackupCollectFiles($includeOptional);
        $manifest = pnvBackupBuildManifest($files);

        if(count($manifest['files']) === 0){
            return ['ok' => false, 'error' => 'هیچ فایل دیتابیسی برای بک‌آپ پیدا نشد.'];
        }

        $tmpDir = pnvBackupRootDir() . '/temp';
        if(!is_dir($tmpDir)){
            @mkdir($tmpDir, 0755, true);
        }

        $stamp = date('Ymd-His');
        $zipPath = $tmpDir . '/pnv-db-backup-' . $stamp . '.zip';

        $zip = new ZipArchive();
        if($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true){
            return ['ok' => false, 'error' => 'ساخت فایل ZIP ناموفق بود.'];
        }

        $zip->addFromString('manifest.json', json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        foreach($files as $file){
            if(empty($file['exists'])){
                continue;
            }
            $zip->addFile($file['path'], $file['relative']);
        }

        $zip->close();

        return [
            'ok' => true,
            'path' => $zipPath,
            'filename' => basename($zipPath),
            'count' => count($manifest['files']),
            'size' => (int)filesize($zipPath),
        ];
    }

    function pnvBackupValidateManifest($manifest){
        if(!is_array($manifest)){
            return 'manifest.json نامعتبر است.';
        }

        if(($manifest['format'] ?? '') !== pnvBackupManifestVersion()){
            return 'نسخه بک‌آپ پشتیبانی نمی‌شود.';
        }

        if(!is_array($manifest['files'] ?? null) || count($manifest['files']) === 0){
            return 'فایل بک‌آپ خالی است.';
        }

        $allowed = array_flip(array_merge(pnvBackupDefaultIncludes(), pnvBackupOptionalIncludes()));

        foreach($manifest['files'] as $entry){
            $path = (string)($entry['path'] ?? '');
            if($path === '' || !isset($allowed[$path])){
                return 'مسیر غیرمجاز در بک‌آپ: ' . $path;
            }
        }

        return null;
    }

    function pnvBackupPreImportSnapshot(){
        $result = pnvBackupExportZip(true);
        if(empty($result['ok'])){
            return $result;
        }

        $archiveDir = pnvBackupDbDir() . '/backups';
        if(!is_dir($archiveDir)){
            @mkdir($archiveDir, 0755, true);
        }

        $dest = $archiveDir . '/pre-import-' . date('Ymd-His') . '.zip';
        if(!@rename($result['path'], $dest)){
            @copy($result['path'], $dest);
            @unlink($result['path']);
        }

        return ['ok' => true, 'path' => $dest, 'filename' => basename($dest)];
    }

    function pnvBackupImportZip($uploadedPath){
        if(!class_exists('ZipArchive')){
            return ['ok' => false, 'error' => 'افزونه ZipArchive در PHP فعال نیست.'];
        }

        if(!is_file($uploadedPath)){
            return ['ok' => false, 'error' => 'فایل آپلود شده یافت نشد.'];
        }

        $zip = new ZipArchive();
        if($zip->open($uploadedPath) !== true){
            return ['ok' => false, 'error' => 'باز کردن ZIP ناموفق بود.'];
        }

        $manifestRaw = $zip->getFromName('manifest.json');
        if($manifestRaw === false){
            $zip->close();
            return ['ok' => false, 'error' => 'manifest.json در بک‌آپ وجود ندارد.'];
        }

        $manifest = json_decode($manifestRaw, true);
        $manifestError = pnvBackupValidateManifest($manifest);
        if($manifestError !== null){
            $zip->close();
            return ['ok' => false, 'error' => $manifestError];
        }

        $snapshot = pnvBackupPreImportSnapshot();
        if(empty($snapshot['ok'])){
            $zip->close();
            return ['ok' => false, 'error' => 'بک‌آپ خودکار قبل از ایمپورت ناموفق بود: ' . ($snapshot['error'] ?? '')];
        }

        $restored = [];
        $skipped = [];

        foreach($manifest['files'] as $entry){
            $relative = (string)($entry['path'] ?? '');
            $target = pnvBackupResolvePath($relative);
            if($target === null){
                $skipped[] = $relative;
                continue;
            }

            $content = $zip->getFromName($relative);
            if($content === false){
                $skipped[] = $relative;
                continue;
            }

            $expectedHash = (string)($entry['sha256'] ?? '');
            if($expectedHash !== '' && !hash_equals($expectedHash, hash('sha256', $content))){
                $zip->close();
                return ['ok' => false, 'error' => 'checksum فایل «' . $relative . '» مطابقت ندارد.'];
            }

            if(str_ends_with(strtolower($relative), '.json')){
                $decoded = json_decode($content, true);
                if($content !== '' && json_last_error() !== JSON_ERROR_NONE){
                    $zip->close();
                    return ['ok' => false, 'error' => 'JSON نامعتبر: ' . $relative];
                }
            }

            $dir = dirname($target);
            if(!is_dir($dir)){
                @mkdir($dir, 0755, true);
            }

            if(file_put_contents($target, $content, LOCK_EX) === false){
                $zip->close();
                return ['ok' => false, 'error' => 'نوشتن فایل «' . $relative . '» ناموفق بود.'];
            }

            $restored[] = $relative;
        }

        $zip->close();

        return [
            'ok' => true,
            'restored' => $restored,
            'skipped' => $skipped,
            'snapshot' => $snapshot['filename'] ?? '',
            'count' => count($restored),
        ];
    }

    function pnvBackupFormatBytes($bytes){
        $bytes = max(0, (int)$bytes);
        if($bytes < 1024){
            return $bytes . ' B';
        }
        if($bytes < 1048576){
            return round($bytes / 1024, 1) . ' KB';
        }
        return round($bytes / 1048576, 2) . ' MB';
    }

}
