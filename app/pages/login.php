<?php
/** Sign in with the same account the app uses. */

$error = null;
$next = (string) ($_GET['next'] ?? ($_POST['next'] ?? '/'));
if (!str_starts_with($next, '/') || str_starts_with($next, '//')) {
    $next = '/';
}
if (Auth::isSignedIn()) {
    redirect(local_url($next));
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_verify();
    try {
        throttle('login', 8, 600);
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
            throw new RuntimeException('Enter your email and password.');
        }
        Auth::signIn($email, $password);
        forget_old();
        redirect(local_url($next));
    } catch (Throwable $failure) {
        $error = $failure->getMessage();
        remember_old(['email' => (string) ($_POST['email'] ?? '')]);
    }
}

$flash = flash('auth');
?>
<div class="auth-card">
    <h1>Welcome back</h1>
    <p class="auth-card__sub">Sign in with your KitsuPlay account — the same one from the app.</p>

    <?php if ($flash): ?><div class="notice notice--success"><?= icon('check') ?><span><?= e($flash) ?></span></div><?php endif; ?>
    <?php if ($error): ?><div class="notice notice--error"><?= icon('alert') ?><span><?= e($error) ?></span></div><?php endif; ?>

    <form method="post" action="<?= e(url('login')) ?>" class="form">
        <?= csrf_field() ?>
        <input type="hidden" name="next" value="<?= e($next) ?>">
        <label class="field">
            <span>Email</span>
            <input type="email" name="email" value="<?= e(old('email')) ?>" required autocomplete="email" inputmode="email">
        </label>
        <label class="field">
            <span>Password</span>
            <input type="password" name="password" required autocomplete="current-password" minlength="8">
        </label>
        <button class="btn btn--primary btn--block" type="submit">Sign in</button>
    </form>

    <div class="auth-divider"><span>or</span></div>
    <a class="btn btn--google btn--block" href="<?= e(url('auth/google')) ?>">
        <?= icon('google') ?> Continue with Google
    </a>

    <div class="auth-links">
        <a href="<?= e(url('forgot-password')) ?>">Forgot password?</a>
        <a href="<?= e(url('register')) ?>">Create an account</a>
    </div>
</div>
