<?php
/**
 * Routes. Pages render server-side; `/api/*` answers JSON for the player,
 * search box and library switches.
 */

declare(strict_types=1);

function route(string $path): void
{
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    // ── API ────────────────────────────────────────────────────────────────
    if (str_starts_with($path, '/api/')) {
        $endpoint = substr($path, 5);
        $file = APP_DIR . '/api/' . $endpoint . '.php';
        if (is_file($file)) {
            require $file;
            return;
        }
        json_out(['ok' => false, 'error' => 'Not found'], 404);
    }

    // ── Static-ish ─────────────────────────────────────────────────────────
    if ($path === '/manifest.json') {
        require APP_DIR . '/pages/manifest.php';
        return;
    }

    // Maintenance mode walls off everything except auth and the notice page.
    if ($method === 'GET' && !in_array($path, ['/login', '/register', '/verify', '/forgot-password', '/auth/callback', '/maintenance', '/offline'], true)) {
        $maintenance = $_SESSION['maintenance_cache'] ?? null;
        if (!is_array($maintenance) || (int) ($maintenance['at'] ?? 0) < time() - 60) {
            $maintenance = ['at' => time(), 'state' => Notices::maintenance()];
            $_SESSION['maintenance_cache'] = $maintenance;
        }
        if (($maintenance['state']['active'] ?? false) === true) {
            http_response_code(503);
            render('maintenance', [
                'title' => 'Under maintenance',
                'message' => $maintenance['state']['message'],
            ], 'bare');
            return;
        }
    }

    // ── Pages ──────────────────────────────────────────────────────────────
    if ($path === '/' || $path === '/home') {
        render('home', ['title' => null]);
        return;
    }

    if (preg_match('#^/browse/(movie|tv|anime|mlbd)$#', $path, $match)) {
        render('browse', ['title' => 'Browse', 'type' => $match[1]]);
        return;
    }

    if ($path === '/discover') {
        render('browse', ['title' => 'Discover', 'type' => 'movie']);
        return;
    }

    if ($path === '/search') {
        render('search', ['title' => 'Search']);
        return;
    }

    if (preg_match('#^/title/([a-z0-9\-]+)$#', $path, $match)) {
        render('title', ['title' => 'Title', 'ref' => $match[1]]);
        return;
    }

    if ($path === '/watch') {
        render('watch', ['title' => 'Watch'], 'bare');
        return;
    }

    if ($path === '/watchlist') {
        render('watchlist', ['title' => 'Watchlist']);
        return;
    }

    if ($path === '/history') {
        render('history', ['title' => 'Watch history']);
        return;
    }

    if ($path === '/notifications') {
        render('notifications', ['title' => 'Announcements']);
        return;
    }

    if ($path === '/profile') {
        if (!Auth::isSignedIn()) {
            redirect(url('login') . '?next=' . rawurlencode('/profile'));
        }
        render('profile', ['title' => 'Profile']);
        return;
    }

    if ($path === '/settings') {
        render('settings', ['title' => 'Settings']);
        return;
    }

    if ($path === '/login') {
        render('login', ['title' => 'Sign in'], 'auth');
        return;
    }

    if ($path === '/register') {
        render('register', ['title' => 'Create account'], 'auth');
        return;
    }

    if ($path === '/verify') {
        render('verify', ['title' => 'Verify email'], 'auth');
        return;
    }

    if ($path === '/forgot-password') {
        render('forgot', ['title' => 'Reset password'], 'auth');
        return;
    }

    if ($path === '/auth/callback') {
        require APP_DIR . '/pages/auth_callback.php';
        return;
    }

    if ($path === '/auth/google') {
        redirect(Auth::googleAuthorizeUrl());
    }

    if ($path === '/logout') {
        require APP_DIR . '/pages/logout.php';
        return;
    }

    if ($path === '/maintenance') {
        $state = Notices::maintenance();
        render('maintenance', [
            'title' => 'Under maintenance',
            'message' => $state['message'],
        ], 'bare');
        return;
    }

    if ($path === '/offline') {
        render('offline', ['title' => 'Offline'], 'bare');
        return;
    }

    http_response_code(404);
    render('error', [
        'title' => 'Page not found',
        'message' => 'That page does not exist.',
    ]);
}
