<?php
/** The saved shelf, with its watch-status switch. */

$entries = Library::watchlist();
$filter = (string) ($_GET['status'] ?? 'all');
$counts = ['all' => count($entries), 'planned' => 0, 'watching' => 0, 'completed' => 0, 'dropped' => 0];
foreach ($entries as $entry) {
    $status = (string) ($entry['watchStatus'] ?? 'planned');
    if (isset($counts[$status])) {
        $counts[$status]++;
    }
}
$shown = $filter === 'all'
    ? $entries
    : array_values(array_filter($entries, static fn ($entry) => ($entry['watchStatus'] ?? 'planned') === $filter));

$progressMap = [];
foreach (Library::progress() as $entry) {
    $ref = (string) $entry['item']['ref'];
    if (!isset($progressMap[$ref])) {
        $progressMap[$ref] = $entry;
    }
}
?>
<div class="page">
    <header class="page__head">
        <div>
            <h1>Watchlist</h1>
        </div>
        <?php if (Library::isGuest()): ?>
            <a class="btn btn--primary" href="<?= e(url('login') . '?next=' . rawurlencode('/watchlist')) ?>">Sign in to sync</a>
        <?php endif; ?>
    </header>

    <?php if (Library::isGuest()): ?>
        <div class="notice">
            <?= icon('info') ?>
            <span>You are browsing as a guest. This shelf stays on this browser — sign in to keep it on every device.</span>
        </div>
    <?php endif; ?>

    <div class="chips">
        <?php foreach (['all' => 'All', 'planned' => 'Planned', 'watching' => 'Watching', 'completed' => 'Completed', 'dropped' => 'Dropped'] as $key => $label): ?>
            <a class="chip<?= $filter === $key ? ' is-active' : '' ?>"
               href="<?= e(url('watchlist') . ($key === 'all' ? '' : '?status=' . $key)) ?>">
                <?= e($label) ?> <small><?= (int) ($counts[$key] ?? 0) ?></small>
            </a>
        <?php endforeach; ?>
    </div>

    <?php if ($shown === []): ?>
        <div class="empty">
            <?= icon('bookmark', 'icon icon--lg') ?>
            <h3>Nothing saved yet</h3>
            <p>Tap the bookmark on any title and it lands here, on every device.</p>
            <a class="btn btn--primary" href="<?= e(url('browse/movie')) ?>">Browse movies</a>
        </div>
    <?php else: ?>
        <div class="grid grid--library">
            <?php foreach ($shown as $entry): ?>
                <?php $item = $entry['item']; ?>
                <div class="library-card">
                    <?= poster_card($item, $progressMap[$item['ref']] ?? null) ?>
                    <div class="library-card__row">
                        <select data-status-select data-ref="<?= e((string) $item['ref']) ?>"
                                aria-label="Watch status for <?= e((string) $item['title']) ?>">
                            <?php foreach (['planned', 'watching', 'completed', 'dropped'] as $status): ?>
                                <option value="<?= $status ?>"<?= ($entry['watchStatus'] ?? 'planned') === $status ? ' selected' : '' ?>>
                                    <?= e(status_label($status)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button class="iconbtn iconbtn--danger" type="button" data-remove
                                data-ref="<?= e((string) $item['ref']) ?>"
                                aria-label="Remove <?= e((string) $item['title']) ?>"><?= icon('x') ?></button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
