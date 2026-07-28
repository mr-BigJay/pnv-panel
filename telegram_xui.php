<?php

require_once __DIR__ . '/xui_lib.php';

if(!function_exists('telegramXuiActionKeyboard')){

    function telegramXuiActionKeyboard($kind, $index){
        $prefix = ($kind === 'تمدید') ? 'renew' : 'buy';

        return json_encode([
            'inline_keyboard' => [
                [
                    ['text' => 'تایید خودکار', 'callback_data' => 'xuiok:' . $prefix . ':' . intval($index)],
                    ['text' => 'رد', 'callback_data' => 'xuino:' . $prefix . ':' . intval($index)]
                ],
                [
                    ['text' => 'بازگشت', 'callback_data' => 'menu:home']
                ]
            ]
        ], JSON_UNESCAPED_UNICODE);
    }

    function telegramXuiFindPaymentIndex($username, $created, $kind = ''){
        $rows = xuiLoadPayments();

        foreach($rows as $i => $row){
            if(trim((string)($row[0] ?? '')) !== trim((string)$username)){
                continue;
            }

            if(intval($row[8] ?? 0) !== intval($created)){
                continue;
            }

            $type = trim((string)($row[9] ?? ''));

            if($kind !== '' && $type !== '' && $type !== $kind){
                continue;
            }

            return $i;
        }

        return -1;
    }

    function telegramHandleXuiCallback($data, $chatId, $messageId, $config = null){
        if(!preg_match('/^xui(ok|no):(buy|renew):(\d+)$/', (string)$data, $m)){
            return false;
        }

        $action = $m[1];
        $kindKey = $m[2];
        $index = intval($m[3]);
        $kind = ($kindKey === 'renew') ? 'تمدید' : 'خرید';

        if($action === 'no'){
            $result = xuiRejectPaymentIndex($index, 'رد از تلگرام');
            $text = !empty($result['ok'])
                ? ('⛔ ' . $kind . ' رد شد.')
                : ('خطا: ' . ($result['error'] ?? 'نامشخص'));
        }
        else{
            $result = xuiApprovePaymentIndex($index, $kind);
            $text = !empty($result['ok'])
                ? ("✅ {$kind} تایید شد\n" . ($result['link'] ?? ''))
                : ('❌ خطا: ' . ($result['error'] ?? 'نامشخص'));
        }

        if(function_exists('telegramEditMessage')){
            telegramEditMessage($chatId, $messageId, $text, [
                'reply_markup' => json_encode([
                    'inline_keyboard' => [[['text' => 'بازگشت', 'callback_data' => 'menu:home']]]
                ], JSON_UNESCAPED_UNICODE)
            ], $config);
        }
        elseif(function_exists('telegramSendMessage')){
            telegramSendMessage($chatId, $text, [], $config);
        }

        return true;
    }
}
