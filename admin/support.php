<?php

/**
 * Legacy include path — always delegate to Telegram-style support v2.
 */
if(!isset($supportEmbedded)){
    $supportEmbedded = false;
}

require __DIR__ . '/support-v2.php';
