<?php
/**
 * Boot: configuration, session hardening, security headers, helpers and the
 * small amount of autoloading this app needs.
 */

declare(strict_types=1);

require __DIR__ . '/config.php';

foreach ([
    'Http',
    'Cache',
    'Supabase',
    'Auth',
    'Library',
    'Notices',
    'Tmdb',
    'AniList',
    'Mlbd',
    'Content',
] as $class) {
    require __DIR__ . '/classes/' . $class . '.php';
}

ini_set('display_errors', APP_DEBUG ? '1' : '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

foreach ([CACHE_DIR, LOG_DIR] as $dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
}

// ── Session ────────────────────────────────────────────────────────────────
$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';

if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.gc_maxlifetime', '1209600');
    session_name('KITSUPLAYSESS');
    session_set_cookie_params([
        'lifetime' => 1209600,
        'path' => '/',
        'secure' => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

if (!isset($_SESSION['csrf']) || !is_string($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

// ── Security headers ───────────────────────────────────────────────────────
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');
header('Cross-Origin-Opener-Policy: same-origin');
header(
    "Content-Security-Policy: default-src 'self'; "
    . "img-src 'self' https: data:; "
    . "media-src 'self' blob:; "
    . "script-src 'self'; "
    . "style-src 'self' 'unsafe-inline'; "
    . "font-src 'self' data:; "
    . "connect-src 'self'; "
    . "worker-src 'self' blob:; "
    . "manifest-src 'self'; "
    . "frame-ancestors 'none'; base-uri 'self'; form-action 'self'; object-src 'none'"
);
if ($https) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}

// ── Helpers ────────────────────────────────────────────────────────────────

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** The install directory, '' at the domain root, '/sub' inside a subfolder. */
function base_path(): string
{
    return rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
}

function url(string $path = '/'): string
{
    $path = '/' . trim($path, '/');
    $base = base_path();
    if ($path === '/') {
        return $base === '' ? '/' : $base . '/';
    }
    return $base . (PRETTY_URLS ? $path : '/index.php' . $path);
}

/** Turns an internal path (old or new style) into the right install URL. */
function local_url(string $path): string
{
    $path = parse_url($path, PHP_URL_PATH) ?: '/';
    if (str_starts_with($path, '/index.php')) {
        $path = substr($path, strlen('/index.php')) ?: '/';
    }
    return url($path);
}

/** Where browsers send API calls: '' with rewrites, '/index.php' without. */
function api_prefix(): string
{
    return base_path() . (PRETTY_URLS ? '' : '/index.php');
}

function asset(string $path): string
{
    $full = ROOT_DIR . '/assets/' . ltrim($path, '/');
    $version = is_file($full) ? (string) filemtime($full) : '1';
    return base_path() . '/assets/' . ltrim($path, '/') . '?v=' . $version;
}

function request_path(): string
{
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    $path = parse_url($uri, PHP_URL_PATH) ?: '/';
    $path = rawurldecode($path);
    // Strip the install directory when the app lives in a subfolder.
    $base = base_path();
    if ($base !== '' && $base !== '/' && str_starts_with($path, $base)) {
        $path = substr($path, strlen($base)) ?: '/';
    }
    // No rewrites? The front controller answers on /index.php/<route>.
    if (str_starts_with($path, '/index.php')) {
        $path = substr($path, strlen('/index.php')) ?: '/';
    }
    return '/' . trim(preg_replace('#/+#', '/', $path) ?? '/', '/');
}

function wants_json(): bool
{
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    return str_contains($accept, 'application/json')
        || str_starts_with(request_path(), '/api/')
        || ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
}

function json_out(array $body, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function redirect(string $to, int $status = 303): never
{
    header('Location: ' . $to, true, $status);
    exit;
}

function csrf_token(): string
{
    return (string) $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

/** Verifies a form token or the `X-CSRF` header. */
function csrf_verify(): void
{
    $sent = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF'] ?? '');
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        if (wants_json()) {
            json_out(['ok' => false, 'error' => 'Your session expired. Reload the page.'], 419);
        }
        http_response_code(419);
        render('error', [
            'title' => 'Session expired',
            'message' => 'The page went stale. Reload and try again.',
        ]);
        exit;
    }
}

function flash(string $key, ?string $value = null): ?string
{
    if ($value !== null) {
        $_SESSION['flash'][$key] = $value;
        return null;
    }
    $message = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return is_string($message) ? $message : null;
}

function old(string $key, string $fallback = ''): string
{
    $value = $_SESSION['old'][$key] ?? $fallback;
    return is_string($value) ? $value : $fallback;
}

function remember_old(array $values): void
{
    $_SESSION['old'] = array_map(
        static fn ($value) => is_string($value) ? $value : '',
        $values,
    );
}

function forget_old(): void
{
    unset($_SESSION['old']);
}

function log_error(Throwable $error): void
{
    $line = sprintf(
        "[%s] %s: %s in %s:%d\n%s\n",
        date('c'),
        $error::class,
        $error->getMessage(),
        $error->getFile(),
        $error->getLine(),
        $error->getTraceAsString(),
    );
    @file_put_contents(LOG_DIR . '/app.log', $line, FILE_APPEND | LOCK_EX);
}

/** Renders a page inside the chrome, or a bare auth/player layout. */
function render(string $template, array $data = [], ?string $layout = null): void
{
    $layout ??= 'layout';
    extract($data, EXTR_SKIP);
    ob_start();
    require APP_DIR . '/pages/' . $template . '.php';
    $content = ob_get_clean();
    require APP_DIR . '/views/' . $layout . '.php';
}

function client_ip(): string
{
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

/** Crude per-session/IP throttle for auth attempts. */
function throttle(string $bucket, int $max, int $seconds): void
{
    $key = 'throttle_' . $bucket;
    $now = time();
    $attempts = $_SESSION[$key] ?? [];
    $attempts = array_values(array_filter(
        is_array($attempts) ? $attempts : [],
        static fn ($stamp) => is_int($stamp) && $stamp > $now - $seconds,
    ));
    if (count($attempts) >= $max) {
        $wait = max(0, $seconds - ($now - $attempts[0]));
        throw new RuntimeException(
            'Too many attempts. Try again in ' . max(1, (int) ceil($wait / 60)) . ' minute(s).',
        );
    }
    $attempts[] = $now;
    $_SESSION[$key] = $attempts;
}

require __DIR__ . '/ui.php';
require __DIR__ . '/routes.php';
