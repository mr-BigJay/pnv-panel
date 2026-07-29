<?php

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/../xui_lib.php';

if(!pnvAdminIsLoggedIn()){
header('Location: ' . pnvAdminEntryUrl());
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

$paymentMessage = '';
$paymentError = '';
$paymentMessageDetail = '';

if(isset($_SESSION['payment_message'])){
$paymentMessage = $_SESSION['payment_message'];
unset($_SESSION['payment_message']);
}

if(isset($_SESSION['payment_message_detail'])){
$paymentMessageDetail = $_SESSION['payment_message_detail'];
unset($_SESSION['payment_message_detail']);
}

if(isset($_SESSION['payment_error'])){
$paymentError = $_SESSION['payment_error'];
unset($_SESSION['payment_error']);
}

if(isset($_POST['approve_payment'])){

$index=intval($_POST['approve_index']);
$link=trim($_POST['approve_link'] ?? '');

$xuiConfig = xuiLoadConfig();

if(function_exists('xuiIsEnabled') ? xuiIsEnabled($xuiConfig) : !empty($xuiConfig['enabled'])){

$result = xuiApprovePaymentIndex($index, 'تمدید');

if(empty($result['ok'])){
$_SESSION['payment_error'] = 'تمدید خودکار ناموفق: ' . ($result['error'] ?? 'خطای نامشخص');
header('Location: ' . pnvAdminUrl('index.php?page=renews'));
exit;
}

$_SESSION['payment_message'] = 'تمدید تایید و اعمال شد';
$_SESSION['payment_message_detail'] = (string)($result['link'] ?? '');
header('Location: ' . pnvAdminUrl('index.php?page=renews'));
exit;

}

if(isset($payments[$index])){

$payments[$index][6]='تایید شد';
$payments[$index][7]=$link;

}

$fp=fopen($paymentsFile,'w');

foreach($payments as $p){
fputcsv($fp,$p);
}

fclose($fp);

$_SESSION['payment_message'] = 'تمدید تایید شد';
$_SESSION['payment_message_detail'] = $link;
header('Location: ' . pnvAdminUrl('index.php?page=renews'));
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

$_SESSION['payment_message'] = 'تمدید رد شد';
$_SESSION['payment_message_detail'] = $reason;
header('Location: ' . pnvAdminUrl('index.php?page=renews'));
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

header('Location: ' . pnvAdminUrl('index.php?page=renews'));
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

function renewParseSubTarget($target){
$target = trim((string)$target);
$out = [
'vip' => '',
'sub_id' => '',
'label' => '-'
];

if($target === ''){
return $out;
}

$host = '';
$subId = '';

if(preg_match('#https?://([^/:]+)(?::\d+)?/sub/([^/?#]+)#i', $target, $m)){
$host = strtolower($m[1]);
$subId = $m[2];
}
elseif(preg_match('#/sub/([^/?#]+)#i', $target, $m)){
$subId = $m[1];
}

if(preg_match('/^(vip\d*)\./i', $host, $m)){
$out['vip'] = strtolower($m[1]);
}
elseif(preg_match('/^(vip\d*)$/i', $host, $m)){
$out['vip'] = strtolower($m[1]);
}

$out['sub_id'] = $subId;

if($out['vip'] !== '' && $subId !== ''){
$out['label'] = $out['vip'] . '-' . $subId;
}
elseif($subId !== ''){
$out['label'] = $subId;
}
elseif($target !== ''){
$out['label'] = $target;
}

return $out;
}

?>

<style>

.renewTable{
background:#1e293b;
border-radius:16px;
overflow:visible;
color:#fff;
}

.renewRow{
display:grid;
grid-template-columns:minmax(0,1.1fr) minmax(0,1.2fr) auto;
align-items:center;
gap:10px;
padding:10px 12px;
border-bottom:1px solid #334155;
position:relative;
}

.renewRow:last-child{
border-bottom:none;
}

.renewCol{
min-width:0;
font-size:13px;
line-height:1.45;
}

.renewCol b{
display:block;
font-size:13px;
font-weight:700;
white-space:nowrap;
overflow:hidden;
text-overflow:ellipsis;
}

.renewCol span{
display:block;
color:#94a3b8;
font-size:12px;
white-space:nowrap;
overflow:hidden;
text-overflow:ellipsis;
}

.renewActions{
display:flex;
align-items:center;
gap:8px;
justify-content:flex-start;
position:relative;
}

.menuBtn,
.statusIcon{
width:38px;
height:38px;
border-radius:10px;
display:inline-flex;
align-items:center;
justify-content:center;
flex:0 0 38px;
box-sizing:border-box;
}

.menuBtn{
border:none;
background:#334155;
color:#fff;
font-size:18px;
cursor:pointer;
line-height:1;
}

.statusIcon{
border:none;
color:#fff;
}

.statusIcon svg{
width:18px;
height:18px;
display:block;
stroke:#fff;
}

.statusIcon.is-ok{
background:#22c55e;
}

.statusIcon.is-no{
background:#ef4444;
}

.statusIcon.is-pending{
background:#f59e0b;
}

.subCopyBtn{
display:block;
width:100%;
max-width:100%;
border:none;
background:transparent;
color:#fff;
font:inherit;
font-size:13px;
font-weight:700;
text-align:right;
padding:0;
cursor:pointer;
white-space:nowrap;
overflow:hidden;
text-overflow:ellipsis;
}

.subCopyBtn:active{
opacity:.75;
}

.dropdown{
display:none;
position:fixed;
background:#0f172a;
width:180px;
padding:10px;
border-radius:14px;
z-index:100000;
box-shadow:0 10px 30px rgba(0,0,0,.35);
border:1px solid #334155;
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

.dropdown button:last-child{
margin-bottom:0;
}

.red{
background:#ef4444 !important;
}

.modalOverlay{
position:fixed;
inset:0;
background:rgba(0,0,0,.55);
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

.modal h3{
margin:0 0 12px;
text-align:center;
}

.modal .resultLead{
text-align:center;
font-size:16px;
font-weight:700;
margin:8px 0 10px;
}

.modal .resultDetail{
text-align:center;
font-size:12px;
line-height:1.8;
color:#cbd5e1;
word-break:break-all;
margin-bottom:16px;
}

.modal .resultOk .resultLead{color:#86efac}
.modal .resultErr .resultLead{color:#fecaca}

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

.green{background:#22c55e}
.gray{background:#475569}

@media(max-width:560px){
.renewRow{
grid-template-columns:minmax(0,1fr) auto;
grid-template-areas:
"user actions"
"plan actions";
gap:6px 10px;
padding:10px;
}
.renewColUser{grid-area:user}
.renewColPlan{grid-area:plan}
.renewActions{grid-area:actions;align-self:center}
}

</style>

<div class="box">

<h2>لیست تمدید ها</h2>

<div class="renewTable">

<?php if(count($renews) === 0){ ?>
<div class="renewRow" style="justify-content:center;color:#94a3b8">تمدیدی برای نمایش نیست</div>
<?php } ?>

<?php foreach($renews as $r){

$i=$r['index'];
$p=$r['data'];

$status=trim((string)($p[6] ?? ''));
if($status === ''){
$status = 'درحال بررسی';
}

$statusClass = 'is-pending';
if($status === 'تایید شد'){
$statusClass = 'is-ok';
}
elseif($status === 'رد شد'){
$statusClass = 'is-no';
}

$mobile=getUserMobile($p[0] ?? '',$users);
$parsedTarget = renewParseSubTarget($p[1] ?? '');
$targetLabel = $parsedTarget['label'];
$targetSubId = $parsedTarget['sub_id'];
$planName = trim((string)($p[2] ?? '-'));
if($planName === ''){
$planName = '-';
}

?>

<div class="renewRow">

<div class="renewCol renewColUser">
<b><?php echo htmlspecialchars($p[0] ?? '-', ENT_QUOTES, 'UTF-8'); ?></b>
<span><?php echo htmlspecialchars($mobile, ENT_QUOTES, 'UTF-8'); ?></span>
</div>

<div class="renewCol renewColPlan">
<?php if($targetSubId !== ''){ ?>
<button
type="button"
class="subCopyBtn"
title="برای کپی SubID لمس کنید"
onclick="copyRenewSubId(this)"
data-subid="<?php echo htmlspecialchars($targetSubId, ENT_QUOTES, 'UTF-8'); ?>">
<?php echo htmlspecialchars($targetLabel, ENT_QUOTES, 'UTF-8'); ?>
</button>
<?php } else { ?>
<b><?php echo htmlspecialchars($targetLabel, ENT_QUOTES, 'UTF-8'); ?></b>
<?php } ?>
<span><?php echo htmlspecialchars($planName, ENT_QUOTES, 'UTF-8'); ?></span>
</div>

<div class="renewActions">

<?php if($statusClass === 'is-ok'){ ?>
<span class="statusIcon is-ok" title="تایید شد" aria-label="تایید شد">
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
</span>
<?php } elseif($statusClass === 'is-no'){ ?>
<span class="statusIcon is-no" title="رد شد" aria-label="رد شد">
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
</span>
<?php } else { ?>
<span class="statusIcon is-pending" title="درحال بررسی" aria-label="درحال بررسی">
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
</span>
<?php } ?>

<button
class="menuBtn"
type="button"
aria-label="منو"
onclick="openMenu(event,'m<?php echo $i; ?>')">
⋮
</button>

<div class="dropdown" id="m<?php echo $i; ?>">
<button type="button" onclick="openDetails(
'<?php echo htmlspecialchars($p[0] ?? '-',ENT_QUOTES); ?>',
'<?php echo htmlspecialchars($mobile,ENT_QUOTES); ?>',
'<?php echo htmlspecialchars($p[1] ?? '-',ENT_QUOTES); ?>',
'<?php echo htmlspecialchars($p[2] ?? '-',ENT_QUOTES); ?>',
'<?php echo htmlspecialchars($p[3] ?? '-',ENT_QUOTES); ?>',
'<?php echo htmlspecialchars($p[4] ?? '-',ENT_QUOTES); ?>',
'<?php echo htmlspecialchars($p[5] ?? '-',ENT_QUOTES); ?>'
)">جزئیات پرداخت</button>
<button type="button" onclick="openAction(
'<?php echo $i; ?>',
'<?php echo htmlspecialchars($p[0] ?? '-',ENT_QUOTES); ?>',
'<?php echo htmlspecialchars($mobile,ENT_QUOTES); ?>',
'<?php echo htmlspecialchars($p[2] ?? '-',ENT_QUOTES); ?>'
)">عملیات</button>
<button type="button" class="red" onclick="deleteItem('<?php echo $i; ?>')">حذف</button>
</div>

</div>

</div>

<?php } ?>

</div>

</div>

<div class="modalOverlay" id="modal">
<div class="modal" id="modalBody"></div>
</div>

<script>

var renewResultMessage = <?php echo json_encode($paymentMessage, JSON_UNESCAPED_UNICODE); ?>;
var renewResultDetail = <?php echo json_encode($paymentMessageDetail, JSON_UNESCAPED_UNICODE); ?>;
var renewResultError = <?php echo json_encode($paymentError, JSON_UNESCAPED_UNICODE); ?>;
var renewsPageUrl = <?php echo json_encode(pnvAdminUrl('index.php?page=renews'), JSON_UNESCAPED_UNICODE); ?>;

function closeMenus(){
document.querySelectorAll('.dropdown').forEach(function(el){
el.classList.remove('active');
});
}

function openMenu(e,id){
e.stopPropagation();
closeMenus();

var m=document.getElementById(id);
if(!m){ return; }

m.classList.add('active');

var btn = e.currentTarget || e.target;
var r=btn.getBoundingClientRect();
var width = 180;
var left = r.left;
var top = r.bottom + 6;

if(left + width > window.innerWidth - 8){
left = r.right - width;
}

if(left < 8){
left = 8;
}

if(top + 180 > window.innerHeight - 8){
top = Math.max(8, r.top - 180);
}

m.style.top = top + 'px';
m.style.left = left + 'px';
}

document.addEventListener('click', closeMenus);
window.addEventListener('resize', closeMenus);
window.addEventListener('scroll', closeMenus, true);

function openModal(html){
closeMenus();
document.getElementById('modalBody').innerHTML=html;
document.getElementById('modal').style.display='flex';
}

function closeModal(){
document.getElementById('modal').style.display='none';
}

function showResultModal(ok, title, detail){
var cls = ok ? 'resultOk' : 'resultErr';
var safeDetail = detail ? String(detail) : '';
openModal(
'<div class="'+cls+'">'+
'<h3>نتیجه عملیات</h3>'+
'<div class="resultLead">'+title+'</div>'+
(safeDetail ? '<div class="resultDetail">'+safeDetail+'</div>' : '')+
'<div class="modalBtns">'+
'<button class="gray" type="button" onclick="closeModal()">بستن</button>'+
'</div>'+
'</div>'
);
}

function copySubId(id){
navigator.clipboard.writeText(id);
alert('SubID کپی شد');
}

function copyRenewSubId(btn){
var id = (btn && btn.getAttribute('data-subid')) || '';
if(!id){
return;
}

if(navigator.clipboard && navigator.clipboard.writeText){
navigator.clipboard.writeText(id).then(function(){
btn.style.opacity = '0.55';
setTimeout(function(){ btn.style.opacity = '1'; }, 250);
}).catch(function(){
window.prompt('SubID را کپی کنید:', id);
});
return;
}

window.prompt('SubID را کپی کنید:', id);
}

function openDetails(user,mobile,config,plan,track,date,time){
var subid='';
try{
subid = config.split('/sub/')[1] || '';
}catch(e){
subid='';
}

openModal(
'<h3>جزئیات پرداخت</h3>'+
'<p><b>کاربر:</b> '+user+'</p>'+
'<p><b>موبایل:</b> '+mobile+'</p>'+
'<p><b>لینک:</b> '+config+'</p>'+
'<p><b>SubID:</b> '+subid+'</p>'+
'<button style="width:100%;padding:10px;border:none;border-radius:10px;background:#22c55e;color:white;cursor:pointer;margin-bottom:12px;" onclick="copySubId(\''+subid+'\')">📋 کپی SubID</button>'+
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
location.href = renewsPageUrl + (renewsPageUrl.indexOf('?') >= 0 ? '&' : '?') + 'deletepayment=' + encodeURIComponent(id);
}
}

if(renewResultError){
showResultModal(false, renewResultError, '');
}else if(renewResultMessage){
showResultModal(true, renewResultMessage, renewResultDetail);
}

</script>
