<?php

session_start();

if(!isset($_SESSION['user'])){
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/chatwoot_lib.php';

$user = $_SESSION['user'];

if(!chatwootEnabled()){
    header('Location: support.php?legacy=1');
    exit;
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
padding:16px;
background:#0f172a;
font-family:tahoma;
direction:rtl;
color:white;
}
.container{
max-width:720px;
margin:auto;
}
.card{
background:#1e293b;
border-radius:18px;
padding:24px;
line-height:32px;
}
h2{
margin:0 0 16px;
font-size:22px;
}
.note{
color:#94a3b8;
font-size:14px;
margin-top:16px;
}
.openBtn{
display:inline-block;
margin-top:18px;
background:#22c55e;
color:white;
border:none;
padding:14px 22px;
border-radius:12px;
font-size:15px;
cursor:pointer;
font-family:tahoma;
}
.back{
display:block;
margin-top:18px;
background:#334155;
padding:14px;
border-radius:14px;
text-align:center;
color:white;
text-decoration:none;
}
</style>
</head>
<body>

<div class="container">

<div class="card">

<h2>پشتیبانی آنلاین</h2>

سلام <?php echo htmlspecialchars($user, ENT_QUOTES, 'UTF-8'); ?>،
برای ارتباط با پشتیبانی روی دکمه زیر بزنید یا از آیکون چت پایین صفحه استفاده کنید.

<br>

<button type="button" class="openBtn" onclick="openChatwoot()">
باز کردن چت پشتیبانی
</button>

<div class="note">
پیام‌های شما به‌صورت لحظه‌ای به تیم پشتیبانی ارسال می‌شود.
</div>

</div>

<a href="dashboard.php" class="back">بازگشت به داشبورد</a>

</div>

<script>
function openChatwoot(){
    if(window.$chatwoot){
        window.$chatwoot.toggle('open');
    }
}
window.addEventListener('chatwoot:ready', function(){
    if(window.$chatwoot){
        window.$chatwoot.toggle('open');
    }
});
</script>

<?php chatwootRenderWidget($user, ['auto_open' => true]); ?>

</body>
</html>
