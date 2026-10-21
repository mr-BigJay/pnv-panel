<?php

require_once __DIR__ . '/../coupon_lib.php';

$samples = [
    [
        'label' => 'Mohammad|1778857845',
        'row' => ['Mohammad','Mohammad.D','10 گیگ - 2 تومان','776707665981','1405/02/25','15:09','تایید شد','https://vip3.boozhaan.ir:2096/sub/skij6awj3f407loq','1778857845','خرید'],
        'expected' => 150,
    ],
    [
        'label' => 'Deniza23c0|1778966627',
        'row' => ['Deniza23c0','https://vip3.boozhaan.ir:2096/sub/jml31jr6pf8o5f6v','10 گیگ - 2 تومان','070916004016','1405/02/26','21:15','تایید شد','اشتراک شما تمدید شد','1778966627','تمدید'],
        'expected' => 150,
    ],
    [
        'label' => 'Hosein|1779476870',
        'row' => ['Hosein','https://vip.boozhaan.ir:2096/sub/imlx2t665bmv32sh','10 گیگ - 1900','071041239726','1405/03/01','19:02','تایید شد','','1779476870','تمدید'],
        'expected' => 150,
    ],
    [
        'label' => 'Hosein|1779903477',
        'row' => ['Hosein','https://vip.boozhaan.ir:2096/sub/imlx2t665bmv32sh','20 گیگ - 1.5 میلیون تومان','071154963789','1405/03/02','15:59','تایید شد','','1779903477','تمدید'],
        'expected' => 1500,
    ],
    [
        'label' => 'instant-pay-rial',
        'row' => ['demo','sub','10 گیگ - 150 هزار تومان','AUTO-123','','','تایید شد','','1700000000','خرید','','20','1500000','123'],
        'expected' => 150,
    ],
];

$ok = true;

foreach($samples as $sample){
    $parsed = couponPaymentApprovedAmountThousands($sample['row']);
    $pass = intval($parsed) === intval($sample['expected']);

    if(!$pass){
        $ok = false;
    }

    echo ($pass ? 'PASS' : 'FAIL') . ' ' . $sample['label']
        . ' parsed=' . $parsed
        . ' expected=' . $sample['expected']
        . ' row2=' . ($sample['row'][2] ?? '')
        . "\n";
}

exit($ok ? 0 : 1);
