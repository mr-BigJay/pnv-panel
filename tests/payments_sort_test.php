<?php

/**
 * Regression test: purchase list is ordered by transaction time, not CSV position.
 * Run with: php tests/payments_sort_test.php
 */

$root = sys_get_temp_dir() . '/pnv-payments-sort-' . bin2hex(random_bytes(8));
mkdir($root . '/admin', 0700, true);
mkdir($root . '/invoices', 0700, true);

try {
    foreach (['admin/payments.php', 'pnv_date_bootstrap.php', 'date_lib.php'] as $path) {
        if (!copy(dirname(__DIR__) . '/' . $path, $root . '/' . $path)) {
            throw new RuntimeException('Cannot prepare ' . $path);
        }
    }

    $makeRow = static function ($name, $date, $time, $timestamp, $type = 'خرید') {
        // Use approved rows so the same sort regression also applies to
        // Production's status-filtered admin list (default: approved).
        return [$name, 'config', '1 گیگ', 'tracking', $date, $time,
            'تایید شد', '', $timestamp, $type];
    };

    // The newest transaction is *not* the last row in the file.
    // A legacy payment has no Unix timestamp but has a valid Jalali date/time.
    $rows = [
        $makeRow('recent', '1403/08/05', '12:00', 1730000000),
        $makeRow('tie-old', '1403/04/13', '12:00', 1720000000),
        $makeRow('legacy-newest', '1404/02/12', '12:00', ''),
        $makeRow('tie-new', '1403/04/13', '12:00', 1720000000),
        $makeRow('old-at-end', '1399/06/23', '12:00', 1600000000),
        $makeRow('renew-excluded', '1405/02/24', '12:00', 1800000000, 'تمدید'),
    ];

    $fp = fopen($root . '/invoices/payments.csv', 'wb');
    if (!$fp) {
        throw new RuntimeException('Cannot open temporary CSV');
    }
    foreach ($rows as $row) {
        fputcsv($fp, $row);
    }
    fclose($fp);

    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $_SESSION['admin'] = true;
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_GET = ['page' => 'payments', 'p' => '1', 'status' => 'approved'];

    ob_start();
    try {
        require $root . '/admin/payments.php';
    } finally {
        ob_end_clean();
    }

    $actualNames = array_map(static function ($item) {
        return $item['data'][0] ?? '';
    }, $buyPayments);
    $expectedNames = ['legacy-newest', 'recent', 'tie-new', 'tie-old', 'old-at-end'];
    if ($actualNames !== $expectedNames) {
        fwrite(STDERR, 'Wrong payment order: ' . json_encode($actualNames) . "\n");
        exit(1);
    }

    // Sorting must not change CSV indices, which are used by payment actions.
    $actualIndices = array_column($buyPayments, 'index');
    if ($actualIndices !== [2, 0, 3, 1, 4]) {
        fwrite(STDERR, 'Original payment indexes were changed.\n');
        exit(1);
    }

    echo "ok\n";
} finally {
    foreach (['admin/payments.php', 'pnv_date_bootstrap.php', 'date_lib.php', 'invoices/payments.csv'] as $path) {
        @unlink($root . '/' . $path);
    }
    @rmdir($root . '/admin');
    @rmdir($root . '/invoices');
    @rmdir($root);
}
