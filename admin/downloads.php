<?php

session_start();

if(!isset($_SESSION['admin'])){
header("Location:index.php");
exit;
}

$dir = __DIR__ . "/../down";

if(!file_exists($dir)){
mkdir($dir,0777,true);
}

if(isset($_POST['upload'])){

if(
isset($_FILES['file'])
&&
$_FILES['file']['size'] > 0
){

$name =
basename($_FILES['file']['name']);

$target =
$dir . "/" . $name;

if(
move_uploaded_file(
$_FILES['file']['tmp_name'],
$target
)
){

echo "OK";

}else{

echo "<pre>";

print_r($_FILES);

echo "</pre>";

echo "TARGET: " . $target;

echo "<br>";

echo "TMP: " . $_FILES['file']['tmp_name'];

exit;

}

}

if(isset($_GET['delete'])){

$file =
basename($_GET['delete']);

$path =
$dir . "/" . $file;

if(file_exists($path)){
unlink($path);
}

header("Location:downloads.php");
exit;

}

$files = [];

if(is_dir($dir)){

$scan = scandir($dir);

if($scan !== false){

foreach($scan as $f){

if($f=='.' || $f=='..'){
continue;
}

if(is_file($dir . '/' . $f)){
$files[] = $f;
}

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

مدیریت دانلودها

</title>

<style>

*{
box-sizing:border-box;
}

html,
body{
width:100%;
overflow-x:hidden;
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
max-width:900px;
margin:auto;
}

h2{
text-align:center;
margin-bottom:20px;
font-size:28px;
}

.uploadBox{
background:#1e293b;
padding:20px;
border-radius:20px;
margin-bottom:20px;
}

input[type=file]{
width:100%;
background:#0f172a;
padding:14px;
border-radius:14px;
margin-bottom:14px;
color:white;
border:none;
}

button{
width:100%;
padding:14px;
border:none;
border-radius:14px;
background:#22c55e;
color:white;
font-size:16px;
cursor:pointer;
}

.progressBox{
width:100%;
height:24px;
background:#0f172a;
border-radius:999px;
overflow:hidden;
margin-top:16px;
display:none;
}

.progressBar{
height:100%;
width:0%;
background:
linear-gradient(
90deg,
#22c55e,
#16a34a
);
display:flex;
align-items:center;
justify-content:center;
font-size:12px;
font-weight:bold;
transition:.2s;
}

.progressText{
margin-top:10px;
font-size:13px;
color:#cbd5e1;
text-align:center;
line-height:26px;
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
word-break:break-word;
font-size:15px;
flex:1;
}

.actions{
display:flex;
gap:10px;
}

.btn{
padding:10px 14px;
border-radius:10px;
text-decoration:none;
color:white;
font-size:14px;
}

.download{
background:#2563eb;
}

.delete{
background:#dc2626;
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

.actions{
width:100%;
}

.btn{
flex:1;
text-align:center;
}

}

</style>

</head>

<body>

<div class="box">

<h2>

مدیریت دانلود نرم افزارها

</h2>

<div class="uploadBox">

<form
id="uploadForm"
method="POST"
enctype="multipart/form-data">

<input
type="file"
name="file"
required>

<button
type="submit">

آپلود فایل

</button>

<div class="progressBox">

<div
class="progressBar"
id="progressBar">

0%

</div>

</div>

<div
class="progressText"
id="progressText">

</div>

</form>

</div>

<?php if(count($files)==0){ ?>

<div class="empty">

فایلی وجود ندارد

</div>

<?php } ?>

<?php foreach($files as $f){ ?>

<div class="card">

<div class="name">

<?php echo htmlspecialchars($f); ?>

</div>

<div class="actions">

<a
href="../down/<?php echo urlencode($f); ?>"
class="btn download"
download>

دانلود

</a>

<a
href="?delete=<?php echo urlencode($f); ?>"
class="btn delete"
onclick="return confirm('فایل حذف شود؟')">

حذف

</a>

</div>

</div>

<?php } ?>

<a
href="index.php"
class="back">

بازگشت

</a>

</div>

<script>

const form =
document.getElementById('uploadForm');

form.addEventListener('submit',function(e){

e.preventDefault();

const formData =
new FormData(form);

const xhr =
new XMLHttpRequest();

document
.querySelector('.progressBox')
.style.display='block';

xhr.upload.addEventListener(
'progress',
function(e){

if(e.lengthComputable){

let percent =
Math.round(
(e.loaded / e.total) * 100
);

document
.getElementById('progressBar')
.style.width =
percent + '%';

document
.getElementById('progressBar')
.innerText =
percent + '%';

let uploaded =
(e.loaded / 1024 / 1024)
.toFixed(2);

let total =
(e.total / 1024 / 1024)
.toFixed(2);

let remain =
(total - uploaded)
.toFixed(2);

document
.getElementById('progressText')
.innerHTML =

'آپلود شده: '
+
uploaded
+
' MB از '
+
total
+
' MB'
+
'<br>'
+
'باقی مانده: '
+
remain
+
' MB';

}

});

xhr.onload = function(){

if(xhr.status == 200){

document
.getElementById('progressText')
.innerHTML =
'✅ آپلود کامل شد';

setTimeout(function(){

location.reload();

},1000);

}else{

document
.getElementById('progressText')
.innerHTML =
'❌ خطا در آپلود';

}

};

xhr.open(
'POST',
''
);

xhr.send(formData);

});

</script>

</body>

</html>
