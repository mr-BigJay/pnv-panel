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

try {

require_once __DIR__ . '/auth.php';

if(!pnvAdminIsLoggedIn()){
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

require_once __DIR__ . '/../support_lib.php';

if(is_file(__DIR__ . '/../profile_lib.php')){
    require_once __DIR__ . '/../profile_lib.php';
}

header('Content-Type: text/plain; charset=utf-8');

$root = dirname(__DIR__);
$file = $root . '/db/support.json';
$user = trim($_GET['user'] ?? '');
$runProbe = isset($_GET['probe']) && ($_GET['probe'] === '1' || $_GET['probe'] === 'true');

echo "Support debug (detailed)\n";
echo "========================\n\n";

echo "[Environment]\n";
echo 'PHP: ' . PHP_VERSION . "\n";
echo 'Server time: ' . date('c') . "\n";
echo 'Root: ' . $root . "\n";
echo 'This file: ' . __FILE__ . "\n";
echo 'Script name: ' . ($_SERVER['SCRIPT_NAME'] ?? '-') . "\n\n";

$paths = [
    'admin/support.php' => $root . '/admin/support.php',
    'admin/index.php' => $root . '/admin/index.php',
    'admin/support_lib.php' => $root . '/support_lib.php',
    'support_ui.css' => $root . '/support_ui.css',
    'support_ui.js' => $root . '/support_ui.js',
    'db/support.json' => $file,
    'bigjay/index.php' => $root . '/bigjay_controller/index.php',
    'bigjay/support.php' => $root . '/bigjay_controller/support.php',
];

echo "[Files]\n";
foreach($paths as $label => $path){
    if(!is_file($path)){
        echo $label . ": MISSING\n";
        continue;
    }

    $size = filesize($path);
    $mtime = date('Y-m-d H:i:s', filemtime($path));
    $md5 = md5_file($path);
    echo $label . ": {$size} bytes, {$mtime}, md5={$md5}\n";
}

echo "\n[bigjay_controller/index.php head]\n";
$bjIndex = $root . '/bigjay_controller/index.php';
if(is_file($bjIndex)){
    $head = array_slice(file($bjIndex, FILE_IGNORE_NEW_LINES), 0, 5);
    foreach($head as $line){
        echo '  ' . $line . "\n";
    }
}
else{
    echo "  MISSING — admin panel may use an old copy!\n";
}

echo "\n[Data load]\n";
$data = supportSortTickets(supportLoad($file));
echo 'supportLoad tickets: ' . count($data) . "\n";
echo 'support.json bytes: ' . (is_file($file) ? filesize($file) : 0) . "\n";

$emptyUsers = 0;
$totalMessages = 0;
$brokenMessages = 0;

foreach($data as $ticket){
    if(!is_array($ticket)){
        continue;
    }

    $u = trim((string)($ticket['user'] ?? ''));

    if($u === ''){
        $emptyUsers++;
        continue;
    }

    $msgs = is_array($ticket['messages'] ?? null) ? $ticket['messages'] : [];
    $totalMessages += count($msgs);

    foreach($msgs as $msg){
        if(!is_array($msg)){
            $brokenMessages++;
            continue;
        }

        if(supportExtractMessageText($msg) === '' && empty($msg['image'])){
            $brokenMessages++;
        }
    }
}

echo "Tickets without username: {$emptyUsers}\n";
echo "Total messages: {$totalMessages}\n";
echo "Messages without text/image: {$brokenMessages}\n";

echo "\n[List render simulation]\n";
$rendered = 0;
$errors = [];

foreach($data as $i => $ticket){
    if(!is_array($ticket)){
        continue;
    }

    $ticketUser = trim((string)($ticket['user'] ?? ''));

    if($ticketUser === ''){
        continue;
    }

    try{
        supportAdminUnreadCount($ticket);
        supportTicketPreview($ticket);
        supportTicketLastTimestamp($ticket);
        supportRelativeTime(supportTicketLastTimestamp($ticket));
        supportAdminUrl($ticketUser, true);
        supportSafeHtml($ticketUser);
        supportSafeHtml(supportTicketPreview($ticket));
        supportRenderConvAvatarHtml($ticketUser);
        $rendered++;
    }
    catch(Throwable $e){
        $errors[] = [
            'index' => $i,
            'user' => $ticketUser,
            'error' => $e->getMessage(),
            'file' => $e->getFile() . ':' . $e->getLine()
        ];
    }
}

echo "Simulated sidebar items: {$rendered}\n";
echo "Render errors: " . count($errors) . "\n";

if(count($errors) > 0){
    echo "\nFirst errors:\n";
    foreach(array_slice($errors, 0, 10) as $err){
        echo "  #{$err['index']} {$err['user']}: {$err['error']} ({$err['file']})\n";
    }
}

echo "\nTop 10 tickets (by last activity):\n";
foreach(array_slice($data, 0, 10) as $ticket){
    $u = $ticket['user'] ?? '?';
    $preview = supportTicketPreview($ticket);
    $msgCount = is_array($ticket['messages'] ?? null) ? count($ticket['messages']) : 0;
    $previewShort = function_exists('mb_substr')
        ? mb_substr($preview, 0, 40)
        : substr($preview, 0, 40);
    echo "  - {$u} ({$msgCount} msgs) preview: " . $previewShort . "\n";
}

if($user !== ''){
    echo "\n[Chat test: {$user}]\n";
    $found = false;

    foreach($data as $ticket){
        if(($ticket['user'] ?? '') !== $user){
            continue;
        }

        $found = true;
        $msgs = is_array($ticket['messages'] ?? null) ? $ticket['messages'] : [];
        echo "Messages in ticket: " . count($msgs) . "\n";

        foreach($msgs as $j => $msg){
            if(!is_array($msg)){
                echo "  msg#{$j}: INVALID (not array)\n";
                continue;
            }

            $text = supportExtractMessageText($msg);
            $htmlLen = 0;
            $err = '';

            try{
                $html = supportRenderMessageHtml($msg, [
                    'currentUser' => $user,
                    'embedded' => true,
                    'csrfField' => '',
                    'editId' => '',
                    'isAdmin' => true,
                    'baseUrl' => supportAdminUrl($user, true)
                ]);
                $htmlLen = strlen($html);
            }
            catch(Throwable $e){
                $err = $e->getMessage();
            }

            echo "  msg#{$j} sender=" . ($msg['sender'] ?? '?')
                . " textLen=" . strlen($text)
                . " htmlLen={$htmlLen}";

            if($err !== ''){
                echo " ERROR={$err}";
            }

            echo "\n";
        }

        break;
    }

    if(!$found){
        echo "User not found in support.json\n";
    }
}

echo "\n[Live HTML probe]\n";

if(!$runProbe){
    echo "Skipped (add ?probe=1 to run — can trigger heavy render).\n";
}
else{

ob_start();
$supportEmbedded = true;
$supportActionResult = [
    'data' => $data,
    'redirect' => null,
    'error' => null
];
$includeOk = true;

try{
    include __DIR__ . '/support.php';
}
catch(Throwable $e){
    $includeOk = false;
    echo "Include support.php FAILED: " . $e->getMessage() . "\n";
}

$html = ob_get_clean();

if($includeOk){
    $convCount = preg_match_all('/class="msgConv\b/', $html);
    $sidebarCount = preg_match('/class="msgSidebarCount">(\d+)</', $html, $m) ? intval($m[1]) : -1;
    $msgRows = preg_match_all('/class="msgRow\b/', $html);

    echo "include support.php: OK\n";
    echo "HTML bytes: " . strlen($html) . "\n";
    echo "msgSidebarCount in HTML: {$sidebarCount}\n";
    echo "msgConv in HTML: {$convCount}\n";
    echo "msgRow in HTML: {$msgRows}\n";

    if($sidebarCount !== $rendered || $convCount !== $rendered){
        echo "\n⚠ MISMATCH: PHP data={$rendered} but HTML conv={$convCount} countBadge={$sidebarCount}\n";
        echo "  → Likely PHP aborted mid-render OR wrong support.php is loaded.\n";
    }
    else{
        echo "\n✓ Data count matches HTML output.\n";
        echo "  If browser still shows 2 items, hard refresh (Ctrl+F5) or old cached CSS/JS.\n";
    }
}

}

echo "\n[Checks]\n";
$checks = [];

$checks[] = [
    'pnvFormatJalaliDate',
    function_exists('pnvFormatJalaliDate'),
    'date_lib.php / pnv_date_bootstrap.php missing — run deploy'
];

$checks[] = [
    'bigjay index stub',
    is_file($bjIndex) && strpos((string)file_get_contents($bjIndex), '/admin/index.php') !== false,
    'bigjay_controller/index.php should require admin/index.php'
];

$checks[] = [
    'support_lib has merge',
    function_exists('supportImportSnapshot'),
    'support_lib.php on server is outdated'
];

$checks[] = [
    'support_lib has extract text',
    function_exists('supportExtractMessageText'),
    'supportExtractMessageText missing — deploy support_lib.php'
];

$css = is_file($root . '/support_ui.css') ? (string)file_get_contents($root . '/support_ui.css') : '';
$checks[] = [
    'CSS has sidebar count',
    strpos($css, 'msgSidebarCount') !== false,
    'support_ui.css outdated'
];

foreach($checks as [$name, $ok, $hint]){
    echo ($ok ? '✓' : '✗') . " {$name}";
    if(!$ok){
        echo " — {$hint}";
    }
    echo "\n";
}

echo "\nUsage:\n";
echo "- Chat test: ?user=Vahid1996\n";
echo "- Diagnose: support-diagnose.php\n";
echo "- Quick ping: support-ping.php\n";

}
catch(Throwable $e){
    if(!headers_sent()){
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
    }

    echo "\n[EXCEPTION]\n";
    echo get_class($e) . ': ' . $e->getMessage() . "\n";
    echo $e->getFile() . ':' . $e->getLine() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
}
