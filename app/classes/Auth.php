<?php
/**
 * Accounts. One Supabase project, the same user rows the Android app writes to.
 *
 * Email + password and the OTP Edge Functions (`register`, `send-otp`,
 * `verify-otp`) are called exactly as the app calls them, and Google runs as a
 * server-side PKCE flow. The resulting tokens stay in the PHP session; no
 * access or refresh token is ever sent to the browser.
 */

declare(strict_types=1);

final class Auth
{
    private const SESSION_KEY = 'auth';

    public static function user(): ?array
    {
        $auth = $_SESSION[self::SESSION_KEY] ?? null;
        if (!is_array($auth) || empty($auth['access_token'])) {
            return null;
        }
        return is_array($auth['user'] ?? null) ? $auth['user'] : null;
    }

    public static function userId(): ?string
    {
        $user = self::user();
        return isset($user['id']) && is_string($user['id']) ? $user['id'] : null;
    }

    public static function accessToken(): ?string
    {
        $auth = $_SESSION[self::SESSION_KEY] ?? null;
        if (!is_array($auth) || empty($auth['access_token'])) {
            return null;
        }
        $expires = (int) ($auth['expires_at'] ?? 0);
        if ($expires > 0 && $expires <= time() + 60) {
            $refreshed = self::refresh();
            if ($refreshed === null) {
                return null;
            }
            $auth = $_SESSION[self::SESSION_KEY];
        }
        return is_string($auth['access_token'] ?? null) ? $auth['access_token'] : null;
    }

    public static function isSignedIn(): bool
    {
        return self::accessToken() !== null;
    }

    /** The profile row, or a metadata-derived fallback for a fresh account. */
    public static function profile(): ?array
    {
        $user = self::user();
        if (!$user) {
            return null;
        }
        $token = self::accessToken();
        if (!$token) {
            return null;
        }
        $cached = $_SESSION['profile_cache'] ?? null;
        if (is_array($cached) && ($cached['id'] ?? null) === ($user['id'] ?? null)
            && (int) ($cached['at'] ?? 0) > time() - 120) {
            return $cached['row'];
        }
        $result = Supabase::rest(
            'profiles',
            'GET',
            'select=id,name,avatar_url,subtitle_style&id=eq.' . rawurlencode((string) $user['id']) . '&limit=1',
            null,
            $token,
        );
        $row = $result['status'] < 400 && is_array($result['body'][0] ?? null)
            ? $result['body'][0]
            : null;
        $metadata = is_array($user['user_metadata'] ?? null) ? $user['user_metadata'] : [];
        $profile = [
            'id' => $user['id'],
            'name' => $row['name'] ?? ($metadata['name'] ?? $metadata['full_name'] ?? null),
            'avatar_url' => $row['avatar_url'] ?? ($metadata['avatar_url'] ?? null),
            'subtitle_style' => $row['subtitle_style'] ?? null,
        ];
        $_SESSION['profile_cache'] = ['id' => $user['id'], 'row' => $profile, 'at' => time()];
        return $profile;
    }

    public static function isAdmin(): bool
    {
        $token = self::accessToken();
        if (!$token) {
            return false;
        }
        $result = Supabase::rpc('kitsuplay_is_admin', [], $token);
        return $result === true || ($result['value'] ?? null) === true;
    }

    public static function signIn(string $email, string $password): void
    {
        $body = Supabase::auth('/token?grant_type=password', 'POST', [
            'email' => trim($email),
            'password' => $password,
        ]);
        self::store($body);
    }

    public static function register(string $name, string $email, string $password): void
    {
        Supabase::invoke('register', [
            'name' => trim($name),
            'email' => trim($email),
            'password' => $password,
        ]);
    }

    public static function sendOtp(string $email): void
    {
        Supabase::invoke('send-otp', ['email' => trim($email)]);
    }

    public static function verifyOtp(string $email, string $code): void
    {
        $body = Supabase::invoke('verify-otp', [
            'email' => trim($email),
            'code' => trim($code),
        ]);
        if (empty($body['access_token']) || empty($body['refresh_token'])) {
            throw new RuntimeException('That code could not be verified. Try again.');
        }
        self::store($body);
    }

    public static function updatePassword(string $password): void
    {
        if (mb_strlen($password) < 8) {
            throw new RuntimeException('Password must be at least 8 characters.');
        }
        $token = self::accessToken();
        if (!$token) {
            throw new RuntimeException('Sign in again to change your password.');
        }
        $result = Supabase::call('/auth/v1/user', 'PUT', ['password' => $password], $token);
        if ($result['status'] >= 400) {
            throw new RuntimeException(Supabase::message($result['body'], 'Password could not be changed.'));
        }
    }

    /** @param array<string,mixed> $patch */
    public static function updateProfile(array $patch): void
    {
        $user = self::user();
        $token = self::accessToken();
        if (!$user || !$token) {
            throw new RuntimeException('Sign in to update your profile.');
        }
        $row = ['id' => $user['id'], 'updated_at' => gmdate('c')];
        if (array_key_exists('name', $patch)) {
            $row['name'] = trim((string) $patch['name']);
        }
        if (array_key_exists('avatar_url', $patch)) {
            $row['avatar_url'] = trim((string) $patch['avatar_url']);
        }
        $result = Supabase::rest(
            'profiles',
            'POST',
            'on_conflict=id',
            [$row],
            $token,
        );
        if ($result['status'] >= 400) {
            throw new RuntimeException('Profile could not be saved.');
        }
        if (!empty($row['name'])) {
            Supabase::call('/auth/v1/user', 'PUT', [
                'data' => ['name' => $row['name']],
            ], $token);
        }
        unset($_SESSION['profile_cache']);
        // Keep the session's copy in step.
        $_SESSION[self::SESSION_KEY]['user']['user_metadata']['name'] = $row['name'] ?? null;
    }

    public static function signOut(): void
    {
        $token = $_SESSION[self::SESSION_KEY]['access_token'] ?? null;
        if (is_string($token) && $token !== '') {
            try {
                Supabase::call('/auth/v1/logout', 'POST', null, $token);
            } catch (Throwable) {
                // A failed revoke must not keep the browser signed in.
            }
        }
        unset(
            $_SESSION[self::SESSION_KEY],
            $_SESSION['profile_cache'],
            $_SESSION['library_cache'],
            $_SESSION['google_pkce'],
        );
        session_regenerate_id(true);
    }

    // ── Google OAuth (PKCE, server-side) ───────────────────────────────────

    public static function googleAuthorizeUrl(): string
    {
        $verifier = rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
        $state = bin2hex(random_bytes(16));
        $_SESSION['google_pkce'] = [
            'verifier' => $verifier,
            'state' => $state,
            'at' => time(),
        ];
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
        $query = http_build_query([
            'provider' => 'google',
            'redirect_to' => site_url() . (PRETTY_URLS ? '/auth/callback' : '/index.php/auth/callback'),
            'code_challenge' => $challenge,
            'code_challenge_method' => 's256',
            'state' => $state,
        ]);
        return SUPABASE_URL . '/auth/v1/authorize?' . $query;
    }

    public static function exchangeGoogleCode(string $code, string $state): void
    {
        $pending = $_SESSION['google_pkce'] ?? null;
        if (!is_array($pending)
            || !hash_equals((string) ($pending['state'] ?? ''), $state)
            || (int) ($pending['at'] ?? 0) < time() - 600) {
            throw new RuntimeException('This sign-in link expired. Start again.');
        }
        unset($_SESSION['google_pkce']);

        $body = Supabase::auth('/token?grant_type=pkce', 'POST', [
            'auth_code' => $code,
            'code_verifier' => $pending['verifier'],
        ]);
        self::store($body);
    }

    /** @param array<string,mixed> $body A GoTrue token payload. */
    private static function store(array $body): void
    {
        $access = $body['access_token'] ?? null;
        $refresh = $body['refresh_token'] ?? null;
        if (!is_string($access) || !is_string($refresh)) {
            throw new RuntimeException('Sign-in did not return a session.');
        }
        $user = is_array($body['user'] ?? null) ? $body['user'] : self::fetchUser($access);
        if (!$user) {
            throw new RuntimeException('Sign-in did not return an account.');
        }
        $_SESSION[self::SESSION_KEY] = [
            'access_token' => $access,
            'refresh_token' => $refresh,
            'expires_at' => time() + max(60, (int) ($body['expires_in'] ?? 3600)) - 60,
            'user' => [
                'id' => $user['id'] ?? null,
                'email' => $user['email'] ?? null,
                'user_metadata' => $user['user_metadata'] ?? [],
            ],
        ];
        unset($_SESSION['profile_cache'], $_SESSION['library_cache']);
        session_regenerate_id(true);
    }

    private static function fetchUser(string $access): ?array
    {
        $result = Supabase::call('/auth/v1/user', 'GET', null, $access);
        return $result['status'] < 400 && is_array($result['body']) ? $result['body'] : null;
    }

    private static function refresh(): ?string
    {
        $refresh = $_SESSION[self::SESSION_KEY]['refresh_token'] ?? null;
        if (!is_string($refresh) || $refresh === '') {
            return null;
        }
        $result = Supabase::call('/auth/v1/token?grant_type=refresh_token', 'POST', [
            'refresh_token' => $refresh,
        ]);
        if ($result['status'] >= 400 || empty($result['body']['access_token'])) {
            unset($_SESSION[self::SESSION_KEY], $_SESSION['profile_cache'], $_SESSION['library_cache']);
            return null;
        }
        $body = $result['body'];
        $body['user'] = $body['user'] ?? self::fetchUser((string) $body['access_token']);
        self::store($body);
        return (string) $body['access_token'];
    }
}
