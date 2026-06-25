<?php

session_start();

require_once "phpqrcode/qrlib.php";

if(!isset($_SESSION['user'])){
header("Location: index.php");
exit;
}

$rows = [];

$file = "invoices/payments.csv";

if(file_exists($file)){

$handle = fopen($file,"r");

while(($data = fgetcsv($handle)) !== FALSE){

if(
isset($data[0])
&&
$data[0] == $_SESSION['user']
){

$rows[] = $data;

}

}

fclose($handle);

}

if(!file_exists("temp")){
mkdir("temp");
}

?>

<!DOCTYPE html>

<html lang="fa">

<head>

<meta charset="UTF-8">

<meta name="viewport"
content="width=device-width, initial-scale=1.0">

<title>

لیست اشتراک ها

</title>

<style>

*{
box-sizing:border-box;
}

body{
background:#0f172a;
font-family:tahoma;
direction:rtl;
color:white;
padding:10px;
margin:0;
}

.container{
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
border-radius:16px;
padding:16px;
margin-bottom:14px;
}

.row{
display:grid;
grid-template-columns:1fr 1fr;
gap:8px;
font-size:14px;
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

.linkbox{
margin-top:12px;
background:#0f172a;
padding:12px;
border-radius:10px;
word-break:break-all;
font-size:13px;
line-height:24px;
}

.copybtn{
margin-top:10px;
background:#22c55e;
border:none;
padding:10px;
border-radius:8px;
color:white;
cursor:pointer;
font-size:13px;
width:100%;
}

.back{
display:block;
background:#334155;
padding:12px;
border-radius:10px;
color:white;
text-decoration:none;
margin-top:18px;
text-align:center;
font-size:15px;
}

.pending{
color:#facc15;
font-weight:bold;
}

.success{
color:#22c55e;
font-weight:bold;
}

.reject{
color:#ef4444;
font-weight:bold;
}

.empty{
background:#1e293b;
padding:20px;
border-radius:16px;
text-align:center;
font-size:15px;
line-height:28px;
}

.qrbox{
margin-top:18px;
text-align:center;
}

.qrbox img{
width:190px;
background:white;
padding:12px;
border-radius:16px;
}

.qrtitle{
margin-bottom:10px;
font-size:14px;
color:#cbd5e1;
}

@media(min-width:768px){

body{
padding:18px;
}

.container{
max-width:920px;
}

h2{
font-size:28px;
margin-bottom:22px;
}

.card{
padding:20px;
}

.row{
grid-template-columns:repeat(2,1fr);
gap:10px;
font-size:15px;
}

.item{
padding:12px;
}

.label{
font-size:12px;
}

.linkbox{
font-size:14px;
line-height:26px;
}

.copybtn{
width:auto;
padding:10px 18px;
font-size:13px;
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

<div class="container">

<h2>

لیست اشتراک ها

</h2>

<?php if(count($rows) == 0){ ?>

<div class="empty">

هنوز اشتراکی ثبت نشده است

</div>

<?php } ?>

<?php

$i = 1;

foreach(array_reverse($rows) as $row){

$configName = $row[1] ?? '';

if(
strpos($configName,'https://vip.') !== false
||
strpos($configName,'https://vip2.') !== false
||
strpos($configName,'https://vip3.') !== false
){
continue;
}

$plan = $row[2] ?? "";
$tracking = $row[3] ?? "";
$date = $row[4] ?? "";
$time = $row[5] ?? "";
$status = $row[6] ?? "درحال بررسی";
$link = $row[7] ?? "";

$statusClass = "pending";

if($status == "تایید شد"){
$statusClass = "success";
}

if($status == "رد شد"){
$statusClass = "reject";
}

?>

<div class="card">

<div class="row">

<div class="item">
<div class="label">ردیف</div>
<?php echo $i; ?>
</div>

<div class="item">
<div class="label">وضعیت</div>

<span class="<?php echo $statusClass; ?>">

<?php echo htmlspecialchars($status); ?>

</span>

</div>

<div class="item">
<div class="label">نام کانفیگ</div>
<?php echo htmlspecialchars($configName); ?>
</div>

<div class="item">
<div class="label">پلن</div>
<?php echo htmlspecialchars($plan); ?>
</div>

<div class="item">
<div class="label">پیگیری</div>
<?php echo htmlspecialchars($tracking); ?>
</div>

<div class="item">
<div class="label">تاریخ</div>
<?php echo htmlspecialchars($date); ?>
</div>

<div class="item">
<div class="label">ساعت</div>
<?php echo htmlspecialchars($time); ?>
</div>

</div>

<div class="linkbox">

<?php if($status == "درحال بررسی"){ ?>

بعد از تایید لینک اشتراک در این قسمت نمایش داده خواهد شد

<?php } ?>

<?php if($status == "تایید شد"){ ?>

<div id="sub<?php echo $i; ?>">

<?php echo htmlspecialchars($link); ?>

</div>

<button class="copybtn"
onclick="copyLink('sub<?php echo $i; ?>')">

کپی لینک اشتراک

</button>

<?php

$qrfile =
"temp/qr".$i.".png";

QRcode::png(
$link,
$qrfile,
QR_ECLEVEL_L,
6
);

?>

<div class="qrbox">

<div class="qrtitle">

اسکن QR Code

</div>

<img src="<?php echo $qrfile; ?>">

</div>

<?php } ?>

<?php if($status == "رد شد"){ ?>

<?php echo htmlspecialchars($link); ?>

<?php } ?>

</div>

</div>

<?php

$i++;

}

?>

<a href="dashboard.php"
class="back">

بازگشت

</a>

</div>

<script>

function copyLink(id){

const text =
document.getElementById(id).innerText;

navigator.clipboard.writeText(text);

alert("لینک اشتراک کپی شد");

}

</script>

</body>

</html>
