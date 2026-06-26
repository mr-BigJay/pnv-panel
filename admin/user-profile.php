<?php

session_start();

if(!isset($_SESSION['admin'])){
    exit;
}

$username = trim($_GET['user'] ?? '');

if($username === ''){
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

        $purchases[] = [
            'config' => $configName,
            'plan' => $d[2] ?? '',
            'tracking' => $d[3] ?? '',
            'date' => $d[4] ?? '',
            'time' => $d[5] ?? '',
            'status' => trim($d[6] ?? 'درحال بررسی'),
            'link' => trim($d[7] ?? ''),
            'timestamp' => intval($d[8] ?? 0)
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

if($page < 1){
    $page = 1;
}

$perPage = 5;
$totalCount = count($purchases);
$totalPages = max(1, (int)ceil($totalCount / $perPage));
$start = ($page - 1) * $perPage;
$purchasesPage = array_slice($purchases, $start, $perPage);

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
            <button type="button" onclick="copySub(this)">کپی لینک</button>
        </div>

        <?php } elseif($status === 'رد شد' && $sub['link'] !== ''){ ?>

        <div class="subRejectReason">
            <?php echo htmlspecialchars($sub['link'], ENT_QUOTES, 'UTF-8'); ?>
        </div>

        <?php } elseif($status === 'درحال بررسی'){ ?>

        <div class="subPendingNote">در انتظار تایید پرداخت</div>

        <?php } ?>

    </div>

    <?php } ?>

    <?php if($totalPages > 1){ ?>

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
