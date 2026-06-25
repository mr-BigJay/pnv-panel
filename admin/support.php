<?php

session_start();

if(!isset($_SESSION['admin'])){
header("Location: index.php");
exit;
}

$file = "../db/support.json";

if(!file_exists($file)){
file_put_contents($file,"[]");
}

$data = json_decode(
file_get_contents($file),
true
);

if(!is_array($data)){
$data = [];
}

if(isset($_GET['user'])){

$current = $_GET['user'];

for($i=0;$i<count($data);$i++){

if(
isset($data[$i]['user'])
&&
$data[$i]['user']==$current
){

if(isset($data[$i]['messages'])){

for($j=0;$j<count($data[$i]['messages']);$j++){

if(
isset($data[$i]['messages'][$j]['sender'])
&&
$data[$i]['messages'][$j]['sender']=='user'
){

$data[$i]['messages'][$j]['seen_by_admin']=true;

}

}

}

}

}

file_put_contents(
$file,
json_encode(
$data,
JSON_UNESCAPED_UNICODE|
JSON_PRETTY_PRINT
)
);

}

if(isset($_GET['delete'])){

$msgid = $_GET['delete'];

for($i=0;$i<count($data);$i++){

if(isset($data[$i]['messages'])){

for($j=0;$j<count($data[$i]['messages']);$j++){

if(
isset($data[$i]['messages'][$j]['id'])
&&
$data[$i]['messages'][$j]['id']==$msgid
){

unset($data[$i]['messages'][$j]);

$data[$i]['messages'] =
array_values(
$data[$i]['messages']
);

}

}

}

}

file_put_contents(
$file,
json_encode(
$data,
JSON_UNESCAPED_UNICODE|
JSON_PRETTY_PRINT
)
);

header(
"Location: support.php?user=".$_GET['user']
);

exit;

}

if(isset($_POST['edit_id'])){

$id =
$_POST['edit_id'];

$text =
trim($_POST['edit_text']);

for($i=0;$i<count($data);$i++){

if(isset($data[$i]['messages'])){

for($j=0;$j<count($data[$i]['messages']);$j++){

if(
isset($data[$i]['messages'][$j]['id'])
&&
$data[$i]['messages'][$j]['id']==$id
){

$data[$i]['messages'][$j]['text']=$text;

$data[$i]['messages'][$j]['edited']=true;

}

}

}

}

file_put_contents(
$file,
json_encode(
$data,
JSON_UNESCAPED_UNICODE|
JSON_PRETTY_PRINT
)
);

header(
"Location: support.php?user=".$_POST['user']
);

exit;

}

if(isset($_POST['reply'])){

$user =
$_POST['user'];

$text =
trim($_POST['message']);

$image = "";

if(
isset($_FILES['image'])
&&
$_FILES['image']['size'] > 0
){

$ext =
strtolower(
pathinfo(
$_FILES['image']['name'],
PATHINFO_EXTENSION
)
);

$allowed = [
'jpg',
'jpeg',
'png',
'webp'
];

if(in_array($ext,$allowed)){

if(!file_exists("../uploads/support")){
mkdir("../uploads/support",0777,true);
}

$filename =
time().
rand(1000,9999).
".".
$ext;

$savePath =
__DIR__ .
"/../uploads/support/" .
$filename;

$image =
"/uploads/support/" .
$filename;

move_uploaded_file(
$_FILES['image']['tmp_name'],
$savePath
);

}

}

for($i=0;$i<count($data);$i++){

if(
isset($data[$i]['user'])
&&
$data[$i]['user']==$user
){

if(!isset($data[$i]['messages'])){
$data[$i]['messages']=[];
}

$data[$i]['messages'][] = [

'id'=>uniqid(),

'sender'=>'admin',

'text'=>$text,

'image'=>$image,

'date'=>date('Y/m/d'),

'time'=>date('H:i'),

'timestamp'=>time(),

'seen_by_user'=>false

];

$data[$i]['status']='answered';

}

}

file_put_contents(
$file,
json_encode(
$data,
JSON_UNESCAPED_UNICODE|
JSON_PRETTY_PRINT
)
);

header(
"Location: support.php?user=".$user
);

exit;

}

$currentUser =
$_GET['user'] ?? '';

usort($data,function($a,$b){

$aTime = 0;
$bTime = 0;

if(isset($a['messages']) && count($a['messages'])>0){

$lastA =
end($a['messages']);

$aTime =
$lastA['timestamp'] ?? 0;

}

if(isset($b['messages']) && count($b['messages'])>0){

$lastB =
end($b['messages']);

$bTime =
$lastB['timestamp'] ?? 0;

}

return $bTime - $aTime;

});

?>

<!DOCTYPE html>

<html lang="fa">

<head>

<meta charset="UTF-8">

<meta name="viewport"
content="width=device-width, initial-scale=1.0">

<title>

پشتیبانی مدیریت

</title>

<style>

*{
box-sizing:border-box;
}

body{
margin:0;
background:#0f172a;
font-family:tahoma;
direction:rtl;
color:white;
display:flex;
height:100vh;
overflow:hidden;
}

.sidebar{
width:320px;
background:#1e293b;
overflow-y:auto;
padding:15px;
border-left:1px solid #334155;
}

.user{
display:block;
background:#0f172a;
padding:14px;
border-radius:14px;
margin-bottom:10px;
text-decoration:none;
color:white;
line-height:28px;
}

.user:hover{
background:#334155;
}

.userTop{
display:flex;
align-items:center;
gap:8px;
margin-bottom:6px;
}

.redDot{
width:10px;
height:10px;
background:#ef4444;
border-radius:50%;
display:inline-block;
}

.chatbox{
flex:1;
display:flex;
flex-direction:column;
padding:15px;
}

.chatHeader{
background:#1e293b;
padding:16px 20px;
border-radius:18px;
margin-bottom:14px;
font-size:18px;
font-weight:bold;
}

.messages{
flex:1;
overflow-y:auto;
background:#1e293b;
border-radius:18px;
padding:15px;
margin-bottom:15px;
display:flex;
flex-direction:column;
}

.msg{
padding:14px;
border-radius:16px;
margin-bottom:12px;
max-width:80%;
line-height:30px;
word-break:break-word;
}

.admin{
background:#22c55e;
margin-left:auto;
}

.usermsg{
background:#334155;
margin-right:auto;
}

.msg img{
max-width:240px;
border-radius:12px;
margin-top:10px;
display:block;
}

.time{
font-size:11px;
opacity:0.7;
margin-top:8px;
display:flex;
gap:8px;
align-items:center;
flex-wrap:wrap;
}

.action{
color:white;
text-decoration:none;
font-size:14px;
width:28px;
height:28px;
display:flex;
align-items:center;
justify-content:center;
background:rgba(255,255,255,0.12);
border-radius:8px;
}

.sendbox{
background:#1e293b;
padding:12px;
border-radius:18px;
}

.formrow{
display:flex;
gap:10px;
align-items:flex-end;
background:#0f172a;
padding:10px;
border-radius:16px;
}

.sidebuttons{
display:flex;
flex-direction:column;
gap:6px;
}

textarea{
flex:1;
min-height:52px;
max-height:180px;
padding:14px;
border:none;
border-radius:14px;
background:transparent;
color:white;
font-family:tahoma;
resize:none;
outline:none;
line-height:28px;
font-size:15px;
overflow-y:auto;
}

.attach{
width:42px;
height:42px;
background:#334155;
border-radius:12px;
display:flex;
align-items:center;
justify-content:center;
cursor:pointer;
font-size:18px;
}

.attach input{
display:none;
}

.sendbtn{
width:42px;
height:42px;
border:none;
border-radius:12px;
background:#22c55e;
color:white;
font-size:18px;
cursor:pointer;
}

.editbox{
margin-top:10px;
}

.editbox textarea{
background:#0f172a;
min-height:80px;
}

.editbtn{
margin-top:10px;
background:#22c55e;
width:42px;
height:42px;
border:none;
border-radius:12px;
color:white;
cursor:pointer;
font-size:20px;
padding:0;
}

.empty{
margin:auto;
color:#94a3b8;
font-size:18px;
}

.back{
display:block;
background:#334155;
padding:14px;
border-radius:14px;
text-align:center;
text-decoration:none;
color:white;
margin-top:15px;
}

@media(max-width:768px){

body{
flex-direction:column;
height:auto;
overflow:auto;
}

.sidebar{
width:100%;
border-left:none;
border-bottom:1px solid #334155;
max-height:250px;
}

.messages{
height:60vh;
}

}

</style>

</head>

<body>

<div class="sidebar">

<h2>

پیام های کاربران

</h2>

<?php foreach($data as $ticket){

$hasUnread = false;

if(isset($ticket['messages'])){

foreach($ticket['messages'] as $msg){

if(

isset($msg['sender'])

&&

$msg['sender']=='user'

&&

empty($msg['seen_by_admin'])

){

$hasUnread = true;
break;

}

}

}

?>

<a
href="?user=<?php echo urlencode($ticket['user']); ?>"
class="user">

<div class="userTop">

<span>👤</span>

<?php if($hasUnread){ ?>

<span class="redDot"></span>

<?php } ?>

</div>

<?php echo htmlspecialchars($ticket['user']); ?>

<br>

وضعیت:

<?php echo htmlspecialchars($ticket['status'] ?? '-'); ?>

</a>

<?php } ?>

<a href="index.php"
class="back">

بازگشت

</a>

</div>

<div class="chatbox">

<?php if($currentUser==''){ ?>

<div class="empty">

یک کاربر را انتخاب کنید

</div>

<?php } ?>

<?php if($currentUser!=''){ ?>

<div class="chatHeader">

چت با :

<?php echo htmlspecialchars($currentUser); ?>

</div>

<div class="messages">

<?php

foreach($data as $ticket){

if(
isset($ticket['user'])
&&
$ticket['user']==$currentUser
){

if(isset($ticket['messages'])){

foreach($ticket['messages'] as $m){

?>

<div class="msg <?php echo ($m['sender']=='admin') ? 'admin' : 'usermsg'; ?>">

<?php echo nl2br(htmlspecialchars($m['text'] ?? '')); ?>

<?php if(!empty($m['edited'])){ ?>

<br>

<small>

(ویرایش شد)

</small>

<?php } ?>

<?php if(!empty($m['image'])){ ?>

<br>

<img
src="<?php echo '/'.ltrim($m['image'],'/'); ?>">

<?php } ?>

<div class="time">

<?php echo $m['date'] ?? ''; ?>

-

<?php echo $m['time'] ?? ''; ?>

<a
href="?user=<?php echo urlencode($currentUser); ?>&edit=<?php echo urlencode($m['id']); ?>"
class="action">

✏️

</a>

<a
href="?user=<?php echo urlencode($currentUser); ?>&delete=<?php echo urlencode($m['id']); ?>"
class="action">

🗑

</a>

</div>

<?php if(
isset($_GET['edit'])
&&
$_GET['edit']==$m['id']
){ ?>

<form
method="POST"
class="editbox">

<textarea
name="edit_text"
required><?php echo htmlspecialchars($m['text']); ?></textarea>

<input
type="hidden"
name="edit_id"
value="<?php echo htmlspecialchars($m['id']); ?>">

<input
type="hidden"
name="user"
value="<?php echo htmlspecialchars($currentUser); ?>">

<button
type="submit"
class="editbtn">

✓

</button>

</form>

<?php } ?>

</div>

<?php

}

}

}

}

?>

</div>

<div class="sendbox">

<form
method="POST"
enctype="multipart/form-data">

<input
type="hidden"
name="user"
value="<?php echo htmlspecialchars($currentUser); ?>">

<div class="formrow">

<div class="sidebuttons">

<label class="attach">

📎

<input
type="file"
name="image"
accept="image/*">

</label>

<button
type="submit"
name="reply"
class="sendbtn">

➤

</button>

</div>

<textarea
name="message"
id="message"
placeholder="پاسخ پشتیبانی..."
required></textarea>

</div>

</form>

</div>

<?php } ?>

</div>

<script>

const textarea =
document.getElementById('message');

if(textarea){

textarea.addEventListener('input',function(){

this.style.height='52px';

this.style.height=
(this.scrollHeight)+'px';

});

}

</script>

</body>

</html>