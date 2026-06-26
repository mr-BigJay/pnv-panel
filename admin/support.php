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
$cssHref = '../support_ui.css?v=3';
$profileApiUrl = function_exists('pnvAdminUrl') ? pnvAdminUrl('user-profile.php') : 'user-profile.php';
$jsHref = '../support_ui.js';

if(!$supportEmbedded){
?>
<!DOCTYPE html>
<html lang="fa">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>پیام‌های کاربران</title>
<link rel="stylesheet" href="<?php echo htmlspecialchars($cssHref, ENT_QUOTES, 'UTF-8'); ?>">
<?php } else { ?>
<link rel="stylesheet" href="<?php echo htmlspecialchars($cssHref, ENT_QUOTES, 'UTF-8'); ?>">
<?php } ?>

<div class="supportPage <?php echo $supportEmbedded ? 'supportEmbedded' : ''; ?> <?php echo $currentUser !== '' ? 'chat-active' : ''; ?>" id="supportPage">

<aside class="msgSidebar" id="supportSidebar">

<div class="msgSidebarHead">
<h2>پیام‌های کاربران</h2>
<input type="text" class="msgSearch" id="supportSearch" placeholder="جستجو...">
</div>

<div class="msgList">

<?php if(count($data) === 0){ ?>
<div class="msgEmpty" style="padding:24px 12px;">
<div class="msgEmptyIcon">📭</div>
هنوز پیامی نیست<br>
کاربران از پنل خود پیام می‌فرستند
</div>
<?php } ?>

<?php foreach($data as $ticket){

    $ticketUser = $ticket['user'] ?? '';
    $isActive = $currentUser === $ticketUser;
    $unread = supportAdminUnreadCount($ticket);
    $preview = supportTicketPreview($ticket);
    $lastTs = supportTicketLastTimestamp($ticket);

?>

<a
    href="<?php echo htmlspecialchars(supportAdminUrl($ticketUser, $supportEmbedded), ENT_QUOTES, 'UTF-8'); ?>"
    class="msgConv <?php echo $isActive ? 'active' : ''; ?>"
    data-username="<?php echo htmlspecialchars($ticketUser, ENT_QUOTES, 'UTF-8'); ?>">

    <div class="msgAvatar"><?php echo htmlspecialchars(supportUserInitial($ticketUser), ENT_QUOTES, 'UTF-8'); ?></div>

    <div class="msgConvBody">
        <div class="msgConvTop">
            <span class="msgConvName"><?php echo htmlspecialchars($ticketUser, ENT_QUOTES, 'UTF-8'); ?></span>
            <span class="msgConvTime"><?php echo htmlspecialchars(supportRelativeTime($lastTs), ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
        <div class="msgConvPreview <?php echo $unread > 0 ? 'unread' : ''; ?>">
            <?php echo htmlspecialchars($preview, ENT_QUOTES, 'UTF-8'); ?>
        </div>
    </div>

    <?php if($unread > 0){ ?>
    <span class="msgBadge"><?php echo $unread > 9 ? '9+' : $unread; ?></span>
    <?php } ?>

</a>

<?php } ?>

</div>

<?php if(!$supportEmbedded){ ?>
<a href="<?php echo htmlspecialchars(function_exists('pnvAdminUrl') ? pnvAdminUrl('index.php') : 'index.php', ENT_QUOTES, 'UTF-8'); ?>" class="msgBack" style="margin:12px;text-align:center;display:block;">بازگشت به داشبورد</a>
<?php } ?>

</aside>

<div class="supportChatbox" id="supportChatbox">

<?php if($currentUser === ''){ ?>

<div class="msgEmpty" style="margin:auto;">
<div class="msgEmptyIcon">👈</div>
یک کاربر را از لیست انتخاب کنید
</div>

<?php } else { ?>

<header class="msgHeader">
<button type="button" class="supportBackBtn" id="supportBackBtn">← لیست</button>
<div class="msgAvatar"><?php echo htmlspecialchars(supportUserInitial($currentUser), ENT_QUOTES, 'UTF-8'); ?></div>
<div class="msgHeaderInfo">
<h2><?php echo htmlspecialchars($currentUser, ENT_QUOTES, 'UTF-8'); ?></h2>
<p>پاسخ به کاربر</p>
</div>
<div class="supportChatHeaderActions">
<button type="button" class="viewSubsBtn" onclick="openUserSubscriptions()">اشتراک‌ها</button>
</div>
</header>

<?php if($supportError){ ?>
<div class="msgFlash"><?php echo htmlspecialchars($supportError, ENT_QUOTES, 'UTF-8'); ?></div>
<?php } ?>

<div class="supportMessages" id="supportMessages">

<?php

$hasMessages = false;

foreach($data as $ticket){

    if(($ticket['user'] ?? '') !== $currentUser){
        continue;
    }

    if(empty($ticket['messages'])){
        break;
    }

    $hasMessages = true;

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

if(!$hasMessages){
    echo '<div class="msgEmpty"><div class="msgEmptyIcon">💬</div>هنوز پیامی رد و بدل نشده</div>';
}

?>

</div>

<footer class="supportSendbox">
<form method="POST" enctype="multipart/form-data" id="supportReplyForm" class="msgComposerInner">

<?php echo $csrfField; ?>
<input type="hidden" name="user" value="<?php echo htmlspecialchars($currentUser, ENT_QUOTES, 'UTF-8'); ?>">

<label class="msgIconBtn msgIconBtn--attach" title="تصویر">📎
<input type="file" name="image" id="supportImage" accept="image/*">
</label>

<textarea name="message" id="supportMessage" placeholder="پاسخ... (Enter ارسال)" rows="1"></textarea>

<button type="submit" name="reply" class="msgIconBtn msgIconBtn--send" title="ارسال">➤</button>

</form>
</footer>

<?php } ?>

</div>

</div>

<div id="profileHost"></div>

<script src="../support_ui.js?v=2"></script>
<script>
(function(){
    const supportMessages = document.getElementById('supportMessages');
    const supportMessage = document.getElementById('supportMessage');
    const supportSearch = document.getElementById('supportSearch');
    const supportBackBtn = document.getElementById('supportBackBtn');
    const supportReplyForm = document.getElementById('supportReplyForm');
    const currentUser = <?php echo json_encode($currentUser, JSON_UNESCAPED_UNICODE); ?>;
    const pollUrl = <?php echo json_encode(
        $supportEmbedded && function_exists('pnvAdminUrl')
            ? pnvAdminUrl('support-api.php')
            : 'support-api.php',
        JSON_UNESCAPED_UNICODE
    ); ?>;
    const listUrl = <?php echo json_encode(supportAdminUrl('', $supportEmbedded), JSON_UNESCAPED_UNICODE); ?>;
    const profileApiUrl = <?php echo json_encode($profileApiUrl, JSON_UNESCAPED_UNICODE); ?>;

    window.openUserSubscriptions = function(){
        if(!currentUser){
            return;
        }
        loadProfile(currentUser);
    };

    window.loadProfile = function(user){
        fetch(
            profileApiUrl + '?user=' + encodeURIComponent(user) + '&all=1',
            {credentials:'same-origin'}
        )
        .then(function(r){ return r.text(); })
        .then(function(html){
            document.getElementById('profileHost').innerHTML = html;
            document.getElementById('profileHost').style.display = 'block';
            document.body.style.overflow = 'hidden';
        })
        .catch(function(){
            alert('خطا در بارگذاری اشتراک‌ها');
        });
    };

    window.closeProfileModal = function(){
        document.getElementById('profileHost').innerHTML = '';
        document.getElementById('profileHost').style.display = 'none';
        document.body.style.overflow = '';
    };

    window.copySub = function(button){
        const input = button.previousElementSibling;
        if(!input){ return; }
        input.select();
        input.setSelectionRange(0, 99999);
        navigator.clipboard.writeText(input.value);
        alert('کپی شد');
    };

    document.addEventListener('keydown', function(e){
        if(e.key === 'Escape'){
            closeProfileModal();
        }
    });

    SupportUI.bindTextareaGrow(supportMessage);
    SupportUI.bindEnterToSend(supportMessage, supportReplyForm, true);
    SupportUI.bindFormGuard(supportReplyForm, supportMessage, 'supportImage');

    if(supportSearch){
        supportSearch.addEventListener('input', function(){
            const q = this.value.trim().toLowerCase();
            document.querySelectorAll('.msgConv[data-username]').forEach(function(item){
                const name = (item.dataset.username || '').toLowerCase();
                item.style.display = name.includes(q) ? 'flex' : 'none';
            });
        });
    }

    if(supportBackBtn){
        supportBackBtn.addEventListener('click', function(){
            window.location.href = listUrl;
        });
    }

    if(currentUser){
        SupportUI.initPolling({
            chatEl: supportMessages,
            pollUrl: pollUrl,
            getParams: function(since){
                return '?user=' + encodeURIComponent(currentUser) + '&since=' + (since || 0);
            },
            classMap: {admin:'admin', user:'usermsg'},
            interval: 5000
        });
    }
})();
</script>

<?php if(!$supportEmbedded){ ?>
</body>
</html>
<?php } ?>
