<?php
/**
 * Configuration. Values come from `app/env.php` (never committed) and fall back
 * to the public KitsuPlay endpoints the Android app ships with.
 */

declare(strict_types=1);

$envFile = __DIR__ . '/env.php';
$env = is_file($envFile) ? require $envFile : [];
if (!is_array($env)) {
    $env = [];
}

function cfg(string $key, string $fallback = ''): string
{
    global $env;
    $value = $env[$key] ?? getenv($key);
    if ($value === false || $value === null) {
        return $fallback;
    }
    return trim((string) $value);
}

define('SUPABASE_URL', rtrim(cfg('SUPABASE_URL', 'https://btqzbzniioqlxhvhahbu.supabase.co'), '/'));
define('SUPABASE_ANON_KEY', cfg('SUPABASE_ANON_KEY', 'sb_publishable_onVNBFgVZG_zAHj7BpDAeA_MCKnvk4j'));
define('TMDB_TOKEN', cfg('TMDB_TOKEN', ''));
define('TMDB_API_KEY', cfg('TMDB_API_KEY', ''));
define('MLBD_ORIGIN', rtrim(cfg('MLBD_ORIGIN', 'https://mlbd.robicodes.xyz'), '/'));
define('MLBD_SCRAPER_ORIGIN', rtrim(cfg('MLBD_SCRAPER_ORIGIN', 'https://mlbd-scraper.robicodes.xyz'), '/'));

/** Where this install lives; used for OAuth redirects and canonical links. */
function site_url(): string
{
    $configured = cfg('SITE_URL');
    if ($configured !== '') {
        return rtrim($configured, '/');
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    $host = $_SERVER['HTTP_HOST'] ?? 'play.robicodes.xyz';
    return ($https ? 'https' : 'http') . '://' . $host . base_path();
}

/**
 * Clean URLs need Apache/LiteSpeed to honour `.htaccess`. When the host has
 * AllowOverride switched off (as play.robicodes.xyz does), links go through
 * `/index.php/...` via PATH_INFO instead — same routes, no rewrite needed.
 * Set PRETTY_URLS=1 in app/env.php once rewrites work.
 */
define('PRETTY_URLS', cfg('PRETTY_URLS', '0') === '1');

define('APP_ENV', cfg('APP_ENV', 'production'));
define('APP_DEBUG', cfg('APP_DEBUG', APP_ENV === 'local' ? '1' : '0') === '1');

/** Optional Firebase Web push; without it the bell explains what to set up. */
define('FIREBASE_CONFIG', [
    'apiKey' => cfg('FIREBASE_API_KEY'),
    'projectId' => cfg('FIREBASE_PROJECT_ID'),
    'messagingSenderId' => cfg('FIREBASE_SENDER_ID'),
    'appId' => cfg('FIREBASE_APP_ID'),
    'vapidKey' => cfg('FIREBASE_VAPID_KEY'),
]);

define('PUSH_CONFIGURED', FIREBASE_CONFIG['apiKey'] !== ''
    && FIREBASE_CONFIG['projectId'] !== ''
    && FIREBASE_CONFIG['appId'] !== ''
    && FIREBASE_CONFIG['vapidKey'] !== '');

define('CACHE_DIR', ROOT_DIR . '/storage/cache');
define('LOG_DIR', ROOT_DIR . '/storage/logs');
