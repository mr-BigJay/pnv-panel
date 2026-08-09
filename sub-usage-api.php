<?php

session_start();
header('Content-Type: application/json; charset=utf-8');

if(!isset($_SESSION['user'])){
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'ورود لازم است'], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/subscription_lib.php';
require_once __DIR__ . '/sub_usage_lib.php';

@set_time_limit(28);
@ini_set('max_execution_time', '28');

$username = (string)$_SESSION['user'];

$input = $_POST;

if(empty($input)){
    $json = json_decode(file_get_contents('php://input') ?: '[]', true);
    if(is_array($json)){
        $input = $json;
    }
}

$requestedLinks = [];

if(isset($input['links']) && is_array($input['links'])){
    foreach($input['links'] as $link){
        $link = trim((string)$link);
        if($link !== ''){
            $requestedLinks[$link] = true;
        }
    }
}

// فقط لینک‌های تایید‌شدهٔ همین کاربر از CSV
$items = [];
$file = __DIR__ . '/invoices/payments.csv';

if(file_exists($file)){
    $handle = fopen($file, 'r');

    while(($row = fgetcsv($handle)) !== false){
        if(($row[0] ?? '') !== $username){
            continue;
        }

        if(trim((string)($row[6] ?? '')) !== 'تایید شد'){
            continue;
        }

        $link = trim((string)($row[7] ?? ''));

        if($link === '' || !xuiParseSubLink($link)){
            continue;
        }

        if(pnvIsSubLinkCleared($username, $link)){
            continue;
        }

        if(count($requestedLinks) > 0 && !isset($requestedLinks[$link])){
            continue;
        }

        $key = subUsageCacheKey($link);

        // جدیدترین ردیف برای هر لینک اولویت دارد
        $items[$key] = [
            'link' => $link,
            'plan' => trim((string)($row[2] ?? '')),
            'date' => trim((string)($row[4] ?? '')),
            'time' => trim((string)($row[5] ?? '')),
        ];
    }

    fclose($handle);
}

$items = array_values($items);

// محدودیت ایمنی برای ۱ CPU
if(count($items) > 40){
    $items = array_slice($items, 0, 40);
}

$maxFresh = 4;

if(isset($input['max_fresh'])){
    $maxFresh = max(1, min(8, intval($input['max_fresh'])));
}

$result = subUsageGetForItems($items, $maxFresh);

// اگر هنوز pending مانده، کلاینت دوباره می‌زند
$pending = 0;

foreach(($result['items'] ?? []) as $row){
    if(!empty($row['pending']) || (empty($row['ok']) && empty($row['cached']))){
        $pending++;
    }
}

$result['pending'] = $pending;
$result['count'] = count($items);

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
