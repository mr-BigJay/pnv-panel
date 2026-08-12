<?php

if(function_exists('pnvFormatJalaliDate') && function_exists('pnvFormatTehranTime')){
    return;
}

$__pnvDateLibCandidates = [
    __DIR__ . '/date_lib.php',
    dirname(__DIR__) . '/date_lib.php',
];

if(!function_exists('pnvFormatJalaliDate')){
    foreach($__pnvDateLibCandidates as $__pnvDateLibPath){
        if(is_file($__pnvDateLibPath)){
            require_once $__pnvDateLibPath;
            break;
        }
    }
}

if(!function_exists('pnvEnsureTehranTimezone')){
    function pnvEnsureTehranTimezone(){
        static $set = false;
        if(!$set){
            date_default_timezone_set('Asia/Tehran');
            $set = true;
        }
    }
}

if(!function_exists('pnvGregorianToJalali')){
    function pnvGregorianToJalali($gy, $gm, $gd){
        return [intval($gy), intval($gm), intval($gd)];
    }
}

if(!function_exists('pnvFormatJalaliDate')){
    function pnvFormatJalaliDate($timestamp = null, $separator = '/'){
        pnvEnsureTehranTimezone();
        $timestamp = $timestamp === null ? time() : intval($timestamp);
        return $timestamp > 0 ? date('Y' . $separator . 'm' . $separator . 'd', $timestamp) : '-';
    }
}

if(!function_exists('pnvFormatTehranTime')){
    function pnvFormatTehranTime($timestamp = null, $withSeconds = false){
        pnvEnsureTehranTimezone();
        $timestamp = $timestamp === null ? time() : intval($timestamp);
        return $timestamp > 0 ? date($withSeconds ? 'H:i:s' : 'H:i', $timestamp) : '-';
    }
}

if(!function_exists('pnvJalaliToday')){
    function pnvJalaliToday($separator = '/'){
        return pnvFormatJalaliDate(time(), $separator);
    }
}

if(!function_exists('pnvNowParts')){
    function pnvNowParts(){
        pnvEnsureTehranTimezone();
        $now = time();
        return [
            'timestamp' => $now,
            'date' => pnvFormatJalaliDate($now, '/'),
            'time' => pnvFormatTehranTime($now, false),
            'datetime' => pnvFormatJalaliDate($now, '/') . ' ' . pnvFormatTehranTime($now, false),
        ];
    }
}

if(!function_exists('pnvFormatStoredDate')){
    function pnvFormatStoredDate($dateStr, $timestamp = 0){
        $timestamp = intval($timestamp);
        if($timestamp > 0){
            return pnvFormatJalaliDate($timestamp, '/');
        }
        $dateStr = trim((string)$dateStr);
        return $dateStr !== '' ? $dateStr : '-';
    }
}

if(!function_exists('pnvFormatStoredTime')){
    function pnvFormatStoredTime($timeStr, $timestamp = 0){
        $timestamp = intval($timestamp);
        if($timestamp > 0){
            return pnvFormatTehranTime($timestamp, false);
        }
        $timeStr = trim((string)$timeStr);
        return $timeStr !== '' ? $timeStr : '-';
    }
}

if(!function_exists('pnvParseDateTimeToTimestamp')){
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
}

if(!function_exists('pnvIsTodayTehran')){
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
}

if(!function_exists('pnvFormatPaymentRowDateTime')){
    function pnvFormatPaymentRowDateTime($row){
        if(!is_array($row)){
            return ['date' => '-', 'time' => '-'];
        }
        return [
            'date' => pnvFormatStoredDate($row[4] ?? '', intval($row[8] ?? 0)),
            'time' => pnvFormatStoredTime($row[5] ?? '', intval($row[8] ?? 0)),
        ];
    }
}

if(!function_exists('pnvFormatUserCreatedAt')){
    function pnvFormatUserCreatedAt($value){
        $value = trim((string)$value);
        return $value !== '' ? $value : '-';
    }
}

if(!function_exists('pnvPaymentRowIsToday')){
    function pnvPaymentRowIsToday($row){
        return pnvIsTodayTehran(is_array($row) ? intval($row[8] ?? 0) : 0)
            || (is_array($row) && trim((string)($row[4] ?? '')) === pnvJalaliToday('/'));
    }
}

pnvEnsureTehranTimezone();
