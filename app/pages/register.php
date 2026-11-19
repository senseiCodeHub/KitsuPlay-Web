<?php
/** Registration runs through the app's own OTP Edge Function. */

$error = null;
if (Auth::isSignedIn()) {
    redirect('/');
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_verify();
    try {
        throttle('register', 5, 900);
        $name = trim((string) ($_POST['name'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['confirm'] ?? '');
        if (mb_strlen($name) < 2) {
            throw new RuntimeException('Tell us your name (at least 2 letters).');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('That email address does not look right.');
        }
        if (mb_strlen($password) < 8) {
            throw new RuntimeException('Password must be at least 8 characters.');
        }
        if ($password !== $confirm) {
            throw new RuntimeException('The two passwords do not match.');
        }
        Auth::register($name, $email, $password);
        $_SESSION['pending_email'] = $email;
        forget_old();
        redirect(url('verify') . '?email=' . rawurlencode($email) . '&mode=register');
    } catch (Throwable $failure) {
        $error = $failure->getMessage();
        remember_old([
            'name' => (string) ($_POST['name'] ?? ''),
            'email' => (string) ($_POST['email'] ?? ''),
        ]);
    }
}
?>
<div class="auth-card">
    <h1>Create your account</h1>
    <p class="auth-card__sub">One KitsuPlay account for the app and the web.</p>

    <?php if ($error): ?><div class="notice notice--error"><?= icon('alert') ?><span><?= e($error) ?></span></div><?php endif; ?>

    <form method="post" action="<?= e(url('register')) ?>" class="form">
        <?= csrf_field() ?>
        <label class="field">
            <span>Name</span>
            <input type="text" name="name" value="<?= e(old('name')) ?>" required minlength="2" maxlength="60" autocomplete="name">
        </label>
        <label class="field">
            <span>Email</span>
            <input type="email" name="email" value="<?= e(old('email')) ?>" required autocomplete="email" inputmode="email">
        </label>
        <label class="field">
            <span>Password</span>
            <input type="password" name="password" required minlength="8" autocomplete="new-password">
            <small>At least 8 characters.</small>
        </label>
        <label class="field">
            <span>Confirm password</span>
            <input type="password" name="confirm" required minlength="8" autocomplete="new-password">
        </label>
        <button class="btn btn--primary btn--block" type="submit">Create account</button>
    </form>

    <div class="auth-divider"><span>or</span></div>
    <a class="btn btn--google btn--block" href="<?= e(url('auth/google')) ?>">
        <?= icon('google') ?> Continue with Google
    </a>

    <div class="auth-links">
        <a href="<?= e(url('login')) ?>">Already have an account? Sign in</a>
    </div>
</div>
