<?php /** Served by the service worker when the network is gone. */ ?>
<section class="state state--bare">
    <span class="brand brand--auth">
        <span class="brand__mark">K</span>
        <span class="brand__name">Kitsu<span>Play</span></span>
    </span>
    <span class="state__icon"><?= icon('alert', 'icon icon--lg') ?></span>
    <h1>You are offline</h1>
    <p>The catalogue needs a connection. Your watchlist and progress are safe — they sync when you are back.</p>
    <div class="state__actions">
        <a class="btn btn--primary" href="<?= e(url('/')) ?>">Retry</a>
    </div>
</section>
