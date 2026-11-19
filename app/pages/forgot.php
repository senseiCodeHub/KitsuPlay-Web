<?php
/** Password reset: code first, then a new password on the fresh session. */

$step = (string) ($_GET['step'] ?? 'email');
$email = trim((string) ($_SESSION['reset_email'] ?? ''));
$error = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_verify();
    try {
        $action = (string) ($_POST['action'] ?? 'send');
        if ($action === 'send') {
            throttle('reset-send', 3, 600);
            $email = trim((string) ($_POST['email'] ?? ''));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Enter a valid email address.');
            }
            Auth::sendOtp($email);
            $_SESSION['reset_email'] = $email;
            redirect(url('forgot-password') . '?step=code');
        }

        if ($action === 'verify') {
            throttle('reset-verify', 8, 600);
            $email = trim((string) ($_POST['email'] ?? $email));
            $code = preg_replace('/\D/', '', (string) ($_POST['code'] ?? '')) ?? '';
            if (strlen($code) !== 6) {
                throw new RuntimeException('Enter the 6-digit code from your email.');
            }
            Auth::verifyOtp($email, $code);
            $_SESSION['reset_email'] = $email;
            redirect(url('forgot-password') . '?step=password');
        }

        if ($action === 'password') {
            $password = (string) ($_POST['password'] ?? '');
            $confirm = (string) ($_POST['confirm'] ?? '');
            if ($password !== $confirm) {
                throw new RuntimeException('The two passwords do not match.');
            }
            Auth::updatePassword($password);
            unset($_SESSION['reset_email']);
            flash('auth', 'Password updated — sign in with the new one.');
            redirect(url('login'));
        }
    } catch (Throwable $failure) {
        $error = $failure->getMessage();
    }
}
?>
<div class="auth-card">
    <h1>Reset password</h1>

    <?php if ($error): ?><div class="notice notice--error"><?= icon('alert') ?><span><?= e($error) ?></span></div><?php endif; ?>

    <?php if ($step === 'email'): ?>
        <p class="auth-card__sub">We will email a 6-digit code to prove it is you.</p>
        <form method="post" action="<?= e(url('forgot-password')) ?>" class="form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="send">
            <label class="field">
                <span>Email</span>
                <input type="email" name="email" value="<?= e($email) ?>" required autocomplete="email">
            </label>
            <button class="btn btn--primary btn--block" type="submit">Send code</button>
        </form>
    <?php elseif ($step === 'code'): ?>
        <p class="auth-card__sub">Enter the code sent to <strong><?= e($email) ?></strong>.</p>
        <form method="post" action="<?= e(url('forgot-password')) ?>" class="form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="verify">
            <input type="hidden" name="email" value="<?= e($email) ?>">
            <label class="field">
                <span>6-digit code</span>
                <input class="code-input" type="text" name="code" inputmode="numeric" pattern="[0-9]{6}"
                       maxlength="6" required autocomplete="one-time-code" placeholder="000000">
            </label>
            <button class="btn btn--primary btn--block" type="submit">Continue</button>
        </form>
    <?php else: ?>
        <p class="auth-card__sub">Choose a new password for your account.</p>
        <form method="post" action="<?= e(url('forgot-password')) ?>" class="form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="password">
            <label class="field">
                <span>New password</span>
                <input type="password" name="password" required minlength="8" autocomplete="new-password">
            </label>
            <label class="field">
                <span>Confirm password</span>
                <input type="password" name="confirm" required minlength="8" autocomplete="new-password">
            </label>
            <button class="btn btn--primary btn--block" type="submit">Save password</button>
        </form>
    <?php endif; ?>

    <div class="auth-links">
        <a href="<?= e(url('login')) ?>">Back to sign in</a>
    </div>
</div>
