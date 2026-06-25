<?php

session_start();

if(!isset($_SESSION['user'])){

header("Location:index.php");
exit;

}

$dir = "down";

$files = [];

if(is_dir($dir)){

$scan = scandir($dir);

foreach($scan as $f){

if($f=='.' || $f=='..'){
continue;
}

if(is_file($dir.'/'.$f)){

$files[] = $f;

}

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

دانلود نرم افزارها

</title>

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

.box{
max-width:850px;
margin:auto;
}

h2{
text-align:center;
margin-bottom:20px;
font-size:28px;
}

.card{
background:#1e293b;
padding:18px;
border-radius:18px;
margin-bottom:14px;
display:flex;
justify-content:space-between;
align-items:center;
gap:10px;
}

.name{
font-size:15px;
word-break:break-word;
}

.btn{
background:#22c55e;
color:white;
padding:12px 18px;
border-radius:12px;
text-decoration:none;
font-size:14px;
white-space:nowrap;
}

.back{
display:block;
margin-top:20px;
background:#334155;
padding:14px;
border-radius:14px;
text-align:center;
text-decoration:none;
color:white;
}

.empty{
background:#1e293b;
padding:25px;
border-radius:18px;
text-align:center;
color:#94a3b8;
}

@media(max-width:768px){

.card{
flex-direction:column;
align-items:flex-start;
}

.btn{
width:100%;
text-align:center;
}

}

</style>

</head>

<body>

<div class="box">

<h2>

دانلود نرم افزارهای مورد نیاز

</h2>

<?php if(count($files)==0){ ?>

<div class="empty">

فعلاً فایلی برای دانلود قرار نگرفته است

</div>

<?php } ?>

<?php foreach($files as $f){ ?>

<div class="card">

<div class="name">

<?php echo htmlspecialchars($f); ?>

</div>

<a
class="btn"
href="down/<?php echo urlencode($f); ?>"
download>

دانلود

</a>

</div>

<?php } ?>

<a
href="dashboard.php"
class="back">

بازگشت

</a>

</div>

</body>

</html>
