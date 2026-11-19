<?php
/** Announcements and browser push opt-in. */

$notices = Notices::active();
$unread = 0;
foreach ($notices as $notice) {
    if (!Notices::isRead($notice['id'])) {
        $unread++;
    }
}
$pushConfig = PUSH_CONFIGURED ? [
    'apiKey' => FIREBASE_CONFIG['apiKey'],
    'projectId' => FIREBASE_CONFIG['projectId'],
    'messagingSenderId' => FIREBASE_CONFIG['messagingSenderId'],
    'appId' => FIREBASE_CONFIG['appId'],
    'vapidKey' => FIREBASE_CONFIG['vapidKey'],
] : null;
?>
<div class="page page--narrow">
    <header class="page__head">
        <div>
            <h1>Announcements</h1>
        </div>
        <?php if ($unread > 0): ?>
            <button class="btn btn--ghost" type="button" data-read-all>Mark all read</button>
        <?php endif; ?>
    </header>

    <section class="push-card">
        <div>
            <h2><?= icon('bell') ?> Browser notifications</h2>
            <?php if ($pushConfig): ?>
                <p>Get an alert when an announcement or a server update lands. You can turn it off any time.</p>
            <?php else: ?>
                <p>
                    Push is not configured on this install yet. A Firebase <strong>Web</strong> app
                    (API key, project id, app id) and a VAPID key are needed — see <code>app/env.example.php</code>.
                </p>
            <?php endif; ?>
        </div>
        <button class="btn btn--primary" type="button" data-enable-push
                <?= $pushConfig ? '' : 'disabled' ?>
                data-config="<?= $pushConfig ? e(json_encode($pushConfig, JSON_UNESCAPED_SLASHES)) : '' ?>">
            Enable notifications
        </button>
    </section>

    <?php if ($notices === []): ?>
        <div class="empty">
            <?= icon('bell', 'icon icon--lg') ?>
            <h3>No announcements right now</h3>
            <p>New releases, fixes and maintenance windows show up here.</p>
        </div>
    <?php else: ?>
        <div class="notices">
            <?php foreach ($notices as $notice): ?>
                <?php $read = Notices::isRead($notice['id']); ?>
                <article class="notice-card notice-card--<?= e((string) $notice['kind']) ?><?= $read ? ' is-read' : '' ?>"
                         data-notice="<?= e((string) $notice['id']) ?>">
                    <div class="notice-card__head">
                        <span class="notice-card__kind"><?= icon($notice['kind'] === 'maintenance' ? 'alert' : 'info') ?></span>
                        <div>
                            <h2><?= e((string) $notice['title']) ?></h2>
                            <time><?= e((string) date('j M Y, H:i', strtotime((string) $notice['createdAt']) ?: time())) ?></time>
                        </div>
                        <?php if (!$read): ?><span class="pill">New</span><?php endif; ?>
                    </div>
                    <p class="notice-card__body"><?= nl2br(e((string) $notice['body'])) ?></p>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
