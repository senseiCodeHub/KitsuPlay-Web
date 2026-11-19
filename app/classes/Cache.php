<?php
/**
 * A small file cache for catalogue responses.
 *
 * TMDB allows a limited number of calls per second and AniList per minute; a
 * page of rails is a dozen calls. Caching on disk means a hundred visitors
 * cost one round trip, and a cached page still renders when a lane is briefly
 * down. Writes are atomic and the store is bounded by age, not size.
 */

declare(strict_types=1);

final class Cache
{
    public static function remember(string $key, int $ttl, callable $loader): mixed
    {
        $file = CACHE_DIR . '/' . hash('sha256', $key) . '.json';
        if (is_file($file)) {
            $raw = @file_get_contents($file);
            $payload = $raw === false ? null : json_decode($raw, true);
            if (is_array($payload) && isset($payload['expires'], $payload['value'])
                && (int) $payload['expires'] > time()) {
                return $payload['value'];
            }
        }

        $value = $loader();
        $tmp = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
        $body = json_encode(['expires' => time() + $ttl, 'value' => $value], JSON_UNESCAPED_SLASHES);
        if ($body !== false && @file_put_contents($tmp, $body, LOCK_EX) !== false) {
            @rename($tmp, $file);
        }
        return $value;
    }

    /** Drops cache entries older than a day; called cheaply on some requests. */
    public static function sweep(): void
    {
        $files = glob(CACHE_DIR . '/*.json') ?: [];
        if (count($files) < 400) {
            return;
        }
        $cutoff = time() - 86400;
        foreach ($files as $file) {
            $modified = @filemtime($file);
            if ($modified !== false && $modified < $cutoff) {
                @unlink($file);
            }
        }
    }
}
