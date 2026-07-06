<?php

ini_set('display_errors',1);
error_reporting(E_ALL);

session_start();

if(!isset($_SESSION['user'])){
header("Location: index.php");
exit;
}

$user = $_SESSION['user'];

$supportFile = "db/support.json";
$hasUnreadSupport = false;

if(file_exists($supportFile)){

$supportData =
json_decode(
file_get_contents($supportFile),
true
);

if(is_array($supportData)){

foreach($supportData as $ticket){

if(
isset($ticket['user'])
&&
$ticket['user'] == $user
){

if(isset($ticket['messages'])){

foreach($ticket['messages'] as $msg){

if(

isset($msg['sender'])
&&

$msg['sender'] == 'admin'

&&

empty($msg['seen_by_user'])

){

$hasUnreadSupport = true;
break 2;

}

}

}

}

}

}

}

?>

<!DOCTYPE html>

<html lang="fa">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>داشبورد کاربر</title>
<link rel="stylesheet" href="user_panel.css?v=1">
</head>

<body class="userPanel userPanel--dashboard">

<div class="userPanelWrap">

<div class="userPanelBox">

<h2 class="userPanelTitle">پنل کاربری</h2>

<div class="userPanelUserbox">
خوش آمدید<br>
<?php echo htmlspecialchars($user); ?>
</div>

<a href="buy.php" class="userPanelMenu">خرید اشتراک جدید</a>
<a href="renew.php" class="userPanelMenu">تمدید اشتراک</a>
<a href="subscriptions.php" class="userPanelMenu">لیست اشتراک ها</a>
<a href="renew-list.php" class="userPanelMenu">لیست تمدید ها</a>
<a href="downloads.php" class="userPanelMenu">دانلود نرم افزارها</a>
<a href="coupon.php" class="userPanelMenu">کوپن تخفیف</a>

<a href="support.php" class="userPanelMenu">
<?php if($hasUnreadSupport){ ?>
<span class="userPanelNotifDot"></span>
<?php } ?>
پیام به پشتیبانی
</a>

<a href="logout.php" class="userPanelMenu userPanelMenu--danger">خروج</a>

</div>

</div>

</body>

</html>