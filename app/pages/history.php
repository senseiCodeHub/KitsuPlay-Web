<?php
/** Watch history: one row per episode mark, newest first. */

$entries = Library::progress();
$rows = [];
foreach ($entries as $entry) {
    $ref = (string) ($entry['item']['ref'] ?? '');
    if ($ref === '') {
        continue;
    }
    $rows[] = $entry;
}
?>
<div class="page">
    <header class="page__head">
        <div>
            <h1>Watch history</h1>
        </div>
        <?php if ($rows !== []): ?>
            <button class="btn btn--ghost" type="button" data-clear-history><?= icon('x') ?> Clear history</button>
        <?php endif; ?>
    </header>

    <?php if ($rows === []): ?>
        <div class="empty">
            <?= icon('history', 'icon icon--lg') ?>
            <h3>Nothing watched yet</h3>
            <p>Playback positions are saved automatically — even mid-episode.</p>
            <a class="btn btn--primary" href="<?= e(url('/')) ?>">Find something</a>
        </div>
    <?php else: ?>
        <div class="history">
            <?php foreach ($rows as $entry): ?>
                <?php
                $item = $entry['item'];
                $duration = (int) $entry['durationSeconds'];
                $position = (int) $entry['positionSeconds'];
                $percent = $duration > 0 ? min(100, ($position / $duration) * 100) : 0;
                ?>
                <div class="history__row">
                    <a class="history__poster" href="<?= e(url('title/' . $item['ref'])) ?>">
                        <?php if (!empty($item['posterUrl'])): ?>
                            <img src="<?= e((string) $item['posterUrl']) ?>" alt="" loading="lazy" referrerpolicy="no-referrer">
                        <?php endif; ?>
                    </a>
                    <div class="history__body">
                        <a class="history__title" href="<?= e(url('title/' . $item['ref'])) ?>"><?= e((string) $item['title']) ?></a>
                        <p class="history__meta">
                            <?php if ((int) $entry['season'] > 0 && (int) $entry['episode'] > 0): ?>
                                <?= e(sprintf('S%02dE%02d', (int) $entry['season'], (int) $entry['episode'])) ?> ·
                            <?php endif; ?>
                            <?= $percent > 0 ? (int) round($percent) . '% watched' : 'Started' ?>
                            · <?= e((string) date('j M Y', strtotime((string) $entry['addedAt']) ?: time())) ?>
                        </p>
                        <div class="history__bar"><span style="width:<?= (int) round($percent) ?>%"></span></div>
                    </div>
                    <a class="btn btn--ghost btn--sm"
                       href="<?= e(url('watch') . '?ref=' . rawurlencode((string) $item['ref']) . '&season=' . (int) $entry['season'] . '&episode=' . (int) $entry['episode']) ?>">
                        <?= icon('play') ?> Resume
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
