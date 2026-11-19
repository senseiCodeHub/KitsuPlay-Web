<?php
/** Web app manifest, rendered so the icons carry the install's own URL. */

declare(strict_types=1);

header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: public, max-age=3600');
echo json_encode([
    'name' => 'KitsuPlay',
    'short_name' => 'KitsuPlay',
    'description' => 'Anime, movies and series — by RobiCodes.',

    'scope' => '/',
    'display' => 'standalone',
    'orientation' => 'any',
    'background_color' => '#0B0B0D',
    'theme_color' => '#0B0B0D',
    'categories' => ['entertainment', 'video'],
    'start_url' => url('home'),
    'icons' => [
        ['src' => asset('icons/icon-192.png'), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => asset('icons/icon-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => asset('icons/icon-maskable.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
    ],
], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
