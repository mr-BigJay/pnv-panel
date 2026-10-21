<?php
/**
 * Read-only Tejarat login controller IPC.
 * Authenticated admin POSTs only; transport uses a restricted Unix socket.
 * No banking passwords, OTPs or session cookies enter logs or browser output.
 */
if (!function_exists('pnvTejaratControlSocketPath')) {
    function pnvTejaratControlSocketPath() {
        $s = getenv('PNV_TEJARAT_CONTROL_SOCKET');
        return is_string($s) && $s !== '' ? $s : '/run/pnv-tejarat-control/control.sock';
    }

    function pnvTejaratControlRequest($action) {
        if (!in_array($action, ['status', 'start', 'check'], true)) {
            return ['ok'=>false, 'code'=>'invalid_action', 'state'=>'unknown'];
        }
        $payload = ['action'=>$action];
        if ($action === 'start') {
            $credentials = pnvBankReadTejaratCredentials();
            if ($credentials === null) return ['ok'=>false,'code'=>'credentials_missing','state'=>'unavailable'];
            $payload['username'] = $credentials['username'];
            $payload['password'] = $credentials['password'];
        }
        $socket = pnvTejaratControlSocketPath();
        if (!str_starts_with($socket, '/') || strlen($socket)>100 ||
            str_contains($socket, '..')) {
            return ['ok'=>false,'code'=>'invalid_socket','state'=>'unavailable'];
        }
        $errno = 0; $errstr = '';
        $fd = @stream_socket_client('unix://' . $socket, $errno, $errstr, 1.5);
        if ($fd === false) {
            unset($payload);
            return ['ok'=>false,'code'=>'worker_offline','state'=>'unavailable'];
        }
        stream_set_timeout($fd, 2);
        $raw = json_encode($payload, JSON_UNESCAPED_SLASHES);
        unset($payload, $credentials);
        $ok = is_string($raw) && strlen($raw) <= 4096 && @fwrite($fd, $raw . "\n") === strlen($raw) + 1;
        unset($raw);
        if (!$ok) { fclose($fd); return ['ok'=>false,'code'=>'ipc_failed','state'=>'unavailable']; }
        $reply = fgets($fd, 4096);
        fclose($fd);
        $data = is_string($reply) ? json_decode($reply, true) : null;
        if (!is_array($data) || !is_bool($data['ok'] ?? null) ||
            !is_string($data['state'] ?? null)) {
            return ['ok'=>false,'code'=>'invalid_reply','state'=>'unavailable'];
        }
        $allowedStates = [
            'idle','starting','checking_session','opening_bank',
            'session_verified','session_stale','login_required','awaiting_first_activation',
            'awaiting_bank_login','waiting_bank_auth','manual_login_timeout',
            'untrusted_redirect','browser_error','unavailable'
        ];
        $state = (string)$data['state'];
        return [
            'ok'=>$data['ok'],
            'code'=>in_array($data['code'] ?? '', ['invalid_action','already_running','credentials_missing'], true) ? $data['code'] : '',
            'state'=>in_array($state, $allowedStates, true) ? $state : 'unavailable',
            'updated_at'=>max(0, intval($data['updated_at'] ?? 0)),
        ];
    }

    function pnvTejaratControlMessage($state) {
        $messages = [
            'unavailable'=>'سرویس کنترل بانک نصب یا روشن نیست.',
            'idle'=>'اتصالی آغاز نشده است.',
            'starting'=>'در حال آماده‌سازی مرورگر بانک…',
            'opening_bank'=>'مرورگر بانک روی سرور باز شده است.',
            'checking_session'=>'در حال بررسی نشست ذخیره‌شده…',
            'session_verified'=>'نشست در آخرین بررسی معتبر بود؛ دریافت تراکنش‌ها هنوز باید جداگانه آزمایش شود.',
            'session_stale'=>'نتیجه بررسی نشست قدیمی است؛ بررسی مجدد لازم است.',
            'login_required'=>'ورود مجدد در محیط رسمی بانک لازم است.',
            'awaiting_first_activation'=>'بانک صفحه فعال‌سازی اولیه و پیامک را نمایش می‌دهد؛ تأیید فقط در مرورگر امن سرور.',
            'awaiting_bank_login'=>'صفحه ورود بانک باز است؛ اگر ورود خودکار ممکن نبود، از مرورگر امن سرور ادامه بده.',
            'waiting_bank_auth'=>'منتظر تکمیل احراز هویت توسط بانک در مرورگر سرور…',
            'manual_login_timeout'=>'زمان ورود اولیه به پایان رسید؛ می‌توانی دوباره شروع کنی.',
            'untrusted_redirect'=>'بانک به دامنه‌ای غیرمنتظره منتقل کرد؛ اتصال برای امنیت متوقف شد.',
            'browser_error'=>'راه‌اندازی مرورگر یا بررسی نشست ناموفق بود؛ تنظیمات سرویس/نمایشگر را بررسی کن.',
        ];
        return $messages[(string)$state] ?? $messages['unavailable'];
    }
}
