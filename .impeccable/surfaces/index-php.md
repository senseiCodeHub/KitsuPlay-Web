---
version: 1
slug: "index-php"
primary_target: "index.php"
related_targets: ["app/views/layout.php","assets/css/app.css","app/pages/home.php","app/pages/watch.php","app/ui.php","assets/js/app.js","assets/js/player.js","app/views/icons.php"]
---

# KitsuPlay Web — streaming front-end redesign (shell, Home, Browse, Search, Title, Watch player, Library, Notices, Profile, Settings)

Scope: the whole web surface set of the browser edition — mobile (<1024px) reads same-to-same as the Android app, desktop gets a desktop-native re-composition of the same world. Mode: Operate. Build path: code-led (an Operate surface is code-led by contract; no comp round).

Audience: South-Asian mobile viewers plus international anime / K-drama / C-drama fans, on a phone browser and on a desktop browser at `play.robicodes.xyz`.
Job: find and start a title. Action: Watch, within three taps on mobile.
Proof/content: real poster art and metadata from the web's own lanes (TMDB / AniList / MovieLinkBD); rails are Continue Watching plus the curated home rows.
Constraints: every existing web feature keeps working (email + Google auth, watchlist, watch status, resume/progress, history, notices + unread badge, web push, PWA/offline, settings, HLS relay player with quality/subtitles/audio/speed/PiP/fullscreen); no Shorts / Downloads / Reports port; strict CSP (no inline scripts), CSRF, PATH_INFO routing with `PRETTY_URLS=0`; English copy; backend/data lanes unchanged.

Chosen direction: **user-pinned — inherit the latest Android app's visual world** (repo `senseiCodeHub/KitsuPlay` at commit `9433e01`, v1.0.8): fox-orange accent, Fredoka interface face, near-black surface stack, stadium controls, 64px indicator nav — re-architected for desktop. A pinned direction beats the roll by contract; no concept-seed roll ran, so the mode file ([mode-operate.md]) governs: the world lends type, palette, density and one signature move; navigation and controls stay standard web components.
Memorable moment: the near-black home where the orange **WATCH NOW** stadium CTA sits on a rounded, edge-peeking hero above reel-meter dots — and at 1440px the identical world runs off a persistent band sidebar with denser shelves, so the browser never stops looking like the app.

## Direction contract

THESIS: this is the app, in a browser. One visual language — near-black ground, one committed fox-orange accent, Fredoka carrying every text role, poster art the only other colour — reads the same at 360px and at 1440px. The desktop layout earns its extra width with structure (persistent sidebar, wider shelves, richer title/watch pages), never with a second identity. It refuses the incumbent slate-navy/cyan pill skin outright, and refuses a desktop redesign that stops matching the app.

OWN-WORLD: ground `#0B0B0D`, band `#111113` (tab bar, top bar, sidebar), surface `#161618`, raised `#1E1E21`, hairline `#2A2A2E`, edge `rgba(255,255,255,.10)`; committed accent `#FF6B1A` with the gradient stops `#E85D00 → #FF6B1A → #FFA04D`, on-accent ink `#1A0E00`, accent tint fill `rgba(255,107,26,.12–.16)`; danger `#E5484D`; text `#F5F7FA` / `#A8ADB5` / `#767D86`. Fredoka 400/500/600/700, self-hosted from `assets/fonts/`, every role; clocks, counts and percentages in tabular figures. Radii: 6 chips/badges, 12 posters/stills, 14 secondary tiles, 18 hero/feature cards, 22 inputs and sheet tops, full stadium for buttons, chips and nav pills. Density: 14px gutters on mobile, poster shelf cards 116px wide inside 236px rows with 10px gaps, section headers plain 16.5/600 with no plate and no rule, section inset 14px / top 20px; tab band 64px tall with 21px icons, 11/600 labels, active = accent icon+label with a 3px accent indicator; buttons stadium h46 (Watch CTA h50), inputs stadium r22; poster press feedback scale .96 / 130ms.

STORY: the viewer lands on the app's home — top bar with the KITSU**PLAY** wordmark, search pill, bell and avatar; a rounded hero carrying fact pills, WATCH NOW and DETAILS; Continue Watching; then poster shelves — taps a poster, taps Watch, and playback starts on the relayed HLS player whose seek bar, transport and menus speak the same orange chrome. If the network dies the offline shell answers. At desktop width the same story runs from the persistent sidebar: same tokens, same components, denser shelves and grids, keyboard-visible focus.

FIRST VIEWPORT (mobile, 390px): top bar on `band` — wordmark left; right a stadium search pill (h38, r19) linking to Search, a 35×35 round bell with unread dot, and a 15px-radius avatar. Below it the hero at feature radius 18 with edge peek (~280px tall): backdrop art, bottom scrim into `#0B0B0D`, fact pills (star in accent), title, the orange WATCH NOW stadium with ink label plus an outline DETAILS, and reel-meter dots. First section header **Continue Watching** (16.5/600) over its shelf, then the next section header and its 116px poster cards — each poster with a bottom scrim, title beneath, star meta. Fixed bottom band 64px: HOME / MOVIES / ANIME / SERIES / SEARCH, active in `#FF6B1A` with the 3px top indicator.
FIRST VIEWPORT (desktop, 1440px): persistent 232px `band` sidebar with hairline right edge — wordmark block, main nav (Home, Movies, Anime, Series, Discover, Search) with accent-active links, a "Your library" group (Watchlist, History, Notices with unread badge, Profile) and the account/settings foot; content area leads with the same hero at feature radius (wider, overview visible), rail arrows on hover, and shelves that resolve into 5–6 column grids — same cards, same tokens, more of them in view.

FORM: the latest app's own layout language, translated to web components in the app's vocabulary (the user pinned it; position: the only candidate — no ordered list, no seed key, because no roll ran). The earned, non-app additions are the desktop sidebar/grid re-composition and web-native affordances (hover states, keyboard focus, rail arrows); every one of them uses the app's tokens, radii and type.

FINISH: unreviewed and undocumented is unfinished; this build ends with the finish review, the verdict, DESIGN.md, and every shipping raster carrying its provenance.

Unresolved: web keeps its own data lanes (TMDB / AniList / MovieLinkBD) — no MovieBox API migration in this round; bottom-nav tab 5 stays Search (Downloads is not ported); web icon PNGs regenerate to the orange mark from the stale blue set.
