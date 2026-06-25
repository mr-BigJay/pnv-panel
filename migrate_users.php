<?php

$dbPath =
__DIR__ . '/db/database.db';

$jsonPath =
__DIR__ . '/db/users.json';

$db = new SQLite3($dbPath);

$db->exec("
CREATE TABLE IF NOT EXISTS users (

id INTEGER PRIMARY KEY AUTOINCREMENT,

username TEXT UNIQUE,

password TEXT,

mobile TEXT UNIQUE,

referral_code TEXT UNIQUE,

referrer TEXT,

created_at TEXT

)
");

if(!file_exists($jsonPath)){

die("users.json پیدا نشد");

}

$users = json_decode(
file_get_contents($jsonPath),
true
);

if(!is_array($users)){

die("فرمت users.json خراب است");

}

$count = 0;

foreach($users as $u){

$username =
trim($u['username'] ?? '');

$password =
trim($u['password'] ?? '');

$mobile =
trim($u['mobile'] ?? '');

$referral_code =
trim($u['referral_code'] ?? '');

$referrer =
trim($u['referrer'] ?? '');

$created_at =
trim($u['created_at'] ?? '');

if($username == '' || $password == ''){
continue;
}

if($referral_code == ''){

$chars =
'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

do{

$code = '';

for($i=0;$i<6;$i++){

$code .=
$chars[rand(0,strlen($chars)-1)];

}

$check =
$db->querySingle(
"SELECT COUNT(*) FROM users WHERE referral_code='$code'"
);

}while($check > 0);

$referral_code = $code;
}

$stmt = $db->prepare("
INSERT OR IGNORE INTO users
(
username,
password,
mobile,
referral_code,
referrer,
created_at
)
VALUES
(
:username,
:password,
:mobile,
:referral_code,
:referrer,
:created_at
)
");

$stmt->bindValue(
':username',
$username,
SQLITE3_TEXT
);

$stmt->bindValue(
':password',
$password,
SQLITE3_TEXT
);

$stmt->bindValue(
':mobile',
$mobile,
SQLITE3_TEXT
);

$stmt->bindValue(
':referral_code',
$referral_code,
SQLITE3_TEXT
);

$stmt->bindValue(
':referrer',
$referrer,
SQLITE3_TEXT
);

$stmt->bindValue(
':created_at',
$created_at,
SQLITE3_TEXT
);

$result = $stmt->execute();

if($result){

$count++;

}

}

echo "انتقال موفق: " . $count . " کاربر";
?>
