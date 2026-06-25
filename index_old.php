<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

<style>
body{
background:#0f172a;
font-family:tahoma;
color:white;
padding:20px;
}
.box{
max-width:400px;
margin:auto;
background:#1e293b;
padding:30px;
border-radius:15px;
margin-top:100px;
}
input{
width:100%;
padding:14px;
margin-bottom:15px;
border:none;
border-radius:8px;
box-sizing:border-box;
}
button,a{
width:100%;
padding:14px;
border:none;
border-radius:8px;
display:block;
text-align:center;
text-decoration:none;
font-size:16px;
box-sizing:border-box;
}
button{
background:#22c55e;
color:white;
cursor:pointer;
}
.register{
background:#334155;
color:white;
margin-top:10px;
}
.error{
background:#dc2626;
padding:12px;
border-radius:8px;
margin-bottom:15px;
text-align:center;
}
</style>
</head>
<body>

<div class="box">

<h2 style="text-align:center">ورود کاربران</h2>

<?php if($error != ""){ ?>
<div class="error"><?php echo $error; ?></div>
<?php } ?>

<form method="POST">

<input type="text" name="username" placeholder="نام کاربری" required>

<input type="password" name="password" placeholder="رمز عبور" required>

<button type="submit">ورود</button>

<a href="register.php" class="register">ایجاد نام کاربری</a>

</form>

</div>

</body>
</html>
