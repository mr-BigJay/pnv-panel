<?php

if(!function_exists('pnvBanksCatalog')){

    /**
     * بانک‌های ایرانی — آیکون‌ها از nima-ca/logo-bank (لوکال: assets/bank-logos)
     */
    function pnvBanksCatalog(){
        return [
            'melli' => 'ملی',
            'mellat' => 'ملت',
            'tejarat' => 'تجارت',
            'saderat' => 'صادرات',
            'sepah' => 'سپه',
            'refah' => 'رفاه',
            'maskan' => 'مسکن',
            'keshavarzi' => 'کشاورزی',
            'post' => 'پست بانک',
            'parsian' => 'پارسیان',
            'pasargad' => 'پاسارگاد',
            'saman' => 'سامان',
            'sina' => 'سینا',
            'eghtesad-novin' => 'اقتصاد نوین',
            'karafarin' => 'کارآفرین',
            'sarmayeh' => 'سرمایه',
            'shahr' => 'شهر',
            'ayandeh' => 'آینده',
            'dey' => 'دی',
            'gardeshgari' => 'گردشگری',
            'iran-zamin' => 'ایران زمین',
            'khavar-mianeh' => 'خاورمیانه',
            'resalat' => 'رسالت',
            'mehr-iran' => 'مهر ایران',
            'mehr-eghtesad' => 'مهر اقتصاد',
            'mehr' => 'مهر',
            'blu' => 'بلو',
            'ansar' => 'انصار',
            'ghavamin' => 'قوامین',
            'kosar' => 'کوثر',
            'arman' => 'آرمان',
            'bank-hekmat' => 'حکمت ایرانیان',
            'sanat-madan' => 'صنعت و معدن',
            'tosee-saderat' => 'توسعه صادرات',
            'tosee-taavon' => 'توسعه تعاون',
            'taavon-eslami' => 'تعاون اسلامی',
            'bank-markazi' => 'بانک مرکزی',
        ];
    }

    function pnvBankContains($haystack, $needle){
        $haystack = (string)$haystack;
        $needle = (string)$needle;

        if($needle === '' || $haystack === ''){
            return false;
        }

        if(function_exists('mb_stripos')){
            return mb_stripos($haystack, $needle) !== false;
        }

        return (bool)preg_match('/' . preg_quote($needle, '/') . '/iu', $haystack);
    }

    function pnvBankIconUrl($bankId){
        $bankId = preg_replace('/[^a-z0-9\-]/', '', strtolower(trim((string)$bankId)));
        if($bankId === ''){
            return '';
        }

        $fsPath = __DIR__ . '/assets/bank-logos/' . $bankId . '.svg';

        if(is_file($fsPath)){
            return 'bank-icon.php?b=' . rawurlencode($bankId);
        }

        $altPath = __DIR__ . '/banks/' . $bankId . '.svg';
        if(is_file($altPath)){
            return 'bank-icon.php?b=' . rawurlencode($bankId);
        }

        return 'bank-icon.php?b=' . rawurlencode($bankId);
    }

    function pnvBankLabel($bankId){
        $catalog = pnvBanksCatalog();
        $bankId = strtolower(trim((string)$bankId));
        return $catalog[$bankId] ?? '';
    }

    function pnvGuessBankIdFromText($text){
        $text = trim((string)$text);
        if($text === ''){
            return '';
        }

        $map = [
            'پست' => 'post',
            'رفاه' => 'refah',
            'ملی' => 'melli',
            'ملت' => 'mellat',
            'تجارت' => 'tejarat',
            'صادرات' => 'saderat',
            'سپه' => 'sepah',
            'مسکن' => 'maskan',
            'کشاورزی' => 'keshavarzi',
            'پارسیان' => 'parsian',
            'پاسارگاد' => 'pasargad',
            'سامان' => 'saman',
            'سینا' => 'sina',
            'اقتصاد نوین' => 'eghtesad-novin',
            'اقتصادنوین' => 'eghtesad-novin',
            'کارآفرین' => 'karafarin',
            'سرمایه' => 'sarmayeh',
            'شهر' => 'shahr',
            'آینده' => 'ayandeh',
            'دی' => 'dey',
            'گردشگری' => 'gardeshgari',
            'ایران زمین' => 'iran-zamin',
            'خاورمیانه' => 'khavar-mianeh',
            'رسالت' => 'resalat',
            'مهر ایران' => 'mehr-iran',
            'مهر اقتصاد' => 'mehr-eghtesad',
            'بلو' => 'blu',
            'انصار' => 'ansar',
            'قوامین' => 'ghavamin',
            'کوثر' => 'kosar',
            'آرمان' => 'arman',
            'حکمت' => 'bank-hekmat',
            'صنعت و معدن' => 'sanat-madan',
            'توسعه صادرات' => 'tosee-saderat',
            'توسعه تعاون' => 'tosee-taavon',
        ];

        foreach($map as $needle => $id){
            if(pnvBankContains($text, $needle)){
                return $id;
            }
        }

        return '';
    }

    function pnvNormalizeCardRecord($card){
        if(!is_array($card)){
            return null;
        }

        $number = preg_replace('/\D+/', '', (string)($card['card'] ?? ''));
        $holder = trim((string)($card['holder'] ?? ''));
        $name = trim((string)($card['name'] ?? ''));
        $bank = strtolower(trim((string)($card['bank'] ?? '')));

        if($holder === '' && $name !== ''){
            // فرمت قدیمی: «پست بانک - صادق جعفری»
            if(strpos($name, ' - ') !== false){
                [$left, $right] = explode(' - ', $name, 2);
                if($bank === ''){
                    $bank = pnvGuessBankIdFromText($left);
                }
                $holder = trim($right);
            } else {
                $holder = $name;
                if($bank === ''){
                    $bank = pnvGuessBankIdFromText($name);
                }
            }
        }

        if($bank === ''){
            $bank = pnvGuessBankIdFromText($name . ' ' . $holder);
        }

        $bankLabel = pnvBankLabel($bank);
        if($bankLabel === '' && $name !== ''){
            $bankLabel = $name;
        }

        $display = $bankLabel !== '' && $holder !== ''
            ? ($bankLabel . ' - ' . $holder)
            : ($holder !== '' ? $holder : $name);

        return [
            'bank' => $bank,
            'bank_label' => $bankLabel !== '' ? $bankLabel : 'بانک',
            'holder' => $holder,
            'name' => $display,
            'card' => $number,
            'icon' => pnvBankIconUrl($bank),
        ];
    }

    function pnvCardsForUi($cards){
        $out = [];
        if(!is_array($cards)){
            return $out;
        }
        foreach($cards as $card){
            $n = pnvNormalizeCardRecord($card);
            if($n && $n['card'] !== ''){
                $out[] = $n;
            }
        }
        return $out;
    }
}
