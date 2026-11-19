<?php
/** Sign out (POST only, CSRF-checked). */

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    redirect('/');
}
csrf_verify();
Auth::signOut();
flash('auth', 'You are signed out. Your library is safe on your account.');
redirect('/');
