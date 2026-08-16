<?php
/**
 * telegram_notify.php — CLI cron script for user Telegram notifications
 *
 * Sends proactive notifications to users who have connected their Telegram
 * accounts. Handles:
 *   - Subscription expiry warnings (< 3 days or < 10% volume/time remaining)
 *   - Campaign/announcement broadcasts to all connected users
 *
 * Usage:
 *   php telegram_notify.php               — run all notification checks
 *   php telegram_notify.php --subs        — only subscription expiry alerts
 *   php telegram_notify.php --campaigns   — only campaign broadcasts
 *
 * Recommended cron (every 6 hours):
 *   0 */6 * * * php /path/to/telegram_notify.php >> /var/log/tg_notify.log 2>&1
 */

if(PHP_SAPI !== 'cli'){
    http_response_code(403);
    exit('CLI only');
}

$rootDir = __DIR__;
define('TG_NOTIFY_ROOT', $rootDir);

require_once $rootDir . '/telegram_lib.php';
require_once $rootDir . '/subscription_lib.php';

$runSubs      = in_array('--subs', $argv ?? [], true);
$runCampaigns = in_array('--campaigns', $argv ?? [], true);

if(!$runSubs && !$runCampaigns){
    $runSubs = true;
    $runCampaigns = true;
}

$config = telegramLoadConfig();

if(empty($config['enabled']) || trim((string)($config['bot_token'] ?? '')) === ''){
    exit("Telegram bot is disabled or not configured.\n");
}

// ─── helpers ─────────────────────────────────────────────────────────────────

function tgNotifyLog($msg){
    echo '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
}

function tgNotifySentPath(){
    return TG_NOTIFY_ROOT . '/db/telegram_notify_sent.json';
}

function tgNotifyLoadSent(){
    $path = tgNotifySentPath();

    if(!file_exists($path)){
        return [];
    }

    $data = json_decode(file_get_contents($path), true);
    return is_array($data) ? $data : [];
}

function tgNotifySaveSent($sent){
    file_put_contents(
        tgNotifySentPath(),
        json_encode($sent, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
        LOCK_EX
    );
}

function tgNotifyAlreadySent($sent, $key){
    if(!isset($sent[$key])){
        return false;
    }

    // هر کلید حداکثر یک بار در 24 ساعت ارسال می‌شود
    return (time() - intval($sent[$key])) < 86400;
}

function tgNotifyMarkSent(&$sent, $key){
    $sent[$key] = time();
}

function tgNotifyPruneSent($sent){
    $cutoff = time() - 7 * 86400; // ورودی‌های قدیمی‌تر از یک هفته را حذف کن
    return array_filter($sent, function($ts) use ($cutoff){
        return intval($ts) >= $cutoff;
    });
}

function tgNotifyLoadAllUsers(){
    $path = TG_NOTIFY_ROOT . '/db/users.json';

    if(!file_exists($path)){
        return [];
    }

    $data = json_decode(file_get_contents($path), true);
    return is_array($data) ? $data : [];
}

function tgNotifyConnectedUsers(){
    $users = tgNotifyLoadAllUsers();
    $result = [];

    foreach($users as $user){
        $chatId = trim((string)($user['telegram_chat_id'] ?? ''));

        if($chatId !== ''){
            $result[] = [
                'username' => trim((string)($user['username'] ?? '')),
                'chat_id' => $chatId
            ];
        }
    }

    return $result;
}

function tgNotifyLoadUsageCache(){
    $path = TG_NOTIFY_ROOT . '/db/sub_usage_cache.json';

    if(!file_exists($path)){
        return [];
    }

    $data = json_decode((string)@file_get_contents($path), true);
    return is_array($data) ? $data : [];
}

function tgNotifySubCacheKey($link){
    if(function_exists('subUsageCacheKey')){
        return subUsageCacheKey($link);
    }

    // fallback
    return 'raw|' . sha1(strtolower(trim((string)$link)));
}

function tgNotifyCheckSub($cached){
    if(!is_array($cached) || empty($cached['ok']) || ($cached['source'] ?? '') !== 'panel'){
        return null;
    }

    $vol  = is_array($cached['volume'] ?? null) ? $cached['volume'] : [];
    $time = is_array($cached['time'] ?? null)   ? $cached['time']   : [];

    $volUnlimited  = !empty($vol['unlimited']);
    $timeUnlimited = !empty($time['unlimited']);

    $volPct  = $volUnlimited  ? 100.0 : floatval($vol['remain_pct']  ?? 0);
    $timePct = $timeUnlimited ? 100.0 : floatval($time['remain_pct'] ?? 0);

    $timeRemainSec = intval($time['remain_seconds'] ?? 0);
    $timeRemainDays = $timeUnlimited ? PHP_INT_MAX : (int)floor($timeRemainSec / 86400);

    $warnings = [];

    if(!$volUnlimited && $volPct <= 10){
        $gb = function_exists('subUsageFormatBytes')
            ? subUsageFormatBytes(floatval($vol['remain'] ?? 0))
            : round(floatval($vol['remain'] ?? 0) / 1073741824, 2) . ' GB';
        $warnings[] = "📦 حجم باقیمانده: {$gb} ({$volPct}٪)";
    }

    if(!$timeUnlimited && $timeRemainDays <= 3 && $timeRemainSec > 0){
        $label = function_exists('subUsageFormatDaysLeft')
            ? subUsageFormatDaysLeft($timeRemainSec)
            : $timeRemainDays . ' روز';
        $warnings[] = "⏳ زمان باقیمانده: {$label}";
    }

    if(!$volUnlimited && $volPct <= 0.05){
        $warnings[] = '⛔ حجم اشتراک تمام شده است';
    }

    if(!$timeUnlimited && $timeRemainSec <= 0){
        $warnings[] = '⛔ زمان اشتراک به پایان رسیده است';
    }

    return count($warnings) > 0 ? $warnings : null;
}

// ─── subscription expiry notifications ───────────────────────────────────────

function tgNotifyRunSubExpiry($config, &$sent){
    $users = tgNotifyConnectedUsers();

    if(count($users) === 0){
        tgNotifyLog('هیچ کاربری تلگرام متصل ندارد');
        return;
    }

    $usageCache = tgNotifyLoadUsageCache();
    $notified = 0;

    foreach($users as $userInfo){
        $username = $userInfo['username'];
        $chatId   = $userInfo['chat_id'];

        if($username === '' || $chatId === ''){
            continue;
        }

        $subs = pnvLoadUserActiveSubscriptions($username, false);

        foreach($subs as $sub){
            $link = trim((string)($sub['link'] ?? ''));

            if($link === ''){
                continue;
            }

            $cacheKey = tgNotifySubCacheKey($link);
            $cached = $usageCache[$cacheKey] ?? null;

            if(!is_array($cached)){
                continue;
            }

            $warnings = tgNotifyCheckSub($cached);

            if(!$warnings){
                continue;
            }

            $sentKey = 'sub:' . $username . ':' . md5($link);

            if(tgNotifyAlreadySent($sent, $sentKey)){
                continue;
            }

            $subName = trim((string)($sub['name'] ?? ''));

            if($subName === '' && preg_match('/\/sub\/([^\/\?]+)/i', $link, $m)){
                $subName = $m[1];
            }

            $text = "⚠️ هشدار اشتراک\n\n";

            if($subName !== ''){
                $text .= "اشتراک: {$subName}\n";
            }

            $text .= implode("\n", $warnings);
            $text .= "\n\nبرای تمدید به پنل مراجعه کنید.";

            $result = telegramSendMessage($chatId, $text, [], $config);

            if(!empty($result['ok'])){
                tgNotifyMarkSent($sent, $sentKey);
                $notified++;
                tgNotifyLog("ارسال هشدار اشتراک به {$username}");
            } else {
                tgNotifyLog("خطا در ارسال به {$username}: " . ($result['description'] ?? 'نامشخص'));
            }

            usleep(300000); // 300ms throttle
        }
    }

    tgNotifyLog("هشدار اشتراک: {$notified} پیام ارسال شد");
}

// ─── campaign announcement broadcasts ────────────────────────────────────────

function tgNotifyLoadAnnouncements(){
    $path = TG_NOTIFY_ROOT . '/db/dashboard_announcements.json';

    if(!file_exists($path)){
        return [];
    }

    $data = json_decode(file_get_contents($path), true);
    return is_array($data) ? $data : [];
}

function tgNotifyRunCampaigns($config, &$sent){
    $announcements = tgNotifyLoadAnnouncements();
    $users = tgNotifyConnectedUsers();

    if(count($users) === 0 || count($announcements) === 0){
        tgNotifyLog('هیچ کمپین یا کاربر متصلی وجود ندارد');
        return;
    }

    $now = time();
    $totalSent = 0;

    foreach($announcements as $ann){
        if(($ann['status'] ?? '') !== 'active'){
            continue;
        }

        $annId = trim((string)($ann['id'] ?? ''));

        if($annId === ''){
            continue;
        }

        $startsAt  = intval($ann['starts_at'] ?? 0);
        $expiresAt = intval($ann['expires_at'] ?? 0);

        if($startsAt > 0 && $now < $startsAt){
            continue;
        }

        if($expiresAt > 0 && $now >= $expiresAt){
            continue;
        }

        $title   = trim((string)($ann['title']   ?? ''));
        $message = trim((string)($ann['message'] ?? ''));

        if($title === '' && $message === ''){
            continue;
        }

        foreach($users as $userInfo){
            $username = $userInfo['username'];
            $chatId   = $userInfo['chat_id'];

            if($username === '' || $chatId === ''){
                continue;
            }

            $sentKey = 'ann:' . $annId . ':' . $username;

            if(tgNotifyAlreadySent($sent, $sentKey)){
                continue;
            }

            $text = "📢 " . ($title !== '' ? $title : 'اطلاع‌رسانی');

            if($message !== ''){
                $text .= "\n\n" . $message;
            }

            $result = telegramSendMessage($chatId, $text, [], $config);

            if(!empty($result['ok'])){
                tgNotifyMarkSent($sent, $sentKey);
                $totalSent++;
                tgNotifyLog("ارسال اطلاع‌رسانی '{$annId}' به {$username}");
            } else {
                tgNotifyLog("خطا در ارسال اطلاع‌رسانی به {$username}: " . ($result['description'] ?? 'نامشخص'));
            }

            usleep(200000); // 200ms throttle
        }
    }

    tgNotifyLog("کمپین: {$totalSent} پیام ارسال شد");
}

// ─── main ─────────────────────────────────────────────────────────────────────

$sent = tgNotifyLoadSent();
$sent = tgNotifyPruneSent($sent);

if($runSubs){
    tgNotifyLog('شروع بررسی اشتراک‌های در حال انقضا...');
    tgNotifyRunSubExpiry($config, $sent);
}

if($runCampaigns){
    tgNotifyLog('شروع ارسال کمپین‌ها...');
    tgNotifyRunCampaigns($config, $sent);
}

tgNotifySaveSent($sent);
tgNotifyLog('پایان اجرا');
