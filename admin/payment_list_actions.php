<?php

if(!function_exists('pnvAdminDeletePaymentByIndex')){
    function pnvAdminDeletePaymentByIndex($paymentsFile, &$payments, $id){
        $id = intval($id);

        if(isset($payments[$id])){
            unset($payments[$id]);
            $payments = array_values($payments);
        }

        pnvAdminSavePaymentsCsv($paymentsFile, $payments);

        if(function_exists('instantPayRebuildCsvIndexes')){
            instantPayRebuildCsvIndexes();
        }

        return true;
    }
}

if(!function_exists('pnvAdminSavePaymentsCsv')){
    function pnvAdminSavePaymentsCsv($paymentsFile, $payments){
        $fp = fopen($paymentsFile, 'w');

        if(!$fp){
            return false;
        }

        foreach($payments as $p){
            fputcsv($fp, $p);
        }

        fclose($fp);
        return true;
    }
}

if(!function_exists('isValidSubscriptionLink')){
    function isValidSubscriptionLink($link){
        $link = trim((string)$link);

        if($link === ''){
            return false;
        }

        if(!filter_var($link, FILTER_VALIDATE_URL)){
            return false;
        }

        $validDomains = [
            'vip.boozhaan.ir',
            'vip2.boozhaan.ir',
            'vip3.boozhaan.ir',
            'vip4.boozhaan.ir'
        ];

        foreach($validDomains as $d){
            if(stripos($link, $d) !== false){
                return true;
            }
        }

        return (bool)preg_match('/^[A-Za-z0-9]{8,32}$/', $link);
    }
}

if(!function_exists('pnvAdminBuyPaymentsHandleActions')){
    function pnvAdminBuyPaymentsHandleActions($paymentsFile, &$payments){
        $allowedPerPage = [20, 50, 100];

        if(isset($_POST['approve_payment'])){
            $index = intval($_POST['approve_index'] ?? -1);
            $link = trim($_POST['approve_link'] ?? '');
            $redirectPer = intval($_POST['per'] ?? $_GET['per'] ?? 20);

            if(!in_array($redirectPer, $allowedPerPage, true)){
                $redirectPer = 20;
            }

            if(is_file(__DIR__ . '/../xui_lib.php')){
                require_once __DIR__ . '/../xui_lib.php';
            }

            $xuiConfig = function_exists('xuiLoadConfig') ? xuiLoadConfig() : [];
            $xuiEnabled = function_exists('xuiIsEnabled')
                ? xuiIsEnabled($xuiConfig)
                : !empty($xuiConfig['enabled']);

            if($xuiEnabled && function_exists('xuiApprovePaymentIndex')){
                $result = xuiApprovePaymentIndex($index, 'خرید');

                if(empty($result['ok'])){
                    $_SESSION['payment_error'] = 'تایید خودکار ناموفق: ' . ($result['error'] ?? 'خطای نامشخص');
                    header('Location: ' . pnvAdminUrl('index.php?page=payments&per=' . $redirectPer));
                    exit;
                }

                $_SESSION['payment_message'] = 'پرداخت تایید و اشتراک ساخته شد: ' . ($result['link'] ?? '');
                header('Location: ' . pnvAdminUrl('index.php?page=payments&per=' . $redirectPer));
                exit;
            }

            if(!isValidSubscriptionLink($link)){
                $_SESSION['payment_error'] = 'برای تایید پرداخت، وارد کردن لینک اشتراک معتبر الزامی است';
                header('Location: ' . pnvAdminUrl('index.php?page=payments&per=' . $redirectPer));
                exit;
            }

            if(isset($payments[$index])){
                $payments[$index][6] = 'تایید شد';
                $payments[$index][7] = $link;
            }

            pnvAdminSavePaymentsCsv($paymentsFile, $payments);
            $_SESSION['payment_message'] = 'پرداخت با موفقیت تایید شد';
            header('Location: ' . pnvAdminUrl('index.php?page=payments&per=' . $redirectPer));
            exit;
        }

        if(isset($_POST['reject_payment'])){
            $index = intval($_POST['reject_index'] ?? -1);
            $reason = trim((string)($_POST['reject_reason'] ?? ''));
            $redirectPer = intval($_POST['per'] ?? $_GET['per'] ?? 20);

            if(!in_array($redirectPer, $allowedPerPage, true)){
                $redirectPer = 20;
            }

            if(isset($payments[$index])){
                $payments[$index][6] = 'رد شد';
                $payments[$index][7] = $reason;
            }

            pnvAdminSavePaymentsCsv($paymentsFile, $payments);
            header('Location: ' . pnvAdminUrl('index.php?page=payments&per=' . $redirectPer));
            exit;
        }

        if(isset($_GET['deletepayment'])){
            $id = intval($_GET['deletepayment']);
            $redirectPer = intval($_GET['per'] ?? 20);
            $redirectPage = max(1, intval($_GET['p'] ?? 1));

            if(!in_array($redirectPer, [20, 50, 100], true)){
                $redirectPer = 20;
            }

            pnvAdminDeletePaymentByIndex($paymentsFile, $payments, $id);

            header('Location: ' . pnvAdminUrl(
                'index.php?page=payments&p=' . $redirectPage . '&per=' . $redirectPer
            ));
            exit;
        }
    }
}

if(!function_exists('pnvAdminRenewPaymentsHandleActions')){
    function pnvAdminRenewPaymentsHandleActions($paymentsFile, &$payments){
        if(isset($_POST['approve_payment'])){
            $index = intval($_POST['approve_index'] ?? -1);
            $link = trim($_POST['approve_link'] ?? '');

            if(is_file(__DIR__ . '/../xui_lib.php')){
                require_once __DIR__ . '/../xui_lib.php';
            }

            $xuiEnabled = function_exists('xuiIsEnabled') && function_exists('xuiLoadConfig')
                ? xuiIsEnabled(xuiLoadConfig())
                : false;

            if($xuiEnabled && function_exists('xuiApprovePaymentIndex')){
                $result = xuiApprovePaymentIndex($index, 'تمدید');

                if(empty($result['ok'])){
                    $_SESSION['payment_error'] = 'تمدید خودکار ناموفق: ' . ($result['error'] ?? 'خطای نامشخص');
                    header('Location: ' . pnvAdminUrl('index.php?page=renews'));
                    exit;
                }

                $_SESSION['payment_message'] = 'تمدید تایید و اعمال شد';
                $_SESSION['payment_message_detail'] = (string)($result['link'] ?? '');
                header('Location: ' . pnvAdminUrl('index.php?page=renews'));
                exit;
            }

            if(isset($payments[$index])){
                $payments[$index][6] = 'تایید شد';
                $payments[$index][7] = $link;
            }

            pnvAdminSavePaymentsCsv($paymentsFile, $payments);
            $_SESSION['payment_message'] = 'تمدید تایید شد';
            $_SESSION['payment_message_detail'] = $link;
            header('Location: ' . pnvAdminUrl('index.php?page=renews'));
            exit;
        }

        if(isset($_POST['reject_payment'])){
            $index = intval($_POST['reject_index'] ?? -1);
            $reason = trim((string)($_POST['reject_reason'] ?? ''));

            if(isset($payments[$index])){
                $payments[$index][6] = 'رد شد';
                $payments[$index][7] = $reason;
            }

            pnvAdminSavePaymentsCsv($paymentsFile, $payments);
            $_SESSION['payment_message'] = 'تمدید رد شد';
            $_SESSION['payment_message_detail'] = $reason;
            header('Location: ' . pnvAdminUrl('index.php?page=renews'));
            exit;
        }

        if(isset($_GET['deletepayment'])){
            $id = intval($_GET['deletepayment']);
            $redirectPage = max(1, intval($_GET['p'] ?? 1));

            pnvAdminDeletePaymentByIndex($paymentsFile, $payments, $id);

            header('Location: ' . pnvAdminUrl('index.php?page=renews&p=' . $redirectPage));
            exit;
        }
    }
}
