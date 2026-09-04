<?php

require_once __DIR__ . '/pnv_date_bootstrap.php';

if(!function_exists('supportLoad')){

    function supportIsEmbeddedRequest(){

        $page = (string)($_GET['page'] ?? '');

        return basename($_SERVER['SCRIPT_NAME'] ?? '') === 'index.php'
            && in_array($page, ['support', 'support-v2'], true);

    }

    function supportLoad($file){

        if(!file_exists($file)){
            supportSave($file, []);
            return [];
        }

        $fp = fopen($file, 'c+');

        if(!$fp){
            return [];
        }

        flock($fp, LOCK_SH);

        $content = stream_get_contents($fp);

        flock($fp, LOCK_UN);
        fclose($fp);

        $data = json_decode($content, true);

        return is_array($data) ? $data : [];

    }

    function supportSave($file, $data){

        $dir = dirname($file);

        if(!is_dir($dir)){
            mkdir($dir, 0755, true);
        }

        $json = json_encode(
            $data,
            JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
        );

        $fp = fopen($file, 'c+');

        if(!$fp){
            return false;
        }

        flock($fp, LOCK_EX);
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, $json);
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);

        return true;

    }

    function supportCsrfToken(){

        if(empty($_SESSION['support_csrf'])){
            $_SESSION['support_csrf'] = bin2hex(random_bytes(16));
        }

        return $_SESSION['support_csrf'];

    }

    function supportCsrfVerify($token){

        return isset($_SESSION['support_csrf'])
            && is_string($token)
            && hash_equals($_SESSION['support_csrf'], $token);

    }

    function supportCsrfField(){

        $token = htmlspecialchars(supportCsrfToken(), ENT_QUOTES, 'UTF-8');

        return '<input type="hidden" name="csrf" value="'.$token.'">';

    }

    function supportEnsureTehranTimezone(){
        pnvEnsureTehranTimezone();
    }

    function supportGregorianToJalali($gy, $gm, $gd){
        return pnvGregorianToJalali($gy, $gm, $gd);
    }

    function supportFormatFromTimestamp($timestamp){
        $timestamp = intval($timestamp);

        if($timestamp <= 0){
            return [
                'date' => '-',
                'time' => '-'
            ];
        }

        return [
            'date' => pnvFormatJalaliDate($timestamp, '/'),
            'time' => pnvFormatTehranTime($timestamp, false)
        ];
    }

    function supportMessageMeta($timestamp = null){

        supportEnsureTehranTimezone();

        if($timestamp === null){
            $timestamp = time();
        }

        $formatted = supportFormatFromTimestamp($timestamp);

        return [
            'date' => $formatted['date'],
            'time' => $formatted['time'],
            'timestamp' => intval($timestamp)
        ];

    }

    function supportMessageDisplayTime($message){

        $timestamp = intval($message['timestamp'] ?? 0);

        if($timestamp > 0){
            return supportFormatFromTimestamp($timestamp);
        }

        return [
            'date' => $message['date'] ?? '-',
            'time' => $message['time'] ?? '-'
        ];

    }

    function supportIsAjaxRequest(){

        if(!empty($_POST['support_ajax']) || !empty($_GET['support_ajax'])){
            return true;
        }

        $hdr = $_SERVER['HTTP_X_SUPPORT_AJAX'] ?? '';

        return $hdr === '1' || strtolower($hdr) === 'true';
    }

    function supportAjaxRespond($payload, $httpCode = 200){

        supportApiRespond($payload, $httpCode);

    }

    function supportApiRespond($payload, $httpCode = 200){

        http_response_code($httpCode);
        header('Content-Type: application/json; charset=utf-8');

        $flags = JSON_UNESCAPED_UNICODE;

        if(defined('JSON_INVALID_UTF8_SUBSTITUTE')){
            $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
        }

        $json = json_encode($payload, $flags);

        if($json === false){
            http_response_code(500);
            $json = json_encode([
                'error' => 'json_encode failed',
                'detail' => json_last_error_msg(),
            ], JSON_UNESCAPED_UNICODE);
        }

        echo $json;
        exit;

    }

    function supportMessageDayKey($message){

        $timestamp = intval($message['timestamp'] ?? 0);

        if($timestamp <= 0){
            return 'unknown';
        }

        supportEnsureTehranTimezone();

        return date('Y-m-d', $timestamp);
    }

    function supportDaySeparatorLabel($message){

        $timestamp = intval($message['timestamp'] ?? 0);

        if($timestamp <= 0){
            return '—';
        }

        supportEnsureTehranTimezone();
        $key = date('Y-m-d', $timestamp);
        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));

        if($key === $today){
            return 'امروز';
        }

        if($key === $yesterday){
            return 'دیروز';
        }

        return supportFormatFromTimestamp($timestamp)['date'];
    }

    function supportRenderDaySeparator($message){

        $label = supportDaySeparatorLabel($message);

        return '<div class="msgDaySep" data-day-key="' . htmlspecialchars(supportMessageDayKey($message), ENT_QUOTES, 'UTF-8') . '"><span>'
            . htmlspecialchars($label, ENT_QUOTES, 'UTF-8')
            . '</span></div>';
    }

    function supportMessageClusterPos($messages, $index){

        if(!isset($messages[$index]) || !is_array($messages[$index])){
            return 'single';
        }

        $cur = $messages[$index];
        $curSender = (string)($cur['sender'] ?? '');
        $curTs = intval($cur['timestamp'] ?? 0);
        $curDay = supportMessageDayKey($cur);
        $prev = $index > 0 ? $messages[$index - 1] : null;
        $next = ($index + 1) < count($messages) ? $messages[$index + 1] : null;

        $samePrev = is_array($prev)
            && (string)($prev['sender'] ?? '') === $curSender
            && supportMessageDayKey($prev) === $curDay
            && ($curTs - intval($prev['timestamp'] ?? 0)) <= 600;

        $sameNext = is_array($next)
            && (string)($next['sender'] ?? '') === $curSender
            && supportMessageDayKey($next) === $curDay
            && (intval($next['timestamp'] ?? 0) - $curTs) <= 600;

        if($samePrev && $sameNext){
            return 'mid';
        }

        if($samePrev){
            return 'bot';
        }

        if($sameNext){
            return 'top';
        }

        return 'single';
    }

    function supportMessageIsOwn($message, $options){

        $sender = (string)($message['sender'] ?? '');
        $isAdmin = !empty($options['isAdmin']);

        if($isAdmin){
            return $sender === 'admin';
        }

        return $sender === 'user';
    }

    function supportRenderReadTicks($message, $options){

        if(!supportMessageIsOwn($message, $options)){
            return '';
        }

        $isAdmin = !empty($options['isAdmin']);
        $seen = $isAdmin
            ? !empty($message['seen_by_user'])
            : !empty($message['seen_by_admin']);

        $class = $seen ? 'msgTicks msgTicks--read' : 'msgTicks';

        return '<span class="' . $class . '" aria-hidden="true">'
            . ($seen ? '✓✓' : '✓')
            . '</span>';
    }

    function supportRenderMessagesList($messages, $options){

        if(!is_array($messages) || count($messages) === 0){
            return '';
        }

        $html = '';
        $lastDay = '';

        foreach($messages as $i => $m){

            if(!is_array($m)){
                continue;
            }

            $dayKey = supportMessageDayKey($m);

            if($dayKey !== $lastDay){
                $html .= supportRenderDaySeparator($m);
                $lastDay = $dayKey;
            }

            $msgOptions = $options;
            $msgOptions['cluster'] = supportMessageClusterPos($messages, $i);
            $rowClass = supportMessageIsOwn($m, $options) ? 'msgRow msgRow--own' : 'msgRow msgRow--other';

            $html .= '<div class="' . $rowClass . '">';
            $html .= supportRenderMessageHtml($m, $msgOptions);
            $html .= '</div>';
        }

        return $html;
    }

    function supportMessageForApi($message, $options = []){

        $display = supportMessageDisplayTime($message);
        $image = $message['image'] ?? '';

        if($image !== ''){
            $image = '/' . ltrim($image, '/');
        }

        $audio = $message['audio'] ?? '';

        if($audio !== ''){
            $audio = '/' . ltrim($audio, '/');
        }

        return [
            'id' => $message['id'] ?? '',
            'sender' => $message['sender'] ?? '',
            'text' => $message['text'] ?? '',
            'image' => $image,
            'audio' => $audio,
            'date' => $display['date'],
            'time' => $display['time'],
            'timestamp' => intval($message['timestamp'] ?? 0),
            'edited' => !empty($message['edited']),
            'reply_to' => is_array($message['reply_to'] ?? null) ? $message['reply_to'] : null,
            'seen_by_admin' => !empty($message['seen_by_admin']),
            'seen_by_user' => !empty($message['seen_by_user']),
            'is_own' => supportMessageIsOwn($message, $options),
        ];

    }

    function supportTicketLastMessage($ticket){

        if(empty($ticket['messages'])){
            return null;
        }

        return end($ticket['messages']);

    }

    function supportTicketPreview($ticket){

        $last = supportTicketLastMessage($ticket);

        if(!$last){
            return 'بدون پیام';
        }

        $text = trim($last['text'] ?? '');

        if($text === '' && !empty($last['image'])){
            return '📷 تصویر';
        }

        if($text === '' && !empty($last['audio'])){
            return '🎤 پیام صوتی';
        }

        if($text === ''){
            return 'پیام';
        }

        if(function_exists('mb_strlen') && mb_strlen($text) > 48){
            return mb_substr($text, 0, 48) . '…';
        }

        if(strlen($text) > 48){
            return substr($text, 0, 48) . '…';
        }

        return $text;

    }

    function supportTicketLastTimestamp($ticket){

        $last = supportTicketLastMessage($ticket);

        return intval($last['timestamp'] ?? 0);

    }

    function supportRelativeTime($timestamp){

        $timestamp = intval($timestamp);

        if($timestamp <= 0){
            return '';
        }

        $diff = time() - $timestamp;

        if($diff < 60){
            return 'همین الان';
        }

        if($diff < 3600){
            return intval($diff / 60) . ' دقیقه پیش';
        }

        if($diff < 86400){
            return intval($diff / 3600) . ' ساعت پیش';
        }

        $display = supportMessageDisplayTime(['timestamp' => $timestamp]);

        return $display['date'];

    }

    function supportAdminUnreadCount($ticket){

        if(empty($ticket['messages'])){
            return 0;
        }

        $count = 0;

        foreach($ticket['messages'] as $msg){

            if(
                ($msg['sender'] ?? '') === 'user'
                && empty($msg['seen_by_admin'])
            ){
                $count++;
            }

        }

        return $count;

    }

    function supportUserInitial($username){

        $username = trim($username);

        if($username === ''){
            return '?';
        }

        return mb_substr($username, 0, 1);

    }

    function supportGetUserProfileSummary($username){

        $username = supportNormalizeUsername($username);
        $summary = [
            'username' => $username,
            'mobile' => '-',
            'exists' => false,
        ];

        if($username === ''){
            return $summary;
        }

        $usersFile = __DIR__ . '/db/users.json';

        if(!is_file($usersFile)){
            return $summary;
        }

        $users = json_decode((string)file_get_contents($usersFile), true);

        if(!is_array($users)){
            return $summary;
        }

        foreach($users as $user){

            if(!is_array($user)){
                continue;
            }

            $stored = supportNormalizeUsername($user['username'] ?? '');

            if($stored === '' || !supportUsernamesMatch($stored, $username)){
                continue;
            }

            $summary['username'] = $stored;
            $summary['mobile'] = trim((string)($user['mobile'] ?? '')) ?: '-';
            $summary['exists'] = true;
            break;

        }

        return $summary;

    }

    function supportSortTickets($data){

        usort($data, function($a, $b){

            $aTime = 0;
            $bTime = 0;

            if(!empty($a['messages'])){
                $lastA = end($a['messages']);
                $aTime = $lastA['timestamp'] ?? 0;
            }

            if(!empty($b['messages'])){
                $lastB = end($b['messages']);
                $bTime = $lastB['timestamp'] ?? 0;
            }

            return $bTime - $aTime;

        });

        return $data;

    }

    function supportTicketHasUnreadForAdmin($ticket){

        if(empty($ticket['messages'])){
            return false;
        }

        foreach($ticket['messages'] as $msg){

            if(
                ($msg['sender'] ?? '') === 'user'
                && empty($msg['seen_by_admin'])
            ){
                return true;
            }

        }

        return false;

    }

    function supportMarkSeenByAdmin(&$data, $username){

        $changed = false;

        foreach($data as $i => $ticket){

            if(!supportUsernamesMatch($ticket['user'] ?? '', $username)){
                continue;
            }

            if(empty($ticket['messages'])){
                continue;
            }

            foreach($ticket['messages'] as $j => $msg){

                if(
                    ($msg['sender'] ?? '') === 'user'
                    && empty($msg['seen_by_admin'])
                ){
                    $data[$i]['messages'][$j]['seen_by_admin'] = true;
                    $changed = true;
                }

            }

        }

        return $changed;

    }

    function supportMarkSeenByUser(&$data, $username){

        $changed = false;

        foreach($data as $i => $ticket){

            if(!supportUsernamesMatch($ticket['user'] ?? '', $username)){
                continue;
            }

            if(empty($ticket['messages'])){
                continue;
            }

            foreach($ticket['messages'] as $j => $msg){

                if(
                    ($msg['sender'] ?? '') === 'admin'
                    && ($msg['seen_by_user'] ?? false) !== true
                ){
                    $data[$i]['messages'][$j]['seen_by_user'] = true;
                    $changed = true;
                }

            }

        }

        return $changed;

    }

    function supportHandleUpload($fileInput, $uploadDir, $urlPrefix){

        if(!is_array($fileInput) || !isset($fileInput['error'])){
            return ['ok' => false, 'path' => '', 'error' => ''];
        }

        if(intval($fileInput['error']) === UPLOAD_ERR_NO_FILE || intval($fileInput['size'] ?? 0) <= 0){
            return ['ok' => false, 'path' => '', 'error' => ''];
        }

        if(intval($fileInput['error']) !== UPLOAD_ERR_OK){
            return ['ok' => false, 'path' => '', 'error' => 'آپلود تصویر ناموفق بود (خطای سرور)'];
        }

        $maxBytes = 5 * 1024 * 1024;
        $phpMax = supportParseIniBytes(ini_get('upload_max_filesize'));

        if($phpMax > 0 && $phpMax < $maxBytes){
            $maxBytes = $phpMax;
        }

        if(intval($fileInput['size']) > $maxBytes){
            return ['ok' => false, 'path' => '', 'error' => 'حجم تصویر نباید بیشتر از ' . round($maxBytes / 1024 / 1024, 1) . 'MB باشد'];
        }

        $ext = strtolower(pathinfo((string)($fileInput['name'] ?? ''), PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];

        $tmp = (string)($fileInput['tmp_name'] ?? '');
        $imageInfo = ($tmp !== '') ? @getimagesize($tmp) : false;

        if($imageInfo === false){
            return ['ok' => false, 'path' => '', 'error' => 'فایل تصویر معتبر نیست'];
        }

        $typeMap = [
            IMAGETYPE_JPEG => 'jpg',
            IMAGETYPE_PNG => 'png',
            IMAGETYPE_WEBP => 'webp'
        ];
        $detected = $typeMap[$imageInfo[2] ?? 0] ?? '';

        if($detected === ''){
            return ['ok' => false, 'path' => '', 'error' => 'فقط JPG، PNG یا WebP مجاز است'];
        }

        if(!in_array($ext, $allowed, true)){
            $ext = $detected;
        } elseif($ext === 'jpeg'){
            $ext = 'jpg';
        }

        if(!is_dir($uploadDir)){
            if(!@mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)){
                return ['ok' => false, 'path' => '', 'error' => 'پوشه آپلود قابل ایجاد نیست'];
            }
        }

        if(!is_writable($uploadDir)){
            @chmod($uploadDir, 0755);
            if(!is_writable($uploadDir)){
                return ['ok' => false, 'path' => '', 'error' => 'پوشه آپلود قابل نوشتن نیست'];
            }
        }

        $filename = time() . rand(1000, 9999) . '.' . $ext;
        $savePath = rtrim($uploadDir, '/') . '/' . $filename;

        if(!move_uploaded_file($tmp, $savePath)){
            return ['ok' => false, 'path' => '', 'error' => 'ذخیره تصویر روی سرور ناموفق بود'];
        }

        return [
            'ok' => true,
            'path' => rtrim($urlPrefix, '/') . '/' . $filename,
            'error' => ''
        ];

    }

    function supportHandleVoiceUpload($fileInput, $uploadDir, $urlPrefix){

        if(!is_array($fileInput) || !isset($fileInput['error'])){
            return ['ok' => false, 'path' => '', 'error' => ''];
        }

        if(intval($fileInput['error']) === UPLOAD_ERR_NO_FILE || intval($fileInput['size'] ?? 0) <= 0){
            return ['ok' => false, 'path' => '', 'error' => ''];
        }

        if(intval($fileInput['error']) !== UPLOAD_ERR_OK){
            return ['ok' => false, 'path' => '', 'error' => 'آپلود ویس ناموفق بود (خطای سرور)'];
        }

        $maxBytes = 10 * 1024 * 1024;
        $phpMax = supportParseIniBytes(ini_get('upload_max_filesize'));

        if($phpMax > 0 && $phpMax < $maxBytes){
            $maxBytes = $phpMax;
        }

        if(intval($fileInput['size']) > $maxBytes){
            return ['ok' => false, 'path' => '', 'error' => 'حجم ویس نباید بیشتر از ' . round($maxBytes / 1024 / 1024, 1) . 'MB باشد'];
        }

        $ext = strtolower(pathinfo((string)($fileInput['name'] ?? ''), PATHINFO_EXTENSION));
        $allowedExt = ['webm', 'ogg', 'mp3', 'm4a', 'wav', 'mp4'];
        $allowedMime = [
            'audio/webm', 'audio/ogg', 'audio/mpeg', 'audio/mp3', 'audio/mp4',
            'audio/x-m4a', 'audio/wav', 'audio/x-wav', 'video/webm'
        ];

        $tmp = (string)($fileInput['tmp_name'] ?? '');
        $finfo = ($tmp !== '' && function_exists('finfo_open')) ? finfo_open(FILEINFO_MIME_TYPE) : false;
        $mime = ($finfo && $tmp !== '') ? (string)finfo_file($finfo, $tmp) : (string)($fileInput['type'] ?? '');

        if($finfo){
            finfo_close($finfo);
        }

        $mimeOk = false;

        foreach($allowedMime as $allowed){
            if($mime === $allowed || strpos($mime, rtrim($allowed, '*')) === 0){
                $mimeOk = true;
                break;
            }
        }

        if(!$mimeOk && !in_array($ext, $allowedExt, true)){
            return ['ok' => false, 'path' => '', 'error' => 'فرمت ویس پشتیبانی نمی‌شود'];
        }

        if(!in_array($ext, $allowedExt, true)){
            if(strpos($mime, 'ogg') !== false){
                $ext = 'ogg';
            }
            elseif(strpos($mime, 'mpeg') !== false || strpos($mime, 'mp3') !== false){
                $ext = 'mp3';
            }
            elseif(strpos($mime, 'wav') !== false){
                $ext = 'wav';
            }
            else{
                $ext = 'webm';
            }
        }

        if(!is_dir($uploadDir)){
            if(!@mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)){
                return ['ok' => false, 'path' => '', 'error' => 'پوشه آپلود قابل ایجاد نیست'];
            }
        }

        if(!is_writable($uploadDir)){
            @chmod($uploadDir, 0755);
            if(!is_writable($uploadDir)){
                return ['ok' => false, 'path' => '', 'error' => 'پوشه آپلود قابل نوشتن نیست'];
            }
        }

        $filename = 'voice_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
        $savePath = rtrim($uploadDir, '/') . '/' . $filename;

        if(!move_uploaded_file($tmp, $savePath)){
            return ['ok' => false, 'path' => '', 'error' => 'ذخیره ویس روی سرور ناموفق بود'];
        }

        return [
            'ok' => true,
            'path' => rtrim($urlPrefix, '/') . '/' . $filename,
            'error' => ''
        ];

    }

    function supportParseIniBytes($value){

        $value = trim((string)$value);

        if($value === ''){
            return 0;
        }

        $unit = strtolower(substr($value, -1));
        $num = floatval($value);

        if($unit === 'g'){
            return (int)($num * 1024 * 1024 * 1024);
        }

        if($unit === 'm'){
            return (int)($num * 1024 * 1024);
        }

        if($unit === 'k'){
            return (int)($num * 1024);
        }

        return (int)$num;

    }

    function supportUserCanEditMessage($message){
        $timestamp = intval($message['timestamp'] ?? 0);
        return $timestamp > 0 && (time() - $timestamp) <= 900;
    }

    function supportUserCanDeleteMessage($message){
        $timestamp = intval($message['timestamp'] ?? 0);
        return $timestamp > 0 && (time() - $timestamp) <= 300;
    }

    function supportBuildReplyPreview($data, $replyId){

        $replyId = trim((string)$replyId);

        if($replyId === ''){
            return null;
        }

        foreach((array)$data as $ticket){
            foreach(($ticket['messages'] ?? []) as $msg){
                if(($msg['id'] ?? '') === $replyId){
                    $text = trim((string)($msg['text'] ?? ''));

                    if($text === '' && !empty($msg['image'])){
                        $text = '📷 تصویر';
                    }

                    if(function_exists('mb_substr')){
                        $text = mb_substr($text, 0, 80, 'UTF-8');
                    }
                    else{
                        $text = substr($text, 0, 80);
                    }

                    return [
                        'id' => $replyId,
                        'sender' => $msg['sender'] ?? '',
                        'text' => $text
                    ];
                }
            }
        }

        return null;

    }

    function supportDeleteMessage(&$data, $msgId){

        foreach($data as $i => $ticket){

            if(empty($ticket['messages'])){
                continue;
            }

            foreach($ticket['messages'] as $j => $msg){

                if(($msg['id'] ?? '') === $msgId){

                    unset($data[$i]['messages'][$j]);
                    $data[$i]['messages'] = array_values($data[$i]['messages']);
                    return true;

                }

            }

        }

        return false;

    }

    function supportNormalizeUsername($username){

        return trim((string)$username);

    }

    function supportUsernamesMatch($left, $right){

        $left = supportNormalizeUsername($left);
        $right = supportNormalizeUsername($right);

        if($left === '' || $right === ''){
            return false;
        }

        return strcasecmp($left, $right) === 0;

    }

    function supportFindTicketIndex($data, $username){

        foreach($data as $i => $ticket){

            if(supportUsernamesMatch($ticket['user'] ?? '', $username)){
                return $i;
            }

        }

        return -1;

    }

    function supportResolveTicketUsername($data, $username){

        $username = supportNormalizeUsername($username);

        if($username === ''){
            return '';
        }

        $ticketIndex = supportFindTicketIndex($data, $username);

        if($ticketIndex >= 0){
            return supportNormalizeUsername($data[$ticketIndex]['user'] ?? $username);
        }

        return $username;

    }

    function supportEnsureTicket(&$data, $username){

        $username = supportNormalizeUsername($username);

        if($username === ''){
            return -1;
        }

        $ticketIndex = supportFindTicketIndex($data, $username);

        if($ticketIndex >= 0){
            return $ticketIndex;
        }

        $data[] = [
            'id' => 'SUP-' . rand(1000, 9999),
            'user' => $username,
            'status' => 'open',
            'messages' => []
        ];

        return count($data) - 1;

    }

    function supportAdminHasUnread($data){

        if(!is_array($data)){
            return false;
        }

        foreach($data as $ticket){

            if(supportTicketHasUnreadForAdmin($ticket)){
                return true;
            }

        }

        return false;

    }

    function supportAdminUnreadTotal($data){

        if(!is_array($data)){
            return 0;
        }

        $total = 0;

        foreach($data as $ticket){
            $total += supportAdminUnreadCount($ticket);
        }

        return $total;

    }

    function supportSearchUsers($query, $limit = 10){

        $query = trim($query);

        if($query === ''){
            return [];
        }

        $usersFile = __DIR__ . '/db/users.json';

        if(!file_exists($usersFile)){
            return [];
        }

        $users = json_decode(file_get_contents($usersFile), true);

        if(!is_array($users)){
            return [];
        }

        $queryLower = mb_strtolower($query);
        $queryDigits = preg_replace('/\D+/', '', $query);
        $results = [];

        foreach($users as $user){

            if(!is_array($user)){
                continue;
            }

            $username = trim($user['username'] ?? '');
            $mobile = trim($user['mobile'] ?? '');

            if($username === ''){
                continue;
            }

            $match = false;

            if(mb_strpos(mb_strtolower($username), $queryLower) !== false){
                $match = true;
            }

            if(
                !$match
                && $queryDigits !== ''
                && $mobile !== ''
            ){
                $mobileDigits = preg_replace('/\D+/', '', $mobile);

                if(
                    $mobileDigits !== ''
                    && strpos($mobileDigits, $queryDigits) !== false
                ){
                    $match = true;
                }

            }

            if(!$match){
                continue;
            }

            $results[] = [
                'username' => $username,
                'mobile' => $mobile
            ];

            if(count($results) >= $limit){
                break;
            }

        }

        return $results;

    }

    function supportAdminUrl($user = '', $embedded = false){

        if(!$embedded && supportIsEmbeddedRequest()){
            $embedded = true;
        }

        if($embedded){

            if(function_exists('pnvAdminUrl')){
                $url = pnvAdminUrl('index.php?page=support');
            }
            else{
                $url = 'index.php?page=support';
            }

            if($user !== ''){
                $url .= (strpos($url, '?') !== false ? '&' : '?') . 'user=' . urlencode($user);
            }

            return $url;

        }

        $url = 'support.php';

        if($user !== ''){
            $url .= '?user=' . urlencode($user);
        }

        return $url;

    }

    function supportRenderMessageHtml($m, $options){

        $sender = $m['sender'] ?? 'user';
        $class = ($sender === 'admin') ? 'is-admin admin' : 'is-user usermsg';
        $cluster = trim((string)($options['cluster'] ?? 'single'));

        if($cluster !== '' && $cluster !== 'single'){
            $class .= ' cluster-' . $cluster;
        }
        $currentUser = $options['currentUser'] ?? '';
        $embedded = !empty($options['embedded']);
        $csrfField = $options['csrfField'] ?? '';
        $editId = $options['editId'] ?? '';
        $isAdmin = !empty($options['isAdmin']);
        $baseUrl = $options['baseUrl'] ?? supportAdminUrl($currentUser, $embedded);
        $canEdit = false;
        $canDelete = false;

        if($isAdmin){
            $canEdit = true;
            $canDelete = true;
            $canReply = ($sender !== 'admin');
            $isOwn = ($sender === 'admin');
        }
        elseif(
            $sender === 'user'
            && !empty($options['ownUsername'])
        ){
            $isOwn = true;
            $canEdit = supportUserCanEditMessage($m);
            $canDelete = supportUserCanDeleteMessage($m);
            $canReply = false;
        }
        else{
            $isOwn = false;
            $canEdit = false;
            $canDelete = false;
            $canReply = !empty($options['ownUsername']);
        }

        $image = $m['image'] ?? '';

        if($image !== ''){
            $image = '/' . ltrim($image, '/');
        }

        $display = supportMessageDisplayTime($m);
        $replyTo = is_array($m['reply_to'] ?? null) ? $m['reply_to'] : null;
        $plainText = (string)($m['text'] ?? '');

        ob_start();
        ?>

        <div
            class="msgBubble msg <?php echo $class; ?>"
            data-msg-id="<?php echo htmlspecialchars($m['id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
            data-timestamp="<?php echo intval($m['timestamp'] ?? 0); ?>"
            data-sender="<?php echo htmlspecialchars($sender, ENT_QUOTES, 'UTF-8'); ?>"
            data-own="<?php echo $isOwn ? '1' : '0'; ?>"
            data-can-edit="<?php echo $canEdit ? '1' : '0'; ?>"
            data-can-delete="<?php echo $canDelete ? '1' : '0'; ?>"
            data-can-reply="<?php echo $canReply ? '1' : '0'; ?>"
            data-text="<?php echo htmlspecialchars($plainText, ENT_QUOTES, 'UTF-8'); ?>"
        >

            <?php if($replyTo){ ?>
            <div class="msgQuote">
                <strong><?php echo (($replyTo['sender'] ?? '') === 'admin') ? 'پشتیبانی' : 'کاربر'; ?></strong>
                <span><?php echo htmlspecialchars($replyTo['text'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <?php } ?>

            <?php if($plainText !== ''){ ?>
            <div class="msgText"><?php echo nl2br(htmlspecialchars($plainText, ENT_QUOTES, 'UTF-8')); ?></div>
            <?php } ?>

            <?php if(!empty($m['edited'])){ ?>
            <span class="msgEditedInline">ویرایش‌شده</span>
            <?php } ?>

            <?php if($image !== ''){ ?>
            <a class="msgImageLink" href="<?php echo htmlspecialchars($image, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">
                <img src="<?php echo htmlspecialchars($image, ENT_QUOTES, 'UTF-8'); ?>" alt="">
            </a>
            <?php } ?>

            <div class="msgMeta">
                <span class="msgTime"><?php echo htmlspecialchars($display['time'], ENT_QUOTES, 'UTF-8'); ?></span>
                <?php echo supportRenderReadTicks($m, $options); ?>
            </div>

            <?php if(
                $editId !== ''
                && $editId === ($m['id'] ?? '')
                && $canEdit
            ){ ?>

            <form method="POST" class="editbox">

                <?php echo $csrfField; ?>

                <textarea name="edit_text" required><?php echo htmlspecialchars($plainText, ENT_QUOTES, 'UTF-8'); ?></textarea>

                <input type="hidden" name="edit_id" value="<?php echo htmlspecialchars($m['id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="user" value="<?php echo htmlspecialchars($currentUser, ENT_QUOTES, 'UTF-8'); ?>">

                <button type="submit" class="editbtn">✓ ذخیره</button>

            </form>

            <?php } ?>

        </div>

        <?php
        return ob_get_clean();

    }

    function supportIsEditRequest(){

        return trim((string)($_POST['edit_id'] ?? '')) !== '';

    }

    function supportNotifyTelegramAdmins($username, $message){

        $lib = __DIR__ . '/telegram_lib.php';

        if(!is_file($lib)){
            return;
        }

        require_once $lib;

        if(!function_exists('telegramSendSupportNotification')){
            return;
        }

        $config = function_exists('telegramLoadConfig') ? telegramLoadConfig() : [];

        if(
            empty($config['enabled'])
            || trim((string)($config['bot_token'] ?? '')) === ''
            || count(telegramAdminChatIds($config)) === 0
        ){
            return;
        }

        $mobile = function_exists('telegramGetUserMobile')
            ? telegramGetUserMobile($username)
            : '';

        try{
            telegramSendSupportNotification($username, $message, $mobile);
        }catch(Throwable $e){
            error_log('support telegram notify failed: ' . $e->getMessage());
        }

    }

    function supportProcessAdminActions($file, $embedded = false){

        $data = supportLoad($file);
        $redirect = null;
        $error = null;

        if(isset($_POST['delete_message'])){

            if(!supportCsrfVerify($_POST['csrf'] ?? '')){
                $error = 'درخواست نامعتبر است';
            }
            else{
                $msgId = $_POST['delete_id'] ?? '';
                $user = $_POST['user'] ?? '';

                if(supportDeleteMessage($data, $msgId)){
                    supportSave($file, $data);
                }

                if(supportIsAjaxRequest()){
                    supportAjaxRespond([
                        'ok' => true,
                        'deleted' => true,
                        'message_id' => $msgId,
                    ]);
                }

                $redirect = supportAdminUrl($user, $embedded);
            }

        }

        if(supportIsEditRequest() && $redirect === null){

            if(!supportCsrfVerify($_POST['csrf'] ?? '')){
                $error = 'درخواست نامعتبر است';
            }
            else{
                $id = $_POST['edit_id'] ?? '';
                $text = trim($_POST['edit_text'] ?? '');
                $user = $_POST['user'] ?? '';

                foreach($data as $i => $ticket){

                    if(empty($ticket['messages'])){
                        continue;
                    }

                    foreach($ticket['messages'] as $j => $msg){

                        if(($msg['id'] ?? '') === $id){
                            $data[$i]['messages'][$j]['text'] = $text;
                            $data[$i]['messages'][$j]['edited'] = true;

                            if(supportIsAjaxRequest()){
                                supportAjaxRespond([
                                    'ok' => true,
                                    'edited' => true,
                                    'message' => supportMessageForApi(
                                        $data[$i]['messages'][$j],
                                        ['isAdmin' => true]
                                    ),
                                ]);
                            }
                        }

                    }

                }

                supportSave($file, $data);
                $redirect = supportAdminUrl($user, $embedded);
            }

        }

        if(
            (
                isset($_POST['reply'])
                || (
                    isset($_POST['message'], $_POST['user'])
                    && !supportIsEditRequest()
                    && !isset($_POST['delete_message'])
                )
            )
            && $redirect === null
        ){

            if(!supportCsrfVerify($_POST['csrf'] ?? '')){
                $error = 'درخواست نامعتبر است';
            }
            else{
                $user = $_POST['user'] ?? '';
                $text = trim($_POST['message'] ?? '');
                $upload = supportHandleUpload(
                    $_FILES['image'] ?? [],
                    dirname($file) . '/../uploads/support',
                    '/uploads/support'
                );
                $image = !empty($upload['ok']) ? ($upload['path'] ?? '') : '';
                $voiceUpload = supportHandleVoiceUpload(
                    $_FILES['voice'] ?? [],
                    dirname($file) . '/../uploads/support',
                    '/uploads/support'
                );
                $audio = !empty($voiceUpload['ok']) ? ($voiceUpload['path'] ?? '') : '';

                if($image === '' && !empty($upload['error'])){
                    $error = $upload['error'];
                }
                elseif($audio === '' && !empty($voiceUpload['error'])){
                    $error = $voiceUpload['error'];
                }
                elseif($text === '' && $image === '' && $audio === ''){
                    $error = 'متن، تصویر یا ویس وارد کنید';
                }
                else{
                    $ticketIndex = supportEnsureTicket($data, $user);

                    if($ticketIndex >= 0){

                        if(!isset($data[$ticketIndex]['messages'])){
                            $data[$ticketIndex]['messages'] = [];
                        }

                        $meta = supportMessageMeta();
                        $replyTo = supportBuildReplyPreview($data, $_POST['reply_to'] ?? '');

                        $row = [
                            'id' => uniqid(),
                            'sender' => 'admin',
                            'text' => $text,
                            'image' => $image,
                            'audio' => $audio,
                            'date' => $meta['date'],
                            'time' => $meta['time'],
                            'timestamp' => $meta['timestamp'],
                            'seen_by_user' => false
                        ];

                        if($replyTo){
                            $row['reply_to'] = $replyTo;
                        }

                        $data[$ticketIndex]['messages'][] = $row;

                        $data[$ticketIndex]['status'] = 'answered';

                        supportSave($file, $data);

                        if(!function_exists('tgUserNotifySupportReply') && is_file(__DIR__ . '/telegram_user_lib.php')){
                            require_once __DIR__ . '/telegram_user_lib.php';
                        }

                        if(function_exists('tgUserNotifySupportReply')){
                            $notifyText = $text !== '' ? $text : ($audio !== '' ? '🎤 پیام صوتی' : 'پیام جدید');
                            tgUserNotifySupportReply($user, $notifyText);
                        }

                        if(supportIsAjaxRequest()){
                            supportAjaxRespond([
                                'ok' => true,
                                'message' => supportMessageForApi($row, ['isAdmin' => true]),
                            ]);
                        }

                    }
                    $redirect = supportAdminUrl($user, $embedded);
                }

            }

        }

        if(
            $redirect === null
            && isset($_GET['user'])
            && supportNormalizeUsername($_GET['user'] ?? '') !== ''
        ){

            $viewUser = supportResolveTicketUsername($data, $_GET['user'] ?? '');

            if(supportMarkSeenByAdmin($data, $viewUser)){
                supportSave($file, $data);
            }

        }

        return [
            'data' => $data,
            'redirect' => $redirect,
            'error' => $error
        ];

    }

    function supportProcessUserActions($file, $username){

        $data = supportLoad($file);
        $redirect = 'support.php';
        $error = null;

        if(isset($_POST['delete_message'])){

            if(!supportCsrfVerify($_POST['csrf'] ?? '')){
                $error = 'درخواست نامعتبر است';
            }
            else{
                $msgId = $_POST['delete_id'] ?? '';

                foreach($data as $i => $ticket){

                    if(!supportUsernamesMatch($ticket['user'] ?? '', $username)){
                        continue;
                    }

                    if(empty($ticket['messages'])){
                        continue;
                    }

                    foreach($ticket['messages'] as $j => $msg){

                        if(
                            ($msg['id'] ?? '') === $msgId
                            && ($msg['sender'] ?? '') === 'user'
                            && supportUserCanDeleteMessage($msg)
                        ){
                            unset($data[$i]['messages'][$j]);
                            $data[$i]['messages'] = array_values($data[$i]['messages']);
                            supportSave($file, $data);

                            if(supportIsAjaxRequest()){
                                supportAjaxRespond([
                                    'ok' => true,
                                    'deleted' => true,
                                    'message_id' => $msgId,
                                ]);
                            }
                        }

                    }

                }

                header('Location: support.php');
                exit;

            }

        }

        if(supportIsEditRequest() && $error === null){

            if(!supportCsrfVerify($_POST['csrf'] ?? '')){
                $error = 'درخواست نامعتبر است';
            }
            else{
                $editId = $_POST['edit_id'] ?? '';
                $newText = trim($_POST['edit_text'] ?? '');

                foreach($data as $i => $ticket){

                    if(!supportUsernamesMatch($ticket['user'] ?? '', $username)){
                        continue;
                    }

                    foreach($ticket['messages'] as $j => $msg){

                        if(
                            ($msg['id'] ?? '') === $editId
                            && ($msg['sender'] ?? '') === 'user'
                            && supportUserCanEditMessage($msg)
                        ){
                            $data[$i]['messages'][$j]['text'] = $newText;
                            $data[$i]['messages'][$j]['edited'] = true;
                            supportSave($file, $data);

                            if(supportIsAjaxRequest()){
                                supportAjaxRespond([
                                    'ok' => true,
                                    'edited' => true,
                                    'message' => supportMessageForApi(
                                        $data[$i]['messages'][$j],
                                        ['isAdmin' => false]
                                    ),
                                ]);
                            }
                        }

                    }

                }

                header('Location: support.php');
                exit;

            }

        }

        if(
            (
                isset($_POST['send'])
                || isset($_POST['message'])
            )
            && $error === null
            && !supportIsEditRequest()
        ){

            if(!supportCsrfVerify($_POST['csrf'] ?? '')){
                $error = 'درخواست نامعتبر است';
            }
            else{
                $text = trim($_POST['message'] ?? '');
                $upload = supportHandleUpload(
                    $_FILES['image'] ?? [],
                    __DIR__ . '/uploads/support',
                    '/uploads/support'
                );
                $image = !empty($upload['ok']) ? ($upload['path'] ?? '') : '';

                if($image === '' && !empty($upload['error'])){
                    $error = $upload['error'];
                }
                elseif($text === '' && $image === ''){
                    $error = 'متن یا تصویر وارد کنید';
                }
                else{
                    $meta = supportMessageMeta();
                    $replyTo = supportBuildReplyPreview($data, $_POST['reply_to'] ?? '');

                    $newmsg = [
                        'id' => uniqid(),
                        'sender' => 'user',
                        'text' => $text,
                        'image' => $image,
                        'date' => $meta['date'],
                        'time' => $meta['time'],
                        'timestamp' => $meta['timestamp'],
                        'seen_by_admin' => false
                    ];

                    if($replyTo){
                        $newmsg['reply_to'] = $replyTo;
                    }

                    $ticketIndex = supportFindTicketIndex($data, $username);

                    if($ticketIndex >= 0){
                        $data[$ticketIndex]['messages'][] = $newmsg;
                        $data[$ticketIndex]['status'] = 'open';
                    }
                    else{
                        $data[] = [
                            'id' => 'SUP-' . rand(1000, 9999),
                            'user' => $username,
                            'status' => 'open',
                            'messages' => [$newmsg]
                        ];
                    }

                    supportSave($file, $data);
                    supportNotifyTelegramAdmins($username, $newmsg);

                    if(supportIsAjaxRequest()){
                        supportAjaxRespond([
                            'ok' => true,
                            'message' => supportMessageForApi($newmsg, ['isAdmin' => false]),
                        ]);
                    }

                    header('Location: support.php');
                    exit;
                }

            }

        }

        if(supportMarkSeenByUser($data, $username)){
            supportSave($file, $data);
        }

        return [
            'data' => $data,
            'redirect' => $redirect,
            'error' => $error
        ];

    }

}

if(!function_exists('supportUserHasUnread')){
    function supportUserHasUnread($username, $file = null){
        $username = trim((string)$username);

        if($username === ''){
            return false;
        }

        $file = $file ?: (__DIR__ . '/db/support.json');
        $data = function_exists('supportLoad') ? supportLoad($file) : json_decode((string)@file_get_contents($file), true);

        if(!is_array($data)){
            return false;
        }

        foreach($data as $ticket){
            if(!supportUsernamesMatch($ticket['user'] ?? '', $username)){
                continue;
            }

            foreach(($ticket['messages'] ?? []) as $msg){
                if(($msg['sender'] ?? '') === 'admin' && empty($msg['seen_by_user'])){
                    return true;
                }
            }
        }

        return false;
    }

    function supportTicketForApi($ticket){

        $user = supportNormalizeUsername($ticket['user'] ?? '');
        $lastTs = supportTicketLastTimestamp($ticket);
        $profile = supportGetUserProfileSummary($user);

        return [
            'user' => $user,
            'initial' => supportUserInitial($user),
            'preview' => supportTicketPreview($ticket),
            'relative_time' => supportRelativeTime($lastTs),
            'timestamp' => $lastTs,
            'unread' => supportAdminUnreadCount($ticket),
            'status' => $ticket['status'] ?? '',
            'mobile' => $profile['mobile'] ?? '-',
            'ticket_id' => $ticket['id'] ?? '',
        ];

    }

    function supportTicketsListForApi($data){

        $sorted = supportSortTickets($data);
        $tickets = [];

        foreach($sorted as $ticket){
            $tickets[] = supportTicketForApi($ticket);
        }

        return [
            'tickets' => $tickets,
            'has_unread' => supportAdminHasUnread($sorted),
            'unread_count' => supportAdminUnreadTotal($sorted),
        ];

    }

    function supportAdminApiBootstrap($embedded = false){

        return [
            'csrf' => supportCsrfToken(),
            'embedded' => (bool)$embedded,
            'poll_interval_ms' => 3000,
        ];

    }

    function supportAdminApiMessages($file, $user, $since = 0, $syncAll = false){

        $data = supportLoad($file);
        $user = supportResolveTicketUsername($data, $user);
        $messages = [];
        $status = '';
        $sync = [];
        $unreadUsers = [];

        foreach($data as $ticket){

            if(supportTicketHasUnreadForAdmin($ticket)){
                $unreadUsers[] = $ticket['user'] ?? '';
            }

            if($user === '' || !supportUsernamesMatch($ticket['user'] ?? '', $user)){
                continue;
            }

            $status = $ticket['status'] ?? '';

            if(empty($ticket['messages'])){
                continue;
            }

            foreach($ticket['messages'] as $msg){

                $timestamp = intval($msg['timestamp'] ?? 0);

                if($syncAll){
                    $sync[] = supportMessageForApi($msg, ['isAdmin' => true]);
                }

                if($since > 0 && $timestamp <= $since){
                    continue;
                }

                $messages[] = supportMessageForApi($msg, ['isAdmin' => true]);

            }

        }

        if($user !== ''){
            $data = supportLoad($file);

            if(supportMarkSeenByAdmin($data, $user)){
                supportSave($file, $data);
            }

        }

        $payload = [
            'user' => $user,
            'messages' => $messages,
            'status' => $status,
            'unreadUsers' => $unreadUsers,
            'has_unread' => count($unreadUsers) > 0,
            'unread_count' => supportAdminUnreadTotal($data),
        ];

        if($syncAll){
            $payload['sync'] = $sync;
        }

        return $payload;

    }

    function supportAdminApiParseInput(){

        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

        if(stripos($contentType, 'application/json') !== false){
            $raw = file_get_contents('php://input');
            $json = json_decode($raw, true);

            return is_array($json) ? $json : [];
        }

        return $_POST;

    }

    function supportAdminApiHandlePost($file, $embedded = false){

        $input = supportAdminApiParseInput();
        $action = trim((string)($input['action'] ?? ''));

        if($action === 'send' || ($action === '' && isset($input['message'], $input['user']))){
            $_POST['message'] = $input['message'] ?? '';
            $_POST['user'] = $input['user'] ?? '';
            $_POST['csrf'] = $input['csrf'] ?? '';
            $_POST['reply_to'] = $input['reply_to'] ?? '';
            $_POST['reply'] = '1';
            $_POST['support_ajax'] = '1';
        }
        elseif($action === 'send_voice'){
            $_POST['message'] = trim((string)($input['message'] ?? $_POST['message'] ?? ''));
            $_POST['user'] = $input['user'] ?? $_POST['user'] ?? '';
            $_POST['csrf'] = $input['csrf'] ?? $_POST['csrf'] ?? '';
            $_POST['reply'] = '1';
            $_POST['support_ajax'] = '1';
        }
        elseif($action === 'edit'){
            $_POST['edit_id'] = $input['edit_id'] ?? $input['id'] ?? '';
            $_POST['edit_text'] = $input['edit_text'] ?? $input['text'] ?? '';
            $_POST['user'] = $input['user'] ?? '';
            $_POST['csrf'] = $input['csrf'] ?? '';
            $_POST['support_ajax'] = '1';
        }
        elseif($action === 'delete'){
            $_POST['delete_message'] = '1';
            $_POST['delete_id'] = $input['delete_id'] ?? $input['id'] ?? '';
            $_POST['user'] = $input['user'] ?? '';
            $_POST['csrf'] = $input['csrf'] ?? '';
            $_POST['support_ajax'] = '1';
        }
        else{
            supportAjaxRespond(['ok' => false, 'error' => 'action نامعتبر است'], 400);
        }

        $result = supportProcessAdminActions($file, $embedded);

        if(!empty($result['error'])){
            supportAjaxRespond(['ok' => false, 'error' => $result['error']], 400);
        }

        supportAjaxRespond(['ok' => false, 'error' => 'درخواست پردازش نشد'], 400);

    }
}
