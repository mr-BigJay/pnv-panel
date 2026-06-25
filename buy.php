<?php

session_start();

if(!isset($_SESSION['user'])){
    header("Location: index.php");
    exit;
}

$plans = [];

if(file_exists("db/plans.json")){

    $plans = json_decode(
        file_get_contents("db/plans.json"),
        true
    );
}

$cards = [];

if(file_exists("db/cards.json")){

    $cards = json_decode(
        file_get_contents("db/cards.json"),
        true
    );
}

$message = "";
$error = "";

if($_SERVER['REQUEST_METHOD'] == "POST"){

    $subname = trim($_POST['subname']);
    $plan = trim($_POST['plan']);
    $tracking = trim($_POST['tracking']);
    $time = trim($_POST['time']);
    $date = trim($_POST['date']);

    if(strlen($subname) < 5 || strlen($subname) > 20){

        $error = "نام کانفیگ باید بین 5 تا 20 کارکتر باشد";
    }

    elseif(!preg_match('/^[a-zA-Z0-9._-]+$/',$subname)){

        $error = "نام کانفیگ فقط میتواند شامل حروف لاتین، عدد و . _ - باشد";
    }

    else{

        if(file_exists("invoices/payments.csv")){

            $handle = fopen("invoices/payments.csv","r");

            while(($data = fgetcsv($handle)) !== FALSE){

                if(
                    isset($data[0]) &&
                    isset($data[1]) &&
                    $data[0] == $_SESSION['user'] &&
                    strtolower($data[1]) == strtolower($subname)
                ){

                    $error = "شما قبلا کانفیگی با این نام ثبت کرده اید";
                    break;
                }
            }

            fclose($handle);
        }
    }

    if($error == ""){

        if(!preg_match('/^(0[0-9]|1[0-9]|2[0-3]):([0-5][0-9])$/',$time)){

            $error = "ساعت وارد شده صحیح نیست";
        }

        elseif(!preg_match('/^140[5-7]\/(0[1-9]|1[0-2])\/(0[1-9]|[12][0-9]|3[01])$/',$date)){

            $error = "تاریخ وارد شده صحیح نیست";
        }
    }

    if($error == ""){

        $status = "درحال بررسی";

        $link = "";

        $created = time();

        $row = [
            $_SESSION['user'],
            $subname,
            $plan,
            $tracking,
            $date,
            $time,
            $status,
            $link,
            $created,
            "خرید"
        ];

        $file = fopen("invoices/payments.csv","a");

        fputcsv($file,$row);

        fclose($file);

        $message = "رسید شما دریافت شد و حداکثر تا یک ساعت آینده بررسی خواهد شد";
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

خرید اشتراک جدید

</title>

<style>

*{
box-sizing:border-box;
}

body{
background:#0f172a;
font-family:tahoma;
direction:rtl;
color:white;
padding:16px;
margin:0;
display:flex;
justify-content:center;
}

.box{
width:100%;
max-width:760px;
margin:auto;
background:#1e293b;
padding:46px 30px;
border-radius:28px;
}

h2{
font-size:32px;
margin-bottom:28px;
text-align:center;
}

input,select{
width:100%;
padding:16px;
margin-top:10px;
margin-bottom:20px;
border:none;
border-radius:14px;
box-sizing:border-box;
font-size:18px;
background:#0f172a;
color:white;
}


button{
width:100%;
padding:16px;
background:#22c55e;
border:none;
border-radius:14px;
color:white;
font-size:22px;
cursor:pointer;
}

.back{
display:block;
margin-top:20px;
text-align:center;
background:#334155;
padding:16px;
border-radius:14px;
color:white;
text-decoration:none;
font-size:20px;
}

.msg{
background:#16a34a;
padding:16px;
border-radius:14px;
margin-bottom:20px;
font-size:18px;
line-height:34px;
}

.err{
background:#dc2626;
padding:16px;
border-radius:14px;
margin-bottom:20px;
font-size:18px;
line-height:34px;
}

.cardbox{
display:none;
background:#0f172a;
padding:18px;
border-radius:16px;
margin-bottom:22px;
word-break:break-all;
font-size:18px;
line-height:36px;
}

.copybtn{
margin-top:14px;
background:#3b82f6;
font-size:18px;
}

.infoText{
margin-bottom:16px;
font-size:18px;
color:#cbd5e1;
line-height:34px;
}

.helper{
font-size:16px;
color:#94a3b8;
margin-bottom:20px;
line-height:30px;
}

@media(max-width:768px){

body{
padding:10px;
}

.box{
max-width:100%;
padding:30px 20px;
border-radius:24px;
}

h2{
font-size:28px;
}

input,
select{
font-size:16px;
padding:14px;
}

button{
font-size:20px;
padding:14px;
}

.back{
font-size:18px;
padding:14px;
}

.cardbox{
font-size:16px;
line-height:30px;
}

.msg,
.err{
font-size:16px;
line-height:30px;
}

.infoText{
font-size:16px;
line-height:30px;
}

.helper{
font-size:14px;
line-height:26px;
}

}

</style>

</head>

<body>

<div class="box">

<h2>

خرید اشتراک جدید

</h2>

<?php if($message!=""){ ?>
<div class="msg"><?php echo $message; ?></div>
<?php } ?>

<?php if($error!=""){ ?>
<div class="err"><?php echo $error; ?></div>
<?php } ?>

<form method="POST">

<input type="text"
name="subname"
placeholder="نام دلخواه برای کانفیگ"
required>

<select name="plan" required>

<option value="">
انتخاب پلن
</option>

<?php

function formatPrice($price){

$price = intval($price);

if($price < 1000){

return
number_format($price)
.
" هزار تومان";

}

$million =
$price / 1000;

$million =
rtrim(
rtrim(
number_format($million,3),
'0'
),
'.'
);

return
$million
.
" میلیون تومان";

}

foreach($plans as $plan){

$priceText =
formatPrice($plan['price']);

$daysText =
($plan['days']=='نامحدود')
?
'نامحدود'
:
$plan['days'].' روز';

$value =
$plan['name']
.
" - "
.
$priceText;

?>

<option value="<?php echo $value; ?>">

<?php echo $value; ?>

</option>

<?php } ?>

</select>

<div class="infoText">

انتخاب شماره کارت جهت پرداخت

</div>

<select id="cardSelect"
onchange="showCard()">

<option value="">
انتخاب کارت
</option>

<?php foreach($cards as $card){ ?>

<option value="<?php echo $card['card']; ?>">

<?php echo $card['name']; ?>

</option>

<?php } ?>

</select>

<div id="cardBox"
class="cardbox">

<div id="cardNumber"></div>

<button type="button"
onclick="copyCard()"
class="copybtn">

کپی شماره کارت

</button>

</div>

<div class="infoText">

لطفا پس از پرداخت، اطلاعات پرداخت خود را ثبت کنيد

</div>

<input type="text"
name="tracking"
placeholder="شماره پیگیری"
required>

<input type="text"
id="time"
name="time"
placeholder="ساعت"
maxlength="5"
required>

<input type="text"
id="date"
name="date"
placeholder="1405/01/01"
maxlength="10"
required>

<div class="helper">

لطفا در ثبت اطلاعات پرداخت خود دقت فرمایید

</div>

<button type="submit">

ثبت خرید

</button>

</form>

<a href="dashboard.php"
class="back">

بازگشت

</a>

</div>

<script>

function showCard(){

let select =
document.getElementById("cardSelect");

let value = select.value;

if(value == ""){

document.getElementById("cardBox").style.display = "none";

return;

}

document.getElementById("cardBox").style.display = "block";

document.getElementById("cardNumber").innerText =
value;

}

function copyCard(){

let text =
document.getElementById("cardNumber").innerText;

navigator.clipboard.writeText(text);

alert("شماره کارت کپی شد");

}

document.getElementById("time").addEventListener("input", function(e){

let v = e.target.value.replace(/\D/g,'');

if(v.length >= 1){

let h1 = parseInt(v.charAt(0));

if(h1 > 2){
v = "2";
}

}

if(v.length >= 2){

let hh = parseInt(v.substring(0,2));

if(hh > 23){
v = "23";
}

}

if(v.length >= 3){

let m1 = parseInt(v.charAt(2));

if(m1 > 5){

v = v.substring(0,2) + "5";

}

}

if(v.length >= 4){

let mm = parseInt(v.substring(2,4));

if(mm > 59){

v = v.substring(0,2) + "59";

}

}

if(v.length >= 3){

v = v.substring(0,2) + ":" + v.substring(2,4);

}

e.target.value = v.substring(0,5);

});

function setTehranTime(){

const now = new Date();

const tehran = new Date(
now.toLocaleString(
"en-US",
{
timeZone: "Asia/Tehran"
}
)
);

let hh = tehran.getHours()
.toString()
.padStart(2,'0');

let mm = tehran.getMinutes()
.toString()
.padStart(2,'0');

document.getElementById("time").value =
hh + ":" + mm;

}

setTehranTime();

function setPersianDate(){

const now = new Date();

const formatter =
new Intl.DateTimeFormat(
'en-CA-u-ca-persian',
{
year:'numeric',
month:'2-digit',
day:'2-digit'
}
);

const parts = formatter.formatToParts(now);

let year = '';
let month = '';
let day = '';

parts.forEach(p => {

if(p.type === 'year'){
year = p.value;
}

if(p.type === 'month'){
month = p.value;
}

if(p.type === 'day'){
day = p.value;
}

});

document.getElementById("date").value =
year + "/" + month + "/" + day;

}

setPersianDate();

</script>

</body>

</html>
