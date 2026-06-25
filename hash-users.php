<?php

$file = __DIR__ . '/db/users.json';

$users = json_decode(
file_get_contents($file),
true
);

if(!is_array($users)){
die("users.json invalid");
}

$updated = 0;

foreach($users as $k=>$u){

if(!isset($u['password'])){
continue;
}

$pass = $u['password'];

if(
strpos($pass,'$2y$') !== 0
){

$users[$k]['password'] =
password_hash(
$pass,
PASSWORD_DEFAULT
);

$updated++;

}

}

file_put_contents(
$file,
json_encode(
$users,
JSON_UNESCAPED_UNICODE |
JSON_PRETTY_PRINT
)
);

echo "done : ".$updated;

