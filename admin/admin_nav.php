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
@media(max-width:600px){.adminQuickNavLink{font-size:12px;padding:7px 10px}}
</style>';
    }

}
