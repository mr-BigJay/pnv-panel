<?php

session_start();

if(!isset($_SESSION['user'])){
header("Location: index.php");
exit;
}

$user = $_SESSION['user'];

$payments = [];

if(file_exists("invoices/payments.csv")){

$f = fopen("invoices/payments.csv","r");

while(($d=fgetcsv($f))!==FALSE){

$payments[] = $d;

}

fclose($f);

}

function statusColor($status){

if($status=="تایید شد"){
return "#22c55e";
}

if($status=="رد شد"){
return "#ef4444";
}

return "#eab308";

}

function findEmailBySub($sub){

preg_match('/\/sub\/([a-zA-Z0-9]+)/',$sub,$m);

$subid = $m[1] ?? "";

$files = [
"db/vip.csv",
"db/vip2.csv",
"db/vip3.csv"
];

foreach($files as $f){

if(file_exists($f)){

$rows =
array_map(
'str_getcsv',
file($f)
);

foreach($rows as $r){

if(
isset($r[1]) &&
trim($r[1]) == $subid
){

return $r[0];

}

}

}

}

return "-";

}

?>

<!DOCTYPE html>
<html lang="fa">

<head>

<meta charset="UTF-8">

<meta name="viewport"
content="width=device-width, initial-scale=1.0">

<title>

لیست تمدید ها

</title>

<style>

*{
box-sizing:border-box;
}

body{
margin:0;
padding:10px;
background:#0f172a;
font-family:tahoma;
direction:rtl;
color:white;
}

.box{
width:100%;
max-width:760px;
margin:auto;
}

h2{
text-align:center;
font-size:24px;
margin-bottom:18px;
}

.card{
background:#1e293b;
padding:16px;
border-radius:16px;
margin-bottom:14px;
}

.grid{
display:grid;
grid-template-columns:1fr 1fr;
gap:8px;
}

.item{
background:#0f172a;
padding:10px;
border-radius:8px;
}

.label{
font-size:11px;
color:#94a3b8;
margin-bottom:4px;
}

.value{
font-size:14px;
line-height:24px;
word-break:break-word;
}

.status{
padding:6px 10px;
border-radius:8px;
display:inline-block;
font-size:13px;
margin-top:4px;
}

.info{
margin-top:12px;
padding:12px;
border-radius:10px;
background:#0f172a;
line-height:24px;
font-size:13px;
word-break:break-word;
}

.back{
display:block;
margin-top:18px;
text-align:center;
background:#334155;
padding:12px;
border-radius:10px;
color:white;
text-decoration:none;
font-size:15px;
}

.empty{
background:#1e293b;
padding:20px;
border-radius:16px;
text-align:center;
font-size:15px;
line-height:28px;
}

@media(min-width:768px){

body{
padding:18px;
}

.box{
max-width:920px;
}

h2{
font-size:28px;
margin-bottom:22px;
}

.card{
padding:20px;
}

.grid{
gap:10px;
}

.item{
padding:12px;
}

.label{
font-size:12px;
}

.value{
font-size:15px;
line-height:26px;
}

.status{
font-size:14px;
padding:7px 12px;
}

.info{
font-size:14px;
line-height:26px;
}

.back{
display:inline-block;
padding:12px 20px;
font-size:15px;
}

}

</style>

</head>

<body>

<div class="box">

<h2>

لیست تمدید ها

</h2>

<?php

$found = false;

foreach(array_reverse($payments) as $p){

if(
($p[0] ?? "") != $user
){
continue;
}

if(
($p[9] ?? "") != "تمدید"
){
continue;
}

$found = true;

$status = $p[6] ?? "";

$email =
findEmailBySub($p[1]);

?>

<div class="card">

<div class="grid">

<div class="item">

<div class="label">

نام کانفیگ

</div>

<div class="value">

<?php echo $email != "" ? $email : "-"; ?>

</div>

</div>

<div class="item">

<div class="label">

پلن

</div>

<div class="value">

<?php echo $p[2]; ?>

</div>

</div>

<div class="item">

<div class="label">

شماره پیگیری

</div>

<div class="value">

<?php echo $p[3]; ?>

</div>

</div>

<div class="item">

<div class="label">

تاریخ

</div>

<div class="value">

<?php echo $p[4]; ?>

</div>

</div>

<div class="item">

<div class="label">

ساعت

</div>

<div class="value">

<?php echo $p[5]; ?>

</div>

</div>

<div class="item">

<div class="label">

وضعیت

</div>

<div class="value">

<span
class="status"
style="background:<?php echo statusColor($status); ?>">

<?php echo $status; ?>

</span>

</div>

</div>

</div>

<div class="info">

<?php if($status=="درحال بررسی"){ ?>

بعد از تایید پرداخت اشتراک شما تمدید خواهد شد.

<?php } ?>

<?php if($status=="تایید شد"){ ?>

اشتراک شما تمدید شد.

<?php } ?>

<?php if($status=="رد شد"){ ?>

<?php echo $p[7]; ?>

<?php } ?>

</div>

</div>

<?php } ?>

<?php if(!$found){ ?>

<div class="empty">

درخواستی برای تمدید ثبت نشده است

</div>

<?php } ?>

<a href="dashboard.php"
class="back">

بازگشت

</a>

</div>

</body>

</html>
