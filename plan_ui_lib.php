<?php

if(!function_exists('pnvFormatPlanPrice')){

    function pnvFormatPlanPrice($price){
        $price = intval($price);

        if($price < 1000){
            return number_format($price) . ' هزار تومان';
        }

        $million = $price / 1000;
        $million = rtrim(rtrim(number_format($million, 3), '0'), '.');

        return $million . ' میلیون تومان';
    }

    function pnvFormatPlanPriceShort($price){
        $price = intval($price);

        if($price < 1000){
            return number_format($price) . ' تومن';
        }

        $million = $price / 1000;
        $million = rtrim(rtrim(number_format($million, 3), '0'), '.');

        return $million . ' میلیون';
    }

    function pnvPlanIsUnlimited($plan){
        $days = trim((string)($plan['days'] ?? ''));

        if($days === '' || $days === 'نامحدود' || strcasecmp($days, 'unlimited') === 0){
            return true;
        }

        return intval($days) <= 0;
    }

    function pnvPlanDaysLabel($plan){
        if(pnvPlanIsUnlimited($plan)){
            return 'نامحدود زمانی';
        }

        $days = trim((string)($plan['days'] ?? ''));

        if($days === ''){
            return '—';
        }

        if(preg_match('/^\d+$/', $days)){
            $n = intval($days);

            if($n <= 0){
                return 'نامحدود زمانی';
            }

            // Exact month multiples (30-day months) → e.g. «۱ ماهه»
            if($n >= 30 && ($n % 30) === 0){
                $months = intdiv($n, 30);
                return $months . ' ماهه';
            }

            return $n . ' روزه';
        }

        return $days;
    }

    function pnvPlanOptionValue($plan){
        $name = trim((string)($plan['name'] ?? ''));
        $priceText = pnvFormatPlanPrice($plan['price'] ?? 0);
        $value = $name . ' - ' . $priceText;

        if(!pnvPlanIsUnlimited($plan)){
            $daysLabel = pnvPlanDaysLabel($plan);

            if($daysLabel !== '' && $daysLabel !== '—' && $daysLabel !== 'نامحدود زمانی'){
                $value .= ' - ' . $daysLabel;
            }
        }

        return $value;
    }

    function pnvFindPlanByValue($planValue, $plans){
        $planValue = trim((string)$planValue);

        if($planValue === '' || !is_array($plans)){
            return null;
        }

        foreach($plans as $plan){
            if(!is_array($plan)){
                continue;
            }

            if(pnvPlanOptionValue($plan) === $planValue){
                return $plan;
            }
        }

        foreach($plans as $plan){
            if(!is_array($plan)){
                continue;
            }

            $legacy = trim((string)($plan['name'] ?? ''));

            if($legacy === ''){
                continue;
            }

            $priceText = pnvFormatPlanPrice($plan['price'] ?? 0);
            $legacyValue = $legacy . ' - ' . $priceText;

            if($legacyValue === $planValue || strpos($planValue, $legacyValue) === 0){
                return $plan;
            }
        }

        return null;
    }

    function pnvPlansForStepUi($plans){
        $out = [];

        if(!is_array($plans)){
            return $out;
        }

        foreach($plans as $plan){
            if(!is_array($plan)){
                continue;
            }

            $name = trim((string)($plan['name'] ?? ''));

            if($name === ''){
                continue;
            }

            $unlimited = pnvPlanIsUnlimited($plan);
            $value = pnvPlanOptionValue($plan);

            $out[] = [
                'name' => $name,
                'price' => intval($plan['price'] ?? 0),
                'price_text' => pnvFormatPlanPrice($plan['price'] ?? 0),
                'price_short' => pnvFormatPlanPriceShort($plan['price'] ?? 0),
                'days' => trim((string)($plan['days'] ?? '')),
                'days_label' => pnvPlanDaysLabel($plan),
                'category' => $unlimited ? 'unlimited' : 'limited',
                'value' => $value
            ];
        }

        return $out;
    }

    function pnvFindSubLinkFromCsv($username, $subLink){
        $username = trim((string)$username);
        $subLink = trim((string)$subLink);
        $file = __DIR__ . '/invoices/payments.csv';

        if($username === '' || $subLink === '' || !file_exists($file)){
            return $subLink;
        }

        if(preg_match('#^https?://#i', $subLink)){
            $clean = pnvNormalizeSubLinkValue($subLink);
            return $clean !== '' ? $clean : $subLink;
        }

        $needle = strtolower($subLink);
        $found = '';

        $handle = fopen($file, 'r');

        while(($row = fgetcsv($handle)) !== false){
            if(($row[0] ?? '') !== $username){
                continue;
            }

            if(trim((string)($row[6] ?? '')) !== 'تایید شد'){
                continue;
            }

            $type = trim((string)($row[9] ?? ''));
            $buyLink = trim((string)($row[7] ?? ''));
            $renewLink = trim((string)($row[1] ?? ''));

            if($type === 'خرید' && $buyLink !== ''){
                $hay = strtolower($buyLink);

                if($hay === $needle || strpos($hay, $needle) !== false || strpos($needle, $hay) !== false){
                    $found = $buyLink;
                    break;
                }
            }

            if($type === 'تمدید' && $renewLink !== ''){
                $hay = strtolower($renewLink);

                if($hay === $needle || strpos($hay, $needle) !== false || strpos($needle, $hay) !== false){
                    $found = $renewLink;
                    break;
                }
            }
        }

        fclose($handle);

        return $found !== '' ? (pnvNormalizeSubLinkValue($found) ?: $found) : $subLink;
    }

    function pnvResolveSubTimeCategory($link, $planText = '', $username = ''){
        $link = trim((string)$link);
        $username = trim((string)$username);

        if(
            $link !== ''
            && !preg_match('#^https?://#i', $link)
            && $username !== ''
            && function_exists('pnvFindSubLinkFromCsv')
        ){
            $resolved = pnvFindSubLinkFromCsv($username, $link);

            if($resolved !== ''){
                $link = $resolved;
            }
        }

        if($link !== '' && preg_match('#^https?://#i', $link) && function_exists('xuiFetchSubUserinfoExpire')){
            $expire = xuiFetchSubUserinfoExpire($link);

            if($expire !== null){
                return $expire > 0 ? 'limited' : 'unlimited';
            }
        }

        $planDays = function_exists('xuiParsePlanDays') ? xuiParsePlanDays($planText) : 0;

        if($planDays > 0){
            return 'limited';
        }

        return 'unlimited';
    }

    function pnvExtractSubIdFromLink($link){
        $link = trim((string)$link);

        if($link === ''){
            return '';
        }

        if(preg_match('/\/sub\/([^\/\?#]+)/i', $link, $m)){
            return strtolower($m[1]);
        }

        if(preg_match('/^[A-Za-z0-9]{8,32}$/', $link)){
            return strtolower($link);
        }

        return '';
    }

    function pnvSubLinksMatch($left, $right){
        $left = strtolower(rtrim(trim((string)$left), '/'));
        $right = strtolower(rtrim(trim((string)$right), '/'));

        if($left === '' || $right === ''){
            return false;
        }

        if($left === $right){
            return true;
        }

        if(strpos($left, $right) !== false || strpos($right, $left) !== false){
            return true;
        }

        $leftId = pnvExtractSubIdFromLink($left);
        $rightId = pnvExtractSubIdFromLink($right);

        return $leftId !== '' && $rightId !== '' && $leftId === $rightId;
    }

    function pnvNameLooksLikeSubId($name, $link = ''){
        $name = trim((string)$name);

        if($name === ''){
            return true;
        }

        if(function_exists('pnvIsValidSubLink') && pnvIsValidSubLink($name)){
            return true;
        }

        if(!preg_match('/^[A-Za-z0-9._-]{8,40}$/', $name)){
            return false;
        }

        $subId = pnvExtractSubIdFromLink($link);

        if($subId !== '' && strcasecmp($name, $subId) === 0){
            return true;
        }

        return !preg_match('/[._-]/', $name) && preg_match('/^[A-Za-z0-9]{10,32}$/', $name);
    }

    function pnvSubNameNeedsPanelResolve($name, $link = ''){
        $name = trim((string)$name);

        if($name === '' || pnvNameLooksLikeSubId($name, $link)){
            return true;
        }

        if($name === 'اشتراک'){
            return true;
        }

        return (bool)preg_match('/^اشتراک\s+\d+$/u', $name);
    }

    function pnvFindSubCachedClientEmail($link){
        if(!function_exists('subUsageLoadCache') || !function_exists('subUsageCacheKey')){
            if(is_file(__DIR__ . '/sub_usage_lib.php')){
                require_once __DIR__ . '/sub_usage_lib.php';
            }
        }

        if(!function_exists('subUsageLoadCache') || !function_exists('subUsageCacheKey')){
            return '';
        }

        $cache = subUsageLoadCache();
        $cached = $cache[subUsageCacheKey($link)] ?? null;

        return trim((string)(is_array($cached) ? ($cached['email'] ?? '') : ''));
    }

    function pnvPanelEmailForDisplay($email, $link = ''){
        $email = trim((string)$email);

        if($email === ''){
            return '';
        }

        $subId = pnvExtractSubIdFromLink($link);

        if($subId !== '' && strcasecmp($email, $subId) === 0){
            return '';
        }

        return $email;
    }

    function pnvSaveSubCachedClientEmail($link, $email){
        $email = trim((string)$email);
        $link = trim((string)$link);

        if($email === '' || $link === ''){
            return;
        }

        if(!function_exists('subUsageLoadCache') || !function_exists('subUsageCacheKey') || !function_exists('subUsageSaveCache')){
            if(is_file(__DIR__ . '/sub_usage_lib.php')){
                require_once __DIR__ . '/sub_usage_lib.php';
            }
        }

        if(!function_exists('subUsageLoadCache') || !function_exists('subUsageCacheKey') || !function_exists('subUsageSaveCache')){
            return;
        }

        $key = subUsageCacheKey($link);
        $cache = subUsageLoadCache();

        if(!isset($cache[$key]) || !is_array($cache[$key])){
            $cache[$key] = ['ok' => true, 'updated_at' => time()];
        }

        $cache[$key]['email'] = $email;
        $cache[$key]['updated_at'] = time();
        subUsageSaveCache($cache);
    }

    function pnvScanInboundForSubEmail($server, $subId){
        if(!function_exists('xuiApiRequest')){
            if(is_file(__DIR__ . '/xui_lib.php')){
                require_once __DIR__ . '/xui_lib.php';
            }
        }

        $subId = trim((string)$subId);
        $inboundId = intval($server['inbound_id'] ?? 1);

        if($subId === '' || $inboundId <= 0 || !function_exists('xuiApiRequest')){
            return '';
        }

        $one = xuiApiRequest($server, 'GET', '/panel/api/inbounds/get/' . $inboundId);

        if(empty($one['success']) || !function_exists('xuiParseInboundClients')){
            return '';
        }

        $inbound = $one['obj'] ?? null;

        if(!is_array($inbound)){
            return '';
        }

        foreach(xuiParseInboundClients($inbound) as $client){
            if(!is_array($client)){
                continue;
            }

            if(function_exists('xuiClientMatchesSubId') && xuiClientMatchesSubId($client, $subId)){
                $email = trim((string)($client['email'] ?? ''));

                if($email !== ''){
                    return $email;
                }
            }

            foreach($client as $value){
                if(is_string($value) && strcasecmp(trim($value), $subId) === 0){
                    $email = trim((string)($client['email'] ?? ''));

                    if($email !== ''){
                        return $email;
                    }
                }
            }
        }

        return '';
    }

    function pnvFindSubClientEmailFromServer($server, $subId, $link = ''){
        if(!function_exists('xuiApiRequest')){
            if(is_file(__DIR__ . '/xui_lib.php')){
                require_once __DIR__ . '/xui_lib.php';
            }
        }

        $subId = trim((string)$subId);

        if($subId === '' || !is_array($server) || !function_exists('xuiApiRequest')){
            return '';
        }

        $email = pnvScanInboundForSubEmail($server, $subId);

        if($email !== ''){
            return $email;
        }

        if($link !== '' && function_exists('xuiFetchSubEmail')){
            $email = trim((string)xuiFetchSubEmail($link));

            if($email !== ''){
                return $email;
            }
        }

        $result = xuiApiRequest(
            $server,
            'GET',
            '/panel/api/clients/list/paged?page=1&pageSize=10&search=' . rawurlencode($subId)
            . '&filter=&protocol=&sort=email&order=ascend'
        );

        if(!empty($result['success']) && function_exists('xuiNormalizeClientRecord') && function_exists('xuiClientMatchesSubId')){
            $obj = $result['obj'] ?? [];
            $list = $obj['list'] ?? $obj['clients'] ?? [];

            if(is_array($list)){
                foreach($list as $item){
                    $client = xuiNormalizeClientRecord($item['client'] ?? $item);

                    if($client && xuiClientMatchesSubId($client, $subId)){
                        $email = trim((string)($client['email'] ?? ''));

                        if($email !== ''){
                            return $email;
                        }
                    }
                }

                if(count($list) === 1){
                    $client = xuiNormalizeClientRecord($list[0]['client'] ?? $list[0]);
                    $email = trim((string)(is_array($client) ? ($client['email'] ?? '') : ''));

                    if($email !== ''){
                        return $email;
                    }
                }
            }
        }

        $subLinks = xuiApiRequest($server, 'GET', '/panel/api/clients/subLinks/' . rawurlencode($subId));

        if(!empty($subLinks['success']) && function_exists('xuiExtractEmailFromSubUrls')){
            $obj = $subLinks['obj'] ?? null;
            $urls = [];

            if(is_array($obj)){
                if(isset($obj[0]) || count($obj) === 0){
                    $urls = $obj;
                }
                elseif(isset($obj['links']) && is_array($obj['links'])){
                    $urls = $obj['links'];
                }
            }

            $email = trim((string)xuiExtractEmailFromSubUrls($urls));

            if($email !== ''){
                return $email;
            }
        }

        if(function_exists('xuiFetchInbounds') && function_exists('xuiParseInboundClients') && function_exists('xuiClientMatchesSubId')){
            foreach(xuiFetchInbounds($server) as $inbound){
                foreach(xuiParseInboundClients($inbound) as $client){
                    if(xuiClientMatchesSubId($client, $subId)){
                        $email = trim((string)($client['email'] ?? ''));

                        if($email !== ''){
                            return $email;
                        }
                    }
                }
            }
        }

        if(function_exists('xuiFindClientBySubId')){
            $client = xuiFindClientBySubId($server, $subId, $link);
            $email = trim((string)(is_array($client) ? ($client['email'] ?? '') : ''));

            if($email !== ''){
                return $email;
            }
        }

        return '';
    }

    function pnvFindSubNameFromCsv($username, $subLink){
        $username = trim((string)$username);
        $subLink = trim((string)$subLink);
        $file = __DIR__ . '/invoices/payments.csv';

        if($username === '' || $subLink === '' || !file_exists($file)){
            return '';
        }

        if(function_exists('pnvFindSubLinkFromCsv')){
            $resolved = pnvFindSubLinkFromCsv($username, $subLink);

            if($resolved !== ''){
                $subLink = $resolved;
            }
        }

        $target = strtolower(rtrim($subLink, '/'));
        $targetId = pnvExtractSubIdFromLink($target);
        $bestName = '';
        $bestTs = 0;

        $handle = fopen($file, 'r');

        while(($row = fgetcsv($handle)) !== false){
            if(($row[0] ?? '') !== $username){
                continue;
            }

            if(trim((string)($row[6] ?? '')) !== 'تایید شد'){
                continue;
            }

            $type = trim((string)($row[9] ?? ''));
            $col1 = trim((string)($row[1] ?? ''));
            $buyLink = strtolower(rtrim(trim((string)($row[7] ?? '')), '/'));
            $renewLink = strtolower(rtrim(trim((string)($row[1] ?? '')), '/'));
            $rowTs = intval($row[8] ?? 0);

            if($type === 'خرید' && $buyLink !== '' && function_exists('pnvIsValidSubLink') && pnvIsValidSubLink($buyLink)){
                $matches = ($buyLink === $target)
                    || ($targetId !== '' && pnvExtractSubIdFromLink($buyLink) === $targetId)
                    || strpos($buyLink, $target) !== false
                    || ($targetId !== '' && strpos($buyLink, $targetId) !== false);

                if(!$matches){
                    continue;
                }

                if($col1 === '' || pnvNameLooksLikeSubId($col1, $buyLink)){
                    continue;
                }

                if($rowTs >= $bestTs){
                    $bestTs = $rowTs;
                    $bestName = $col1;
                }

                continue;
            }

            if($type === 'تمدید' && $renewLink !== '' && function_exists('pnvIsValidSubLink') && pnvIsValidSubLink($renewLink)){
                $matches = ($renewLink === $target)
                    || ($targetId !== '' && pnvExtractSubIdFromLink($renewLink) === $targetId)
                    || strpos($renewLink, $target) !== false
                    || ($targetId !== '' && strpos($renewLink, $targetId) !== false);

                if(!$matches){
                    continue;
                }

                if($col1 !== '' && !pnvNameLooksLikeSubId($col1, $renewLink) && !pnvIsValidSubLink($col1)){
                    if($rowTs >= $bestTs){
                        $bestTs = $rowTs;
                        $bestName = $col1;
                    }
                }
            }
        }

        fclose($handle);

        return $bestName;
    }

    function pnvSubDisplayNameFromClientEmail($email, $link = ''){
        $email = trim((string)$email);

        if($email === '' || pnvNameLooksLikeSubId($email, $link)){
            return '';
        }

        return $email;
    }

    function pnvFindSubClientEmail($link){
        $link = trim((string)$link);

        if($link === ''){
            return '';
        }

        $cachedEmail = pnvFindSubCachedClientEmail($link);

        if($cachedEmail !== ''){
            return $cachedEmail;
        }

        static $liveLookups = 0;

        if($liveLookups >= 4){
            return '';
        }

        if(!function_exists('xuiParseSubLink') && is_file(__DIR__ . '/xui_lib.php')){
            require_once __DIR__ . '/xui_lib.php';
        }

        if(function_exists('xuiParseSubLink')){
            $parsed = xuiParseSubLink($link);

            if(is_array($parsed) && function_exists('xuiLoadConfig') && function_exists('xuiFindServerByHost')){
                $config = xuiLoadConfig();
                $server = xuiFindServerByHost($parsed['host'], $config);

                if($server && function_exists('xuiServerHasAuth') && xuiServerHasAuth($server)){
                    $liveLookups++;
                    $email = pnvFindSubClientEmailFromServer($server, $parsed['sub_id'], $link);

                    if($email !== ''){
                        return $email;
                    }
                }
            }
        }

        if(function_exists('xuiFetchSubEmail')){
            $liveLookups++;
            $email = trim((string)xuiFetchSubEmail($link));

            if($email !== ''){
                return $email;
            }
        }

        return '';
    }

    function pnvExtractConfigNameFromClientEmail($email){
        $email = trim((string)$email);

        if($email === ''){
            return '';
        }

        if(preg_match('/^(.+)_\d{4}$/u', $email, $m)){
            $name = trim((string)$m[1]);

            if($name !== '' && !pnvNameLooksLikeSubId($name)){
                return $name;
            }
        }

        if(!pnvNameLooksLikeSubId($email)){
            return $email;
        }

        return '';
    }

    function pnvFetchSubPanelEmail($link){
        $link = trim((string)$link);

        if($link === ''){
            return '';
        }

        $cachedEmail = pnvFindSubCachedClientEmail($link);

        if($cachedEmail !== ''){
            return $cachedEmail;
        }

        if(!function_exists('xuiParseSubLink') && is_file(__DIR__ . '/xui_lib.php')){
            require_once __DIR__ . '/xui_lib.php';
        }

        if(!function_exists('xuiParseSubLink') || !function_exists('xuiLoadConfig') || !function_exists('xuiFindServerByHost')){
            return '';
        }

        $parsed = xuiParseSubLink($link);

        if(!is_array($parsed)){
            return '';
        }

        $server = xuiFindServerByHost($parsed['host'], xuiLoadConfig());

        if(!$server || !function_exists('xuiServerHasAuth') || !xuiServerHasAuth($server)){
            return '';
        }

        $email = pnvFindSubClientEmailFromServer($server, $parsed['sub_id'], $link);

        if($email === '' && function_exists('xuiFetchSubEmail')){
            $email = trim((string)xuiFetchSubEmail($link));
        }

        if($email !== ''){
            pnvSaveSubCachedClientEmail($link, $email);
        }

        return $email;
    }

    /**
     * فقط برای نمایش در «اشتراک من» و dropdown تمدید — روند پرداخت/تمدید را تغییر نمی‌دهد.
     */
    function pnvEnsureSubDisplayName($username, $link, $currentName = '', $hintEmail = ''){
        $currentName = trim((string)$currentName);
        $link = trim((string)$link);
        $hintEmail = trim((string)$hintEmail);

        if($currentName !== '' && !pnvSubNameNeedsPanelResolve($currentName, $link)){
            return $currentName;
        }

        $fromCsv = pnvFindSubNameFromCsv($username, $link);

        if($fromCsv !== ''){
            return $fromCsv;
        }

        $emailCandidates = [
            $hintEmail,
            pnvFindSubCachedClientEmail($link),
            pnvFetchSubPanelEmail($link),
        ];

        if(function_exists('xuiFetchSubEmail')){
            if(!function_exists('xuiParseSubLink') && is_file(__DIR__ . '/xui_lib.php')){
                require_once __DIR__ . '/xui_lib.php';
            }

            $emailCandidates[] = trim((string)xuiFetchSubEmail($link));
        }

        foreach($emailCandidates as $candidate){
            $display = pnvPanelEmailForDisplay($candidate, $link);

            if($display !== ''){
                pnvSaveSubCachedClientEmail($link, $candidate);
                return $display;
            }
        }

        if($currentName !== '' && !pnvSubNameNeedsPanelResolve($currentName, $link)){
            return $currentName;
        }

        return 'اشتراک';
    }

    function pnvFindSubNameFromPanel($link){
        return pnvSubDisplayNameFromClientEmail(pnvFindSubClientEmail($link), $link);
    }

    function pnvResolveSubDisplayName($username, $link, $fallback = ''){
        $fallback = trim((string)$fallback);

        if($fallback !== '' && !pnvSubNameNeedsPanelResolve($fallback, $link)){
            return $fallback;
        }

        $fromCsv = pnvFindSubNameFromCsv($username, $link);

        if($fromCsv !== ''){
            return $fromCsv;
        }

        $cachedEmail = pnvSubDisplayNameFromClientEmail(pnvFindSubCachedClientEmail($link), $link);

        if($cachedEmail !== ''){
            return $cachedEmail;
        }

        $fromPanel = pnvFindSubNameFromPanel($link);

        if($fromPanel !== ''){
            return $fromPanel;
        }

        if($fallback !== '' && !pnvSubNameNeedsPanelResolve($fallback, $link)){
            return $fallback;
        }

        return 'اشتراک';
    }

    function pnvFindSubPlanTextFromCsv($username, $subLink){
        $username = trim((string)$username);
        $subLink = trim((string)$subLink);
        $file = __DIR__ . '/invoices/payments.csv';

        if($username === '' || $subLink === '' || !file_exists($file)){
            return '';
        }

        $target = strtolower(rtrim($subLink, '/'));
        $planText = '';

        $handle = fopen($file, 'r');

        while(($row = fgetcsv($handle)) !== false){
            if(($row[0] ?? '') !== $username){
                continue;
            }

            if(trim((string)($row[6] ?? '')) !== 'تایید شد'){
                continue;
            }

            $type = trim((string)($row[9] ?? ''));
            $buyLink = strtolower(rtrim(trim((string)($row[7] ?? '')), '/'));
            $renewLink = strtolower(rtrim(trim((string)($row[1] ?? '')), '/'));

            if($type === 'خرید' && $buyLink !== '' && ($buyLink === $target || strpos($target, $buyLink) !== false || strpos($buyLink, $target) !== false)){
                $planText = trim((string)($row[2] ?? ''));
                break;
            }

            if($type === 'تمدید' && $renewLink !== '' && ($renewLink === $target || strpos($target, $renewLink) !== false || strpos($renewLink, $target) !== false)){
                if($planText === ''){
                    $planText = trim((string)($row[2] ?? ''));
                }
            }
        }

        fclose($handle);

        return $planText;
    }

    function pnvValidateRenewPlanCategory($username, $subLink, $planValue, $plans){
        $selectedPlan = null;

        if(!is_array($plans)){
            $plans = [];
        }

        foreach($plans as $plan){
            if(!is_array($plan)){
                continue;
            }

            if(pnvPlanOptionValue($plan) === $planValue){
                $selectedPlan = $plan;
                break;
            }
        }

        if(!$selectedPlan){
            $selectedPlan = pnvFindPlanByValue($planValue, $plans);
        }

        if(!$selectedPlan){
            return ['ok' => false, 'error' => 'پلن انتخاب‌شده معتبر نیست'];
        }

        $selectedCategory = pnvPlanIsUnlimited($selectedPlan) ? 'unlimited' : 'limited';
        $fullLink = pnvFindSubLinkFromCsv($username, $subLink);
        $planText = pnvFindSubPlanTextFromCsv($username, $subLink);
        $subCategory = pnvResolveSubTimeCategory($fullLink, $planText, $username);

        if($subCategory === $selectedCategory){
            return ['ok' => true];
        }

        if($subCategory === 'limited'){
            return [
                'ok' => false,
                'error' => 'این اشتراک زمان‌دار است و نمی‌توان آن را با پلن نامحدود زمانی تمدید کرد.'
            ];
        }

        return [
            'ok' => false,
            'error' => 'این اشتراک نامحدود زمانی است و نمی‌توان آن را با پلن زمان‌دار تمدید کرد.'
        ];
    }
}
