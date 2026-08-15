<?php

require_once __DIR__ . '/xui_lib.php';

if(is_file(__DIR__ . '/pnv_date_bootstrap.php')){
    require_once __DIR__ . '/pnv_date_bootstrap.php';
}

if(!function_exists('subUsageCachePath')){

    function subUsageCachePath(){
        return __DIR__ . '/db/sub_usage_cache.json';
    }

    function subUsageTtlSeconds(){
        return 60; // 1 دقیقه
    }

    function subUsageInvalidateLink($link){
        $link = trim((string)$link);

        if($link === ''){
            return;
        }

        $cache = subUsageLoadCache();
        $key = subUsageCacheKey($link);
        $dirty = false;

        if(isset($cache[$key])){
            unset($cache[$key]);
            $dirty = true;
        }

        $subId = '';

        if(function_exists('pnvExtractSubIdFromLink')){
            if(is_file(__DIR__ . '/plan_ui_lib.php')){
                require_once __DIR__ . '/plan_ui_lib.php';
            }

            if(function_exists('pnvExtractSubIdFromLink')){
                $subId = strtolower(pnvExtractSubIdFromLink($link));
            }
        }

        if($subId !== ''){
            foreach(array_keys($cache) as $cacheKey){
                if(stripos($cacheKey, $subId) !== false){
                    unset($cache[$cacheKey]);
                    $dirty = true;
                }
            }
        }

        if($dirty){
            subUsageSaveCache($cache);
        }
    }

    function subUsageLoadCache(){
        $path = subUsageCachePath();

        if(!file_exists($path)){
            return [];
        }

        $data = json_decode((string)@file_get_contents($path), true);
        return is_array($data) ? $data : [];
    }

    function subUsageSaveCache($cache){
        if(!is_array($cache)){
            return;
        }

        $path = subUsageCachePath();
        $dir = dirname($path);

        if(!is_dir($dir)){
            @mkdir($dir, 0755, true);
        }

        @file_put_contents(
            $path,
            json_encode($cache, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            LOCK_EX
        );
    }

    function subUsageCacheKey($link){
        $parsed = xuiParseSubLink($link);

        if($parsed){
            return strtolower($parsed['host'] . '|' . $parsed['sub_id']);
        }

        return 'raw|' . sha1(strtolower(trim((string)$link)));
    }

    function subUsageFormatBytes($bytes){
        $bytes = max(0, floatval($bytes));

        if($bytes < 1024){
            return round($bytes) . ' B';
        }

        $units = ['KB', 'MB', 'GB', 'TB'];
        $value = $bytes / 1024;

        foreach($units as $i => $unit){
            if($value < 1024 || $i === count($units) - 1){
                $decimals = $value >= 100 ? 0 : ($value >= 10 ? 1 : 2);
                return rtrim(rtrim(number_format($value, $decimals, '.', ''), '0'), '.') . ' ' . $unit;
            }

            $value /= 1024;
        }

        return round($bytes) . ' B';
    }

    function subUsageSanitizeLink($link){
        $link = trim((string)$link);

        if($link === ''){
            return '';
        }

        if(!function_exists('pnvNormalizeSubLinkValue') && is_file(__DIR__ . '/subscription_lib.php')){
            require_once __DIR__ . '/subscription_lib.php';
        }

        if(function_exists('pnvNormalizeSubLinkValue')){
            $clean = trim((string)pnvNormalizeSubLinkValue($link));

            if($clean !== '' && preg_match('#^https?://#i', $clean)){
                return $clean;
            }
        }

        if(preg_match('#(https?://[^/\s]+(?::\d+)?/sub/[A-Za-z0-9]+)#i', $link, $m)){
            return $m[1];
        }

        return $link;
    }

    function subUsageIsDisplayExpired($usage){
        if(!is_array($usage) || empty($usage['ok'])){
            return false;
        }

        // فقط پنل معتبر است؛ userinfo بعد از تمدید اغلب منقضیِ کاذب نشان می‌دهد
        if(($usage['source'] ?? '') !== 'panel'){
            return false;
        }

        $vol = is_array($usage['volume'] ?? null) ? $usage['volume'] : [];
        $time = is_array($usage['time'] ?? null) ? $usage['time'] : [];
        $volPct = !empty($vol['unlimited']) ? 100.0 : floatval($vol['remain_pct'] ?? 0);
        $timePct = !empty($time['unlimited']) ? 100.0 : floatval($time['remain_pct'] ?? 0);
        $volGone = empty($vol['unlimited']) && $volPct <= 0.05;
        $timeCounts = empty($time['unlimited']) && empty($time['estimated']);
        $timeGone = $timeCounts && $timePct <= 0.05;

        return $volGone || ($timeGone && $volPct <= 5);
    }

    function subUsageViewLooksDepleted($view){
        if(!is_array($view) || empty($view['ok'])){
            return false;
        }

        $vol = is_array($view['volume'] ?? null) ? $view['volume'] : [];
        $time = is_array($view['time'] ?? null) ? $view['time'] : [];
        $volPct = !empty($vol['unlimited']) ? 100.0 : floatval($vol['remain_pct'] ?? 0);
        $timePct = !empty($time['unlimited']) ? 100.0 : floatval($time['remain_pct'] ?? 0);

        return $volPct <= 0.05 || $timePct <= 0.05;
    }

    function subUsageCalcVersion(){
        return 3;
    }

    function subUsageCacheIsUsable($cached, $forceRefresh, $age, $ttl){
        if($forceRefresh){
            return false;
        }

        if(!is_array($cached) || empty($cached['ok'])){
            return false;
        }

        if($age < 0 || $age >= $ttl){
            return false;
        }

        if(intval($cached['calc_version'] ?? 0) < subUsageCalcVersion()){
            return false;
        }

        return ($cached['source'] ?? '') === 'panel';
    }

    function subUsagePurgeNonPanelCache(&$cache){
        $dirty = false;

        foreach(array_keys($cache) as $cacheKey){
            $row = $cache[$cacheKey];

            if(!is_array($row) || ($row['source'] ?? '') === 'panel'){
                continue;
            }

            unset($cache[$cacheKey]);
            $dirty = true;
        }

        return $dirty;
    }

    function subUsageFormatDaysLeft($secondsLeft){
        $secondsLeft = max(0, intval($secondsLeft));

        if($secondsLeft <= 0){
            return 'منقضی';
        }

        $days = (int)floor($secondsLeft / 86400);
        $hours = (int)floor(($secondsLeft % 86400) / 3600);

        if($days >= 1){
            return $days . ' روز' . ($hours > 0 ? ' و ' . $hours . ' ساعت' : '');
        }

        if($hours >= 1){
            return $hours . ' ساعت';
        }

        $mins = max(1, (int)floor($secondsLeft / 60));
        return $mins . ' دقیقه';
    }

    function subUsageFormatTimeLabel($remainSeconds, $totalSeconds){
        $remainSeconds = max(0, intval($remainSeconds));
        $totalSeconds = max(1, intval($totalSeconds));

        if($remainSeconds <= 0){
            return 'منقضی';
        }

        $remainDays = max(1, (int)ceil($remainSeconds / 86400));
        $totalDays = max($remainDays, (int)round($totalSeconds / 86400));

        return $remainDays . ' روز از ' . $totalDays . ' روز باقیمانده';
    }

    function subUsageParseDateTs($date, $time = ''){
        $date = trim((string)$date);
        $time = trim((string)$time);

        if($date === ''){
            return 0;
        }

        $combined = trim($date . ($time !== '' ? (' ' . $time) : ''));

        if(function_exists('pnvParseDateTimeToTimestamp')){
            $ts = pnvParseDateTimeToTimestamp($combined);

            if($ts > 0){
                return intval($ts);
            }
        }

        $candidates = [];

        if(preg_match('/^\d{4}\/\d{1,2}\/\d{1,2}$/', $date)){
            $candidates[] = str_replace('/', '-', $date) . ($time !== '' ? (' ' . $time) : ' 00:00:00');
        }

        $candidates[] = $date . ($time !== '' ? (' ' . $time) : '');

        foreach($candidates as $c){
            $ts = strtotime($c);

            if($ts !== false && $ts > 0){
                return intval($ts);
            }
        }

        return 0;
    }

    function subUsageInferBillingPeriodSeconds($remainSeconds){
        $remainSeconds = max(0, intval($remainSeconds));
        $remainDays = max(1, (int)ceil($remainSeconds / 86400));
        $candidates = [7, 15, 30, 60, 90, 180, 365];

        foreach($candidates as $days){
            if($days >= $remainDays){
                return $days * 86400;
            }
        }

        return (int)ceil($remainDays / 365) * 365 * 86400;
    }

    function subUsageNormalizeTimeTotalSeconds($totalSeconds, $remainSeconds){
        $totalSeconds = max(0, intval($totalSeconds));
        $remainSeconds = max(0, intval($remainSeconds));

        if($totalSeconds <= 0){
            return 0;
        }

        if($totalSeconds < $remainSeconds){
            return 0;
        }

        if($totalSeconds > (3 * 365 * 86400)){
            return 0;
        }

        return $totalSeconds;
    }

    function subUsageResolveTimeTotalSeconds($expireTs, $remainSeconds, $planDays, $startTs){
        $expireTs = max(0, intval($expireTs));
        $remainSeconds = max(0, intval($remainSeconds));
        $planDays = max(0, intval($planDays));
        $startTs = max(0, intval($startTs));
        $totalSeconds = 0;

        if($startTs > 0 && $expireTs > $startTs){
            $totalSeconds = subUsageNormalizeTimeTotalSeconds($expireTs - $startTs, $remainSeconds);
        }

        if($totalSeconds <= 0 && $planDays > 0){
            $totalSeconds = subUsageNormalizeTimeTotalSeconds($planDays * 86400, $remainSeconds);
        }

        if($totalSeconds <= 0 && $remainSeconds > 0){
            $totalSeconds = subUsageNormalizeTimeTotalSeconds(
                subUsageInferBillingPeriodSeconds($remainSeconds),
                $remainSeconds
            );
        }

        return max(1, $totalSeconds > 0 ? $totalSeconds : subUsageInferBillingPeriodSeconds(max($remainSeconds, 86400)));
    }

    function subUsageBuildView($used, $total, $expiryMs, $meta = []){
        $used = max(0, floatval($used));
        $total = max(0, floatval($total));
        $nowMs = (int)round(microtime(true) * 1000);
        $planDays = max(0, intval($meta['plan_days'] ?? 0));
        $startTs = max(0, intval($meta['start_ts'] ?? 0));

        $volumeUnlimited = ($total <= 0);
        $remainBytes = $volumeUnlimited ? 0 : max(0, $total - $used);
        $volumePct = 100.0;

        if(!$volumeUnlimited && $total > 0){
            $volumePct = max(0, min(100, ($remainBytes / $total) * 100));
        }

        $timeUnlimited = true;
        $timePct = 100.0;
        $remainSeconds = 0;
        $timeEstimated = false;
        $expireTs = 0;
        $totalSeconds = 0;
        $trustExpiry = !empty($meta['trust_expiry']);

        if($expiryMs > 0){
            $timeUnlimited = false;
            $expireTs = (int)floor($expiryMs / 1000);
            $remainSeconds = max(0, $expireTs - time());
            $totalSeconds = subUsageResolveTimeTotalSeconds(
                $expireTs,
                $remainSeconds,
                $planDays,
                $startTs
            );
            $timePct = max(0, min(100, ($remainSeconds / max(1, $totalSeconds)) * 100));
        }
        elseif(!$trustExpiry && $planDays > 0 && $startTs > 0){
            // فقط وقتی پنل/userinfo در دسترس نبود: تخمین از تاریخ فاکتور
            $timeUnlimited = false;
            $timeEstimated = true;
            $expireTs = $startTs + ($planDays * 86400);
            $remainSeconds = max(0, $expireTs - time());
            $totalSeconds = $planDays * 86400;
            $timePct = max(0, min(100, ($remainSeconds / max(1, $totalSeconds)) * 100));
        }

        return [
            'ok' => true,
            'volume' => [
                'unlimited' => $volumeUnlimited,
                'used_bytes' => (int)$used,
                'total_bytes' => (int)$total,
                'remain_bytes' => (int)$remainBytes,
                'remain_pct' => round($volumePct, 1),
                'label' => $volumeUnlimited
                    ? 'حجم نامحدود'
                    : (subUsageFormatBytes($remainBytes) . ' از ' . subUsageFormatBytes($total) . ' مانده'),
            ],
            'time' => [
                'unlimited' => $timeUnlimited,
                'estimated' => $timeEstimated,
                'expire_ts' => $expireTs,
                'remain_seconds' => $remainSeconds,
                'total_seconds' => $totalSeconds,
                'remain_pct' => round($timePct, 1),
                'label' => $timeUnlimited
                    ? 'زمان نامحدود'
                    : subUsageFormatTimeLabel($remainSeconds, $totalSeconds),
            ],
            'updated_at' => time(),
            'calc_version' => subUsageCalcVersion(),
        ];
    }

    function subUsageFromClient($client, $meta = []){
        return subUsageBuildView(
            xuiClientUsedBytes($client),
            xuiClientTotalBytes($client),
            xuiClientExpiryMs($client),
            $meta
        );
    }

    function subUsageParseUserinfoHeader($headerLine){
        $headerLine = trim((string)$headerLine);

        if($headerLine === ''){
            return null;
        }

        // upload=; download=; total=; expire=
        $parts = preg_split('/\s*;\s*/', $headerLine) ?: [];
        $map = [];

        foreach($parts as $part){
            if(strpos($part, '=') === false){
                continue;
            }

            [$k, $v] = array_map('trim', explode('=', $part, 2));
            $map[strtolower($k)] = $v;
        }

        if(!isset($map['total']) && !isset($map['download']) && !isset($map['upload'])){
            return null;
        }

        $upload = floatval($map['upload'] ?? 0);
        $download = floatval($map['download'] ?? 0);
        $total = floatval($map['total'] ?? 0);
        $expire = intval($map['expire'] ?? 0);
        $expiryMs = $expire > 0 ? ($expire * 1000) : 0;

        return [
            'used' => $upload + $download,
            'total' => $total,
            'expiry_ms' => $expiryMs,
        ];
    }

    function subUsageFetchFromSubUserinfo($link){
        $link = trim((string)$link);

        if($link === '' || !function_exists('curl_init')){
            return null;
        }

        $modes = [
            ['nobody' => true],
            ['nobody' => false, 'range' => '0-0'],
            ['nobody' => false],
        ];

        foreach($modes as $mode){
            $curl = curl_init($link);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_HEADER, true);
            curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 5);
            curl_setopt($curl, CURLOPT_TIMEOUT, 12);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);

            if(!empty($mode['nobody'])){
                curl_setopt($curl, CURLOPT_NOBODY, true);
            }

            if(!empty($mode['range'])){
                curl_setopt($curl, CURLOPT_RANGE, $mode['range']);
            }

            $raw = curl_exec($curl);
            curl_close($curl);

            if($raw !== false && $raw !== '' && preg_match('/^subscription-userinfo:\s*(.+)$/im', $raw, $m)){
                return subUsageParseUserinfoHeader($m[1]);
            }
        }

        return null;
    }

    function subUsageFetchFromPanel($link, $cachedHint = []){
        $parsed = xuiParseSubLink($link);

        if(!$parsed){
            return null;
        }

        $config = xuiLoadConfig();
        $server = xuiFindServerByHost($parsed['host'], $config);

        if(!$server){
            return null;
        }

        $email = trim((string)($cachedHint['email'] ?? ''));

        if($email === '' && is_file(__DIR__ . '/plan_ui_lib.php')){
            require_once __DIR__ . '/plan_ui_lib.php';

            if(function_exists('pnvFetchSubPanelEmail')){
                $email = trim((string)pnvFetchSubPanelEmail($link));
            }
        }

        if($email === '' && function_exists('xuiFetchSubEmail')){
            $email = trim((string)xuiFetchSubEmail($link));
        }

        if($email !== ''){
            $full = xuiApiRequest(
                $server,
                'GET',
                '/panel/api/clients/get/' . rawurlencode($email)
            );

            if(!empty($full['success']) && is_array($full['obj'] ?? null)){
                $client = xuiNormalizeClientRecord($full['obj']['client'] ?? $full['obj']);

                if($client){
                    foreach(['up', 'down', 'total', 'totalGB', 'expiryTime'] as $field){
                        if((!isset($client[$field]) || $client[$field] === '' || $client[$field] === null)
                            && isset($full['obj'][$field])){
                            $client[$field] = $full['obj'][$field];
                        }
                    }

                    $client = subUsageFinalizePanelClient($server, $client, $parsed['sub_id'], $link);

                    return [
                        'client' => $client,
                        'email' => $client['email'] ?? $email,
                        'server_id' => $server['id'] ?? '',
                    ];
                }
            }
        }

        // جستجوی کامل کلاینت از پنل (subId، inbound، UUID، email)
        $client = xuiFindClientBySubId($server, $parsed['sub_id'], $link);

        if($client){
            $client = xuiHydrateClientByEmail($server, $client);
            $client = subUsageFinalizePanelClient($server, $client, $parsed['sub_id'], $link);
            return [
                'client' => $client,
                'email' => $client['email'] ?? '',
                'server_id' => $server['id'] ?? '',
            ];
        }

        return null;
    }

    function subUsageFinalizePanelClient($server, $client, $subId, $link){
        if(!is_array($client)){
            return $client;
        }

        if(function_exists('xuiEnrichClientTrafficFromInbounds')){
            $client = xuiEnrichClientTrafficFromInbounds($server, $client, (string)$subId, (string)$link);
        }

        if(xuiClientUsedBytes($client) <= 0){
            $userinfo = subUsageFetchFromSubUserinfo($link);

            if(is_array($userinfo)){
                $uiUsed = max(0, floatval($userinfo['used'] ?? 0));
                $uiTotal = max(0, floatval($userinfo['total'] ?? 0));

                if($uiUsed > 0){
                    $client['used'] = $uiUsed;

                    if($uiTotal > 0){
                        $panelTotal = xuiClientTotalBytes($client);

                        if($panelTotal <= 0 || $uiTotal > $panelTotal){
                            $client['total'] = $uiTotal;
                        }
                    }
                }
            }
        }

        return $client;
    }

    function subUsageEstimateFromMeta($meta = []){
        $planText = trim((string)($meta['plan_text'] ?? $meta['plan'] ?? ''));
        $planGb = function_exists('xuiParsePlanGb') ? xuiParsePlanGb($planText) : 0;
        $planDays = function_exists('xuiParsePlanDays') ? xuiParsePlanDays($planText) : 0;
        $totalBytes = $planGb > 0 ? ($planGb * 1024 * 1024 * 1024) : 0;

        $view = subUsageBuildView(0, $totalBytes, 0, [
            'plan_days' => $planDays,
            'start_ts' => max(0, intval($meta['start_ts'] ?? 0)),
        ]);
        $view['source'] = 'estimate';
        $view['estimated'] = true;
        $view['ok'] = true;

        if($totalBytes <= 0){
            $view['volume']['unlimited'] = true;
            $view['volume']['label'] = 'حجم نامحدود';
            $view['volume']['remain_pct'] = 100;
        }
        else{
            $view['volume']['estimated'] = true;
            $view['volume']['label'] = subUsageFormatBytes($totalBytes) . ' پلن (مصرف دقیق در حال دریافت…)';
        }

        if($planDays <= 0 && intval($meta['start_ts'] ?? 0) <= 0){
            $view['time']['unlimited'] = true;
            $view['time']['label'] = 'زمان نامحدود';
            $view['time']['remain_pct'] = 100;
        }

        return $view;
    }

    function subUsageRefreshOne($link, $meta = [], $cacheEntry = null, $preferPanel = false){
        $link = subUsageSanitizeLink($link);
        $hint = is_array($cacheEntry) ? $cacheEntry : [];
        $meta['plan_text'] = trim((string)($meta['plan_text'] ?? $meta['plan'] ?? ''));

        // 1) پنل API — منبع اصلی؛ userinfo بعد از تمدید اغلب قدیمی می‌ماند
        $panel = subUsageFetchFromPanel($link, $hint);

        if(is_array($panel) && !empty($panel['client'])){
            $meta['trust_expiry'] = true;
            $view = subUsageFromClient($panel['client'], $meta);
            $view['source'] = 'panel';
            $view['email'] = $panel['email'] ?? '';
            $view['server_id'] = $panel['server_id'] ?? '';
            return $view;
        }

        // 2) هدر subscription-userinfo (fallback)
        $userinfo = subUsageFetchFromSubUserinfo($link);

        if(is_array($userinfo)){
            $meta['trust_expiry'] = true;
            $view = subUsageBuildView(
                $userinfo['used'],
                $userinfo['total'],
                $userinfo['expiry_ms'],
                $meta
            );
            $view['source'] = 'userinfo';
            $view['email'] = $hint['email'] ?? '';
            $view['server_id'] = $hint['server_id'] ?? '';

            // userinfo قدیمی بعد از تمدید → به تخمین پلن برو
            if(!subUsageViewLooksDepleted($view)){
                return $view;
            }
        }

        // 3) تخمین از اطلاعات فاکتور وقتی پنل در دسترس نیست
        $estimate = subUsageEstimateFromMeta($meta);

        if(!empty($estimate['ok'])){
            $estimate['email'] = $hint['email'] ?? (is_array($panel) ? ($panel['email'] ?? '') : '');
            $estimate['server_id'] = $hint['server_id'] ?? (is_array($panel) ? ($panel['server_id'] ?? '') : '');
            return $estimate;
        }

        return [
            'ok' => false,
            'error' => 'خواندن مصرف از پنل ممکن نشد',
            'updated_at' => time(),
            'email' => $hint['email'] ?? '',
            'server_id' => $hint['server_id'] ?? '',
        ];
    }

    /**
     * @param array $items [ ['link'=>..., 'plan'=>..., 'date'=>..., 'time'=>...], ... ]
     * @param int $maxFresh حداکثر تعداد رفرش زنده در همین درخواست
     */
    function subUsageGetForItems($items, $maxFresh = 4, $forceRefresh = false){
        $cache = subUsageLoadCache();
        $ttl = subUsageTtlSeconds();
        $now = time();
        $freshUsed = 0;
        $out = [];
        $cacheDirty = false;

        if(subUsagePurgeNonPanelCache($cache)){
            $cacheDirty = true;
        }

        foreach($items as $item){
            if(!is_array($item)){
                continue;
            }

            $link = trim((string)($item['link'] ?? ''));
            $link = subUsageSanitizeLink($link);

            if($link === ''){
                continue;
            }

            $key = subUsageCacheKey($link);
            $planText = trim((string)($item['plan'] ?? ''));
            $meta = [
                'plan' => $planText,
                'plan_text' => $planText,
                'plan_days' => xuiParsePlanDays($planText),
                'start_ts' => max(
                    0,
                    intval($item['created_ts'] ?? 0),
                    subUsageParseDateTs($item['date'] ?? '', $item['time'] ?? '')
                ),
            ];

            $cached = $cache[$key] ?? null;
            $age = is_array($cached) ? ($now - intval($cached['updated_at'] ?? 0)) : PHP_INT_MAX;
            $isFresh = subUsageCacheIsUsable($cached, $forceRefresh, $age, $ttl);

            if($isFresh){
                $view = $cached;
                $view['cached'] = true;
                $view['age'] = $age;
                $out[$key] = $view;
                continue;
            }

            if($freshUsed >= $maxFresh){
                if(is_array($cached) && subUsageCacheIsUsable($cached, true, $age, $ttl)){
                    $view = $cached;
                    $view['cached'] = true;
                    $view['stale'] = true;
                    $view['age'] = $age;
                    $out[$key] = $view;
                }
                else{
                    $out[$key] = [
                        'ok' => false,
                        'pending' => true,
                        'error' => 'در صف بروزرسانی',
                        'updated_at' => 0,
                    ];
                }
                continue;
            }

            $freshUsed++;
            $view = subUsageRefreshOne(
                $link,
                $meta,
                is_array($cached) ? $cached : [],
                $forceRefresh
            );
            $view['cached'] = false;
            $view['link'] = $link;
            $view['key'] = $key;

            $cache[$key] = $view;
            $cacheDirty = true;
            $out[$key] = $view;
        }

        if($cacheDirty){
            // پاکسازی ورودی‌های خیلی قدیمی (> ۷ روز)
            foreach($cache as $k => $row){
                if(!is_array($row) || ($now - intval($row['updated_at'] ?? 0)) > 604800){
                    unset($cache[$k]);
                }
            }

            subUsageSaveCache($cache);
        }

        return [
            'ok' => true,
            'ttl' => $ttl,
            'refreshed' => $freshUsed,
            'items' => $out,
        ];
    }
}
