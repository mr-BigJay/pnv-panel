<?php

require_once __DIR__ . '/auth.php';
foreach ([__DIR__ . '/admin_nav.php', __DIR__ . '/../admin/admin_nav.php'] as $__navFile) {
    if (is_file($__navFile)) {
        require_once $__navFile;
        break;
    }
}
if(!function_exists('adminQuickNav')){
    function adminQuickNav($active = ''){}
    function adminQuickNavStyles(){}
    function adminBottomNavStyles(){}
    function adminBottomNav($options = []){}
    function adminBottomNavScript(){}
}
require_once "functions.php";

pnvAdminRequireAuth();

$pnvRootDir = dirname(__DIR__);
$usersFile = $pnvRootDir . '/db/users.json';
if (!is_file($usersFile) && is_file(__DIR__ . '/../db/users.json')) {
    $usersFile = __DIR__ . '/../db/users.json';
}

if(!file_exists($usersFile)){

file_put_contents(
$usersFile,
"[]"
);

}

$allUsers = json_decode(
file_get_contents($usersFile),
true
);

if(!is_array($allUsers)){
$allUsers = [];
}

$users = $allUsers;

if(isset($_GET['backup'])){

header('Content-Type: application/json');

header(
'Content-Disposition: attachment; filename="users-backup.json"'
);

readfile($usersFile);

exit;
}

if(isset($_POST['changepass'])){

$id =
intval($_POST['userid']);

$newpass =
trim($_POST['newpassword']);

if(

isset($allUsers[$id])

&&

strlen($newpass) >= 8

){

$newHash =
password_hash(
$newpass,
PASSWORD_DEFAULT
);

$allUsers[$id]['password'] =
$newHash;

file_put_contents(
$usersFile,
json_encode(
$allUsers,
JSON_UNESCAPED_UNICODE |
JSON_PRETTY_PRINT
),
LOCK_EX
);

}

header("Location: " . pnvAdminUrl('users.php'));
exit;

}

if(isset($_POST['changemobile'])){

$id =
intval($_POST['userid']);

$newmobile =
trim($_POST['newmobile']);

if(

isset($allUsers[$id])

&&

preg_match(
'/^09[0-9]{9}$/',
$newmobile
)

){

$allUsers[$id]['mobile'] =
$newmobile;

file_put_contents(
$usersFile,
json_encode(
$allUsers,
JSON_UNESCAPED_UNICODE |
JSON_PRETTY_PRINT
),
LOCK_EX
);

}

header("Location: " . pnvAdminUrl('users.php'));
exit;
}

if(isset($_POST['changereferrer'])){

$id =
intval($_POST['userid']);

$newref =
trim($_POST['newreferrer']);

if(isset($allUsers[$id])){

$allUsers[$id]['referrer'] =
$newref;

file_put_contents(
$usersFile,
json_encode(
$allUsers,
JSON_UNESCAPED_UNICODE |
JSON_PRETTY_PRINT
),
LOCK_EX
);

}

header("Location: " . pnvAdminUrl('users.php'));
exit;
}

if(isset($_GET['delete'])){

$id =
intval($_GET['delete']);

if(isset($allUsers[$id])){

unset($allUsers[$id]);

$allUsers =
array_values($allUsers);

file_put_contents(
$usersFile,
json_encode(
$allUsers,
JSON_UNESCAPED_UNICODE |
JSON_PRETTY_PRINT
),
LOCK_EX
);

}

header("Location: " . pnvAdminUrl('users.php'));
exit;
}

$search =
trim($_GET['search'] ?? '');

$openProfile =
trim($_GET['openProfile'] ?? '');

if($search != ''){

$users = array_filter($users,function($u) use ($search){

return
stripos($u['username'] ?? '',$search)!==false
||
stripos($u['mobile'] ?? '',$search)!==false
||
stripos($u['referrer'] ?? '',$search)!==false;

});

$users =
array_values($users);

}

$perPage = 50;

$page =
intval($_GET['p'] ?? 1);

if($page < 1){
$page = 1;
}

$totalUsers =
count($users);

$totalPages =
ceil($totalUsers / $perPage);

$start =
($page - 1) * $perPage;

$users =
array_slice(
$users,
$start,
$perPage
);

if(!function_exists('pnvUsersAvatarInitial')){
    function pnvUsersAvatarInitial($username){
        $username = trim((string)$username);
        if($username === ''){
            return '?';
        }
        if(function_exists('mb_substr')){
            return mb_strtoupper(mb_substr($username, 0, 1, 'UTF-8'), 'UTF-8');
        }
        return strtoupper(substr($username, 0, 1));
    }

    function pnvUsersAvatarHue($username){
        return abs(crc32((string)$username)) % 360;
    }
}

$totalAllUsers = count($allUsers);
$todayRegistrations = 0;

foreach($allUsers as $u){
    if(
        !empty($u['created_at'])
        && function_exists('pnvIsTodayTehran')
        && pnvIsTodayTehran($u['created_at'])
    ){
        $todayRegistrations++;
    }
}

?>

<!DOCTYPE html>

<html lang="fa">

<head>

<meta charset="UTF-8">

<meta name="viewport"
content="width=device-width, initial-scale=1.0">

<title>

لیست کاربران

</title>

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

.box{
max-width:950px;
margin:auto;
}

h2{
text-align:center;
margin-bottom:20px;
font-size:24px;
}

.backTop{
display:block;
background:#334155;
padding:12px;
border-radius:10px;
color:white;
text-decoration:none;
text-align:center;
margin-bottom:18px;
font-size:14px;
}

.topbar{
display:flex;
flex-direction:column;
gap:12px;
margin-bottom:16px;
}

.usersToolbar{
display:flex;
gap:10px;
align-items:stretch;
}

.searchBox{
flex:1;
background:#1e293b;
padding:10px 12px;
border-radius:14px;
border:1px solid #334155;
display:flex;
align-items:center;
gap:10px;
}

.searchBox svg{
flex:0 0 auto;
opacity:.65;
}

.searchBox form{
flex:1;
min-width:0;
}

.searchBox input{
width:100%;
padding:10px 0;
border:none;
background:transparent;
font-size:14px;
color:#fff;
outline:none;
font-family:tahoma;
}

.searchBox input::placeholder{
color:#94a3b8;
}

.backupBtn{
display:inline-flex;
align-items:center;
justify-content:center;
gap:6px;
background:#1e293b;
border:1px solid #2563eb;
padding:0 14px;
border-radius:14px;
color:#93c5fd;
text-decoration:none;
font-size:13px;
white-space:nowrap;
min-height:52px;
}

.usersStats{
display:flex;
flex-wrap:wrap;
gap:8px;
margin-bottom:16px;
}

.usersStatPill{
display:inline-flex;
align-items:center;
gap:6px;
padding:8px 12px;
border-radius:999px;
background:#1e293b;
border:1px solid #334155;
font-size:12px;
color:#cbd5e1;
}

.usersStatPill strong{
color:#fff;
font-weight:700;
}

.usersStatPill.is-today{
border-color:#166534;
background:#052e16;
color:#bbf7d0;
}

.userList{
display:flex;
flex-direction:column;
gap:12px;
}

.userCard{
position:relative;
background:#1e293b;
border-radius:18px;
overflow:hidden;
border:1px solid #334155;
}

.userCardAccent{
position:absolute;
right:0;
top:0;
bottom:0;
width:4px;
background:linear-gradient(180deg,#22c55e,#16a34a);
}

.userCardBody{
padding:14px 16px 14px 18px;
}

.userCardHead{
display:flex;
align-items:center;
gap:12px;
margin-bottom:12px;
}

.userAvatar{
width:48px;
height:48px;
border-radius:999px;
display:flex;
align-items:center;
justify-content:center;
font-size:20px;
font-weight:700;
color:#fff;
flex:0 0 auto;
box-shadow:0 8px 18px rgba(0,0,0,.22);
}

.userCardMeta{
flex:1;
min-width:0;
}

.userCardName{
font-size:17px;
font-weight:700;
line-height:1.3;
word-break:break-word;
}

.userCardIndex{
font-size:12px;
color:#94a3b8;
margin-top:2px;
}

.userChips{
display:flex;
flex-wrap:wrap;
gap:8px;
margin-bottom:12px;
}

.userChip{
display:inline-flex;
align-items:center;
gap:6px;
padding:7px 11px;
border-radius:999px;
font-size:12px;
line-height:1.2;
max-width:100%;
}

.userChip svg{
flex:0 0 auto;
opacity:.85;
}

.userChip--phone{
background:#0f172a;
color:#e2e8f0;
border:1px solid #334155;
}

.userChip--ref{
background:#172554;
color:#bfdbfe;
border:1px solid #1d4ed8;
}

.userChipText{
overflow:hidden;
text-overflow:ellipsis;
white-space:nowrap;
}

.userCardFoot{
display:flex;
align-items:center;
justify-content:space-between;
gap:10px;
flex-wrap:wrap;
}

.userDateBadge{
display:inline-flex;
align-items:center;
gap:6px;
padding:7px 11px;
border-radius:999px;
background:#0f172a;
border:1px solid #334155;
font-size:12px;
color:#94a3b8;
}

.userSubsBtn{
border:none;
border-radius:12px;
background:#22c55e;
color:#052e16;
padding:10px 16px;
font-size:13px;
font-weight:700;
cursor:pointer;
font-family:tahoma;
white-space:nowrap;
}

.userSubsBtn:active{
transform:scale(.98);
}

.menuWrap{
position:relative;
flex:0 0 auto;
}

.menuBtn{
background:#0f172a;
border:1px solid #334155;
width:40px;
height:40px;
border-radius:12px;
color:white;
font-size:22px;
cursor:pointer;
line-height:1;
}

.dropdown{
display:none;
position:absolute;
left:0;
top:48px;
background:#0f172a;
border-radius:12px;
padding:10px;
width:220px;
z-index:100;
box-shadow:0 10px 25px rgba(0,0,0,0.4);
}

.dropdown.active{
display:block;
}

.dropdown button,
.deleteBtn{
width:100%;
padding:11px;
border:none;
border-radius:10px;
margin-bottom:8px;
cursor:pointer;
font-size:13px;
text-align:center;
text-decoration:none;
display:block;
}

.dropdown button{
background:#334155;
color:white;
}

.deleteBtn{
background:#ef4444;
color:white;
}

.pagination{
margin-top:20px;
text-align:center;
}

.pagination a{
display:inline-block;
padding:10px 14px;
margin:4px;
background:#334155;
color:white;
border-radius:8px;
text-decoration:none;
font-size:14px;
}

.activePage{
background:#22c55e !important;
}

.modalOverlay{
position:fixed;
inset:0;
background:rgba(0,0,0,0.45);
backdrop-filter:blur(6px);
display:none;
justify-content:center;
align-items:center;
z-index:9999;
padding:15px;
}

.modal{
background:#1e293b;
width:100%;
max-width:420px;
border-radius:18px;
padding:22px;
}

.modalTitle{
font-size:18px;
font-weight:bold;
margin-bottom:18px;
text-align:center;
}

.modalInfo{
background:#0f172a;
padding:14px;
border-radius:12px;
line-height:30px;
font-size:14px;
margin-bottom:16px;
word-break:break-word;
}

.modal input{
width:100%;
padding:12px;
border:none;
border-radius:10px;
margin-bottom:12px;
font-size:14px;
}

.modal button{
width:100%;
padding:12px;
border:none;
border-radius:10px;
background:#22c55e;
color:white;
cursor:pointer;
font-size:14px;
}

.closeBtn{
margin-top:10px;
background:#475569 !important;
}

.deleteButton{
background:#ef4444 !important;
display:block;
text-align:center;
text-decoration:none;
padding:12px;
border-radius:10px;
color:white;
}

.passWrap{
position:relative;
}

.passWrap input{
padding-left:45px;
margin-bottom:0;
}

.eye{
position:absolute;
left:14px;
top:10px;
font-size:20px;
cursor:pointer;
user-select:none;
color:#94a3b8;
}

#profileHost{
display:none;
position:fixed;
inset:0;
z-index:10000;
}

.profileOverlay{
position:absolute;
inset:0;
background:rgba(0,0,0,0.5);
backdrop-filter:blur(4px);
}

.profileModal{
position:absolute;
left:50%;
top:50%;
transform:translate(-50%,-50%);
width:calc(100% - 24px);
max-width:620px;
max-height:88vh;
overflow-y:auto;
background:#1e293b;
border-radius:18px;
padding:20px;
color:white;
}

.profileHeader{
display:flex;
align-items:center;
justify-content:space-between;
font-size:18px;
font-weight:bold;
margin-bottom:16px;
}

.profileCloseBtn{
background:#475569;
border:none;
color:white;
width:34px;
height:34px;
border-radius:10px;
cursor:pointer;
font-size:16px;
}

.profileInfo{
background:#0f172a;
border-radius:14px;
padding:14px;
margin-bottom:16px;
line-height:30px;
font-size:14px;
}

.infoItem span{
color:#94a3b8;
display:inline-block;
min-width:110px;
}

.subsTitle{
font-size:16px;
font-weight:bold;
margin-bottom:12px;
}

.emptySubs{
text-align:center;
color:#94a3b8;
padding:24px 12px;
}

.subCard{
background:#0f172a;
border-radius:14px;
padding:14px;
margin-bottom:12px;
}

.subTop{
display:flex;
justify-content:space-between;
align-items:flex-start;
gap:10px;
margin-bottom:10px;
}

.subPlan{
font-weight:bold;
font-size:15px;
}

.subStatus{
font-size:12px;
padding:6px 10px;
border-radius:999px;
white-space:nowrap;
}

.subStatusApproved{
background:#14532d;
color:#bbf7d0;
}

.subStatusRejected{
background:#450a0a;
color:#fecaca;
}

.subStatusPending{
background:#422006;
color:#fde68a;
}

.subMeta{
font-size:13px;
line-height:28px;
color:#cbd5e1;
margin-bottom:10px;
}

.subMeta b{
color:#94a3b8;
}

.subLink{
display:flex;
gap:8px;
}

.subLink input{
flex:1;
padding:10px;
border:none;
border-radius:10px;
background:#1e293b;
color:white;
font-size:12px;
}

.subLink button,
.profilePagination button{
border:none;
border-radius:10px;
background:#22c55e;
color:white;
padding:10px 14px;
cursor:pointer;
font-family:tahoma;
white-space:nowrap;
}

.subLink .subClearBtn{
background:#dc2626;
}

.subsHint{
font-size:12px;
line-height:24px;
color:#94a3b8;
margin:-4px 0 14px;
}

.subClearedNote{
font-size:13px;
line-height:26px;
padding:10px;
border-radius:10px;
background:#1e293b;
color:#fbbf24;
}

.subRejectReason,
.subPendingNote{
font-size:13px;
line-height:26px;
padding:10px;
border-radius:10px;
background:#1e293b;
}

.subRejectReason{
color:#fecaca;
}

.subPendingNote{
color:#fde68a;
}

.profilePagination{
display:flex;
gap:8px;
justify-content:center;
margin-top:14px;
flex-wrap:wrap;
}

.profilePagination button{
background:#334155;
min-width:38px;
}

.profilePagination .activePage{
background:#22c55e;
}

</style>

<?php adminBottomNavStyles(); ?>

</head>

<body class="adminHasBottomNav">
<?php adminQuickNavStyles(); adminQuickNav('users'); ?>


<div class="box">

<h2>

لیست کاربران

</h2>

<a
href="<?php echo htmlspecialchars(pnvAdminUrl(), ENT_QUOTES, 'UTF-8'); ?>"
class="backTop">

بازگشت

</a>

<div class="usersStats">
<span class="usersStatPill">
<strong><?php echo number_format($totalAllUsers); ?></strong>
کاربر
</span>
<?php if($todayRegistrations > 0){ ?>
<span class="usersStatPill is-today">
<strong><?php echo number_format($todayRegistrations); ?></strong>
ثبت‌نام امروز
</span>
<?php } ?>
</div>

<div class="topbar">

<div class="usersToolbar">

<div class="searchBox">

<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>

<form method="GET">

<input
type="text"
name="search"
placeholder="جستجو نام کاربری ، موبایل ، معرف"
value="<?php echo htmlspecialchars($search); ?>">

</form>

</div>

<a
href="<?php echo htmlspecialchars(pnvAdminUrl('users.php?backup=1'), ENT_QUOTES, 'UTF-8'); ?>"
class="backupBtn"
title="دانلود بکاپ">

<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 3v12"/><path d="M7 10l5 5 5-5"/><path d="M5 21h14"/></svg>
بکاپ

</a>

</div>

</div>

<div class="userList">

<?php if(count($users) === 0){ ?>
<div class="emptySubs" style="padding:32px 16px;border:1px dashed #334155;border-radius:16px;">
<?php echo $search !== '' ? 'کاربری با این جستجو پیدا نشد' : 'هنوز کاربری ثبت نشده'; ?>
</div>
<?php } ?>

<?php foreach($users as $i=>$u){

$realId =
array_search(
$u,
$allUsers
);

$username = $u['username'] ?? '-';
$mobile = $u['mobile'] ?? '-';
$referrer = trim((string)($u['referrer'] ?? ''));
$referrerLabel = $referrer !== '' ? $referrer : 'بدون معرف';
$createdLabel = pnvFormatUserCreatedAt($u['created_at'] ?? '');
if($createdLabel === '' || $createdLabel === '-'){
    $createdLabel = 'تاریخ نامشخص';
}
$avatarHue = pnvUsersAvatarHue($username);
$avatarInitial = pnvUsersAvatarInitial($username);

?>

<div class="userCard">

<div class="userCardAccent"></div>

<div class="userCardBody">

<div class="userCardHead">

<div class="userAvatar" style="background:linear-gradient(135deg,hsl(<?php echo $avatarHue; ?>,68%,48%),hsl(<?php echo ($avatarHue + 36) % 360; ?>,72%,36%));">
<?php echo htmlspecialchars($avatarInitial, ENT_QUOTES, 'UTF-8'); ?>
</div>

<div class="userCardMeta">
<div class="userCardName"><?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?></div>
<div class="userCardIndex">#<?php echo $start + $i + 1; ?></div>
</div>

<div class="menuWrap">

<button
class="menuBtn"
type="button"
aria-label="منو"
onclick="toggleMenu('menu<?php echo $i; ?>')">

⋮

</button>

<div
class="dropdown"
id="menu<?php echo $i; ?>">

<button
type="button"
onclick="openMobileModal(
'<?php echo $realId; ?>',
'<?php echo htmlspecialchars($u['username'], ENT_QUOTES); ?>',
'<?php echo htmlspecialchars($u['mobile'], ENT_QUOTES); ?>'
)">

ویرایش شماره موبایل

</button>

<button
type="button"
onclick="openRefModal(
'<?php echo $realId; ?>',
'<?php echo htmlspecialchars($u['username'], ENT_QUOTES); ?>',
'<?php echo htmlspecialchars($u['referrer'] ?? '', ENT_QUOTES); ?>'
)">

ویرایش معرف

</button>

<button
type="button"
onclick="openPassModal(
'<?php echo $realId; ?>',
'<?php echo htmlspecialchars($u['username'], ENT_QUOTES); ?>',
'<?php echo htmlspecialchars($u['mobile'], ENT_QUOTES); ?>'
)">

ویرایش رمز عبور

</button>

<a
href="#"
class="deleteBtn"
onclick="openDeleteModal(
'<?php echo $realId; ?>',
'<?php echo htmlspecialchars($u['username'], ENT_QUOTES); ?>',
'<?php echo htmlspecialchars($u['mobile'], ENT_QUOTES); ?>'
); return false;">

حذف کاربر

</a>

</div>

</div>

</div>

<div class="userChips">

<span class="userChip userChip--phone">
<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72c.12.86.3 1.7.54 2.5a2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.58-1.11a2 2 0 012.11-.45c.8.24 1.64.42 2.5.54A2 2 0 0122 16.92z"/></svg>
<span class="userChipText"><?php echo htmlspecialchars($mobile, ENT_QUOTES, 'UTF-8'); ?></span>
</span>

<span class="userChip userChip--ref">
<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
<span class="userChipText"><?php echo htmlspecialchars($referrerLabel, ENT_QUOTES, 'UTF-8'); ?></span>
</span>

</div>

<div class="userCardFoot">

<span class="userDateBadge">
<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
<?php echo htmlspecialchars($createdLabel, ENT_QUOTES, 'UTF-8'); ?>
</span>

<button
type="button"
class="userSubsBtn"
onclick="loadProfile(<?php echo json_encode($username, JSON_UNESCAPED_UNICODE); ?>)">

اشتراک‌ها

</button>

</div>

</div>

</div>

<?php } ?>

</div>

<?php if($totalPages > 1){ ?>

<div class="pagination">

<?php for($x=1;$x<=$totalPages;$x++){ ?>

<a
href="<?php echo htmlspecialchars(pnvAdminUrl('users.php?p=' . $x), ENT_QUOTES, 'UTF-8'); ?>"
class="<?php echo ($page==$x)?'activePage':''; ?>">

<?php echo $x; ?>

</a>

<?php } ?>

</div>

<?php } ?>

</div>

<div
class="modalOverlay"
id="modalOverlay">

<div class="modal"
id="modalContent"></div>

</div>

<div id="profileHost"></div>

<script>

const usersPageUrl = <?php echo json_encode(pnvAdminUrl('users.php'), JSON_UNESCAPED_UNICODE); ?>;
const profileApiUrl = <?php echo json_encode(pnvAdminUrl('user-profile.php'), JSON_UNESCAPED_UNICODE); ?>;

function toggleMenu(id){

document
.querySelectorAll('.dropdown')
.forEach(function(el){

if(el.id != id){

el.classList.remove('active');

}

});

document
.getElementById(id)
.classList.toggle('active');

}

document.addEventListener('click',function(e){

if(!e.target.closest('.menuWrap')){

document
.querySelectorAll('.dropdown')
.forEach(function(el){

el.classList.remove('active');

});

}

});

function closeModal(){

document
.getElementById('modalOverlay')
.style.display='none';

}

function openModal(html){

document
.getElementById('modalContent')
.innerHTML = html;

document
.getElementById('modalOverlay')
.style.display='flex';

}

function openMobileModal(id,user,mobile){

openModal(`

<div class="modalTitle">

ویرایش شماره موبایل

</div>

<div class="modalInfo">

نام کاربری: ${user}

</div>

<form method="POST">

<input
type="hidden"
name="userid"
value="${id}">

<input
type="text"
name="newmobile"
value="${mobile}"
placeholder="شماره موبایل"
required>

<button
type="submit"
name="changemobile">

ثبت تغییرات

</button>

<button
type="button"
class="closeBtn"
onclick="closeModal()">

بستن

</button>

</form>

`);

}

function openPassModal(id,user,mobile){

openModal(`

<div class="modalTitle">

ویرایش رمز عبور

</div>

<div class="modalInfo">

نام کاربری: ${user}

</div>

<form method="POST">

<input
type="hidden"
name="userid"
value="${id}">

<div class="passWrap">

<input
type="password"
name="newpassword"
id="newpassword"
placeholder="رمز عبور جدید"
required>

<span
class="eye"
onclick="togglePass()">

👁

</span>

</div>

<br>

<button
type="submit"
name="changepass">

ثبت تغییرات

</button>

<button
type="button"
class="closeBtn"
onclick="closeModal()">

بستن

</button>

</form>

`);

}

function openRefModal(id,user,ref){

openModal(`

<div class="modalTitle">

ویرایش معرف

</div>

<div class="modalInfo">

نام کاربری: ${user}

</div>

<form method="POST">

<input
type="hidden"
name="userid"
value="${id}">

<input
type="text"
name="newreferrer"
value="${ref}"
placeholder="معرف">

<button
type="submit"
name="changereferrer">

ثبت تغییرات

</button>

<button
type="button"
class="closeBtn"
onclick="closeModal()">

بستن

</button>

</form>

`);

}

function openDeleteModal(id,user,mobile){

openModal(`

<div class="modalTitle">

حذف کاربر

</div>

<div class="modalInfo">

نام کاربری: ${user}
<br>
شماره موبایل: ${mobile}

</div>

<a
href="${usersPageUrl}?delete=${id}"
class="deleteButton">

حذف کاربر

</a>

<button
type="button"
class="closeBtn"
onclick="closeModal()">

بستن

</button>

`);

}

function togglePass(){

let p =
document.getElementById('newpassword');

if(p.type=='password'){

p.type='text';

}else{

p.type='password';

}

}

function loadProfile(user, page = 1){

fetch(
profileApiUrl + '?user='
+ encodeURIComponent(user)
+ '&p='
+ page,
{credentials:'same-origin'}
)
.then(function(response){
return response.text();
})
.then(function(html){

document.getElementById('profileHost').innerHTML = html;
document.getElementById('profileHost').style.display = 'block';

})
.catch(function(){
alert('خطا در بارگذاری اشتراک‌ها');
});

}

function closeProfileModal(){

document.getElementById('profileHost').innerHTML = '';
document.getElementById('profileHost').style.display = 'none';

}

function copySub(button){

const input = button.parentElement
    ? button.parentElement.querySelector('input')
    : button.previousElementSibling;

if(!input){
return;
}

input.select();
input.setSelectionRange(0, 99999);
navigator.clipboard.writeText(input.value);
alert('کپی شد');

}

function clearSubLink(button){

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
headers: {
'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'
},
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

}

<?php if($openProfile !== ''){ ?>

loadProfile(<?php echo json_encode($openProfile, JSON_UNESCAPED_UNICODE); ?>);

<?php } ?>

</script>

<?php
adminBottomNav(['active' => 'users', 'more_mode' => 'sheet']);
adminBottomNavScript();
?>

</body>

</html>