<?php

session_start();
header('Content-Type: application/json; charset=utf-8');

if(!isset($_SESSION['user'])){
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'ورود لازم است'], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/subscription_lib.php';
require_once __DIR__ . '/plan_ui_lib.php';
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
            $requestedLinks[] = $link;
        }
    }
}

$activeSubs = pnvLoadUserActiveSubscriptions($username, false);
$items = [];
$itemKeys = [];

foreach($activeSubs as $sub){
    if(!is_array($sub)){
        continue;
    }

    $link = trim((string)($sub['link'] ?? ''));

    if($link === '' || !pnvIsValidSubLink($link)){
        continue;
    }

    $link = function_exists('subUsageSanitizeLink') ? subUsageSanitizeLink($link) : $link;

    if(count($requestedLinks) > 0){
        $matched = false;

        foreach($requestedLinks as $requested){
            if(pnvSubLinksMatch($link, $requested)){
                $matched = true;
                break;
            }
        }

        if(!$matched){
            continue;
        }
    }

    $key = subUsageCacheKey($link);

    if(isset($itemKeys[$key])){
        continue;
    }

    $itemKeys[$key] = true;
    $items[] = [
        'link' => $link,
        'plan' => trim((string)($sub['plan_text'] ?? '')),
        'date' => trim((string)($sub['date'] ?? '')),
        'time' => trim((string)($sub['time'] ?? '')),
        'created_ts' => intval($sub['created_ts'] ?? 0),
    ];
}

// محدودیت ایمنی برای ۱ CPU
if(count($items) > 40){
    $items = array_slice($items, 0, 40);
}

$maxFresh = 4;

if(isset($input['max_fresh'])){
    $maxFresh = max(1, min(8, intval($input['max_fresh'])));
}

$forceRefresh = !empty($input['force']);
$result = subUsageGetForItems($items, $maxFresh, $forceRefresh);

$pending = 0;

foreach(($result['items'] ?? []) as $row){
    if(!empty($row['pending']) || (empty($row['ok']) && empty($row['cached']))){
        $pending++;
    }
}

$result['pending'] = $pending;
$result['count'] = count($items);

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
