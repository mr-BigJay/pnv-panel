<?php

$root = getenv('ROOT') ?: dirname(__DIR__);
chdir($root);

require_once $root . '/reserved_renewal_lib.php';

$result = reservedRenewalsActivateDue(50);

echo 'activated=' . intval($result['activated'] ?? 0) . PHP_EOL;

if(!empty($result['errors'])){
    foreach($result['errors'] as $error){
        fwrite(STDERR, $error . PHP_EOL);
    }
}
