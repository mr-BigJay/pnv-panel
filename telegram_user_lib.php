<?php

require_once __DIR__ . '/telegram_lib.php';
require_once __DIR__ . '/profile_lib.php';

if(!function_exists('tgUserFaNum')){

    function tgUserFaNum($value){
        return str_replace(
            ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
            ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'],
            (string)$value
        );
    }

    function tgUserRtlLine($text){
        $text = (string)$text;

        if($text === ''){
            return $text;
        }

        // RIGHT-TO-LEFT MARK — راست‌چین شدن خطوطی که با ایموجی یا لاتین شروع می‌شوند
        return "\u{200F}" . $text;
    }

    function tgUserLinksPath(){
        return __DIR__ . '/db/telegram_links.json';
    }

    function tgUserSessionsPath(){
        return __DIR__ . '/db/telegram_user_sessions.json';
    }

    function tgUserNotifyStatePath(){
        return __DIR__ . '/db/telegram_notify_state.json';
    }

    function tgUserDefaultNotifyPrefs(){
        return [
            'expiry' => true,
            'traffic' => true,
            'support' => true,
            'campaign' => true,
            'payment' => true,
        ];
    }

    function tgUserPanelUrl($config = null){
        if($config === null){
            $config = telegramLoadConfig();
        }

        $url = rtrim(trim((string)($config['panel_url'] ?? '')), '/');

        if($url === ''){
            $url = 'https://panel.ticketin.ir';
        }

        return $url;
    }

    function tgUserBtnSubs(){
        return '📋 اشتراک‌ها';
    }

    function tgUserBtnSupport(){
        return '💬 پشتیبانی';
    }

    function tgUserBtnNotify(){
        return '🔔 اعلان‌ها';
    }

    function tgUserBtnSettings(){
        return '⚙️ تنظیمات';
    }

    function tgUserBtnPanel(){
        return '🌐 ورود به پنل';
    }

    function tgUserBtnBack(){
        return '🔙 بازگشت';
    }

    function tgUserLoadLinks(){
        $path = tgUserLinksPath();

        if(!file_exists($path)){
            return [];
        }

        $data = json_decode((string)file_get_contents($path), true);
        return is_array($data) ? $data : [];
    }

    function tgUserSaveLinks($rows){
        $path = tgUserLinksPath();
        $dir = dirname($path);

        if(!is_dir($dir)){
            @mkdir($dir, 0775, true);
        }

        file_put_contents(
            $path,
            json_encode(array_values($rows), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            LOCK_EX
        );
    }

    function tgUserPruneLinks($rows){
        $now = time();
        $clean = [];

        foreach($rows as $row){
            if(!is_array($row)){
                continue;
            }

            if(!empty($row['used'])){
                continue;
            }

            if(intval($row['expires_at'] ?? 0) < $now){
                continue;
            }

            $clean[] = $row;
        }

        return $clean;
    }

    function tgUserLoadSessions(){
        $path = tgUserSessionsPath();

        if(!file_exists($path)){
            return [];
        }

        $data = json_decode((string)file_get_contents($path), true);
        return is_array($data) ? $data : [];
    }

    function tgUserSaveSessions($rows){
        $path = tgUserSessionsPath();
        $dir = dirname($path);

        if(!is_dir($dir)){
            @mkdir($dir, 0775, true);
        }

        file_put_contents(
            $path,
            json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            LOCK_EX
        );
    }

    function tgUserGetSession($chatId){
        $rows = tgUserLoadSessions();
        $key = (string)$chatId;

        return is_array($rows[$key] ?? null) ? $rows[$key] : [];
    }

    function tgUserSetSession($chatId, $patch){
        $rows = tgUserLoadSessions();
        $key = (string)$chatId;
        $current = is_array($rows[$key] ?? null) ? $rows[$key] : [];
        $rows[$key] = array_merge($current, $patch, ['updated_at' => time()]);
        tgUserSaveSessions($rows);
    }

    function tgUserFindByChatId($chatId){
        $chatId = trim((string)$chatId);

        if($chatId === ''){
            return null;
        }

        foreach(profileLoadUsers() as $user){
            if((string)($user['telegram_chat_id'] ?? '') === $chatId){
                return $user;
            }
        }

        return null;
    }

    function tgUserGetNotifyPrefs($userRow){
        $defaults = tgUserDefaultNotifyPrefs();
        $saved = $userRow['telegram_notify'] ?? [];

        if(!is_array($saved)){
            return $defaults;
        }

        return array_merge($defaults, $saved);
    }

    function tgUserIsLinkedChat($chatId, $config = null){
        if(telegramCanUseBot($chatId, $config)){
            return false;
        }

        return tgUserFindByChatId($chatId) !== null;
    }

    function tgUserGetBotUsername($config = null){
        if($config === null){
            $config = telegramLoadConfig();
        }

        $configured = trim(ltrim((string)($config['bot_username'] ?? ''), '@'));

        if($configured !== ''){
            return $configured;
        }

        $tokenHash = tgUserBotTokenHash($config);
        $cached = tgUserLoadBotUsernameCache();

        if(
            is_array($cached)
            && ($cached['token_hash'] ?? '') === $tokenHash
            && trim((string)($cached['username'] ?? '')) !== ''
        ){
            return ltrim((string)$cached['username'], '@');
        }

        $me = telegramApiRequest('getMe', [], [], $config);

        if(!empty($me['ok']) && !empty($me['result']['username'])){
            $username = ltrim((string)$me['result']['username'], '@');
            tgUserSaveBotUsernameCache($config, $username);
            return $username;
        }

        return '';
    }

    function tgUserBotTokenHash($config){
        return hash('sha256', trim((string)($config['bot_token'] ?? '')));
    }

    function tgUserBotUsernameCachePath(){
        return __DIR__ . '/db/telegram_bot_username.cache';
    }

    function tgUserLoadBotUsernameCache(){
        $path = tgUserBotUsernameCachePath();

        if(!file_exists($path)){
            return null;
        }

        $raw = trim((string)file_get_contents($path));

        if($raw === ''){
            return null;
        }

        $data = json_decode($raw, true);

        if(is_array($data) && trim((string)($data['username'] ?? '')) !== ''){
            return $data;
        }

        return [
            'username' => ltrim($raw, '@'),
            'token_hash' => '',
        ];
    }

    function tgUserSaveBotUsernameCache($config, $username){
        $username = trim(ltrim((string)$username, '@'));

        if($username === ''){
            return;
        }

        $path = tgUserBotUsernameCachePath();
        $dir = dirname($path);

        if(!is_dir($dir)){
            @mkdir($dir, 0775, true);
        }

        file_put_contents(
            $path,
            json_encode([
                'username' => $username,
                'token_hash' => tgUserBotTokenHash($config),
                'updated_at' => time(),
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            LOCK_EX
        );
    }

    function tgUserClearBotUsernameCache(){
        $path = tgUserBotUsernameCachePath();

        if(file_exists($path)){
            @unlink($path);
        }
    }

    function tgUserClearLinkTokens(){
        tgUserSaveLinks([]);
    }

    function tgUserHandleBotConfigChange($oldConfig, $newConfig){
        $oldToken = trim((string)($oldConfig['bot_token'] ?? ''));
        $newToken = trim((string)($newConfig['bot_token'] ?? ''));
        $oldUser = trim(ltrim((string)($oldConfig['bot_username'] ?? ''), '@'));
        $newUser = trim(ltrim((string)($newConfig['bot_username'] ?? ''), '@'));

        if($oldToken !== $newToken || $oldUser !== $newUser){
            tgUserClearBotUsernameCache();
            tgUserClearLinkTokens();
        }

        if($newUser === '' && $newToken !== ''){
            $me = telegramApiRequest('getMe', [], [], $newConfig);

            if(!empty($me['ok']) && !empty($me['result']['username'])){
                $newConfig['bot_username'] = ltrim((string)$me['result']['username'], '@');
            }
        }

        if(trim(ltrim((string)($newConfig['bot_username'] ?? ''), '@')) !== ''){
            tgUserSaveBotUsernameCache($newConfig, $newConfig['bot_username']);
        }

        return $newConfig;
    }

    function tgUserCreateLinkToken($username){
        $username = trim((string)$username);

        if($username === ''){
            return ['ok' => false, 'error' => 'کاربر نامعتبر است'];
        }

        $token = bin2hex(random_bytes(8));
        $now = time();
        $rows = tgUserPruneLinks(tgUserLoadLinks());

        foreach($rows as $row){
            if(strcasecmp((string)($row['username'] ?? ''), $username) === 0 && empty($row['used'])){
                $existingToken = (string)($row['token'] ?? '');
                $existingBot = trim(ltrim((string)($row['bot_username'] ?? ''), '@'));
                $currentBot = tgUserGetBotUsername();

                if($existingToken !== '' && strcasecmp($existingBot, $currentBot) === 0){
                    return [
                        'ok' => true,
                        'token' => $existingToken,
                        'expires_at' => intval($row['expires_at'] ?? 0),
                    ];
                }
            }
        }

        foreach($rows as $i => $row){
            if(strcasecmp((string)($row['username'] ?? ''), $username) === 0 && empty($row['used'])){
                unset($rows[$i]);
            }
        }
        $rows = array_values($rows);

        $rows[] = [
            'token' => $token,
            'username' => $username,
            'bot_username' => tgUserGetBotUsername(),
            'created_at' => $now,
            'expires_at' => $now + 900,
            'used' => false,
        ];

        tgUserSaveLinks($rows);

        return [
            'ok' => true,
            'token' => $token,
            'expires_at' => $now + 900,
        ];
    }

    function tgUserBuildLinkUrl($token, $config = null){
        $username = tgUserGetBotUsername($config);
        $token = trim((string)$token);

        if($username === '' || $token === ''){
            return '';
        }

        return 'https://t.me/' . rawurlencode($username) . '?start=link_' . rawurlencode($token);
    }

    function tgUserGetTelegramStatus($username){
        $users = profileLoadUsers();
        $index = profileFindUserIndex($users, $username);

        if($index < 0){
            return ['linked' => false];
        }

        $row = $users[$index];
        $linked = trim((string)($row['telegram_chat_id'] ?? '')) !== '';

        return [
            'linked' => $linked,
            'chat_id' => trim((string)($row['telegram_chat_id'] ?? '')),
            'telegram_username' => trim((string)($row['telegram_username'] ?? '')),
            'linked_at' => trim((string)($row['telegram_linked_at'] ?? '')),
            'notify' => tgUserGetNotifyPrefs($row),
        ];
    }

    function tgUserLinkAccount($username, $chatId, $telegramUsername = ''){
        $username = trim((string)$username);
        $chatId = trim((string)$chatId);

        if($username === '' || $chatId === ''){
            return ['ok' => false, 'error' => 'اطلاعات اتصال ناقص است'];
        }

        $users = profileLoadUsers();
        $index = profileFindUserIndex($users, $username);

        if($index < 0){
            return ['ok' => false, 'error' => 'کاربر پیدا نشد'];
        }

        foreach($users as $i => $user){
            if($i !== $index && (string)($user['telegram_chat_id'] ?? '') === $chatId){
                $users[$i]['telegram_chat_id'] = '';
                $users[$i]['telegram_username'] = '';
                $users[$i]['telegram_linked_at'] = '';
            }
        }

        $nowParts = function_exists('pnvNowParts') ? pnvNowParts() : ['date' => date('Y-m-d'), 'time' => date('H:i:s')];
        $users[$index]['telegram_chat_id'] = $chatId;
        $users[$index]['telegram_username'] = trim((string)$telegramUsername);
        $users[$index]['telegram_linked_at'] = trim(($nowParts['date'] ?? date('Y-m-d')) . ' ' . ($nowParts['time'] ?? date('H:i:s')));

        if(!isset($users[$index]['telegram_notify']) || !is_array($users[$index]['telegram_notify'])){
            $users[$index]['telegram_notify'] = tgUserDefaultNotifyPrefs();
        }

        profileSaveUsers($users);

        return ['ok' => true, 'username' => $username];
    }

    function tgUserDisconnect($username){
        $users = profileLoadUsers();
        $index = profileFindUserIndex($users, $username);

        if($index < 0){
            return ['ok' => false, 'error' => 'کاربر پیدا نشد'];
        }

        $users[$index]['telegram_chat_id'] = '';
        $users[$index]['telegram_username'] = '';
        $users[$index]['telegram_linked_at'] = '';
        profileSaveUsers($users);

        return ['ok' => true];
    }

    function tgUserConsumeLinkToken($token, $chatId, $from = []){
        $token = trim((string)$token);
        $chatId = trim((string)$chatId);
        $now = time();
        $rows = tgUserLoadLinks();
        $found = -1;

        foreach($rows as $i => $row){
            if((string)($row['token'] ?? '') === $token){
                $found = $i;
                break;
            }
        }

        if($found < 0){
            return ['ok' => false, 'error' => 'لینک اتصال نامعتبر یا منقضی شده است'];
        }

        $row = $rows[$found];

        if(!empty($row['used'])){
            return ['ok' => false, 'error' => 'این لینک قبلاً استفاده شده است'];
        }

        if(intval($row['expires_at'] ?? 0) < $now){
            return ['ok' => false, 'error' => 'لینک اتصال منقضی شده است. از داشبورد دوباره تلاش کنید'];
        }

        $username = trim((string)($row['username'] ?? ''));

        if($username === ''){
            return ['ok' => false, 'error' => 'کاربر مرتبط با لینک پیدا نشد'];
        }

        $rows[$found]['used'] = true;
        $rows[$found]['used_at'] = $now;
        $rows[$found]['chat_id'] = $chatId;
        tgUserSaveLinks(tgUserPruneLinks($rows));

        $tgUsername = trim((string)($from['username'] ?? ''));

        if($tgUsername !== '' && $tgUsername[0] !== '@'){
            $tgUsername = '@' . $tgUsername;
        }

        return tgUserLinkAccount($username, $chatId, $tgUsername);
    }

    function tgUserReplyMarkup($rows){
        return json_encode([
            'keyboard' => $rows,
            'resize_keyboard' => true,
            'is_persistent' => true,
        ], JSON_UNESCAPED_UNICODE);
    }

    function tgUserMainKeyboard(){
        return tgUserReplyMarkup([
            [tgUserBtnSubs(), tgUserBtnSupport()],
            [tgUserBtnNotify(), tgUserBtnSettings()],
            [tgUserBtnPanel()],
        ]);
    }

    function tgUserBackKeyboard(){
        return tgUserReplyMarkup([
            [tgUserBtnBack()],
        ]);
    }

    function tgUserSettingsKeyboard($prefs){
        $on = function($key) use ($prefs){
            return !empty($prefs[$key]) ? 'روشن' : 'خاموش';
        };

        return tgUserReplyMarkup([
            ['⏳ انقضا: ' . $on('expiry'), '📦 حجم: ' . $on('traffic')],
            ['💬 پشتیبانی: ' . $on('support'), '🎁 کمپین: ' . $on('campaign')],
            ['💳 پرداخت: ' . $on('payment'), '🔕 خاموش کردن همه'],
            ['🔌 قطع اتصال تلگرام'],
            [tgUserBtnBack()],
        ]);
    }

    function tgUserSupportKeyboard(){
        return tgUserReplyMarkup([
            ['📩 مشاهده پیام‌های جدید'],
            ['✏️ ارسال پیام جدید'],
            [tgUserBtnBack()],
        ]);
    }

    function tgUserSubsKeyboard($subs){
        $rows = [];
        $line = [];

        foreach($subs as $sub){
            $label = tgUserLimitButtonLabel(tgUserSubDisplayName($sub));
            $line[] = $label;

            if(count($line) === 2){
                $rows[] = $line;
                $line = [];
            }
        }

        if(count($line) > 0){
            $rows[] = $line;
        }

        $rows[] = [tgUserBtnBack()];
        return tgUserReplyMarkup($rows);
    }

    function tgUserLimitButtonLabel($text){
        $text = trim((string)$text);

        if(function_exists('mb_substr')){
            return mb_strlen($text, 'UTF-8') > 28 ? mb_substr($text, 0, 25, 'UTF-8') . '…' : $text;
        }

        return strlen($text) > 28 ? substr($text, 0, 25) . '...' : $text;
    }

    function tgUserAnnouncementMsgsPath(){
        return __DIR__ . '/db/telegram_announcement_msgs.json';
    }

    function tgUserAnnouncementMsgsLoad(){
        $path = tgUserAnnouncementMsgsPath();

        if(!file_exists($path)){
            return [];
        }

        $data = json_decode((string)file_get_contents($path), true);
        return is_array($data) ? $data : [];
    }

    function tgUserAnnouncementMsgsSave($rows){
        $path = tgUserAnnouncementMsgsPath();
        $dir = dirname($path);

        if(!is_dir($dir)){
            @mkdir($dir, 0775, true);
        }

        file_put_contents(
            $path,
            json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            LOCK_EX
        );
    }

    function tgUserCampaignLibReady(){
        if(!function_exists('campaignAnnouncementsLoad')){
            require_once __DIR__ . '/campaign_lib.php';
        }
    }

    function tgUserBuildAnnouncementText($row){
        tgUserCampaignLibReady();

        $title = trim((string)($row['title'] ?? ''));
        $message = trim((string)($row['message'] ?? ''));
        $type = campaignAnnouncementTypeLabel($row['type'] ?? 'info');
        $lines = ['📢 ' . ($title !== '' ? $title : 'اطلاع‌رسانی')];

        if($message !== ''){
            $lines[] = $message;
        }

        $lines[] = '';
        $lines[] = 'نوع: ' . $type;

        return trim(implode("\n", $lines));
    }

    function tgUserGetTelegramAnnouncements($now = null){
        tgUserCampaignLibReady();

        $now = $now ?? time();
        $rows = campaignAnnouncementsLoad();
        $active = [];

        foreach($rows as $row){
            if(!is_array($row) || !campaignAnnouncementIsActive($row, $now)){
                continue;
            }

            $active[] = $row;
        }

        usort($active, function($a, $b){
            $createdDiff = intval($a['created_at'] ?? 0) <=> intval($b['created_at'] ?? 0);

            if($createdDiff !== 0){
                return $createdDiff;
            }

            return strcmp((string)($a['id'] ?? ''), (string)($b['id'] ?? ''));
        });

        return $active;
    }

    function tgUserShouldReceiveAnnouncement($userRow){
        if(!is_array($userRow)){
            return false;
        }

        if(trim((string)($userRow['telegram_chat_id'] ?? '')) === ''){
            return false;
        }

        $prefs = tgUserGetNotifyPrefs($userRow);
        return !empty($prefs['campaign']);
    }

    function tgUserDeleteAnnouncementMessage($chatId, $messageId, $config = null){
        $messageId = intval($messageId);

        if($messageId <= 0){
            return;
        }

        telegramDeleteMessage((string)$chatId, $messageId, $config);
    }

    function tgUserShouldClearUserInputMessage($text, $session){
        $text = trim((string)$text);

        if($text === ''){
            return false;
        }

        if(trim((string)($session['mode'] ?? '')) === 'write'){
            return false;
        }

        return true;
    }

    function tgUserClearUserInputMessage($chatId, $message, $config = null){
        if($config === null){
            $config = telegramLoadConfig();
        }

        $chatId = (string)$chatId;
        $session = tgUserGetSession($chatId);
        $storedIds = is_array($session['user_input_message_ids'] ?? null) ? $session['user_input_message_ids'] : [];

        foreach($storedIds as $msgId){
            tgUserDeleteAnnouncementMessage($chatId, intval($msgId), $config);
        }

        if(is_array($message)){
            $currentId = intval($message['message_id'] ?? 0);

            if($currentId > 0){
                tgUserDeleteAnnouncementMessage($chatId, $currentId, $config);
            }
        }

        tgUserSetSession($chatId, ['user_input_message_ids' => []]);
    }

    function tgUserClearMenu($chatId, $config = null){
        $session = tgUserGetSession($chatId);
        $menuMessageId = intval($session['menu_message_id'] ?? 0);

        if($menuMessageId > 0){
            tgUserDeleteAnnouncementMessage($chatId, $menuMessageId, $config);
        }

        tgUserSetSession($chatId, [
            'menu_message_id' => 0,
            'menu_text' => '',
            'menu_keyboard' => '',
        ]);
    }

    function tgUserShowMenu($chatId, $text, $keyboard, $config = null, $forceNew = false){
        if($config === null){
            $config = telegramLoadConfig();
        }

        $chatId = (string)$chatId;
        $session = tgUserGetSession($chatId);
        $menuMessageId = intval($session['menu_message_id'] ?? 0);
        $keyboardJson = is_string($keyboard) ? $keyboard : json_encode($keyboard, JSON_UNESCAPED_UNICODE);
        $extra = ['reply_markup' => $keyboardJson];

        if(!$forceNew && $menuMessageId > 0){
            $edited = telegramEditMessage($chatId, $menuMessageId, $text, $extra, $config);

            if(
                !empty($edited['ok'])
                || stripos((string)($edited['description'] ?? ''), 'message is not modified') !== false
            ){
                tgUserSetSession($chatId, [
                    'menu_message_id' => $menuMessageId,
                    'menu_text' => $text,
                    'menu_keyboard' => $keyboardJson,
                ]);
                return $edited;
            }
        }

        if($menuMessageId > 0){
            tgUserDeleteAnnouncementMessage($chatId, $menuMessageId, $config);
        }

        $sent = telegramSendMessage($chatId, $text, $extra, $config);
        $newId = intval($sent['result']['message_id'] ?? 0);

        if($newId > 0){
            tgUserSetSession($chatId, [
                'menu_message_id' => $newId,
                'menu_text' => $text,
                'menu_keyboard' => $keyboardJson,
            ]);
        }

        return $sent;
    }

    function tgUserBumpMenu($chatId, $config = null){
        $session = tgUserGetSession($chatId);
        $text = trim((string)($session['menu_text'] ?? ''));
        $keyboard = trim((string)($session['menu_keyboard'] ?? ''));

        if($text === '' || $keyboard === ''){
            return;
        }

        tgUserShowMenu($chatId, $text, $keyboard, $config, true);
    }

    function tgUserSendAnnouncementMessage($chatId, $row, $config = null){
        if($config === null){
            $config = telegramLoadConfig();
        }

        if(empty($config['enabled']) || trim((string)($config['bot_token'] ?? '')) === ''){
            return 0;
        }

        $sent = telegramSendMessage((string)$chatId, tgUserBuildAnnouncementText($row), [], $config);
        return intval($sent['result']['message_id'] ?? 0);
    }

    function tgUserSyncAnnouncementsForChat($chatId, $username, $config = null){
        $chatId = (string)$chatId;
        $username = trim((string)$username);

        if($chatId === '' || $username === ''){
            return;
        }

        if($config === null){
            $config = telegramLoadConfig();
        }

        $userRow = null;

        foreach(profileLoadUsers() as $user){
            if(strcasecmp((string)($user['username'] ?? ''), $username) === 0){
                $userRow = $user;
                break;
            }
        }

        if(!$userRow || !tgUserShouldReceiveAnnouncement($userRow)){
            tgUserPurgeAnnouncementMessagesForChat($chatId, $config);
            return;
        }

        $active = tgUserGetTelegramAnnouncements();
        $activeIds = [];

        foreach($active as $row){
            $annId = trim((string)($row['id'] ?? ''));

            if($annId !== ''){
                $activeIds[] = $annId;
            }
        }

        $store = tgUserAnnouncementMsgsLoad();
        $sentNew = false;

        foreach($store as $annId => $chatMap){
            if(!is_array($chatMap) || !isset($chatMap[$chatId])){
                continue;
            }

            if(!in_array((string)$annId, $activeIds, true)){
                tgUserDeleteAnnouncementMessage($chatId, intval($chatMap[$chatId]), $config);
                unset($store[$annId][$chatId]);

                if(count($store[$annId]) === 0){
                    unset($store[$annId]);
                }
            }
        }

        foreach($active as $row){
            $annId = trim((string)($row['id'] ?? ''));

            if($annId === ''){
                continue;
            }

            $existingId = intval($store[$annId][$chatId] ?? 0);

            if($existingId > 0){
                $edited = telegramEditMessage(
                    $chatId,
                    $existingId,
                    tgUserBuildAnnouncementText($row),
                    [],
                    $config
                );

                if(empty($edited['ok'])){
                    tgUserDeleteAnnouncementMessage($chatId, $existingId, $config);
                    unset($store[$annId][$chatId]);
                    $existingId = 0;
                }
            }

            if($existingId > 0){
                continue;
            }

            $messageId = tgUserSendAnnouncementMessage($chatId, $row, $config);

            if($messageId > 0){
                if(!isset($store[$annId]) || !is_array($store[$annId])){
                    $store[$annId] = [];
                }

                $store[$annId][$chatId] = $messageId;
                $sentNew = true;
            }
        }

        tgUserAnnouncementMsgsSave($store);

        if($sentNew){
            tgUserBumpMenu($chatId, $config);
        }
    }

    function tgUserPurgeAnnouncementMessagesForChat($chatId, $config = null){
        $chatId = (string)$chatId;
        $store = tgUserAnnouncementMsgsLoad();
        $changed = false;

        foreach($store as $annId => $chatMap){
            if(!is_array($chatMap) || !isset($chatMap[$chatId])){
                continue;
            }

            tgUserDeleteAnnouncementMessage($chatId, intval($chatMap[$chatId]), $config);
            unset($store[$annId][$chatId]);
            $changed = true;

            if(count($store[$annId]) === 0){
                unset($store[$annId]);
            }
        }

        if($changed){
            tgUserAnnouncementMsgsSave($store);
        }
    }

    function tgUserPublishAnnouncement($row, $config = null){
        if(!is_array($row)){
            return;
        }

        tgUserCampaignLibReady();

        $annId = trim((string)($row['id'] ?? ''));

        if($annId === '' || ($row['status'] ?? '') !== 'active'){
            return;
        }

        if($config === null){
            $config = telegramLoadConfig();
        }

        if(empty($config['enabled']) || trim((string)($config['bot_token'] ?? '')) === ''){
            return;
        }

        if(!campaignAnnouncementIsActive($row)){
            return;
        }

        $store = tgUserAnnouncementMsgsLoad();

        foreach(profileLoadUsers() as $user){
            if(!tgUserShouldReceiveAnnouncement($user)){
                continue;
            }

            $chatId = trim((string)($user['telegram_chat_id'] ?? ''));

            if($chatId === ''){
                continue;
            }

            if(intval($store[$annId][$chatId] ?? 0) > 0){
                continue;
            }

            $messageId = tgUserSendAnnouncementMessage($chatId, $row, $config);

            if($messageId <= 0){
                continue;
            }

            if(!isset($store[$annId]) || !is_array($store[$annId])){
                $store[$annId] = [];
            }

            $store[$annId][$chatId] = $messageId;
            tgUserBumpMenu($chatId, $config);
        }

        tgUserAnnouncementMsgsSave($store);
    }

    function tgUserUpdateAnnouncement($row, $config = null){
        if(!is_array($row)){
            return;
        }

        tgUserCampaignLibReady();

        $annId = trim((string)($row['id'] ?? ''));

        if($annId === ''){
            return;
        }

        if($config === null){
            $config = telegramLoadConfig();
        }

        if(($row['status'] ?? '') !== 'active' || !campaignAnnouncementIsActive($row)){
            tgUserRemoveAnnouncement($annId, $config);
            return;
        }

        $store = tgUserAnnouncementMsgsLoad();
        $chatMap = is_array($store[$annId] ?? null) ? $store[$annId] : [];
        $text = tgUserBuildAnnouncementText($row);

        foreach($chatMap as $chatId => $messageId){
            $messageId = intval($messageId);

            if($messageId <= 0){
                continue;
            }

            $edited = telegramEditMessage((string)$chatId, $messageId, $text, [], $config);

            if(empty($edited['ok'])){
                tgUserDeleteAnnouncementMessage($chatId, $messageId, $config);
                unset($store[$annId][$chatId]);
            }
        }

        if(isset($store[$annId]) && count($store[$annId]) === 0){
            unset($store[$annId]);
        }

        tgUserAnnouncementMsgsSave($store);
        tgUserPublishAnnouncement($row, $config);
    }

    function tgUserRemoveAnnouncement($announcementId, $config = null){
        $announcementId = trim((string)$announcementId);

        if($announcementId === ''){
            return;
        }

        if($config === null){
            $config = telegramLoadConfig();
        }

        $store = tgUserAnnouncementMsgsLoad();
        $chatMap = is_array($store[$announcementId] ?? null) ? $store[$announcementId] : [];

        foreach($chatMap as $chatId => $messageId){
            tgUserDeleteAnnouncementMessage($chatId, intval($messageId), $config);
        }

        unset($store[$announcementId]);
        tgUserAnnouncementMsgsSave($store);
    }

    function tgUserSendKeyboardMessage($chatId, $text, $keyboard, $config = null){
        return tgUserShowMenu($chatId, $text, $keyboard, $config);
    }

    function tgUserLoadSubsBundle($username, $options = []){
        if(!function_exists('pnvLoadUserActiveSubscriptions')){
            require_once __DIR__ . '/subscription_lib.php';
        }

        if(!function_exists('subUsageGetForItems')){
            require_once __DIR__ . '/sub_usage_lib.php';
        }

        if(!function_exists('pnvEnsureSubDisplayName')){
            require_once __DIR__ . '/plan_ui_lib.php';
        }

        $username = trim((string)$username);
        $skipUsage = !empty($options['skip_usage']);
        $forceRefresh = !empty($options['force_refresh']);
        $activeSubs = pnvLoadUserActiveSubscriptions($username, false);
        $items = [];
        $i = 0;

        foreach($activeSubs as $sub){
            $link = trim((string)($sub['link'] ?? ''));

            if($link === '' || !pnvIsValidSubLink($link)){
                continue;
            }

            $i++;
            $items[] = [
                'name' => trim((string)($sub['name'] ?? '')) !== '' ? trim((string)$sub['name']) : ('اشتراک ' . $i),
                'plan' => trim((string)($sub['plan_text'] ?? '')),
                'tracking' => trim((string)($sub['tracking'] ?? '')),
                'date' => trim((string)($sub['date'] ?? '')),
                'time' => trim((string)($sub['time'] ?? '')),
                'created_ts' => intval($sub['created_ts'] ?? 0),
                'link' => $link,
                'usage_key' => subUsageCacheKey($link),
                'usage' => null,
            ];
        }

        if($skipUsage || count($items) === 0){
            return $items;
        }

        $usageItems = [];
        foreach($items as $item){
            $usageItems[] = [
                'link' => $item['link'],
                'plan' => $item['plan'],
                'date' => $item['date'],
                'time' => $item['time'],
                'created_ts' => intval($item['created_ts'] ?? 0),
            ];
        }

        $maxFresh = intval($options['max_fresh'] ?? 0);

        if($maxFresh <= 0){
            $maxFresh = max(1, min(8, count($usageItems)));
        }

        $bundle = subUsageGetForItems($usageItems, $maxFresh, $forceRefresh);
        $usageMap = is_array($bundle['items'] ?? null) ? $bundle['items'] : [];

        foreach($items as &$item){
            $key = $item['usage_key'] ?? subUsageCacheKey($item['link']);
            $usage = $usageMap[$key] ?? null;
            $item['usage'] = $usage;
            $hintEmail = is_array($usage) ? trim((string)($usage['email'] ?? '')) : '';
            $item['name'] = pnvEnsureSubDisplayName($username, $item['link'], $item['name'] ?? '', $hintEmail);
        }
        unset($item);

        return $items;
    }

    function tgUserRefreshSubUsage($sub, $forceRefresh = true){
        if(!is_array($sub)){
            return $sub;
        }

        if(!function_exists('subUsageGetForItems')){
            require_once __DIR__ . '/sub_usage_lib.php';
        }

        $usageItems = [[
            'link' => $sub['link'] ?? '',
            'plan' => $sub['plan'] ?? '',
            'date' => $sub['date'] ?? '',
            'time' => $sub['time'] ?? '',
            'created_ts' => intval($sub['created_ts'] ?? 0),
        ]];

        $bundle = subUsageGetForItems($usageItems, 1, $forceRefresh);
        $usageMap = is_array($bundle['items'] ?? null) ? $bundle['items'] : [];
        $key = subUsageCacheKey($sub['link'] ?? '');
        $usage = $usageMap[$key] ?? null;

        if(is_array($usage)){
            $sub['usage'] = $usage;
        }

        return $sub;
    }

    function tgUserUsageLabels($sub){
        $usage = is_array($sub['usage'] ?? null) ? $sub['usage'] : [];
        $plan = trim((string)($sub['plan'] ?? ''));

        if(empty($usage['ok'])){
            if($plan !== ''){
                return [
                    'time' => 'پلن: ' . $plan,
                    'volume' => 'در حال دریافت اطلاعات مصرف',
                    'remain_pct' => 0,
                    'time_pct' => 0,
                    'warn' => false,
                ];
            }

            return [
                'time' => 'اطلاعات مصرف در دسترس نیست',
                'volume' => '',
                'remain_pct' => 0,
                'time_pct' => 0,
                'warn' => false,
            ];
        }

        $time = is_array($usage['time'] ?? null) ? $usage['time'] : [];
        $vol = is_array($usage['volume'] ?? null) ? $usage['volume'] : [];
        $volPct = !empty($vol['unlimited']) ? 100 : floatval($vol['remain_pct'] ?? 0);
        $timePct = !empty($time['unlimited']) ? 100 : floatval($time['remain_pct'] ?? 0);

        return [
            'time' => trim((string)($time['label'] ?? 'زمان نامشخص')),
            'volume' => trim((string)($vol['label'] ?? '')),
            'remain_pct' => $volPct,
            'time_pct' => $timePct,
            'warn' => ($timePct <= 20 || $volPct <= 20),
        ];
    }

    function tgUserSubDisplayName($sub){
        $name = trim((string)($sub['name'] ?? ''));

        if($name !== '' && $name !== 'اشتراک'){
            return $name;
        }

        $usage = is_array($sub['usage'] ?? null) ? $sub['usage'] : [];
        $email = trim((string)($usage['email'] ?? ''));

        if($email !== ''){
            return $email;
        }

        return $name !== '' ? $name : 'اشتراک';
    }

    function tgUserSupportUnreadCount($username){
        if(!function_exists('supportLoad')){
            require_once __DIR__ . '/support_lib.php';
        }

        $data = supportLoad(__DIR__ . '/db/support.json');

        foreach($data as $ticket){
            if(strcasecmp((string)($ticket['user'] ?? ''), $username) !== 0){
                continue;
            }

            $count = 0;

            foreach(($ticket['messages'] ?? []) as $msg){
                if(($msg['sender'] ?? '') === 'admin' && empty($msg['seen_by_user'])){
                    $count++;
                }
            }

            return $count;
        }

        return 0;
    }

    function tgUserBuildConnectWelcomeText($username){
        $count = count(tgUserLoadSubsBundle($username, ['skip_usage' => true]));
        $lines = [
            'اتصال با موفقیت انجام شد. ✅',
            '',
            'سلام ' . $username . ' 👋',
            '',
            'به ربات پنل خوش آمدید!',
            '',
            'وضعیت اشتراک‌ها:',
        ];

        if($count === 0){
            $lines[] = 'شما اشتراک فعالی ندارید.';
        }
        else{
            $lines[] = 'شما (' . tgUserFaNum((string)$count) . ') اشتراک فعال دارید.';
        }

        return implode("\n", $lines);
    }

    function tgUserBuildHomeText($username){
        $count = count(tgUserLoadSubsBundle($username, ['skip_usage' => true]));
        $unread = tgUserSupportUnreadCount($username);
        $lines = [
            'سلام ' . $username . ' 👋',
            '',
            'به ربات پنل خوش آمدید!',
            '',
            'وضعیت اشتراک‌ها:',
        ];

        if($count === 0){
            $lines[] = 'شما اشتراک فعالی ندارید.';
        }
        else{
            $lines[] = 'شما (' . tgUserFaNum((string)$count) . ') اشتراک فعال دارید.';
        }

        if($unread > 0){
            $lines[] = '';
            $lines[] = tgUserFaNum((string)$unread) . ' پیام خوانده‌نشده از پشتیبانی 📨';
        }

        return trim(implode("\n", $lines));
    }

    function tgUserBuildSubsListText($subs){
        if(count($subs) === 0){
            return "اشتراک فعالی ثبت نشده است.\n\nبرای خرید از دکمه «ورود به پنل» استفاده کنید.";
        }

        $lines = ['اشتراک‌های فعال (' . tgUserFaNum((string)count($subs)) . ')', ''];

        foreach($subs as $i => $sub){
            $lines[] = tgUserFaNum((string)($i + 1)) . '. ' . tgUserSubDisplayName($sub);
        }

        $lines[] = '';
        $lines[] = 'برای جزئیات بیشتر، نام اشتراک را از منوی پایین انتخاب کنید.';
        return trim(implode("\n", $lines));
    }

    function tgUserBuildSubLoadingText($sub){
        $name = tgUserSubDisplayName($sub);

        return implode("\n", [
            $name,
            '',
            tgUserRtlLine('در حال آنالیز اشتراک، لطفاً منتظر بمانید ⏳'),
        ]);
    }

    function tgUserShowSubDetail($chatId, $sub, $config = null){
        if($config === null){
            $config = telegramLoadConfig();
        }

        tgUserSetSession($chatId, [
            'screen' => 'sub_detail',
            'mode' => 'loading',
            'selected_link' => $sub['link'] ?? '',
        ]);
        tgUserSendKeyboardMessage($chatId, tgUserBuildSubLoadingText($sub), tgUserBackKeyboard(), $config);

        $sub = tgUserRefreshSubUsage($sub, true);

        tgUserSetSession($chatId, [
            'screen' => 'sub_detail',
            'mode' => '',
            'selected_link' => $sub['link'] ?? '',
        ]);
        tgUserSendKeyboardMessage($chatId, tgUserBuildSubDetailText($sub), tgUserBackKeyboard(), $config);
    }

    function tgUserBuildSubDetailText($sub){
        $name = tgUserSubDisplayName($sub);
        $labels = tgUserUsageLabels($sub);
        $lines = [
            $name,
            '',
            tgUserRtlLine('⏳ ' . $labels['time']),
        ];

        if($labels['volume'] !== ''){
            $lines[] = tgUserRtlLine('📦 حجم: ' . $labels['volume']);
        }
        else{
            $lines[] = tgUserRtlLine('📦 حجم: ' . tgUserFaNum((string)round(floatval($labels['remain_pct']))) . '٪ باقیمانده');
        }

        if(trim((string)($sub['date'] ?? '')) !== ''){
            $lines[] = tgUserRtlLine('📅 تاریخ: ' . trim((string)$sub['date']) . ' ' . trim((string)($sub['time'] ?? '')));
        }

        if(trim((string)($sub['tracking'] ?? '')) !== ''){
            $lines[] = tgUserRtlLine('🔖 کد پیگیری: ' . trim((string)$sub['tracking']));
        }

        if(trim((string)($sub['plan'] ?? '')) !== ''){
            $lines[] = tgUserRtlLine('📋 پلن: ' . trim((string)$sub['plan']));
        }

        $lines[] = tgUserRtlLine('✅ وضعیت: فعال');
        $lines[] = '';
        $lines[] = 'لینک اشتراک:';
        $lines[] = trim((string)($sub['link'] ?? ''));

        return implode("\n", $lines);
    }

    function tgUserBuildSupportText($username){
        if(!function_exists('supportLoad')){
            require_once __DIR__ . '/support_lib.php';
        }

        $data = supportLoad(__DIR__ . '/db/support.json');
        $unread = tgUserSupportUnreadCount($username);
        $lines = ['پشتیبانی', ''];

        if($unread > 0){
            $lines[] = tgUserFaNum((string)$unread) . ' پیام خوانده‌نشده از ادمین 📨';
            $lines[] = '';
        }

        foreach($data as $ticket){
            if(strcasecmp((string)($ticket['user'] ?? ''), $username) !== 0){
                continue;
            }

            $messages = $ticket['messages'] ?? [];

            if(!is_array($messages) || count($messages) === 0){
                $lines[] = 'هنوز پیامی ارسال نکرده‌اید.';
                break;
            }

            $last = end($messages);

            if(is_array($last)){
                $sender = ($last['sender'] ?? '') === 'admin' ? 'ادمین' : 'شما';
                $preview = trim((string)($last['text'] ?? ''));

                if($preview === '' && trim((string)($last['image'] ?? '')) !== ''){
                    $preview = '[تصویر]';
                }

                $lines[] = 'آخرین پیام (' . $sender . '):';
                $lines[] = '«' . telegramLimitText($preview, 180) . '»';
            }

            break;
        }

        return trim(implode("\n", $lines));
    }

    function tgUserBuildSettingsText($userRow){
        $prefs = tgUserGetNotifyPrefs($userRow);
        $lines = [
            'تنظیمات اعلان‌ها',
            '',
            'وضعیت اتصال: ✅ متصل',
            'حساب: ' . trim((string)($userRow['username'] ?? '')),
        ];

        $tg = trim((string)($userRow['telegram_username'] ?? ''));

        if($tg !== ''){
            $lines[] = 'تلگرام: ' . $tg;
        }

        $lines[] = '';
        $lines[] = 'اعلان‌های فعال:';

        $map = [
            'expiry' => 'انقضای اشتراک',
            'traffic' => 'اتمام حجم',
            'support' => 'پاسخ پشتیبانی',
            'campaign' => 'کمپین و تخفیف',
            'payment' => 'تأیید پرداخت',
        ];

        foreach($map as $key => $label){
            $lines[] = '  ' . (!empty($prefs[$key]) ? '✅' : '⬜') . ' ' . $label;
        }

        return implode("\n", $lines);
    }

    function tgUserBuildGuestText($config = null){
        return trim(implode("\n", [
            'سلام 👋',
            '',
            'برای استفاده از ربات، ابتدا از داشبورد پنل',
            'حساب تلگرام خود را متصل کنید.',
            '',
            tgUserPanelUrl($config),
        ]));
    }

    function tgUserFindSubByLabel($subs, $label){
        $label = trim((string)$label);

        foreach($subs as $sub){
            if(tgUserLimitButtonLabel(tgUserSubDisplayName($sub)) === $label){
                return $sub;
            }

            if(tgUserSubDisplayName($sub) === $label){
                return $sub;
            }
        }

        return null;
    }

    function tgUserToggleNotifyPref($username, $key, $value = null){
        $users = profileLoadUsers();
        $index = profileFindUserIndex($users, $username);

        if($index < 0){
            return false;
        }

        $prefs = tgUserGetNotifyPrefs($users[$index]);

        if($key === 'all_off'){
            foreach(array_keys($prefs) as $prefKey){
                $prefs[$prefKey] = false;
            }
        }
        elseif(array_key_exists($key, $prefs)){
            $prefs[$key] = $value === null ? !$prefs[$key] : (bool)$value;
        }
        else{
            return false;
        }

        $users[$index]['telegram_notify'] = $prefs;
        profileSaveUsers($users);

        return $prefs;
    }

    function tgUserAddSupportMessage($username, $text){
        if(!function_exists('supportLoad')){
            require_once __DIR__ . '/support_lib.php';
        }

        $text = trim((string)$text);
        $username = trim((string)$username);

        if($username === '' || $text === ''){
            return ['ok' => false, 'error' => 'متن پیام خالی است'];
        }

        $file = __DIR__ . '/db/support.json';
        $data = supportLoad($file);
        $ticketIndex = supportEnsureTicket($data, $username);

        if($ticketIndex < 0){
            return ['ok' => false, 'error' => 'ثبت تیکت ناموفق بود'];
        }

        $meta = supportMessageMeta();
        $row = [
            'id' => uniqid(),
            'sender' => 'user',
            'text' => $text,
            'image' => '',
            'date' => $meta['date'],
            'time' => $meta['time'],
            'timestamp' => $meta['timestamp'],
            'seen_by_admin' => false,
        ];

        if(!isset($data[$ticketIndex]['messages']) || !is_array($data[$ticketIndex]['messages'])){
            $data[$ticketIndex]['messages'] = [];
        }

        $data[$ticketIndex]['messages'][] = $row;
        $data[$ticketIndex]['status'] = 'open';
        supportSave($file, $data);

        if(function_exists('supportNotifyTelegramAdmins')){
            supportNotifyTelegramAdmins($username, $row);
        }

        return ['ok' => true, 'message' => $row];
    }

    function tgUserShowUnreadSupport($username, $chatId, $config = null){
        if(!function_exists('supportLoad')){
            require_once __DIR__ . '/support_lib.php';
        }

        $file = __DIR__ . '/db/support.json';
        $data = supportLoad($file);
        $lines = [];
        $changed = false;

        foreach($data as $i => $ticket){
            if(strcasecmp((string)($ticket['user'] ?? ''), $username) !== 0){
                continue;
            }

            foreach(($ticket['messages'] ?? []) as $j => $msg){
                if(($msg['sender'] ?? '') !== 'admin' || !empty($msg['seen_by_user'])){
                    continue;
                }

                $text = trim((string)($msg['text'] ?? ''));

                if($text === '' && trim((string)($msg['image'] ?? '')) !== ''){
                    $text = '[تصویر]';
                }

                $lines[] = 'ادمین: «' . telegramLimitText($text, 300) . '»';
                $data[$i]['messages'][$j]['seen_by_user'] = true;
                $changed = true;
            }

            break;
        }

        if($changed){
            supportSave($file, $data);
        }

        if(count($lines) === 0){
            tgUserSendKeyboardMessage($chatId, 'پیام خوانده‌نشده‌ای وجود ندارد.', tgUserSupportKeyboard(), $config);
            return;
        }

        tgUserSendKeyboardMessage($chatId, implode("\n\n", $lines), tgUserSupportKeyboard(), $config);
    }

    function tgUserHandleStart($chatId, $args, $from, $config = null, $message = null){
        if(is_array($message)){
            tgUserClearUserInputMessage($chatId, $message, $config);
        }

        if(preg_match('/^link[_-]([a-f0-9]+)$/i', (string)$args, $m)){
            $result = tgUserConsumeLinkToken($m[1], $chatId, is_array($from) ? $from : []);

            if(empty($result['ok'])){
                tgUserSendKeyboardMessage($chatId, ($result['error'] ?? 'اتصال ناموفق بود') . '.', tgUserReplyMarkup([[tgUserBtnPanel()]]), $config);
                return;
            }

            $username = trim((string)($result['username'] ?? ''));
            tgUserSetSession($chatId, ['screen' => 'home', 'username' => $username, 'mode' => '']);
            tgUserSyncAnnouncementsForChat($chatId, $username, $config);
            tgUserSendKeyboardMessage($chatId, tgUserBuildConnectWelcomeText($username), tgUserMainKeyboard(), $config);
            return;
        }

        if(telegramCanUseBot($chatId, $config)){
            telegramHandleAdminText($chatId, '/start', $config);
            return;
        }

        $user = tgUserFindByChatId($chatId);

        if($user){
            tgUserSetSession($chatId, ['screen' => 'home', 'username' => $user['username'], 'mode' => '']);
            tgUserSyncAnnouncementsForChat($chatId, $user['username'], $config);
            tgUserSendKeyboardMessage($chatId, tgUserBuildHomeText($user['username']), tgUserMainKeyboard(), $config);
            return;
        }

        tgUserSendKeyboardMessage($chatId, tgUserBuildGuestText($config), tgUserReplyMarkup([[tgUserBtnPanel()]]), $config);
    }

    function tgUserHandleSettingsButton($username, $chatId, $text, $config = null){
        $user = null;

        foreach(profileLoadUsers() as $row){
            if(strcasecmp((string)($row['username'] ?? ''), $username) === 0){
                $user = $row;
                break;
            }
        }

        if(!$user){
            return false;
        }

        $prefs = tgUserGetNotifyPrefs($user);

        if($text === '🔌 قطع اتصال تلگرام'){
            tgUserDisconnect($username);
            tgUserPurgeAnnouncementMessagesForChat($chatId, $config);
            tgUserClearMenu($chatId, $config);
            tgUserSetSession($chatId, []);
            tgUserSendKeyboardMessage(
                $chatId,
                "اتصال تلگرام قطع شد. برای اتصال مجدد از داشبورد پنل اقدام کنید.\n\n" . tgUserBuildGuestText($config),
                tgUserReplyMarkup([[tgUserBtnPanel()]]),
                $config
            );
            return true;
        }

        if($text === '🔕 خاموش کردن همه'){
            tgUserToggleNotifyPref($username, 'all_off', false);
        }
        elseif(strpos($text, '⏳ انقضا:') === 0){
            tgUserToggleNotifyPref($username, 'expiry');
        }
        elseif(strpos($text, '📦 حجم:') === 0){
            tgUserToggleNotifyPref($username, 'traffic');
        }
        elseif(strpos($text, '💬 پشتیبانی:') === 0){
            tgUserToggleNotifyPref($username, 'support');
        }
        elseif(strpos($text, '🎁 کمپین:') === 0){
            tgUserToggleNotifyPref($username, 'campaign');
        }
        elseif(strpos($text, '💳 پرداخت:') === 0){
            tgUserToggleNotifyPref($username, 'payment');
        }
        else{
            return false;
        }

        foreach(profileLoadUsers() as $row){
            if(strcasecmp((string)($row['username'] ?? ''), $username) === 0){
                $user = $row;
                break;
            }
        }

        $prefs = tgUserGetNotifyPrefs($user);
        tgUserSyncAnnouncementsForChat($chatId, $username, $config);
        tgUserSendKeyboardMessage($chatId, tgUserBuildSettingsText($user), tgUserSettingsKeyboard($prefs), $config);
        return true;
    }

    function tgUserHandleMessage($message, $config = null){
        $chatId = (string)($message['chat']['id'] ?? '');
        $text = trim((string)($message['text'] ?? ''));
        $from = is_array($message['from'] ?? null) ? $message['from'] : [];

        if($chatId === ''){
            return;
        }

        if($text === '/start' || strpos($text, '/start ') === 0){
            $args = trim(substr($text, 6));
            tgUserHandleStart($chatId, $args, $from, $config, $message);
            return;
        }

        if(telegramCanUseBot($chatId, $config)){
            if($text !== ''){
                telegramHandleAdminText($chatId, $text, $config);
            }
            return;
        }

        $user = tgUserFindByChatId($chatId);

        if(!$user){
            if($text !== ''){
                tgUserClearUserInputMessage($chatId, $message, $config);
                tgUserSendKeyboardMessage($chatId, tgUserBuildGuestText($config), tgUserReplyMarkup([[tgUserBtnPanel()]]), $config);
            }
            return;
        }

        $username = trim((string)($user['username'] ?? ''));
        $session = tgUserGetSession($chatId);
        $mode = trim((string)($session['mode'] ?? ''));

        if($mode === 'loading'){
            return;
        }

        if(tgUserShouldClearUserInputMessage($text, $session)){
            tgUserClearUserInputMessage($chatId, $message, $config);
        }

        if($text === '/status'){
            tgUserSendKeyboardMessage($chatId, tgUserBuildHomeText($username), tgUserMainKeyboard(), $config);
            return;
        }

        if($text === '/support' || $text === tgUserBtnSupport()){
            tgUserSetSession($chatId, ['screen' => 'support', 'mode' => '']);
            tgUserSendKeyboardMessage($chatId, tgUserBuildSupportText($username), tgUserSupportKeyboard(), $config);
            return;
        }

        if($text === tgUserBtnSettings()){
            tgUserSetSession($chatId, ['screen' => 'settings', 'mode' => '']);
            tgUserSendKeyboardMessage($chatId, tgUserBuildSettingsText($user), tgUserSettingsKeyboard(tgUserGetNotifyPrefs($user)), $config);
            return;
        }

        if($text === tgUserBtnNotify()){
            tgUserSetSession($chatId, ['screen' => 'home', 'mode' => '']);
            tgUserSendKeyboardMessage($chatId, "اعلان‌های مهم به‌صورت خودکار ارسال می‌شوند.\n\nبرای خاموش یا روشن کردن هر نوع اعلان، از «تنظیمات» استفاده کنید.", tgUserMainKeyboard(), $config);
            return;
        }

        if($text === tgUserBtnPanel()){
            tgUserSendKeyboardMessage($chatId, 'ورود به پنل:' . "\n" . tgUserPanelUrl($config), tgUserMainKeyboard(), $config);
            return;
        }

        if($text === tgUserBtnBack()){
            tgUserSetSession($chatId, ['screen' => 'home', 'mode' => '']);
            tgUserSendKeyboardMessage($chatId, tgUserBuildHomeText($username), tgUserMainKeyboard(), $config);
            return;
        }

        if($text === tgUserBtnSubs()){
            $subs = tgUserLoadSubsBundle($username, ['skip_usage' => true]);
            tgUserSetSession($chatId, ['screen' => 'subs', 'mode' => '', 'subs_cache' => array_map(function($sub){
                return [
                    'link' => $sub['link'] ?? '',
                    'name' => tgUserSubDisplayName($sub),
                ];
            }, $subs)]);
            tgUserSendKeyboardMessage($chatId, tgUserBuildSubsListText($subs), tgUserSubsKeyboard($subs), $config);
            return;
        }

        if(($session['screen'] ?? '') === 'settings' && tgUserHandleSettingsButton($username, $chatId, $text, $config)){
            return;
        }

        if($text === '📩 مشاهده پیام‌های جدید'){
            tgUserShowUnreadSupport($username, $chatId, $config);
            return;
        }

        if($text === '✏️ ارسال پیام جدید'){
            tgUserSetSession($chatId, ['screen' => 'support', 'mode' => 'write']);
            tgUserSendKeyboardMessage($chatId, 'پیام خود را بنویسید و ارسال کنید.' . "\n\n" . 'برای بازگشت از «بازگشت» استفاده کنید.', tgUserBackKeyboard(), $config);
            return;
        }

        if($mode === 'write'){
            if($text === tgUserBtnBack()){
                tgUserSetSession($chatId, ['screen' => 'support', 'mode' => '']);
                tgUserSendKeyboardMessage($chatId, tgUserBuildSupportText($username), tgUserSupportKeyboard(), $config);
                return;
            }

            $result = tgUserAddSupportMessage($username, $text);

            if(empty($result['ok'])){
                tgUserSendKeyboardMessage($chatId, 'ارسال پیام ناموفق بود: ' . ($result['error'] ?? 'خطا'), tgUserBackKeyboard(), $config);
                return;
            }

            tgUserSetSession($chatId, ['screen' => 'support', 'mode' => '']);
            tgUserSendKeyboardMessage($chatId, "پیام شما ثبت شد. ✅\n\n" . tgUserBuildSupportText($username), tgUserSupportKeyboard(), $config);
            return;
        }

        if(($session['screen'] ?? '') === 'subs'){
            $subs = tgUserLoadSubsBundle($username, ['skip_usage' => true]);
            $sub = tgUserFindSubByLabel($subs, $text);

            if($sub){
                tgUserShowSubDetail($chatId, $sub, $config);
                return;
            }
        }

        if($text !== ''){
            tgUserSendKeyboardMessage($chatId, 'گزینه نامعتبر است. از منوی پایین استفاده کنید.', tgUserMainKeyboard(), $config);
        }
    }

    function tgUserGetChatId($username){
        $users = profileLoadUsers();
        $index = profileFindUserIndex($users, $username);

        if($index < 0){
            return '';
        }

        return trim((string)($users[$index]['telegram_chat_id'] ?? ''));
    }

    function tgUserNotifyIfEnabled($username, $type, $text, $config = null){
        $users = profileLoadUsers();
        $index = profileFindUserIndex($users, $username);

        if($index < 0){
            return ['ok' => false, 'error' => 'کاربر پیدا نشد'];
        }

        $row = $users[$index];
        $chatId = trim((string)($row['telegram_chat_id'] ?? ''));

        if($chatId === ''){
            return ['ok' => false, 'error' => 'تلگرام متصل نیست'];
        }

        $prefs = tgUserGetNotifyPrefs($row);

        if($type !== '' && empty($prefs[$type])){
            return ['ok' => false, 'error' => 'اعلان غیرفعال است'];
        }

        if($config === null){
            $config = telegramLoadConfig();
        }

        if(empty($config['enabled']) || trim((string)($config['bot_token'] ?? '')) === ''){
            return ['ok' => false, 'error' => 'ربات غیرفعال است'];
        }

        return telegramSendMessage($chatId, $text, [], $config);
    }

    function tgUserNotifySupportReply($username, $messageText){
        $preview = trim((string)$messageText);

        if($preview === ''){
            return;
        }

        $text = implode("\n", [
            'ادمین به تیکت شما پاسخ داد: 💬',
            '',
            '«' . telegramLimitText($preview, 500) . '»',
        ]);

        tgUserNotifyIfEnabled($username, 'support', $text);
    }

    function tgUserNotifyPaymentApproved($username, $subName, $planText = '', $isRenew = false){
        $subName = trim((string)$subName);
        $planText = trim((string)$planText);
        $lines = [
            'پرداخت شما تأیید شد. ✅',
            '',
            'اشتراک: ' . ($subName !== '' ? $subName : '—'),
        ];

        if($planText !== ''){
            $lines[] = 'پلن: ' . $planText;
        }

        if($isRenew){
            $lines[] = '';
            $lines[] = 'تمدید با موفقیت انجام شد.';
        }

        tgUserNotifyIfEnabled($username, 'payment', implode("\n", $lines));
    }

    function tgUserNotifyCampaign($title, $message, $row = null){
        if(is_array($row)){
            tgUserPublishAnnouncement($row);
            return;
        }

        tgUserCampaignLibReady();
        $rows = campaignAnnouncementsLoad();
        $needleTitle = trim((string)$title);
        $needleMessage = trim((string)$message);
        $match = null;

        foreach($rows as $item){
            if(
                trim((string)($item['title'] ?? '')) === $needleTitle
                && trim((string)($item['message'] ?? '')) === $needleMessage
            ){
                $match = $item;
                break;
            }
        }

        if(is_array($match)){
            tgUserPublishAnnouncement($match);
        }
    }

    function tgUserLoadNotifyState(){
        $path = tgUserNotifyStatePath();

        if(!file_exists($path)){
            return [];
        }

        $data = json_decode((string)file_get_contents($path), true);
        return is_array($data) ? $data : [];
    }

    function tgUserSaveNotifyState($state){
        $path = tgUserNotifyStatePath();
        $dir = dirname($path);

        if(!is_dir($dir)){
            @mkdir($dir, 0775, true);
        }

        file_put_contents($path, json_encode($state, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
    }

    function tgUserShouldNotify($username, $subKey, $eventKey, $cooldownSeconds = 86400){
        $state = tgUserLoadNotifyState();
        $userKey = strtolower(trim((string)$username));
        $last = intval($state[$userKey][$subKey][$eventKey] ?? 0);

        if($last > 0 && (time() - $last) < $cooldownSeconds){
            return false;
        }

        $state[$userKey][$subKey][$eventKey] = time();
        tgUserSaveNotifyState($state);
        return true;
    }

    function tgUserProcessExpiryNotifications($config = null){
        if($config === null){
            $config = telegramLoadConfig();
        }

        $expireDays = max(1, intval($config['notify_expire_days'] ?? 3));
        $trafficPct = max(1, min(100, intval($config['notify_traffic_pct'] ?? 20)));

        foreach(profileLoadUsers() as $user){
            $username = trim((string)($user['username'] ?? ''));

            if($username === '' || trim((string)($user['telegram_chat_id'] ?? '')) === ''){
                continue;
            }

            $subs = tgUserLoadSubsBundle($username);

            foreach($subs as $sub){
                $name = tgUserSubDisplayName($sub);
                $usage = is_array($sub['usage'] ?? null) ? $sub['usage'] : [];
                $time = is_array($usage['time'] ?? null) ? $usage['time'] : [];
                $vol = is_array($usage['volume'] ?? null) ? $usage['volume'] : [];

                if(empty($time['unlimited'])){
                    $remainSeconds = max(0, intval($time['remain_seconds'] ?? 0));
                    $remainDays = max(0, (int)ceil($remainSeconds / 86400));

                    if($remainDays > 0 && $remainDays <= $expireDays){
                        $eventKey = 'expiry_' . $remainDays;

                        if(tgUserShouldNotify($username, $name, $eventKey)){
                            $text = 'اشتراک ' . $name . ' شما ' . tgUserFaNum((string)$remainDays) . ' روز دیگر منقضی می‌شود. ⚠️';
                            tgUserNotifyIfEnabled($username, 'expiry', $text, $config);
                        }
                    }
                    elseif($remainSeconds <= 0){
                        if(tgUserShouldNotify($username, $name, 'expired', 604800)){
                            $text = 'اشتراک ' . $name . ' منقضی شد. 🔴';
                            tgUserNotifyIfEnabled($username, 'expiry', $text, $config);
                        }
                    }
                }

                if(empty($vol['unlimited'])){
                    $remainPct = floatval($vol['remain_pct'] ?? 100);
                    $usedPct = max(0, min(100, 100 - $remainPct));

                    if($usedPct >= (100 - $trafficPct) && $remainPct > 0){
                        $threshold = (int)round($usedPct / 10) * 10;
                        $eventKey = 'traffic_' . $threshold;

                        if(tgUserShouldNotify($username, $name, $eventKey)){
                            $text = tgUserFaNum((string)(int)round($usedPct)) . '٪ حجم اشتراک ' . $name . ' مصرف شده. برای تمدید اقدام کنید. 📦';
                            tgUserNotifyIfEnabled($username, 'traffic', $text, $config);
                        }
                    }
                    elseif($remainPct <= 0){
                        if(tgUserShouldNotify($username, $name, 'traffic_done', 604800)){
                            $text = 'حجم اشتراک ' . $name . ' به پایان رسید. 📦';
                            tgUserNotifyIfEnabled($username, 'traffic', $text, $config);
                        }
                    }
                }
            }
        }
    }

}
