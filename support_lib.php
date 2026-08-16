<?php

require_once __DIR__ . '/pnv_date_bootstrap.php';

if(!function_exists('supportLoad')){

    function supportIsEmbeddedRequest(){

        return basename($_SERVER['SCRIPT_NAME'] ?? '') === 'index.php'
            && (($_GET['page'] ?? '') === 'support');

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

    function supportMessageForApi($message){

        $display = supportMessageDisplayTime($message);
        $image = $message['image'] ?? '';

        if($image !== ''){
            $image = '/' . ltrim($image, '/');
        }

        return [
            'id' => $message['id'] ?? '',
            'sender' => $message['sender'] ?? '',
            'text' => $message['text'] ?? '',
            'image' => $image,
            'date' => $display['date'],
            'time' => $display['time'],
            'timestamp' => intval($message['timestamp'] ?? 0),
            'edited' => !empty($message['edited']),
            'reply_to' => is_array($message['reply_to'] ?? null) ? $message['reply_to'] : null
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

            if(($ticket['user'] ?? '') !== $username){
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

            if(($ticket['user'] ?? '') !== $username){
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

    function supportFindTicketIndex($data, $username){

        foreach($data as $i => $ticket){

            if(($ticket['user'] ?? '') === $username){
                return $i;
            }

        }

        return -1;

    }

    function supportEnsureTicket(&$data, $username){

        $username = trim($username);

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
            <small class="msgEdited">(ویرایش شد)</small>
            <?php } ?>

            <?php if($image !== ''){ ?>
            <a class="msgImageLink" href="<?php echo htmlspecialchars($image, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">
                <img src="<?php echo htmlspecialchars($image, ENT_QUOTES, 'UTF-8'); ?>" alt="">
            </a>
            <?php } ?>

            <div class="msgMeta">
                <?php echo htmlspecialchars($display['time'], ENT_QUOTES, 'UTF-8'); ?>
                -
                <?php echo htmlspecialchars($display['date'], ENT_QUOTES, 'UTF-8'); ?>
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

                if($image === '' && !empty($upload['error'])){
                    $error = $upload['error'];
                }
                elseif($text === '' && $image === ''){
                    $error = 'متن یا تصویر وارد کنید';
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
                            tgUserNotifySupportReply($user, $text);
                        }

                    }
                    $redirect = supportAdminUrl($user, $embedded);
                }

            }

        }

        if(
            $redirect === null
            && isset($_GET['user'])
            && ($_GET['user'] ?? '') !== ''
        ){

            if(supportMarkSeenByAdmin($data, $_GET['user'])){
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

                    if(($ticket['user'] ?? '') !== $username){
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

                    if(($ticket['user'] ?? '') !== $username){
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
            if(strcasecmp(trim((string)($ticket['user'] ?? '')), $username) !== 0){
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
}
