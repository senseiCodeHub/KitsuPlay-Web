<?php
/**
 * Copy to `app/env.php` and fill in. `app/env.php` is git-ignored and blocked
 * by .htaccess — it must never be readable over HTTP.
 */

return [
    // Public Supabase values (safe to ship; RLS protects every row).
    'SUPABASE_URL' => 'https://btqzbzniioqlxhvhahbu.supabase.co',
    'SUPABASE_ANON_KEY' => 'sb_publishable_onVNBFgVZG_zAHj7BpDAeA_MCKnvk4j',

    // TMDB credentials — server only. Either a v4 read token (preferred) or
    // a v3 API key; the token is used when both are set.
    'TMDB_TOKEN' => '',
    'TMDB_API_KEY' => '',

    // https://play.robicodes.xyz (no trailing slash)
    'SITE_URL' => 'https://play.robicodes.xyz',

    'MLBD_ORIGIN' => 'https://mlbd.robicodes.xyz',
    'MLBD_SCRAPER_ORIGIN' => 'https://mlbd-scraper.robicodes.xyz',

    // '1' once the host honours .htaccess; '0' keeps links on /index.php/... .
    'PRETTY_URLS' => '0',

    'APP_ENV' => 'production',
    'APP_DEBUG' => '0',

    // Optional Firebase Web push.
    'FIREBASE_API_KEY' => '',
    'FIREBASE_PROJECT_ID' => '',
    'FIREBASE_SENDER_ID' => '',
    'FIREBASE_APP_ID' => '',
    'FIREBASE_VAPID_KEY' => '',
];
