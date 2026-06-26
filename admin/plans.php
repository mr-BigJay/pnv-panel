<?php

require_once __DIR__ . '/auth.php';

pnvAdminRequireAuth();

$plansFile = "../db/plans.json";

if(!file_exists($plansFile)){
file_put_contents($plansFile,"[]");
}

$plans = json_decode(
file_get_contents($plansFile),
true
);

if(!is_array($plans)){
$plans = [];
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

if(isset($_POST['add'])){

$name =
trim($_POST['name']);

$price =
intval($_POST['price']);

$days =
trim($_POST['days']);

if($days==''){
$days = 'نامحدود';
}

$plans[] = [

'name'=>$name,

'price'=>$price,

'days'=>$days

];

file_put_contents(
$plansFile,
json_encode(
$plans,
JSON_UNESCAPED_UNICODE|
JSON_PRETTY_PRINT
)
);

header("Location: " . pnvAdminUrl('plans.php'));
exit;

}

if(isset($_GET['delete'])){

$id =
intval($_GET['delete']);

unset($plans[$id]);

$plans =
array_values($plans);

file_put_contents(
$plansFile,
json_encode(
$plans,
JSON_UNESCAPED_UNICODE|
JSON_PRETTY_PRINT
)
);

header("Location: " . pnvAdminUrl('plans.php'));
exit;

}

?>

<!DOCTYPE html>

<html lang="fa">

<head>

<meta charset="UTF-8">

<meta name="viewport"
content="width=device-width, initial-scale=1.0">

<title>

مدیریت پلن ها

</title>

<style>

*{
box-sizing:border-box;
}

body{
margin:0;
padding:20px;
background:#0f172a;
font-family:tahoma;
direction:rtl;
color:white;
}

.container{
max-width:1100px;
margin:auto;
}

.box{
background:#1e293b;
padding:20px;
border-radius:20px;
margin-bottom:20px;
}

h2{
margin-top:0;
margin-bottom:20px;
font-size:24px;
}

.formgrid{
display:grid;
grid-template-columns:1fr 1fr 1fr;
gap:12px;
}

input{
width:100%;
padding:14px;
border:none;
border-radius:12px;
background:#0f172a;
color:white;
font-family:tahoma;
font-size:14px;
outline:none;
}

button{
width:100%;
padding:14px;
border:none;
border-radius:12px;
background:#22c55e;
color:white;
cursor:pointer;
font-family:tahoma;
font-size:15px;
transition:0.3s;
}

button:hover{
opacity:0.9;
}

.tablebox{
overflow-x:auto;
}

table{
width:100%;
border-collapse:collapse;
min-width:700px;
}

th,
td{
padding:16px;
text-align:center;
font-size:14px;
}

th{
background:#334155;
}

td{
background:#1e293b;
border-top:1px solid #334155;
}

.delete{
background:#dc2626;
padding:10px 14px;
border-radius:10px;
color:white;
text-decoration:none;
display:inline-block;
font-size:13px;
}

.delete:hover{
background:#b91c1c;
}

.back{
display:block;
margin-top:20px;
background:#334155;
padding:14px;
border-radius:14px;
text-align:center;
color:white;
text-decoration:none;
font-size:15px;
}

.price{
color:#22c55e;
font-weight:bold;
}

.unlimited{
color:#38bdf8;
font-weight:bold;
}

.note{
background:#0f172a;
padding:14px;
border-radius:12px;
margin-bottom:18px;
line-height:30px;
font-size:14px;
color:#cbd5e1;
}

@media(max-width:768px){

body{
padding:10px;
}

.box{
padding:15px;
border-radius:16px;
}

h2{
font-size:20px;
}

.formgrid{
grid-template-columns:1fr;
}

table{
min-width:620px;
}

th,
td{
padding:12px;
font-size:13px;
}

button,
input{
font-size:16px;
}

}

</style>

</head>

<body>

<div class="container">

<div class="box">

<h2>

افزودن پلن جدید

</h2>

<div class="note">

اعداد زیر 1000 بصورت هزار تومان نمایش داده میشوند

<br>

مثال:
600 → 600 هزار تومان

<br><br>

اعداد بالای 1000 تقسیم بر 1000 شده و بصورت میلیون تومان نمایش داده میشوند

<br>

مثال:
3500 → 3.5 میلیون تومان

</div>

<form method="POST">

<div class="formgrid">

<input
type="text"
name="name"
placeholder="نام پلن"
required>

<input
type="number"
name="price"
min="100"
max="30000"
placeholder="قیمت بین 100 تا 30000"
required>

<input
type="text"
name="days"
placeholder="مدت - خالی = نامحدود">

</div>

<br>

<button
type="submit"
name="add">

ثبت پلن

</button>

</form>

</div>

<div class="box">

<h2>

لیست پلن ها

</h2>

<div class="tablebox">

<table>

<tr>

<th>

ردیف

</th>

<th>

نام پلن

</th>

<th>

قیمت

</th>

<th>

مدت

</th>

<th>

حذف

</th>

</tr>

<?php foreach($plans as $i=>$p){ ?>

<tr>

<td>

<?php echo $i+1; ?>

</td>

<td>

<?php echo htmlspecialchars($p['name']); ?>

</td>

<td class="price">

<?php echo formatPrice($p['price']); ?>

</td>

<td>

<?php if($p['days']=='نامحدود'){ ?>

<span class="unlimited">

نامحدود

</span>

<?php }else{ ?>

<?php echo htmlspecialchars($p['days']); ?>

روز

<?php } ?>

</td>

<td>

<a
href="?delete=<?php echo $i; ?>"
class="delete"
onclick="return confirm('پلن حذف شود؟')">

حذف

</a>

</td>

</tr>

<?php } ?>

</table>

</div>

<a href="<?php echo htmlspecialchars(pnvAdminUrl(), ENT_QUOTES, 'UTF-8'); ?>"
class="back">

بازگشت به مدیریت

</a>

</div>

</div>

</body>

</html>
