<?php

session_start();

require_once "functions.php";

if(!isset($_SESSION['admin'])){
header("Location: index.php");
exit;
}

$usersFile = '../db/users.json';

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

header("Location: users.php");
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

header("Location: users.php");
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

header("Location: users.php");
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

header("Location: users.php");
exit;
}

$search =
trim($_GET['search'] ?? '');

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
margin-bottom:20px;
}

.backupBtn{
background:#2563eb;
padding:12px;
border-radius:10px;
color:white;
text-decoration:none;
font-size:14px;
text-align:center;
}

.searchBox{
background:#1e293b;
padding:14px;
border-radius:14px;
}

.searchBox input{
width:100%;
padding:12px;
border:none;
border-radius:10px;
font-size:14px;
}

.userCard{
background:#1e293b;
border-radius:16px;
padding:16px;
margin-bottom:14px;
}

.top{
display:flex;
justify-content:space-between;
align-items:flex-start;
gap:10px;
}

.info{
flex:1;
line-height:30px;
font-size:14px;
word-break:break-word;
}

.info b{
display:inline-block;
min-width:90px;
color:#cbd5e1;
}

.menuWrap{
position:relative;
}

.menuBtn{
background:#334155;
border:none;
width:40px;
height:40px;
border-radius:10px;
color:white;
font-size:22px;
cursor:pointer;
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

</style>

</head>

<body>

<div class="box">

<h2>

لیست کاربران

</h2>

<a
href="index.php"
class="backTop">

بازگشت

</a>

<div class="topbar">

<a
href="users.php?backup=1"
class="backupBtn">

دانلود بکاپ کاربران

</a>

<div class="searchBox">

<form method="GET">

<input
type="text"
name="search"
placeholder="جستجو نام کاربری ، موبایل ، معرف"
value="<?php echo htmlspecialchars($search); ?>">

</form>

</div>

</div>

<?php foreach($users as $i=>$u){

$realId =
array_search(
$u,
$allUsers
);

?>

<div class="userCard">

<div class="top">

<div class="info">

<div>
<b>ردیف:</b>
<?php echo $start + $i + 1; ?>
</div>

<div>
<b>نام کاربری:</b>
<?php echo htmlspecialchars($u['username']); ?>
</div>

<div>
<b>موبایل:</b>
<?php echo htmlspecialchars($u['mobile']); ?>
</div>

<div>
<b>معرف:</b>
<?php echo htmlspecialchars($u['referrer'] ?? '-'); ?>
</div>

<div>
<b>تاریخ ثبت نام:</b>
<?php echo htmlspecialchars($u['created_at'] ?? '-'); ?>
</div>

</div>

<div class="menuWrap">

<button
class="menuBtn"
onclick="toggleMenu('menu<?php echo $i; ?>')">

⋮

</button>

<div
class="dropdown"
id="menu<?php echo $i; ?>">

<button
onclick="openMobileModal(
'<?php echo $realId; ?>',
'<?php echo htmlspecialchars($u['username']); ?>',
'<?php echo htmlspecialchars($u['mobile']); ?>'
)">

ویرایش شماره موبایل

</button>

<button
onclick="openRefModal(
'<?php echo $realId; ?>',
'<?php echo htmlspecialchars($u['username']); ?>',
'<?php echo htmlspecialchars($u['referrer'] ?? ''); ?>'
)">

ویرایش معرف

</button>

<button
onclick="openPassModal(
'<?php echo $realId; ?>',
'<?php echo htmlspecialchars($u['username']); ?>',
'<?php echo htmlspecialchars($u['mobile']); ?>'
)">

ویرایش رمز عبور

</button>

<a
href="#"
class="deleteBtn"
onclick="openDeleteModal(
'<?php echo $realId; ?>',
'<?php echo htmlspecialchars($u['username']); ?>',
'<?php echo htmlspecialchars($u['mobile']); ?>'
)">

حذف کاربر

</a>

</div>

</div>

</div>

</div>

<?php } ?>

<?php if($totalPages > 1){ ?>

<div class="pagination">

<?php for($x=1;$x<=$totalPages;$x++){ ?>

<a
href="users.php?p=<?php echo $x; ?>"
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

<script>

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
href="users.php?delete=${id}"
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

</script>

</body>

</html>