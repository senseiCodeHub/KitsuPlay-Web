<?php
/**
 * AniList GraphQL — anime metadata. Public API, no key.
 */

declare(strict_types=1);

final class AniList
{
    private const ENDPOINT = 'https://graphql.anilist.co';

    private const FIELDS = 'id title { romaji english native } coverImage { extraLarge large }
        bannerImage description(asHtml: false) seasonYear averageScore episodes status genres format isAdult';

    /** @return array<string,mixed> */
    public static function query(string $query, array $variables = [], int $ttl = 900): array
    {
        $key = 'anilist:' . $query . ':' . json_encode($variables);
        return Cache::remember($key, $ttl, static function () use ($query, $variables) {
            $result = Http::json(self::ENDPOINT, [
                'Content-Type: application/json',
                'Accept: application/json',
            ], ['query' => $query, 'variables' => (object) $variables], 'POST');
            if (!empty($result['errors'])) {
                throw new RuntimeException((string) ($result['errors'][0]['message'] ?? 'AniList failed'));
            }
            return is_array($result['data'] ?? null) ? $result['data'] : [];
        });
    }

    private static function text(?string $html): string
    {
        if ($html === null) {
            return '';
        }
        $text = preg_replace('/<br\s*\/?>/i', ' ', $html) ?? $html;
        $text = strip_tags($text);
        return trim(html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    /** @param array<string,mixed> $media */
    public static function card(array $media): array
    {
        $title = $media['title']['english'] ?? $media['title']['romaji'] ?? $media['title']['native'] ?? '';
        $status = match ((string) ($media['status'] ?? '')) {
            'RELEASING' => 'airing',
            'FINISHED' => 'finished',
            'NOT_YET_RELEASED' => 'upcoming',
            default => null,
        };
        return [
            'ref' => 'anilist-anime-' . (int) ($media['id'] ?? 0),
            'title' => (string) $title,
            'posterUrl' => $media['coverImage']['extraLarge'] ?? ($media['coverImage']['large'] ?? null),
            'backdropUrl' => $media['bannerImage'] ?? null,
            'overview' => self::text($media['description'] ?? null),
            'year' => isset($media['seasonYear']) ? (string) $media['seasonYear'] : null,
            'rating' => isset($media['averageScore']) ? ((int) $media['averageScore']) / 10 : null,
            'badge' => $media['format'] ?? 'ANIME',
            'episodeCount' => isset($media['episodes']) ? (int) $media['episodes'] : null,
            'status' => $status,
            'genres' => is_array($media['genres'] ?? null) ? $media['genres'] : [],
            'originalLanguage' => 'ja',
            'provider' => 'anilist',
        ];
    }

    /** @return array<int,array> */
    private static function page(string $sort, array $variables, string $extra = '', int $perPage = 20): array
    {
        $data = self::query(
            'query ($page: Int, $perPage: Int) { Page(page: $page, perPage: $perPage) { media(type: ANIME, sort: [' . $sort . '], isAdult: false ' . $extra . ') { ' . self::FIELDS . ' } } }',
            array_merge(['page' => 1, 'perPage' => $perPage], $variables),
        );
        $out = [];
        foreach (($data['Page']['media'] ?? []) as $media) {
            if (is_array($media) && empty($media['isAdult'])) {
                $out[] = self::card($media);
            }
        }
        return $out;
    }

    public static function trending(): array
    {
        return self::page('TRENDING_DESC', []);
    }

    public static function popular(int $page = 1): array
    {
        return self::page('POPULARITY_DESC', ['page' => $page], '', 24);
    }

    public static function topRated(): array
    {
        return self::page('SCORE_DESC', []);
    }

    public static function airing(): array
    {
        return self::page('POPULARITY_DESC', [], ', status: RELEASING');
    }

    public static function byGenre(string $genre, int $page = 1): array
    {
        $data = self::query(
            'query ($page: Int, $perPage: Int, $genre: String) { Page(page: $page, perPage: $perPage) { media(type: ANIME, genre: $genre, sort: [POPULARITY_DESC], isAdult: false) { ' . self::FIELDS . ' } } }',
            ['page' => $page, 'perPage' => 24, 'genre' => $genre],
        );
        $out = [];
        foreach (($data['Page']['media'] ?? []) as $media) {
            if (is_array($media) && empty($media['isAdult'])) {
                $out[] = self::card($media);
            }
        }
        return $out;
    }

    /** @return array<int,array> */
    public static function search(string $term): array
    {
        if (trim($term) === '') {
            return [];
        }
        $data = self::query(
            'query ($search: String) { Page(page: 1, perPage: 24) { media(type: ANIME, search: $search, sort: [SEARCH_MATCH], isAdult: false) { ' . self::FIELDS . ' } } }',
            ['search' => $term],
            300,
        );
        $out = [];
        foreach (($data['Page']['media'] ?? []) as $media) {
            if (is_array($media) && empty($media['isAdult'])) {
                $out[] = self::card($media);
            }
        }
        return $out;
    }

    public static function detail(string $id): ?array
    {
        $mediaId = (int) $id;
        if ($mediaId <= 0) {
            return null;
        }
        $data = self::query(
            'query ($id: Int) { Media(id: $id, type: ANIME) { ' . self::FIELDS . ' duration
                trailer { id site }
                studios(isMain: true) { nodes { name } }
                recommendations(perPage: 12, sort: RATING_DESC) { nodes { mediaRecommendation { ' . self::FIELDS . ' } } }
                characters(perPage: 12, sort: ROLE) { edges { role node { name { full } image { large } } } }
            } }',
            ['id' => $mediaId],
            3600,
        );
        $media = $data['Media'] ?? null;
        if (!is_array($media)) {
            return null;
        }

        $episodes = [];
        $count = (int) ($media['episodes'] ?? 0);
        $airing = ($media['status'] ?? '') === 'RELEASING';
        for ($number = 1; $number <= min(2000, $count); $number++) {
            $episodes[] = [
                'season' => 1,
                'number' => $number,
                'label' => 'EP ' . $number,
                'sources' => [],
            ];
        }

        $cast = [];
        foreach (($media['characters']['edges'] ?? []) as $edge) {
            $cast[] = [
                'name' => (string) ($edge['node']['name']['full'] ?? ''),
                'role' => $edge['role'] ?? null,
                'photoUrl' => $edge['node']['image']['large'] ?? null,
            ];
        }

        $similar = [];
        foreach (($media['recommendations']['nodes'] ?? []) as $node) {
            $entry = $node['mediaRecommendation'] ?? null;
            if (is_array($entry)) {
                $similar[] = self::card($entry);
            }
        }

        $studios = array_filter(array_map(
            static fn ($node) => (string) ($node['name'] ?? ''),
            $media['studios']['nodes'] ?? [],
        ));

        return [
            'item' => self::card($media),
            'overview' => self::text($media['description'] ?? null),
            'runtime' => isset($media['duration']) ? (int) $media['duration'] : null,
            'genres' => is_array($media['genres'] ?? null) ? $media['genres'] : [],
            'cast' => $cast,
            'seasons' => $count > 0 ? [['season' => 1, 'episodes' => $count]] : [],
            'episodes' => $episodes,
            'similar' => $similar,
            'trailerKey' => ($media['trailer']['site'] ?? '') === 'youtube' ? ($media['trailer']['id'] ?? null) : null,
            'tagline' => $studios ? implode(', ', $studios) : null,
            'homepage' => 'https://anilist.co/anime/' . $mediaId,
            'playSource' => 'mlbd',
            'audio' => [],
            'subtitles' => [],
            'airing' => $airing,
        ];
    }

    /** Romaji/English/native names, for matching an anime against MLBD. */
    public static function altTitles(string $term): array
    {
        $data = self::query(
            'query ($id: Int) { Media(id: $id) { title { romaji english native } } }',
            ['id' => (int) (preg_replace('/\D/', '', $term) ?: 0)],
        );
        $title = $data['Media']['title'] ?? null;
        if (!is_array($title)) {
            return [];
        }
        return array_values(array_unique(array_filter([
            $title['english'] ?? null,
            $title['romaji'] ?? null,
            $title['native'] ?? null,
        ])));
    }
}
