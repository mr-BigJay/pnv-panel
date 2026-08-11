<?php

if(function_exists('pnvIsTodayTehran')){
    return;
}

$__pnvDateLibCandidates = [
    __DIR__ . '/date_lib.php',
    dirname(__DIR__) . '/date_lib.php',
];

if(!function_exists('pnvGregorianToJalali')){
    foreach($__pnvDateLibCandidates as $__pnvDateLibPath){
        if(is_file($__pnvDateLibPath)){
            require_once $__pnvDateLibPath;
            return;
        }
    }
}

// auth.php may already define pnvGregorianToJalali / pnvJalaliToday — only patch missing helpers
if(!function_exists('pnvEnsureTehranTimezone')){
    function pnvEnsureTehranTimezone(){
        static $set = false;
        if(!$set){
            date_default_timezone_set('Asia/Tehran');
            $set = true;
        }
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
            'date' => trim((string)($row[4] ?? '')) ?: '-',
            'time' => trim((string)($row[5] ?? '')) ?: '-',
        ];
    }
}

if(!function_exists('pnvPaymentRowIsToday')){
    function pnvPaymentRowIsToday($row){
        if(!is_array($row)){
            return false;
        }
        $timestamp = intval($row[8] ?? 0);
        if($timestamp > 0){
            return pnvIsTodayTehran($timestamp);
        }
        if(function_exists('pnvJalaliToday')){
            $date = trim((string)($row[4] ?? ''));
            return $date !== '' && $date === pnvJalaliToday('/');
        }
        return false;
    }
}

if(function_exists('pnvEnsureTehranTimezone')){
    pnvEnsureTehranTimezone();
}
