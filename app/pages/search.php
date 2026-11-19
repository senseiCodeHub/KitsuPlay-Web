<?php
/** Search: server-rendered first answer, debounced live results after. */

$term = trim((string) ($_GET['q'] ?? ''));
$results = [];
$error = null;
if ($term !== '') {
    try {
        $results = Content::search($term);
    } catch (Throwable $failure) {
        log_error($failure);
        $error = 'Search is unavailable right now.';
    }
}
?>
<div class="page">
    <header class="page__head">
        <div>
            <h1>Search</h1>
        </div>
    </header>

    <form class="searchbar" method="get" action="<?= e(url('search')) ?>" data-search-form>
        <span class="searchbar__icon"><?= icon('search') ?></span>
        <input type="search" name="q" value="<?= e($term) ?>" placeholder="Anime, films, series, Bangla titles…"
               autocomplete="off" minlength="2" maxlength="80" inputmode="search" autofocus
               data-search-input aria-label="Search KitsuPlay">
        <button class="btn btn--primary" type="submit">Search</button>
    </form>

    <?php if ($error): ?>
        <div class="notice notice--error"><?= icon('alert') ?><span><?= e($error) ?></span></div>
    <?php endif; ?>

    <div id="search-results" data-search-results>
        <?php if ($term === ''): ?>
            <div class="empty">
                <?= icon('compass', 'icon icon--lg') ?>
                <h3>Type at least two letters</h3>
                <p>TMDB, AniList and the MLBD catalogue are searched together.</p>
            </div>
        <?php elseif ($results === []): ?>
            <div class="empty">
                <?= icon('search', 'icon icon--lg') ?>
                <h3>No matches for “<?= e($term) ?>”</h3>
                <p>Try the original spelling, a shorter title, or the release year.</p>
            </div>
        <?php else: ?>
            <?= poster_grid($results) ?>
        <?php endif; ?>
    </div>
</div>
