<?php

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../subscription_lib.php';

if(!pnvAdminIsLoggedIn()){
    http_response_code(403);
    exit;
}

$username = trim($_GET['user'] ?? $_POST['user'] ?? '');

if($username === ''){
    http_response_code(400);
    exit('user required');
}

// حذف لینک اشتراک قدیمی (برای کسانی که اشتباه خرید زدند به‌جای تمدید)
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_link'])){
    header('Content-Type: application/json; charset=utf-8');

    $result = pnvClearUserSubscriptionLink(
        $username,
        trim((string)($_POST['tracking'] ?? '')),
        intval($_POST['timestamp'] ?? 0)
    );

    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    exit;
}

$usersFile = '../db/users.json';
$paymentsFile = '../invoices/payments.csv';

$users = [];

if(file_exists($usersFile)){
    $users = json_decode(file_get_contents($usersFile), true);
}

if(!is_array($users)){
    $users = [];
}

$userData = null;

foreach($users as $u){

    if(
        strtolower(trim($u['username'] ?? ''))
        ===
        strtolower($username)
    ){
        $userData = $u;
        break;
    }

}

$purchases = [];

if(file_exists($paymentsFile)){

    $f = fopen($paymentsFile, 'r');

    while(($d = fgetcsv($f)) !== false){

        if(
            !isset($d[0])
            ||
            strtolower(trim($d[0])) !== strtolower($username)
        ){
            continue;
        }

        $type = trim($d[9] ?? 'خرید');

        if($type === 'تمدید'){
            continue;
        }

        $configName = trim($d[1] ?? '');

        if(
            stripos($configName, 'https://vip.') !== false
            ||
            stripos($configName, 'https://vip2.') !== false
            ||
            stripos($configName, 'https://vip3.') !== false
            ||
            stripos($configName, 'https://vip4.') !== false
        ){
            continue;
        }

        $link = trim($d[7] ?? '');
        $status = trim($d[6] ?? 'درحال بررسی');
        $linkCleared = ($status === 'تایید شد' && $link === '');

        $when = pnvFormatPaymentRowDateTime($d);

        $purchases[] = [
            'config' => $configName,
            'plan' => $d[2] ?? '',
            'tracking' => $d[3] ?? '',
            'date' => $when['date'],
            'time' => $when['time'],
            'status' => $status,
            'link' => $link,
            'timestamp' => intval($d[8] ?? 0),
            'link_cleared' => $linkCleared
        ];

    }

    fclose($f);

}

usort($purchases, function($a, $b){

    $aTime = $a['timestamp'] ?: 0;
    $bTime = $b['timestamp'] ?: 0;

    if($aTime !== $bTime){
        return $bTime <=> $aTime;
    }

    return strcmp(
        ($b['date'] ?? '') . ' ' . ($b['time'] ?? ''),
        ($a['date'] ?? '') . ' ' . ($a['time'] ?? '')
    );

});

$page = intval($_GET['p'] ?? 1);
$showAll = isset($_GET['all']) && $_GET['all'] === '1';

if($page < 1){
    $page = 1;
}

$perPage = 5;
$totalCount = count($purchases);
$totalPages = max(1, (int)ceil($totalCount / $perPage));

if($showAll){
    $purchasesPage = $purchases;
    $totalPages = 1;
    $page = 1;
}
else{
    $start = ($page - 1) * $perPage;
    $purchasesPage = array_slice($purchases, $start, $perPage);
}

function profileStatusClass($status){

    if($status === 'تایید شد'){
        return 'subStatusApproved';
    }

    if($status === 'رد شد'){
        return 'subStatusRejected';
    }

    return 'subStatusPending';

}

?>

<style>
.subsHint{font-size:12px;line-height:24px;color:#94a3b8;margin:-4px 0 14px}
.subClearedNote{font-size:13px;line-height:26px;padding:10px;border-radius:10px;background:#1e293b;color:#fbbf24}
.subLink{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
.subLink input{flex:1;min-width:140px;padding:10px;border:none;border-radius:10px;background:#1e293b;color:#fff;font-size:12px}
.subLink button{border:none;border-radius:10px;background:#22c55e;color:#fff;padding:10px 14px;cursor:pointer;font-family:tahoma;white-space:nowrap}
.subLink .subClearBtn{background:#dc2626}
</style>

<div class="profileOverlay" onclick="closeProfileModal()"></div>

<div class="profileModal">

    <div class="profileHeader">
        👤 اشتراک‌های <?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?>
        <button type="button" class="profileCloseBtn" onclick="closeProfileModal()">✕</button>
    </div>

    <div class="profileInfo">

        <div class="infoItem">
            <span>نام کاربری:</span>
            <?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?>
        </div>

        <div class="infoItem">
            <span>شماره موبایل:</span>
            <?php echo htmlspecialchars($userData['mobile'] ?? '-', ENT_QUOTES, 'UTF-8'); ?>
        </div>

        <div class="infoItem">
            <span>معرف:</span>
            <?php echo htmlspecialchars($userData['referrer'] ?? '-', ENT_QUOTES, 'UTF-8'); ?>
        </div>

        <div class="infoItem">
            <span>تعداد خرید:</span>
            <?php echo $totalCount; ?>
        </div>

    </div>

    <div class="subsTitle">📦 لیست اشتراک‌های خریداری‌شده</div>
    <div class="subsHint">اگر کاربر اشتباه خرید زده به‌جای تمدید، از «حذف لینک» برای پاک کردن لینک قدیمی از پنل کاربر استفاده کنید. سابقه پرداخت باقی می‌ماند.</div>

    <?php if(count($purchasesPage) === 0){ ?>

    <div class="emptySubs">اشتراکی یافت نشد</div>

    <?php } ?>

    <?php foreach($purchasesPage as $sub){

        $status = $sub['status'] ?: 'درحال بررسی';
        $statusClass = profileStatusClass($status);

    ?>

    <div class="subCard">

        <div class="subTop">
            <div class="subPlan"><?php echo htmlspecialchars($sub['plan'], ENT_QUOTES, 'UTF-8'); ?></div>
            <span class="subStatus <?php echo $statusClass; ?>">
                <?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?>
            </span>
        </div>

        <div class="subMeta">
            <div><b>نام کانفیگ:</b> <?php echo htmlspecialchars($sub['config'], ENT_QUOTES, 'UTF-8'); ?></div>
            <div><b>پیگیری:</b> <?php echo htmlspecialchars($sub['tracking'], ENT_QUOTES, 'UTF-8'); ?></div>
            <div><b>تاریخ:</b> <?php echo htmlspecialchars($sub['date'], ENT_QUOTES, 'UTF-8'); ?> - <?php echo htmlspecialchars($sub['time'], ENT_QUOTES, 'UTF-8'); ?></div>
        </div>

        <?php if($status === 'تایید شد' && $sub['link'] !== ''){ ?>

        <div class="subLink">
            <input type="text" readonly value="<?php echo htmlspecialchars($sub['link'], ENT_QUOTES, 'UTF-8'); ?>">
            <button type="button" onclick="copySub(this)">کپی</button>
            <button
                type="button"
                class="subClearBtn"
                data-user="<?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?>"
                data-tracking="<?php echo htmlspecialchars($sub['tracking'], ENT_QUOTES, 'UTF-8'); ?>"
                data-timestamp="<?php echo intval($sub['timestamp']); ?>"
                onclick="clearSubLink(this)">
                حذف لینک
            </button>
        </div>

        <?php } elseif(!empty($sub['link_cleared'])){ ?>

        <div class="subClearedNote">لینک این اشتراک از پنل کاربر حذف شده است</div>

        <?php } elseif($status === 'رد شد' && $sub['link'] !== ''){ ?>

        <div class="subRejectReason">
            <?php echo htmlspecialchars($sub['link'], ENT_QUOTES, 'UTF-8'); ?>
        </div>

        <?php } elseif($status === 'درحال بررسی'){ ?>

        <div class="subPendingNote">در انتظار تایید پرداخت</div>

        <?php } ?>

    </div>

    <?php } ?>

    <?php if(!$showAll && $totalPages > 1){ ?>

    <div class="profilePagination">

        <?php for($i = 1; $i <= $totalPages; $i++){ ?>

        <button
            type="button"
            onclick="loadProfile(<?php echo json_encode($username, JSON_UNESCAPED_UNICODE); ?>, <?php echo $i; ?>)"
            class="<?php echo $page === $i ? 'activePage' : ''; ?>">

            <?php echo $i; ?>

        </button>

        <?php } ?>

    </div>

    <?php } ?>

</div>
