<?php
/** Full-bleed pages: the player, maintenance and error screens. */
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title><?= e($title ?? 'KitsuPlay') ?> · KitsuPlay</title>
    <meta name="theme-color" content="#0B0B0D">
    <meta name="csrf" content="<?= e(csrf_token()) ?>">
    <link rel="icon" href="<?= e(asset('icons/favicon.svg')) ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="bare-body" data-path="<?= e(request_path()) ?>" data-base="<?= e(api_prefix()) ?>" data-signed-in="<?= Auth::isSignedIn() ? '1' : '0' ?>">
<?php require APP_DIR . '/views/icons.php'; ?>
<?= $content ?>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
<?php if (!empty($needsPlayer)): ?>
    <script src="<?= e(asset('vendor/hls.min.js')) ?>" defer></script>
    <script src="<?= e(asset('js/player.js')) ?>" defer></script>
<?php endif; ?>
</body>
</html>
