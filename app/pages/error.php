<?php /** 404 / 500 state. */ ?>
<section class="state">
    <span class="state__icon"><?= icon('alert', 'icon icon--lg') ?></span>
    <h1><?= e((string) ($title ?? 'Something went wrong')) ?></h1>
    <p><?= e((string) ($message ?? 'Please try again.')) ?></p>
    <div class="state__actions">
        <a class="btn btn--primary" href="<?= e(url('/')) ?>">Go home</a>
        <a class="btn btn--ghost" href="<?= e(url('browse/movie')) ?>">Browse</a>
    </div>
</section>
