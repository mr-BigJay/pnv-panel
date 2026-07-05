<?php

require_once __DIR__ . '/auth.php';

header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: public, max-age=3600');

$base = rtrim(PNV_ADMIN_BASE, '/') . '/';

echo json_encode([
    'id' => $base,
    'name' => 'پنل مدیریت تیکتین',
    'short_name' => 'ادمین',
    'description' => 'پنل مدیریت و پیام‌های پشتیبانی',
    'start_url' => $base . '?page=support',
    'scope' => $base,
    'display' => 'standalone',
    'orientation' => 'any',
    'background_color' => '#0f172a',
    'theme_color' => '#111827',
    'lang' => 'fa',
    'dir' => 'rtl',
    'icons' => [
        [
            'src' => $base . 'icons/icon-192.png',
            'sizes' => '192x192',
            'type' => 'image/png',
            'purpose' => 'any'
        ],
        [
            'src' => $base . 'icons/icon-512.png',
            'sizes' => '512x512',
            'type' => 'image/png',
            'purpose' => 'any'
        ],
        [
            'src' => $base . 'icons/icon-512.png',
            'sizes' => '512x512',
            'type' => 'image/png',
            'purpose' => 'maskable'
        ]
    ]
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
