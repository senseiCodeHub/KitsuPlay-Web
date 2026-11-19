<?php
/**
 * POST /api/library — watchlist switches and watch status.
 * POST /api/progress — playback position.
 *
 * Both require the session's CSRF token (`X-CSRF`). Guests write to their
 * server-side shelf; signed-in viewers write through to Supabase.
 */

declare(strict_types=1);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    json_out(['ok' => false, 'error' => 'Method not allowed'], 405);
}
csrf_verify();

$body = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($body)) {
    $body = $_POST;
}

$action = (string) ($body['action'] ?? '');
$ref = (string) ($body['ref'] ?? '');

try {
    switch ($action) {
        case 'toggle':
            $item = $body['item'] ?? null;
            if (!is_array($item) || empty($item['ref']) || empty($item['title'])) {
                json_out(['ok' => false, 'error' => 'This title cannot be saved.'], 400);
            }
            // Only the fields the Android app stores travel upward.
            $clean = [
                'ref' => (string) $item['ref'],
                'title' => (string) $item['title'],
                'posterUrl' => $item['posterUrl'] ?? null,
                'backdropUrl' => $item['backdropUrl'] ?? null,
                'overview' => mb_substr((string) ($item['overview'] ?? ''), 0, 1200),
                'year' => $item['year'] ?? null,
                'rating' => isset($item['rating']) ? (float) $item['rating'] : null,
                'badge' => $item['badge'] ?? null,
                'episodeCount' => isset($item['episodeCount']) ? (int) $item['episodeCount'] : null,
                'status' => $item['status'] ?? null,
                'genres' => is_array($item['genres'] ?? null) ? array_slice($item['genres'], 0, 8) : [],
                'originalLanguage' => $item['originalLanguage'] ?? null,
                'provider' => $item['provider'] ?? null,
            ];
            $saved = Library::toggle($clean);
            json_out(['ok' => true, 'saved' => $saved]);

        case 'status':
            Library::setStatus($ref, (string) ($body['status'] ?? ''));
            json_out(['ok' => true]);

        case 'remove':
            Library::remove($ref);
            json_out(['ok' => true]);

        case 'clear-history':
            Library::clearHistory();
            json_out(['ok' => true]);

        case 'progress':
            $item = $body['item'] ?? null;
            if (!is_array($item) || empty($item['ref'])) {
                json_out(['ok' => false, 'error' => 'Nothing to record.'], 400);
            }
            Library::markProgress([
                'item' => $item,
                'season' => (int) ($body['season'] ?? 1),
                'episode' => (int) ($body['episode'] ?? 1),
                'positionSeconds' => (float) ($body['positionSeconds'] ?? 0),
                'durationSeconds' => (float) ($body['durationSeconds'] ?? 0),
                'episodeCount' => (int) ($body['episodeCount'] ?? 0),
                'seasonCounts' => is_array($body['seasonCounts'] ?? null) ? $body['seasonCounts'] : [],
                'runtimeMinutes' => (int) ($body['runtimeMinutes'] ?? 0),
            ]);
            json_out(['ok' => true, 'synced' => !Library::isGuest()]);

        default:
            json_out(['ok' => false, 'error' => 'Unknown action'], 400);
    }
} catch (Throwable $error) {
    log_error($error);
    json_out(['ok' => false, 'error' => $error->getMessage()], 400);
}
