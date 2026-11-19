<?php
/** Browse: filters + a grid that pages itself as the viewer scrolls. */

$type = $type ?? 'movie';
$query = $_GET;
$page = max(1, (int) ($_GET['page'] ?? 1));
$collection = isset($_GET['collection']) ? (string) $_GET['collection'] : ($type === 'mlbd' ? 'bangla-movie' : '');
$filters = [
    'genre' => isset($_GET['genre']) ? (string) $_GET['genre'] : '',
    'year' => isset($_GET['year']) ? (string) $_GET['year'] : '',
    'country' => isset($_GET['country']) ? (string) $_GET['country'] : '',
    'language' => isset($_GET['language']) ? (string) $_GET['language'] : '',
    'sort' => isset($_GET['sort']) ? (string) $_GET['sort'] : 'popularity.desc',
    'collection' => $collection,
];

try {
    $items = Content::browse($type, $filters, 1);
    $error = null;
} catch (Throwable $failure) {
    log_error($failure);
    $items = [];
    $error = 'That catalogue is unavailable right now.';
}

$title = match ($type) {
    'tv' => 'Series',
    'anime' => 'Anime',
    'mlbd' => 'Bangla & Indian',
    default => 'Movies',
};
$title = $title . ' · Browse';

$collections = [
    'bangla-movie' => 'Bangla Movies',
    'bangla-series' => 'Bangla Web Series',
    'bangla-dubbed' => 'Bangla Dubbed',
    'bangla-dubbed-series' => 'Bangla Dubbed Series',
    'hindi-movie' => 'Hindi Movies',
    'hindi-dubbed' => 'Hindi Dubbed',
    'south-indian-hindi-dubbed' => 'South Indian Hindi Dub',
    'korean-drama' => 'Korean Drama',
];

$genres = match ($type) {
    'movie' => Tmdb::GENRES['movie'],
    'tv' => Tmdb::GENRES['tv'],
    'anime' => [
        'Action' => 'Action', 'Adventure' => 'Adventure', 'Comedy' => 'Comedy',
        'Drama' => 'Drama', 'Fantasy' => 'Fantasy', 'Horror' => 'Horror',
        'Romance' => 'Romance', 'Sci-Fi' => 'Sci-Fi', 'Slice of Life' => 'Slice of Life',
        'Sports' => 'Sports', 'Supernatural' => 'Supernatural', 'Thriller' => 'Thriller',
    ],
    default => [],
};

$years = range((int) date('Y') + 1, 1980);

/** Keeps the other filters in a link. */
$linkFor = static function (string $key, string $value) use ($query, $type): string {
    $next = $query;
    $next[$key] = $value;
    unset($next['page']);
    return url('browse/' . $type) . '?' . http_build_query($next);
};
?>
<div class="page">
    <header class="page__head">
        <div>
            <h1><?= e($title) ?></h1>
        </div>
    </header>

    <?php if ($type === 'mlbd'): ?>
        <div class="chips">
            <?php foreach ($collections as $slug => $label): ?>
                <a class="chip<?= $collection === $slug ? ' is-active' : '' ?>"
                   href="<?= e($linkFor('collection', $slug)) ?>"><?= e($label) ?></a>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <form class="filters" method="get" action="<?= e(url('browse/' . $type)) ?>">
            <label class="field field--inline">
                <span>Genre</span>
                <select name="genre" data-autosubmit>
                    <option value="">Any genre</option>
                    <?php foreach ($genres as $id => $label): ?>
                        <?php $value = $type === 'anime' ? (string) $id : (string) $id; ?>
                        <option value="<?= e($value) ?>"<?= $filters['genre'] === (string) $id ? ' selected' : '' ?>>
                            <?= e((string) $label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="field field--inline">
                <span>Year</span>
                <select name="year" data-autosubmit>
                    <option value="">Any year</option>
                    <?php foreach ($years as $year): ?>
                        <option value="<?= $year ?>"<?= $filters['year'] === (string) $year ? ' selected' : '' ?>><?= $year ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <?php if ($type !== 'anime'): ?>
                <label class="field field--inline">
                    <span>Language</span>
                    <select name="language" data-autosubmit>
                        <option value="">Any language</option>
                        <?php foreach (Tmdb::LANGUAGES as $code => $label): ?>
                            <option value="<?= e((string) $code) ?>"<?= $filters['language'] === (string) $code ? ' selected' : '' ?>>
                                <?= e((string) $label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="field field--inline">
                    <span>Country</span>
                    <select name="country" data-autosubmit>
                        <option value="">Any country</option>
                        <?php foreach (Tmdb::COUNTRIES as $code => $label): ?>
                            <option value="<?= e((string) $code) ?>"<?= $filters['country'] === (string) $code ? ' selected' : '' ?>>
                                <?= e((string) $label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="field field--inline">
                    <span>Sort</span>
                    <select name="sort" data-autosubmit>
                        <?php foreach ([
                            'popularity.desc' => 'Most popular',
                            'vote_average.desc' => 'Top rated',
                            'primary_release_date.desc' => 'Newest',
                        ] as $value => $label): ?>
                            <option value="<?= e($value) ?>"<?= $filters['sort'] === $value ? ' selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            <?php endif; ?>
            <button class="btn btn--primary" type="submit">Apply</button>
        </form>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="notice notice--error"><?= icon('alert') ?><span><?= e($error) ?></span></div>
    <?php endif; ?>

    <div id="browse-grid"
         data-browse
         data-type="<?= e($type) ?>"
         data-collection="<?= e($collection) ?>"
         data-genre="<?= e($filters['genre']) ?>"
         data-year="<?= e($filters['year']) ?>"
         data-country="<?= e($filters['country']) ?>"
         data-language="<?= e($filters['language']) ?>"
         data-sort="<?= e($filters['sort']) ?>">
        <?= poster_grid($items, 'No titles match these filters', 'Try a different genre or year.') ?>
    </div>
    <div class="load-sentinel" data-browse-sentinel>
        <span class="spinner" hidden></span>
    </div>
</div>
