<?php

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
        $gy = intval($gy);
        $gm = intval($gm);
        $gd = intval($gd);

        $g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
        $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
        $days = 355666 + (365 * $gy) + intdiv($gy2 + 3, 4) - intdiv($gy2 + 99, 100) + intdiv($gy2 + 399, 400) + $gd + $g_d_m[$gm - 1];
        $jy = -1595 + (33 * intdiv($days, 12053));
        $days %= 12053;
        $jy += 4 * intdiv($days, 1461);
        $days %= 1461;

        if($days > 365){
            $jy += intdiv($days - 1, 365);
            $days = ($days - 1) % 365;
        }

        if($days < 186){
            $jm = 1 + intdiv($days, 31);
            $jd = 1 + ($days % 31);
        }
        else{
            $jm = 7 + intdiv($days - 186, 30);
            $jd = 1 + (($days - 186) % 30);
        }

        return [$jy, $jm, $jd];
    }

}

if(!function_exists('pnvDigitsToLatin')){

    function pnvDigitsToLatin($value){
        return strtr((string)$value, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);
    }

}

if(!function_exists('pnvFormatJalaliDate')){

    function pnvFormatJalaliDate($timestamp = null, $separator = '/'){
        pnvEnsureTehranTimezone();

        if($timestamp === null){
            $timestamp = time();
        }
        else{
            $timestamp = intval($timestamp);
        }

        if($timestamp <= 0){
            return '-';
        }

        [$jy, $jm, $jd] = pnvGregorianToJalali(
            intval(date('Y', $timestamp)),
            intval(date('n', $timestamp)),
            intval(date('j', $timestamp))
        );

        return sprintf('%04d%s%02d%s%02d', $jy, $separator, $jm, $separator, $jd);
    }

}

if(!function_exists('pnvFormatTehranTime')){

    function pnvFormatTehranTime($timestamp = null, $withSeconds = false){
        pnvEnsureTehranTimezone();

        if($timestamp === null){
            $timestamp = time();
        }
        else{
            $timestamp = intval($timestamp);
        }

        if($timestamp <= 0){
            return '-';
        }

        return date($withSeconds ? 'H:i:s' : 'H:i', $timestamp);
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

if(!function_exists('pnvParseDateTimeToTimestamp')){

    function pnvParseDateTimeToTimestamp($value){
        pnvEnsureTehranTimezone();
        $value = trim(pnvDigitsToLatin($value));

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

if(!function_exists('pnvIsGregorianYear')){

    function pnvIsGregorianYear($year){
        return intval($year) >= 1700;
    }

}

if(!function_exists('pnvFormatStoredDate')){

    function pnvFormatStoredDate($dateStr, $timestamp = 0){
        $timestamp = intval($timestamp);

        if($timestamp > 0){
            return pnvFormatJalaliDate($timestamp, '/');
        }

        $dateStr = trim(pnvDigitsToLatin((string)$dateStr));

        if($dateStr === '' || $dateStr === '-'){
            return '-';
        }

        if(preg_match('/^(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})$/', $dateStr, $m)){
            $y = intval($m[1]);
            $mo = intval($m[2]);
            $d = intval($m[3]);

            if(!pnvIsGregorianYear($y)){
                return sprintf('%04d/%02d/%02d', $y, $mo, $d);
            }

            [$jy, $jm, $jd] = pnvGregorianToJalali($y, $mo, $d);
            return sprintf('%04d/%02d/%02d', $jy, $jm, $jd);
        }

        return $dateStr;
    }

}

if(!function_exists('pnvFormatStoredTime')){

    function pnvFormatStoredTime($timeStr, $timestamp = 0){
        $timestamp = intval($timestamp);

        if($timestamp > 0){
            return pnvFormatTehranTime($timestamp, false);
        }

        $timeStr = trim(pnvDigitsToLatin((string)$timeStr));
        return $timeStr !== '' ? $timeStr : '-';
    }

}

if(!function_exists('pnvFormatPaymentRowDateTime')){

    function pnvFormatPaymentRowDateTime($row){
        if(!is_array($row)){
            return ['date' => '-', 'time' => '-'];
        }

        $timestamp = intval($row[8] ?? 0);

        return [
            'date' => pnvFormatStoredDate($row[4] ?? '', $timestamp),
            'time' => pnvFormatStoredTime($row[5] ?? '', $timestamp),
        ];
    }

}

if(!function_exists('pnvFormatUserCreatedAt')){

    function pnvFormatUserCreatedAt($value){
        $value = trim((string)$value);

        if($value === ''){
            return '-';
        }

        $ts = pnvParseDateTimeToTimestamp($value);

        if($ts > 0){
            return pnvFormatJalaliDate($ts, '/') . ' ' . pnvFormatTehranTime($ts, false);
        }

        return pnvFormatStoredDate($value);
    }

}

if(!function_exists('pnvIsTodayTehran')){

    function pnvIsTodayTehran($timestampOrString){
        pnvEnsureTehranTimezone();

        if(is_numeric($timestampOrString)){
            $ts = intval($timestampOrString);
        }
        else{
            $ts = pnvParseDateTimeToTimestamp($timestampOrString);
        }

        if($ts <= 0){
            return false;
        }

        return date('Y-m-d', $ts) === date('Y-m-d');
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

        $formatted = pnvFormatPaymentRowDateTime($row);
        return ($formatted['date'] ?? '') === pnvJalaliToday('/');
    }

}

pnvEnsureTehranTimezone();
