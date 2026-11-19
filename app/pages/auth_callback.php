<?php
/** Google's PKCE callback: exchange the code on the server, then go home. */

$code = (string) ($_GET['code'] ?? '');
$state = (string) ($_GET['state'] ?? '');
$error = (string) ($_GET['error_description'] ?? $_GET['error'] ?? '');

if ($error !== '') {
    render('login', [
        'title' => 'Sign in',
        'error' => 'Google sign-in was cancelled or refused.',
    ], 'auth');
    return;
}

if ($code === '' || $state === '') {
    render('login', [
        'title' => 'Sign in',
        'error' => 'That sign-in link is incomplete. Start again.',
    ], 'auth');
    return;
}

try {
    Auth::exchangeGoogleCode($code, $state);
    redirect('/');
} catch (Throwable $failure) {
    log_error($failure);
    render('login', [
        'title' => 'Sign in',
        'error' => $failure->getMessage(),
    ], 'auth');
}
