<?php

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../profile_lib.php';

header('Content-Type: application/json; charset=utf-8');

if(!pnvAdminIsLoggedIn()){
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'دسترسی غیرمجاز'], JSON_UNESCAPED_UNICODE);
    exit;
}

$adminUser = pnvAdminUser();

if($adminUser === ''){
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'ادمین پیدا نشد'], JSON_UNESCAPED_UNICODE);
    exit;
}

if($_SERVER['REQUEST_METHOD'] === 'GET'){
    $avatar = profileGetAdminAvatar($adminUser);

    echo json_encode([
        'ok' => true,
        'username' => $adminUser,
        'avatar' => $avatar
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if($_SERVER['REQUEST_METHOD'] !== 'POST'){
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'متد نامعتبر'], JSON_UNESCAPED_UNICODE);
    exit;
}

if(isset($_POST['removeavatar'])){
    $result = profileRemoveAdminAvatar($adminUser);
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    exit;
}

if(isset($_POST['setavatar'])){
    $result = profileUploadAdminAvatar($adminUser, $_FILES['avatar'] ?? []);
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['ok' => false, 'error' => 'درخواست نامعتبر'], JSON_UNESCAPED_UNICODE);
