<?php

session_start();

if(!isset($_SESSION['admin'])){
exit;
}

$username =
$_GET['user'] ?? '';

if($username==''){
exit;
}

$usersFile =
"../db/users.json";

$paymentsFile =
"../invoices/payments.csv";

$users = [];

if(file_exists($usersFile)){

$users =
json_decode(
file_get_contents($usersFile),
true
);

}

if(!is_array($users)){
$users = [];
}

$userData = null;

foreach($users as $u){

if(
strtolower($u['username'])
==
strtolower($username)
){

$userData = $u;
break;

}

}

$purchases = [];

if(file_exists($paymentsFile)){

$f =
fopen($paymentsFile,'r');

while(($d=fgetcsv($f))!==FALSE){

if(
isset($d[0])
&&
strtolower(trim($d[0]))
==
strtolower(trim($username))
){

$purchases[] = [

'link' =>
$d[1] ?? '',

'plan' =>
$d[2] ?? '',

'date' =>
$d[4] ?? '',

'time' =>
$d[5] ?? '',

'status' =>
$d[6] ?? '',

'type' =>
$d[9] ?? ''

];

}

}

fclose($f);

}

usort($purchases,function($a,$b){

return strcmp(
$b['date'].' '.$b['time'],
$a['date'].' '.$a['time']
);

});

$page =
intval($_GET['p'] ?? 1);

if($page<1){
$page=1;
}

$perPage = 5;

$total =
count($purchases);

$totalPages =
ceil($total / $perPage);

$start =
($page-1) * $perPage;

$purchases =
array_slice(
$purchases,
$start,
$perPage
);

?>

<div class="profileOverlay"
onclick="closeProfileModal()"></div>

<div class="profileModal">

<div class="profileHeader">

👤 پروفایل کاربر

<span
class="closeBtn"
onclick="closeProfileModal()">

✕

</span>

</div>

<div class="profileInfo">

<div class="infoItem">

<span>

نام کاربری:

</span>

<?php echo htmlspecialchars($username); ?>

</div>

<div class="infoItem">

<span>

شماره موبایل:

</span>

<?php echo htmlspecialchars($userData['mobile'] ?? '-'); ?>

</div>

<div class="infoItem">

<span>

معرف:

</span>

<?php echo htmlspecialchars($userData['referrer'] ?? '-'); ?>

</div>

<div class="infoItem">

<span>

کد معرف:

</span>

<?php echo htmlspecialchars($userData['referral_code'] ?? '-'); ?>

</div>

<div class="infoItem">

<span>

تعداد خرید:

</span>

<?php echo $total; ?>

</div>

</div>

<div class="subsTitle">

📦 اشتراک ها

</div>

<?php if(count($purchases)==0){ ?>

<div class="emptySubs">

اشتراکی یافت نشد

</div>

<?php } ?>

<?php foreach($purchases as $sub){ ?>

<div class="subCard">

<div class="subTop">

<div>

<?php echo htmlspecialchars($sub['plan']); ?>

</div>

<div class="subType">

<?php echo htmlspecialchars($sub['type']); ?>

</div>

</div>

<div class="subDate">

<?php echo $sub['date']; ?>

-

<?php echo $sub['time']; ?>

</div>

<div class="subLink">

<input
type="text"
readonly
value="<?php echo htmlspecialchars($sub['link']); ?>">

<button
onclick="copySub(this)">

کپی

</button>

</div>

</div>

<?php } ?>

<?php if($totalPages > 1){ ?>

<div class="profilePagination">

<?php for($i=1;$i<=$totalPages;$i++){ ?>

<button
onclick="loadProfile('<?php echo $username; ?>',<?php echo $i; ?>)"
class="<?php echo $page==$i ? 'activePage' : ''; ?>">

<?php echo $i; ?>

</button>

<?php } ?>

</div>

<?php } ?>

</div>
