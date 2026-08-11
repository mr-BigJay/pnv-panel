<?php

if(!function_exists('pnvEnsureTehranTimezone')){

    function pnvEnsureTehranTimezone(){
        static $set = false;

        if(!$set){
            date_default_timezone_set('Asia/Tehran');
            $set = true;
        }
    }

    function pnvJalaliToGregorian($jy, $jm, $jd){
        $jy = intval($jy);
        $jm = intval($jm);
        $jd = intval($jd);

        $jy -= 979;
        $jm -= 1;
        $jd -= 1;

        $dayNo = 365 * $jy + intdiv($jy, 33) * 8 + intdiv(($jy % 33) + 3, 4);

        for($i = 0; $i < $jm; ++$i){
            $dayNo += ($i < 6) ? 31 : 30;
        }

        $dayNo += $jd;

        $gy = 1600 + 400 * intdiv($dayNo, 146097);
        $dayNo %= 146097;
        $leap = true;

        if($dayNo >= 36525){
            $dayNo--;
            $gy += 100 * intdiv($dayNo, 36524);
            $dayNo %= 36524;

            if($dayNo >= 365){
                $dayNo++;
            }
            else{
                $leap = false;
            }
        }

        $gy += 4 * intdiv($dayNo, 1461);
        $dayNo %= 1461;

        if($dayNo >= 366){
            $leap = false;
            $dayNo--;
            $gy += intdiv($dayNo, 365);
            $dayNo %= 365;
        }

        $gd = $dayNo + 1;
        $sal_a = [0, 31, ($leap ? 29 : 28), 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
        $gm = 0;

        for($gm = 0; $gm < 13 && $gd > $sal_a[$gm]; $gm++){
            $gd -= $sal_a[$gm];
        }

        return [$gy, $gm, $gd];
    }

    function pnvJalaliDateTimeToTimestamp($dateStr, $timeStr = ''){
        pnvEnsureTehranTimezone();

        $dateStr = trim(pnvDigitsToLatin((string)$dateStr));
        $timeStr = trim(pnvDigitsToLatin((string)$timeStr));

        if($dateStr === ''){
            return 0;
        }

        if(!preg_match('/^(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})$/', $dateStr, $m)){
            return pnvParseDateTimeToTimestamp(trim($dateStr . ' ' . $timeStr));
        }

        $y = intval($m[1]);
        $mo = intval($m[2]);
        $d = intval($m[3]);

        if(pnvIsGregorianYear($y)){
            $datePart = sprintf('%04d-%02d-%02d', $y, $mo, $d);
        }
        else{
            [$gy, $gm, $gd] = pnvJalaliToGregorian($y, $mo, $d);
            $datePart = sprintf('%04d-%02d-%02d', $gy, $gm, $gd);
        }

        if($timeStr !== '' && preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $timeStr, $tm)){
            $datePart .= sprintf(
                ' %02d:%02d:%02d',
                intval($tm[1]),
                intval($tm[2]),
                intval($tm[3] ?? 0)
            );
        }
        else{
            $datePart .= ' 00:00:00';
        }

        $ts = strtotime($datePart);

        return $ts ? intval($ts) : 0;
    }

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

    function pnvDigitsToLatin($value){
        return strtr((string)$value, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);
    }

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

    function pnvJalaliToday($separator = '/'){
        return pnvFormatJalaliDate(time(), $separator);
    }

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

    function pnvIsGregorianYear($year){
        return intval($year) >= 1700;
    }

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

    function pnvFormatStoredTime($timeStr, $timestamp = 0){
        $timestamp = intval($timestamp);

        if($timestamp > 0){
            return pnvFormatTehranTime($timestamp, false);
        }

        $timeStr = trim(pnvDigitsToLatin((string)$timeStr));
        return $timeStr !== '' ? $timeStr : '-';
    }

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

    pnvEnsureTehranTimezone();
}
