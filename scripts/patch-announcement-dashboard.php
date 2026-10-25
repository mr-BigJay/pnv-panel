<?php
// CLI-only preparation; does not execute the dashboard or alter live data.
if(PHP_SAPI !== 'cli' || $argc !== 3){ exit(2); }
$source = file_get_contents($argv[1]);
$marker = '<div class="dashPrimaryGrid">';
$include = "<?php require __DIR__ . '/pnv_dashboard_announcements.php'; ?>";
if(strpos($source, $include) !== false){
    file_put_contents($argv[2], $source); exit(0);
}
if(substr_count($source, $marker) !== 1 || strpos($source, 'userPanel--dashboard') === false){
    fwrite(STDERR, "STOP: dashboard layout differs; no live files changed. Send dashboard.php.\n"); exit(1);
}
if(strpos($source, 'pnv_dashboard_announcements.php') !== false || strpos($source, 'announcement-api.php') !== false || strpos($source, 'campaignUserAnnouncements(') !== false){
    fwrite(STDERR, "STOP: dashboard already has an announcement integration; review required.\n"); exit(1);
}
$updated = str_replace($marker, $include . "\n\n" . $marker, $source);
if(file_put_contents($argv[2], $updated) === false){exit(1);}
