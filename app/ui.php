<?php
/**
 * Small render helpers shared by the pages. Every value they print is escaped.
 */

declare(strict_types=1);

function icon(string $name, string $class = 'icon'): string
{
    return '<svg class="' . e($class) . '" aria-hidden="true" focusable="false"><use href="#i-' . e($name) . '"></use></svg>';
}

function score_text(?float $rating): string
{
    if ($rating === null || $rating <= 0) {
        return '';
    }
    return '★ ' . number_format($rating, 1);
}

/** The one-line meta under a title. */
function meta_line(array $item): string
{
    $parts = [];
    if (!empty($item['year'])) {
        $parts[] = (string) $item['year'];
    }
    $score = score_text(isset($item['rating']) ? (float) $item['rating'] : null);
    if ($score !== '') {
        $parts[] = $score;
    }
    if (($item['status'] ?? null) === 'airing') {
        $parts[] = 'Airing';
    }
    return implode(' · ', $parts);
}

/** Meta line for cards: parts escaped, the star carries its own tint span. */
function meta_html(array $item): string
{
    $parts = [];
    $title = (string) ($item['title'] ?? '');
    if (!empty($item['year']) && !str_contains($title, (string) $item['year'])) {
        $parts[] = e((string) $item['year']);
    }
    $rating = isset($item['rating']) ? (float) $item['rating'] : 0.0;
    if ($rating > 0) {
        $parts[] = '<span class="card__star">★ ' . number_format($rating, 1) . '</span>';
    }
    if (($item['status'] ?? null) === 'airing') {
        $parts[] = 'Airing';
    }
    return implode(' · ', $parts);
}

function poster_card(array $item, ?array $progress = null, string $variant = 'poster'): string
{
    $ref = (string) ($item['ref'] ?? '');
    if ($ref === '') {
        return '';
    }
    $image = $variant === 'wide'
        ? ($item['backdropUrl'] ?: $item['posterUrl'])
        : ($item['posterUrl'] ?? '');
    $title = (string) ($item['title'] ?? '');
    $ratio = 0.0;
    if ($progress && (int) ($progress['durationSeconds'] ?? 0) > 0) {
        $ratio = min(100, ((int) $progress['positionSeconds'] / (int) $progress['durationSeconds']) * 100);
    }
    $meta = meta_html($item);

    ob_start();
    ?>
    <a class="card <?= $variant === 'wide' ? 'card--wide' : '' ?>" href="<?= e(url('title/' . $ref)) ?>">
        <span class="card__art">
            <?php if ($image): ?>
                <img src="<?= e((string) $image) ?>" alt="" loading="lazy" decoding="async"
                     referrerpolicy="no-referrer">
            <?php endif; ?>
            <span class="card__shade"></span>
            <?php if (!empty($item['badge'])): ?>
                <span class="card__badge"><?= e((string) $item['badge']) ?></span>
            <?php endif; ?>
            <span class="card__play"><?= icon('play') ?></span>
            <?php if ($ratio > 1): ?>
                <span class="card__progress"><span style="width:<?= (int) round($ratio) ?>%"></span></span>
            <?php endif; ?>
        </span>
        <span class="card__body">
            <span class="card__title"><?= e($title) ?></span>
            <?php if ($meta !== ''): ?><span class="card__meta"><?= $meta ?></span><?php endif; ?>
        </span>
    </a>
    <?php
    return (string) ob_get_clean();
}

/** One horizontal shelf. */
function rail(string $title, array $items, ?string $more = null, array $progress = []): string
{
    if ($items === []) {
        return '';
    }
    ob_start();
    ?>
    <section class="rail-block" data-rail>
        <div class="rail-head">
            <h2><?= e($title) ?></h2>
            <div class="rail-tools">
                <?php if ($more): ?>
                    <a class="rail-more" href="<?= e($more) ?>">See all</a>
                <?php endif; ?>
                <button class="rail-arrow" type="button" data-rail-prev aria-label="Scroll left"><?= icon('chevron-left') ?></button>
                <button class="rail-arrow" type="button" data-rail-next aria-label="Scroll right"><?= icon('chevron-right') ?></button>
            </div>
        </div>
        <div class="rail" data-rail-track>
            <?php foreach ($items as $item): ?>
                <?php $ref = (string) ($item['ref'] ?? ''); ?>
                <?= poster_card($item, $progress[$ref] ?? null) ?>
            <?php endforeach; ?>
        </div>
    </section>
    <?php
    return (string) ob_get_clean();
}

/** A grid of cards with an empty state. */
function poster_grid(array $items, string $emptyTitle = 'Nothing here yet', string $emptyBody = ''): string
{
    if ($items === []) {
        ob_start();
        ?>
        <div class="empty">
            <?= icon('compass', 'icon icon--lg') ?>
            <h3><?= e($emptyTitle) ?></h3>
            <?php if ($emptyBody !== ''): ?><p><?= e($emptyBody) ?></p><?php endif; ?>
        </div>
        <?php
        return (string) ob_get_clean();
    }
    ob_start();
    echo '<div class="grid">';
    foreach ($items as $item) {
        echo poster_card($item);
    }
    echo '</div>';
    return (string) ob_get_clean();
}

function status_label(string $status): string
{
    return match ($status) {
        'watching' => 'Watching',
        'completed' => 'Completed',
        'dropped' => 'Dropped',
        default => 'Planned',
    };
}

/** A tiny SVG-less star row for detail pages. */
function rating_html(?float $rating): string
{
    if ($rating === null || $rating <= 0) {
        return '';
    }
    return '<span class="rating">' . icon('star') . number_format($rating, 1) . '</span>';
}

function season_options(array $seasons, int $current): string
{
    $out = '';
    foreach ($seasons as $season) {
        $number = (int) ($season['season'] ?? 0);
        if ($number <= 0) {
            continue;
        }
        $out .= '<option value="' . $number . '"' . ($number === $current ? ' selected' : '') . '>'
            . 'Season ' . $number . ' · ' . (int) ($season['episodes'] ?? 0) . ' eps</option>';
    }
    return $out;
}
