<!DOCTYPE html>

<html lang="fa">

<head>

<meta charset="UTF-8">

<meta name="viewport"
content="width=device-width, initial-scale=1.0">

<title>

فروش موقتاً متوقف شده

</title>

<style>

*{
box-sizing:border-box;
}

body{
margin:0;
padding:20px;
font-family:tahoma;
direction:rtl;
overflow:hidden;
background:
radial-gradient(circle at top right,#1e3a8a 0%,#0f172a 45%),
linear-gradient(135deg,#020617,#0f172a);
color:white;
min-height:100vh;
display:flex;
justify-content:center;
align-items:center;
position:relative;
}

.bg1,
.bg2,
.bg3{
position:absolute;
border-radius:50%;
filter:blur(90px);
opacity:.25;
animation:float 8s infinite ease-in-out;
}

.bg1{
width:320px;
height:320px;
background:#2563eb;
top:-80px;
right:-60px;
}

.bg2{
width:260px;
height:260px;
background:#06b6d4;
bottom:-80px;
left:-50px;
animation-delay:2s;
}

.bg3{
width:180px;
height:180px;
background:#7c3aed;
top:40%;
left:45%;
animation-delay:4s;
}

@keyframes float{

0%{
transform:translateY(0px);
}

50%{
transform:translateY(25px);
}

100%{
transform:translateY(0px);
}

}

.card{
position:relative;
z-index:5;
width:100%;
max-width:700px;
padding:55px 38px;
border-radius:34px;
background:
rgba(15,23,42,.72);

backdrop-filter:blur(18px);

border:
1px solid rgba(255,255,255,.08);

box-shadow:
0 20px 60px rgba(0,0,0,.45);

text-align:center;
overflow:hidden;
}

.card:before{
content:"";
position:absolute;
top:0;
right:0;
width:100%;
height:5px;
background:
linear-gradient(
90deg,
#3b82f6,
#06b6d4,
#8b5cf6
);
}

.iconWrap{
width:120px;
height:120px;
margin:auto;
margin-bottom:28px;
border-radius:50%;
display:flex;
justify-content:center;
align-items:center;

background:
linear-gradient(
135deg,
rgba(59,130,246,.25),
rgba(139,92,246,.2)
);

border:
1px solid rgba(255,255,255,.08);

box-shadow:
0 10px 30px rgba(59,130,246,.2);
}

.icon{
font-size:62px;
}

h1{
margin:0;
font-size:40px;
line-height:65px;
font-weight:bold;
margin-bottom:26px;
}

.desc{
font-size:21px;
line-height:44px;
color:#cbd5e1;
margin-bottom:34px;
}

.status{
display:inline-block;
padding:14px 28px;
border-radius:999px;
font-size:17px;
font-weight:bold;

background:
linear-gradient(
135deg,
#dc2626,
#ef4444
);

box-shadow:
0 10px 25px rgba(239,68,68,.3);
}

.footer{
margin-top:34px;
font-size:15px;
color:#94a3b8;
line-height:30px;
}

@media(max-width:768px){

body{
padding:14px;
}

.card{
padding:40px 24px;
border-radius:28px;
}

.iconWrap{
width:95px;
height:95px;
margin-bottom:22px;
}

.icon{
font-size:48px;
}

h1{
font-size:29px;
line-height:48px;
margin-bottom:20px;
}

.desc{
font-size:17px;
line-height:36px;
margin-bottom:28px;
}

.status{
font-size:14px;
padding:12px 20px;
}

.footer{
font-size:13px;
line-height:26px;
}

}

</style>

</head>

<body>

<div class="bg1"></div>
<div class="bg2"></div>
<div class="bg3"></div>

<div class="card">

<div class="iconWrap">

<div class="icon">

🚫

</div>

</div>

<h1>

فروش موقتاً متوقف شده

</h1>

<div class="desc">

به دلیل تکمیل ظرفیت سرورها،
ثبت سفارش جدید در حال حاضر غیرفعال شده است.

<br><br>

پس از تامین ظرفیت جدید،
فروش مجدداً فعال خواهد شد.

</div>

<div class="status">

ظرفیت تکمیل شده

</div>

<div class="footer">

از صبوری و همراهی شما سپاسگزاریم 🌹

</div>

</div>

</body>

</html>