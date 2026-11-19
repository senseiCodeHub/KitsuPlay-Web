# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

Two primary audiences, confirmed with the companion app (same product, same account):

1. **South Asian mobile viewers** (Bangladeshi / Indian / Pakistani) — phone-first, watching free cinema and drama in their leisure time; on web they arrive at `play.robicodes.xyz` on a phone browser.
2. **International anime and Asian-drama fans** — younger viewers following seasonal anime, K-drama and C-drama, on phone and on desktop browser.

## Product Purpose

The browser edition of KitsuPlay: anime, Bangla/Hindi films and series in one dark, fast interface. Success means a viewer opens the site, finds something worth watching within a couple of scrolls, and playback starts without friction — with the same account, watchlist, watch status and per-episode progress as the Android app.

## Positioning

One product that puts South-Asian cinema, anime and Asian drama first instead of the Western / Nollywood-first ordering most streaming front-ends default to. App and web share one Supabase project, so the library and progress follow the viewer across devices.

## Operating Context

- Plain PHP 8.1+ front controller, no framework, no build step, no Node on the server; deployed on Webuzo shared hosting (LiteSpeed, `AllowOverride` off → `PATH_INFO` routing with `PRETTY_URLS => '0'`), developed locally on XAMPP.
- Shared Supabase project for auth, `watchlist`, `watch_progress`, `profiles`, `app_announcements`, `fcm_tokens`; access/refresh tokens stay server-side in the PHP session.
- Content lanes: TMDB (optional v4 token), AniList, MovieLinkBD catalogue. Playback: the MovieLinkBD HLS master is relayed through `/api/hls/playlist` and `/api/hls/segment` because the CDN requires a `Referer`, answers `.jpg` files that are really video, and needs closed byte ranges; relays accept whitelisted media hosts only. Direct MP4 is the fallback source.
- PWA: service-worker offline shell plus web push via Firebase compat SDK.
- Companion Flutter app: https://github.com/senseiCodeHub/KitsuPlay (latest, v1.0.8-era, cloned for reference during the 2026-11 redesign).

## Capabilities and Constraints

- Surfaces: Home, Browse (movie / series / anime), Search, Title detail, Watch (HLS player), Watchlist, History, Notifications, Profile, Settings, Auth (login / register / forgot / verify / OAuth callback), Offline, Maintenance, Error.
- **Scope (confirmed 2026-11):** redesign covers the existing web features with app-like UI. Shorts, Downloads and Reports chat are **not** ported to web.
- **Mobile web must read same-to-same as the Android app** (shell, nav, cards, player chrome); **desktop gets the app's visual language in a desktop-native layout** (persistent nav, wider grids, denser rails, richer detail/watch pages).
- Focus of the redesign: the player, the server-side stream relay, and UI design. Web data lanes stay as they are — a MovieBox-API migration for web is undecided and out of scope unless requested.
- UI copy: English, short and casual. No Bangla script in product UI; developer-facing chat runs in Banglish/English.
- No premium, VIP, unlock or subscription labels anywhere — nothing in the product is gated.
- Security posture is a product constraint: strict CSP (no inline scripts), CSRF on state-changing requests, `HttpOnly` session cookies, throttled auth endpoints, `app/` and `storage/` never web-served.

## Brand Commitments

- Name: **KitsuPlay**. Wordmark reads "Kitsu" in the foreground text colour and "Play" in the accent colour, next to a play-mark drawn in code.
- **Accent (verified against the latest app, v1.0.8, commit `9433e01`): fox orange `#FF6B1A`** with `#FFA04D` / `#E85D00` gradient stops and near-black ink `#1A0E00` on solid accent fills. The Android launcher icon and splash logo shipped orange; the old sky-blue `#54C5F8` era (older PRODUCT.md/docs and the web's blue favicon) is stale and gets replaced, not preserved.
- Interface face: **Fredoka** (400–700) — the app's registered UI family; HindSiliguri ships in-app for Bengali fallback.
- Voice: short, casual, English UI copy; no premium/VIP hue anywhere.
- One visual language must hold across the Android app and the web at every width.

## Evidence on Hand

- App direction contract (the pinned visual world): `KitsuPlay-app/.impeccable/surfaces/lib-main-dart.md` — full token set, density and screen structure.
- App source at `C:\Users\iamro\AppData\Local\Temp\opencode\KitsuPlay-app`, commit `9433e01` (2026-11-09).
- Web architecture, routing, playback and security facts: `README.md` in this repo.
- Web has no DESIGN.md yet; the app's world is the reference authority for this redesign.
- Absent and not to be fabricated: no brand guideline file, no owned artwork (posters come from the APIs), no subscriber/press material.

## Product Principles

1. Playable titles come before decoration — the first screenful must already be watchable content.
2. South-Asian and anime-forward rows lead; Western, Nollywood and adult rows are hidden rather than merely demoted.
3. Playback is the failure mode that matters most, so every UI change keeps the watch path short and the HLS relay solid.
4. One visual language must hold across portrait browsing, landscape playback, and the web from 360px to desktop.
5. Server-side token handling, CSRF and strict CSP are product constraints, not preferences.

## Accessibility & Inclusion

No formal accessibility standard was established. Dark UI is the viewing default, so text and controls must stay legible on a phone in low light — and at desktop widths, with keyboard focus visible on every interactive control.
