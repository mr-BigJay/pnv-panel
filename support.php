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

    if(($ticket['user'] ?? '') === $user){

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
<link rel="stylesheet" href="user_nav.css?v=1">
<link rel="stylesheet" href="support_ui.css?v=39">
<link rel="stylesheet" href="fonts.css">
<style>
html,body{margin:0;padding:0;background:#0b1220;color:#f1f5f9;height:100%;overflow:hidden;}
</style>
</head>
<body>

<div class="msgApp msgApp--user">

<header class="msgHeader">
<a href="dashboard.php" class="userBack" title="بازگشت">بازگشت</a>
<div class="msgAvatar"><?php echo htmlspecialchars(supportUserInitial($user), ENT_QUOTES, 'UTF-8'); ?></div>
<div class="msgHeaderInfo">
<h1>پشتیبانی</h1>
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

<?php foreach($messages as $m){
    echo supportRenderMessageHtml($m, [
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
    placeholder="ارسال پیام . . . ."
    rows="1"></textarea>

<button type="submit" class="msgIconBtn msgIconBtn--send" title="ارسال" aria-label="ارسال">➤</button>
</div>

</form>
</footer>

</div>

<script src="support_ui.js?v=33"></script>
<script>
(function(){
    const userChat = document.getElementById('userChat');
    const messageInput = document.getElementById('message');
    const userSupportForm = document.getElementById('userSupportForm');

    SupportUI.bindTextareaGrow(messageInput);
    SupportUI.bindEnterToSend(messageInput, userSupportForm, true);
    SupportUI.bindFormGuard(userSupportForm, messageInput, 'userImage');
    SupportUI.bindImageAttach(userSupportForm, 'userImage', 'attachBtn');
    SupportUI.bindMessageActions({
        chatEl: userChat,
        form: userSupportForm,
        role: 'user'
    });

    SupportUI.initPolling({
        chatEl: userChat,
        pollUrl: 'support-api.php',
        getParams: function(since){
            return '?since=' + (since || 0);
        },
        classMap: {admin:'admin', user:'usermsg'},
        actionMeta: {isAdmin: false, ownSender: 'user'},
        interval: 5000
    });
})();
</script>

</body>
</html>
