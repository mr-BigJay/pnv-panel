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
    $rows = campaignUserAnnouncements($username);
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
        ];

        if($modal === null && empty($row['is_read'])){
            $modal = $item;
        }
        else{
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

    campaignAnnouncementMarkRead($username, $id);
    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code(400);
echo json_encode(['ok' => false, 'error' => 'action نامعتبر'], JSON_UNESCAPED_UNICODE);
