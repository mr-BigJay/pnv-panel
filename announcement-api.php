<?php

session_start();
header('Content-Type: application/json; charset=utf-8');

if(!isset($_SESSION['user'])){
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'دسترسی مجاز نیست'], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/campaign_lib.php';

$username = $_SESSION['user'];
$action = trim((string)($_GET['action'] ?? $_POST['action'] ?? 'list'));

if($action === 'list'){
    $loginSession = campaignAnnouncementLoginSessionId();
    $rows = campaignUserAnnouncements($username, $loginSession);
    $modal = null;
    $banners = [];

    foreach($rows as $row){
        $item = [
            'id' => $row['id'] ?? '',
            'title' => $row['title'] ?? '',
            'message' => $row['message'] ?? '',
            'type' => $row['type'] ?? 'info',
            'type_label' => campaignAnnouncementTypeLabel($row['type'] ?? 'info'),
            'is_read' => !empty($row['is_read']),
            'should_show' => !empty($row['should_show']),
            'view_count' => intval($row['view_count'] ?? 0),
            'max_views_per_user' => intval($row['max_views_per_user'] ?? 0),
        ];

        if($modal === null && !empty($row['should_show'])){
            $modal = $item;
        }
        elseif(!empty($row['should_show'])){
            $banners[] = $item;
        }
    }

    echo json_encode([
        'ok' => true,
        'modal' => $modal,
        'banners' => $banners,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if($action === 'dismiss'){
    $id = trim((string)($_POST['id'] ?? $_GET['id'] ?? ''));

    if($id === ''){
        echo json_encode(['ok' => false, 'error' => 'شناسه پیام نامعتبر است'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $loginSession = campaignAnnouncementLoginSessionId();
    $ok = campaignAnnouncementMarkRead($username, $id, $loginSession);

    echo json_encode(['ok' => $ok], JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code(400);
echo json_encode(['ok' => false, 'error' => 'action نامعتبر'], JSON_UNESCAPED_UNICODE);
