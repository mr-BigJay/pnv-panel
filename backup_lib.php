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

    /**
     * @return array<string,array{label:string,paths:array<int,string>}>
     */
    function pnvBackupSections(){
        return [
            'users' => [
                'label' => 'Karbaran va avatar',
                'paths' => ['db/users.json', 'uploads/avatars/'],
            ],
            'admins' => [
                'label' => 'Admin ha',
                'paths' => ['db/admins.json'],
            ],
            'payments' => [
                'label' => 'Kharid ha va pardakht',
                'paths' => ['invoices/payments.csv', 'db/instant_payments.json', 'db/renews.json', 'db/cleared_subscriptions.json'],
            ],
            'plans' => [
                'label' => 'Plan ha va takhfif',
                'paths' => [
                    'db/plans.json',
                    'db/cards.json',
                    'db/coupons.json',
                    'db/discount_codes.json',
                    'db/discount_code_usages.json',
                ],
            ],
            'support' => [
                'label' => 'Peygiri / payam ha',
                'paths' => ['db/support.json', 'uploads/support/'],
            ],
            'bots' => [
                'label' => 'Telegram va Bale',
                'paths' => [
                    'db/bale.json',
                    'db/telegram.json',
                    'db/telegram_sessions.json',
                    'db/telegram_reminders.json',
                    'db/telegram_updates.json',
                ],
            ],
            'xui' => [
                'label' => '3x-ui servers',
                'paths' => [
                    'db/xui_servers.json',
                    'db/xui_state.json',
                    'db/vip.csv',
                    'db/vip2.csv',
                    'db/vip3.csv',
                ],
            ],
            'sms' => [
                'label' => 'SMS panel',
                'paths' => ['db/sms.json'],
            ],
            'announcements' => [
                'label' => 'Elan haye dashboard',
                'paths' => ['db/dashboard_announcements.json', 'db/dashboard_announcement_reads.json'],
            ],
            'settings' => [
                'label' => 'Tanzimat digar',
                'paths' => ['db/register_limit.json'],
            ],
            'cache' => [
                'label' => 'Cache (ekhtiyari)',
                'paths' => ['db/sub_usage_cache.json'],
            ],
            'qr_temp' => [
                'label' => 'QR temp (ekhtiyari)',
                'paths' => ['temp/'],
            ],
        ];
    }

    function pnvBackupAllRelativePaths(){
        $paths = [];

        foreach(pnvBackupSections() as $section){
            foreach($section['paths'] as $relative){
                $paths[] = $relative;
            }
        }

        return array_values(array_unique($paths));
    }

    function pnvBackupResolvePath($relativePath){
        $relativePath = str_replace('\\', '/', trim((string)$relativePath));
        $relativePath = rtrim($relativePath, '/');

        if($relativePath === '' || strpos($relativePath, '..') !== false){
            return null;
        }

        $root = realpath(pnvBackupRootDir());
        if($root === false){
            return null;
        }

        $full = pnvBackupRootDir() . '/' . $relativePath;
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

    function pnvBackupRelativeFromFull($fullPath){
        $root = realpath(pnvBackupRootDir());
        $fullPath = realpath($fullPath) ?: $fullPath;

        if($root === false || strpos($fullPath, $root) !== 0){
            return null;
        }

        return ltrim(str_replace('\\', '/', substr($fullPath, strlen($root))), '/');
    }

    function pnvBackupSectionForRelative($relativePath){
        $relativePath = str_replace('\\', '/', trim((string)$relativePath));

        foreach(pnvBackupSections() as $key => $section){
            foreach($section['paths'] as $pattern){
                $pattern = rtrim(str_replace('\\', '/', $pattern), '/');

                if($pattern === $relativePath){
                    return $key;
                }

                if(str_ends_with($pattern, '/') && str_starts_with($relativePath . '/', $pattern)){
                    return $key;
                }
            }
        }

        return 'other';
    }

    function pnvBackupExpandPathEntry($relativePath){
        $relativePath = str_replace('\\', '/', trim((string)$relativePath));
        $entries = [];

        if($relativePath === '' || strpos($relativePath, '..') !== false){
            return $entries;
        }

        $isDirPattern = str_ends_with($relativePath, '/');
        $relativePath = rtrim($relativePath, '/');
        $resolved = pnvBackupResolvePath($relativePath);

        if($resolved === null){
            return $entries;
        }

        if(is_file($resolved)){
            $entries[] = [
                'relative' => $relativePath,
                'path' => $resolved,
                'exists' => true,
                'size' => (int)filesize($resolved),
                'mtime' => (int)filemtime($resolved),
                'type' => 'file',
            ];

            return $entries;
        }

        if(is_dir($resolved) || $isDirPattern){
            if(!is_dir($resolved)){
                return $entries;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($resolved, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );

            foreach($iterator as $item){
                if(!$item->isFile()){
                    continue;
                }

                $full = $item->getPathname();
                $rel = pnvBackupRelativeFromFull($full);

                if($rel === null){
                    continue;
                }

                $entries[] = [
                    'relative' => $rel,
                    'path' => $full,
                    'exists' => true,
                    'size' => (int)$item->getSize(),
                    'mtime' => (int)$item->getMTime(),
                    'type' => 'file',
                ];
            }

            return $entries;
        }

        $entries[] = [
            'relative' => $relativePath,
            'path' => $resolved,
            'exists' => false,
            'size' => 0,
            'mtime' => 0,
            'type' => 'file',
        ];

        return $entries;
    }

    function pnvBackupCollectFiles($selectedSections = null){
        $sections = pnvBackupSections();

        if($selectedSections === null){
            $selectedSections = array_keys($sections);
        }

        if(!is_array($selectedSections)){
            $selectedSections = [];
        }

        $selectedSections = array_values(array_unique(array_map('strval', $selectedSections)));
        $files = [];
        $seen = [];

        foreach($selectedSections as $sectionKey){
            if(!isset($sections[$sectionKey])){
                continue;
            }

            foreach($sections[$sectionKey]['paths'] as $relative){
                foreach(pnvBackupExpandPathEntry($relative) as $file){
                    $rel = $file['relative'] ?? '';

                    if($rel === '' || isset($seen[$rel])){
                        continue;
                    }

                    $seen[$rel] = true;
                    $file['section'] = $sectionKey;
                    $files[] = $file;
                }
            }
        }

        usort($files, static function($a, $b){
            return strcmp($a['relative'] ?? '', $b['relative'] ?? '');
        });

        return $files;
    }

    function pnvBackupManifestVersion(){
        return 'pnv-backup-2';
    }

    function pnvBackupLegacyManifestVersion(){
        return 'pnv-backup-1';
    }

    function pnvBackupBuildManifest($files){
        $entries = [];
        $sectionCounts = [];

        foreach($files as $file){
            if(empty($file['exists'])){
                continue;
            }

            $section = (string)($file['section'] ?? pnvBackupSectionForRelative($file['relative'] ?? ''));
            $sectionCounts[$section] = ($sectionCounts[$section] ?? 0) + 1;

            $entries[] = [
                'path' => $file['relative'],
                'section' => $section,
                'size' => (int)$file['size'],
                'sha256' => hash_file('sha256', $file['path']),
            ];
        }

        return [
            'format' => pnvBackupManifestVersion(),
            'created_at' => gmdate('c'),
            'panel' => 'pnv-panel',
            'panel_version' => trim((string)@file_get_contents(pnvBackupRootDir() . '/VERSION')),
            'sections' => $sectionCounts,
            'files' => $entries,
        ];
    }

    function pnvBackupExportZip($selectedSections = null){
        if(!class_exists('ZipArchive')){
            return ['ok' => false, 'error' => 'افزونه ZipArchive در PHP فعال نیست.'];
        }

        if($selectedSections === true){
            $selectedSections = array_keys(pnvBackupSections());
        }
        elseif($selectedSections === false){
            $selectedSections = array_keys(array_diff_key(pnvBackupSections(), ['cache' => 1, 'qr_temp' => 1]));
        }

        $files = pnvBackupCollectFiles($selectedSections);
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
            'sections' => $manifest['sections'] ?? [],
        ];
    }

    function pnvBackupAllowedRelativePaths(){
        $allowed = [];

        foreach(pnvBackupAllRelativePaths() as $path){
            if(str_ends_with($path, '/')){
                continue;
            }
            $allowed[$path] = true;
        }

        foreach(pnvBackupExpandPathEntry('uploads/support/') as $file){
            $allowed[$file['relative']] = true;
        }
        foreach(pnvBackupExpandPathEntry('uploads/avatars/') as $file){
            $allowed[$file['relative']] = true;
        }
        foreach(pnvBackupExpandPathEntry('temp/') as $file){
            $allowed[$file['relative']] = true;
        }

        return $allowed;
    }

    function pnvBackupValidateManifest($manifest){
        if(!is_array($manifest)){
            return 'manifest.json نامعتبر است.';
        }

        $format = (string)($manifest['format'] ?? '');
        if($format !== pnvBackupManifestVersion() && $format !== pnvBackupLegacyManifestVersion()){
            return 'نسخه بک‌آپ پشتیبانی نمی‌شود.';
        }

        if(!is_array($manifest['files'] ?? null) || count($manifest['files']) === 0){
            return 'فایل بک‌آپ خالی است.';
        }

        $allowed = pnvBackupAllowedRelativePaths();

        foreach($manifest['files'] as $entry){
            $path = (string)($entry['path'] ?? '');
            if($path === ''){
                return 'مسیر خالی در manifest.';
            }

            if($format === pnvBackupLegacyManifestVersion()){
                $legacyAllowed = array_flip(array_merge(
                    [
                        'db/admins.json','db/users.json','db/plans.json','db/cards.json','db/coupons.json',
                        'db/support.json','db/discount_codes.json','db/discount_code_usages.json',
                        'db/dashboard_announcements.json','db/dashboard_announcement_reads.json',
                        'db/register_limit.json','db/cleared_subscriptions.json','db/renews.json',
                        'db/bale.json','db/telegram.json','db/telegram_sessions.json','db/telegram_reminders.json',
                        'db/xui_servers.json','db/xui_state.json','db/instant_payments.json','db/sms.json',
                        'db/vip.csv','db/vip2.csv','db/vip3.csv','invoices/payments.csv',
                        'db/sub_usage_cache.json','db/telegram_updates.json',
                    ]
                ));

                if(!isset($legacyAllowed[$path])){
                    return 'مسیر غیرمجاز در بک‌آپ: ' . $path;
                }

                continue;
            }

            if(!isset($allowed[$path])){
                return 'مسیر غیرمجاز در بک‌آپ: ' . $path;
            }
        }

        return null;
    }

    function pnvBackupManifestSections($manifest){
        $sections = [];
        $known = array_keys(pnvBackupSections());

        foreach(($manifest['files'] ?? []) as $entry){
            $section = (string)($entry['section'] ?? '');

            if($section === '' || !in_array($section, $known, true)){
                $section = pnvBackupSectionForRelative($entry['path'] ?? '');
            }

            if(!in_array($section, $known, true)){
                continue;
            }

            $sections[$section] = ($sections[$section] ?? 0) + 1;
        }

        return $sections;
    }

    function pnvBackupInspectZip($uploadedPath){
        if(!class_exists('ZipArchive')){
            return ['ok' => false, 'error' => 'ZipArchive faal nist.'];
        }

        if(!is_file($uploadedPath)){
            return ['ok' => false, 'error' => 'File peida nashod.'];
        }

        $zip = new ZipArchive();
        if($zip->open($uploadedPath) !== true){
            return ['ok' => false, 'error' => 'ZIP baz nashod.'];
        }

        $manifestRaw = $zip->getFromName('manifest.json');
        $zip->close();

        if($manifestRaw === false){
            return ['ok' => false, 'error' => 'manifest.json nadarad.'];
        }

        $manifest = json_decode($manifestRaw, true);
        $manifestError = pnvBackupValidateManifest($manifest);

        if($manifestError !== null){
            return ['ok' => false, 'error' => $manifestError];
        }

        return [
            'ok' => true,
            'manifest' => $manifest,
            'sections' => pnvBackupManifestSections($manifest),
            'file_count' => count($manifest['files'] ?? []),
            'created_at' => (string)($manifest['created_at'] ?? ''),
            'panel_version' => (string)($manifest['panel_version'] ?? ''),
        ];
    }

    function pnvBackupPreImportSnapshot(){
        $result = pnvBackupExportZip(array_keys(pnvBackupSections()));
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

    function pnvBackupImportZip($uploadedPath, $selectedSections = null){
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

        if($selectedSections === null){
            $selectedSections = array_keys(pnvBackupManifestSections($manifest));
        }

        if(!is_array($selectedSections)){
            $selectedSections = [];
        }

        $selectedSections = array_flip(array_values(array_unique(array_map('strval', $selectedSections))));

        $snapshot = pnvBackupPreImportSnapshot();
        if(empty($snapshot['ok'])){
            $zip->close();
            return ['ok' => false, 'error' => 'بک‌آپ خودکار قبل از ایمپورت ناموفق بود: ' . ($snapshot['error'] ?? '')];
        }

        $restored = [];
        $skipped = [];

        foreach($manifest['files'] as $entry){
            $relative = (string)($entry['path'] ?? '');
            $section = (string)($entry['section'] ?? pnvBackupSectionForRelative($relative));

            if($relative === ''){
                continue;
            }

            if($selectedSections !== [] && !isset($selectedSections[$section])){
                $skipped[] = $relative;
                continue;
            }

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
                if($content !== '' && json_decode($content, true) === null && json_last_error() !== JSON_ERROR_NONE){
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
            'sections' => array_keys($selectedSections),
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

    function pnvBackupSectionLabelsFa(){
        return [
            'users' => 'کاربران و آواتار',
            'admins' => 'ادمین‌ها',
            'payments' => 'خریدها و پرداخت‌ها',
            'plans' => 'پلن‌ها و تخفیف',
            'support' => 'پشتیبانی / پیام‌ها',
            'bots' => 'تلگرام و بله',
            'xui' => 'سرورهای 3x-ui',
            'sms' => 'پنل پیامک',
            'announcements' => 'اعلان‌های داشبورد',
            'settings' => 'تنظیمات',
            'cache' => 'کش مصرف (اختیاری)',
            'qr_temp' => 'QR موقت (اختیاری)',
        ];
    }
}

if(!function_exists('str_starts_with')){
    function str_starts_with($haystack, $needle){
        $haystack = (string)$haystack;
        $needle = (string)$needle;

        if($needle === ''){
            return true;
        }

        return strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}
