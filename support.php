<?php

session_start();

if(!isset($_SESSION['user'])){
header("Location: index.php");
exit;
}

$user = $_SESSION['user'];

$file = "db/support.json";

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

if(isset($_GET['delete'])){

$msgid = $_GET['delete'];

for($i=0;$i<count($data);$i++){

if($data[$i]['user']==$user){

for($j=0;$j<count($data[$i]['messages']);$j++){

$m = $data[$i]['messages'][$j];

if(
isset($m['id'])
&&
$m['id']==$msgid
&&
$m['sender']=='user'
){

if(
time()-$m['timestamp'] <= 60
){

unset($data[$i]['messages'][$j]);

$data[$i]['messages'] =
array_values(
$data[$i]['messages']
);

file_put_contents(
$file,
json_encode(
$data,
JSON_UNESCAPED_UNICODE|
JSON_PRETTY_PRINT
)
);

}

}

}

}

}

header("Location: support.php");
exit;

}

if(isset($_POST['edit_id'])){

$editid = $_POST['edit_id'];

$newtext =
trim($_POST['edit_text']);

for($i=0;$i<count($data);$i++){

if($data[$i]['user']==$user){

for($j=0;$j<count($data[$i]['messages']);$j++){

$m = &$data[$i]['messages'][$j];

if(
isset($m['id'])
&&
$m['id']==$editid
&&
$m['sender']=='user'
){

if(
time()-$m['timestamp'] <= 3600
){

$m['text'] = $newtext;

$m['edited'] = true;

file_put_contents(
$file,
json_encode(
$data,
JSON_UNESCAPED_UNICODE|
JSON_PRETTY_PRINT
)
);

}

}

}

}

}

header("Location: support.php");
exit;

}

if(isset($_POST['message'])){

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

if(!file_exists("uploads/support")){
mkdir("uploads/support",0777,true);
}

$image =
"uploads/support/".
time().
rand(1000,9999).
".".
$ext;

move_uploaded_file(
$_FILES['image']['tmp_name'],
$image
);

}

}

$ticketFound = false;

$newmsg = [

'id'=>uniqid(),

'sender'=>'user',

'text'=>$text,

'image'=>$image,

'date'=>date('Y/m/d'),

'time'=>date('H:i'),

'timestamp'=>time()

];

for($i=0;$i<count($data);$i++){

if($data[$i]['user']==$user){

$data[$i]['messages'][] = $newmsg;

$data[$i]['status']='open';

$ticketFound = true;

break;

}

}

if(!$ticketFound){

$data[] = [

'id'=>'SUP-'.rand(1000,9999),

'user'=>$user,

'status'=>'open',

'messages'=>[
$newmsg
]

];

}

file_put_contents(
$file,
json_encode(
$data,
JSON_UNESCAPED_UNICODE|
JSON_PRETTY_PRINT
)
);

header("Location: support.php");
exit;

}

$messages = [];

foreach($data as $ticket){

if($ticket['user']==$user){

if(isset($ticket['messages'])){

$messages = $ticket['messages'];

}

break;

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

پشتیبانی

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

.container{
max-width:760px;
margin:auto;
}

.chat{
background:#1e293b;
padding:15px;
border-radius:18px;
height:68vh;
overflow-y:auto;
margin-bottom:15px;
display:flex;
flex-direction:column-reverse;
}

.msg{
padding:14px;
border-radius:16px;
margin-bottom:12px;
max-width:85%;
line-height:30px;
word-break:break-word;
position:relative;
}

.user{
background:#2563eb;
margin-left:auto;
}

.admin{
background:#334155;
margin-right:auto;
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

.msg img{
max-width:100%;
margin-top:10px;
border-radius:12px;
}

.empty{
text-align:center;
padding:40px 20px;
color:#94a3b8;
line-height:34px;
}

.formbox{
background:#1e293b;
padding:12px;
border-radius:18px;
}

.sendbox{
display:flex;
align-items:flex-end;
gap:8px;
background:#0f172a;
padding:8px;
border-radius:18px;
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
font-size:15px;
resize:none;
outline:none;
overflow-y:auto;
line-height:28px;
}

textarea::placeholder{
color:#94a3b8;
}

.attach{
width:42px;
height:42px;
background:#334155;
border-radius:12px;
display:flex;
align-items:center;
justify-content:center;
font-size:18px;
cursor:pointer;
flex-shrink:0;
margin-bottom:2px;
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
flex-shrink:0;
padding:0;
margin-bottom:2px;
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

.editbox{
margin-top:10px;
}

.editbox textarea{
background:#1e293b;
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

.back{
display:block;
margin-top:15px;
background:#334155;
padding:15px;
border-radius:16px;
text-align:center;
color:white;
text-decoration:none;
}

@media(max-width:768px){

.chat{
height:64vh;
}

.msg{
max-width:92%;
font-size:15px;
line-height:28px;
}

textarea{
font-size:16px;
}

}

</style>

</head>

<body>

<div class="container">

<div class="chat">

<?php if(count($messages)==0){ ?>

<div class="empty">

هنوز پیامی ارسال نشده است

</div>

<?php } ?>

<?php foreach(array_reverse($messages) as $m){ ?>

<div class="msg <?php echo $m['sender']; ?>">

<?php echo nl2br(htmlspecialchars($m['text'])); ?>

<?php if(isset($m['edited'])){ ?>

<br><small>(ویرایش شد)</small>

<?php } ?>

<?php if($m['image']!=""){ ?>

<br>

<img src="<?php echo $m['image']; ?>">

<?php } ?>

<div class="time">

<?php echo $m['date']; ?>

-

<?php echo $m['time']; ?>

<?php if(
$m['sender']=='user'
&&
time()-$m['timestamp'] <= 3600
){ ?>

<a
href="?edit=<?php echo $m['id']; ?>"
class="action">

✏️

</a>

<?php } ?>

<?php if(
$m['sender']=='user'
&&
time()-$m['timestamp'] <= 60
){ ?>

<a
href="?delete=<?php echo $m['id']; ?>"
class="action">

🗑

</a>

<?php } ?>

</div>

<?php if(
isset($_GET['edit'])
&&
$_GET['edit']==$m['id']
&&
$m['sender']=='user'
){ ?>

<form method="POST"
class="editbox">

<textarea
name="edit_text"
required><?php echo htmlspecialchars($m['text']); ?></textarea>

<input
type="hidden"
name="edit_id"
value="<?php echo $m['id']; ?>">

<button
type="submit"
class="editbtn">

✓

</button>

</form>

<?php } ?>

</div>

<?php } ?>

</div>

<div class="formbox">

<form method="POST"
enctype="multipart/form-data">

<div class="sendbox">

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
class="sendbtn">

➤

</button>

</div>

<textarea
name="message"
id="message"
placeholder="پیام خود را بنویسید..."
required></textarea>

</div>

</form>

</div>

<a href="dashboard.php"
class="back">

بازگشت

</a>

</div>

<script>

const textarea =
document.getElementById('message');

textarea.addEventListener('input',function(){

this.style.height='52px';

this.style.height=
(this.scrollHeight)+'px';

});

</script>

</body>

</html>
