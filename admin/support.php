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
$cssHref = '/support_ui.css?v=39';
$profileApiUrl = function_exists('pnvAdminUrl') ? pnvAdminUrl('user-profile.php') : 'user-profile.php';
$usersApiUrl = function_exists('pnvAdminUrl') ? pnvAdminUrl('support-users-api.php') : 'support-users-api.php';
$jsHref = '/support_ui.js?v=39';

if(is_file(__DIR__ . '/../profile_lib.php')){
    require_once __DIR__ . '/../profile_lib.php';
    if(function_exists('profileLoadUsers')){
        profileLoadUsers();
    }
}

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
<div class="msgSidebarHeadRow">
<a href="<?php echo htmlspecialchars(function_exists('pnvAdminUrl') ? pnvAdminUrl('index.php') : 'index.php', ENT_QUOTES, 'UTF-8'); ?>" class="msgMobileDashBack">← داشبورد</a>
<h2>پیام‌های کاربران <span class="msgSidebarCount"><?php echo count($data); ?></span></h2>
</div>
<div class="msgSearchWrap">
<input type="text" class="msgSearch" id="supportSearch" placeholder="جستجو با نام کاربری یا شماره موبایل..." autocomplete="off">
<div class="msgUserSearchResults" id="supportUserResults"></div>
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

    if(!is_array($ticket)){
        continue;
    }

    $ticketUser = trim((string)($ticket['user'] ?? ''));

    if($ticketUser === ''){
        continue;
    }

    $isActive = $currentUser === $ticketUser;
    $unread = supportAdminUnreadCount($ticket);
    $preview = supportTicketPreview($ticket);
    $lastTs = supportTicketLastTimestamp($ticket);
    $listTime = supportRelativeTime($lastTs);

    if($listTime === ''){
        $listTime = '—';
    }

    if($preview === ''){
        $preview = 'پیام';
    }

?>

<a
    href="<?php echo supportSafeHtml(supportAdminUrl($ticketUser, $supportEmbedded)); ?>"
    class="msgConv <?php echo $isActive ? 'active' : ''; ?>"
    data-username="<?php echo supportSafeHtml($ticketUser); ?>">

    <?php
    try{
        echo supportRenderConvAvatarHtml($ticketUser);
    }catch(Throwable $e){
        echo '<div class="msgAvatar">' . supportSafeHtml(supportUserInitial($ticketUser)) . '</div>';
    }
    ?>

    <div class="msgConvBody">
        <div class="msgConvTop">
            <span class="msgConvName"><?php echo supportSafeHtml($ticketUser); ?></span>
            <span class="msgConvTime"><?php echo supportSafeHtml($listTime); ?></span>
        </div>
        <div class="msgConvPreview <?php echo $unread > 0 ? 'unread' : ''; ?>">
            <?php echo supportSafeHtml($preview); ?>
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
<?php
try{
    echo supportRenderHeaderAvatarHtml($currentUser);
}catch(Throwable $e){
    echo '<button type="button" class="msgAvatar msgAvatar--header" id="supportHeaderAvatar" aria-label="نمایش عکس پروفایل">' . supportSafeHtml(supportUserInitial($currentUser)) . '</button>';
}
?>
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

        if(!is_array($m)){
            continue;
        }

        try{
            echo supportRenderMessageHtml($m, [
                'currentUser' => $currentUser,
                'embedded' => $supportEmbedded,
                'csrfField' => $csrfField,
                'editId' => $editId,
                'isAdmin' => true,
                'baseUrl' => $baseUrl
            ]);
        }catch(Throwable $e){
            $fallback = supportSafeHtml(supportExtractMessageText($m) ?: 'پیام');
            echo '<div class="msgRow msgRow--user"><div class="msgBubble msg usermsg"><div class="msgText">' . $fallback . '</div></div></div>';
        }

    }

    break;

}

if(!$hasMessages){
    echo '<div class="msgEmpty"><div class="msgEmptyIcon">💬</div>هنوز پیامی رد و بدل نشده</div>';
}

?>

</div>

<footer class="supportSendbox">
<form method="POST" enctype="multipart/form-data" id="supportReplyForm" class="msgComposerInner" action="<?php echo htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8'); ?>">

<?php echo $csrfField; ?>
<input type="hidden" name="reply" value="1">
<input type="hidden" name="user" value="<?php echo htmlspecialchars($currentUser, ENT_QUOTES, 'UTF-8'); ?>">

<div class="msgComposerRow">
<button type="button" class="msgIconBtn msgIconBtn--attach" id="attachBtnAdmin" title="تصویر" aria-label="پیوست تصویر">📎</button>
<input type="file" name="image" id="supportImage" class="msgFileInput" accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp">

<textarea name="message" id="supportMessage" placeholder="ارسال پیام . . . ." rows="1"></textarea>

<button type="submit" class="msgIconBtn msgIconBtn--send" title="ارسال" aria-label="ارسال">➤</button>
</div>

</form>
</footer>

<?php } ?>

</div>

</div>

<div id="profileHost"></div>

<div class="supportAvatarLightbox" id="supportAvatarLightbox" hidden aria-hidden="true">
<div class="supportAvatarLightbox__backdrop" data-close="1"></div>
<div class="supportAvatarLightbox__card" id="supportAvatarLightboxCard" aria-hidden="true"></div>
</div>

<script src="<?php echo htmlspecialchars($jsHref, ENT_QUOTES, 'UTF-8'); ?>"></script>
<script>
(function(){
    const supportMessages = document.getElementById('supportMessages');
    const supportMessage = document.getElementById('supportMessage');
    const supportSearch = document.getElementById('supportSearch');
    const supportUserResults = document.getElementById('supportUserResults');
    const supportBackBtn = document.getElementById('supportBackBtn');
    const supportReplyForm = document.getElementById('supportReplyForm');
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
    let userSearchTimer = null;
    let userSearchRequest = 0;

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

    function closeSupportAvatarLightbox(){
        const box = document.getElementById('supportAvatarLightbox');
        const card = document.getElementById('supportAvatarLightboxCard');

        if(!box){
            return;
        }

        box.hidden = true;
        box.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('supportAvatarLightboxOpen');

        if(card){
            card.innerHTML = '';
        }
    }

    function initSupportHeaderAvatar(){
        const btn = document.getElementById('supportHeaderAvatar');
        const box = document.getElementById('supportAvatarLightbox');
        const card = document.getElementById('supportAvatarLightboxCard');

        if(!btn || !box || !card){
            return;
        }

        btn.addEventListener('click', function(){
            card.innerHTML = btn.innerHTML;
            box.hidden = false;
            box.setAttribute('aria-hidden', 'false');
            document.body.classList.add('supportAvatarLightboxOpen');
        });

        box.addEventListener('click', function(e){
            if(e.target && e.target.getAttribute('data-close') === '1'){
                closeSupportAvatarLightbox();
            }
        });
    }

    initSupportHeaderAvatar();

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
                button.textContent = 'حذف لینک';
                return;
            }
            alert(data.message || 'لینک حذف شد');
            loadProfile(user);
        })
        .catch(function(){
            alert('خطا در ارتباط با سرور');
            button.disabled = false;
            button.textContent = 'حذف لینک';
        });
    };

    document.addEventListener('keydown', function(e){
        if(e.key === 'Escape'){
            closeSupportAvatarLightbox();
            closeProfileModal();
        }
    });

    SupportUI.bindTextareaGrow(supportMessage);
    SupportUI.bindEnterToSend(supportMessage, supportReplyForm, true);
    SupportUI.bindFormGuard(supportReplyForm, supportMessage, 'supportImage');
    if(supportReplyForm){
        SupportUI.bindComposerFocus(supportMessage, supportReplyForm);
        SupportUI.bindImageAttach(supportReplyForm, 'supportImage', 'attachBtnAdmin');
        SupportUI.bindMessageActions({
            chatEl: supportMessages,
            form: supportReplyForm,
            role: 'admin'
        });
    }

    function hideUserSearchResults(){
        if(!supportUserResults){
            return;
        }
        supportUserResults.classList.remove('is-open');
        supportUserResults.innerHTML = '';
    }

    function openUserChat(username){
        if(!username){
            return;
        }
        const sep = listUrl.indexOf('?') >= 0 ? '&' : '?';
        window.location.href = listUrl + sep + 'user=' + encodeURIComponent(username);
    }

    function renderUserSearchResults(users){
        if(!supportUserResults){
            return;
        }

        supportUserResults.innerHTML = '';

        if(!users || users.length === 0){
            supportUserResults.innerHTML = '<div class="msgUserSearchEmpty">کاربری یافت نشد</div>';
            supportUserResults.classList.add('is-open');
            return;
        }

        users.forEach(function(user){
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'msgUserSearchItem';
            btn.innerHTML =
                '<span class="msgUserSearchName">' + escapeHtml(user.username || '') + '</span>' +
                '<span class="msgUserSearchMobile">' + escapeHtml(user.mobile || 'بدون موبایل') + '</span>';
            btn.addEventListener('click', function(){
                openUserChat(user.username || '');
            });
            supportUserResults.appendChild(btn);
        });

        supportUserResults.classList.add('is-open');
    }

    function escapeHtml(value){
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function searchUsersByQuery(query){
        if(!supportUserResults){
            return;
        }

        const requestId = ++userSearchRequest;

        fetch(
            usersApiUrl + '?q=' + encodeURIComponent(query),
            {credentials:'same-origin'}
        )
        .then(function(r){ return r.json(); })
        .then(function(data){
            if(requestId !== userSearchRequest){
                return;
            }
            renderUserSearchResults(data.users || []);
        })
        .catch(function(){
            if(requestId !== userSearchRequest){
                return;
            }
            hideUserSearchResults();
        });
    }

    if(supportSearch){
        supportSearch.addEventListener('input', function(){
            const q = this.value.trim();
            const qLower = q.toLowerCase();

            document.querySelectorAll('.msgConv[data-username]').forEach(function(item){
                const name = (item.dataset.username || '').toLowerCase();
                item.style.display = name.includes(qLower) ? 'flex' : 'none';
            });

            clearTimeout(userSearchTimer);

            if(q.length < 2){
                hideUserSearchResults();
                return;
            }

            userSearchTimer = setTimeout(function(){
                searchUsersByQuery(q);
            }, 250);
        });

        supportSearch.addEventListener('keydown', function(e){
            if(e.key === 'Escape'){
                hideUserSearchResults();
            }
        });
    }

    document.addEventListener('click', function(e){
        if(
            supportUserResults
            && supportSearch
            && !supportUserResults.contains(e.target)
            && e.target !== supportSearch
        ){
            hideUserSearchResults();
        }
    });

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
            actionMeta: {isAdmin: true, ownSender: 'admin'},
            interval: 5000
        });
    }
})();
</script>

<?php if(!$supportEmbedded){ ?>
</body>
</html>
<?php } ?>
