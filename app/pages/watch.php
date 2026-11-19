<?php
/** The player page. Playback itself is assets/js/player.js. */

$ref = (string) ($_GET['ref'] ?? '');
$season = max(0, (int) ($_GET['season'] ?? 1));
$episode = max(0, (int) ($_GET['episode'] ?? 1));

$detail = $ref !== '' ? Content::detail($ref) : null;
if (!$detail) {
    http_response_code(404);
    render('error', [
        'title' => 'Title not found',
        'message' => 'That title could not be opened.',
    ], 'bare');
    return;
}

$item = $detail['item'];
$refValue = (string) $item['ref'];
$episodes = $detail['episodes'];
if ($episodes === [] && $detail['seasons'] !== []) {
    try {
        $episodes = Content::episodesFor($refValue, $season);
    } catch (Throwable $failure) {
        log_error($failure);
        $episodes = [];
    }
}

// A film ships as one stream with no season or episode number; the app reads
// that shape as a film and never names an S/E tag for it.
$isFilm = str_contains($refValue, '-movie-')
    || ($episodes !== [] && !array_filter(
        $episodes,
        static fn (array $entry): bool => (int) $entry['season'] > 0,
    ));

// Snap the requested episode onto one the catalogue actually ships, so the
// header, the highlight and the saved progress all describe the file that
// plays — the same nearest-neighbour placement the app does.
if ($episodes !== []) {
    $match = null;
    foreach ($episodes as $entry) {
        if ((int) $entry['season'] === $season && (int) $entry['number'] === $episode) {
            $match = $entry;
            break;
        }
    }
    if ($match === null) {
        $sameSeason = array_values(array_filter(
            $episodes,
            static fn (array $entry): bool => (int) $entry['season'] === $season,
        ));
        $pool = $sameSeason !== [] ? $sameSeason : $episodes;
        $match = $pool[0];
        $nearest = PHP_INT_MAX;
        foreach ($pool as $entry) {
            $gap = abs((int) $entry['number'] - $episode);
            if ($gap < $nearest) {
                $nearest = $gap;
                $match = $entry;
            }
        }
    }
    $season = (int) $match['season'];
    $episode = (int) $match['number'];
}

$seasonCounts = [];
foreach ($episodes as $entry) {
    $seasonCounts[(int) $entry['season']] = max(
        $seasonCounts[(int) $entry['season']] ?? 0,
        (int) $entry['number'],
    );
}
$episodeCount = count($episodes);
$currentIndex = null;
foreach ($episodes as $index => $entry) {
    if ((int) $entry['season'] === $season && (int) $entry['number'] === $episode) {
        $currentIndex = $index;
        break;
    }
}

$resume = Library::progressFor($refValue, $season, $episode);

$playPayload = [
    'ref' => $refValue,
    'item' => $item,
    'resume' => $resume ? (int) $resume['positionSeconds'] : 0,
    'title' => (string) $item['title'],
    'season' => $season,
    'episode' => $episode,
    'poster' => $item['posterUrl'] ?? null,
    'episodeCount' => $episodeCount,
    'seasonCounts' => $seasonCounts,
    'runtimeMinutes' => (int) ($detail['runtime'] ?? 0),
    'playUrl' => url('api/play'),
    'csrf' => csrf_token(),
    'progressUrl' => url('api/library'),
    'nextUrl' => $currentIndex !== null && isset($episodes[$currentIndex + 1])
        ? url('watch') . '?ref=' . rawurlencode($refValue)
            . '&season=' . (int) $episodes[$currentIndex + 1]['season']
            . '&episode=' . (int) $episodes[$currentIndex + 1]['number']
        : null,
    'episodes' => array_map(static fn ($entry) => [
        'season' => (int) $entry['season'],
        'number' => (int) $entry['number'],
        'label' => (string) ($entry['label'] ?? $entry['number']),
        'title' => $entry['title'] ?? null,
    ], $episodes),
];

$needsPlayer = true;
?>
<div class="watch">
    <header class="watch__bar">
        <a class="iconbtn" href="<?= e(url('title/' . $refValue)) ?>" aria-label="Back to title"><?= icon('chevron-left') ?></a>
        <div class="watch__title">
            <strong><?= e((string) $item['title']) ?></strong>
            <?php if ($episodeCount > 0 && !$isFilm): ?>
                <small><?= e(sprintf('S%02dE%02d', $season, $episode)) ?></small>
            <?php endif; ?>
        </div>
        <span class="watch__status" data-player-status>Ready</span>
    </header>

    <div class="player" data-player
         data-payload="<?= e(json_encode($playPayload, JSON_UNESCAPED_SLASHES)) ?>">
        <video class="player__video" data-video playsinline preload="metadata"></video>

        <div class="player__spinner" data-spinner>
            <span class="spinner"></span>
        </div>

        <div class="player__error" data-player-error hidden>
            <?= icon('alert', 'icon icon--lg') ?>
            <h2 data-error-title>This stream could not be played</h2>
            <p data-error-body></p>
            <div class="player__error-actions">
                <button class="btn btn--primary" type="button" data-retry>Try again</button>
                <a class="btn btn--ghost" href="<?= e(url('title/' . $refValue)) ?>">Back to title</a>
            </div>
        </div>

        <div class="player__big" data-big-play><?= icon('play') ?></div>

        <div class="player__controls" data-controls>
            <input class="player__seek" type="range" min="0" max="1000" value="0" step="1"
                   data-seek aria-label="Seek">
            <div class="player__buffered" data-buffered></div>

            <div class="player__row">
                <button class="pbtn" type="button" data-play aria-label="Play"><?= icon('play') ?></button>
                <button class="pbtn pbtn--sm" type="button" data-back aria-label="Back 10 seconds"><?= icon('rewind') ?></button>
                <button class="pbtn pbtn--sm" type="button" data-forward aria-label="Forward 10 seconds"><?= icon('forward') ?></button>
                <div class="player__volume">
                    <button class="pbtn pbtn--sm" type="button" data-mute aria-label="Mute"><?= icon('volume') ?></button>
                    <input type="range" min="0" max="100" value="100" data-volume aria-label="Volume">
                </div>
                <span class="player__time"><span data-time-current>0:00</span> / <span data-time-total>0:00</span></span>

                <div class="player__spacer"></div>

                <button class="pbtn pbtn--sm" type="button" data-speed-toggle aria-label="Playback speed">
                    <span data-speed-label>1×</span>
                </button>
                <div class="player__menu" data-menu="speed" hidden>
                    <?php foreach ([0.5, 0.75, 1, 1.25, 1.5, 1.75, 2] as $rate): ?>
                        <button type="button" data-rate="<?= $rate ?>"><?= $rate ?>×</button>
                    <?php endforeach; ?>
                </div>

                <button class="pbtn pbtn--sm" type="button" data-track-toggle="subs" aria-label="Subtitles">
                    <?= icon('captions') ?>
                </button>
                <div class="player__menu" data-menu="subs" hidden></div>

                <button class="pbtn pbtn--sm" type="button" data-track-toggle="quality" aria-label="Quality">
                    <span data-quality-label>Auto</span>
                </button>
                <div class="player__menu" data-menu="quality" hidden></div>

                <button class="pbtn pbtn--sm" type="button" data-track-toggle="audio" aria-label="Audio">
                    <span data-audio-label>Audio</span>
                </button>
                <div class="player__menu" data-menu="audio" hidden></div>

                <button class="pbtn pbtn--sm" type="button" data-pip aria-label="Picture in picture"><?= icon('pip') ?></button>
                <button class="pbtn pbtn--sm" type="button" data-fullscreen aria-label="Fullscreen"><?= icon('fullscreen') ?></button>
            </div>
        </div>
    </div>

    <div class="watch__under">
        <div class="watch__nav">
            <?php if ($currentIndex !== null && $currentIndex > 0): ?>
                <?php $prev = $episodes[$currentIndex - 1]; ?>
                <a class="btn btn--ghost" href="<?= e(url('watch') . '?ref=' . rawurlencode($refValue) . '&season=' . (int) $prev['season'] . '&episode=' . (int) $prev['number']) ?>">
                    <?= icon('chevron-left') ?> <?= e((string) ($prev['label'] ?? 'Previous')) ?>
                </a>
            <?php endif; ?>
            <?php if ($currentIndex !== null && isset($episodes[$currentIndex + 1])): ?>
                <?php $next = $episodes[$currentIndex + 1]; ?>
                <a class="btn btn--ghost" href="<?= e(url('watch') . '?ref=' . rawurlencode($refValue) . '&season=' . (int) $next['season'] . '&episode=' . (int) $next['number']) ?>">
                    <?= e((string) ($next['label'] ?? 'Next')) ?> <?= icon('chevron-right') ?>
                </a>
            <?php endif; ?>
            <button class="btn btn--ghost" type="button" data-autoplay-toggle>
                <span data-autoplay-label>Autoplay next: on</span>
            </button>
        </div>

        <?php if ($episodeCount > 0 && !$isFilm): ?>
            <section class="watch__episodes">
                <h2>Episodes</h2>
                <div class="episodes episodes--compact">
                    <?php foreach ($episodes as $entry): ?>
                        <?php $active = (int) $entry['season'] === $season && (int) $entry['number'] === $episode; ?>
                        <a class="episode<?= $active ? ' is-active' : '' ?>"
                           href="<?= e(url('watch') . '?ref=' . rawurlencode($refValue) . '&season=' . (int) $entry['season'] . '&episode=' . (int) $entry['number']) ?>"
                           data-episode-link
                           data-season="<?= (int) $entry['season'] ?>" data-episode="<?= (int) $entry['number'] ?>">
                            <span class="episode__text">
                                <strong><?= e((string) ($entry['label'] ?? $entry['number'])) ?></strong>
                                <?php if (!empty($entry['title'])): ?><small><?= e((string) $entry['title']) ?></small><?php endif; ?>
                            </span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
    </div>
</div>
