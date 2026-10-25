<?php
if(session_status() !== PHP_SESSION_ACTIVE || empty($_SESSION['user'])){ return; }
require_once __DIR__ . '/campaign_lib.php';
$pnvDashboardNotices = array_values(array_filter(
    campaignUserAnnouncements((string)$_SESSION['user']),
    static function($row){ return empty($row['is_read']); }
));
if(!$pnvDashboardNotices){ return; }
?>
<style>
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
<?php foreach($pnvDashboardNotices as $pnvNoticeIndex => $pnvNoticeRow){
    $pnvNoticeType = in_array($pnvNoticeRow['type'] ?? '', ['info','success','warning','special'], true) ? $pnvNoticeRow['type'] : 'info';
?>
<dialog class="pnvNoticeDialog pnvNoticeDialog--<?php echo $pnvNoticeType; ?>" aria-labelledby="pnvNoticeTitle<?php echo $pnvNoticeIndex; ?>" data-notice-id="<?php echo htmlspecialchars((string)($pnvNoticeRow['id'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
<h2 id="pnvNoticeTitle<?php echo $pnvNoticeIndex; ?>"><?php echo htmlspecialchars((string)($pnvNoticeRow['title'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></h2>
<div class="pnvNoticeText"><?php echo htmlspecialchars((string)($pnvNoticeRow['message'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></div>
<footer><button type="button" autofocus>ادامه</button></footer>
</dialog>
<?php } ?>
<script>
(function(){
    'use strict';
    const dialogs = Array.from(document.querySelectorAll('.pnvNoticeDialog'));
    let index = 0;
    const previousFocus = document.activeElement;
    function showNext(){
        if(index < dialogs.length){
            document.body.appendChild(dialogs[index]);
            dialogs[index].showModal();
        } else if(previousFocus && previousFocus.isConnected){ previousFocus.focus(); }
    }
    dialogs.forEach(function(dialog){
        const button = dialog.querySelector('button');
        dialog.addEventListener('cancel', function(event){
            event.preventDefault();
            if(button.disabled) return;
            // Escape closes without recording acknowledgement.
            dialog.close(); index++; showNext();
        });
        button.addEventListener('click', async function(){
            if(button.disabled) return;
            button.disabled = true;
            const controller = new AbortController();
            const timer = setTimeout(function(){ controller.abort(); }, 5000);
            try {
                const response = await fetch('announcement-api.php', {
                    method:'POST', credentials:'same-origin', cache:'no-store',
                    body:new URLSearchParams({action:'dismiss', id:dialog.dataset.noticeId}),
                    signal:controller.signal
                });
                const result = await response.json();
                if(!response.ok || !result.ok) throw new Error('acknowledgement failed');
            } catch(error) {
                // Keep the dashboard accessible; an unsaved notice may reappear next visit.
            } finally {
                clearTimeout(timer);
                dialog.close(); index++; showNext();
            }
        });
    });
    showNext();
})();
</script>
<?php unset($pnvDashboardNotices, $pnvNoticeIndex, $pnvNoticeRow, $pnvNoticeType); ?>
