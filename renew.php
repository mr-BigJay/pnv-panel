<?php

session_start();

if(!isset($_SESSION['user'])){
    header("Location: index.php");
    exit;
}

$message = "";
$error = "";

if($_SERVER['REQUEST_METHOD'] == "POST"){

    $sub = trim($_POST['sub']);
    $plan = trim($_POST['plan']);
    $tracking = trim($_POST['tracking']);
    $time = trim($_POST['time']);
    $date = trim($_POST['date']);

    $validDomains = [

        'vip.boozhaan.ir',
        'vip2.boozhaan.ir',
        'vip3.boozhaan.ir',
        'vip4.boozhaan.ir'

    ];

    $valid = false;

    foreach($validDomains as $d){

        if(
            stripos($sub,$d) !== false
        ){

            $valid = true;
            break;

        }

    }

    if(!$valid){

        $error = "لینک اشتراک صحیح نیست";

    }

    elseif(!preg_match('/^(0[0-9]|1[0-9]|2[0-3]):([0-5][0-9])$/',$time)){

        $error = "ساعت وارد شده صحیح نیست";

    }

    elseif(!preg_match('/^140[5-7]\/(0[1-9]|1[0-2])\/(0[1-9]|[12][0-9]|3[01])$/',$date)){

        $error = "تاریخ وارد شده صحیح نیست";

    }

    else{

        $status = "درحال بررسی";

        $link = "";

        $created = time();

        $row = [
            $_SESSION['user'],
            $sub,
            $plan,
            $tracking,
            $date,
            $time,
            $status,
            $link,
            $created,
            "تمدید"
        ];

        $file = fopen("invoices/payments.csv","a");

        fputcsv($file,$row);

        fclose($file);

        $message = "درخواست تمدید ثبت شد و درحال بررسی است";
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

تمدید اشتراک

</title>

<style>

*{
box-sizing:border-box;
}

body{
margin:0;
padding:14px;
background:#0f172a;
font-family:tahoma;
direction:rtl;
color:white;
}

.box{
width:100%;
max-width:520px;
margin:auto;
background:#1e293b;
padding:22px;
border-radius:18px;
}

h2{
margin-top:0;
margin-bottom:24px;
text-align:center;
font-size:24px;
}

input,
select{
width:100%;
padding:14px;
margin-top:10px;
margin-bottom:18px;
border:none;
border-radius:10px;
box-sizing:border-box;
font-size:14px;
}

button{
width:100%;
padding:14px;
background:#22c55e;
border:none;
border-radius:10px;
color:white;
font-size:15px;
cursor:pointer;
}

button:hover{
opacity:0.9;
}

.msg{
background:#16a34a;
padding:12px;
border-radius:10px;
margin-bottom:18px;
text-align:center;
line-height:28px;
}

.err{
background:#dc2626;
padding:12px;
border-radius:10px;
margin-bottom:18px;
text-align:center;
line-height:28px;
}

.back{
display:block;
margin-top:18px;
background:#334155;
padding:13px;
border-radius:10px;
color:white;
text-decoration:none;
text-align:center;
}

@media(max-width:768px){

body{
padding:10px;
}

.box{
padding:18px;
border-radius:14px;
}

h2{
font-size:22px;
}

input,
select,
button{
font-size:16px;
}

}

</style>

</head>

<body>

<div class="box">

<h2>

تمدید اشتراک

</h2>

<?php if($message!=""){ ?>

<div class="msg">

<?php echo $message; ?>

</div>

<?php } ?>

<?php if($error!=""){ ?>

<div class="err">

<?php echo $error; ?>

</div>

<?php } ?>

<form method="POST">

<input
type="text"
name="sub"
placeholder="لینک اشتراک"
required>

<select name="plan" required>

<option value="">
انتخاب پلن
</option>

<?php

$plans = [];

if(file_exists("db/plans.json")){

$plans = json_decode(
file_get_contents("db/plans.json"),
true
);

}

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

<input
type="text"
name="tracking"
placeholder="شماره پیگیری"
required>

<input
type="text"
id="time"
name="time"
maxlength="5"
placeholder="13:45"
required>

<input
type="text"
id="date"
name="date"
maxlength="10"
placeholder="1405/01/01"
required>

<button type="submit">

ثبت درخواست تمدید

</button>

</form>

<a
href="dashboard.php"
class="back">

بازگشت

</a>

</div>

<script>

document
.getElementById("time")
.addEventListener("input",function(e){

let v =
e.target.value.replace(/\D/g,'');

if(v.length >= 3){

v =
v.substring(0,2)
+
":"
+
v.substring(2,4);

}

e.target.value = v;

});

document
.getElementById("date")
.addEventListener("input",function(e){

let v =
e.target.value.replace(/\D/g,'');

if(v.length >= 5){

v =
v.substring(0,4)
+
"/"
+
v.substring(4);

}

if(v.length >= 8){

v =
v.substring(0,7)
+
"/"
+
v.substring(7,9);

}

e.target.value = v;

});

</script>

</body>

</html>
