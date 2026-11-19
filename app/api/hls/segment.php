<?php
/**
 * GET /api/hls/segment?src=<b64url>
 *
 * Byte-range relay for MovieLinkBD's media. The CDN:
 *   - answers 403 without a Referer,
 *   - answers 416 to a request with no Range,
 *   - answers 500 to an open-ended range.
 * So every request carries a closed range, measured once against the file.
 * Bytes are copied straight through; nothing is buffered in memory.
 */

declare(strict_types=1);

const RELAY_HOSTS = [
    'watch.movielink.tv',
    'fsl.movielink.tv',
    'mlbd.robicodes.xyz',
    'mlbd-scraper.robicodes.xyz',
];

$raw = (string) ($_GET['src'] ?? '');
$src = b64url_decode($raw);
$parts = $src !== '' ? parse_url($src) : false;
$host = strtolower((string) ($parts['host'] ?? ''));
$scheme = strtolower((string) ($parts['scheme'] ?? ''));

if (!$parts || $scheme !== 'https' || !in_array($host, RELAY_HOSTS, true)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Source not allowed';
    exit;
}

$origin = $scheme . '://' . $host;
$baseHeaders = [
    'Referer: ' . $origin . '/',
    'Origin: ' . $origin,
    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
    'Accept: */*',
    'Accept-Encoding: identity',
];

$asked = $_SERVER['HTTP_RANGE'] ?? '';
$range = is_string($asked) ? trim($asked) : '';

// Close an open range (or fill in a missing one) against the real file size.
if ($range === '' || preg_match('/^bytes=\d+-$/', $range)) {
    $size = Http::size($src, $baseHeaders);
    if ($size !== null) {
        if ($range === '') {
            $range = 'bytes=0-' . ($size - 1);
        } else {
            $range = preg_replace('/-$/', '-' . ($size - 1), $range) ?? $range;
        }
    } elseif ($range === '') {
        $range = 'bytes=0-';
    }
}

$curl = curl_init($src);
if ($curl === false) {
    http_response_code(502);
    exit('Upstream unavailable');
}

$status = 0;
$upstream = [];
$sent = false;

curl_setopt_array($curl, [
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS => 3,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT => 120,
    CURLOPT_HTTPHEADER => [...$baseHeaders, 'Range: ' . $range],
    CURLOPT_HEADERFUNCTION => static function ($handle, string $line) use (&$status, &$upstream): int {
        $trimmed = trim($line);
        if (preg_match('#^HTTP/\S+\s+(\d{3})#', $trimmed, $match)) {
            $status = (int) $match[1];
            $upstream = [];
        } elseif (str_contains($trimmed, ':')) {
            [$name, $value] = explode(':', $trimmed, 2);
            $upstream[strtolower(trim($name))] = trim($value);
        }
        return strlen($line);
    },
    CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$status, &$upstream, &$sent): int {
        if (!$sent) {
            $sent = true;
            http_response_code($status >= 400 ? $status : 200);
            $type = $upstream['content-type'] ?? 'application/octet-stream';
            if (str_starts_with($type, 'image/')) {
                // The ladder's files are real video behind a .jpg name.
                $type = 'video/mp4';
            }
            header('Content-Type: ' . $type);
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Expose-Headers: Content-Length, Content-Range, Accept-Ranges, Content-Type');
            header('Accept-Ranges: bytes');
            header('Cache-Control: public, max-age=31536000, immutable');
            if (isset($upstream['content-range'])) {
                header('Content-Range: ' . $upstream['content-range']);
            }
            if (isset($upstream['content-length'])) {
                header('Content-Length: ' . $upstream['content-length']);
            }
            while (ob_get_level() > 0) {
                ob_end_flush();
            }
        }
        echo $chunk;
        flush();
        return strlen($chunk);
    },
]);

curl_exec($curl);
curl_close($curl);
