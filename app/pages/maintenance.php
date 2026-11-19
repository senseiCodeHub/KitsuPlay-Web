<?php /** The maintenance wall. */ ?>
<section class="state state--bare">
    <span class="brand brand--auth">
        <span class="brand__mark">K</span>
        <span class="brand__name">Kitsu<span>Play</span></span>
    </span>
    <span class="state__icon"><?= icon('alert', 'icon icon--lg') ?></span>
    <h1>We are updating KitsuPlay</h1>
    <p><?= e((string) ($message ?? 'The site is briefly under maintenance. Please check back in a few minutes.')) ?></p>
    <div class="state__actions">
        <a class="btn btn--primary" href="<?= e(url('/')) ?>">Try again</a>
        <?php if (Auth::isSignedIn()): ?>
            <a class="btn btn--ghost" href="<?= e(url('profile')) ?>">Your account</a>
        <?php endif; ?>
    </div>
</section>
