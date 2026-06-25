<?php

if(!isset($_SESSION['admin'])){
exit;
}

$paymentsFile='../invoices/payments.csv';
$usersFile='../db/users.json';

$payments=[];
$users=[];

if(file_exists($usersFile)){
$users=json_decode(file_get_contents($usersFile),true);
}

if(!is_array($users)){
$users=[];
}

if(file_exists($paymentsFile)){

$f=fopen($paymentsFile,'r');

while(($d=fgetcsv($f))!==false){
$payments[]=$d;
}

fclose($f);

}

if(isset($_POST['approve_payment'])){

$index=intval($_POST['approve_index']);
$link=trim($_POST['approve_link']);

if(isset($payments[$index])){

$payments[$index][6]='تایید شد';
$payments[$index][7]=$link;

}

$fp=fopen($paymentsFile,'w');

foreach($payments as $p){
fputcsv($fp,$p);
}

fclose($fp);

header('Location:index.php?page=renews');
exit;

}

if(isset($_POST['reject_payment'])){

$index=intval($_POST['reject_index']);
$reason=trim($_POST['reject_reason']);

if(isset($payments[$index])){

$payments[$index][6]='رد شد';
$payments[$index][7]=$reason;

}

$fp=fopen($paymentsFile,'w');

foreach($payments as $p){
fputcsv($fp,$p);
}

fclose($fp);

header('Location:index.php?page=renews');
exit;

}

if(isset($_GET['deletepayment'])){

$id=intval($_GET['deletepayment']);

if(isset($payments[$id])){

unset($payments[$id]);

$payments=array_values($payments);

}

$fp=fopen($paymentsFile,'w');

foreach($payments as $p){
fputcsv($fp,$p);
}

fclose($fp);

header('Location:index.php?page=renews');
exit;

}

$renews=[];

foreach($payments as $index=>$pay){

$type=trim($pay[9] ?? '');

if($type=='تمدید'){

$renews[]=[
'index'=>$index,
'data'=>$pay
];

}

}

$renews=array_reverse($renews);

?>

<style>

.renewCard{
background:#1e293b;
padding:15px;
border-radius:16px;
margin-bottom:15px;
color:#fff;
}

.rowTop{
display:flex;
justify-content:space-between;
align-items:center;
gap:10px;
}

.userInfo{
font-size:13px;
line-height:28px;
}

.status{
padding:6px 10px;
border-radius:10px;
font-size:12px;
display:inline-block;
}

.pending{
background:#facc15;
color:#000;
}

.ok{
background:#22c55e;
}

.no{
background:#ef4444;
}

.menuBtn{
width:38px;
height:38px;
border:none;
border-radius:10px;
background:#334155;
color:#fff;
font-size:18px;
cursor:pointer;
}

.dropdown{
display:none;
position:fixed;
background:#0f172a;
width:180px;
padding:10px;
border-radius:14px;
z-index:999999;
}

.dropdown.active{
display:block;
}

.dropdown button{
width:100%;
padding:11px;
border:none;
border-radius:10px;
margin-bottom:8px;
background:#334155;
color:#fff;
cursor:pointer;
}

.red{
background:#ef4444 !important;
}

.modalOverlay{
position:fixed;
inset:0;
background:rgba(0,0,0,.5);
display:none;
justify-content:center;
align-items:center;
padding:15px;
z-index:9999999;
}

.modal{
background:#1e293b;
padding:20px;
border-radius:18px;
width:100%;
max-width:420px;
color:#fff;
}

.modal input,
.modal select{
width:100%;
padding:12px;
margin-bottom:12px;
border:none;
border-radius:10px;
background:#0f172a;
color:#fff;
box-sizing:border-box;
}

.modalBtns{
display:flex;
gap:10px;
}

.modalBtns button{
flex:1;
padding:12px;
border:none;
border-radius:10px;
cursor:pointer;
color:#fff;
}

.green{
background:#22c55e;
}

.gray{
background:#475569;
}

</style>

<div class="box">

<h2>

لیست تمدید ها

</h2>

<?php foreach($renews as $r){

$i=$r['index'];
$p=$r['data'];

$status=$p[6] ?? '';

$class='pending';

if($status=='تایید شد'){
$class='ok';
}

if($status=='رد شد'){
$class='no';
}

$mobile=getUserMobile($p[0] ?? '',$users);

?>

<div class="renewCard">

<div class="rowTop">

<div class="userInfo">

<b>

<?php echo htmlspecialchars($p[0] ?? '-'); ?>

</b>

<br>

<?php echo htmlspecialchars($mobile); ?>

</div>

<div>

<span class="status <?php echo $class; ?>">

<?php echo $status ?: 'درحال بررسی'; ?>

</span>

</div>

<div>

<button
class="menuBtn"
onclick="openMenu(event,'m<?php echo $i; ?>')">

⋮

</button>

<div
class="dropdown"
id="m<?php echo $i; ?>">

<button
onclick="openDetails(
'<?php echo htmlspecialchars($p[0] ?? '-',ENT_QUOTES); ?>',
'<?php echo htmlspecialchars($mobile,ENT_QUOTES); ?>',
'<?php echo htmlspecialchars($p[1] ?? '-',ENT_QUOTES); ?>',
'<?php echo htmlspecialchars($p[2] ?? '-',ENT_QUOTES); ?>',
'<?php echo htmlspecialchars($p[3] ?? '-',ENT_QUOTES); ?>',
'<?php echo htmlspecialchars($p[4] ?? '-',ENT_QUOTES); ?>',
'<?php echo htmlspecialchars($p[5] ?? '-',ENT_QUOTES); ?>'
)">

جزئیات پرداخت

</button>

<button
onclick="openAction(
'<?php echo $i; ?>',
'<?php echo htmlspecialchars($p[0] ?? '-',ENT_QUOTES); ?>',
'<?php echo htmlspecialchars($mobile,ENT_QUOTES); ?>',
'<?php echo htmlspecialchars($p[2] ?? '-',ENT_QUOTES); ?>'
)">

عملیات

</button>

<button
class="red"
onclick="deleteItem('<?php echo $i; ?>')">

حذف

</button>

</div>

</div>

</div>

</div>

<?php } ?>

</div>

<div class="modalOverlay" id="modal">

<div class="modal" id="modalBody"></div>

</div>

<script>

function closeMenus(){

document
.querySelectorAll('.dropdown')
.forEach(function(el){

el.classList.remove('active');

});

}

function openMenu(e,id){

e.stopPropagation();

closeMenus();

var m=document.getElementById(id);

m.classList.add('active');

var r=e.target.getBoundingClientRect();

m.style.top=(r.bottom+5)+'px';
m.style.left=(r.right-180)+'px';

}

document.addEventListener('click',function(){

closeMenus();

});

function openModal(html){

closeMenus();

document.getElementById('modalBody').innerHTML=html;

document.getElementById('modal').style.display='flex';

}

function closeModal(){

document.getElementById('modal').style.display='none';

}

function copySubId(id){

navigator.clipboard.writeText(id);

alert('SubID کپی شد');

}

function openDetails(user,mobile,config,plan,track,date,time){

var subid='';

try{

subid =
config.split('/sub/')[1] || '';

}catch(e){

subid='';

}

openModal(

'<h3>جزئیات پرداخت</h3>'+

'<p><b>کاربر:</b> '+user+'</p>'+

'<p><b>موبایل:</b> '+mobile+'</p>'+

'<p><b>لینک:</b> '+config+'</p>'+

'<p><b>SubID:</b> '+subid+'</p>'+

'<button '+
'style="width:100%;padding:10px;border:none;border-radius:10px;background:#22c55e;color:white;cursor:pointer;margin-bottom:12px;" '+
'onclick="copySubId(\''+subid+'\')">'+
'📋 کپی SubID'+
'</button>'+

'<p><b>پلن:</b> '+plan+'</p>'+

'<hr>'+

'<p><b>پیگیری:</b> '+track+'</p>'+

'<p><b>تاریخ:</b> '+date+' '+time+'</p>'+

'<div class="modalBtns">'+

'<button class="gray" onclick="closeModal()">بستن</button>'+

'</div>'

);

}

function openAction(id,user,mobile,plan){

openModal(

'<h3>عملیات تمدید</h3>'+

'<p><b>کاربر:</b> '+user+'</p>'+

'<p><b>موبایل:</b> '+mobile+'</p>'+

'<p><b>پلن:</b> '+plan+'</p>'+

'<form method="POST">'+

'<input type="hidden" name="approve_index" value="'+id+'">'+

'<input type="text" name="approve_link" placeholder="لینک تمدید">'+

'<div class="modalBtns">'+

'<button class="green" name="approve_payment">تایید</button>'+

'</div>'+

'</form>'+

'<hr style="margin:15px 0;">'+

'<form method="POST">'+

'<input type="hidden" name="reject_index" value="'+id+'">'+

'<select name="reject_reason">'+

'<option value="اطلاعات پرداخت اشتباه است">اطلاعات پرداخت اشتباه است</option>'+

'<option value="اطلاعات پرداخت تکراری است">اطلاعات پرداخت تکراری است</option>'+

'</select>'+

'<div class="modalBtns">'+

'<button class="red" name="reject_payment">رد پرداخت</button>'+

'</div>'+

'</form>'+

'<div class="modalBtns" style="margin-top:10px;">'+

'<button class="gray" onclick="closeModal()">بستن</button>'+

'</div>'

);

}

function deleteItem(id){

if(confirm('حذف شود؟')){

location.href=
'index.php?page=renews&deletepayment='+id;

}

}

</script>
