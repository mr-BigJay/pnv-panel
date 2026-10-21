<?php

session_start();
$_SESSION['user'] = 'Elahe';
header('Location: /coupon.php');
exit;
