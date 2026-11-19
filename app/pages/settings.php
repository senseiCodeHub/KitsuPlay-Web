<?php
/** Player and device preferences. Stored in this browser, never on the server. */
?>
<div class="page page--narrow">
    <header class="page__head">
        <div>
            <h1>Settings</h1>
        </div>
    </header>

    <section class="panel">
        <h2>Playback</h2>
        <div class="setting">
            <div><strong>Autoplay next episode</strong><small>Start the next episode when one finishes.</small></div>
            <label class="switch"><input type="checkbox" data-pref="autoplayNext" checked><span></span></label>
        </div>
        <div class="setting">
            <div><strong>Preferred quality</strong><small>The player starts here when the ladder offers it.</small></div>
            <select data-pref="preferredQuality">
                <option value="auto">Auto</option>
                <option value="1080p">1080p</option>
                <option value="720p">720p</option>
                <option value="480p">480p</option>
            </select>
        </div>
        <div class="setting">
            <div><strong>Subtitles</strong><small>Turn caption tracks on by default.</small></div>
            <label class="switch"><input type="checkbox" data-pref="subtitlesOn"><span></span></label>
        </div>
        <div class="setting">
            <div><strong>Subtitle language</strong><small>Used to pick the default caption track.</small></div>
            <select data-pref="subtitleLanguage">
                <option value="en">English</option>
                <option value="bn">Bangla</option>
                <option value="hi">Hindi</option>
                <option value="ar">Arabic</option>
            </select>
        </div>
        <div class="setting">
            <div><strong>Reduce data</strong><small>Start on 480p and avoid 1080p on slow connections.</small></div>
            <label class="switch"><input type="checkbox" data-pref="reducedData"><span></span></label>
        </div>
    </section>

    <section class="panel">
        <h2>Family Mode</h2>
        <div class="setting">
            <div>
                <strong>Hide mature catalogues</strong>
                <small>The 18+ MLBD shelves stay off this browser while Family Mode is on.</small>
            </div>
            <label class="switch"><input type="checkbox" data-pref="familyMode"><span></span></label>
        </div>
    </section>

    <section class="panel">
        <h2>App</h2>
        <div class="rows">
            <button class="row" type="button" data-install><?= icon('plus') ?> Install KitsuPlay <span>PWA</span></button>
            <button class="row" type="button" data-enable-push-settings><?= icon('bell') ?> Browser notifications <span>→</span></button>
            <a class="row" href="<?= e(url('notifications')) ?>"><?= icon('info') ?> Announcements <span>→</span></a>
            <a class="row" href="<?= e(url('offline')) ?>"><?= icon('alert') ?> Offline page <span>→</span></a>
        </div>
    </section>

    <section class="panel">
        <h2>Account</h2>
        <?php if (Auth::isSignedIn()): ?>
            <p class="muted">Signed in as <strong><?= e((string) (Auth::user()['email'] ?? '')) ?></strong>.</p>
            <form method="post" action="<?= e(url('logout')) ?>">
                <?= csrf_field() ?>
                <button class="btn btn--ghost" type="submit"><?= icon('logout') ?> Sign out</button>
            </form>
        <?php else: ?>
            <p class="muted">You are browsing as a guest. Sign in to sync watchlist and progress across devices.</p>
            <a class="btn btn--primary" href="<?= e(url('login')) ?>">Sign in</a>
        <?php endif; ?>
    </section>

    <p class="legal">KitsuPlay · built by RobiCodes · <a href="<?= e(url('offline')) ?>">Offline</a></p>
</div>
