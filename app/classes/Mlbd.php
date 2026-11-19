<?php
/**
 * MovieLinkBD via the project's own API.
 *
 * `/api/v2` is the catalogue (search, collections, detail); the scraper's
 * `/v1/title/{slug}` is the record that carries each episode's HLS master.
 * Both are server-to-server here, so the browser never talks to them and their
 * missing CORS headers do not matter.
 */

declare(strict_types=1);

final class Mlbd
{
    private const HEADERS = [
        'Accept: application/json',
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
    ];

    /** @return array<string,mixed> */
    private static function v2(string $path, array $params = [], int $ttl = 600): array
    {
        $query = http_build_query(array_filter(
            $params,
            static fn ($value) => $value !== '' && $value !== null && $value !== false,
        ));
        $url = MLBD_ORIGIN . '/api/v2' . $path . ($query !== '' ? '?' . $query : '');
        return Cache::remember('mlbd:' . $path . ':' . $query, $ttl, static function () use ($url) {
            $result = Http::json($url, [
                ...self::HEADERS,
                'Referer: ' . MLBD_ORIGIN . '/',
            ]);
            if (($result['success'] ?? false) !== true || !is_array($result['data'] ?? null)) {
                throw new RuntimeException((string) ($result['error'] ?? 'MLBD request failed.'));
            }
            return $result['data'];
        });
    }

    public static function titleV1(string $slug): ?array
    {
        $url = MLBD_SCRAPER_ORIGIN . '/v1/title/' . rawurlencode($slug);
        try {
            return Cache::remember('mlbd-v1:' . $slug, 300, static function () use ($url) {
                $result = Http::json($url, [
                    ...self::HEADERS,
                    'Referer: ' . MLBD_ORIGIN . '/',
                ], null, 'GET', 25);
                return ($result['ok'] ?? false) && is_array($result['data'] ?? null)
                    ? $result['data']
                    : null;
            });
        } catch (Throwable $error) {
            log_error($error);
            return null;
        }
    }

    public static function kindOf(array $record): string
    {
        $type = strtolower((string) ($record['content_type'] ?? $record['kind'] ?? ''));
        if (str_contains($type, 'anime')) {
            return 'anime';
        }
        if (str_contains($type, 'series') || str_contains($type, 'tv')) {
            return 'series';
        }
        if (str_contains($type, 'movie') || str_contains($type, 'film')) {
            return 'movie';
        }
        return ((int) ($record['episode_count'] ?? 0)) > 1 ? 'series' : 'movie';
    }

    public static function card(array $record): array
    {
        $kind = self::kindOf($record);
        $title = (string) ($record['title'] ?? '');
        $year = $record['year'] ?? null;
        if (!$year && preg_match('/\b(19|20)\d{2}\b/', $title, $match)) {
            $year = (int) $match[0];
        }
        return [
            'ref' => 'mlbd-' . $kind . '-' . $record['slug'],
            'title' => $title,
            'posterUrl' => $record['poster'] ?? null,
            'backdropUrl' => null,
            'overview' => '',
            'year' => $year ? (string) $year : null,
            'rating' => null,
            'badge' => $kind === 'anime' ? 'ANIME' : ($kind === 'series' ? 'SERIES' : 'FILM'),
            'episodeCount' => isset($record['episode_count']) ? (int) $record['episode_count'] : null,
            'status' => null,
            'genres' => !empty($record['category']) ? [(string) $record['category']] : [],
            'originalLanguage' => null,
            'provider' => 'mlbd',
        ];
    }

    /** @return array<int,array> */
    public static function search(string $term, int $limit = 24): array
    {
        if (trim($term) === '') {
            return [];
        }
        try {
            $data = self::v2('/titles', [
                'q' => trim($term),
                'limit' => $limit,
                'status' => 'saved',
                'include_adult' => 'false',
            ], 300);
        } catch (Throwable $error) {
            log_error($error);
            return [];
        }
        $out = [];
        foreach (($data['results'] ?? []) as $record) {
            if (is_array($record) && !empty($record['slug'])) {
                $out[] = self::card($record);
            }
        }
        return $out;
    }

    /** @return array<int,array> */
    public static function collection(string $slug, int $limit = 20, int $page = 1): array
    {
        $adult = str_starts_with($slug, 'adult-');
        try {
            $data = self::v2('/titles', [
                'limit' => $limit,
                'offset' => max(0, ($page - 1) * $limit),
                'status' => 'saved',
                'collection' => $slug,
                'include_adult' => $adult ? 'true' : 'false',
            ]);
        } catch (Throwable $error) {
            log_error($error);
            return [];
        }
        $out = [];
        foreach (($data['results'] ?? []) as $record) {
            if (is_array($record) && !empty($record['slug'])) {
                $out[] = self::card($record);
            }
        }
        return $out;
    }

    /** @return array<int,array> */
    public static function latest(int $limit = 20, int $page = 1): array
    {
        try {
            $data = self::v2('/titles', [
                'limit' => $limit,
                'offset' => max(0, ($page - 1) * $limit),
                'status' => 'saved',
                'sort' => 'updated',
                'include_adult' => 'false',
            ]);
        } catch (Throwable $error) {
            log_error($error);
            return [];
        }
        $out = [];
        foreach (($data['results'] ?? []) as $record) {
            if (is_array($record) && !empty($record['slug'])) {
                $out[] = self::card($record);
            }
        }
        return $out;
    }

    public static function detailV2(string $slug): ?array
    {
        try {
            return self::v2('/title/' . rawurlencode($slug), [], 300);
        } catch (Throwable $error) {
            log_error($error);
            return null;
        }
    }

    /**
     * Ranked slug matches for a title from another catalogue.
     *
     * @return array<int,array>
     */
    public static function match(string $title, int $year = 0, int $season = 0, int $episode = 0, array $alt = []): array
    {
        $params = [
            'title' => $title,
            'year' => $year,
            'season' => $season,
            'episode' => $episode,
        ];
        $alt = array_values(array_filter(array_map('trim', $alt)));
        if ($alt !== []) {
            $params['alt'] = implode(',', array_slice($alt, 0, 3));
        }
        try {
            $data = self::v2('/match', $params, 300);
        } catch (Throwable $error) {
            log_error($error);
            $data = [];
        }

        $out = [];
        $seen = [];
        $candidates = [];
        if (is_array($data['match'] ?? null)) {
            $candidates[] = $data['match'];
        }
        foreach (($data['candidates'] ?? []) as $candidate) {
            $candidates[] = $candidate;
        }
        foreach ($candidates as $candidate) {
            $slug = (string) ($candidate['slug'] ?? '');
            if ($slug !== '' && !isset($seen[$slug])) {
                $seen[$slug] = true;
                $out[] = $candidate;
            }
        }

        if ($out === []) {
            foreach (self::search($title, 8) as $item) {
                $slug = substr((string) $item['ref'], strpos((string) $item['ref'], '-', 6) + 1);
                $out[] = [
                    'slug' => $slug,
                    'title' => $item['title'],
                    'poster' => $item['posterUrl'],
                    'year' => $item['year'] ? (int) $item['year'] : null,
                    'episode_count' => $item['episodeCount'] ?? null,
                ];
            }
        }
        return array_slice($out, 0, 6);
    }

    /**
     * The files for one episode. Falls back to the nearest episode the block
     * ships, exactly as the app's `MlbdSeason::nearest()` does.
     *
     * @return array{record:array,files:array<int,array>,season:int,episode:int}|null
     */
    public static function streamsFor(string $slug, int $season, int $episode): ?array
    {
        $record = self::titleV1($slug);
        if (!$record || empty($record['streams']) || !is_array($record['streams'])) {
            return null;
        }

        $sameSeason = array_values(array_filter(
            $record['streams'],
            static fn ($stream) => (int) ($stream['season'] ?? 0) === $season,
        ));
        $pool = $sameSeason !== [] ? $sameSeason : array_values($record['streams']);
        if ($pool === []) {
            return null;
        }

        $best = $pool[0];
        $distance = PHP_INT_MAX;
        foreach ($pool as $stream) {
            $gap = abs((int) ($stream['episode'] ?? 0) - $episode);
            if ($gap < $distance) {
                $best = $stream;
                $distance = $gap;
            }
        }

        $files = [];
        if (!empty($best['hls'])) {
            $files[] = [
                'url' => (string) $best['hls'],
                'hls' => (string) $best['hls'],
                'quality' => 'auto',
                'format' => 'hls',
                'label' => 'HLS · Auto',
            ];
        }
        if (!empty($best['url'])) {
            $files[] = [
                'url' => (string) $best['url'],
                'hls' => null,
                'quality' => $record['quality'] ?? null,
                'format' => 'mp4',
                'label' => (string) ($record['quality'] ?? 'MP4'),
            ];
        }
        if ($files === []) {
            return null;
        }

        return [
            'record' => $record,
            'files' => $files,
            'season' => (int) ($best['season'] ?? $season),
            'episode' => (int) ($best['episode'] ?? $episode),
        ];
    }
}
