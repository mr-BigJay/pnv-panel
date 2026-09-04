<?php

session_start();

if(!isset($_SESSION['user'])){
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/support_lib.php';

$user = $_SESSION['user'];
$file = __DIR__ . '/db/support.json';
$csrfField = supportCsrfField();
$actionResult = supportProcessUserActions($file, $user);
$supportError = $actionResult['error'] ?? '';
$data = $actionResult['data'];
$messages = [];
$editId = $_GET['edit'] ?? '';

foreach($data as $ticket){

    if(supportUsernamesMatch($ticket['user'] ?? '', $user)){

        if(isset($ticket['messages'])){
            $messages = $ticket['messages'];
        }

        break;

    }

}

?>
<!DOCTYPE html>
<html lang="fa">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
<title>پیام به پشتیبانی</title>
<link rel="stylesheet" href="support_ui.css?v=45">
<link rel="stylesheet" href="fonts.css">
<style>
html,body{margin:0;padding:0;background:#0e1621;color:#e4ecf4;height:100%;overflow:hidden;}
</style>
</head>
<body>

<div class="msgApp msgApp--user">

<header class="msgHeader msgHeader--user">
<a href="dashboard.php" class="msgBackLink" title="بازگشت">← بازگشت</a>
<div class="msgAvatar msgAvatar--support">پ</div>
<div class="msgHeaderInfo">
<h1>پشتیبانی</h1>
<p>معمولاً در کمتر از ۱ ساعت پاسخ می‌دهیم</p>
</div>
</header>

<?php if($supportError){ ?>
<div class="msgFlash"><?php echo htmlspecialchars($supportError, ENT_QUOTES, 'UTF-8'); ?></div>
<?php } ?>

<div class="msgBody" id="userChat">

<?php if(count($messages) === 0){ ?>
<div class="msgEmpty">
<div class="msgEmptyIcon">💬</div>
هنوز پیامی نفرستاده‌اید<br>
اولین پیام را پایین بنویسید
</div>
<?php } ?>

<?php if(count($messages) > 0){
    echo supportRenderMessagesList($messages, [
        'ownUsername' => $user,
        'csrfField' => $csrfField,
        'editId' => $editId,
        'baseUrl' => 'support.php'
    ]);
} ?>

</div>

<footer class="msgComposer">
<form method="POST" enctype="multipart/form-data" id="userSupportForm" class="msgComposerInner" action="support.php">

<?php echo $csrfField; ?>
<input type="hidden" name="send" value="1">

<div class="msgComposerRow">
<button type="button" class="msgIconBtn msgIconBtn--attach" id="attachBtn" title="پیوست تصویر" aria-label="پیوست تصویر">📎</button>
<input type="file" name="image" id="userImage" class="msgFileInput" accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp">

<textarea
    name="message"
    id="message"
    placeholder="پیام به پشتیبانی..."
    rows="1"></textarea>

<button type="submit" class="msgIconBtn msgIconBtn--send" title="ارسال" aria-label="ارسال">➤</button>
</div>

</form>
</footer>

</div>

<script src="support_ui.js?v=45"></script>
<script>
(function(){
    const userChat = document.getElementById('userChat');
    const messageInput = document.getElementById('message');
    const userSupportForm = document.getElementById('userSupportForm');
    const pinScope = 'user';

    SupportUI.bindTextareaGrow(messageInput);
    SupportUI.bindEnterToSend(messageInput, userSupportForm, true);
    SupportUI.bindFormGuard(userSupportForm, messageInput, 'userImage');
    SupportUI.bindImageAttach(userSupportForm, 'userImage', 'attachBtn');
    SupportUI.bindAjaxSend({
        form: userSupportForm,
        chatEl: userChat,
        classMap: {admin:'admin', user:'usermsg'},
        actionMeta: {isAdmin: false, ownSender: 'user', pinScope: pinScope}
    });
    SupportUI.bindMessageActions({
        chatEl: userChat,
        form: userSupportForm,
        role: 'user',
        pinScope: pinScope
    });

    SupportUI.initPolling({
        chatEl: userChat,
        pollUrl: 'support-api.php',
        pinScope: pinScope,
        getParams: function(since){
            return '?since=' + (since || 0);
        },
        classMap: {admin:'admin', user:'usermsg'},
        actionMeta: {isAdmin: false, ownSender: 'user', pinScope: pinScope},
        interval: 5000
    });

    if(userChat){
        SupportUI.scrollToBottomOnOpen(userChat);
    }
})();
</script>

<?php require_once __DIR__ . '/form_validation_fa.php'; pnvFormValidationFaScript(); ?>

</body>
</html>
