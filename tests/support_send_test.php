<?php

require_once __DIR__ . '/../support_lib.php';

$failures = 0;

function supportAssertTrue($condition, $message){
    global $failures;

    if(!$condition){
        echo "FAIL: {$message}\n";
        $failures++;
        return;
    }

    echo "OK: {$message}\n";
}

session_start();
$_POST = ['edit_id' => ''];
supportAssertTrue(supportIsEditRequest() === false, 'empty edit_id is not treated as edit');

$_POST = ['edit_id' => 'abc123'];
supportAssertTrue(supportIsEditRequest() === true, 'non-empty edit_id is treated as edit');

$_POST = ['edit_id' => '   '];
supportAssertTrue(supportIsEditRequest() === false, 'whitespace edit_id is not treated as edit');

if($failures > 0){
    fwrite(STDERR, "\n{$failures} test(s) failed.\n");
    exit(1);
}

echo "\nAll support send guard tests passed.\n";
