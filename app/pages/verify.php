<?php
/** The 6-digit code that finishes registration (or a fresh sign-in). */

$email = trim((string) ($_GET['email'] ?? ($_SESSION['pending_email'] ?? '')));
$mode = (string) ($_GET['mode'] ?? 'register');
$error = null;
$notice = flash('verify');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_verify();
    try {
        $email = trim((string) ($_POST['email'] ?? $email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Enter the email you registered with.');
        }
        if (($_POST['action'] ?? '') === 'resend') {
            throttle('otp-send', 3, 300);
            Auth::sendOtp($email);
            flash('verify', 'A new code is on its way to ' . $email . '.');
            redirect(url('verify') . '?email=' . rawurlencode($email) . '&mode=' . rawurlencode($mode));
        }
        throttle('otp-verify', 8, 600);
        $code = preg_replace('/\D/', '', (string) ($_POST['code'] ?? '')) ?? '';
        if (strlen($code) !== 6) {
            throw new RuntimeException('Enter the 6-digit code from your email.');
        }
        Auth::verifyOtp($email, $code);
        unset($_SESSION['pending_email']);
        if ($mode === 'reset') {
            redirect(url('forgot-password') . '?step=password');
        }
        redirect('/');
    } catch (Throwable $failure) {
        $error = $failure->getMessage();
    }
}
?>
<div class="auth-card">
    <h1>Verify your email</h1>
    <p class="auth-card__sub">
        We sent a 6-digit code to
        <strong><?= e($email !== '' ? $email : 'your inbox') ?></strong>.
        Enter it below to finish.
    </p>

    <?php if ($notice): ?><div class="notice notice--success"><?= icon('check') ?><span><?= e($notice) ?></span></div><?php endif; ?>
    <?php if ($error): ?><div class="notice notice--error"><?= icon('alert') ?><span><?= e($error) ?></span></div><?php endif; ?>

    <form method="post" action="<?= e(url('verify') . '?mode=' . rawurlencode($mode)) ?>" class="form">
        <?= csrf_field() ?>
        <label class="field">
            <span>Email</span>
            <input type="email" name="email" value="<?= e($email) ?>" required autocomplete="email">
        </label>
        <label class="field">
            <span>6-digit code</span>
            <input class="code-input" type="text" name="code" inputmode="numeric" pattern="[0-9]{6}"
                   maxlength="6" autocomplete="one-time-code" required placeholder="000000">
        </label>
        <button class="btn btn--primary btn--block" type="submit">Verify and continue</button>
    </form>

    <form method="post" action="<?= e(url('verify') . '?mode=' . rawurlencode($mode)) ?>" class="auth-resend">
        <?= csrf_field() ?>
        <input type="hidden" name="email" value="<?= e($email) ?>">
        <input type="hidden" name="action" value="resend">
        <button class="linkbtn" type="submit">Send a new code</button>
    </form>

    <div class="auth-links">
        <a href="<?= e(url('login')) ?>">Back to sign in</a>
    </div>
</div>
