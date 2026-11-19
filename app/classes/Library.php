<?php
/**
 * The viewer's library: watchlist, per-episode progress and their watch status.
 *
 * Signed in, it reads and writes the same Supabase rows the Android app uses
 * (`watchlist`, `watch_progress`) with the same merge rule — newest wins, a
 * missing timestamp loses, remote-only rows join, removals prune. Signed out,
 * it lives in the server-side session, so a guest can still build a shelf and
 * a sign-in folds that shelf into the account.
 */

declare(strict_types=1);

final class Library
{
    private const GUEST_KEY = 'guest_library';
    private const CACHE_KEY = 'library_cache';
    private const CACHE_TTL = 20;

    private static ?array $state = null;

    public static function isGuest(): bool
    {
        return !Auth::isSignedIn();
    }

    /** @return array{watchlist:array<int,array>,progress:array<int,array>} */
    public static function all(bool $fresh = false): array
    {
        if (!Auth::isSignedIn()) {
            $guest = $_SESSION[self::GUEST_KEY] ?? null;
            return [
                'watchlist' => is_array($guest['watchlist'] ?? null) ? $guest['watchlist'] : [],
                'progress' => is_array($guest['progress'] ?? null) ? $guest['progress'] : [],
            ];
        }

        if (self::$state !== null && !$fresh) {
            return self::$state;
        }

        $cached = $_SESSION[self::CACHE_KEY] ?? null;
        if (!$fresh && is_array($cached)
            && (int) ($cached['at'] ?? 0) > time() - self::CACHE_TTL
            && is_array($cached['state'] ?? null)) {
            self::$state = $cached['state'];
            return self::$state;
        }

        self::$state = self::pull();
        $_SESSION[self::CACHE_KEY] = ['at' => time(), 'state' => self::$state];
        return self::$state;
    }

    public static function watchlist(): array
    {
        return self::all()['watchlist'];
    }

    public static function progress(): array
    {
        return self::all()['progress'];
    }

    public static function isSaved(string $ref): bool
    {
        foreach (self::watchlist() as $entry) {
            if (($entry['item']['ref'] ?? null) === $ref) {
                return true;
            }
        }
        return false;
    }

    public static function statusOf(string $ref): ?string
    {
        foreach (self::watchlist() as $entry) {
            if (($entry['item']['ref'] ?? null) === $ref) {
                return $entry['watchStatus'] ?? 'planned';
            }
        }
        return null;
    }

    public static function progressFor(string $ref, ?int $season = null, ?int $episode = null): ?array
    {
        $matches = array_values(array_filter(
            self::progress(),
            static fn ($entry) => ($entry['item']['ref'] ?? null) === $ref,
        ));
        if ($matches === []) {
            return null;
        }
        if ($season !== null && $episode !== null) {
            foreach ($matches as $entry) {
                if ((int) $entry['season'] === $season && (int) $entry['episode'] === $episode) {
                    return $entry;
                }
            }
        }
        return $matches[0];
    }

    /** What "continue watching" shows: everything not essentially finished. */
    public static function continueWatching(int $limit = 20): array
    {
        $rows = array_values(array_filter(
            self::progress(),
            static function ($entry) {
                $duration = (int) ($entry['durationSeconds'] ?? 0);
                if ($duration <= 0) {
                    return true;
                }
                return ((int) $entry['positionSeconds'] / $duration) < 0.95;
            },
        ));
        usort($rows, static fn ($a, $b) => strcmp((string) $b['addedAt'], (string) $a['addedAt']));
        return array_slice($rows, 0, $limit);
    }

    // ── mutations ──────────────────────────────────────────────────────────

    public static function toggle(array $item): bool
    {
        $ref = (string) ($item['ref'] ?? '');
        if ($ref === '') {
            throw new RuntimeException('This title cannot be saved.');
        }
        $state = self::all(true);
        $exists = false;
        foreach ($state['watchlist'] as $entry) {
            if (($entry['item']['ref'] ?? null) === $ref) {
                $exists = true;
                break;
            }
        }

        if ($exists) {
            $state['watchlist'] = array_values(array_filter(
                $state['watchlist'],
                static fn ($entry) => ($entry['item']['ref'] ?? null) !== $ref,
            ));
        } else {
            array_unshift($state['watchlist'], self::newEntry($item));
        }

        self::save($state);
        return !$exists;
    }

    public static function setStatus(string $ref, string $status): void
    {
        $allowed = ['planned', 'watching', 'dropped', 'completed'];
        if (!in_array($status, $allowed, true)) {
            throw new RuntimeException('Unknown status.');
        }
        $state = self::all(true);
        foreach ($state['watchlist'] as &$entry) {
            if (($entry['item']['ref'] ?? null) === $ref) {
                $entry['watchStatus'] = $status;
                $entry['addedAt'] = gmdate('c');
            }
        }
        unset($entry);
        self::save($state);
    }

    public static function remove(string $ref): void
    {
        $state = self::all(true);
        $state['watchlist'] = array_values(array_filter(
            $state['watchlist'],
            static fn ($entry) => ($entry['item']['ref'] ?? null) !== $ref,
        ));
        self::save($state);
    }

    public static function clearHistory(): void
    {
        $state = self::all(true);
        $state['progress'] = [];
        self::save($state);
    }

    /**
     * Records where playback stopped. Called on a timer and on leave, so it is
     * deliberately cheap: one row in memory, one upsert in the background.
     */
    public static function markProgress(array $entry): void
    {
        $ref = (string) ($entry['item']['ref'] ?? '');
        if ($ref === '') {
            return;
        }
        $state = self::all(true);
        $row = [
            'item' => $entry['item'],
            'addedAt' => gmdate('c'),
            'season' => max(0, (int) ($entry['season'] ?? 1)),
            'episode' => max(1, (int) ($entry['episode'] ?? 1)),
            'positionSeconds' => max(0, (int) round((float) ($entry['positionSeconds'] ?? 0))),
            'durationSeconds' => max(0, (int) round((float) ($entry['durationSeconds'] ?? 0))),
            'episodeCount' => max(0, (int) ($entry['episodeCount'] ?? 0)),
            'seasonCounts' => is_array($entry['seasonCounts'] ?? null) ? $entry['seasonCounts'] : [],
            'runtimeMinutes' => max(0, (int) ($entry['runtimeMinutes'] ?? 0)),
            'watchStatus' => 'watching',
        ];

        $state['progress'] = array_values(array_filter(
            $state['progress'],
            static fn ($existing) => !(
                ($existing['item']['ref'] ?? null) === $ref
                && (int) $existing['season'] === $row['season']
                && (int) $existing['episode'] === $row['episode']
            ),
        ));
        array_unshift($state['progress'], $row);
        $state['progress'] = array_slice($state['progress'], 0, 400);

        // A title being watched belongs on the shelf too, as on Android.
        foreach ($state['watchlist'] as &$saved) {
            if (($saved['item']['ref'] ?? null) === $ref) {
                $saved['watchStatus'] = 'watching';
                $saved['addedAt'] = $row['addedAt'];
            }
        }
        unset($saved);

        self::save($state);
    }

    private static function newEntry(array $item): array
    {
        return [
            'item' => $item,
            'addedAt' => gmdate('c'),
            'season' => 1,
            'episode' => 1,
            'positionSeconds' => 0,
            'durationSeconds' => 0,
            'episodeCount' => (int) ($item['episodeCount'] ?? 0),
            'seasonCounts' => [],
            'runtimeMinutes' => 0,
            'watchStatus' => 'planned',
        ];
    }

    private static function save(array $state): void
    {
        self::$state = $state;
        if (Auth::isSignedIn()) {
            $_SESSION[self::CACHE_KEY] = ['at' => time(), 'state' => $state];
            self::push($state);
            return;
        }
        $_SESSION[self::GUEST_KEY] = $state;
    }

    // ── cloud ──────────────────────────────────────────────────────────────

    /** Pulls both shelves, folds in a guest shelf, and pushes the result back. */
    public static function pull(): array
    {
        $token = Auth::accessToken();
        $userId = Auth::userId();
        if (!$token || !$userId) {
            return ['watchlist' => [], 'progress' => []];
        }

        $watch = Supabase::rest(
            'watchlist',
            'GET',
            'select=*&user_id=eq.' . rawurlencode($userId) . '&order=added_at.desc',
            null,
            $token,
        );
        $progress = Supabase::rest(
            'watch_progress',
            'GET',
            'select=*&user_id=eq.' . rawurlencode($userId) . '&order=updated_at.desc',
            null,
            $token,
        );

        $remoteWatchlist = [];
        foreach ($watch['status'] < 400 ? $watch['body'] : [] as $row) {
            $entry = self::fromRow($row, 'added_at');
            if ($entry) {
                $remoteWatchlist[] = $entry;
            }
        }
        $remoteProgress = [];
        foreach ($progress['status'] < 400 ? $progress['body'] : [] as $row) {
            $entry = self::fromRow($row, 'updated_at');
            if ($entry) {
                $remoteProgress[] = $entry;
            }
        }

        $guest = $_SESSION[self::GUEST_KEY] ?? null;
        $guestWatchlist = is_array($guest['watchlist'] ?? null) ? $guest['watchlist'] : [];
        $guestProgress = is_array($guest['progress'] ?? null) ? $guest['progress'] : [];

        $watchlist = self::merge($remoteWatchlist, $guestWatchlist, 'ref');
        $mergedProgress = self::merge($remoteProgress, $guestProgress, 'episode');

        $state = ['watchlist' => $watchlist, 'progress' => $mergedProgress];

        if ($guestWatchlist !== [] || $guestProgress !== []) {
            unset($_SESSION[self::GUEST_KEY]);
            self::push($state);
        }

        return $state;
    }

    /** @param array<int,array> $local @param array<int,array> $remote */
    private static function merge(array $local, array $remote, string $mode): array
    {
        $map = [];
        foreach ([...$local, ...$remote] as $entry) {
            $ref = (string) ($entry['item']['ref'] ?? '');
            if ($ref === '') {
                continue;
            }
            $key = $mode === 'episode'
                ? $ref . ':' . (int) $entry['season'] . ':' . (int) $entry['episode']
                : $ref;
            $held = $map[$key] ?? null;
            if ($held === null || self::isNewer((string) $entry['addedAt'], (string) $held['addedAt'])) {
                $map[$key] = $entry;
            }
        }
        $merged = array_values($map);
        usort($merged, static fn ($a, $b) => strcmp((string) $b['addedAt'], (string) $a['addedAt']));
        return $merged;
    }

    private static function isNewer(string $incoming, string $held): bool
    {
        if ($incoming === '') {
            return false;
        }
        if ($held === '') {
            return true;
        }
        return strtotime($incoming) > strtotime($held);
    }

    /** @param array<string,mixed> $row */
    private static function fromRow(array $row, string $addedAtKey): ?array
    {
        $item = $row['item'] ?? null;
        if (!is_array($item) || empty($item['ref'])) {
            return null;
        }
        $counts = is_array($row['season_counts'] ?? null) ? $row['season_counts'] : [];
        $seasonCounts = [];
        foreach ($counts as $season => $count) {
            if ((int) $season > 0 && (int) $count > 0) {
                $seasonCounts[(int) $season] = (int) $count;
            }
        }
        return [
            'item' => $item,
            'addedAt' => (string) ($row[$addedAtKey] ?? ''),
            'season' => (int) ($row['season'] ?? 1),
            'episode' => (int) ($row['episode'] ?? 1),
            'positionSeconds' => (int) ($row['position_seconds'] ?? 0),
            'durationSeconds' => (int) ($row['duration_seconds'] ?? 0),
            'episodeCount' => (int) ($row['episode_count'] ?? 0),
            'seasonCounts' => $seasonCounts,
            'runtimeMinutes' => (int) ($row['runtime_minutes'] ?? 0),
            'watchStatus' => (string) ($row['watch_status'] ?? 'planned'),
        ];
    }

    /** Upserts the whole shelf and prunes rows this device removed. */
    public static function push(?array $state = null): void
    {
        $token = Auth::accessToken();
        $userId = Auth::userId();
        if (!$token || !$userId) {
            return;
        }
        $state ??= self::all(true);

        try {
            if ($state['watchlist'] !== []) {
                $rows = [];
                foreach ($state['watchlist'] as $entry) {
                    $rows[] = [
                        'user_id' => $userId,
                        'media_ref' => (string) $entry['item']['ref'],
                        'item' => $entry['item'],
                        'added_at' => $entry['addedAt'] ?: gmdate('c'),
                        'watch_status' => $entry['watchStatus'],
                    ];
                }
                Supabase::rest('watchlist', 'POST', 'on_conflict=user_id,media_ref', $rows, $token);
            }

            $remote = Supabase::rest(
                'watchlist',
                'GET',
                'select=media_ref&user_id=eq.' . rawurlencode($userId),
                null,
                $token,
            );
            $keep = array_flip(array_map(
                static fn ($entry) => (string) $entry['item']['ref'],
                $state['watchlist'],
            ));
            foreach ($remote['status'] < 400 ? $remote['body'] : [] as $row) {
                $ref = (string) ($row['media_ref'] ?? '');
                if ($ref !== '' && !isset($keep[$ref])) {
                    Supabase::rest(
                        'watchlist',
                        'DELETE',
                        'user_id=eq.' . rawurlencode($userId) . '&media_ref=eq.' . rawurlencode($ref),
                        null,
                        $token,
                    );
                }
            }

            if ($state['progress'] !== []) {
                $rows = [];
                foreach ($state['progress'] as $entry) {
                    $rows[] = [
                        'user_id' => $userId,
                        'media_ref' => (string) $entry['item']['ref'],
                        'item' => $entry['item'],
                        'season' => (int) $entry['season'],
                        'episode' => (int) $entry['episode'],
                        'position_seconds' => (int) $entry['positionSeconds'],
                        'duration_seconds' => (int) $entry['durationSeconds'],
                        'episode_count' => (int) $entry['episodeCount'],
                        'season_counts' => $entry['seasonCounts'],
                        'runtime_minutes' => (int) $entry['runtimeMinutes'],
                        'updated_at' => $entry['addedAt'] ?: gmdate('c'),
                    ];
                }
                Supabase::rest(
                    'watch_progress',
                    'POST',
                    'on_conflict=user_id,media_ref,season,episode',
                    $rows,
                    $token,
                );
            }

            $remoteProgress = Supabase::rest(
                'watch_progress',
                'GET',
                'select=media_ref,season,episode&user_id=eq.' . rawurlencode($userId),
                null,
                $token,
            );
            $keepProgress = [];
            foreach ($state['progress'] as $entry) {
                $keepProgress[$entry['item']['ref'] . ':' . (int) $entry['season'] . ':' . (int) $entry['episode']] = true;
            }
            foreach ($remoteProgress['status'] < 400 ? $remoteProgress['body'] : [] as $row) {
                $key = ($row['media_ref'] ?? '') . ':' . (int) ($row['season'] ?? 0) . ':' . (int) ($row['episode'] ?? 0);
                if (!isset($keepProgress[$key])) {
                    Supabase::rest(
                        'watch_progress',
                        'DELETE',
                        'user_id=eq.' . rawurlencode($userId)
                        . '&media_ref=eq.' . rawurlencode((string) $row['media_ref'])
                        . '&season=eq.' . (int) $row['season']
                        . '&episode=eq.' . (int) $row['episode'],
                        null,
                        $token,
                    );
                }
            }
        } catch (Throwable $error) {
            // A push that fails leaves the local/session shelf intact; the next
            // change tries again. Logged, never hidden from the log.
            log_error($error);
        }
    }
}
