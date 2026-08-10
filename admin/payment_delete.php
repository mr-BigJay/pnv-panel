<?php

ob_start();

$adminDir = __DIR__;
$rootDir = dirname($adminDir);

foreach ([$adminDir . '/auth.php', $rootDir . '/admin/auth.php'] as $bootFile) {
    if (is_file($bootFile)) {
        require_once $bootFile;
        if (function_exists('pnvAdminRequireAuth')) {
            break;
        }
    }
}

if (!function_exists('pnvAdminRequireAuth')) {
    http_response_code(500);
    exit;
}

pnvAdminRequireAuth();

$page = (string)($_GET['page'] ?? $_GET['list'] ?? '');
if (!in_array($page, ['payments', 'renews'], true)) {
    http_response_code(400);
    exit;
}

if (!isset($_GET['deletepayment'])) {
    http_response_code(400);
    exit;
}

$paymentsFile = $rootDir . '/invoices/payments.csv';
if (!is_file($paymentsFile) && is_file($adminDir . '/../invoices/payments.csv')) {
    $paymentsFile = $adminDir . '/../invoices/payments.csv';
}

$payments = [];

if (is_file($rootDir . '/instant_pay_lib.php')) {
    require_once $rootDir . '/instant_pay_lib.php';
}

if (function_exists('instantPayPurgeAndReloadPaymentsCsv')) {
    $payments = instantPayPurgeAndReloadPaymentsCsv($paymentsFile);
} elseif (is_file($paymentsFile)) {
    $handle = fopen($paymentsFile, 'r');
    if ($handle) {
        while (($row = fgetcsv($handle)) !== false) {
            $payments[] = $row;
        }
        fclose($handle);
    }
}

foreach ([$adminDir . '/payment_list_actions.php', $rootDir . '/admin/payment_list_actions.php'] as $actionsFile) {
    if (is_file($actionsFile)) {
        require_once $actionsFile;
        break;
    }
}

if (!function_exists('pnvAdminDeletePaymentByIndex')) {
    http_response_code(500);
    exit;
}

$id = intval($_GET['deletepayment']);
$redirectPer = intval($_GET['per'] ?? 20);
$redirectPage = max(1, intval($_GET['p'] ?? 1));

pnvAdminDeletePaymentByIndex($paymentsFile, $payments, $id);

$redirectQuery = 'page=' . rawurlencode($page) . '&p=' . $redirectPage;
if ($page === 'payments') {
    if (!in_array($redirectPer, [20, 50, 100], true)) {
        $redirectPer = 20;
    }
    $redirectQuery .= '&per=' . $redirectPer;
}

header('Location: ' . pnvAdminUrl('index.php?' . $redirectQuery));
exit;
