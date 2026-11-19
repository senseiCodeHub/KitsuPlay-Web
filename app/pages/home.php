<?php
/** Home: hero, continue watching, then the catalogue rails. */

$rows = Content::home();
$continue = Library::continueWatching(12);
$progressMap = [];
foreach ($continue as $entry) {
    $progressMap[(string) $entry['item']['ref']] = $entry;
}

$heroItems = [];
foreach ($rows as $row) {
    foreach ($row['items'] as $item) {
        if (count($heroItems) >= 6) {
            break 2;
        }
        if (!empty($item['backdropUrl']) || !empty($item['posterUrl'])) {
            $heroItems[] = $item;
        }
    }
}

$watchHref = static function (array $item) use ($progressMap): string {
    $ref = (string) $item['ref'];
    $entry = $progressMap[$ref] ?? null;
    $season = $entry ? (int) $entry['season'] : 1;
    $episode = $entry ? (int) $entry['episode'] : 1;
    return url('watch') . '?ref=' . rawurlencode($ref) . '&season=' . $season . '&episode=' . $episode;
};
?>
<?php if ($heroItems !== []): ?>
    <section class="hero" data-hero>
        <?php foreach ($heroItems as $index => $item): ?>
            <div class="hero__slide<?= $index === 0 ? ' is-active' : '' ?>" data-hero-slide>
                <?php if (!empty($item['backdropUrl']) || !empty($item['posterUrl'])): ?>
                    <img class="hero__image" src="<?= e((string) ($item['backdropUrl'] ?: $item['posterUrl'])) ?>"
                         alt="" <?= $index === 0 ? 'fetchpriority="high"' : 'loading="lazy"' ?> referrerpolicy="no-referrer">
                <?php endif; ?>
                <span class="hero__fade"></span>
                <div class="hero__content">
                    <h1><?= e((string) $item['title']) ?></h1>
                    <?php if (meta_line($item) !== ''): ?>
                        <p class="hero__meta"><?= e(meta_line($item)) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($item['overview'])): ?>
                        <p class="hero__overview"><?= e(mb_substr((string) $item['overview'], 0, 220)) ?></p>
                    <?php endif; ?>
                    <div class="hero__actions">
                        <a class="btn btn--primary" href="<?= e($watchHref($item)) ?>">
                            <?= icon('play') ?> Play now
                        </a>
                        <button class="btn btn--ghost" type="button"
                                data-watchlist
                                data-item="<?= e(json_encode($item, JSON_UNESCAPED_SLASHES)) ?>">
                            <?= icon(Library::isSaved((string) $item['ref']) ? 'check' : 'plus') ?>
                            <span><?= Library::isSaved((string) $item['ref']) ? 'In watchlist' : 'Watchlist' ?></span>
                        </button>
                        <a class="btn btn--round" href="<?= e(url('title/' . $item['ref'])) ?>" aria-label="More info">
                            <?= icon('info') ?>
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        <div class="hero__dots">
            <?php foreach ($heroItems as $index => $item): ?>
                <button type="button" class="<?= $index === 0 ? 'is-active' : '' ?>" data-hero-dot="<?= $index ?>"
                        aria-label="Show <?= e((string) $item['title']) ?>"></button>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<div class="home">
    <?php if ($continue !== []): ?>
        <?= rail('Continue Watching', array_map(static fn ($entry) => $entry['item'], $continue), url('history'), $progressMap) ?>
    <?php endif; ?>

    <?php if ($rows === []): ?>
        <div class="empty">
            <?= icon('alert', 'icon icon--lg') ?>
            <h3>The catalogue could not be reached</h3>
            <p>Check the connection and reload the page.</p>
            <a class="btn btn--primary" href="<?= e(url('/')) ?>">Reload</a>
        </div>
    <?php else: ?>
        <?php foreach ($rows as $row): ?>
            <?= rail((string) $row['title'], $row['items'], (string) $row['more'], $progressMap) ?>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
