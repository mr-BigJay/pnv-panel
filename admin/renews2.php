<?php

require_once __DIR__ . '/auth.php';
pnvAdminRequireAuth();

header('Location: ' . pnvAdminUrl('index.php?page=renews'));
exit;
