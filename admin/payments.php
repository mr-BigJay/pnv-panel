<?php

if(!isset($_SESSION['admin'])){
    exit;
}

$paymentsFile = '../invoices/payments.csv';
$usersFile = '../db/users.json';

$payments = [];
$users = [];

if(file_exists($usersFile)){
    $users = json_decode(file_get_contents($usersFile), true);
}

if(!is_array($users)){
    $users = [];
}

if(file_exists($paymentsFile)){
    $f = fopen($paymentsFile,'r');

    while(($d = fgetcsv($f)) !== FALSE){
        $payments[] = $d;
    }

    fclose($f);
}

if(!function_exists('getUserMobile')){

    function getUserMobile($username, $users){

        foreach($users as $u){

            if(
                strtolower(trim($u['username'] ?? ''))
                ==
                strtolower(trim($username))
            ){

                return $u['mobile'] ?? '-';

            }

        }

        return '-';

    }

}

// ==================== اعتبارسنجی لینک ====================

if(!function_exists('isValidSubscriptionLink')){

    function isValidSubscriptionLink($link){

        $link = trim($link);

        if($link === ''){
            return false;
        }

        if(!filter_var($link, FILTER_VALIDATE_URL)){
            return false;
        }

        $validDomains = [
            'vip.boozhaan.ir',
            'vip2.boozhaan.ir',
            'vip3.boozhaan.ir',
            'vip4.boozhaan.ir'
        ];

        foreach($validDomains as $d){

            if(stripos($link, $d) !== false){
                return true;
            }

        }

        return false;

    }

}

$paymentMessage = '';
$paymentError = '';

if(isset($_SESSION['payment_message'])){
    $paymentMessage = $_SESSION['payment_message'];
    unset($_SESSION['payment_message']);
}

if(isset($_SESSION['payment_error'])){
    $paymentError = $_SESSION['payment_error'];
    unset($_SESSION['payment_error']);
}

// ==================== عملیات POST ====================

if(isset($_POST['approve_payment'])){

    $index = intval($_POST['approve_index']);

    $link = trim($_POST['approve_link']);

    if(!isValidSubscriptionLink($link)){

        $_SESSION['payment_error'] = 'برای تایید پرداخت، وارد کردن لینک اشتراک معتبر الزامی است';

        header('Location: index.php?page=payments');

        exit;

    }

    if(isset($payments[$index])){

        $payments[$index][6] = 'تایید شد';

        $payments[$index][7] = $link;

        $fp = fopen($paymentsFile,'w');

        foreach($payments as $p){

            fputcsv($fp, $p);

        }

        fclose($fp);

        $_SESSION['payment_message'] = 'پرداخت با موفقیت تایید شد';

    }

    header('Location: index.php?page=payments');

    exit;

}

if(isset($_POST['edit_link'])){

    $index = intval($_POST['edit_index']);

    $link = trim($_POST['edit_link_value']);

    if(!isValidSubscriptionLink($link)){

        $_SESSION['payment_error'] = 'لینک اشتراک معتبر وارد کنید';

        header('Location: index.php?page=payments');

        exit;

    }

    if(
        isset($payments[$index])
        &&
        trim($payments[$index][6] ?? '') === 'تایید شد'
    ){

        $payments[$index][7] = $link;

        $fp = fopen($paymentsFile,'w');

        foreach($payments as $p){

            fputcsv($fp, $p);

        }

        fclose($fp);

        $_SESSION['payment_message'] = 'لینک اشتراک ذخیره شد';

    }

    header('Location: index.php?page=payments');

    exit;

}

if(isset($_POST['reject_payment'])){

    $index = intval($_POST['reject_index']);

    $reason = trim($_POST['reject_reason']);

    if(isset($payments[$index])){

        $payments[$index][6] = 'رد شد';

        $payments[$index][7] = $reason;

    }

    $fp = fopen($paymentsFile,'w');

    foreach($payments as $p){

        fputcsv($fp, $p);

    }

    fclose($fp);

    header('Location: index.php?page=payments');

    exit;

}

if(isset($_GET['deletepayment'])){

    $id = intval($_GET['deletepayment']);

    if(isset($payments[$id])){

        unset($payments[$id]);

        $payments = array_values($payments);

    }

    $fp = fopen($paymentsFile,'w');

    foreach($payments as $p){

        fputcsv($fp, $p);

    }

    fclose($fp);

    header('Location: index.php?page=payments');

    exit;

}

// ==================== آماده‌سازی لیست خرید ====================

$buyPayments = [];

foreach($payments as $index => $pay){

    $type = trim($pay[9] ?? '');

    if($type == 'خرید' || $type == ''){

        $buyPayments[] = [

            'index' => $index,

            'data' => $pay

        ];

    }

}

$buyPayments = array_reverse($buyPayments);

$currentPage = intval($_GET['p'] ?? 1);

if($currentPage < 1){
    $currentPage = 1;
}

$perPage = 50;

$totalItems = count($buyPayments);

$totalPages = ceil($totalItems / $perPage);

$start = ($currentPage - 1) * $perPage;

$buyPayments = array_slice($buyPayments, $start, $perPage);

?>

<style>

.payTable{
    width:100%;
    border-collapse:collapse;
    background:#1e293b;
    border-radius:16px;
    overflow:hidden;
}

.payTable th{
    background:#334155;
    padding:14px;
    font-size:14px;
    color:white;
}

.payTable td{
    padding:14px;
    border-bottom:1px solid #334155;
    font-size:13px;
    text-align:center;
    color:white;
    vertical-align: middle;
}

.status{
    padding:8px 12px;
    border-radius:10px;
    font-size:12px;
    display:inline-block;
    color:white;
}

.greenStatus{
    background:#22c55e;
}

.redStatus{
    background:#ef4444;
}

.yellowStatus{
    background:#facc15;
    color:black;
}

.menuWrap{
    position:relative;
    display:inline-block;
    width:40px;
}

.menuBtn{
    width:40px;
    height:40px;
    border:none;
    border-radius:10px;
    background:#334155;
    color:white;
    font-size:20px;
    cursor:pointer;
}

.dropdown{
    display:none;
    position:absolute;
    right:0;
    top:45px;
    background:#0f172a;
    width:200px;
    padding:10px;
    border-radius:14px;
    z-index:999;
    box-shadow:0 10px 30px rgba(0,0,0,0.4);
    direction:rtl;
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
    color:white;
    cursor:pointer;
    font-size:13px;
    text-align:right;
}

.dropdown .red{
    background:#ef4444;
}

.modalOverlay{
    position:fixed;
    inset:0;
    background:rgba(0,0,0,0.45);
    backdrop-filter:blur(6px);
    display:none;
    justify-content:center;
    align-items:center;
    z-index:99999;
    padding:16px;
}

.modal{
    background:#1e293b;
    width:100%;
    max-width:430px;
    border-radius:20px;
    padding:22px;
    color:white;
}

.modalTitle{
    font-size:20px;
    text-align:center;
    margin-bottom:18px;
    font-weight:bold;
}

.modalInfo{
    background:#0f172a;
    padding:14px;
    border-radius:12px;
    line-height:28px;
    font-size:13px;
    margin-bottom:16px;
}

.bigText{
    background:#0f172a;
    padding:18px;
    border-radius:14px;
    font-size:16px;
    line-height:34px;
    word-break:break-all;
    margin-bottom:16px;
}

.modal input,
.modal select{
    width:100%;
    padding:12px;
    border:none;
    border-radius:10px;
    margin-bottom:12px;
    background:#0f172a;
    color:white;
    font-size:14px;
    box-sizing:border-box;
}

.modalBtns{
    display:flex;
    gap:10px;
    margin-top:12px;
}

.modalBtns button{
    flex:1;
    padding:12px;
    border:none;
    border-radius:12px;
    cursor:pointer;
    color:white;
    font-size:14px;
}

.green{
    background:#22c55e;
}

.redBtn{
    background:#ef4444;
}

.gray{
    background:#475569;
}

.pagination{
    margin-top:25px;
    text-align:center;
}

.pagination a{
    display:inline-block;
    padding:10px 15px;
    margin:5px;
    background:#334155;
    color:white;
    border-radius:8px;
    text-decoration:none;
}

.pagination a.active{
    background:#22c55e;
}

.flashMessage{
    padding:14px 18px;
    border-radius:12px;
    margin-bottom:18px;
    font-size:14px;
}

.flashSuccess{
    background:#14532d;
    color:#bbf7d0;
    border:1px solid #22c55e;
}

.flashError{
    background:#450a0a;
    color:#fecaca;
    border:1px solid #ef4444;
}

@media(max-width:900px){

    .dropdown{
        right:auto;
        left:0;
    }

}

@media(max-width:768px){

    .dropdown{
        width:180px;
    }

}

</style>

<div class="box">

    <h2>

        لیست خرید های جدید

    </h2>

    <?php if($paymentMessage){ ?>

    <div class="flashMessage flashSuccess">

        <?php echo htmlspecialchars($paymentMessage); ?>

    </div>

    <?php } ?>

    <?php if($paymentError){ ?>

    <div class="flashMessage flashError">

        <?php echo htmlspecialchars($paymentError); ?>

    </div>

    <?php } ?>

    <table class="payTable">

        <thead>

            <tr>

                <th>شماره</th>

                <th>کاربر</th>

                <th>پلن اشتراک</th>

                <th>وضعیت</th>

                <th>عملیات</th>

            </tr>

        </thead>

        <tbody>

        <?php foreach($buyPayments as $row){

            $i = $row['index'];

            $p = $row['data'];

            $status = $p[6] ?? '';

            $statusClass = 'yellowStatus';

            if($status=='تایید شد'){
                $statusClass='greenStatus';
            }

            if($status=='رد شد'){
                $statusClass='redStatus';
            }

            $mobile =
            getUserMobile(
                $p[0] ?? '',
                $users
            );

        ?>

            <tr>

                <td>

                    <?php echo $i+1; ?>

                </td>

                <td>

                    <?php echo htmlspecialchars($p[0] ?? '-'); ?>

                </td>

                <td>

                    <?php echo htmlspecialchars($p[2] ?? '-'); ?>

                </td>

                <td>

                    <span class="status <?php echo $statusClass; ?>">

                        <?php echo $status ?: 'درحال بررسی'; ?>

                    </span>

                </td>

                <td>

                    <div class="menuWrap">

                        <button
                            class="menuBtn"
                            onclick="toggleMenu('m<?php echo $i; ?>')">

                            ⋮

                        </button>

                        <div
                            class="dropdown"
                            id="m<?php echo $i; ?>">

                            <button
                                onclick='showConfig(
                                <?php echo json_encode($p[0] ?? ""); ?>,
                                <?php echo json_encode($mobile); ?>,
                                <?php echo json_encode($p[4] ?? ""); ?>,
                                <?php echo json_encode($p[5] ?? ""); ?>,
                                <?php echo json_encode($p[1] ?? ""); ?>,
                                <?php echo json_encode($p[2] ?? ""); ?>
                                )'>

                                نام کانفیگ

                            </button>

                            <button
                                onclick='showPayment(
                                <?php echo json_encode($p[0] ?? ""); ?>,
                                <?php echo json_encode($mobile); ?>,
                                <?php echo json_encode($p[1] ?? ""); ?>,
                                <?php echo json_encode($p[3] ?? ""); ?>,
                                <?php echo json_encode($p[4] ?? ""); ?>,
                                <?php echo json_encode($p[5] ?? ""); ?>
                                )'>

                                جزئیات پرداخت

                            </button>

                            <button
                                onclick='showAction(
                                <?php echo $i; ?>,
                                <?php echo json_encode($p[0] ?? ""); ?>,
                                <?php echo json_encode($mobile); ?>,
                                <?php echo json_encode($p[1] ?? ""); ?>,
                                <?php echo json_encode($status); ?>,
                                <?php echo json_encode($p[7] ?? ""); ?>
                                )'>

                                عملیات

                            </button>

                            <button
                                class="red"
                                onclick='showDelete(
                                <?php echo $i; ?>,
                                <?php echo json_encode($p[0] ?? ""); ?>,
                                <?php echo json_encode($mobile); ?>,
                                <?php echo json_encode($p[1] ?? ""); ?>
                                )'>

                                حذف

                            </button>

                        </div>

                    </div>

                </td>

            </tr>

        <?php } ?>

        </tbody>

    </table>

    <div class="pagination">

    <?php for($x=1; $x<=$totalPages; $x++){ ?>

        <a
            href="index.php?page=payments&p=<?php echo $x; ?>"
            class="<?php echo $x==$currentPage ? 'active' : ''; ?>">

            <?php echo $x; ?>

        </a>

    <?php } ?>

    </div>

</div>

<div class="modalOverlay" id="modal">

    <div class="modal" id="modalContent"></div>

</div>

<script>

function toggleMenu(id){

    document.querySelectorAll('.dropdown').forEach(el => {

        if(el.id != id){

            el.classList.remove('active');

        }

    });

    document
    .getElementById(id)
    .classList.toggle('active');

}

function closeModal(){

    document
    .getElementById('modal')
    .style.display = 'none';

}

function openModal(html){

    document
    .getElementById('modalContent')
    .innerHTML = html;

    document
    .getElementById('modal')
    .style.display = 'flex';

}

function showConfig(
    user,
    mobile,
    date,
    time,
    config,
    plan
){

    let last4 = '';

    if(mobile){

        mobile = mobile.toString();

        last4 = mobile.slice(-4);

    }

let planNumber = '';

let match =
plan.match(/\d+/);

if(match){

planNumber = match[0];

}

let finalName =
config
+
'_'
+
last4
+
'_'
+
planNumber;

    openModal(`

        <div class="modalTitle">

            نام نهایی کانفیگ

        </div>

        <div class="modalInfo">

            نام کاربر: ${user}<br>
            شماره موبایل: ${mobile}<br>
            تاریخ: ${date}<br>
            ساعت: ${time}

        </div>

        <div class="bigText" id="cfgText">

            ${finalName}

        </div>

        <button
            class="green"
            style="width:100%;padding:12px;border:none;border-radius:12px;color:white;"
            onclick="copyText('cfgText')">

            کپی نام کانفیگ

        </button>

        <div class="modalBtns">

            <button
                class="gray"
                onclick="closeModal()">

                بستن

            </button>

        </div>

    `);

}

function showPayment(
    user,
    mobile,
    config,
    track,
    date,
    time
){

    openModal(`

        <div class="modalTitle">

            جزئیات پرداخت

        </div>

        <div class="modalInfo">

            نام کاربر: ${user}<br>
            شماره موبایل: ${mobile}<br>
            نام کانفیگ: ${config}

        </div>

        <div class="bigText">

            شماره پیگیری: ${track}<br>
            تاریخ: ${date}<br>
            ساعت: ${time}

        </div>

        <div class="modalBtns">

            <button
                class="gray"
                onclick="closeModal()">

                بستن

            </button>

        </div>

    `);

}

function showAction(
    id,
    user,
    mobile,
    config,
    status='',
    savedLink=''
){

    let content = '';

    if(status === 'تایید شد'){

        const linkDisplay = savedLink
            ? savedLink
            : 'لینکی ثبت نشده است';

        content = `

            <div class="bigText">

                ${linkDisplay}

            </div>

            <form method="POST" onsubmit="return validateLinkForm(this, 'edit_link_value')">

                <input
                    type="hidden"
                    name="edit_index"
                    value="${id}">

                <input
                    type="text"
                    name="edit_link_value"
                    id="editLink"
                    placeholder="لینک اشتراک"
                    value="${savedLink}"
                    required>

                <div class="modalBtns">

                    <button
                        type="button"
                        class="gray"
                        onclick="pasteToInput('editLink')">

                        Paste

                    </button>

                    <button
                        type="submit"
                        name="edit_link"
                        class="green">

                        ${savedLink ? 'ویرایش لینک' : 'افزودن لینک'}

                    </button>

                </div>

            </form>

            <div class="modalBtns">

                <button
                    class="gray"
                    onclick="closeModal()">

                    بستن

                </button>

            </div>

        `;

    }

    else if(status === 'رد شد'){

        content = `

            <div style="background:#450a0a;padding:14px;border-radius:12px;line-height:30px;margin-bottom:15px;">

                ${savedLink}

            </div>

            <div class="modalBtns">

                <button
                    class="gray"
                    onclick="closeModal()">

                    بستن

                </button>

            </div>

        `;

    }

    else{

        content = `

            <form method="POST" onsubmit="return validateLinkForm(this, 'approve_link')">

                <input
                    type="hidden"
                    name="approve_index"
                    value="${id}">

                <input
                    type="text"
                    name="approve_link"
                    id="approveLink"
                    placeholder="لینک اشتراک"
                    required>

                <div class="modalBtns">

                    <button
                        type="button"
                        class="gray"
                        onclick="pasteToInput('approveLink')">

                        Paste

                    </button>

                    <button
                        type="submit"
                        name="approve_payment"
                        class="green">

                        تایید

                    </button>

                </div>

            </form>

            <hr style="margin:20px 0;border-color:#334155;">

            <form method="POST">

                <input
                    type="hidden"
                    name="reject_index"
                    value="${id}">

                <select name="reject_reason">

                    <option value="اطلاعات پرداخت اشتباه است">

                        اطلاعات پرداخت اشتباه است

                    </option>

                    <option value="اطلاعات پرداخت تکراری است">

                        اطلاعات پرداخت تکراری است

                    </option>

                </select>

                <button
                    type="submit"
                    name="reject_payment"
                    class="redBtn"
                    style="width:100%;padding:12px;border:none;border-radius:12px;color:white;">

                    رد پرداخت

                </button>

            </form>

            <div class="modalBtns">

                <button
                    class="gray"
                    onclick="closeModal()">

                    بستن

                </button>

            </div>

        `;

    }

    openModal(`

        <div class="modalTitle">

            عملیات پرداخت

        </div>

        <div class="modalInfo">

            نام کاربر: ${user}<br>
            شماره موبایل: ${mobile}<br>
            نام کانفیگ: ${config}

        </div>

        ${content}

    `);

}

function showDelete(
    id,
    user,
    mobile,
    config
){

    openModal(`

        <div class="modalTitle">

            حذف پرداخت

        </div>

        <div class="modalInfo">

            نام کاربر: ${user}<br>
            شماره موبایل: ${mobile}<br>
            نام کانفیگ: ${config}

        </div>

        <div class="modalBtns">

            <button
                class="redBtn"
                onclick="confirmDelete(${id})">

                حذف

            </button>

            <button
                class="gray"
                onclick="closeModal()">

                بستن

            </button>

        </div>

    `);

}

function confirmDelete(id){

    if(confirm('مطمئن هستید؟')){

        location.href =
        'index.php?page=payments&deletepayment='
        +
        id;

    }

}

function copyText(id){

    let text =
    document.getElementById(id).innerText;

    navigator.clipboard.writeText(text);

    alert('کپی شد');

}

function validateLinkForm(form, fieldName){

    const input = form.querySelector(`[name="${fieldName}"]`);

    if(!input){
        return false;
    }

    const link = input.value.trim();

    if(link === ''){
        alert('برای تایید پرداخت، وارد کردن لینک اشتراک الزامی است');
        input.focus();
        return false;
    }

    try{
        new URL(link);
    }
    catch(e){
        alert('لینک اشتراک معتبر نیست');
        input.focus();
        return false;
    }

    const validDomains = [
        'vip.boozhaan.ir',
        'vip2.boozhaan.ir',
        'vip3.boozhaan.ir',
        'vip4.boozhaan.ir'
    ];

    const hasValidDomain = validDomains.some(
        d => link.toLowerCase().includes(d)
    );

    if(!hasValidDomain){
        alert('لینک اشتراک باید از دامنه‌های مجاز باشد');
        input.focus();
        return false;
    }

    return true;

}

async function pasteToInput(inputId){

    try{

        const text =
        await navigator.clipboard.readText();

        document
        .getElementById(inputId)
        .value = text;

    }

    catch(e){

        alert('دسترسی clipboard داده نشده');

    }

}

document.addEventListener('click', function(e){

    if(!e.target.closest('.menuWrap')){

        document.querySelectorAll('.dropdown').forEach(el => {

            el.classList.remove('active');

        });

    }

});

</script>