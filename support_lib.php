<?php

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

        static $set = false;

        if(!$set){
            date_default_timezone_set('Asia/Tehran');
            $set = true;
        }

    }

    function supportGregorianToJalali($gy, $gm, $gd){

        $g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
        $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
        $days = 355666
            + (365 * $gy)
            + (int)(($gy2 + 3) / 4)
            - (int)(($gy2 + 99) / 100)
            + (int)(($gy2 + 399) / 400)
            + $gd
            + $g_d_m[$gm - 1];
        $jy = -1595 + (33 * (int)($days / 12053));
        $days %= 12053;
        $jy += 4 * (int)($days / 1461);
        $days %= 1461;

        if($days > 365){
            $jy += (int)(($days - 1) / 365);
            $days = ($days - 1) % 365;
        }

        if($days < 186){
            $jm = 1 + (int)($days / 31);
            $jd = 1 + ($days % 31);
        }
        else{
            $jm = 7 + (int)(($days - 186) / 30);
            $jd = 1 + (($days - 186) % 30);
        }

        return [$jy, $jm, $jd];

    }

    function supportFormatFromTimestamp($timestamp){

        supportEnsureTehranTimezone();

        $timestamp = intval($timestamp);

        if($timestamp <= 0){
            return [
                'date' => '-',
                'time' => '-'
            ];
        }

        $gy = (int)date('Y', $timestamp);
        $gm = (int)date('n', $timestamp);
        $gd = (int)date('j', $timestamp);

        [$jy, $jm, $jd] = supportGregorianToJalali($gy, $gm, $gd);

        return [
            'date' => sprintf('%04d/%02d/%02d', $jy, $jm, $jd),
            'time' => date('H:i', $timestamp)
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
            'edited' => !empty($message['edited'])
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

        if(mb_strlen($text) > 48){
            return mb_substr($text, 0, 48) . '…';
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

        if(
            !isset($fileInput['size'])
            || $fileInput['size'] <= 0
        ){
            return '';
        }

        if($fileInput['size'] > 5 * 1024 * 1024){
            return '';
        }

        $ext = strtolower(pathinfo($fileInput['name'], PATHINFO_EXTENSION));

        $allowed = ['jpg', 'jpeg', 'png', 'webp'];

        if(!in_array($ext, $allowed, true)){
            return '';
        }

        $imageInfo = @getimagesize($fileInput['tmp_name']);

        if($imageInfo === false){
            return '';
        }

        if(!is_dir($uploadDir)){
            mkdir($uploadDir, 0755, true);
        }

        $filename = time() . rand(1000, 9999) . '.' . $ext;
        $savePath = rtrim($uploadDir, '/') . '/' . $filename;

        if(!move_uploaded_file($fileInput['tmp_name'], $savePath)){
            return '';
        }

        return rtrim($urlPrefix, '/') . '/' . $filename;

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
        }
        elseif(
            $sender === 'user'
            && !empty($options['ownUsername'])
        ){
            $timestamp = $m['timestamp'] ?? 0;

            if(time() - $timestamp <= 3600){
                $canEdit = true;
            }

            if(time() - $timestamp <= 60){
                $canDelete = true;
            }

        }

        $image = $m['image'] ?? '';

        if($image !== ''){
            $image = '/' . ltrim($image, '/');
        }

        $display = supportMessageDisplayTime($m);

        ob_start();
        ?>

        <div class="msgBubble msg <?php echo $class; ?>" data-msg-id="<?php echo htmlspecialchars($m['id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" data-timestamp="<?php echo intval($m['timestamp'] ?? 0); ?>">

            <?php echo nl2br(htmlspecialchars($m['text'] ?? '', ENT_QUOTES, 'UTF-8')); ?>

            <?php if(!empty($m['edited'])){ ?>

            <br><small>(ویرایش شد)</small>

            <?php } ?>

            <?php if($image !== ''){ ?>

            <br>
            <img src="<?php echo htmlspecialchars($image, ENT_QUOTES, 'UTF-8'); ?>" alt="">

            <?php } ?>

            <div class="msgMeta">

                <?php echo htmlspecialchars($display['date'], ENT_QUOTES, 'UTF-8'); ?>
                -
                <?php echo htmlspecialchars($display['time'], ENT_QUOTES, 'UTF-8'); ?>

                <?php if($canEdit){ ?>

                <a
                    href="<?php echo htmlspecialchars($baseUrl . (strpos($baseUrl, '?') !== false ? '&' : '?') . 'edit=' . urlencode($m['id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                    class="action">

                    ✏️

                </a>

                <?php } ?>

                <?php if($canDelete){ ?>

                <form
                    method="POST"
                    class="deleteForm"
                    onsubmit="return confirm('پیام حذف شود؟');">

                    <?php echo $csrfField; ?>

                    <input type="hidden" name="delete_message" value="1">
                    <input type="hidden" name="delete_id" value="<?php echo htmlspecialchars($m['id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="user" value="<?php echo htmlspecialchars($currentUser, ENT_QUOTES, 'UTF-8'); ?>">

                    <button type="submit" class="action deleteBtn">🗑</button>

                </form>

                <?php } ?>

            </div>

            <?php if(
                $editId !== ''
                && $editId === ($m['id'] ?? '')
                && $canEdit
            ){ ?>

            <form method="POST" class="editbox">

                <?php echo $csrfField; ?>

                <textarea name="edit_text" required><?php echo htmlspecialchars($m['text'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>

                <input type="hidden" name="edit_id" value="<?php echo htmlspecialchars($m['id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="user" value="<?php echo htmlspecialchars($currentUser, ENT_QUOTES, 'UTF-8'); ?>">

                <button type="submit" class="editbtn">✓</button>

            </form>

            <?php } ?>

        </div>

        <?php
        return ob_get_clean();

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

        if(isset($_POST['edit_id']) && $redirect === null){

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

        if(isset($_POST['reply']) && $redirect === null){

            if(!supportCsrfVerify($_POST['csrf'] ?? '')){
                $error = 'درخواست نامعتبر است';
            }
            else{
                $user = $_POST['user'] ?? '';
                $text = trim($_POST['message'] ?? '');
                $image = supportHandleUpload(
                    $_FILES['image'] ?? [],
                    dirname($file) . '/../uploads/support',
                    '/uploads/support'
                );

                if($text === '' && $image === ''){
                    $error = 'متن یا تصویر وارد کنید';
                }
                else{
                    $ticketIndex = supportFindTicketIndex($data, $user);

                    if($ticketIndex >= 0){

                        if(!isset($data[$ticketIndex]['messages'])){
                            $data[$ticketIndex]['messages'] = [];
                        }

                        $meta = supportMessageMeta();

                        $data[$ticketIndex]['messages'][] = [
                            'id' => uniqid(),
                            'sender' => 'admin',
                            'text' => $text,
                            'image' => $image,
                            'date' => $meta['date'],
                            'time' => $meta['time'],
                            'timestamp' => $meta['timestamp'],
                            'seen_by_user' => false
                        ];

                        $data[$ticketIndex]['status'] = 'answered';

                    }

                    supportSave($file, $data);
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
                            && time() - ($msg['timestamp'] ?? 0) <= 60
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

        if(isset($_POST['edit_id']) && $error === null){

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
                            && time() - ($msg['timestamp'] ?? 0) <= 3600
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

        if(isset($_POST['message']) && $error === null){

            if(!supportCsrfVerify($_POST['csrf'] ?? '')){
                $error = 'درخواست نامعتبر است';
            }
            else{
                $text = trim($_POST['message'] ?? '');
                $image = supportHandleUpload(
                    $_FILES['image'] ?? [],
                    __DIR__ . '/uploads/support',
                    'uploads/support'
                );

                if($text === '' && $image === ''){
                    $error = 'متن یا تصویر وارد کنید';
                }
                else{
                    $meta = supportMessageMeta();

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
