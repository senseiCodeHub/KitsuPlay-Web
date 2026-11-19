<?php
/**
 * TMDB — films and television. The read token stays on the server; the
 * browser never sees it.
 */

declare(strict_types=1);

final class Tmdb
{
    private const BASE = 'https://api.themoviedb.org/3';
    private const IMAGE = 'https://image.tmdb.org/t/p';

    public const GENRES = [
        'movie' => [
            28 => 'Action', 12 => 'Adventure', 16 => 'Animation', 35 => 'Comedy',
            80 => 'Crime', 99 => 'Documentary', 18 => 'Drama', 10751 => 'Family',
            14 => 'Fantasy', 27 => 'Horror', 9648 => 'Mystery', 10749 => 'Romance',
            878 => 'Sci-Fi', 53 => 'Thriller', 10752 => 'War', 37 => 'Western',
        ],
        'tv' => [
            10759 => 'Action', 16 => 'Animation', 35 => 'Comedy', 80 => 'Crime',
            99 => 'Documentary', 18 => 'Drama', 9648 => 'Mystery', 10764 => 'Reality',
            10765 => 'Sci-Fi', 10766 => 'Soap', 10768 => 'War', 37 => 'Western',
        ],
    ];

    public const LANGUAGES = [
        'en' => 'English', 'bn' => 'Bangla', 'hi' => 'Hindi', 'ta' => 'Tamil',
        'te' => 'Telugu', 'ko' => 'Korean', 'ja' => 'Japanese', 'zh' => 'Chinese',
        'es' => 'Spanish', 'fr' => 'French', 'th' => 'Thai', 'tr' => 'Turkish',
    ];

    public const COUNTRIES = [
        'US' => 'United States', 'GB' => 'United Kingdom', 'IN' => 'India',
        'BD' => 'Bangladesh', 'KR' => 'South Korea', 'JP' => 'Japan',
        'CN' => 'China', 'TH' => 'Thailand', 'TR' => 'Turkey', 'ES' => 'Spain',
    ];

    /** Whether a v4 read token or a v3 API key is present. */
    public static function configured(): bool
    {
        return TMDB_TOKEN !== '' || TMDB_API_KEY !== '';
    }

    /**
     * Accepts either credential: a v4 read access token (sent as a Bearer
     * header) or a v3 API key (sent as `api_key`). Both are server-side only.
     *
     * @return array<string,mixed>
     */
    public static function get(string $path, array $params = [], int $ttl = 1800): array
    {
        if (!self::configured()) {
            throw new RuntimeException('TMDB_READ_TOKEN / TMDB_API_KEY is not configured.');
        }
        $query = array_filter(
            $params,
            static fn ($value) => $value !== '' && $value !== null,
        );
        $headers = ['Accept: application/json'];
        if (TMDB_TOKEN !== '') {
            $headers[] = 'Authorization: Bearer ' . TMDB_TOKEN;
        } else {
            $query['api_key'] = TMDB_API_KEY;
        }
        $encoded = http_build_query($query);
        $url = self::BASE . $path . ($encoded !== '' ? '?' . $encoded : '');
        return Cache::remember('tmdb:' . $path . ':' . $encoded, $ttl, static function () use ($url, $headers) {
            return Http::json($url, $headers);
        });
    }

    public static function poster(?string $path, string $size = 'w500'): ?string
    {
        return $path ? self::IMAGE . '/' . $size . $path : null;
    }

    public static function backdrop(?string $path, string $size = 'w1280'): ?string
    {
        return $path ? self::IMAGE . '/' . $size . $path : null;
    }

    /** @param array<string,mixed> $entry */
    public static function card(array $entry, string $kind): ?array
    {
        $title = (string) ($kind === 'movie' ? ($entry['title'] ?? '') : ($entry['name'] ?? ''));
        $id = (int) ($entry['id'] ?? 0);
        if ($title === '' || $id === 0) {
            return null;
        }
        $date = (string) ($kind === 'movie' ? ($entry['release_date'] ?? '') : ($entry['first_air_date'] ?? ''));
        $genres = [];
        if (is_array($entry['genres'] ?? null)) {
            foreach ($entry['genres'] as $genre) {
                if (!empty($genre['name'])) {
                    $genres[] = (string) $genre['name'];
                }
            }
        } elseif (is_array($entry['genre_ids'] ?? null)) {
            foreach ($entry['genre_ids'] as $genreId) {
                $name = self::GENRES[$kind][(int) $genreId] ?? null;
                if ($name) {
                    $genres[] = $name;
                }
            }
        }

        return [
            'ref' => 'tmdb-' . ($kind === 'movie' ? 'movie' : 'series') . '-' . $id,
            'title' => $title,
            'posterUrl' => self::poster($entry['poster_path'] ?? null),
            'backdropUrl' => self::backdrop($entry['backdrop_path'] ?? null),
            'overview' => (string) ($entry['overview'] ?? ''),
            'year' => $date !== '' ? substr($date, 0, 4) : null,
            'rating' => isset($entry['vote_average']) ? (float) $entry['vote_average'] : null,
            'badge' => $kind === 'movie' ? 'FILM' : 'SERIES',
            'episodeCount' => isset($entry['number_of_episodes']) ? (int) $entry['number_of_episodes'] : null,
            'status' => self::status($entry['status'] ?? null),
            'genres' => $genres,
            'originalLanguage' => $entry['original_language'] ?? null,
            'provider' => 'tmdb',
        ];
    }

    private static function status(mixed $raw): ?string
    {
        return match ((string) $raw) {
            'Returning Series' => 'airing',
            'Ended' => 'finished',
            'In Production', 'Planned' => 'upcoming',
            default => null,
        };
    }

    /** @return array<int,array> */
    public static function list(string $path, string $kind, array $params = []): array
    {
        $json = self::get($path, array_merge(['page' => 1, 'include_adult' => 'false'], $params));
        $out = [];
        foreach (($json['results'] ?? []) as $entry) {
            $item = is_array($entry) ? self::card($entry, $kind) : null;
            if ($item) {
                $out[] = $item;
            }
        }
        return $out;
    }

    public static function trending(): array
    {
        $items = self::list('/trending/all/week', 'movie');
        return array_values(array_filter(
            $items,
            static fn ($item) => str_contains((string) $item['ref'], '-movie-'),
        ));
    }

    public static function popularMovies(int $page = 1): array
    {
        return self::list('/movie/popular', 'movie', ['page' => $page]);
    }

    public static function topMovies(int $page = 1): array
    {
        return self::list('/movie/top_rated', 'movie', ['page' => $page]);
    }

    public static function popularSeries(int $page = 1): array
    {
        return self::list('/tv/popular', 'tv', ['page' => $page]);
    }

    public static function airingSeries(int $page = 1): array
    {
        return self::list('/tv/on_the_air', 'tv', ['page' => $page]);
    }

    /** @return array<int,array> */
    public static function discover(string $kind, array $filters, int $page = 1): array
    {
        $query = [
            'sort_by' => $filters['sort'] ?? 'popularity.desc',
            'page' => $page,
            'include_adult' => 'false',
        ];
        if (!empty($filters['genre'])) {
            $query['with_genres'] = (int) $filters['genre'];
        }
        if (!empty($filters['country'])) {
            $query['with_origin_country'] = $filters['country'];
        }
        if (!empty($filters['language'])) {
            $query['with_original_language'] = $filters['language'];
        }
        if (!empty($filters['year'])) {
            if ($kind === 'movie') {
                $query['primary_release_year'] = $filters['year'];
                $query['primary_release_date.lte'] = date('Y-m-d');
            } else {
                $query['first_air_date_year'] = $filters['year'];
            }
        }
        return self::list('/discover/' . ($kind === 'movie' ? 'movie' : 'tv'), $kind, $query);
    }

    /** @return array<int,array> */
    public static function search(string $term, int $page = 1): array
    {
        if (trim($term) === '') {
            return [];
        }
        $json = self::get('/search/multi', [
            'query' => $term,
            'page' => $page,
            'include_adult' => 'false',
        ], 300);
        $out = [];
        foreach (($json['results'] ?? []) as $entry) {
            $type = $entry['media_type'] ?? '';
            if ($type !== 'movie' && $type !== 'tv') {
                continue;
            }
            $item = self::card($entry, $type);
            if ($item) {
                $out[] = $item;
            }
        }
        return $out;
    }

    /** One title's full record, shaped for the detail page. */
    public static function detail(string $ref): ?array
    {
        if (!preg_match('/^tmdb-(movie|series)-(\d+)$/', $ref, $match)) {
            return null;
        }
        $kind = $match[1] === 'movie' ? 'movie' : 'tv';
        $id = $match[2];
        $json = self::get('/' . $kind . '/' . $id, [
            'append_to_response' => 'credits,videos,similar',
        ], 3600);
        $item = self::card($json, $kind);
        if (!$item) {
            return null;
        }

        $cast = [];
        foreach (array_slice($json['credits']['cast'] ?? [], 0, 12) as $member) {
            $cast[] = [
                'name' => (string) ($member['name'] ?? ''),
                'role' => $member['character'] ?? null,
                'photoUrl' => self::poster($member['profile_path'] ?? null, 'w185'),
            ];
        }

        $trailer = null;
        foreach (($json['videos']['results'] ?? []) as $video) {
            if (($video['site'] ?? '') === 'YouTube' && ($video['type'] ?? '') === 'Trailer') {
                $trailer = (string) $video['key'];
                break;
            }
        }

        $seasons = [];
        if ($kind === 'tv') {
            foreach (($json['seasons'] ?? []) as $season) {
                if ((int) ($season['season_number'] ?? 0) > 0 && (int) ($season['episode_count'] ?? 0) > 0) {
                    $seasons[] = [
                        'season' => (int) $season['season_number'],
                        'episodes' => (int) $season['episode_count'],
                        'name' => $season['name'] ?? null,
                    ];
                }
            }
        }

        $similar = [];
        foreach (array_slice($json['similar']['results'] ?? [], 0, 12) as $entry) {
            $card = is_array($entry) ? self::card($entry, $kind) : null;
            if ($card) {
                $similar[] = $card;
            }
        }

        return [
            'item' => $item,
            'overview' => (string) ($json['overview'] ?? $item['overview']),
            'runtime' => isset($json['runtime']) ? (int) $json['runtime'] : null,
            'genres' => array_values(array_filter(array_map(
                static fn ($genre) => is_array($genre) ? (string) ($genre['name'] ?? '') : '',
                $json['genres'] ?? [],
            ))),
            'cast' => $cast,
            'seasons' => $seasons,
            'episodes' => [],
            'similar' => $similar,
            'trailerKey' => $trailer,
            'tagline' => $json['tagline'] ?? null,
            'homepage' => $json['homepage'] ?? null,
            'playSource' => 'mlbd',
            'audio' => [],
            'subtitles' => [],
        ];
    }

    /** @return array<int,array> */
    public static function season(string $ref, int $season): array
    {
        if (!preg_match('/^tmdb-series-(\d+)$/', $ref, $match)) {
            return [];
        }
        $json = self::get('/tv/' . $match[1] . '/season/' . $season, [], 3600);
        $out = [];
        foreach (($json['episodes'] ?? []) as $episode) {
            $number = (int) ($episode['episode_number'] ?? 0);
            if ($number <= 0) {
                continue;
            }
            $out[] = [
                'season' => (int) ($episode['season_number'] ?? $season),
                'number' => $number,
                'label' => sprintf('S%02dE%02d', (int) ($episode['season_number'] ?? $season), $number),
                'title' => $episode['name'] ?? null,
                'overview' => $episode['overview'] ?? null,
                'stillUrl' => self::backdrop($episode['still_path'] ?? null, 'w300'),
                'runtime' => isset($episode['runtime']) ? (int) $episode['runtime'] : null,
                'airDate' => $episode['air_date'] ?? null,
                'sources' => [],
            ];
        }
        return $out;
    }

    /** @return array{seasonCounts:array<int,int>,episodeCount:int} */
    public static function seasonCounts(string $ref): array
    {
        if (!preg_match('/^tmdb-series-(\d+)$/', $ref, $match)) {
            return ['seasonCounts' => [], 'episodeCount' => 0];
        }
        $json = self::get('/tv/' . $match[1], [], 3600);
        $counts = [];
        foreach (($json['seasons'] ?? []) as $season) {
            if ((int) ($season['season_number'] ?? 0) > 0 && (int) ($season['episode_count'] ?? 0) > 0) {
                $counts[(int) $season['season_number']] = (int) $season['episode_count'];
            }
        }
        return [
            'seasonCounts' => $counts,
            'episodeCount' => (int) ($json['number_of_episodes'] ?? 0),
        ];
    }
}
