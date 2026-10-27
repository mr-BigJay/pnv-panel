<?php
if(session_status() !== PHP_SESSION_ACTIVE || empty($_SESSION['user'])){ return; }
require_once __DIR__ . '/campaign_lib.php';
require_once __DIR__ . '/announcement_display_lib.php';
$pnvDashboardNotices = campaignUserAnnouncements((string)$_SESSION['user']);
if(!$pnvDashboardNotices){ return; }
$pnvAutoNoticeId = pnvAnnouncementReserveDisplay((string)$_SESSION['user'], $pnvDashboardNotices);
?>
<style>
html{height:auto;min-height:100%;overflow-y:auto}
body.userPanel--dashboard{height:auto;min-height:100dvh;overflow-y:auto}
.pnvNoticeList{display:flex;flex-direction:column;gap:8px;margin:4px 0;direction:rtl}
.pnvNoticeOpen{display:flex;align-items:center;justify-content:space-between;gap:12px;width:100%;box-sizing:border-box;text-align:right;border:1px solid #475569;border-radius:12px;padding:12px;background:#172554;color:#e2e8f0;font:inherit;font-size:13px;line-height:1.7;cursor:pointer;overflow-wrap:anywhere}
.pnvNoticeOpen:focus-visible{outline:2px solid #93c5fd;outline-offset:2px}

.pnvNoticeDialog{box-sizing:border-box;width:calc(100% - 32px);max-width:460px;max-height:85vh;max-height:85dvh;margin:auto;padding:0;border:1px solid #475569;border-radius:20px;background:#0f172a;color:#e2e8f0;box-shadow:0 24px 80px #0008;font-family:inherit;direction:rtl;text-align:right;overflow:hidden}
.pnvNoticeDialog::backdrop{background:rgba(2,6,23,.78);backdrop-filter:blur(4px)}
.pnvNoticeDialog[open]{display:flex;flex-direction:column}
.pnvNoticeDialog h2{margin:0;padding:20px 20px 12px;font-size:18px;line-height:1.6;overflow-wrap:anywhere;flex-shrink:0}
.pnvNoticeDialog .pnvNoticeText{padding:0 20px 16px;font-size:14px;line-height:2;white-space:pre-wrap;overflow-wrap:anywhere;overflow-y:auto;min-height:0;overscroll-behavior:contain}
.pnvNoticeDialog footer{padding:14px 20px 18px;border-top:1px solid #334155;flex-shrink:0}
.pnvNoticeDialog button{display:block;width:100%;padding:12px 16px;border:0;border-radius:12px;background:#2563eb;color:white;font:inherit;font-size:15px;font-weight:700;cursor:pointer}
.pnvNoticeDialog button:focus-visible{outline:3px solid #93c5fd;outline-offset:3px}
.pnvNoticeDialog button:disabled{opacity:.65;cursor:wait}
.pnvNoticeDialog--success{border-color:#22c55e}
.pnvNoticeDialog--warning{border-color:#eab308}
.pnvNoticeDialog--special{border-color:#a78bfa}
</style>
<section class="pnvNoticeList" aria-label="اطلاعیه‌ها">
<?php foreach($pnvDashboardNotices as $pnvNoticeIndex => $pnvNoticeRow){ ?>
<button type="button" class="pnvNoticeOpen" data-notice-index="<?php echo $pnvNoticeIndex; ?>" aria-haspopup="dialog" aria-controls="pnvNoticeDialog<?php echo $pnvNoticeIndex; ?>"><span><?php echo htmlspecialchars((string)($pnvNoticeRow['title'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></span><span aria-hidden="true">‹</span></button>
<?php } ?>
</section>
<?php foreach($pnvDashboardNotices as $pnvNoticeIndex => $pnvNoticeRow){
    $pnvNoticeType = in_array($pnvNoticeRow['type'] ?? '', ['info','success','warning','special'], true) ? $pnvNoticeRow['type'] : 'info';
?>
<dialog id="pnvNoticeDialog<?php echo $pnvNoticeIndex; ?>" data-auto="<?php echo ($pnvAutoNoticeId !== null && (string)$pnvNoticeRow['id'] === $pnvAutoNoticeId) ? '1' : '0'; ?>" class="pnvNoticeDialog pnvNoticeDialog--<?php echo $pnvNoticeType; ?>" aria-labelledby="pnvNoticeTitle<?php echo $pnvNoticeIndex; ?>" data-notice-id="<?php echo htmlspecialchars((string)($pnvNoticeRow['id'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
<h2 id="pnvNoticeTitle<?php echo $pnvNoticeIndex; ?>"><?php echo htmlspecialchars((string)($pnvNoticeRow['title'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></h2>
<div class="pnvNoticeText"><?php echo htmlspecialchars((string)($pnvNoticeRow['message'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></div>
<footer><button type="button" autofocus>ادامه</button></footer>
</dialog>
<?php } ?>
<script>
(function(){
    'use strict';
    const dialogs = Array.from(document.querySelectorAll('.pnvNoticeDialog'));
    let previousFocus = null;
    function openNotice(dialog){
        if(!dialog || dialog.open) return;
        previousFocus = document.activeElement;
        document.body.appendChild(dialog);
        dialog.showModal();
    }
    dialogs.forEach(function(dialog){
        dialog.querySelector('button').addEventListener('click', function(){ dialog.close(); });
        dialog.addEventListener('close', function(){
            if(previousFocus && previousFocus.isConnected) previousFocus.focus();
        });
    });
    document.querySelectorAll('.pnvNoticeOpen').forEach(function(button){
        button.addEventListener('click', function(){ openNotice(dialogs[Number(button.dataset.noticeIndex)]); });
    });
    const automatic = dialogs.find(function(dialog){ return dialog.dataset.auto === '1'; });
    if(automatic) openNotice(automatic);
})();
</script>
<?php unset($pnvDashboardNotices, $pnvNoticeIndex, $pnvNoticeRow, $pnvNoticeType, $pnvAutoNoticeId); ?>
