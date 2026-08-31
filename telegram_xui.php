<?php

require_once __DIR__ . '/xui_lib.php';

if(!function_exists('telegramXuiActionKeyboard')){

    function telegramXuiResultKeyboard($kind, $index){
        if(function_exists('telegramReplyKeyboard') && function_exists('telegramAdminBtnBack')){
            return telegramReplyKeyboard([
                [telegramAdminBtnBack()],
            ]);
        }

        $backMenu = ($kind === 'تمدید') ? 'menu:renews' : 'menu:buys';

        return json_encode([
            'inline_keyboard' => [
                [
                    ['text' => 'بازگشت', 'callback_data' => $backMenu]
                ]
            ]
        ], JSON_UNESCAPED_UNICODE);
    }

    function telegramXuiActionKeyboard($kind, $index){
        if(function_exists('telegramReplyKeyboard') && function_exists('telegramAdminBtnBack')){
            return telegramReplyKeyboard([
                [telegramAdminBtnBack()],
            ]);
        }

        $prefix = ($kind === 'تمدید') ? 'renew' : 'buy';

        return json_encode([
            'inline_keyboard' => [
                [
                    ['text' => '✅ تایید', 'callback_data' => 'xuiok:' . $prefix . ':' . intval($index)],
                    ['text' => '⛔ رد', 'callback_data' => 'xuino:' . $prefix . ':' . intval($index)]
                ],
                [
                    ['text' => 'بازگشت', 'callback_data' => 'menu:home']
                ]
            ]
        ], JSON_UNESCAPED_UNICODE);
    }

    function telegramXuiFindPaymentIndex($username, $created, $kind = ''){
        $rows = xuiLoadPayments();
        $username = trim((string)$username);
        $created = intval($created);
        $fallback = -1;

        foreach($rows as $i => $row){
            if(trim((string)($row[0] ?? '')) !== $username){
                continue;
            }

            $type = trim((string)($row[9] ?? ''));

            if($kind !== '' && $type !== '' && $type !== $kind){
                continue;
            }

            if(intval($row[8] ?? 0) === $created){
                return $i;
            }

            $fallback = $i;
        }

        return $fallback;
    }

    function telegramXuiFormatApproveResult($kind, $result){
        if(empty($result['ok'])){
            return '❌ تایید ' . $kind . " ناموفق بود\n" . ($result['error'] ?? 'خطای نامشخص');
        }

        if(!empty($result['already'])){
            $link = trim((string)($result['link'] ?? ''));
            return 'ℹ️ این ' . $kind . " قبلاً تایید شده است." . ($link !== '' ? ("\n" . $link) : '');
        }

        $lines = ['✅ ' . $kind . ' تایید شد'];

        if(!empty($result['server_id'])){
            $lines[] = 'سرور: ' . $result['server_id'];
        }

        if(!empty($result['email'])){
            $lines[] = 'کلاینت: ' . $result['email'];
        }

        if(!empty($result['gb'])){
            $lines[] = 'حجم: ' . $result['gb'] . 'GB';
        }

        if(!empty($result['days'])){
            $lines[] = 'مدت: ' . $result['days'] . ' روز';
        }

        if(!empty($result['recreated'])){
            $lines[] = '♻️ اشتراک حذف‌شده دوباره ساخته شد';
        }

        if(!empty($result['link'])){
            $lines[] = '';
            $lines[] = $result['link'];
        }

        return implode("\n", $lines);
    }

    function telegramAdminRunXuiAction($chatId, $action, $kind, $index, $config = null){
        $index = intval($index);
        $kind = trim((string)$kind);
        $action = ($action === 'no') ? 'no' : 'ok';

        if($action === 'no'){
            $payments = xuiLoadPayments();
            $status = trim((string)($payments[$index][6] ?? ''));

            if($status === 'تایید شد' || $status === 'رد شد'){
                $text = 'ℹ️ این مورد قبلاً رسیدگی شده است (' . $status . ').';
            }
            else{
                $result = xuiRejectPaymentIndex($index, 'رد از تلگرام');
                $text = !empty($result['ok'])
                    ? ('⛔ ' . $kind . ' رد شد.')
                    : ('❌ خطا: ' . ($result['error'] ?? 'نامشخص'));
            }
        }
        else{
            if(!function_exists('xuiIsEnabled') || !xuiIsEnabled()){
                $text = "❌ اتوماسیون 3x-ui خاموش است.\nاز پنل ادمین → سرورهای 3x-ui آن را فعال و ذخیره کنید.";
            }
            else{
                $result = xuiApprovePaymentIndex($index, $kind);
                $text = telegramXuiFormatApproveResult($kind, $result);
            }
        }

        $keyboard = telegramXuiResultKeyboard($kind, $index);

        if(function_exists('telegramShowPage')){
            telegramShowPage($chatId, $text, $keyboard, $config);
        }
        elseif(function_exists('telegramSendMessage')){
            telegramSendMessage($chatId, $text, [
                'reply_markup' => $keyboard
            ], $config);
        }

        if(function_exists('telegramAdminRememberMap') && function_exists('telegramAdminBtnBack')){
            telegramAdminRememberMap($chatId, [
                telegramAdminBtnBack() => ['a' => 'payments', 'kind' => $kind],
            ], [
                'screen' => 'xui_result',
                'payment_kind' => $kind,
                'mode' => '',
            ]);
        }
        elseif(function_exists('telegramUpdateSessionScreen')){
            telegramUpdateSessionScreen($chatId, [
                'screen' => 'xui_result',
            ]);
        }

        return true;
    }

    function telegramHandleXuiCallback($data, $chatId, $messageId, $config = null){
        if(!preg_match('/^xui(ok|no):(buy|renew):(\d+)$/', (string)$data, $m)){
            return false;
        }

        $action = $m[1] === 'ok' ? 'ok' : 'no';
        $kind = ($m[2] === 'renew') ? 'تمدید' : 'خرید';
        $index = intval($m[3]);

        if($messageId > 0 && function_exists('telegramEditMessage')){
            $preview = ($action === 'no') ? ('⛔ ' . $kind . ' در حال رد...') : ('✅ ' . $kind . ' در حال تایید...');
            $edited = telegramEditMessage($chatId, $messageId, $preview, [], $config);

            if(!empty($edited['ok']) && function_exists('telegramUpdateSessionScreen')){
                telegramUpdateSessionScreen($chatId, [
                    'screen' => 'xui_result',
                    'screen_message_id' => intval($messageId),
                ]);
            }
        }

        telegramAdminRunXuiAction($chatId, $action, $kind, $index, $config);

        if($messageId > 0 && function_exists('telegramDeleteMessage')){
            telegramDeleteMessage($chatId, $messageId, $config);
        }

        return true;
    }
}
