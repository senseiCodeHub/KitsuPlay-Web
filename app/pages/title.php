<?php
/** One title: artwork, facts, episodes and the actions around them. */

$detail = Content::detail((string) $ref);
if (!$detail) {
    http_response_code(404);
    render('error', [
        'title' => 'Title not found',
        'message' => 'This title is not in the catalogue, or the link is out of date.',
    ]);
    return;
}

$item = $detail['item'];
$refValue = (string) $item['ref'];
$saved = Library::isSaved($refValue);
$progress = Library::progressFor($refValue);
$season = max(1, (int) ($_GET['season'] ?? ($progress['season'] ?? 1)));
$episodes = $detail['episodes'];
if ($episodes === [] && $detail['seasons'] !== []) {
    try {
        $episodes = Content::episodesFor($refValue, $season);
    } catch (Throwable $failure) {
        log_error($failure);
        $episodes = [];
    }
}

$firstEpisode = $episodes[0] ?? null;
$resumeSeason = $progress ? (int) $progress['season'] : (int) ($firstEpisode['season'] ?? 1);
$resumeEpisode = $progress ? (int) $progress['episode'] : (int) ($firstEpisode['number'] ?? 1);
$watchHref = url('watch') . '?ref=' . rawurlencode($refValue) . '&season=' . $resumeSeason . '&episode=' . $resumeEpisode;

// A film ships as one stream with no season or episode number; the app reads
// that shape as a film and never names an S/E tag or an episode browser for it.
$isFilm = str_contains($refValue, '-movie-')
    || ($episodes !== [] && !array_filter(
        $episodes,
        static fn (array $entry): bool => (int) $entry['season'] > 0,
    ));

$title = (string) $item['title'];
$runtime = (int) ($detail['runtime'] ?? 0);
$metaParts = array_filter([
    $item['year'] ?? null,
    $runtime > 0 ? ($runtime >= 60 ? floor($runtime / 60) . 'h ' . ($runtime % 60) . 'm' : $runtime . 'm') : null,
    $item['badge'] ?? null,
]);
?>
<div class="detail">
    <div class="detail__backdrop">
        <?php if (!empty($item['backdropUrl']) || !empty($item['posterUrl'])): ?>
            <img src="<?= e((string) ($item['backdropUrl'] ?: $item['posterUrl'])) ?>" alt="" referrerpolicy="no-referrer">
        <?php endif; ?>
        <span class="detail__fade"></span>
    </div>

    <div class="detail__top">
        <div class="detail__poster">
            <?php if (!empty($item['posterUrl'])): ?>
                <img src="<?= e((string) $item['posterUrl']) ?>" alt="<?= e($title) ?>" referrerpolicy="no-referrer">
            <?php endif; ?>
        </div>
        <div class="detail__info">
            <h1><?= e($title) ?></h1>
            <div class="detail__meta">
                <?php if ($metaParts !== []): ?><span><?= e(implode(' · ', $metaParts)) ?></span><?php endif; ?>
                <?= rating_html(isset($item['rating']) ? (float) $item['rating'] : null) ?>
                <?php if (!empty($detail['tagline'])): ?><span class="detail__tag"><?= e((string) $detail['tagline']) ?></span><?php endif; ?>
            </div>
            <?php if (!empty($detail['overview'])): ?>
                <p class="detail__overview"><?= e((string) $detail['overview']) ?></p>
            <?php endif; ?>
            <?php if (!empty($detail['genres'])): ?>
                <div class="chips chips--tight">
                    <?php foreach ($detail['genres'] as $genre): ?>
                        <span class="chip chip--static"><?= e((string) $genre) ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="detail__actions">
                <a class="btn btn--primary" href="<?= e($watchHref) ?>">
                    <?= icon('play') ?>
                    <?= $progress && (int) $progress['positionSeconds'] > 30 ? 'Resume' : 'Play now' ?>
                </a>
                <button class="btn btn--ghost" type="button" data-watchlist
                        data-item="<?= e(json_encode($item, JSON_UNESCAPED_SLASHES)) ?>">
                    <?= icon($saved ? 'check' : 'plus') ?>
                    <span><?= $saved ? 'In watchlist' : 'Add to watchlist' ?></span>
                </button>
                <button class="btn btn--round" type="button" data-share
                        data-title="<?= e($title) ?>" aria-label="Share"><?= icon('share') ?></button>
            </div>

            <?php if ($progress && !$isFilm): ?>
                <p class="detail__resume">
                    Last watched: <?= e(sprintf('S%02dE%02d', (int) $progress['season'], (int) $progress['episode'])) ?>
                    · <?= (int) floor(((int) $progress['positionSeconds']) / 60) ?>m in
                </p>
            <?php elseif ($progress): ?>
                <p class="detail__resume">
                    Last watched: <?= (int) floor(((int) $progress['positionSeconds']) / 60) ?>m in
                </p>
            <?php endif; ?>

            <?php if (!empty($detail['audio'])): ?>
                <p class="detail__fact"><strong>Audio</strong> <?= e(implode(', ', array_slice((array) $detail['audio'], 0, 8))) ?></p>
            <?php endif; ?>
            <?php if (!empty($detail['subtitles'])): ?>
                <p class="detail__fact"><strong>Subtitles</strong>
                    <?= e(implode(', ', array_map(static fn ($sub) => (string) ($sub['label'] ?? ''), array_slice((array) $detail['subtitles'], 0, 8)))) ?>
                </p>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($episodes !== [] && !$isFilm): ?>
        <section class="detail__episodes">
            <div class="section-head">
                <h2>Episodes</h2>
                <?php if ($detail['seasons'] !== [] && str_starts_with($refValue, 'tmdb-series-')): ?>
                    <form method="get" action="<?= e(url('title/' . $refValue)) ?>" class="field field--inline">
                        <select name="season" data-autosubmit aria-label="Season">
                            <?= season_options($detail['seasons'], $season) ?>
                        </select>
                    </form>
                <?php endif; ?>
            </div>
            <div class="episodes">
                <?php foreach ($episodes as $episode): ?>
                    <a class="episode" href="<?= e(url('watch') . '?ref=' . rawurlencode($refValue) . '&season=' . (int) $episode['season'] . '&episode=' . (int) $episode['number']) ?>">
                        <?php if (!empty($episode['stillUrl'])): ?>
                            <span class="episode__still"><img src="<?= e((string) $episode['stillUrl']) ?>" alt="" loading="lazy" referrerpolicy="no-referrer"></span>
                        <?php endif; ?>
                        <span class="episode__text">
                            <strong><?= e((string) ($episode['label'] ?? $episode['number'])) ?></strong>
                            <?php if (!empty($episode['title'])): ?><small><?= e((string) $episode['title']) ?></small><?php endif; ?>
                        </span>
                        <?= icon('play') ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if (!empty($detail['cast'])): ?>
        <section class="detail__section">
            <div class="section-head"><h2>Cast</h2></div>
            <div class="cast">
                <?php foreach (array_slice((array) $detail['cast'], 0, 12) as $member): ?>
                    <div class="cast__member">
                        <span class="cast__photo">
                            <?php if (!empty($member['photoUrl'])): ?>
                                <img src="<?= e((string) $member['photoUrl']) ?>" alt="" loading="lazy" referrerpolicy="no-referrer">
                            <?php endif; ?>
                        </span>
                        <strong><?= e((string) ($member['name'] ?? '')) ?></strong>
                        <?php if (!empty($member['role'])): ?><small><?= e((string) $member['role']) ?></small><?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if (!empty($detail['similar'])): ?>
        <div class="detail__similar">
            <?= rail('More like this', (array) $detail['similar']) ?>
        </div>
    <?php endif; ?>
</div>
