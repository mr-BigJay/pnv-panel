<?php

if(!function_exists('campaignAdminStyles')){

    function campaignAdminStyles(){
        static $done = false;

        if($done){
            return;
        }

        $done = true;

        echo '<style>
*{box-sizing:border-box}
body.campaignAdmin{margin:0;padding:16px 14px 28px;background:#171f2e;font-family:tahoma,system-ui,sans-serif;direction:rtl;color:#f8fafc}
.campaignShell{max-width:760px;margin:0 auto}
.campaignPageTitle{margin:0 0 14px;text-align:center;font-size:22px;font-weight:700;color:#f8fafc}
.campaignTabs{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px;margin-bottom:16px}
.campaignTab{display:flex;align-items:center;justify-content:center;min-height:42px;padding:8px 6px;border-radius:14px;background:#242d3d;color:#cbd5e1;text-decoration:none;font-size:12px;font-weight:600;text-align:center;line-height:1.5;border:1px solid #334155}
.campaignTab.is-active{background:#34d399;border-color:#34d399;color:#052e16;box-shadow:0 8px 24px rgba(52,211,153,.18)}
.campaignCard{background:#1f2937;border:1px solid #334155;border-radius:18px;padding:16px;margin-bottom:16px;overflow:visible}
.campaignCardHead{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:16px}
.campaignCardTitle{margin:0;font-size:18px;font-weight:700;color:#f8fafc}
.campaignCardIcon{width:38px;height:38px;border-radius:12px;background:rgba(52,211,153,.14);color:#34d399;display:inline-flex;align-items:center;justify-content:center;flex-shrink:0}
.campaignSection{margin-bottom:16px}
.campaignSectionTitle{margin:0 0 10px;font-size:13px;color:#94a3b8;font-weight:700}
.campaignField{margin-bottom:10px}
.campaignLabel{display:block;margin-bottom:6px;font-size:12px;color:#94a3b8}
.campaignInputWrap{position:relative}
.campaignInputWrap svg{position:absolute;right:12px;top:50%;transform:translateY(-50%);width:18px;height:18px;color:#64748b;pointer-events:none}
.campaignInputWrap .campaignSuffix{position:absolute;left:12px;top:50%;transform:translateY(-50%);font-size:13px;color:#64748b}
.campaignInput,.campaignSelect,.campaignTextarea{width:100%;padding:12px 14px;border:1px solid #334155;border-radius:14px;background:#141b26;color:#f8fafc;font-family:inherit;font-size:14px;outline:none}
.campaignInputWrap .campaignInput{padding-right:40px}
.campaignInputWrap.hasSuffix .campaignInput{padding-left:34px}
.campaignTextarea{min-height:92px;resize:vertical;line-height:1.8}
.campaignGrid2{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.campaignHint{margin-top:4px;font-size:11px;color:#64748b;line-height:1.6}
.campaignToggleRow{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px 14px;border:1px solid #334155;border-radius:14px;background:#141b26}
.campaignToggleText{font-size:13px;color:#e2e8f0;line-height:1.7}
.campaignToggle{position:relative;width:48px;height:28px;flex-shrink:0}
.campaignToggle input{opacity:0;width:0;height:0;position:absolute}
.campaignToggleTrack{position:absolute;inset:0;background:#475569;border-radius:999px;transition:.2s}
.campaignToggleTrack::after{content:"";position:absolute;width:22px;height:22px;top:3px;right:3px;background:#fff;border-radius:50%;transition:.2s}
.campaignToggle input:checked + .campaignToggleTrack{background:#34d399}
.campaignToggle input:checked + .campaignToggleTrack::after{transform:translateX(-20px)}
.campaignSubmit{width:100%;margin-top:4px;padding:14px;border:none;border-radius:14px;background:#34d399;color:#052e16;font-size:15px;font-weight:700;font-family:inherit;cursor:pointer}
.campaignFlash{margin-bottom:12px;padding:12px 14px;border-radius:14px;background:#713f12;color:#fde68a;font-size:13px;line-height:1.7}
.campaignFlash.is-success{background:#14532d;color:#bbf7d0}
.campaignSearchRow{display:flex;gap:8px;margin-bottom:14px}
.campaignSearchWrap{flex:1;position:relative}
.campaignSearchWrap svg{position:absolute;right:12px;top:50%;transform:translateY(-50%);width:18px;height:18px;color:#64748b}
.campaignSearchWrap input{width:100%;padding:12px 40px 12px 12px;border:1px solid #334155;border-radius:14px;background:#141b26;color:#f8fafc;font-family:inherit;font-size:14px}
.campaignFilterBtn{padding:0 14px;border:1px solid #334155;border-radius:14px;background:#242d3d;color:#e2e8f0;font-family:inherit;font-size:13px;cursor:pointer;white-space:nowrap}
.campaignList{display:flex;flex-direction:column;gap:10px;overflow:visible}
.campaignListItem{position:relative;padding:14px 52px 14px 14px;border:1px solid #334155;border-radius:16px;background:#141b26;overflow:visible}
.campaignListItem.is-menu-open{z-index:30}
.campaignMenuBtn{width:34px;height:34px;border:none;border-radius:10px;background:#242d3d;color:#cbd5e1;font-size:18px;line-height:1;cursor:pointer}
.campaignMenu{position:absolute;top:10px;right:10px;z-index:2}
.campaignMenuPanel{display:none;min-width:168px;background:#242d3d;border:1px solid #334155;border-radius:12px;padding:6px;box-shadow:0 12px 30px rgba(0,0,0,.35)}
.campaignMenuPanel.is-open{display:block;position:fixed;z-index:1100}
.campaignMenuPanel a,.campaignMenuPanel button{display:block;width:100%;padding:10px 12px;border:none;border-radius:8px;background:transparent;color:#e2e8f0;text-decoration:none;text-align:right;font-family:inherit;font-size:13px;cursor:pointer}
.campaignMenuPanel a:hover,.campaignMenuPanel button:hover{background:#334155}
.campaignMenuPanel .is-danger{color:#fca5a5}
.campaignItemTop{display:flex;align-items:flex-start;justify-content:space-between;gap:10px;margin-bottom:8px}
.campaignItemCode{font-size:16px;font-weight:700;color:#f8fafc}
.campaignBadge{display:inline-flex;padding:4px 10px;border-radius:999px;font-size:11px;font-weight:700}
.campaignBadge.is-active{background:rgba(52,211,153,.16);color:#6ee7b7}
.campaignBadge.is-inactive{background:#334155;color:#94a3b8}
.campaignItemType{font-size:13px;color:#cbd5e1;margin-bottom:8px}
.campaignProgressWrap{margin-bottom:8px}
.campaignProgressMeta{display:flex;justify-content:space-between;gap:8px;font-size:11px;color:#94a3b8;margin-bottom:4px}
.campaignProgress{height:8px;border-radius:999px;background:#242d3d;overflow:hidden}
.campaignProgressBar{height:100%;border-radius:999px;background:linear-gradient(90deg,#22c55e,#34d399)}
.campaignItemMeta{display:grid;grid-template-columns:1fr 1fr;gap:8px;font-size:11px;color:#94a3b8;line-height:1.7}
.campaignItemMeta strong{display:block;color:#cbd5e1;font-size:11px;margin-bottom:2px}
.campaignMoreBtn{width:100%;margin-top:4px;padding:12px;border:1px solid #334155;border-radius:14px;background:#242d3d;color:#cbd5e1;font-family:inherit;font-size:13px;cursor:pointer}
.campaignMoreBtn svg{display:inline-block;width:14px;height:14px;vertical-align:-2px;margin-right:4px}
.campaignHidden{display:none}
.campaignBack{display:block;margin-top:8px;padding:14px;border-radius:14px;background:#242d3d;color:#fff;text-decoration:none;text-align:center;font-size:14px}
.campaignPreviewBox{margin-top:10px;padding:14px;border:1px solid #334155;border-radius:14px;background:#141b26;line-height:1.9}
.campaignPreviewBox strong{display:block;margin-bottom:6px;font-size:15px;color:#f8fafc}
.campaignPreviewBox.is-info{border-color:#38bdf8}.campaignPreviewBox.is-success{border-color:#34d399}
.campaignPreviewBox.is-warning{border-color:#f59e0b}.campaignPreviewBox.is-special{border-color:#a855f7}
.campaignBadge.is-info{background:rgba(56,189,248,.16);color:#7dd3fc}
.campaignBadge.is-success{background:rgba(52,211,153,.16);color:#6ee7b7}
.campaignBadge.is-warning{background:rgba(245,158,11,.16);color:#fcd34d}
.campaignBadge.is-special{background:rgba(168,85,247,.16);color:#d8b4fe}
.campaignItemMessage{font-size:12px;color:#94a3b8;line-height:1.8;margin-bottom:8px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.campaignItemBadges{display:flex;flex-wrap:wrap;gap:6px;margin-top:6px}
@media(max-width:640px){body.campaignAdmin{padding:12px 10px 24px}.campaignGrid2{grid-template-columns:1fr}.campaignItemMeta{grid-template-columns:1fr}}
body.campaignAdmin.adminHasBottomNav{padding-bottom:calc(84px + env(safe-area-inset-bottom,0))}
@media(max-width:768px){body.campaignAdmin .adminBottomNav{display:block}}
.campaignDateInput{cursor:pointer}
.jdp-container{z-index:1000}
body.campaignAdmin .jdp-container{background:#1f2937;border:1px solid #334155;border-radius:16px;color:#f8fafc;box-shadow:0 16px 40px rgba(0,0,0,.35);font-family:tahoma,system-ui,sans-serif}
body.campaignAdmin .jdp-container .jdp-day-name,body.campaignAdmin .jdp-container .jdp-day{color:#e2e8f0}
body.campaignAdmin .jdp-container .jdp-day:not(.disabled-day):hover{background:#334155}
body.campaignAdmin .jdp-container .jdp-day.selected-day{background:#34d399;color:#052e16}
body.campaignAdmin .jdp-container .jdp-day.today{background:#242d3d;color:#34d399}
body.campaignAdmin .jdp-container .jdp-btn-today,body.campaignAdmin .jdp-container .jdp-btn-empty,body.campaignAdmin .jdp-container .jdp-btn-close{background:#242d3d;color:#e2e8f0;border:1px solid #334155;border-radius:10px}
body.campaignAdmin .jdp-container .jdp-time-container select{background:#141b26;color:#f8fafc;border:1px solid #334155;border-radius:8px}
body.campaignAdmin .jdp-container .jdp-month,body.campaignAdmin .jdp-container .jdp-year{color:#f8fafc}
body.campaignAdmin .jdp-container .jdp-icon-plus,body.campaignAdmin .jdp-container .jdp-icon-minus{filter:invert(1)}
.campaignStatsModalOverlay{display:none;position:fixed;inset:0;z-index:80;background:rgba(0,0,0,.55);align-items:center;justify-content:center;padding:16px}
.campaignStatsModalOverlay.is-open{display:flex}
.campaignStatsModal{width:100%;max-width:360px;background:#1f2937;border:1px solid #334155;border-radius:18px;padding:18px;box-shadow:0 20px 48px rgba(0,0,0,.35)}
.campaignStatsModalTitle{margin:0 0 14px;font-size:17px;font-weight:700;color:#f8fafc;text-align:center}
.campaignStatsGrid{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.campaignStatsBox{padding:14px;border:1px solid #334155;border-radius:14px;background:#141b26;text-align:center}
.campaignStatsNum{font-size:28px;font-weight:700;color:#34d399;margin-bottom:4px}
.campaignStatsLabel{font-size:12px;color:#94a3b8;line-height:1.6}
.campaignStatsClose{width:100%;margin-top:14px;padding:12px;border:none;border-radius:14px;background:#242d3d;color:#e2e8f0;font-family:inherit;font-size:14px;cursor:pointer}
</style>';
    }

    function campaignAdminNav($active){
        $tabs = [
            'overview' => ['label' => 'نمای کلی', 'href' => pnvAdminUrl('campaigns.php')],
            'discounts' => ['label' => 'کدهای تخفیف', 'href' => pnvAdminUrl('campaign-discounts.php')],
            'announcements' => ['label' => 'پیام‌های داشبورد', 'href' => pnvAdminUrl('campaign-announcements.php')],
        ];

        echo '<h1 class="campaignPageTitle">کمپین‌ها</h1>';
        echo '<nav class="campaignTabs">';

        foreach($tabs as $key => $tab){
            $cls = ($active === $key) ? ' campaignTab is-active' : ' campaignTab';
            echo '<a class="' . trim($cls) . '" href="' . htmlspecialchars($tab['href'], ENT_QUOTES, 'UTF-8') . '">'
                . htmlspecialchars($tab['label'], ENT_QUOTES, 'UTF-8') . '</a>';
        }

        echo '</nav>';
    }

    function campaignIconTicket(){
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 9a2 2 0 012-2h12a2 2 0 012 2v2a2 2 0 010 4v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2a2 2 0 010-4V9z"/><path d="M12 7v10"/></svg>';
    }

    function campaignIconUser(){
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20 21a8 8 0 10-16 0"/><circle cx="12" cy="8" r="4"/></svg>';
    }

    function campaignIconMoney(){
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="6" width="18" height="12" rx="2"/><circle cx="12" cy="12" r="2"/></svg>';
    }

    function campaignIconCalendar(){
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 10h18"/></svg>';
    }

    function campaignIconList(){
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M8 6h13M8 12h13M8 18h13"/><path d="M3 6h.01M3 12h.01M3 18h.01"/></svg>';
    }

    function campaignIconSearch(){
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>';
    }

    function campaignIconMessage(){
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 15a4 4 0 01-4 4H8l-5 3V7a4 4 0 014-4h10a4 4 0 014 4z"/></svg>';
    }

    function campaignJalaliDatePickerHead(){
        static $done = false;

        if($done){
            return;
        }

        $done = true;

        echo '<link rel="stylesheet" href="https://unpkg.com/@majidh1/jalalidatepicker/dist/jalalidatepicker.min.css">';
        echo '<style>
.campaignDateInput{cursor:pointer}
.jdp-container{z-index:1000}
.jdp-container{background:#1f2937;border:1px solid #334155;border-radius:16px;color:#f8fafc;box-shadow:0 16px 40px rgba(0,0,0,.35);font-family:tahoma,system-ui,sans-serif}
.jdp-container .jdp-day-name,.jdp-container .jdp-day{color:#e2e8f0}
.jdp-container .jdp-day:not(.disabled-day):hover{background:#334155}
.jdp-container .jdp-day.selected-day{background:#34d399;color:#052e16}
.jdp-container .jdp-day.today{background:#242d3d;color:#34d399}
.jdp-container .jdp-btn-today,.jdp-container .jdp-btn-empty,.jdp-container .jdp-btn-close{background:#242d3d;color:#e2e8f0;border:1px solid #334155;border-radius:10px}
.jdp-container .jdp-time-container select{background:#141b26;color:#f8fafc;border:1px solid #334155;border-radius:8px}
.jdp-container .jdp-month,.jdp-container .jdp-year{color:#f8fafc}
.jdp-container .jdp-icon-plus,.jdp-container .jdp-icon-minus{filter:invert(1)}
</style>';
    }

    function campaignJalaliDatePickerFoot(){
        static $done = false;

        if($done){
            return;
        }

        $done = true;

        echo '<script src="https://unpkg.com/@majidh1/jalalidatepicker/dist/jalalidatepicker.min.js"></script>';
        echo '<script>
jalaliDatepicker.startWatch({
    time: true,
    hasSecond: false,
    autoShow: true,
    autoHide: true,
    hideAfterChange: true,
    separatorChars: {date: "/", between: " ", time: ":"},
    persianDigits: true
});
</script>';
    }

    function campaignJalaliDateTimeInput($name, $timestamp = 0, $placeholder = '۱۴۰۴/۰۵/۲۱ ۱۴:۳۰'){
        $value = campaignInputJalaliDateTime($timestamp);

        echo '<input class="campaignInput campaignDateInput" data-jdp data-jdp-has-second="false" name="'
            . htmlspecialchars($name, ENT_QUOTES, 'UTF-8')
            . '" placeholder="'
            . htmlspecialchars($placeholder, ENT_QUOTES, 'UTF-8')
            . '" value="'
            . htmlspecialchars($value, ENT_QUOTES, 'UTF-8')
            . '" autocomplete="off" inputmode="numeric">';
    }

    function campaignAdminBottomNavHead(){
        if(function_exists('adminBottomNavStyles')){
            adminBottomNavStyles();
        }
    }

    function campaignAdminBottomNavFoot(){
        if(function_exists('adminPageEnd')){
            adminPageEnd(['active' => 'campaigns', 'more_mode' => 'sheet']);
            return;
        }

        if(function_exists('adminBottomNav')){
            adminBottomNav(['active' => 'campaigns', 'more_mode' => 'sheet']);
        }

        if(function_exists('adminBottomNavScript')){
            adminBottomNavScript();
        }
    }

    function campaignAdminPageHead($includeJalaliPicker = false){
        campaignAdminStyles();
        campaignAdminBottomNavHead();

        if($includeJalaliPicker){
            campaignJalaliDatePickerHead();
        }
    }

    function campaignAdminPageFoot($includeJalaliPicker = false){
        if($includeJalaliPicker){
            campaignJalaliDatePickerFoot();
        }

        if(!function_exists('pnvFormValidationFaScript')){
            require_once dirname(__DIR__) . '/form_validation_fa.php';
        }

        pnvFormValidationFaScript();
        campaignAdminMenuScript();
        campaignAdminBottomNavFoot();
    }

    function campaignAdminMenuScript(){
        static $done = false;

        if($done){
            return;
        }

        $done = true;

        echo <<<'JS'
<script>
(function(){
    function closeCampaignMenus(){
        document.querySelectorAll('.campaignMenuPanel.is-open').forEach(function(panel){
            panel.classList.remove('is-open');
            panel.style.top = '';
            panel.style.right = '';
            panel.style.left = '';
            panel.style.minWidth = '';
        });

        document.querySelectorAll('.campaignListItem.is-menu-open').forEach(function(item){
            item.classList.remove('is-menu-open');
        });
    }

    function positionCampaignMenuPanel(btn, panel){
        var rect = btn.getBoundingClientRect();
        var panelWidth = Math.max(168, panel.offsetWidth || 168);
        var top = rect.bottom + 6;
        var right = window.innerWidth - rect.right;

        if(top + panel.offsetHeight > window.innerHeight - 8){
            top = Math.max(8, rect.top - panel.offsetHeight - 6);
        }

        if(right + panelWidth > window.innerWidth - 8){
            right = Math.max(8, window.innerWidth - panelWidth - 8);
        }

        panel.style.top = Math.round(top) + 'px';
        panel.style.right = Math.round(right) + 'px';
        panel.style.left = 'auto';
        panel.style.minWidth = '168px';
    }

    document.querySelectorAll('[data-menu-btn]').forEach(function(btn){
        btn.addEventListener('click', function(e){
            e.preventDefault();
            e.stopPropagation();

            var menu = btn.closest('.campaignMenu');
            var panel = menu ? menu.querySelector('.campaignMenuPanel') : null;
            var item = btn.closest('.campaignListItem');
            var willOpen = panel && !panel.classList.contains('is-open');

            closeCampaignMenus();

            if(!willOpen || !panel){
                return;
            }

            panel.classList.add('is-open');
            panel.style.visibility = 'hidden';

            if(item){
                item.classList.add('is-menu-open');
            }

            positionCampaignMenuPanel(btn, panel);
            panel.style.visibility = '';
        });
    });

    document.addEventListener('click', closeCampaignMenus);

    document.querySelectorAll('.campaignMenuPanel').forEach(function(panel){
        panel.addEventListener('click', function(e){
            e.stopPropagation();
        });
    });

    window.addEventListener('resize', closeCampaignMenus);
    window.addEventListener('scroll', closeCampaignMenus, true);
})();
</script>
JS;
    }

    function campaignAdminBodyClass(){
        return 'campaignAdmin adminHasBottomNav';
    }

}
