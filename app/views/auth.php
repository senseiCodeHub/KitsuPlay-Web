<?php
/** The auth screens: one centred card, no chrome. */
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title><?= e($title ?? 'Sign in') ?> · KitsuPlay</title>
    <meta name="theme-color" content="#0B0B0D">
    <meta name="csrf" content="<?= e(csrf_token()) ?>">
    <link rel="icon" href="<?= e(asset('icons/favicon.svg')) ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="auth-body" data-path="<?= e(request_path()) ?>">
<?php require APP_DIR . '/views/icons.php'; ?>
<main class="auth">
    <a class="brand brand--auth" href="<?= e(url('/')) ?>">
        <span class="brand__mark">K</span>
        <span class="brand__name">Kitsu<span>Play</span></span>
    </a>
    <?= $content ?>
    <p class="auth__foot">Same account as the KitsuPlay app · <a href="<?= e(url('/')) ?>">Back to home</a></p>
</main>
</body>
</html>
