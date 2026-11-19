<?php
/** Account profile: name, avatar, numbers. */

$user = Auth::user();
$profile = Auth::profile() ?? [];
$error = null;
$notice = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_verify();
    try {
        $action = (string) ($_POST['action'] ?? 'save');
        if ($action === 'signout') {
            Auth::signOut();
            redirect('/');
        }
        $name = trim((string) ($_POST['name'] ?? ''));
        $avatar = trim((string) ($_POST['avatar_url'] ?? ''));
        if (mb_strlen($name) < 2) {
            throw new RuntimeException('Name must be at least 2 characters.');
        }
        if ($avatar !== '' && !filter_var($avatar, FILTER_VALIDATE_URL)) {
            throw new RuntimeException('That avatar link is not a valid URL.');
        }
        if ($avatar !== '' && !str_starts_with($avatar, 'https://')) {
            throw new RuntimeException('Avatar links must use https.');
        }
        Auth::updateProfile(['name' => $name, 'avatar_url' => $avatar]);
        $profile = Auth::profile() ?? [];
        $notice = 'Profile saved.';
    } catch (Throwable $failure) {
        $error = $failure->getMessage();
    }
}

$watchlist = Library::watchlist();
$progress = Library::progress();
$episodesWatched = 0;
foreach ($progress as $entry) {
    if (((int) $entry['positionSeconds']) > 30) {
        $episodesWatched++;
    }
}
$name = (string) ($profile['name'] ?? '');
if ($name === '') {
    $name = (string) explode('@', (string) ($user['email'] ?? 'KitsuPlay'))[0];
}
$avatar = (string) ($profile['avatar_url'] ?? '');
$initials = strtoupper(mb_substr($name, 0, 2));
?>
<div class="page page--narrow">
    <header class="page__head">
        <div>
            <h1>Profile</h1>
        </div>
        <form method="post" action="<?= e(url('profile')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="signout">
            <button class="btn btn--ghost" type="submit"><?= icon('logout') ?> Sign out</button>
        </form>
    </header>

    <?php if ($error): ?><div class="notice notice--error"><?= icon('alert') ?><span><?= e($error) ?></span></div><?php endif; ?>
    <?php if ($notice): ?><div class="notice notice--success"><?= icon('check') ?><span><?= e($notice) ?></span></div><?php endif; ?>

    <section class="profile-card">
        <span class="avatar avatar--lg">
            <?php if ($avatar !== ''): ?>
                <img src="<?= e($avatar) ?>" alt="" referrerpolicy="no-referrer">
            <?php else: ?>
                <?= e($initials) ?>
            <?php endif; ?>
        </span>
        <div>
            <h2><?= e($name) ?></h2>
            <p><?= e((string) ($user['email'] ?? '')) ?></p>
            <p class="profile-card__sync"><?= icon('check') ?> Library synced with the KitsuPlay app</p>
        </div>
    </section>

    <div class="stats">
        <div class="stat"><strong><?= count($watchlist) ?></strong><span>Saved titles</span></div>
        <div class="stat"><strong><?= $episodesWatched ?></strong><span>Episodes started</span></div>
        <div class="stat"><strong><?= count($progress) ?></strong><span>Resume marks</span></div>
    </div>

    <section class="panel">
        <h2>Edit profile</h2>
        <form method="post" action="<?= e(url('profile')) ?>" class="form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save">
            <label class="field">
                <span>Display name</span>
                <input type="text" name="name" value="<?= e($name) ?>" required minlength="2" maxlength="60">
            </label>
            <label class="field">
                <span>Avatar image URL</span>
                <input type="url" name="avatar_url" value="<?= e($avatar) ?>" placeholder="https://…" inputmode="url">
                <small>Any https image. Leave empty to use your initials.</small>
            </label>
            <button class="btn btn--primary" type="submit">Save changes</button>
        </form>
    </section>

    <section class="panel">
        <h2>Account</h2>
        <div class="rows">
            <a class="row" href="<?= e(url('watchlist')) ?>"><?= icon('bookmark') ?> Watchlist <span><?= count($watchlist) ?></span></a>
            <a class="row" href="<?= e(url('history')) ?>"><?= icon('history') ?> Watch history <span><?= count($progress) ?></span></a>
            <a class="row" href="<?= e(url('settings')) ?>"><?= icon('settings') ?> Settings <span>→</span></a>
            <a class="row" href="<?= e(url('notifications')) ?>"><?= icon('bell') ?> Announcements <span>→</span></a>
        </div>
    </section>
</div>
