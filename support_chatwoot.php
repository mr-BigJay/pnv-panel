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
<title>پشتیبانی آنلاین</title>
<style>
*{
box-sizing:border-box;
}
body{
margin:0;
min-height:100vh;
padding:12px;
background:#0f172a;
font-family:tahoma;
direction:rtl;
color:white;
}
.topbar{
max-width:900px;
margin:0 auto 12px;
display:flex;
align-items:center;
justify-content:space-between;
gap:12px;
}
.topbar h2{
margin:0;
font-size:20px;
}
.back{
background:#334155;
color:white;
text-decoration:none;
padding:10px 16px;
border-radius:12px;
font-size:14px;
white-space:nowrap;
}
.hint{
max-width:900px;
margin:0 auto 12px;
padding:12px 14px;
background:#1e293b;
border-radius:12px;
color:#94a3b8;
font-size:13px;
line-height:26px;
}
.chatHost{
max-width:900px;
margin:0 auto;
min-height:70vh;
}
</style>
</head>
<body>

<div class="topbar">
<h2>پشتیبانی آنلاین</h2>
<a href="dashboard.php" class="back">بازگشت</a>
</div>

<div class="hint">
سلام <?php echo htmlspecialchars($user, ENT_QUOTES, 'UTF-8'); ?> — چت با نام کاربری پنل شما باز می‌شود؛ نیازی به ورود جداگانه نیست.
</div>

<div class="chatHost" id="chatHost"></div>

<script>
function openChatwoot(){
    if(window.$chatwoot){
        window.$chatwoot.toggle('open');
    }
}
window.addEventListener('chatwoot:ready', function(){
    openChatwoot();
});
</script>

<?php chatwootRenderWidget($user, ['auto_open' => true]); ?>

</body>
</html>
