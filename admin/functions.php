<?php

function statusColor($status){

if($status=='تایید شد'){
return '#22c55e';
}

if($status=='رد شد'){
return '#ef4444';
}

return '#eab308';

}

function getUserMobile($username,$users){

foreach($users as $u){

if(
isset($u['username']) &&
$u['username']==$username
){

return $u['mobile'] ?? '-';

}

}

return '-';

}

function findEmailBySub($sub){

preg_match('/\\/sub\\/([a-zA-Z0-9]+)/',$sub,$m);

$subid = $m[1] ?? '';

$files=[

'/var/www/html/db/vip.csv',
'/var/www/html/db/vip2.csv',
'/var/www/html/db/vip3.csv'

];

foreach($files as $f){

if(file_exists($f)){

$rows=array_map(
'str_getcsv',
file($f)
);

foreach($rows as $r){

if(
isset($r[1]) &&
trim($r[1])==$subid
){

return $r[0];

}

}

}

}

return '-';

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
