<?php

if(!function_exists('adminQuickNav')){

    function adminQuickNav($active = ''){

        if(!function_exists('pnvAdminUrl')){
            return;
        }

        $items = [
            ['key' => 'dashboard', 'label' => 'داشبورد', 'href' => pnvAdminUrl()],
            ['key' => 'payments', 'label' => 'خریدها', 'href' => pnvAdminUrl('index.php?page=payments')],
            ['key' => 'renews', 'label' => 'تمدیدها', 'href' => pnvAdminUrl('index.php?page=renews')],
            ['key' => 'users', 'label' => 'کاربران', 'href' => pnvAdminUrl('users.php')],
            ['key' => 'telegram', 'label' => 'تلگرام', 'href' => pnvAdminUrl('telegram.php')],
            ['key' => 'bale', 'label' => 'بله', 'href' => pnvAdminUrl('bale.php')],
            ['key' => 'xui', 'label' => '3x-ui', 'href' => pnvAdminUrl('xui-servers.php')],
        ];

        echo '<nav class="adminQuickNav">';

        foreach($items as $item){
            $cls = ($active === $item['key']) ? ' is-active' : '';
            echo '<a class="adminQuickNavLink' . $cls . '" href="' . htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8') . '">'
                . htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8')
                . '</a>';
        }

        echo '</nav>';
    }

    function adminQuickNavStyles(){
        echo '<style>
.adminQuickNav{display:flex;flex-wrap:wrap;gap:8px;margin:0 0 16px}
.adminQuickNavLink{display:inline-flex;align-items:center;padding:8px 12px;border-radius:10px;background:#334155;color:#fff;text-decoration:none;font-size:13px}
.adminQuickNavLink.is-active{background:#22c55e}
@media(max-width:768px){.adminQuickNav{display:none}}
</style>';
    }

    function adminBottomNavStyles(){
        echo '<style>
.adminBottomNav{
display:none;
position:fixed;
left:0;
right:0;
bottom:0;
z-index:60;
background:#111827;
border-top:1px solid #334155;
padding-bottom:env(safe-area-inset-bottom,0);
}
.adminBottomNavInner{
display:flex;
align-items:stretch;
min-height:64px;
}
.adminBottomNavPrimary{
flex:1;
display:flex;
align-items:stretch;
border-top:3px solid #22c55e;
}
.adminBottomNavMore{
width:26%;
max-width:108px;
display:flex;
align-items:center;
justify-content:center;
border-top:3px solid #334155;
border-right:1px solid #1e293b;
}
.adminBottomNavItem{
flex:1;
display:flex;
flex-direction:column;
align-items:center;
justify-content:center;
gap:4px;
padding:8px 4px 10px;
text-decoration:none;
color:#94a3b8;
font-size:11px;
font-family:tahoma;
position:relative;
min-width:0;
}
.adminBottomNavItem svg{
width:22px;
height:22px;
stroke:currentColor;
fill:none;
stroke-width:1.8;
}
.adminBottomNavItem.is-active{
color:#22c55e;
}
.adminBottomNavMoreBtn{
width:100%;
height:100%;
border:none;
background:transparent;
color:#94a3b8;
display:flex;
flex-direction:column;
align-items:center;
justify-content:center;
gap:4px;
font-size:11px;
font-family:tahoma;
cursor:pointer;
padding:8px 4px 10px;
}
.adminBottomNavMoreBtn svg{
width:22px;
height:22px;
stroke:currentColor;
fill:none;
stroke-width:1.8;
}
.adminBottomNavBadge{
position:absolute;
top:4px;
left:50%;
margin-left:10px;
min-width:16px;
height:16px;
padding:0 4px;
border-radius:999px;
background:#ef4444;
color:#fff;
font-size:10px;
font-weight:700;
line-height:16px;
text-align:center;
box-shadow:0 0 8px rgba(239,68,68,.55);
}
.adminMoreSheet{
display:none;
position:fixed;
inset:0;
z-index:70;
}
.adminMoreSheet.is-open{
display:block;
}
.adminMoreSheetBackdrop{
position:absolute;
inset:0;
background:rgba(0,0,0,.45);
}
.adminMoreSheetPanel{
position:absolute;
left:0;
right:0;
bottom:0;
background:#1e293b;
border-radius:18px 18px 0 0;
padding:14px 14px calc(14px + env(safe-area-inset-bottom,0));
max-height:72vh;
overflow:auto;
}
.adminMoreSheetTitle{
font-size:15px;
font-weight:700;
margin:0 0 12px;
text-align:center;
color:#e2e8f0;
}
.adminMoreSheetGrid{
display:grid;
grid-template-columns:repeat(2,minmax(0,1fr));
gap:10px;
}
.adminMoreSheetLink{
display:flex;
align-items:center;
justify-content:center;
min-height:48px;
padding:10px 12px;
border-radius:12px;
background:#0f172a;
border:1px solid #334155;
color:#fff;
text-decoration:none;
font-size:13px;
text-align:center;
}
.adminMoreSheetLink.is-active{
border-color:#22c55e;
color:#86efac;
}
.adminMoreSheetLink.is-danger{
background:#450a0a;
border-color:#7f1d1d;
color:#fecaca;
}
@media(max-width:768px){
.adminBottomNav{display:block}
body.adminHasBottomNav{padding-bottom:84px}
body.adminHasBottomNav .content{padding-bottom:84px !important}
body.adminHasBottomNav .content-support{padding-bottom:0 !important}
body.adminPageSupport .adminBottomNav{display:none}
body.adminPageSupport{padding-bottom:0}
}
</style>';
    }

    function adminBottomNav($options = []){
        if(!function_exists('pnvAdminUrl')){
            return;
        }

        $active = (string)($options['active'] ?? '');
        $badges = is_array($options['badges'] ?? null) ? $options['badges'] : [];
        $moreMode = (string)($options['more_mode'] ?? 'sheet');

        $items = [
            [
                'key' => 'support',
                'label' => 'پیام‌ها',
                'href' => pnvAdminUrl('index.php?page=support'),
                'icon' => '<path d="M21 15a2 2 0 01-2 2H8l-5 3V7a2 2 0 012-2h14a2 2 0 012 2z"/>',
            ],
            [
                'key' => 'renews',
                'label' => 'تمدید',
                'href' => pnvAdminUrl('index.php?page=renews'),
                'icon' => '<path d="M21 12a9 9 0 11-3-6.7"/><path d="M21 3v6h-6"/>',
            ],
            [
                'key' => 'payments',
                'label' => 'خرید',
                'href' => pnvAdminUrl('index.php?page=payments'),
                'icon' => '<path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 01-8 0"/>',
            ],
        ];

        $moreLinks = [
            ['key' => 'dashboard', 'label' => 'داشبورد', 'href' => pnvAdminUrl()],
            ['key' => 'users', 'label' => 'کاربران', 'href' => pnvAdminUrl('users.php')],
            ['key' => 'plans', 'label' => 'پلن‌ها', 'href' => pnvAdminUrl('plans.php')],
            ['key' => 'campaigns', 'label' => 'کمپین‌ها', 'href' => pnvAdminUrl('campaigns.php')],
            ['key' => 'cards', 'label' => 'کارت‌ها', 'href' => pnvAdminUrl('index.php?page=cards')],
            ['key' => 'downloads', 'label' => 'دانلودها', 'href' => pnvAdminUrl('downloads.php')],
            ['key' => 'telegram', 'label' => 'تلگرام', 'href' => pnvAdminUrl('telegram.php')],
            ['key' => 'bale', 'label' => 'بله', 'href' => pnvAdminUrl('bale.php')],
            ['key' => 'xui', 'label' => '3x-ui', 'href' => pnvAdminUrl('xui-servers.php')],
            ['key' => 'upload', 'label' => 'آپلود CSV', 'href' => pnvAdminUrl('index.php?page=upload')],
            ['key' => 'logout', 'label' => 'خروج', 'href' => pnvAdminUrl('index.php?logout=1'), 'danger' => true],
        ];

        echo '<nav class="adminBottomNav" aria-label="منوی اصلی">';
        echo '<div class="adminBottomNavInner">';
        echo '<div class="adminBottomNavPrimary">';

        foreach($items as $item){
            $cls = ($active === $item['key']) ? ' is-active' : '';
            $badge = intval($badges[$item['key']] ?? 0);
            echo '<a class="adminBottomNavItem' . $cls . '" href="' . htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8') . '">';
            echo '<svg viewBox="0 0 24 24" aria-hidden="true">' . $item['icon'] . '</svg>';
            if($badge > 0){
                echo '<span class="adminBottomNavBadge">' . ($badge > 9 ? '9+' : $badge) . '</span>';
            }
            echo '<span>' . htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') . '</span>';
            echo '</a>';
        }

        echo '</div>';
        echo '<div class="adminBottomNavMore">';
        echo '<button type="button" class="adminBottomNavMoreBtn" id="adminBottomMoreBtn" data-more-mode="' . htmlspecialchars($moreMode, ENT_QUOTES, 'UTF-8') . '" aria-label="بیشتر">';
        echo '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>';
        echo '<span>بیشتر</span>';
        echo '</button>';
        echo '</div>';
        echo '</div>';
        echo '</nav>';

        echo '<div class="adminMoreSheet" id="adminMoreSheet" hidden>';
        echo '<div class="adminMoreSheetBackdrop" id="adminMoreSheetBackdrop"></div>';
        echo '<div class="adminMoreSheetPanel">';
        echo '<div class="adminMoreSheetTitle">منوی بیشتر</div>';
        echo '<div class="adminMoreSheetGrid">';

        foreach($moreLinks as $link){
            $cls = 'adminMoreSheetLink';
            if($active === $link['key']){
                $cls .= ' is-active';
            }
            if(!empty($link['danger'])){
                $cls .= ' is-danger';
            }
            echo '<a class="' . $cls . '" href="' . htmlspecialchars($link['href'], ENT_QUOTES, 'UTF-8') . '">'
                . htmlspecialchars($link['label'], ENT_QUOTES, 'UTF-8')
                . '</a>';
        }

        echo '</div></div></div>';
    }

    function adminBottomNavScript(){
        echo <<<'JS'
<script>
(function(){
    document.body.classList.add('adminHasBottomNav');

    const moreBtn = document.getElementById('adminBottomMoreBtn');
    const sheet = document.getElementById('adminMoreSheet');
    const backdrop = document.getElementById('adminMoreSheetBackdrop');

    function openSheet(){
        if(!sheet){ return; }
        sheet.hidden = false;
        sheet.classList.add('is-open');
    }

    function closeSheet(){
        if(!sheet){ return; }
        sheet.classList.remove('is-open');
        sheet.hidden = true;
    }

    function toggleSidebar(){
        document.body.classList.toggle('adminSidebarOpen');
    }

    if(moreBtn){
        moreBtn.addEventListener('click', function(){
            if(moreBtn.getAttribute('data-more-mode') === 'sidebar'){
                toggleSidebar();
                return;
            }
            openSheet();
        });
    }

    if(backdrop){
        backdrop.addEventListener('click', closeSheet);
    }

    window.adminBottomNavSetBadge = function(key, count){
        const map = {support:'support', renews:'renews', payments:'payments'};
        const itemKey = map[key] || key;
        const items = document.querySelectorAll('.adminBottomNavItem');
        items.forEach(function(item){
            const href = item.getAttribute('href') || '';
            const isMatch =
                (itemKey === 'support' && href.indexOf('page=support') >= 0) ||
                (itemKey === 'renews' && href.indexOf('page=renews') >= 0) ||
                (itemKey === 'payments' && href.indexOf('page=payments') >= 0);
            if(!isMatch){ return; }

            let badge = item.querySelector('.adminBottomNavBadge');
            count = parseInt(count || 0, 10);
            if(count > 0){
                if(!badge){
                    badge = document.createElement('span');
                    badge.className = 'adminBottomNavBadge';
                    item.appendChild(badge);
                }
                badge.textContent = count > 9 ? '9+' : String(count);
            }else if(badge){
                badge.remove();
            }
        });
    };
})();
</script>
JS;
    }

}
