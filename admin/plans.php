<?php

/**
 * Admin plans manager — safe to deploy as /bigjay_controller/plans.php
 * (auth.php / admin_nav.php are often missing there; soft-load like renews).
 */

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$plansBootCandidates = [
    __DIR__ . '/auth.php',
    __DIR__ . '/functions.php',
    __DIR__ . '/admin_nav.php',
    dirname(__DIR__) . '/admin/auth.php',
    dirname(__DIR__) . '/admin/functions.php',
    dirname(__DIR__) . '/admin/admin_nav.php',
];

foreach ($plansBootCandidates as $plansBootFile) {
    if (is_file($plansBootFile)) {
        require_once $plansBootFile;
    }
}

$planUiCandidates = [
    dirname(__DIR__) . '/plan_ui_lib.php',
    __DIR__ . '/../plan_ui_lib.php',
    __DIR__ . '/plan_ui_lib.php',
];

foreach ($planUiCandidates as $planUiFile) {
    if (is_file($planUiFile)) {
        require_once $planUiFile;
        break;
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

if (!function_exists('pnvFormatPlanPrice')) {
    function pnvFormatPlanPrice($price) {
        $price = intval($price);
        if ($price < 1000) {
            return number_format($price) . ' هزار تومان';
        }
        $million = rtrim(rtrim(number_format($price / 1000, 3), '0'), '.');
        return $million . ' میلیون تومان';
    }
}

if (!function_exists('pnvPlanIsUnlimited')) {
    function pnvPlanIsUnlimited($plan) {
        $days = trim((string)($plan['days'] ?? ''));
        if ($days === '' || $days === 'نامحدود' || strcasecmp($days, 'unlimited') === 0) {
            return true;
        }
        return intval($days) <= 0;
    }
}

if (!function_exists('pnvPlanDaysLabel')) {
    function pnvPlanDaysLabel($plan) {
        if (pnvPlanIsUnlimited($plan)) {
            return 'نامحدود';
        }
        $days = trim((string)($plan['days'] ?? ''));
        if ($days === '') {
            return '—';
        }
        if (preg_match('/^\d+$/', $days)) {
            return $days . ' روز';
        }
        return $days;
    }
}

if (!function_exists('adminQuickNav')) {
    function adminQuickNav($active = '') {}
    function adminQuickNavStyles() {}
}

// Accept new pnv_admin session OR legacy $_SESSION['admin'].
// Do NOT call pnvAdminIsLoggedIn() — it can unset legacy admin.
$plansLoggedIn = (
    !empty($_SESSION['pnv_admin']['user'])
    && !empty($_SESSION['pnv_admin']['token'])
) || !empty($_SESSION['admin']);

if (!$plansLoggedIn) {
    if (function_exists('pnvAdminEntryUrl')) {
        header('Location: ' . pnvAdminEntryUrl());
        exit;
    }
    header('Location: ' . pnvAdminUrl());
    exit;
}

$_SESSION['admin'] = true;

$plansFileCandidates = [
    dirname(__DIR__) . '/db/plans.json',
    __DIR__ . '/../db/plans.json',
    __DIR__ . '/db/plans.json',
];

$plansFile = $plansFileCandidates[0];
foreach ($plansFileCandidates as $candidate) {
    if (is_file($candidate) || is_dir(dirname($candidate))) {
        $plansFile = $candidate;
        if (is_file($candidate)) {
            break;
        }
    }
}

if (!is_file($plansFile)) {
    $dir = dirname($plansFile);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    file_put_contents($plansFile, "[]");
}

$plans = json_decode((string)file_get_contents($plansFile), true);
if (!is_array($plans)) {
    $plans = [];
}

$plansError = '';
$plansFlash = '';

function plansNormalizeDays($category, $daysRaw) {
    $category = ($category === 'limited') ? 'limited' : 'unlimited';
    $daysRaw = trim((string)$daysRaw);

    if ($category === 'unlimited') {
        return ['ok' => true, 'days' => 'نامحدود', 'error' => ''];
    }

    if ($daysRaw === '' || !preg_match('/^\d+$/', $daysRaw) || intval($daysRaw) <= 0) {
        return [
            'ok' => false,
            'days' => '',
            'error' => 'برای پلن محدود زمانی، تعداد روز را به صورت عدد وارد کنید (مثلاً 30)'
        ];
    }

    return ['ok' => true, 'days' => (string)intval($daysRaw), 'error' => ''];
}

if (isset($_POST['add'])) {
    $name = trim((string)($_POST['name'] ?? ''));
    $price = intval($_POST['price'] ?? 0);
    $category = trim((string)($_POST['category'] ?? 'unlimited'));
    $daysRaw = trim((string)($_POST['days'] ?? ''));
    $normalized = plansNormalizeDays($category, $daysRaw);

    if ($name === '') {
        $plansError = 'نام پلن الزامی است';
    } elseif ($price < 100 || $price > 30000) {
        $plansError = 'قیمت باید بین 100 تا 30000 باشد';
    } elseif (!$normalized['ok']) {
        $plansError = $normalized['error'];
    } else {
        $plans[] = [
            'name' => $name,
            'price' => $price,
            'days' => $normalized['days']
        ];

        file_put_contents(
            $plansFile,
            json_encode($plans, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );

        header('Location: ' . pnvAdminUrl('plans.php'));
        exit;
    }
}

if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);

    if (isset($plans[$id])) {
        unset($plans[$id]);
        $plans = array_values($plans);
        file_put_contents(
            $plansFile,
            json_encode($plans, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );
    }

    header('Location: ' . pnvAdminUrl('plans.php'));
    exit;
}

$plansUi = function_exists('pnvPlansForStepUi')
    ? pnvPlansForStepUi($plans)
    : [];

?>
<!DOCTYPE html>
<html lang="fa">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>مدیریت پلن ها</title>
<style>
*{box-sizing:border-box}
body{
margin:0;
padding:20px;
background:#0f172a;
font-family:tahoma,sans-serif;
direction:rtl;
color:#fff;
}
.container{max-width:1100px;margin:auto}
.box{
background:#1e293b;
padding:20px;
border-radius:20px;
margin-bottom:20px;
}
h2{margin:0 0 16px;font-size:22px;font-weight:700}
.note{
background:#0f172a;
padding:14px;
border-radius:12px;
margin-bottom:18px;
line-height:1.9;
font-size:13px;
color:#cbd5e1;
}
.formgrid{
display:grid;
grid-template-columns:1.2fr 1fr 1fr 1fr;
gap:12px;
align-items:end;
}
label.field{
display:flex;
flex-direction:column;
gap:6px;
font-size:12px;
color:#94a3b8;
}
input,select{
width:100%;
padding:14px;
border:1px solid #334155;
border-radius:12px;
background:#0f172a;
color:#fff;
font-family:inherit;
font-size:14px;
outline:none;
}
input:focus,select:focus{border-color:#22c55e}
button.primary{
width:100%;
padding:14px;
border:none;
border-radius:12px;
background:#22c55e;
color:#052e16;
cursor:pointer;
font-family:inherit;
font-size:15px;
font-weight:700;
}
button.primary:hover{opacity:.92}
.tablebox{overflow-x:auto}
table{
width:100%;
border-collapse:collapse;
min-width:720px;
}
th,td{
padding:14px 12px;
text-align:center;
font-size:14px;
}
th{background:#334155}
td{
background:#1e293b;
border-top:1px solid #334155;
}
.price{color:#22c55e;font-weight:700}
.badge{
display:inline-flex;
align-items:center;
padding:5px 10px;
border-radius:999px;
font-size:12px;
font-weight:700;
}
.badge-unlimited{
background:rgba(56,189,248,.15);
color:#38bdf8;
}
.badge-limited{
background:rgba(251,191,36,.15);
color:#fbbf24;
}
.delete{
background:#dc2626;
padding:9px 12px;
border-radius:10px;
color:#fff;
text-decoration:none;
display:inline-block;
font-size:13px;
}
.delete:hover{background:#b91c1c}
.back{
display:block;
margin-top:18px;
background:#334155;
padding:14px;
border-radius:14px;
text-align:center;
color:#fff;
text-decoration:none;
font-size:15px;
}
.flash-error{
background:#450a0a;
color:#fecaca;
padding:12px 14px;
border-radius:12px;
margin-bottom:14px;
font-size:13px;
}
.muted{color:#94a3b8;font-size:12px}
@media(max-width:900px){
.formgrid{grid-template-columns:1fr 1fr}
}
@media(max-width:640px){
body{padding:10px}
.box{padding:15px;border-radius:16px}
.formgrid{grid-template-columns:1fr}
table{min-width:620px}
input,select,button.primary{font-size:16px}
}
</style>
</head>
<body>
<?php adminQuickNavStyles(); adminQuickNav('plans'); ?>

<div class="container">

<div class="box">
<h2>افزودن پلن جدید</h2>

<div class="note">
این پلن‌ها در فرم <b>خرید</b> و <b>تمدید</b> با دو دسته نمایش داده می‌شوند:<br>
• <b>نامحدود زمانی</b> → فیلد مدت خالی یا «نامحدود»<br>
• <b>محدود زمانی</b> → عدد روز (مثل ۳۰)<br><br>
قیمت زیر ۱۰۰۰ → هزار تومان (۶۰۰ = ۶۰۰ هزار تومان)<br>
قیمت بالای ۱۰۰۰ → میلیون تومان (۳۵۰۰ = ۳.۵ میلیون تومان)
</div>

<?php if ($plansError !== '') { ?>
<div class="flash-error"><?php echo htmlspecialchars($plansError, ENT_QUOTES, 'UTF-8'); ?></div>
<?php } ?>

<form method="POST" id="plansAddForm">
<div class="formgrid">
<label class="field">نام پلن
<input type="text" name="name" placeholder="مثال: 10 گیگ" required
value="<?php echo htmlspecialchars($_POST['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
</label>

<label class="field">قیمت (۱۰۰ تا ۳۰۰۰۰)
<input type="number" name="price" min="100" max="30000" placeholder="150" required
value="<?php echo htmlspecialchars((string)($_POST['price'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
</label>

<label class="field">نوع زمانی (مثل خرید/تمدید)
<select name="category" id="planCategory">
<option value="unlimited" <?php echo (($_POST['category'] ?? 'unlimited') === 'unlimited') ? 'selected' : ''; ?>>نامحدود زمانی</option>
<option value="limited" <?php echo (($_POST['category'] ?? '') === 'limited') ? 'selected' : ''; ?>>محدود زمانی</option>
</select>
</label>

<label class="field" id="daysField">تعداد روز
<input type="number" name="days" id="planDays" min="1" max="3650" placeholder="مثلاً 30"
value="<?php echo htmlspecialchars((string)($_POST['days'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
<span class="muted" id="daysHint">برای نامحدود نیازی به روز نیست</span>
</label>
</div>

<br>
<button type="submit" name="add" value="1" class="primary">ثبت پلن</button>
</form>
</div>

<div class="box">
<h2>لیست پلن ها</h2>

<div class="tablebox">
<table>
<tr>
<th>ردیف</th>
<th>نام پلن</th>
<th>قیمت</th>
<th>دسته در خرید/تمدید</th>
<th>مدت</th>
<th>حذف</th>
</tr>

<?php if (count($plans) === 0) { ?>
<tr><td colspan="6" class="muted">هنوز پلنی ثبت نشده</td></tr>
<?php } ?>

<?php foreach ($plans as $i => $p) {
    $unlimited = pnvPlanIsUnlimited($p);
    $catLabel = $unlimited ? 'نامحدود زمانی' : 'محدود زمانی';
    $catClass = $unlimited ? 'badge-unlimited' : 'badge-limited';
    $daysLabel = pnvPlanDaysLabel($p);
?>
<tr>
<td><?php echo $i + 1; ?></td>
<td><?php echo htmlspecialchars((string)($p['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
<td class="price"><?php echo htmlspecialchars(pnvFormatPlanPrice($p['price'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></td>
<td><span class="badge <?php echo $catClass; ?>"><?php echo $catLabel; ?></span></td>
<td><?php echo htmlspecialchars($daysLabel, ENT_QUOTES, 'UTF-8'); ?></td>
<td>
<a href="?delete=<?php echo $i; ?>"
   class="delete"
   onclick="return confirm('پلن حذف شود؟')">حذف</a>
</td>
</tr>
<?php } ?>
</table>
</div>

<a href="<?php echo htmlspecialchars(pnvAdminUrl(), ENT_QUOTES, 'UTF-8'); ?>" class="back">بازگشت به مدیریت</a>
</div>

</div>

<script>
(function(){
    var cat = document.getElementById('planCategory');
    var days = document.getElementById('planDays');
    var hint = document.getElementById('daysHint');
    if(!cat || !days){ return; }

    function sync(){
        var limited = cat.value === 'limited';
        days.disabled = !limited;
        days.required = limited;
        if(!limited){
            days.value = '';
            if(hint){ hint.textContent = 'برای نامحدود نیازی به روز نیست'; }
        }else if(hint){
            hint.textContent = 'عدد روز برای دسته «محدود زمانی» در فرم خرید/تمدید';
        }
    }

    cat.addEventListener('change', sync);
    sync();
})();
</script>

</body>
</html>
