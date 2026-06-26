<?php

if(!function_exists('supportLoad')){

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

        if($embedded){
            $url = 'index.php?page=support';

            if($user !== ''){
                $url .= '&user=' . urlencode($user);
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
        $class = ($sender === 'admin') ? 'admin' : 'usermsg';
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

        ob_start();
        ?>

        <div class="msg <?php echo $class; ?>" data-msg-id="<?php echo htmlspecialchars($m['id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" data-timestamp="<?php echo intval($m['timestamp'] ?? 0); ?>">

            <?php echo nl2br(htmlspecialchars($m['text'] ?? '', ENT_QUOTES, 'UTF-8')); ?>

            <?php if(!empty($m['edited'])){ ?>

            <br><small>(ویرایش شد)</small>

            <?php } ?>

            <?php if($image !== ''){ ?>

            <br>
            <img src="<?php echo htmlspecialchars($image, ENT_QUOTES, 'UTF-8'); ?>" alt="">

            <?php } ?>

            <div class="time">

                <?php echo htmlspecialchars($m['date'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                -
                <?php echo htmlspecialchars($m['time'] ?? '', ENT_QUOTES, 'UTF-8'); ?>

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

                        $data[$ticketIndex]['messages'][] = [
                            'id' => uniqid(),
                            'sender' => 'admin',
                            'text' => $text,
                            'image' => $image,
                            'date' => date('Y/m/d'),
                            'time' => date('H:i'),
                            'timestamp' => time(),
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
                    $newmsg = [
                        'id' => uniqid(),
                        'sender' => 'user',
                        'text' => $text,
                        'image' => $image,
                        'date' => date('Y/m/d'),
                        'time' => date('H:i'),
                        'timestamp' => time(),
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
