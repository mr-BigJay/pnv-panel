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
            ['key' => 'sms', 'label' => 'پیامک', 'href' => pnvAdminUrl('sms.php')],
            ['key' => 'backup', 'label' => 'بک‌آپ', 'href' => pnvAdminUrl('backup.php')],
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

}

if(!function_exists('adminBottomNavStyles')){

    function adminBottomNavStyles(){
        static $done = false;

        if($done){
            return;
        }

        $done = true;

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
direction:rtl;
}
.adminBottomNavPrimary{
flex:1;
display:flex;
align-items:stretch;
border-top:3px solid #22c55e;
min-width:0;
}
.adminBottomNavDashboard{
width:22%;
max-width:96px;
display:flex;
align-items:stretch;
border-top:3px solid #334155;
border-right:1px solid #1e293b;
}
.adminBottomNavMore{
width:22%;
max-width:96px;
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
.adminBottomNavMoreBtn.is-active{
color:#22c55e;
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
.adminMgmtDrawer{
position:fixed;
inset:0;
z-index:80;
visibility:hidden;
pointer-events:none;
}
.adminMgmtDrawer.is-open{
visibility:visible;
pointer-events:auto;
}
.adminMgmtDrawerOverlay{
position:absolute;
inset:0;
background:rgba(0,0,0,.55);
opacity:0;
transition:opacity .22s ease;
}
.adminMgmtDrawer.is-open .adminMgmtDrawerOverlay{
opacity:1;
}
.adminMgmtDrawerPanel{
position:absolute;
top:0;
left:0;
bottom:0;
width:min(82vw,320px);
max-width:320px;
background:#111827;
border-right:1px solid #334155;
box-shadow:8px 0 32px rgba(0,0,0,.42);
border-radius:0 16px 16px 0;
transform:translateX(-105%);
transition:transform .24s ease;
display:flex;
flex-direction:column;
min-height:0;
direction:rtl;
font-family:tahoma;
color:#fff;
}
.adminMgmtDrawer.is-open .adminMgmtDrawerPanel{
transform:translateX(0);
}
.adminMgmtDrawerHead{
flex:0 0 auto;
display:flex;
align-items:center;
justify-content:space-between;
gap:10px;
padding:16px 14px 12px;
border-bottom:1px solid #334155;
background:#111827;
}
.adminMgmtDrawerTitle{
margin:0;
font-size:17px;
font-weight:700;
color:#f8fafc;
}
.adminMgmtDrawerClose{
width:36px;
height:36px;
border:none;
border-radius:10px;
background:#1e293b;
color:#e2e8f0;
font-size:22px;
line-height:1;
cursor:pointer;
flex-shrink:0;
display:inline-flex;
align-items:center;
justify-content:center;
}
.adminMgmtDrawerClose:active{
background:#334155;
}
.adminMgmtDrawerScroll{
flex:1 1 auto;
min-height:0;
overflow-y:auto;
overflow-x:hidden;
-webkit-overflow-scrolling:touch;
overscroll-behavior:contain;
padding:12px 12px 16px;
}
.adminMgmtDrawerItem{
display:flex;
align-items:center;
gap:12px;
padding:12px 14px;
margin-bottom:8px;
border-radius:12px;
background:#1e293b;
border:1px solid #334155;
color:#f1f5f9;
text-decoration:none;
font-size:14px;
line-height:1.4;
transition:background .15s ease,border-color .15s ease,color .15s ease;
position:relative;
}
.adminMgmtDrawerItem:last-child{
margin-bottom:0;
}
.adminMgmtDrawerItem svg{
width:20px;
height:20px;
stroke:currentColor;
fill:none;
stroke-width:1.8;
flex-shrink:0;
}
.adminMgmtDrawerItemLabel{
flex:1;
min-width:0;
}
.adminMgmtDrawerItem.is-active{
border-color:#22c55e;
color:#86efac;
background:rgba(34,197,94,.12);
}
.adminMgmtDrawerItem:active,
.adminMgmtDrawerItem:hover{
border-color:rgba(34,197,94,.55);
background:rgba(34,197,94,.08);
}
.adminMgmtDrawerItem.is-logout{
margin-top:4px;
background:#450a0a;
border-color:#7f1d1d;
color:#fecaca;
}
.adminMgmtDrawerItem.is-logout.is-active,
.adminMgmtDrawerItem.is-logout:hover,
.adminMgmtDrawerItem.is-logout:active{
border-color:#ef4444;
background:#581c1c;
color:#fff;
}
.adminMgmtDrawerBadge{
min-width:18px;
height:18px;
padding:0 5px;
border-radius:999px;
background:#ef4444;
color:#fff;
font-size:10px;
font-weight:700;
line-height:18px;
text-align:center;
flex-shrink:0;
box-shadow:0 0 8px rgba(239,68,68,.45);
}
body.adminMgmtDrawerOpen{
overflow:hidden;
}
@media(max-width:768px){
.adminBottomNav{display:block !important}
body.adminHasBottomNav{padding-bottom:84px}
body.adminHasBottomNav .content{padding-bottom:84px !important}
body.adminHasBottomNav .content-support{padding-bottom:0 !important}
body.adminPageSupport .adminBottomNav{display:none !important}
body.adminPageSupport{padding-bottom:0}
}
</style>';
    }

}

if(!function_exists('adminMgmtMenuItems')){

    function adminMgmtMenuItems($options = []){
        if(!function_exists('pnvAdminUrl')){
            return [];
        }

        $active = (string)($options['active'] ?? '');
        $badges = is_array($options['badges'] ?? null) ? $options['badges'] : [];

        $items = [
            [
                'key' => 'dashboard',
                'label' => 'داشبورد',
                'href' => pnvAdminUrl(),
                'icon' => '<path d="M3 10.5L12 3l9 7.5"/><path d="M5 9.5V20h14V9.5"/><path d="M10 20v-6h4v6"/>',
            ],
            [
                'key' => 'support',
                'label' => 'پیام‌های کاربران',
                'href' => pnvAdminUrl('index.php?page=support'),
                'icon' => '<path d="M21 15a2 2 0 01-2 2H8l-5 3V7a2 2 0 012-2h14a2 2 0 012 2z"/>',
                'badge' => intval($badges['support'] ?? 0),
            ],
            [
                'key' => 'users',
                'label' => 'لیست کاربران',
                'href' => pnvAdminUrl('users.php'),
                'icon' => '<path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/>',
            ],
            [
                'key' => 'payments',
                'label' => 'لیست خریدهای جدید',
                'href' => pnvAdminUrl('index.php?page=payments'),
                'icon' => '<path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 01-8 0"/>',
                'badge' => intval($badges['payments'] ?? 0),
            ],
            [
                'key' => 'renews',
                'label' => 'لیست تمدیدها',
                'href' => pnvAdminUrl('index.php?page=renews'),
                'icon' => '<path d="M21 12a9 9 0 11-3-6.7"/><path d="M21 3v6h-6"/>',
                'badge' => intval($badges['renews'] ?? 0),
            ],
            [
                'key' => 'plans',
                'label' => 'مدیریت پلن‌ها',
                'href' => pnvAdminUrl('plans.php'),
                'icon' => '<path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/>',
            ],
            [
                'key' => 'campaigns',
                'label' => 'کمپین‌ها',
                'href' => pnvAdminUrl('campaigns.php'),
                'icon' => '<path d="M3 11l18-5v12L3 13v-2z"/><path d="M11 13v8"/>',
            ],
            [
                'key' => 'cards',
                'label' => 'مدیریت کارت‌ها',
                'href' => pnvAdminUrl('index.php?page=cards'),
                'icon' => '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/>',
            ],
            [
                'key' => 'downloads',
                'label' => 'مدیریت دانلودها',
                'href' => pnvAdminUrl('downloads.php'),
                'icon' => '<path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><path d="M7 10l5 5 5-5"/><path d="M12 15V3"/>',
            ],
            [
                'key' => 'telegram',
                'label' => 'تنظیمات بات تلگرام',
                'href' => pnvAdminUrl('telegram.php'),
                'icon' => '<path d="M22 2L11 13"/><path d="M22 2l-7 20-4-9-9-4 20-7z"/>',
            ],
            [
                'key' => 'bale',
                'label' => 'بله - پرداخت آنی',
                'href' => pnvAdminUrl('bale.php'),
                'icon' => '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/><path d="M6 15h.01"/>',
            ],
            [
                'key' => 'xui',
                'label' => 'سرورهای 3x-ui',
                'href' => pnvAdminUrl('xui-servers.php'),
                'icon' => '<rect x="2" y="3" width="20" height="8" rx="2"/><rect x="2" y="13" width="20" height="8" rx="2"/><path d="M6 7h.01M6 17h.01"/>',
            ],
            [
                'key' => 'upload',
                'label' => 'آپلود فایل کاربران سرورها',
                'href' => pnvAdminUrl('index.php?page=upload'),
                'icon' => '<path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><path d="M17 8l-5-5-5 5"/><path d="M12 3v12"/>',
            ],
            [
                'key' => 'logout',
                'label' => 'خروج',
                'href' => pnvAdminUrl('index.php?logout=1'),
                'icon' => '<path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/>',
                'logout' => true,
            ],
        ];

        foreach($items as $i => $item){
            $key = $item['key'] ?? '';
            $items[$i]['active'] = ($active !== '' && $active === $key)
                || ($active === '' && $key === 'dashboard');
        }

        return $items;
    }

}

if(!function_exists('adminMgmtDrawer')){

    function adminMgmtDrawer($options = []){
        static $rendered = false;

        if($rendered){
            return;
        }

        $rendered = true;
        $items = adminMgmtMenuItems($options);

        echo '<div class="adminMgmtDrawer" id="adminMgmtDrawer" hidden aria-hidden="true">';
        echo '<div class="adminMgmtDrawerOverlay" id="adminMgmtDrawerOverlay"></div>';
        echo '<aside class="adminMgmtDrawerPanel" role="dialog" aria-modal="true" aria-label="منوی مدیریت">';
        echo '<div class="adminMgmtDrawerHead">';
        echo '<h2 class="adminMgmtDrawerTitle">مدیریت</h2>';
        echo '<button type="button" class="adminMgmtDrawerClose" id="adminMgmtDrawerClose" aria-label="بستن منو">×</button>';
        echo '</div>';
        echo '<div class="adminMgmtDrawerScroll">';

        foreach($items as $item){
            $cls = 'adminMgmtDrawerItem';

            if(!empty($item['active'])){
                $cls .= ' is-active';
            }

            if(!empty($item['logout'])){
                $cls .= ' is-logout';
            }

            echo '<a class="' . $cls . '" href="' . htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8') . '">';
            echo '<svg viewBox="0 0 24 24" aria-hidden="true">' . ($item['icon'] ?? '') . '</svg>';
            echo '<span class="adminMgmtDrawerItemLabel">' . htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') . '</span>';

            $badge = intval($item['badge'] ?? 0);

            if($badge > 0){
                echo '<span class="adminMgmtDrawerBadge">' . ($badge > 9 ? '9+' : $badge) . '</span>';
            }

            echo '</a>';
        }

        echo '</div>';
        echo '</aside>';
        echo '</div>';
    }

}

if(!function_exists('adminBottomNav')){

    function adminBottomNav($options = []){
        if(!function_exists('pnvAdminUrl')){
            return;
        }

        $active = (string)($options['active'] ?? '');
        $badges = is_array($options['badges'] ?? null) ? $options['badges'] : [];

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

        $dashboardHref = pnvAdminUrl();
        $dashboardActive = ($active === 'dashboard' || $active === '');

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

        echo '<div class="adminBottomNavDashboard">';
        echo '<a class="adminBottomNavItem' . ($dashboardActive ? ' is-active' : '') . '" href="' . htmlspecialchars($dashboardHref, ENT_QUOTES, 'UTF-8') . '">';
        echo '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 10.5L12 3l9 7.5"/><path d="M5 9.5V20h14V9.5"/><path d="M10 20v-6h4v6"/></svg>';
        echo '<span>داشبورد</span>';
        echo '</a>';
        echo '</div>';

        echo '<div class="adminBottomNavMore">';
        echo '<button type="button" class="adminBottomNavMoreBtn" id="adminBottomMoreBtn" aria-label="بیشتر" aria-expanded="false" aria-controls="adminMgmtDrawer">';
        echo '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>';
        echo '<span>بیشتر</span>';
        echo '</button>';
        echo '</div>';
        echo '</div>';
        echo '</nav>';

        if(function_exists('adminMgmtDrawer')){
            adminMgmtDrawer(array_merge($options, [
                'active' => $active,
                'badges' => $badges,
            ]));
        }
    }

}

if(!function_exists('adminBottomNavScript')){

    function adminBottomNavScript(){
        echo <<<'JS'
<script>
(function(){
    document.body.classList.add('adminHasBottomNav');

    const moreBtn = document.getElementById('adminBottomMoreBtn');
    const drawer = document.getElementById('adminMgmtDrawer');
    const overlay = document.getElementById('adminMgmtDrawerOverlay');
    const closeBtn = document.getElementById('adminMgmtDrawerClose');

    function openDrawer(){
        if(!drawer){ return; }
        drawer.hidden = false;
        drawer.setAttribute('aria-hidden', 'false');
        drawer.classList.add('is-open');
        document.body.classList.add('adminMgmtDrawerOpen');
        if(moreBtn){
            moreBtn.classList.add('is-active');
            moreBtn.setAttribute('aria-expanded', 'true');
        }
    }

    function closeDrawer(){
        if(!drawer){ return; }
        drawer.classList.remove('is-open');
        drawer.setAttribute('aria-hidden', 'true');
        drawer.hidden = true;
        document.body.classList.remove('adminMgmtDrawerOpen');
        if(moreBtn){
            moreBtn.classList.remove('is-active');
            moreBtn.setAttribute('aria-expanded', 'false');
        }
    }

    function toggleDrawer(){
        if(drawer && drawer.classList.contains('is-open')){
            closeDrawer();
        }else{
            openDrawer();
        }
    }

    if(moreBtn){
        moreBtn.addEventListener('click', toggleDrawer);
    }

    if(overlay){
        overlay.addEventListener('click', closeDrawer);
    }

    if(closeBtn){
        closeBtn.addEventListener('click', closeDrawer);
    }

    document.addEventListener('keydown', function(ev){
        if(ev.key === 'Escape'){
            closeDrawer();
        }
    });

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

        const drawerLinks = document.querySelectorAll('.adminMgmtDrawerItem[href]');
        drawerLinks.forEach(function(link){
            const href = link.getAttribute('href') || '';
            const isMatch =
                (itemKey === 'support' && href.indexOf('page=support') >= 0) ||
                (itemKey === 'renews' && href.indexOf('page=renews') >= 0) ||
                (itemKey === 'payments' && href.indexOf('page=payments') >= 0);
            if(!isMatch){ return; }

            let badge = link.querySelector('.adminMgmtDrawerBadge');
            count = parseInt(count || 0, 10);
            if(count > 0){
                if(!badge){
                    badge = document.createElement('span');
                    badge.className = 'adminMgmtDrawerBadge';
                    link.appendChild(badge);
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

if(!function_exists('adminPageEnd')){

    function adminPageEnd($options = []){
        static $rendered = false;

        if($rendered){
            return;
        }

        $rendered = true;
        adminBottomNavStyles();
        adminBottomNav($options);
        adminBottomNavScript();
    }

}
