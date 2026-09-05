<?php

if(!function_exists('paymentListEnsureInstantPay')){
    function paymentListEnsureInstantPay(){
        if(!function_exists('instantPayApplyExpiredStatuses')){
            require_once __DIR__ . '/instant_pay_lib.php';
        }

        instantPayApplyExpiredStatuses();
    }

    function paymentListUserDisplayTab($row){
        paymentListEnsureInstantPay();

        if(!function_exists('instantPayResolveDisplayTab')){
            return 'pending';
        }

        $tab = instantPayResolveDisplayTab($row);

        if($tab === 'rejected'){
            return 'approved';
        }

        if($tab === 'hidden'){
            return null;
        }

        return $tab;
    }

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

    function paymentListStatusColor($status){
        if($status === 'تایید شد'){
            return '#22c55e';
        }

        if($status === 'رد شد'){
            return '#ef4444';
        }

        if($status === 'منقضی'){
            return '#64748b';
        }

        return '#eab308';
    }

    function paymentListInfoText($status, $type = 'renew'){
        if($status === 'درحال بررسی' || $status === 'در حال بررسی'){
            return $type === 'renew'
                ? 'درخواست تمدید در حال بررسی است. پس از تأیید پرداخت، اشتراک تمدید می‌شود.'
                : 'درخواست خرید در حال بررسی است. پس از تأیید پرداخت، اشتراک فعال می‌شود.';
        }

        if($status === 'تایید شد'){
            return $type === 'renew' ? 'اشتراک شما تمدید شد.' : 'خرید شما تأیید شد.';
        }

        if($status === 'منقضی'){
            return 'مهلت پرداخت و بررسی (۳۰+۱۰ دقیقه) تمام شده است. در صورت واریز، با پشتیبانی تماس بگیرید.';
        }

        return '';
    }

    function paymentListTabsCss(){
        return <<<'CSS'
.payTabs{display:flex;gap:8px;margin:0 0 16px;overflow-x:auto;padding-bottom:4px}
.payTab{flex:1;min-width:92px;text-align:center;padding:10px 8px;border-radius:12px;background:#1e293b;color:#cbd5e1;text-decoration:none;font-size:13px;border:1px solid #334155;white-space:nowrap}
.payTab.is-active{background:#2563eb;color:#fff;border-color:#2563eb;font-weight:700}
CSS;
    }

    function paymentListRenderTabs($basePath, $activeTab){
        $labels = paymentListTabLabels();
        echo '<div class="payTabs">';

        foreach($labels as $key => $label){
            $href = htmlspecialchars($basePath . '?tab=' . urlencode($key), ENT_QUOTES, 'UTF-8');
            $cls = $key === $activeTab ? 'payTab is-active' : 'payTab';
            echo '<a class="' . $cls . '" href="' . $href . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</a>';
        }

        echo '</div>';
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
