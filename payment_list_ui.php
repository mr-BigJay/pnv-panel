<?php

if(!function_exists('paymentListTabLabels')){
    function paymentListTabLabels(){
        return [
            'approved' => 'تایید شده',
            'pending' => 'درحال بررسی',
            'expired' => 'منقضی',
        ];
    }

    function paymentListActiveTab($fallback = 'approved'){
        $tab = trim((string)($_GET['tab'] ?? $fallback));

        if(!in_array($tab, ['approved', 'pending', 'expired'], true)){
            return $fallback;
        }

        return $tab;
    }

    function paymentListAdminTabsCss(){
        return <<<'CSS'
.payAdminTabs{display:flex;gap:8px;margin:0 0 16px;flex-wrap:wrap}
.payAdminTab{padding:10px 14px;border-radius:12px;background:#1e293b;color:#e2e8f0;text-decoration:none;font-size:13px;border:1px solid rgba(148,163,184,.14)}
.payAdminTab.is-active{background:#2563eb;border-color:#2563eb;color:#fff;font-weight:700}
.statusIcon.is-expired{background:#64748b}
CSS;
    }

    function paymentListRenderAdminTabs($baseUrl, $activeTab){
        $labels = paymentListTabLabels();
        echo '<div class="payAdminTabs">';

        foreach($labels as $key => $label){
            $sep = strpos($baseUrl, '?') !== false ? '&' : '?';
            $href = htmlspecialchars($baseUrl . $sep . 'tab=' . urlencode($key), ENT_QUOTES, 'UTF-8');
            $cls = $key === $activeTab ? 'payAdminTab is-active' : 'payAdminTab';
            echo '<a class="' . $cls . '" href="' . $href . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</a>';
        }

        echo '</div>';
    }
}
