<?php
/** GET /api/search?q= — the catalogue search behind the search page. */

declare(strict_types=1);

$term = trim((string) ($_GET['q'] ?? ''));
if (mb_strlen($term) < 2) {
    json_out(['ok' => true, 'results' => []]);
}
if (mb_strlen($term) > 80) {
    $term = mb_substr($term, 0, 80);
}

try {
    json_out(['ok' => true, 'results' => Content::search($term)]);
} catch (Throwable $error) {
    log_error($error);
    json_out(['ok' => false, 'error' => 'Search is unavailable right now.'], 502);
}
