# KitsuPlay Web

The browser edition of KitsuPlay: anime, Bangla/Hindi films and series in one
dark, fast interface. Plain PHP 8.1+ with no framework, no build step and no
Node on the server — made for Webuzo/shared hosting at
`https://play.robicodes.xyz`.

## What it shares with the Android app

Same Supabase project, so the account, watchlist, watch status and per-episode
progress are the same rows on both sides:

| App table | Web use |
| --- | --- |
| `watchlist` | saved shelf, `media_ref` = `{source}-{kind}-{id}` |
| `watch_progress` | resume position, one row per episode |
| `profiles` | display name and avatar |
| `app_announcements` | announcements page + unread badge |
| `fcm_tokens` | optional browser push token (`platform = 'web'`) |

Library merging mirrors `lib/core/sync/cloud_sync.dart`: newest timestamp
wins, rows without one lose, remote-only rows are added, removals prune.

## Layout

```
index.php            front controller (every request lands here)
.htaccess            rewrites, blocks /app and /storage, adds headers
app/
  config.php         reads app/env.php, defines endpoints
  env.php            live secrets (git-ignored, blocked over HTTP)
  bootstrap.php      session, CSP/security headers, helpers, renderer
  routes.php         every page and API route
  ui.php             card / rail / grid render helpers
  classes/           Http, Cache, Supabase, Auth, Library, Notices,
                     Tmdb, AniList, Mlbd, Content
  api/               search, browse, play, library, notices, push-token,
                     hls/playlist, hls/segment
  pages/             one file per screen
  views/             layout, auth layout, bare layout, SVG sprite
assets/              app.css, app.js, player.js, icons, vendored hls.js
sw.js                offline shell + push notifications
storage/             cache and logs (writable, never web-served)
```

## Setup

1. Upload everything except `storage/cache/*`, `storage/logs/*` and `.git/`.
2. `storage/` must be writable (0755 is enough on most Webuzo accounts).
3. Copy `app/env.example.php` to `app/env.php` and fill in `TMDB_TOKEN`
   (a TMDB **v4 read access token**) plus the Firebase Web push keys if push
   is wanted. Supabase's public URL and publishable key are already there.
4. Point the domain/subfolder at `index.php` and make sure `mod_rewrite` is on.
5. In Supabase → Authentication → URL configuration, add the callback the
   install actually uses:
   `https://play.robicodes.xyz/index.php/auth/callback` (with `PRETTY_URLS`
   on it is `https://play.robicodes.xyz/auth/callback`).

### URLs on a host without rewrites

`play.robicodes.xyz` runs LiteSpeed with `AllowOverride` off, so `.htaccess`
(and with it `mod_rewrite`) is ignored. The front controller therefore also
answers on `PATH_INFO`: `/index.php/browse/movie`, `/index.php/title/…`.
`app/env.php` sets `PRETTY_URLS => '0'`, and every link, redirect, API call,
the service worker and the OAuth callback follow that flag. Set it to `'1'`
after enabling `.htaccess`/rewrites in the panel and the original clean URLs
(`/browse/movie`) come back — the `.htaccess` shipped here is ready for it.

Without a TMDB token the TMDB lanes (Movies, Series, their rails) are skipped
and the app still runs on the MLBD catalogue and AniList.

## Playback

Only the MovieLinkBD lane is wired for the browser: its HLS master is relayed
through `/api/hls/playlist` and `/api/hls/segment` because the CDN requires a
`Referer`, answers `.jpg` files that are really video, and needs closed byte
ranges. Both relays only accept whitelisted media hosts, so they can never be
used as an open proxy. The direct MP4 is offered as the fallback source.
Other providers the app supports are not shipped on web.

## Security

- Supabase access/refresh tokens stay in the PHP session; the browser never
  sees them.
- Session cookie is `HttpOnly`, `SameSite=Lax`, `Secure` on HTTPS, with strict
  session mode and id regeneration on sign-in.
- Every state-changing request needs the session CSRF token (`X-CSRF` header
  or `_csrf` field).
- Strict CSP (no inline scripts), `X-Frame-Options: DENY`, `nosniff`,
  `Referrer-Policy`, HSTS on HTTPS.
- Auth endpoints are throttled; `app/` and `storage/` are blocked by
  `.htaccess` and again by the front controller's routing.
