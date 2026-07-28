<?php

require_once __DIR__ . '/xui_lib.php';

if(!function_exists('telegramXuiActionKeyboard')){

    function telegramXuiActionKeyboard($kind, $index){
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

            // آخرین مورد هم‌نوع همین کاربر به‌عنوان پشتیبان
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

        if(!empty($result['link'])){
            $lines[] = '';
            $lines[] = $result['link'];
        }

        return implode("\n", $lines);
    }

    function telegramHandleXuiCallback($data, $chatId, $messageId, $config = null){
        if(!preg_match('/^xui(ok|no):(buy|renew):(\d+)$/', (string)$data, $m)){
            return false;
        }

        $action = $m[1];
        $kindKey = $m[2];
        $index = intval($m[3]);
        $kind = ($kindKey === 'renew') ? 'تمدید' : 'خرید';
        $backMenu = ($kindKey === 'renew') ? 'menu:renews' : 'menu:buys';

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

        $keyboard = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => 'مشاهده لیست', 'callback_data' => $backMenu],
                    ['text' => 'منوی اصلی', 'callback_data' => 'menu:home']
                ]
            ]
        ], JSON_UNESCAPED_UNICODE);

        if(function_exists('telegramEditMessage')){
            telegramEditMessage($chatId, $messageId, $text, [
                'reply_markup' => $keyboard
            ], $config);
        }
        elseif(function_exists('telegramSendMessage')){
            telegramSendMessage($chatId, $text, [
                'reply_markup' => $keyboard
            ], $config);
        }

        return true;
    }
}
