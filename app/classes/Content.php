<?php
/**
 * The catalogue, assembled from the three lanes for the pages to render.
 */

declare(strict_types=1);

final class Content
{
    /** Home rails, in the order the app's board uses them. */
    public static function home(): array
    {
        $rows = [
            ['id' => 'mlbd-latest', 'title' => 'Recently Added', 'more' => '/browse/mlbd', 'loader' => static fn () => Mlbd::latest(20)],
            ['id' => 'bangla-movie', 'title' => 'Bangla Movies', 'more' => '/browse/mlbd?collection=bangla-movie', 'loader' => static fn () => Mlbd::collection('bangla-movie')],
            ['id' => 'bangla-series', 'title' => 'Bangla Web Series', 'more' => '/browse/mlbd?collection=bangla-series', 'loader' => static fn () => Mlbd::collection('bangla-series')],
            ['id' => 'popular-movies', 'title' => 'Popular Movies', 'more' => '/browse/movie', 'loader' => static fn () => Tmdb::popularMovies()],
            ['id' => 'anime-spotlight', 'title' => 'Anime Spotlight', 'more' => '/browse/anime', 'loader' => static fn () => AniList::trending()],
            ['id' => 'hindi-movie', 'title' => 'Hindi Movies', 'more' => '/browse/mlbd?collection=hindi-movie', 'loader' => static fn () => Mlbd::collection('hindi-movie')],
            ['id' => 'hindi-dubbed', 'title' => 'Hindi Dubbed', 'more' => '/browse/mlbd?collection=hindi-dubbed', 'loader' => static fn () => Mlbd::collection('hindi-dubbed')],
            ['id' => 'popular-series', 'title' => 'Popular Series', 'more' => '/browse/tv', 'loader' => static fn () => Tmdb::popularSeries()],
            ['id' => 'south-dubbed', 'title' => 'South Indian Hindi Dub', 'more' => '/browse/mlbd?collection=south-indian-hindi-dubbed', 'loader' => static fn () => Mlbd::collection('south-indian-hindi-dubbed')],
            ['id' => 'airing-anime', 'title' => 'Airing Now · Anime', 'more' => '/browse/anime', 'loader' => static fn () => AniList::airing()],
            ['id' => 'korean-drama', 'title' => 'Korean Drama', 'more' => '/browse/mlbd?collection=korean-drama', 'loader' => static fn () => Mlbd::collection('korean-drama')],
            ['id' => 'airing-series', 'title' => 'On The Air · Series', 'more' => '/browse/tv', 'loader' => static fn () => Tmdb::airingSeries()],
            ['id' => 'top-movies', 'title' => 'Top Rated Films', 'more' => '/browse/movie?sort=vote_average.desc', 'loader' => static fn () => Tmdb::topMovies()],
            ['id' => 'top-anime', 'title' => 'All-Time Anime', 'more' => '/browse/anime', 'loader' => static fn () => AniList::topRated()],
        ];

        $out = [];
        foreach ($rows as $row) {
            try {
                $items = ($row['loader'])();
            } catch (Throwable $error) {
                log_error($error);
                $items = [];
            }
            if ($items !== []) {
                $out[] = [
                    'id' => $row['id'],
                    'title' => $row['title'],
                    'more' => $row['more'],
                    'items' => $items,
                ];
            }
        }
        return $out;
    }

    /** @return array<int,array> */
    public static function search(string $term): array
    {
        $term = trim($term);
        if (mb_strlen($term) < 2) {
            return [];
        }

        $lists = [];
        foreach ([
            static fn () => Tmdb::search($term),
            static fn () => AniList::search($term),
            static fn () => Mlbd::search($term),
        ] as $loader) {
            try {
                $lists[] = $loader();
            } catch (Throwable $error) {
                log_error($error);
            }
        }

        $needle = mb_strtolower($term);
        $words = array_values(array_filter(
            preg_split('/\s+/', $needle) ?: [],
            static fn ($word) => mb_strlen($word) > 2,
        ));

        $seen = [];
        $results = [];
        foreach ($lists as $list) {
            foreach ($list as $item) {
                $title = mb_strtolower((string) $item['title']);
                $matchesNeedle = str_contains($title, $needle);
                $matchesWords = $words === [] || array_reduce(
                    $words,
                    static fn ($carry, $word) => $carry && str_contains($title, $word),
                    true,
                );
                if (!$matchesNeedle && !$matchesWords) {
                    continue;
                }
                $key = preg_replace('/[^a-z0-9]+/', '', $title) . '|' . ($item['year'] ?? '');
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $results[] = $item;
            }
        }

        usort($results, static function ($a, $b) use ($needle) {
            $aStart = str_starts_with(mb_strtolower((string) $a['title']), $needle) ? 0 : 1;
            $bStart = str_starts_with(mb_strtolower((string) $b['title']), $needle) ? 0 : 1;
            if ($aStart !== $bStart) {
                return $aStart <=> $bStart;
            }
            return ((float) ($b['rating'] ?? 0)) <=> ((float) ($a['rating'] ?? 0));
        });

        return array_slice($results, 0, 60);
    }

    public static function detail(string $ref): ?array
    {
        if (str_starts_with($ref, 'tmdb-')) {
            return Tmdb::detail($ref);
        }
        if (str_starts_with($ref, 'anilist-')) {
            $id = (string) preg_replace('/^anilist-anime-/', '', $ref);
            return AniList::detail($id);
        }
        if (str_starts_with($ref, 'mlbd-')) {
            $slug = substr($ref, strpos($ref, '-', 6) + 1);
            return self::mlbdDetail($slug);
        }
        return null;
    }

    public static function mlbdDetail(string $slug): ?array
    {
        $record = Mlbd::titleV1($slug);
        if (!$record) {
            return null;
        }
        $v2 = Mlbd::detailV2($slug);

        $byEpisode = [];
        foreach (($record['streams'] ?? []) as $stream) {
            $season = (int) ($stream['season'] ?? 1);
            $number = (int) ($stream['episode'] ?? 1);
            $key = $season . ':' . $number;
            $files = $byEpisode[$key] ?? [];
            if (!empty($stream['hls'])) {
                $files[] = [
                    'url' => (string) $stream['hls'],
                    'hls' => (string) $stream['hls'],
                    'format' => 'hls',
                    'quality' => 'auto',
                    'label' => 'HLS · Auto',
                ];
            }
            if (!empty($stream['url'])) {
                $files[] = [
                    'url' => (string) $stream['url'],
                    'hls' => null,
                    'format' => 'mp4',
                    'quality' => $record['quality'] ?? null,
                    'label' => (string) ($record['quality'] ?? 'MP4'),
                ];
            }
            $byEpisode[$key] = $files;
        }

        $episodes = [];
        foreach ($byEpisode as $key => $files) {
            [$season, $number] = array_map('intval', explode(':', $key));
            $episodes[] = [
                'season' => $season,
                'number' => $number,
                'label' => sprintf('S%02dE%02d', $season, $number),
                'sources' => $files,
            ];
        }
        usort($episodes, static fn ($a, $b) => [$a['season'], $a['number']] <=> [$b['season'], $b['number']]);

        $seasonMap = [];
        foreach ($episodes as $episode) {
            $seasonMap[$episode['season']] = max($seasonMap[$episode['season']] ?? 0, $episode['number']);
        }
        $seasons = [];
        foreach ($seasonMap as $season => $count) {
            $seasons[] = ['season' => $season, 'episodes' => $count];
        }

        $item = Mlbd::card([
            'slug' => $record['slug'],
            'title' => $record['title'],
            'poster' => $record['poster'] ?? null,
            'kind' => $record['kind'] ?? null,
            'year' => $record['year'] ?? null,
            'episode_count' => count($episodes),
            'category' => is_array($record['categories'] ?? null) ? ($record['categories'][0] ?? null) : null,
        ]);
        $item['episodeCount'] = count($episodes);
        $item['genres'] = is_array($record['categories'] ?? null) ? $record['categories'] : [];

        $subtitles = [];
        foreach (($v2['subtitles'] ?? []) as $subtitle) {
            if (!empty($subtitle['url'])) {
                $subtitles[] = [
                    'label' => (string) ($subtitle['label'] ?? 'Subtitles'),
                    'language' => (string) ($subtitle['language'] ?? ''),
                    'url' => (string) $subtitle['url'],
                ];
            }
        }

        return [
            'item' => $item,
            'overview' => '',
            'runtime' => null,
            'genres' => $item['genres'],
            'cast' => [],
            'seasons' => $seasons,
            'episodes' => $episodes,
            'similar' => [],
            'trailerKey' => null,
            'tagline' => $record['quality'] ?? null,
            'homepage' => $record['page_url'] ?? null,
            'playSource' => 'mlbd',
            'mlbdSlug' => $record['slug'],
            'audio' => !empty($record['lang']) ? [$record['lang']] : ($v2['audio'] ?? []),
            'subtitles' => $subtitles,
        ];
    }

    /** Episodes of one season for a TV-shaped detail. */
    public static function episodesFor(string $ref, int $season): array
    {
        if (str_starts_with($ref, 'tmdb-series-')) {
            return Tmdb::season($ref, $season);
        }
        return [];
    }

    /**
     * Resolves playable files for one episode. MLBD's HLS master is the only
     * stream lane exposed on web; the direct MP4 is kept as the fallback.
     *
     * @return array{ok:bool,error?:string,sources?:array,slug?:string,title?:string,season?:int,episode?:int}
     */
    public static function playable(string $ref, string $title, int $year, int $season, int $episode): array
    {
        if (str_starts_with($ref, 'mlbd-')) {
            $slug = substr($ref, strpos($ref, '-', 6) + 1);
            $resolved = Mlbd::streamsFor($slug, $season, $episode);
            if (!$resolved) {
                return ['ok' => false, 'error' => 'This title has no playable stream yet.'];
            }
            return [
                'ok' => true,
                'slug' => $slug,
                'title' => (string) ($resolved['record']['title'] ?? $title),
                'season' => $resolved['season'],
                'episode' => $resolved['episode'],
                'sources' => array_map([self::class, 'source'], $resolved['files']),
            ];
        }

        $candidates = Mlbd::match($title, $year, $season, $episode);
        foreach (array_slice($candidates, 0, 4) as $candidate) {
            $resolved = Mlbd::streamsFor((string) $candidate['slug'], $season, $episode);
            if (!$resolved) {
                continue;
            }
            return [
                'ok' => true,
                'slug' => (string) $candidate['slug'],
                'title' => (string) ($resolved['record']['title'] ?? $title),
                'season' => $resolved['season'],
                'episode' => $resolved['episode'],
                'sources' => array_map([self::class, 'source'], $resolved['files']),
            ];
        }

        return [
            'ok' => false,
            'error' => 'No playable source for this episode yet. The catalogue may still be scraping it.',
        ];
    }

    /** Wraps one upstream file in the relay address the player understands. */
    public static function source(array $file): array
    {
        $url = (string) ($file['hls'] ?? $file['url']);
        $isPlaylist = !empty($file['hls'])
            || str_contains($url, '/hls/')
            || str_contains($url, '/m/')
            || str_contains($url, '.m3u8');
        return [
            'label' => (string) ($file['label'] ?? ($isPlaylist ? 'HLS · Auto' : 'Direct file')),
            'kind' => $isPlaylist ? 'hls' : 'file',
            'url' => $isPlaylist
                ? url('api/hls/playlist') . '?src=' . b64url_encode($url)
                : url('api/hls/segment') . '?src=' . b64url_encode($url),
        ];
    }

    /** Browse grids. */
    public static function browse(string $type, array $filters, int $page = 1): array
    {
        if ($type === 'anime') {
            return empty($filters['genre'])
                ? AniList::popular($page)
                : AniList::byGenre((string) $filters['genre'], $page);
        }
        if ($type === 'mlbd') {
            $collection = (string) ($filters['collection'] ?? '');
            return $collection !== ''
                ? Mlbd::collection($collection, 24, $page)
                : Mlbd::latest(24, $page);
        }
        return Tmdb::discover($type === 'tv' ? 'tv' : 'movie', $filters, $page);
    }
}

/** Base64url without padding, for a URL riding in a query string. */
function b64url_encode(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function b64url_decode(string $value): string
{
    $padded = $value . str_repeat('=', (4 - strlen($value) % 4) % 4);
    $decoded = base64_decode(strtr($padded, '-_', '+/'), true);
    return $decoded === false ? '' : $decoded;
}
