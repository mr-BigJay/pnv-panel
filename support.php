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
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
<title>پیام به پشتیبانی</title>
<link rel="stylesheet" href="user_nav.css?v=1">
<link rel="stylesheet" href="/assets/support-chat/pnv-support-shell.css?v=7">
<link rel="stylesheet" href="fonts.css">
<style>
html,body{margin:0;padding:0;height:100%;overflow:hidden;}
</style>
</head>
<body>

<div class="msgApp msgApp--user pnv-chat pnv-chat--user" id="userSupportPage">

<header class="msgHeader">
<a href="dashboard.php" class="userBack pnv-topbar-back" title="بازگشت" aria-label="بازگشت">←</a>
<div class="msgAvatar"><?php echo htmlspecialchars(supportUserInitial($user), ENT_QUOTES, 'UTF-8'); ?></div>
<div class="msgHeaderInfo">
<h1>پشتیبانی</h1>
<p>آنلاین</p>
</div>
<button type="button" class="pnv-chat-theme-toggle" id="pnvChatThemeToggle" aria-label="تغییر تم">حالت روشن</button>
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

<div class="pnv-voice-recording" hidden>
<span class="pnv-voice-recording-dot" aria-hidden="true"></span>
<span class="pnv-voice-recording-time">0:00</span>
<span class="pnv-voice-recording-label">در حال ضبط…</span>
<button type="button" class="pnv-voice-recording-cancel" aria-label="لغو ضبط">لغو</button>
<button type="button" class="pnv-voice-recording-stop" aria-label="توقف ضبط">توقف</button>
</div>
<div class="pnv-voice-preview" hidden>
<button type="button" class="pnv-voice-preview-play" aria-label="پخش پیش‌نمایش">▶</button>
<audio preload="metadata"></audio>
<span class="pnv-voice-preview-duration">0:00</span>
<button type="button" class="pnv-voice-preview-send">ارسال</button>
<button type="button" class="pnv-voice-preview-cancel">لغو</button>
</div>
<div class="msgComposerRow">
<button type="button" class="msgIconBtn msgIconBtn--attach" id="attachBtn" title="پیوست تصویر" aria-label="پیوست تصویر">📎</button>
<input type="file" name="image" id="userImage" class="msgFileInput" accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp">
<input type="file" name="audio" id="userAudio" class="msgFileInput" accept="audio/webm,audio/ogg,video/webm">

<textarea
    name="message"
    id="message"
    placeholder="ارسال پیام . . . ."
    rows="1"></textarea>

<div class="msgComposerAction">
<button type="button" class="msgIconBtn msgIconBtn--mic" id="userMic" title="پیام صوتی" aria-label="ضبط پیام صوتی">🎤</button>
<button type="submit" class="msgIconBtn msgIconBtn--send" id="userSend" title="ارسال" aria-label="ارسال" hidden>➤</button>
</div>
</div>

</form>
</footer>

</div>

<script src="/support_ui.js?v=43"></script>
<script src="/assets/support-chat/pnv-support-shell.js?v=7"></script>
<script src="/assets/support-chat/pnv-support-voice.js?v=7"></script>
<script src="/assets/support-chat/pnv-support-adapter.js?v=7"></script>
<script>
(function(){
    PNVSupportChat.init({
        mode: 'user',
        rootEl: document.getElementById('userSupportPage'),
        messagesEl: document.getElementById('userChat'),
        textareaEl: document.getElementById('message'),
        formEl: document.getElementById('userSupportForm'),
        imageInputId: 'userImage',
        attachBtnId: 'attachBtn',
        audioInputId: 'userAudio',
        micBtnEl: document.getElementById('userMic'),
        sendBtnEl: document.getElementById('userSend'),
        pollUrl: 'support-api.php'
    });
})();
</script>

</body>
</html>
