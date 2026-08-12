<?php

register_shutdown_function(function(){
    $error = error_get_last();

    if(
        !$error
        || !in_array($error['type'], [E_ERROR, E_PARSE, E_COMPILE_ERROR, E_CORE_ERROR], true)
    ){
        return;
    }

    if(!headers_sent()){
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
    }

    echo "\n\n[FATAL ERROR]\n";
    echo $error['message'] . "\n";
    echo $error['file'] . ':' . $error['line'] . "\n";
});

set_error_handler(function($severity, $message, $file, $line){
    if(!(error_reporting() & $severity)){
        return false;
    }

    throw new ErrorException($message, 0, $severity, $file, $line);
});

header('Content-Type: text/plain; charset=utf-8');

function supportPingStep($label, $callback){
    echo $label . ' ... ';

    try{
        $callback();
        echo "OK\n";
        return true;
    }
    catch(Throwable $e){
        echo "FAIL\n";
        echo '  ' . get_class($e) . ': ' . $e->getMessage() . "\n";
        echo '  ' . $e->getFile() . ':' . $e->getLine() . "\n";
        return false;
    }
}

echo "Support ping\n";
echo "============\n\n";

echo "[Environment]\n";
echo 'PHP: ' . PHP_VERSION . "\n";
echo 'SAPI: ' . PHP_SAPI . "\n";
echo 'File: ' . __FILE__ . "\n";
echo 'Script: ' . ($_SERVER['SCRIPT_NAME'] ?? '-') . "\n\n";

$root = dirname(__DIR__);

if(!supportPingStep('[1/8] Load auth.php', function(){
    require_once __DIR__ . '/auth.php';
})){
    exit(1);
}

if(!supportPingStep('[2/8] Admin session', function(){
    if(!pnvAdminIsLoggedIn()){
        http_response_code(403);
        throw new RuntimeException('Not logged in — open this URL while logged into admin panel');
    }
})){
    exit(1);
}

if(!supportPingStep('[3/8] Load support_lib.php', function(){
    require_once __DIR__ . '/../support_lib.php';
})){
    exit(1);
}

if(!supportPingStep('[4/8] Date helpers', function(){
    if(!function_exists('pnvFormatJalaliDate')){
        throw new RuntimeException('pnvFormatJalaliDate() is missing');
    }

    if(!function_exists('pnvFormatTehranTime')){
        throw new RuntimeException('pnvFormatTehranTime() is missing');
    }

    $sample = pnvFormatJalaliDate(time(), '/');

    if($sample === '' || $sample === '-'){
        throw new RuntimeException('pnvFormatJalaliDate() returned empty sample');
    }

    echo '(' . $sample . ') ';
})){
    exit(1);
}

$file = $root . '/db/support.json';
$data = [];

if(!supportPingStep('[5/8] Load support.json', function() use ($file, &$data){
    if(!is_file($file)){
        throw new RuntimeException('Missing ' . $file);
    }

    $data = supportSortTickets(supportLoad($file));
    echo '(' . count($data) . ' tickets) ';
})){
    exit(1);
}

if(!supportPingStep('[6/8] Render sidebar sample', function() use ($data){
    if(is_file(__DIR__ . '/../profile_lib.php')){
        require_once __DIR__ . '/../profile_lib.php';
    }

    $tested = 0;
    $failed = 0;

    foreach($data as $ticket){
        if(!is_array($ticket)){
            continue;
        }

        $user = trim((string)($ticket['user'] ?? ''));

        if($user === ''){
            continue;
        }

        supportTicketPreview($ticket);
        supportRelativeTime(supportTicketLastTimestamp($ticket));
        supportRenderConvAvatarHtml($user);
        $tested++;

        if($tested >= 5){
            break;
        }
    }

    if($tested === 0){
        throw new RuntimeException('No tickets to test');
    }

    echo "({$tested} ok) ";
})){
    exit(1);
}

if(!supportPingStep('[7/8] Render one message', function() use ($data){
    foreach($data as $ticket){
        if(empty($ticket['messages'][0]) || !is_array($ticket['messages'][0])){
            continue;
        }

        $html = supportRenderMessageHtml($ticket['messages'][0], [
            'currentUser' => $ticket['user'] ?? '',
            'embedded' => true,
            'csrfField' => '',
            'editId' => '',
            'isAdmin' => true,
            'baseUrl' => 'index.php?page=support'
        ]);

        if(strlen($html) < 20){
            throw new RuntimeException('Message HTML too short');
        }

        echo '(html ' . strlen($html) . ' bytes) ';
        return;
    }

    throw new RuntimeException('No messages found to render');
})){
    exit(1);
}

if(!supportPingStep('[8/8] PHP lint key files', function() use ($root){
    $files = [
        $root . '/support_lib.php',
        $root . '/pnv_date_bootstrap.php',
        $root . '/date_lib.php',
        __DIR__ . '/support.php',
        __DIR__ . '/support-debug.php',
    ];

    foreach($files as $path){
        if(!is_file($path)){
            throw new RuntimeException('Missing ' . $path);
        }

        $output = [];
        $code = 0;
        exec('php -l ' . escapeshellarg($path) . ' 2>&1', $output, $code);

        if($code !== 0){
            throw new RuntimeException(basename($path) . ': ' . implode(' ', $output));
        }
    }
})){
    exit(1);
}

echo "\nAll checks passed.\n";
echo "If support page still fails, open support-debug.php?probe=0\n";
