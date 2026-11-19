<?php
/**
 * GET /api/play?ref=…&season=…&episode=…
 *
 * Resolves the stream for one episode. The title and year come from the
 * catalogue record the server already has, never from the query string, so a
 * crafted URL cannot aim the matcher at another title.
 */

declare(strict_types=1);

$ref = (string) ($_GET['ref'] ?? '');
$season = max(0, (int) ($_GET['season'] ?? 1));
$episode = max(1, (int) ($_GET['episode'] ?? 1));

if (!preg_match('/^[a-z]+-[a-z]+-[a-z0-9\-]+$/i', $ref)) {
    json_out(['ok' => false, 'error' => 'Unknown title.'], 400);
}

$detail = Content::detail($ref);
if (!$detail) {
    json_out(['ok' => false, 'error' => 'This title could not be read.'], 404);
}

$item = $detail['item'];
$title = (string) ($item['title'] ?? '');
$year = (int) ($item['year'] ?? 0);

try {
    $result = Content::playable($ref, $title, $year, $season, $episode);
    if (($result['ok'] ?? false) !== true) {
        json_out($result, 404);
    }
    json_out($result);
} catch (Throwable $error) {
    log_error($error);
    json_out(['ok' => false, 'error' => 'The stream lookup failed. Try again.'], 502);
}
