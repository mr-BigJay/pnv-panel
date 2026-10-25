<?php
/** Reserve at most one automatic notice per dashboard response, across all tabs. */
function pnvAnnouncementDisplayLimit(array $row){
    return max(0, min(1000, (int)($row['display_limit'] ?? 1)));
}
function pnvAnnouncementReserveDisplay($username, array $rows){
    if(trim((string)$username) === ''){ return null; }
    $path = getenv('PNV_ANNOUNCEMENT_VIEWS_PATH') ?: __DIR__ . '/db/dashboard_announcement_views.json';
    $lock = @fopen($path . '.lock', 'c');
    if(!$lock){ return null; }
    $temp = null;
    try {
        if(!flock($lock, LOCK_EX)){ return null; }
        $views = [];
        if(is_file($path)){
            $raw = @file_get_contents($path);
            $views = $raw === false ? null : json_decode($raw, true);
            // Never overwrite invalid counters or silently reset a user's quota.
            if(!is_array($views)){ return null; }
        }
        $userKey = hash('sha256', (string)$username);
        if(isset($views[$userKey]) && !is_array($views[$userKey])){ return null; }
        foreach($rows as $row){
            $id = (string)($row['id'] ?? '');
            if($id === '' || !campaignAnnouncementIsActive($row)){ continue; }
            $key = hash('sha256', $id);
            if(isset($views[$userKey][$key]) && (!is_int($views[$userKey][$key]) || $views[$userKey][$key] < 0)){ return null; }
            // Acknowledgements from the previous version count as one display.
            $count = max(0, (int)($views[$userKey][$key] ?? (!empty($row['is_read']) ? 1 : 0)));
            if($count >= pnvAnnouncementDisplayLimit($row)){ continue; }
            $views[$userKey][$key] = $count + 1;
            $json = json_encode($views, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            if($json === false){ return null; }
            $temp = @tempnam(dirname($path), '.notice-views-');
            if($temp === false){ $temp = null; return null; }
            if(@file_put_contents($temp, $json) !== strlen($json)){ return null; }
            @chmod($temp, is_file($path) ? (fileperms($path) & 0777) : 0640);
            if(!@rename($temp, $path)){ return null; }
            $temp = null;
            return $id;
        }
        return null;
    } finally {
        if($temp !== null){ @unlink($temp); }
        flock($lock, LOCK_UN); fclose($lock);
    }
}
