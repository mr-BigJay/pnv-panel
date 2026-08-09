<?php

if(function_exists('pnvEnsureTehranTimezone')){
    return;
}

$__pnvDateLibCandidates = [
    __DIR__ . '/date_lib.php',
    dirname(__DIR__) . '/date_lib.php',
];

foreach($__pnvDateLibCandidates as $__pnvDateLibPath){
    if(is_file($__pnvDateLibPath)){
        require_once $__pnvDateLibPath;
        return;
    }
}

// fallback: جلوگیری از 500 اگر date_lib.php آپلود نشده باشد
if(!function_exists('pnvEnsureTehranTimezone')){
    function pnvEnsureTehranTimezone(){
        static $set = false;
        if(!$set){
            date_default_timezone_set('Asia/Tehran');
            $set = true;
        }
    }

    function pnvJalaliToday($separator = '/'){
        pnvEnsureTehranTimezone();
        return date('Y' . $separator . 'm' . $separator . 'd');
    }

    function pnvFormatJalaliDate($timestamp = null, $separator = '/'){
        pnvEnsureTehranTimezone();
        $timestamp = $timestamp === null ? time() : intval($timestamp);
        return $timestamp > 0 ? date('Y' . $separator . 'm' . $separator . 'd', $timestamp) : '-';
    }

    function pnvFormatTehranTime($timestamp = null, $withSeconds = false){
        pnvEnsureTehranTimezone();
        $timestamp = $timestamp === null ? time() : intval($timestamp);
        return $timestamp > 0 ? date($withSeconds ? 'H:i:s' : 'H:i', $timestamp) : '-';
    }

    function pnvNowParts(){
        pnvEnsureTehranTimezone();
        $now = time();
        return [
            'timestamp' => $now,
            'date' => date('Y/m/d', $now),
            'time' => date('H:i', $now),
            'datetime' => date('Y/m/d H:i', $now),
        ];
    }

    function pnvFormatStoredDate($dateStr, $timestamp = 0){
        $timestamp = intval($timestamp);
        if($timestamp > 0){
            return pnvFormatJalaliDate($timestamp, '/');
        }
        $dateStr = trim((string)$dateStr);
        return $dateStr !== '' ? $dateStr : '-';
    }

    function pnvFormatStoredTime($timeStr, $timestamp = 0){
        $timestamp = intval($timestamp);
        if($timestamp > 0){
            return pnvFormatTehranTime($timestamp, false);
        }
        $timeStr = trim((string)$timeStr);
        return $timeStr !== '' ? $timeStr : '-';
    }

    function pnvFormatPaymentRowDateTime($row){
        if(!is_array($row)){
            return ['date' => '-', 'time' => '-'];
        }
        return [
            'date' => pnvFormatStoredDate($row[4] ?? '', intval($row[8] ?? 0)),
            'time' => pnvFormatStoredTime($row[5] ?? '', intval($row[8] ?? 0)),
        ];
    }

    function pnvFormatUserCreatedAt($value){
        $value = trim((string)$value);
        return $value !== '' ? $value : '-';
    }

    function pnvParseDateTimeToTimestamp($value){
        pnvEnsureTehranTimezone();
        $value = trim((string)$value);
        if($value === ''){
            return 0;
        }
        if(is_numeric($value)){
            return intval($value);
        }
        $ts = strtotime($value);
        return $ts ? intval($ts) : 0;
    }

    function pnvIsTodayTehran($timestampOrString){
        pnvEnsureTehranTimezone();
        $ts = is_numeric($timestampOrString)
            ? intval($timestampOrString)
            : pnvParseDateTimeToTimestamp($timestampOrString);
        if($ts <= 0){
            return false;
        }
        return date('Y-m-d', $ts) === date('Y-m-d');
    }

    function pnvPaymentRowIsToday($row){
        return pnvIsTodayTehran(is_array($row) ? intval($row[8] ?? 0) : 0)
            || (is_array($row) && trim((string)($row[4] ?? '')) === pnvJalaliToday('/'));
    }

    function pnvGregorianToJalali($gy, $gm, $gd){
        return [intval($gy), intval($gm), intval($gd)];
    }

    pnvEnsureTehranTimezone();
}
