<?php

// Live panel includes this from /bigjay_controller/ where auth.php & functions.php
// are often missing (HTTP 404). Hard require_once fatals → blank content area.
foreach ([__DIR__ . '/auth.php', __DIR__ . '/functions.php'] as $renewsBootFile) {
    if (is_file($renewsBootFile)) {
        require_once $renewsBootFile;
    }
}

if (!function_exists('pnvAdminUrl')) {
    function pnvAdminUrl($path = 'index.php') {
        $base = defined('PNV_ADMIN_BASE') ? rtrim(PNV_ADMIN_BASE, '/') : '/bigjay_controller';
        if ($path === '' || $path === 'index.php') {
            return $base . '/';
        }
        if (strpos($path, '?') !== false) {
            [$file, $query] = explode('?', $path, 2);
            return $base . '/' . ltrim($file, '/') . '?' . $query;
        }
        return $base . '/' . ltrim($path, '/');
    }
}

// Accept new pnv_admin session OR legacy $_SESSION['admin'].
// Do NOT call pnvAdminIsLoggedIn() — it unsets legacy admin and blanks the page.
$renewsLoggedIn = (
    !empty($_SESSION['pnv_admin']['user'])
    && !empty($_SESSION['pnv_admin']['token'])
) || !empty($_SESSION['admin']);

if (!$renewsLoggedIn) {
    // Match old payments.php behavior when embedded in index
    echo '<div style="padding:20px;color:#fecaca;background:#7f1d1d;border-radius:12px;margin:12px 0;">نشست مدیریت معتبر نیست. دوباره وارد شوید.</div>';
    return;
}

$_SESSION['admin'] = true;

if (is_file(__DIR__ . '/../xui_lib.php')) {
    require_once __DIR__ . '/../xui_lib.php';
}

$paymentsFile = dirname(__DIR__) . '/invoices/payments.csv';
$usersFile = dirname(__DIR__) . '/db/users.json';
if (!is_file($paymentsFile) && is_file(__DIR__ . '/../invoices/payments.csv')) {
    $paymentsFile = __DIR__ . '/../invoices/payments.csv';
}
if (!is_file($usersFile) && is_file(__DIR__ . '/../db/users.json')) {
    $usersFile = __DIR__ . '/../db/users.json';
}

$payments = [];
$users = [];

if(file_exists($usersFile)){
    $users = json_decode(file_get_contents($usersFile), true);
}

if(!is_array($users)){
    $users = [];
}

if(file_exists($paymentsFile)){
    $f = fopen($paymentsFile, 'r');
    if($f){
        while(($d = fgetcsv($f)) !== false){
            $payments[] = $d;
        }
        fclose($f);
    }
}

if(!function_exists('getUserMobile')){
    function getUserMobile($username, $users){
        foreach((array)$users as $u){
            if(($u['username'] ?? '') === $username){
                return $u['mobile'] ?? '-';
            }
        }
        return '-';
    }
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

$xuiEnabled = function_exists('xuiIsEnabled') && function_exists('xuiLoadConfig')
    ? xuiIsEnabled(xuiLoadConfig())
    : false;

if($xuiEnabled && function_exists('xuiApprovePaymentIndex')){

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

$perPage = 20;
$currentPage = intval($_GET['p'] ?? 1);
if($currentPage < 1){
$currentPage = 1;
}

$totalItems = count($renews);
$totalPages = max(1, (int)ceil($totalItems / $perPage));
if($currentPage > $totalPages){
$currentPage = $totalPages;
}

$start = ($currentPage - 1) * $perPage;
$renewsPage = array_slice($renews, $start, $perPage);

if(!function_exists('renewsListUrl')){
function renewsListUrl($page){
return pnvAdminUrl('index.php?page=renews&p=' . intval($page));
}
}


if(!function_exists('renewParsePlanParts')){
function renewParsePlanParts($plan){
$plan = trim((string)$plan);
$out = ['size' => '', 'price' => '', 'raw' => $plan !== '' ? $plan : '-'];

if($plan === '' || $plan === '-'){
return $out;
}

if(strpos($plan, ' - ') !== false){
$parts = explode(' - ', $plan, 2);
$out['size'] = trim($parts[0] ?? '');
$out['price'] = trim($parts[1] ?? '');
return $out;
}

if(preg_match('/^(.+?)\s*[-–]\s*(.+)$/u', $plan, $m)){
$out['size'] = trim($m[1]);
$out['price'] = trim($m[2]);
return $out;
}

$out['size'] = $plan;
return $out;
}
}


if(!function_exists('renewParseSubTarget')){
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

// فقط SubID الفبانumeric؛ متن اضافه فرم را نگیر
if(preg_match('~https?://([^/:]+)(?::\d+)?/sub/([A-Za-z0-9]+)~i', $target, $m)){
$host = strtolower($m[1]);
$subId = $m[2];
}
elseif(preg_match('~/sub/([A-Za-z0-9]+)~i', $target, $m)){
$subId = $m[1];
}
elseif(preg_match('~\b([A-Za-z0-9]{8,32})\b~', $target, $m)){
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

return $out;
}
}


?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>

body{
background:linear-gradient(165deg,#0B1220 0%,#182537 55%,#0f172a 100%) !important;
background-attachment:fixed !important;
font-family:"Vazirmatn",tahoma,sans-serif !important;
}

.content{
background:transparent !important;
}

.content > .box.renewsPage{
background:rgba(24,37,55,.72);
backdrop-filter:blur(14px);
-webkit-backdrop-filter:blur(14px);
border:1px solid rgba(148,163,184,.12);
border-radius:24px;
padding:20px 16px 18px;
box-shadow:0 18px 50px rgba(0,0,0,.35);
overflow:visible;
}

.renewsPage h2{
margin:0 0 18px;
font-size:22px;
font-weight:700;
letter-spacing:-.02em;
text-align:right;
color:#fff;
}

.renewList{
display:flex;
flex-direction:column;
gap:14px;
}

.renewCard{
display:grid;
grid-template-columns:minmax(0,.95fr) minmax(0,1.55fr) max-content;
align-items:center;
gap:12px;
padding:16px 14px;
border-radius:16px;
background:linear-gradient(180deg,rgba(30,41,59,.95),rgba(15,23,42,.92));
border:1px solid rgba(148,163,184,.10);
box-shadow:0 10px 28px rgba(0,0,0,.28);
position:relative;
}

.renewCardEmpty{
justify-content:center;
text-align:center;
color:#94a3b8;
font-size:14px;
padding:28px 16px;
}

.renewColUser,
.renewColPlan{
min-width:0;
}

.renewColUser{
text-align:right;
padding-inline-end:2px;
}

.renewUserName{
display:block;
font-size:14px;
font-weight:700;
color:#fff;
line-height:1.35;
white-space:nowrap;
overflow:hidden;
text-overflow:ellipsis;
}

.renewUserMobile{
display:block;
margin-top:4px;
font-size:12px;
color:#94a3b8;
line-height:1.3;
white-space:nowrap;
overflow:hidden;
text-overflow:ellipsis;
direction:ltr;
unicode-bidi:plaintext;
}

.renewColPlan{
text-align:center;
}

.subCopyBtn{
display:block;
width:100%;
max-width:100%;
border:none;
background:transparent;
color:#fff;
font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;
font-size:12.5px;
font-weight:700;
text-align:center;
padding:0 !important;
margin:0 0 10px !important;
cursor:pointer;
white-space:nowrap;
overflow:hidden;
text-overflow:ellipsis;
letter-spacing:.01em;
}

.subCopyBtn:active{
opacity:.75;
}

.renewPlanFallback{
display:block;
font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;
font-size:12.5px;
font-weight:700;
margin-bottom:10px;
white-space:nowrap;
overflow:hidden;
text-overflow:ellipsis;
}

.renewPills{
display:flex;
flex-wrap:wrap;
justify-content:center;
gap:6px;
}

.renewPill{
display:inline-flex;
align-items:center;
justify-content:center;
max-width:100%;
padding:5px 10px;
border-radius:999px;
background:rgba(51,65,85,.85);
border:1px solid rgba(148,163,184,.12);
color:#e2e8f0;
font-size:11px;
font-weight:600;
line-height:1.2;
white-space:nowrap;
}

.renewPill strong{
color:#4ade80;
font-weight:700;
margin-inline-start:4px;
}

.renewActions{
display:flex;
align-items:center;
justify-content:center;
gap:8px;
position:relative;
flex-shrink:0;
}

.menuBtn{
width:30px !important;
height:30px;
min-width:30px;
border:none;
border-radius:10px;
background:#1e293b;
color:#e2e8f0;
font-size:15px;
cursor:pointer;
line-height:1;
display:inline-flex;
align-items:center;
justify-content:center;
flex:0 0 30px;
box-sizing:border-box;
margin:0 !important;
padding:0 !important;
box-shadow:inset 0 0 0 1px rgba(148,163,184,.16);
}

.statusIcon{
width:22px !important;
height:22px;
min-width:22px;
border:none;
border-radius:999px;
color:#fff;
display:inline-flex;
align-items:center;
justify-content:center;
flex:0 0 22px;
box-sizing:border-box;
margin:0 !important;
padding:0 !important;
box-shadow:0 4px 10px rgba(0,0,0,.25);
}

.statusIcon svg{
width:12px;
height:12px;
display:block;
stroke:#fff;
}

.statusIcon.is-ok{background:#22c55e}
.statusIcon.is-no{background:#ef4444}
.statusIcon.is-pending{background:#f59e0b}

.dropdown{
display:none;
position:fixed;
background:#0f172a;
width:180px;
padding:10px;
border-radius:14px;
z-index:100000;
box-shadow:0 14px 36px rgba(0,0,0,.45);
border:1px solid #334155;
}

.dropdown.active{display:block}

.dropdown button{
width:100%;
padding:11px;
border:none;
border-radius:10px;
margin-bottom:8px;
background:#334155;
color:#fff;
cursor:pointer;
font-family:inherit;
}

.dropdown button:last-child{margin-bottom:0}
.red{background:#ef4444 !important}

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
font-family:inherit;
}

.modal h3{margin:0 0 12px;text-align:center}
.modal .resultLead{text-align:center;font-size:16px;font-weight:700;margin:8px 0 10px}
.modal .resultDetail{text-align:center;font-size:12px;line-height:1.8;color:#cbd5e1;word-break:break-all;margin-bottom:16px}
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
font-family:inherit;
}

.modalBtns{display:flex;gap:10px}
.modalBtns button{
flex:1;
padding:12px;
border:none;
border-radius:10px;
cursor:pointer;
color:#fff;
font-family:inherit;
}

.green{background:#22c55e}
.gray{background:#475569}

.renewsPage .pagination{
margin-top:18px;
text-align:center;
}

.renewsPage .pagination a{
display:inline-flex;
align-items:center;
justify-content:center;
min-width:36px;
padding:8px 12px;
margin:4px;
background:#1e293b;
color:#fff;
border-radius:10px;
text-decoration:none;
border:1px solid rgba(148,163,184,.12);
}

.renewsPage .pagination a.active{
background:#22c55e;
border-color:transparent;
}

@media(max-width:560px){
.content > .box.renewsPage{
padding:16px 12px 14px;
border-radius:22px;
}
.renewCard{
grid-template-columns:minmax(0,.9fr) minmax(0,1.4fr) max-content;
gap:10px;
padding:14px 12px;
}
.renewUserName{font-size:13px}
.renewUserMobile{font-size:11px}
.subCopyBtn,
.renewPlanFallback{font-size:11.5px}
.renewPill{font-size:10.5px;padding:4px 8px}
.menuBtn{
width:28px !important;
height:28px;
min-width:28px;
flex:0 0 28px;
}
.statusIcon{
width:20px !important;
height:20px;
min-width:20px;
flex:0 0 20px;
}
.statusIcon svg{width:11px;height:11px}
}

</style>

<div class="box renewsPage">

<h2>لیست تمدید ها</h2>

<div class="renewList">

<?php if($totalItems === 0){ ?>
<div class="renewCard renewCardEmpty">تمدیدی برای نمایش نیست</div>
<?php } ?>

<?php foreach($renewsPage as $r){

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
$planParts = renewParsePlanParts($p[2] ?? '');

?>

<div class="renewCard">

<div class="renewColUser">
<span class="renewUserName"><?php echo htmlspecialchars($p[0] ?? '-', ENT_QUOTES, 'UTF-8'); ?></span>
<span class="renewUserMobile"><?php echo htmlspecialchars($mobile !== '' ? $mobile : '—', ENT_QUOTES, 'UTF-8'); ?></span>
</div>

<div class="renewColPlan">
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
<span class="renewPlanFallback"><?php echo htmlspecialchars($targetLabel, ENT_QUOTES, 'UTF-8'); ?></span>
<?php } ?>

<div class="renewPills">
<?php if($planParts['size'] !== ''){ ?>
<span class="renewPill">حجم<strong><?php echo htmlspecialchars($planParts['size'], ENT_QUOTES, 'UTF-8'); ?></strong></span>
<?php } ?>
<?php if($planParts['price'] !== ''){ ?>
<span class="renewPill">قیمت<strong><?php echo htmlspecialchars($planParts['price'], ENT_QUOTES, 'UTF-8'); ?></strong></span>
<?php } elseif($planParts['size'] === ''){ ?>
<span class="renewPill"><strong><?php echo htmlspecialchars($planParts['raw'], ENT_QUOTES, 'UTF-8'); ?></strong></span>
<?php } ?>
</div>
</div>

<div class="renewActions">

<?php if($statusClass === 'is-ok'){ ?>
<span class="statusIcon is-ok" title="تایید شد" aria-label="تایید شد">
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
</span>
<?php } elseif($statusClass === 'is-no'){ ?>
<span class="statusIcon is-no" title="رد شد" aria-label="رد شد">
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
</span>
<?php } else { ?>
<span class="statusIcon is-pending" title="درحال بررسی" aria-label="درحال بررسی">
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
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

<?php if($totalPages > 1){ ?>
<div class="pagination">
<?php for($x = 1; $x <= $totalPages; $x++){ ?>
<a
href="<?php echo htmlspecialchars(renewsListUrl($x), ENT_QUOTES, 'UTF-8'); ?>"
class="<?php echo ($currentPage === $x) ? 'active' : ''; ?>">
<?php echo $x; ?>
</a>
<?php } ?>
</div>
<?php } ?>

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
