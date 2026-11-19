<?php
/** GET /api/browse — one page of a browse grid (used by infinite scroll). */

declare(strict_types=1);

$type = (string) ($_GET['type'] ?? 'movie');
if (!in_array($type, ['movie', 'tv', 'anime', 'mlbd'], true)) {
    json_out(['ok' => false, 'error' => 'Unknown catalogue'], 400);
}
$page = max(1, min(20, (int) ($_GET['page'] ?? 1)));

$filters = [
    'genre' => (string) ($_GET['genre'] ?? ''),
    'year' => (string) ($_GET['year'] ?? ''),
    'country' => (string) ($_GET['country'] ?? ''),
    'language' => (string) ($_GET['language'] ?? ''),
    'sort' => (string) ($_GET['sort'] ?? 'popularity.desc'),
    'collection' => (string) ($_GET['collection'] ?? ''),
];

$allowedSorts = ['popularity.desc', 'vote_average.desc', 'primary_release_date.desc', 'first_air_date.desc'];
if (!in_array($filters['sort'], $allowedSorts, true)) {
    $filters['sort'] = 'popularity.desc';
}
if (!preg_match('/^\d{1,4}$/', $filters['year'])) {
    $filters['year'] = '';
}
if (!preg_match('/^[A-Z]{2}$/', $filters['country'])) {
    $filters['country'] = '';
}
if (!preg_match('/^[a-z]{2}$/', $filters['language'])) {
    $filters['language'] = '';
}
if (!preg_match('/^[a-z0-9\-]{0,40}$/', $filters['genre'])) {
    $filters['genre'] = '';
}
if (!preg_match('/^[a-z0-9\-]{0,40}$/', $filters['collection'])) {
    $filters['collection'] = '';
}

try {
    $items = Content::browse($type, $filters, $page);
    json_out(['ok' => true, 'items' => $items, 'page' => $page, 'hasMore' => count($items) >= 18]);
} catch (Throwable $error) {
    log_error($error);
    json_out(['ok' => false, 'error' => 'That catalogue is unavailable right now.'], 502);
}
