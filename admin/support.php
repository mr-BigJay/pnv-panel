<?php

if(!isset($supportEmbedded)){
    $supportEmbedded = false;
}

require_once __DIR__ . '/../support_lib.php';

if(!function_exists('supportUsernamesMatch')){

    function supportUsernamesMatch($left, $right){

        $left = trim((string)$left);
        $right = trim((string)$right);

        if($left === '' || $right === ''){
            return false;
        }

        return strcasecmp($left, $right) === 0;

    }

}

if(!function_exists('supportGetUserProfileSummary')){

    function supportGetUserProfileSummary($username){

        $username = trim((string)$username);
        $summary = [
            'username' => $username,
            'mobile' => '-',
            'exists' => false,
        ];

        if($username === ''){
            return $summary;
        }

        $usersFile = dirname(__DIR__) . '/db/users.json';

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

            $stored = trim((string)($user['username'] ?? ''));

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

}

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
$currentUser = trim((string)($_GET['user'] ?? ''));
if($currentUser !== '' && function_exists('supportResolveTicketUsername')){
    $resolvedUser = supportResolveTicketUsername($data, $currentUser);
    if($resolvedUser !== ''){
        $currentUser = $resolvedUser;
    }
}
$editId = $_GET['edit'] ?? '';
$supportError = $actionResult['error'] ?? '';

if($supportError === '' && !empty($_SESSION['support_flash_error'])){
    $supportError = (string)$_SESSION['support_flash_error'];
    unset($_SESSION['support_flash_error']);
}
$baseUrl = supportAdminUrl($currentUser, $supportEmbedded);
$formAction = function_exists('supportAdminFormAction')
    ? supportAdminFormAction($currentUser, $supportEmbedded)
    : $baseUrl;
$adminBase = defined('PNV_ADMIN_BASE') ? rtrim(PNV_ADMIN_BASE, '/') : '/bigjay_controller';
$dashboardUrl = $adminBase . '/index.php';
$chatAssetVer = '18';
$chatAssetBase = '/assets/support-chat/';
$shellCssHref = $chatAssetBase . 'pnv-support-shell.css?v=' . $chatAssetVer;
$legacyJsHref = '/support_ui.js?v=48';
$shellJsHref = $chatAssetBase . 'pnv-support-shell.js?v=' . $chatAssetVer;
$voiceJsHref = $chatAssetBase . 'pnv-support-voice.js?v=' . $chatAssetVer;
$adapterJsHref = $chatAssetBase . 'pnv-support-adapter.js?v=' . $chatAssetVer;
$profileCssHref = $chatAssetBase . 'pnv-support-profile.css?v=' . $chatAssetVer;
$profileApiUrl = function_exists('pnvAdminUrl') ? pnvAdminUrl('user-profile.php') : 'user-profile.php';
$usersApiUrl = function_exists('pnvAdminUrl') ? pnvAdminUrl('support-users-api.php') : 'support-users-api.php';

if(!$supportEmbedded){
?>
<!DOCTYPE html>
<html lang="fa">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, interactive-widget=resizes-content">
<meta http-equiv="Cache-Control" content="no-store, max-age=0">
<title>پیام‌های کاربران</title>
<link rel="stylesheet" href="<?php echo htmlspecialchars($shellCssHref, ENT_QUOTES, 'UTF-8'); ?>">
<link rel="stylesheet" href="<?php echo htmlspecialchars($profileCssHref, ENT_QUOTES, 'UTF-8'); ?>">
<?php } else { ?>
<link rel="stylesheet" href="<?php echo htmlspecialchars($shellCssHref, ENT_QUOTES, 'UTF-8'); ?>">
<link rel="stylesheet" href="<?php echo htmlspecialchars($profileCssHref, ENT_QUOTES, 'UTF-8'); ?>">
<?php } ?>

<div class="supportPage pnv-chat <?php echo $supportEmbedded ? 'supportEmbedded' : ''; ?> <?php echo $currentUser !== '' ? 'chat-active' : ''; ?>" id="supportPage">

<aside class="msgSidebar" id="supportSidebar">

<div class="msgSidebarHead">
<div class="msgSidebarHeadRow">
<a href="<?php echo htmlspecialchars($dashboardUrl, ENT_QUOTES, 'UTF-8'); ?>" class="msgMobileDashBack" aria-label="بازگشت به داشبورد"><span class="msgMobileDashBackIcon" aria-hidden="true">←</span><span class="msgMobileDashBackLabel">داشبورد</span></a>
<h2 class="msgSidebarTitle">پیام‌های کاربران</h2>
<button type="button" class="pnv-chat-theme-toggle" id="pnvChatThemeToggle" aria-label="تغییر تم"><span class="pnv-chat-theme-toggleLabel">حالت روشن</span></button>
</div>
<div class="msgSidebarControls">
<div class="msgSearchWrap">
<input type="text" class="msgSearch" id="supportSearch" placeholder="جستجو با نام کاربری یا شماره موبایل..." autocomplete="off">
<div class="msgUserSearchResults" id="supportUserResults"></div>
</div>
</div>
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
    $isActive = function_exists('supportUsernamesMatch')
        ? supportUsernamesMatch($currentUser, $ticketUser)
        : ($currentUser === $ticketUser);
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
<a href="<?php echo htmlspecialchars($dashboardUrl, ENT_QUOTES, 'UTF-8'); ?>" class="msgBack" style="margin:12px;text-align:center;display:block;">بازگشت به داشبورد</a>
<?php } ?>

</aside>

<div class="supportChatbox" id="supportChatbox">

<?php if($currentUser === ''){ ?>

<div class="msgEmpty" style="margin:auto;">
<div class="msgEmptyIcon">👈</div>
یک کاربر را از لیست انتخاب کنید
</div>

<?php } else {

$profileSummary = supportGetUserProfileSummary($currentUser);

?>

<div class="supportChatTop" id="supportChatTop">
<header class="msgHeader">
<button type="button" class="supportBackBtn pnv-topbar-back" id="supportBackBtn" aria-label="بازگشت به لیست">←</button>
<div class="msgAvatar"><?php echo htmlspecialchars(supportUserInitial($currentUser), ENT_QUOTES, 'UTF-8'); ?></div>
<div class="msgHeaderInfo">
<h2><?php echo htmlspecialchars($currentUser, ENT_QUOTES, 'UTF-8'); ?></h2>
<p><?php echo htmlspecialchars($profileSummary['mobile'] !== '-' ? $profileSummary['mobile'] : '—', ENT_QUOTES, 'UTF-8'); ?></p>
</div>
<div class="supportChatHeaderActions">
<button type="button" class="viewSubsBtn pnv-topbar-action" onclick="openUserSubscriptions()" title="اشتراک‌ها" aria-label="اشتراک‌ها">📋</button>
</div>
</header>
</div>

<?php if($supportError){ ?>
<div class="msgFlash"><?php echo htmlspecialchars($supportError, ENT_QUOTES, 'UTF-8'); ?></div>
<?php } ?>

<div class="supportMessages" id="supportMessages">

<?php

$hasMessages = false;

foreach($data as $ticket){

    if(
        function_exists('supportUsernamesMatch')
            ? !supportUsernamesMatch($ticket['user'] ?? '', $currentUser)
            : ($ticket['user'] ?? '') !== $currentUser
    ){
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
<form method="POST" enctype="multipart/form-data" id="supportReplyForm" class="msgComposerInner" action="<?php echo htmlspecialchars($formAction, ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off">

<?php echo $csrfField; ?>
<input type="hidden" name="reply" value="1">
<input type="hidden" name="user" value="<?php echo htmlspecialchars($currentUser, ENT_QUOTES, 'UTF-8'); ?>">

<div class="pnv-voice-recording" hidden>
<span class="pnv-voice-recording-dot" aria-hidden="true"></span>
<span class="pnv-voice-recording-time">0:00</span>
<span class="pnv-voice-recording-label">در حال ضبط…</span>
<button type="button" class="pnv-voice-recording-cancel" aria-label="لغو ضبط">لغو</button>
<button type="button" class="pnv-voice-recording-stop" aria-label="ارسال پیام صوتی">ارسال</button>
</div>
<div class="pnv-voice-preview" hidden>
<button type="button" class="pnv-voice-preview-play" aria-label="پخش پیش‌نمایش">▶</button>
<audio preload="metadata"></audio>
<span class="pnv-voice-preview-duration">0:00</span>
<button type="button" class="pnv-voice-preview-send">ارسال</button>
<button type="button" class="pnv-voice-preview-cancel">لغو</button>
</div>
<div class="msgComposerRow">
<button type="button" class="msgIconBtn msgIconBtn--attach" id="attachBtnAdmin" title="تصویر" aria-label="پیوست تصویر">📎</button>
<input type="file" name="image" id="supportImage" class="msgFileInput" accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp">
<input type="file" name="audio" id="supportAudio" class="msgFileInput" accept="audio/webm,audio/ogg,video/webm">

<textarea name="message" id="supportMessage" placeholder="ارسال پیام . . . ." rows="1"></textarea>

<div class="msgComposerAction">
<button type="button" class="msgIconBtn msgIconBtn--mic" id="supportMicAdmin" title="پیام صوتی" aria-label="ضبط پیام صوتی"><span class="msgIconGlyph" aria-hidden="true"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 14a3 3 0 0 0 3-3V6a3 3 0 1 0-6 0v5a3 3 0 0 0 3 3zm5-3a5 5 0 0 1-10 0H5a7 7 0 0 0 6 6.92V21h2v-3.08A7 7 0 0 0 19 11h-2z"/></svg></span></button>
<button type="submit" class="msgIconBtn msgIconBtn--send" id="supportSendAdmin" title="ارسال" aria-label="ارسال" hidden><span class="msgIconGlyph" aria-hidden="true"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M2.01 21 23 12 2.01 3 2 10l15 2-15 2z"/></svg></span></button>
</div>
</div>

</form>
</footer>

<?php } ?>

</div>

</div>

<div id="profileHost"></div>

<script src="<?php echo htmlspecialchars($legacyJsHref, ENT_QUOTES, 'UTF-8'); ?>"></script>
<script src="<?php echo htmlspecialchars($shellJsHref, ENT_QUOTES, 'UTF-8'); ?>"></script>
<script src="<?php echo htmlspecialchars($voiceJsHref, ENT_QUOTES, 'UTF-8'); ?>"></script>
<script src="<?php echo htmlspecialchars($adapterJsHref, ENT_QUOTES, 'UTF-8'); ?>"></script>
<script>
(function(){
    const currentUser = <?php echo json_encode($currentUser, JSON_UNESCAPED_UNICODE); ?>;
    const pollUrl = <?php echo json_encode(
        $supportEmbedded && function_exists('pnvAdminUrl')
            ? pnvAdminUrl('support-api.php')
            : 'support-api.php',
        JSON_UNESCAPED_UNICODE
    ); ?>;
    const usersApiUrl = <?php echo json_encode($usersApiUrl, JSON_UNESCAPED_UNICODE); ?>;
    const listUrl = <?php echo json_encode(supportAdminUrl('', $supportEmbedded), JSON_UNESCAPED_UNICODE); ?>;
    const profileApiUrl = <?php echo json_encode($profileApiUrl, JSON_UNESCAPED_UNICODE); ?>;

    window.openUserSubscriptions = function(){
        if(!currentUser){
            return;
        }
        loadProfile(currentUser);
    };

    window.loadProfile = function(user, page){
        page = parseInt(page, 10) || 1;
        var url = profileApiUrl + '?user=' + encodeURIComponent(user) + '&p=' + page;
        fetch(url, {credentials:'same-origin'})
        .then(function(r){
            if(!r.ok){
                throw new Error('HTTP ' + r.status);
            }
            return r.text();
        })
        .then(function(html){
            if(!html || !String(html).trim()){
                throw new Error('empty response');
            }
            var host = document.getElementById('profileHost');
            if(!host){
                throw new Error('profileHost missing');
            }
            host.innerHTML = html;
            host.style.display = 'block';
            document.body.style.overflow = 'hidden';
        })
        .catch(function(err){
            console.error('support subscriptions:', err);
            alert('خطا در بارگذاری اشتراک‌ها');
        });
    };

    window.closeProfileModal = function(){
        document.getElementById('profileHost').innerHTML = '';
        document.getElementById('profileHost').style.display = 'none';
        document.body.style.overflow = '';
    };

    window.copySub = function(button){
        const input = button.parentElement
            ? button.parentElement.querySelector('input')
            : button.previousElementSibling;
        if(!input){ return; }
        input.select();
        input.setSelectionRange(0, 99999);
        navigator.clipboard.writeText(input.value);
        alert('کپی شد');
    };

    window.clearSubLink = function(button){
        const user = button.getAttribute('data-user') || '';
        const tracking = button.getAttribute('data-tracking') || '';
        const timestamp = button.getAttribute('data-timestamp') || '0';

        if(!user || !tracking){
            alert('اطلاعات اشتراک ناقص است');
            return;
        }

        if(!confirm('لینک این اشتراک از پنل کاربر حذف شود؟\nسابقه پرداخت باقی می‌ماند.')){
            return;
        }

        button.disabled = true;
        button.textContent = '...';

        const body = new URLSearchParams();
        body.set('clear_link', '1');
        body.set('user', user);
        body.set('tracking', tracking);
        body.set('timestamp', timestamp);

        fetch(profileApiUrl, {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'},
            body: body.toString(),
            credentials: 'same-origin'
        })
        .then(function(res){ return res.json(); })
        .then(function(data){
            if(!data || !data.ok){
                alert((data && data.error) ? data.error : 'حذف لینک ناموفق بود');
                button.disabled = false;
                button.textContent = 'حذف';
                return;
            }
            alert(data.message || 'لینک حذف شد');
            loadProfile(user);
        })
        .catch(function(){
            alert('خطا در ارتباط با سرور');
            button.disabled = false;
            button.textContent = 'حذف';
        });
    };

    (function bindProfileModalUi(){
        if(window.__pnvProfileModalUiBound){
            return;
        }
        window.__pnvProfileModalUiBound = true;

        document.addEventListener('click', function(e){
            var menuBtn = e.target.closest('.subCardMenuBtn');
            if(menuBtn){
                e.preventDefault();
                e.stopPropagation();
                var wrap = menuBtn.closest('.subCardMenuWrap');
                document.querySelectorAll('.subCardMenuWrap.is-open').forEach(function(openWrap){
                    if(openWrap !== wrap){
                        openWrap.classList.remove('is-open');
                    }
                });
                if(wrap){
                    wrap.classList.toggle('is-open');
                }
                return;
            }

            if(!e.target.closest('.subCardMenuWrap')){
                document.querySelectorAll('.subCardMenuWrap.is-open').forEach(function(openWrap){
                    openWrap.classList.remove('is-open');
                });
            }

            var copyBtn = e.target.closest('.subLinkCopyBtn');
            if(!copyBtn){
                return;
            }
            e.preventDefault();
            var field = copyBtn.closest('.subLinkField');
            var input = field ? field.querySelector('.subLinkInput') : null;
            var tip = field ? field.querySelector('.subLinkCopyTip') : null;
            if(!input || !input.value){
                return;
            }
            var text = input.value;
            var done = function(){
                if(tip){
                    tip.hidden = false;
                    setTimeout(function(){ tip.hidden = true; }, 1600);
                }
                copyBtn.classList.add('is-copied');
                setTimeout(function(){ copyBtn.classList.remove('is-copied'); }, 1600);
            };
            if(navigator.clipboard && navigator.clipboard.writeText){
                navigator.clipboard.writeText(text).then(done).catch(function(){
                    input.select();
                    try{ document.execCommand('copy'); done(); }catch(err){}
                });
            }else{
                input.select();
                try{ document.execCommand('copy'); done(); }catch(err){}
            }
        });
    })();

    document.addEventListener('keydown', function(e){
        if(e.key === 'Escape'){
            closeProfileModal();
        }
    });

    PNVSupportChat.init({
        mode: 'admin',
        rootEl: document.getElementById('supportPage'),
        containerEl: document.querySelector('.content-support') || document.getElementById('supportPage'),
        messagesEl: document.getElementById('supportMessages'),
        textareaEl: document.getElementById('supportMessage'),
        formEl: document.getElementById('supportReplyForm'),
        chatTopEl: document.getElementById('supportChatTop'),
        searchEl: document.getElementById('supportSearch'),
        userResultsEl: document.getElementById('supportUserResults'),
        backBtnEl: document.getElementById('supportBackBtn'),
        imageInputId: 'supportImage',
        attachBtnId: 'attachBtnAdmin',
        audioInputId: 'supportAudio',
        micBtnEl: document.getElementById('supportMicAdmin'),
        sendBtnEl: document.getElementById('supportSendAdmin'),
        currentUser: currentUser,
        pollUrl: pollUrl,
        usersApiUrl: usersApiUrl,
        listUrl: listUrl
    });
})();
</script>

<?php if(!$supportEmbedded){ ?>
</body>
</html>
<?php } ?>
