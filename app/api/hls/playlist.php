<?php
/**
 * GET /api/hls/playlist?src=<b64url>
 *
 * Relays a playlist and rewrites every URI back through this site, because
 * MovieLinkBD's CDN refuses a request without its Referer and serves its
 * segments as `.jpg`. Only media hosts are allowed — the relay must never
 * become an open proxy (SSRF).
 */

declare(strict_types=1);

const RELAY_HOSTS = [
    'watch.movielink.tv',
    'fsl.movielink.tv',
    'mlbd.robicodes.xyz',
    'mlbd-scraper.robicodes.xyz',
];

function relay_url(string $raw): string
{
    $decoded = b64url_decode($raw);
    if ($decoded === '') {
        json_out(['ok' => false, 'error' => 'Missing source'], 400);
    }
    $parts = parse_url($decoded);
    $host = strtolower((string) ($parts['host'] ?? ''));
    $scheme = strtolower((string) ($parts['scheme'] ?? ''));
    if ($scheme !== 'https' || !in_array($host, RELAY_HOSTS, true)) {
        json_out(['ok' => false, 'error' => 'Source not allowed'], 403);
    }
    return $decoded;
}

function upstream_headers(string $src): array
{
    $parts = parse_url($src);
    $origin = ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? 'watch.movielink.tv');
    return [
        'Referer: ' . $origin . '/',
        'Origin: ' . $origin,
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
        'Accept: */*',
        'Range: bytes=0-',
    ];
}

$src = relay_url((string) ($_GET['src'] ?? ''));
$response = Http::request($src, upstream_headers($src), null, 'GET', 25);
$body = $response['body'];

if ($response['status'] >= 400 || $body === '') {
    http_response_code($response['status'] >= 400 ? $response['status'] : 502);
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store');
    echo 'Playlist unavailable';
    exit;
}

// A caption file (or anything that is not a playlist) passes through.
if (!str_contains($body, '#EXTM3U')) {
    $type = $response['headers']['content-type'] ?? 'text/vtt';
    header('Content-Type: ' . (str_contains($type, 'vtt') ? 'text/vtt; charset=utf-8' : $type));
    header('Cache-Control: public, max-age=300');
    header('Access-Control-Allow-Origin: *');
    echo $body;
    exit;
}

$isMaster = str_contains($body, '#EXT-X-STREAM-INF');
$playlistUrl = url('api/hls/playlist') . '?src=';
$segmentUrl = url('api/hls/segment') . '?src=';

$rewrite = static function (string $uri) use ($src, $isMaster, $playlistUrl, $segmentUrl): string {
    $absolute = $uri;
    if (!preg_match('#^https?://#i', $uri)) {
        $parts = parse_url($src);
        $base = ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '') . '/';
        $dir = rtrim(dirname((string) ($parts['path'] ?? '/')), '/') . '/';
        $absolute = str_starts_with($uri, '/')
            ? $base . ltrim($uri, '/')
            : $base . ltrim($dir . $uri, '/');
    }
    $target = $isMaster ? $playlistUrl : $segmentUrl;
    return $target . b64url_encode($absolute);
};

$lines = preg_split('/\r\n|\r|\n/', $body) ?: [];
$out = [];
foreach ($lines as $line) {
    $trimmed = trim($line);
    if ($trimmed === '') {
        $out[] = '';
        continue;
    }
    if (str_starts_with($trimmed, '#')) {
        if (preg_match('/^#EXT-X-(MEDIA|KEY|SESSION-KEY|MAP|PART):/i', $trimmed)) {
            $out[] = preg_replace_callback(
                '/URI="([^"]*)"/',
                static fn ($match) => 'URI="' . $rewrite($match[1]) . '"',
                $line,
            ) ?? $line;
            continue;
        }
        $out[] = $line;
        continue;
    }
    $out[] = $rewrite($trimmed);
}

header('Content-Type: application/vnd.apple.mpegurl');
header('Cache-Control: no-store');
header('Access-Control-Allow-Origin: *');
echo implode("\n", $out) . "\n";
