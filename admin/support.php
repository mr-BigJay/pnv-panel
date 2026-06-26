<?php

if(!isset($supportEmbedded)){
    $supportEmbedded = false;
}

require_once __DIR__ . '/../support_lib.php';

if(!$supportEmbedded && supportIsEmbeddedRequest()){
    $supportEmbedded = true;
}

if(!$supportEmbedded){

    require_once __DIR__ . '/auth.php';

    if(!pnvAdminIsLoggedIn()){
        header('Location: ' . pnvAdminEntryUrl());
        exit;
    }

}

$file = __DIR__ . '/../db/support.json';
$csrfField = supportCsrfField();

if(
    isset($supportActionResult)
    && is_array($supportActionResult)
){
    $actionResult = $supportActionResult;
}
else{
    $actionResult = supportProcessAdminActions($file, $supportEmbedded);
}

if($actionResult['redirect']){
    header('Location: ' . $actionResult['redirect']);
    exit;
}

$data = supportSortTickets($actionResult['data']);
$currentUser = $_GET['user'] ?? '';
$editId = $_GET['edit'] ?? '';
$supportError = $actionResult['error'] ?? '';
$baseUrl = supportAdminUrl($currentUser, $supportEmbedded);

if(!$supportEmbedded){
?>

<!DOCTYPE html>
<html lang="fa">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>پشتیبانی مدیریت</title>
<?php } ?>

<style>

.supportPage{
<?php if($supportEmbedded){ ?>
height:calc(100vh - 0px);
margin:-20px;
<?php } else { ?>
height:100vh;
<?php } ?>
display:flex;
background:#0f172a;
font-family:tahoma;
direction:rtl;
color:white;
overflow:hidden;
}

.supportSidebar{
width:320px;
background:#1e293b;
overflow-y:auto;
padding:15px;
border-left:1px solid #334155;
flex-shrink:0;
}

.supportSearch{
width:100%;
padding:12px;
border:none;
border-radius:12px;
margin-bottom:12px;
background:#0f172a;
color:white;
font-family:tahoma;
box-sizing:border-box;
}

.supportUser{
display:block;
background:#0f172a;
padding:14px;
border-radius:14px;
margin-bottom:10px;
text-decoration:none;
color:white;
line-height:28px;
}

.supportUser:hover,
.supportUser.active{
background:#334155;
}

.supportUserTop{
display:flex;
align-items:center;
gap:8px;
margin-bottom:6px;
}

.supportRedDot{
width:10px;
height:10px;
background:#ef4444;
border-radius:50%;
display:inline-block;
flex-shrink:0;
}

.supportChatbox{
flex:1;
display:flex;
flex-direction:column;
padding:15px;
min-width:0;
}

.supportChatHeader{
background:#1e293b;
padding:16px 20px;
border-radius:18px;
margin-bottom:14px;
font-size:18px;
font-weight:bold;
display:flex;
align-items:center;
justify-content:space-between;
gap:10px;
flex-wrap:wrap;
}

.supportChatHeaderActions{
display:flex;
align-items:center;
gap:8px;
flex-wrap:wrap;
}

.viewSubsBtn{
background:#2563eb;
color:white;
text-decoration:none;
font-size:13px;
padding:8px 14px;
border-radius:10px;
white-space:nowrap;
}

.supportBackBtn{
display:none;
background:#334155;
border:none;
color:white;
padding:8px 14px;
border-radius:10px;
cursor:pointer;
font-family:tahoma;
}

.supportMessages{
flex:1;
overflow-y:auto;
background:#1e293b;
border-radius:18px;
padding:15px;
margin-bottom:15px;
display:flex;
flex-direction:column;
}

.supportPage .msg{
padding:14px;
border-radius:16px;
margin-bottom:12px;
max-width:80%;
line-height:30px;
word-break:break-word;
}

.supportPage .msg.admin{
background:#22c55e;
margin-left:auto;
}

.supportPage .msg.usermsg{
background:#334155;
margin-right:auto;
}

.supportPage .msg img{
max-width:240px;
border-radius:12px;
margin-top:10px;
display:block;
}

.supportPage .time{
font-size:11px;
opacity:0.7;
margin-top:8px;
display:flex;
gap:8px;
align-items:center;
flex-wrap:wrap;
}

.supportPage .action,
.supportPage .deleteBtn{
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

.supportPage .deleteForm{
display:inline;
margin:0;
}

.supportSendbox{
background:#1e293b;
padding:12px;
border-radius:18px;
}

.supportFormrow{
display:flex;
gap:10px;
align-items:flex-end;
background:#0f172a;
padding:10px;
border-radius:16px;
}

.supportSidebuttons{
display:flex;
flex-direction:column;
gap:6px;
}

.supportPage textarea{
flex:1;
min-height:52px;
max-height:180px;
padding:14px;
border:none;
border-radius:14px;
background:transparent;
color:white;
font-family:tahoma;
resize:none;
outline:none;
line-height:28px;
font-size:15px;
overflow-y:auto;
box-sizing:border-box;
}

.supportAttach{
width:42px;
height:42px;
background:#334155;
border-radius:12px;
display:flex;
align-items:center;
justify-content:center;
cursor:pointer;
font-size:18px;
}

.supportAttach input{
display:none;
}

.supportSendbtn{
width:42px;
height:42px;
border:none;
border-radius:12px;
background:#22c55e;
color:white;
font-size:18px;
cursor:pointer;
}

.supportPage .editbox{
margin-top:10px;
}

.supportPage .editbox textarea{
background:#0f172a;
min-height:80px;
width:100%;
}

.supportEditbtn{
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

.supportEmpty{
margin:auto;
color:#94a3b8;
font-size:18px;
text-align:center;
padding:20px;
}

.supportBackLink{
display:block;
background:#334155;
padding:14px;
border-radius:14px;
text-align:center;
text-decoration:none;
color:white;
margin-top:15px;
}

.supportFlashError{
background:#450a0a;
color:#fecaca;
padding:12px 14px;
border-radius:12px;
margin-bottom:12px;
font-size:13px;
}

.supportStatusBadge{
font-size:12px;
opacity:0.8;
}

@media(max-width:768px){

.supportPage{
height:100vh;
margin:0;
}

.supportSidebar{
width:100%;
border-left:none;
border-bottom:1px solid #334155;
max-height:none;
flex:1;
}

.supportChatbox{
display:none;
flex:1;
padding:10px;
}

.supportPage.chat-active .supportSidebar{
display:none;
}

.supportPage.chat-active .supportChatbox{
display:flex;
}

.supportBackBtn{
display:inline-block;
}

.supportMessages{
height:auto;
flex:1;
}

}

</style>

<?php if(!$supportEmbedded){ ?>
</head>
<body>
<?php } ?>

<div class="supportPage <?php echo $currentUser !== '' ? 'chat-active' : ''; ?>" id="supportPage">

<div class="supportSidebar" id="supportSidebar">

<h2>پیام های کاربران</h2>

<input
    type="text"
    class="supportSearch"
    id="supportSearch"
    placeholder="جستجوی کاربر...">

<?php foreach($data as $ticket){

    $hasUnread = supportTicketHasUnreadForAdmin($ticket);
    $ticketUser = $ticket['user'] ?? '';
    $isActive = $currentUser === $ticketUser;

?>

<a
    href="<?php echo htmlspecialchars(supportAdminUrl($ticketUser, $supportEmbedded), ENT_QUOTES, 'UTF-8'); ?>"
    class="supportUser <?php echo $isActive ? 'active' : ''; ?>"
    data-username="<?php echo htmlspecialchars($ticketUser, ENT_QUOTES, 'UTF-8'); ?>">

    <div class="supportUserTop">
        <span>👤</span>
        <?php if($hasUnread){ ?>
        <span class="supportRedDot" data-unread-dot="<?php echo htmlspecialchars($ticketUser, ENT_QUOTES, 'UTF-8'); ?>"></span>
        <?php } ?>
    </div>

    <?php echo htmlspecialchars($ticketUser, ENT_QUOTES, 'UTF-8'); ?>
    <br>
    <span class="supportStatusBadge">
        وضعیت: <?php echo htmlspecialchars($ticket['status'] ?? '-', ENT_QUOTES, 'UTF-8'); ?>
    </span>

</a>

<?php } ?>

<?php if(!$supportEmbedded){ ?>

<a href="index.php" class="supportBackLink">بازگشت</a>

<?php } ?>

</div>

<div class="supportChatbox" id="supportChatbox">

<?php if($currentUser === ''){ ?>

<div class="supportEmpty">یک کاربر را انتخاب کنید</div>

<?php } else { ?>

<?php if($supportError){ ?>
<div class="supportFlashError"><?php echo htmlspecialchars($supportError, ENT_QUOTES, 'UTF-8'); ?></div>
<?php } ?>

<div class="supportChatHeader">
    <button type="button" class="supportBackBtn" id="supportBackBtn">← لیست</button>
    <span>چت با: <?php echo htmlspecialchars($currentUser, ENT_QUOTES, 'UTF-8'); ?></span>
    <div class="supportChatHeaderActions">
        <a
            href="users.php?openProfile=<?php echo urlencode($currentUser); ?>"
            class="viewSubsBtn">
            📦 اشتراک‌ها
        </a>
    </div>
</div>

<div class="supportMessages" id="supportMessages">

<?php

foreach($data as $ticket){

    if(($ticket['user'] ?? '') !== $currentUser){
        continue;
    }

    if(empty($ticket['messages'])){
        break;
    }

    foreach($ticket['messages'] as $m){

        echo supportRenderMessageHtml($m, [
            'currentUser' => $currentUser,
            'embedded' => $supportEmbedded,
            'csrfField' => $csrfField,
            'editId' => $editId,
            'isAdmin' => true,
            'baseUrl' => $baseUrl
        ]);

    }

    break;

}

?>

</div>

<div class="supportSendbox">

<form method="POST" enctype="multipart/form-data" id="supportReplyForm">

    <?php echo $csrfField; ?>

    <input type="hidden" name="user" value="<?php echo htmlspecialchars($currentUser, ENT_QUOTES, 'UTF-8'); ?>">

    <div class="supportFormrow">

        <div class="supportSidebuttons">

            <label class="supportAttach">
                📎
                <input type="file" name="image" id="supportImage" accept="image/*">
            </label>

            <button type="submit" name="reply" class="supportSendbtn">➤</button>

        </div>

        <textarea
            name="message"
            id="supportMessage"
            placeholder="پاسخ پشتیبانی..."></textarea>

    </div>

</form>

</div>

<?php } ?>

</div>

</div>

<script>

(function(){

const supportPage = document.getElementById('supportPage');
const supportMessages = document.getElementById('supportMessages');
const supportMessage = document.getElementById('supportMessage');
const supportSearch = document.getElementById('supportSearch');
const supportBackBtn = document.getElementById('supportBackBtn');
const supportReplyForm = document.getElementById('supportReplyForm');
const currentUser = <?php echo json_encode($currentUser, JSON_UNESCAPED_UNICODE); ?>;
const pollUrl = 'support-api.php';
const listUrl = <?php echo json_encode(supportAdminUrl('', $supportEmbedded), JSON_UNESCAPED_UNICODE); ?>;

function scrollMessagesToBottom(force){

    if(!supportMessages){
        return;
    }

    const distance =
    supportMessages.scrollHeight -
    supportMessages.scrollTop -
    supportMessages.clientHeight;

    if(force || distance < 120){
        supportMessages.scrollTop = supportMessages.scrollHeight;
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
    const senderClass = msg.sender === 'admin' ? 'admin' : 'usermsg';

    wrap.className = 'msg ' + senderClass;
    wrap.dataset.msgId = msg.id || '';

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

if(supportMessages){

    supportMessages.querySelectorAll('.msg[data-timestamp]').forEach(function(node){

        const ts = parseInt(node.dataset.timestamp || '0', 10);

        if(ts > lastPollTimestamp){
            lastPollTimestamp = ts;
        }

    });

}

async function pollMessages(){

    if(!currentUser || !supportMessages){
        return;
    }

    const since = lastPollTimestamp || 0;
    const url = pollUrl + '?user=' + encodeURIComponent(currentUser) + '&since=' + since;

    try{

        const response = await fetch(url, {credentials: 'same-origin'});

        if(!response.ok){
            return;
        }

        const payload = await response.json();
        let added = false;

        (payload.messages || []).forEach(function(msg){

            if(supportMessages.querySelector('[data-msg-id="' + msg.id + '"]')){
                return;
            }

            const node = buildMessageNode(msg);
            node.dataset.timestamp = msg.timestamp || 0;
            supportMessages.appendChild(node);
            lastPollTimestamp = Math.max(lastPollTimestamp, msg.timestamp || 0);
            added = true;

        });

        if(added){
            scrollMessagesToBottom(false);
        }

    }
    catch(e){}

}

if(supportMessage){

    supportMessage.addEventListener('input', function(){

        this.style.height = '52px';
        this.style.height = (this.scrollHeight) + 'px';

    });

}

if(supportReplyForm){

    supportReplyForm.addEventListener('submit', function(e){

        const text = (supportMessage?.value || '').trim();
        const image = document.getElementById('supportImage');

        if(text === '' && (!image || image.files.length === 0)){
            e.preventDefault();
            alert('متن یا تصویر وارد کنید');
        }

    });

}

if(supportSearch){

    supportSearch.addEventListener('input', function(){

        const query = this.value.trim().toLowerCase();

        document.querySelectorAll('.supportUser[data-username]').forEach(function(item){

            const username = (item.dataset.username || '').toLowerCase();
            item.style.display = username.includes(query) ? 'block' : 'none';

        });

    });

}

if(supportBackBtn){

    supportBackBtn.addEventListener('click', function(){
        window.location.href = listUrl;
    });

}

scrollMessagesToBottom(true);

if(currentUser){
    setInterval(pollMessages, 8000);
}

})();

</script>

<?php if(!$supportEmbedded){ ?>
</body>
</html>
<?php } ?>
