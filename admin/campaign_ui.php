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
.campaignCard{background:#1f2937;border:1px solid #334155;border-radius:18px;padding:16px;margin-bottom:16px}
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
.campaignSearchRow{display:flex;gap:8px;margin-bottom:14px}
.campaignSearchWrap{flex:1;position:relative}
.campaignSearchWrap svg{position:absolute;right:12px;top:50%;transform:translateY(-50%);width:18px;height:18px;color:#64748b}
.campaignSearchWrap input{width:100%;padding:12px 40px 12px 12px;border:1px solid #334155;border-radius:14px;background:#141b26;color:#f8fafc;font-family:inherit;font-size:14px}
.campaignFilterBtn{padding:0 14px;border:1px solid #334155;border-radius:14px;background:#242d3d;color:#e2e8f0;font-family:inherit;font-size:13px;cursor:pointer;white-space:nowrap}
.campaignList{display:flex;flex-direction:column;gap:10px}
.campaignListItem{display:grid;grid-template-columns:34px 1fr;gap:10px;align-items:start;padding:14px;border:1px solid #334155;border-radius:16px;background:#141b26}
.campaignMenuBtn{width:34px;height:34px;border:none;border-radius:10px;background:#242d3d;color:#cbd5e1;font-size:18px;line-height:1;cursor:pointer}
.campaignMenu{position:relative}
.campaignMenuPanel{position:absolute;top:calc(100% + 6px);left:0;min-width:140px;background:#242d3d;border:1px solid #334155;border-radius:12px;padding:6px;z-index:20;display:none;box-shadow:0 12px 30px rgba(0,0,0,.28)}
.campaignMenuPanel.is-open{display:block}
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
@media(max-width:640px){body.campaignAdmin{padding:12px 10px 24px}.campaignGrid2{grid-template-columns:1fr}.campaignItemMeta{grid-template-columns:1fr}}
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

}
