<?php

/**
 * تاریخ/ساعت پنل: هجری شمسی + Asia/Tehran
 */

if(!function_exists('pnvEnsureTehranTimezone')){

    function pnvEnsureTehranTimezone(){
        static $set = false;
        if($set){
            return;
        }
        date_default_timezone_set('Asia/Tehran');
        $set = true;
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
        } else {
            $jm = 7 + intdiv($days - 186, 30);
            $jd = 1 + (($days - 186) % 30);
        }

        return [$jy, $jm, $jd];
    }

    function pnvJalaliFromTimestamp($timestamp = null, $separator = '/'){
        pnvEnsureTehranTimezone();
        $timestamp = $timestamp === null ? time() : intval($timestamp);
        if($timestamp <= 0){
            $timestamp = time();
        }

        [$jy, $jm, $jd] = pnvGregorianToJalali(
            intval(date('Y', $timestamp)),
            intval(date('n', $timestamp)),
            intval(date('j', $timestamp))
        );

        return sprintf('%04d%s%02d%s%02d', $jy, $separator, $jm, $separator, $jd);
    }

    function pnvTehranTime($timestamp = null, $format = 'H:i'){
        pnvEnsureTehranTimezone();
        $timestamp = $timestamp === null ? time() : intval($timestamp);
        if($timestamp <= 0){
            $timestamp = time();
        }
        return date($format, $timestamp);
    }

    function pnvJalaliToday($separator = '/'){
        return pnvJalaliFromTimestamp(null, $separator);
    }

    function pnvNowJalaliMeta(){
        $now = time();
        return [
            'date' => pnvJalaliFromTimestamp($now, '/'),
            'time' => pnvTehranTime($now, 'H:i'),
            'timestamp' => $now
        ];
    }
}

pnvEnsureTehranTimezone();
