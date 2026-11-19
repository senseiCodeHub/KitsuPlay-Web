<?php
/** The signed-in chrome: sidebar on desktop, bottom tabs on mobile. */
$path = request_path();
$user = Auth::user();
$profile = $user ? Auth::profile() : null;
$displayName = trim((string) ($profile['name'] ?? ''));
if ($displayName === '') {
    $displayName = (string) ($user['email'] ?? '');
    $displayName = $displayName !== '' ? explode('@', $displayName)[0] : 'Sign in';
}
$avatar = (string) ($profile['avatar_url'] ?? '');
$initials = strtoupper(mb_substr($displayName, 0, 2));
$unread = Notices::unreadCount();
$nav = [
    ['/', 'Home', 'home'],
    ['/browse/movie', 'Movies', 'film'],
    ['/browse/tv', 'Series', 'tv'],
    ['/browse/anime', 'Anime', 'sparkles'],
    ['/discover', 'Discover', 'compass'],
];
$libraryNav = [
    ['/watchlist', 'Watchlist', 'bookmark'],
    ['/history', 'History', 'history'],
    ['/notifications', 'Notifications', 'bell'],
    ['/profile', 'Profile', 'user'],
];
$isActive = static function (string $href) use ($path): bool {
    return $href === '/' ? $path === '/' : ($path === $href || str_starts_with($path, $href . '/'));
};
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title><?= $title ? e($title) . ' · KitsuPlay' : 'KitsuPlay — Anime, Movies & Series' ?></title>
    <meta name="description" content="Stream anime, movies and series in your browser with your KitsuPlay account.">
    <meta name="theme-color" content="#0B0B0D">
    <meta name="csrf" content="<?= e(csrf_token()) ?>">
    <link rel="manifest" href="<?= e(url('manifest.json')) ?>">
    <link rel="icon" href="<?= e(asset('icons/favicon.svg')) ?>" type="image/svg+xml">
    <link rel="apple-touch-icon" href="<?= e(asset('icons/icon-192.png')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body data-path="<?= e($path) ?>" data-base="<?= e(api_prefix()) ?>" data-signed-in="<?= $user ? '1' : '0' ?>">
<?php require APP_DIR . '/views/icons.php'; ?>

<aside class="sidebar">
    <a class="brand" href="<?= e(url('/')) ?>">
        <span class="brand__mark">K</span>
        <span class="brand__name">Kitsu<span>Play</span></span>
    </a>

    <nav class="nav" aria-label="Main">
        <?php foreach ($nav as [$href, $label, $name]): ?>
            <a class="nav__link<?= $isActive($href) ? ' is-active' : '' ?>" href="<?= e(url($href)) ?>">
                <?= icon($name) ?><span><?= e($label) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <p class="nav__label">Your library</p>
    <nav class="nav" aria-label="Library">
        <?php foreach ($libraryNav as [$href, $label, $name]): ?>
            <a class="nav__link<?= $isActive($href) ? ' is-active' : '' ?>" href="<?= e(url($href)) ?>">
                <?= icon($name) ?><span><?= e($label) ?></span>
                <?php if ($href === '/notifications' && $unread > 0): ?>
                    <em class="nav__badge"><?= $unread ?></em>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="sidebar__foot">
        <a class="nav__link<?= $isActive('/settings') ? ' is-active' : '' ?>" href="<?= e(url('/settings')) ?>">
            <?= icon('settings') ?><span>Settings</span>
        </a>
        <a class="account" href="<?= e(url($user ? '/profile' : '/login')) ?>">
            <span class="avatar avatar--sm">
                <?php if ($avatar !== ''): ?>
                    <img src="<?= e($avatar) ?>" alt="" referrerpolicy="no-referrer">
                <?php else: ?>
                    <?= e($initials) ?>
                <?php endif; ?>
            </span>
            <span class="account__text">
                <strong><?= e($displayName) ?></strong>
                <small><?= $user ? 'Synced account' : 'Local library' ?></small>
            </span>
        </a>
    </div>
</aside>

<header class="topbar">
    <a class="brand brand--sm" href="<?= e(url('/')) ?>">
        <span class="brand__mark">K</span>
        <span class="brand__name">Kitsu<span>Play</span></span>
    </a>
    <div class="topbar__actions">
        <a class="searchpill" href="<?= e(url('/search')) ?>" aria-label="Search"><?= icon('search') ?><span>Search</span></a>
        <a class="iconbtn" href="<?= e(url('/notifications')) ?>" aria-label="Notifications">
            <?= icon('bell') ?>
            <?php if ($unread > 0): ?><span class="dot"></span><?php endif; ?>
        </a>
        <a class="avatar avatar--sm" href="<?= e(url($user ? '/profile' : '/login')) ?>" aria-label="Profile">
            <?php if ($avatar !== ''): ?>
                <img src="<?= e($avatar) ?>" alt="" referrerpolicy="no-referrer">
            <?php else: ?>
                <?= e($initials) ?>
            <?php endif; ?>
        </a>
    </div>
</header>

<main class="main">
    <?= $content ?>
</main>

<nav class="tabbar" aria-label="Tabs">
    <?php foreach ([
        ['/', 'Home', 'home'],
        ['/browse/movie', 'Movies', 'film'],
        ['/browse/anime', 'Anime', 'sparkles'],
        ['/browse/tv', 'Series', 'tv'],
        ['/search', 'Search', 'search'],
    ] as [$href, $label, $name]): ?>
        <a class="tabbar__link<?= $isActive($href) ? ' is-active' : '' ?>" href="<?= e(url($href)) ?>">
            <?= icon($name) ?><span><?= e($label) ?></span>
        </a>
    <?php endforeach; ?>
</nav>

<script src="<?= e(asset('js/app.js')) ?>" defer></script>
<?php if (!empty($needsPlayer)): ?>
    <script src="<?= e(asset('vendor/hls.min.js')) ?>" defer></script>
    <script src="<?= e(asset('js/player.js')) ?>" defer></script>
<?php endif; ?>
</body>
</html>
