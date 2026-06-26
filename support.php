<?php

session_start();

if(!isset($_SESSION['user'])){
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/chatwoot_lib.php';

if(chatwootEnabled() && !isset($_GET['legacy'])){
    include __DIR__ . '/support_chatwoot.php';
    exit;
}

require_once __DIR__ . '/support_lib.php';

$user = $_SESSION['user'];
$file = __DIR__ . '/db/support.json';
$csrfField = supportCsrfField();
$actionResult = supportProcessUserActions($file, $user);

if($actionResult['error']){
    $supportError = $actionResult['error'];
}
else{
    $supportError = '';
}

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
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>پشتیبانی</title>
<style>

*{
box-sizing:border-box;
}

body{
margin:0;
padding:12px;
background:#0f172a;
font-family:tahoma;
direction:rtl;
color:white;
}

.container{
max-width:760px;
margin:auto;
}

.chat{
background:#1e293b;
padding:15px;
border-radius:18px;
height:68vh;
overflow-y:auto;
margin-bottom:15px;
display:flex;
flex-direction:column;
}

.msg{
padding:14px;
border-radius:16px;
margin-bottom:12px;
max-width:85%;
line-height:30px;
word-break:break-word;
position:relative;
}

.msg.user{
background:#2563eb;
margin-left:auto;
}

.msg.admin{
background:#334155;
margin-right:auto;
}

.time{
font-size:11px;
opacity:0.7;
margin-top:8px;
display:flex;
gap:8px;
align-items:center;
flex-wrap:wrap;
}

.msg img{
max-width:100%;
margin-top:10px;
border-radius:12px;
}

.empty{
text-align:center;
padding:40px 20px;
color:#94a3b8;
line-height:34px;
}

.formbox{
background:#1e293b;
padding:12px;
border-radius:18px;
}

.sendbox{
display:flex;
align-items:flex-end;
gap:8px;
background:#0f172a;
padding:8px;
border-radius:18px;
}

.sidebuttons{
display:flex;
flex-direction:column;
gap:6px;
}

textarea{
flex:1;
min-height:52px;
max-height:180px;
padding:14px;
border:none;
border-radius:14px;
background:transparent;
color:white;
font-family:tahoma;
font-size:15px;
resize:none;
outline:none;
overflow-y:auto;
line-height:28px;
}

textarea::placeholder{
color:#94a3b8;
}

.attach{
width:42px;
height:42px;
background:#334155;
border-radius:12px;
display:flex;
align-items:center;
justify-content:center;
font-size:18px;
cursor:pointer;
flex-shrink:0;
margin-bottom:2px;
}

.attach input{
display:none;
}

.sendbtn{
width:42px;
height:42px;
border:none;
border-radius:12px;
background:#22c55e;
color:white;
font-size:18px;
cursor:pointer;
flex-shrink:0;
padding:0;
margin-bottom:2px;
}

.action,
.deleteBtn{
color:white;
text-decoration:none;
font-size:14px;
width:28px;
height:28px;
display:flex;
align-items:center;
justify-content:center;
background:rgba(255,255,255,0.12);
border-radius:8px;
border:none;
cursor:pointer;
padding:0;
}

.deleteForm{
display:inline;
margin:0;
}

.editbox{
margin-top:10px;
}

.editbox textarea{
background:#1e293b;
min-height:80px;
width:100%;
}

.editbtn{
margin-top:10px;
background:#22c55e;
width:42px;
height:42px;
border:none;
border-radius:12px;
color:white;
cursor:pointer;
font-size:20px;
padding:0;
}

.back{
display:block;
margin-top:15px;
background:#334155;
padding:15px;
border-radius:16px;
text-align:center;
color:white;
text-decoration:none;
}

.flashError{
background:#450a0a;
color:#fecaca;
padding:12px 14px;
border-radius:12px;
margin-bottom:12px;
font-size:13px;
}

@media(max-width:768px){

.chat{
height:64vh;
}

.msg{
max-width:92%;
font-size:15px;
line-height:28px;
}

textarea{
font-size:16px;
}

}

</style>
</head>
<body>

<div class="container">

<?php if($supportError){ ?>
<div class="flashError"><?php echo htmlspecialchars($supportError, ENT_QUOTES, 'UTF-8'); ?></div>
<?php } ?>

<div class="chat" id="userChat">

<?php if(count($messages) === 0){ ?>
<div class="empty">هنوز پیامی ارسال نشده است</div>
<?php } ?>

<?php foreach($messages as $m){

    $sender = $m['sender'] ?? 'user';
    $timestamp = $m['timestamp'] ?? 0;
    $canEdit = $sender === 'user' && time() - $timestamp <= 3600;
    $canDelete = $sender === 'user' && time() - $timestamp <= 60;
    $display = supportMessageDisplayTime($m);
    $image = $m['image'] ?? '';

    if($image !== ''){
        $image = '/' . ltrim($image, '/');
    }

?>

<div class="msg <?php echo htmlspecialchars($sender, ENT_QUOTES, 'UTF-8'); ?>" data-msg-id="<?php echo htmlspecialchars($m['id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" data-timestamp="<?php echo intval($timestamp); ?>">

<?php echo nl2br(htmlspecialchars($m['text'] ?? '', ENT_QUOTES, 'UTF-8')); ?>

<?php if(!empty($m['edited'])){ ?>
<br><small>(ویرایش شد)</small>
<?php } ?>

<?php if($image !== ''){ ?>
<br><img src="<?php echo htmlspecialchars($image, ENT_QUOTES, 'UTF-8'); ?>" alt="">
<?php } ?>

<div class="time">

<?php echo htmlspecialchars($display['date'], ENT_QUOTES, 'UTF-8'); ?>
-
<?php echo htmlspecialchars($display['time'], ENT_QUOTES, 'UTF-8'); ?>

<?php if($canEdit){ ?>
<a href="?edit=<?php echo urlencode($m['id'] ?? ''); ?>" class="action">✏️</a>
<?php } ?>

<?php if($canDelete){ ?>
<form method="POST" class="deleteForm" onsubmit="return confirm('پیام حذف شود؟');">
<?php echo $csrfField; ?>
<input type="hidden" name="delete_message" value="1">
<input type="hidden" name="delete_id" value="<?php echo htmlspecialchars($m['id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
<button type="submit" class="deleteBtn">🗑</button>
</form>
<?php } ?>

</div>

<?php if($editId !== '' && $editId === ($m['id'] ?? '') && $canEdit){ ?>

<form method="POST" class="editbox">
<?php echo $csrfField; ?>
<textarea name="edit_text" required><?php echo htmlspecialchars($m['text'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
<input type="hidden" name="edit_id" value="<?php echo htmlspecialchars($m['id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
<button type="submit" class="editbtn">✓</button>
</form>

<?php } ?>

</div>

<?php } ?>

</div>

<div class="formbox">

<form method="POST" enctype="multipart/form-data" id="userSupportForm">

<?php echo $csrfField; ?>

<div class="sendbox">

<div class="sidebuttons">

<label class="attach">
📎
<input type="file" name="image" id="userImage" accept="image/*">
</label>

<button type="submit" class="sendbtn">➤</button>

</div>

<textarea
    name="message"
    id="message"
    placeholder="پیام خود را بنویسید..."></textarea>

</div>

</form>

</div>

<a href="dashboard.php" class="back">بازگشت</a>

</div>

<script>

(function(){

const userChat = document.getElementById('userChat');
const messageInput = document.getElementById('message');
const userSupportForm = document.getElementById('userSupportForm');

function scrollChatToBottom(force){

    if(!userChat){
        return;
    }

    const distance =
    userChat.scrollHeight -
    userChat.scrollTop -
    userChat.clientHeight;

    if(force || distance < 120){
        userChat.scrollTop = userChat.scrollHeight;
    }

}

function escapeHtml(text){

    return String(text)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

}

function buildMessageNode(msg){

    const wrap = document.createElement('div');
    const senderClass = msg.sender === 'admin' ? 'admin' : 'user';

    wrap.className = 'msg ' + senderClass;
    wrap.dataset.msgId = msg.id || '';
    wrap.dataset.timestamp = msg.timestamp || 0;

    let html = escapeHtml(msg.text || '').replace(/\n/g, '<br>');

    if(msg.edited){
        html += '<br><small>(ویرایش شد)</small>';
    }

    if(msg.image){
        html += '<br><img src="' + escapeHtml(msg.image) + '" alt="">';
    }

    html += '<div class="time">' +
        escapeHtml(msg.date || '') + ' - ' + escapeHtml(msg.time || '') +
        '</div>';

    wrap.innerHTML = html;
    return wrap;

}

let lastPollTimestamp = 0;

if(userChat){

    userChat.querySelectorAll('.msg[data-timestamp]').forEach(function(node){

        const ts = parseInt(node.dataset.timestamp || '0', 10);

        if(ts > lastPollTimestamp){
            lastPollTimestamp = ts;
        }

    });

}

async function pollMessages(){

    if(!userChat){
        return;
    }

    const url = 'support-api.php?since=' + (lastPollTimestamp || 0);

    try{

        const response = await fetch(url, {credentials: 'same-origin'});

        if(!response.ok){
            return;
        }

        const payload = await response.json();
        let added = false;

        (payload.messages || []).forEach(function(msg){

            if(userChat.querySelector('[data-msg-id="' + msg.id + '"]')){
                return;
            }

            const empty = userChat.querySelector('.empty');

            if(empty){
                empty.remove();
            }

            const node = buildMessageNode(msg);
            userChat.appendChild(node);
            lastPollTimestamp = Math.max(lastPollTimestamp, msg.timestamp || 0);
            added = true;

        });

        if(added){
            scrollChatToBottom(false);
        }

    }
    catch(e){}

}

if(messageInput){

    messageInput.addEventListener('input', function(){

        this.style.height = '52px';
        this.style.height = (this.scrollHeight) + 'px';

    });

}

if(userSupportForm){

    userSupportForm.addEventListener('submit', function(e){

        const text = (messageInput?.value || '').trim();
        const image = document.getElementById('userImage');

        if(text === '' && (!image || image.files.length === 0)){
            e.preventDefault();
            alert('متن یا تصویر وارد کنید');
        }

    });

}

scrollChatToBottom(true);
setInterval(pollMessages, 8000);

})();

</script>

</body>
</html>
