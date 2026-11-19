<?php
/**
 * The Supabase REST/GoTrue client used by the server.
 *
 * The anon (publishable) key is the only key this app ever holds: RLS decides
 * what a signed-in user may read and write, and the service-role key is never
 * present on the server. User tokens live in the PHP session, never in a
 * cookie the browser can read.
 */

declare(strict_types=1);

final class Supabase
{
    /** @return array{status:int,body:array<string,mixed>} */
    public static function call(
        string $path,
        string $method = 'GET',
        ?array $payload = null,
        ?string $token = null,
        array $extraHeaders = [],
        int $timeout = 20,
    ): array {
        $headers = [
            'apikey: ' . SUPABASE_ANON_KEY,
            'Authorization: Bearer ' . ($token ?? SUPABASE_ANON_KEY),
            'Accept: application/json',
            'Content-Type: application/json',
            'X-Client-Info: kitsuplay-web',
        ];
        foreach ($extraHeaders as $header) {
            $headers[] = $header;
        }

        $response = Http::request(
            SUPABASE_URL . $path,
            $headers,
            $payload,
            $method,
            $timeout,
        );
        $decoded = json_decode($response['body'], true);
        return [
            'status' => $response['status'],
            'body' => is_array($decoded) ? $decoded : [],
        ];
    }

    /** @return array<string,mixed> */
    public static function auth(
        string $path,
        string $method = 'POST',
        ?array $payload = null,
    ): array {
        $result = self::call('/auth/v1' . $path, $method, $payload);
        if ($result['status'] >= 400) {
            throw new RuntimeException(self::message($result['body'], 'Sign-in failed.'));
        }
        return $result['body'];
    }

    public static function rest(
        string $table,
        string $method,
        string $query = '',
        ?array $payload = null,
        ?string $token = null,
    ): array {
        $extra = [];
        if ($method === 'POST') {
            $extra[] = 'Prefer: return=representation';
        }
        if ($method === 'POST' && str_contains($query, 'on_conflict=')) {
            $extra[] = 'Prefer: resolution=merge-duplicates,return=minimal';
        }
        $path = '/rest/v1/' . $table . ($query !== '' ? '?' . $query : '');
        return self::call($path, $method, $payload, $token, $extra);
    }

    /** @return array<string,mixed>|null */
    public static function rpc(string $name, array $args = [], ?string $token = null): ?array
    {
        $result = self::call('/rest/v1/rpc/' . $name, 'POST', $args, $token);
        return $result['status'] < 400 ? $result['body'] : null;
    }

    /** Invokes one of the project's Edge Functions with the anon key. */
    public static function invoke(string $function, array $payload): array
    {
        $result = self::call('/functions/v1/' . $function, 'POST', $payload);
        if ($result['status'] >= 400) {
            throw new RuntimeException(self::message($result['body'], 'Request failed.'));
        }
        return $result['body'];
    }

    /** The sentence a viewer should see for a Supabase error body. */
    public static function message(array $body, string $fallback): string
    {
        foreach (['error_description', 'msg', 'message', 'error'] as $key) {
            if (!empty($body[$key]) && is_string($body[$key])) {
                return self::friendly($body[$key]);
            }
        }
        return $fallback;
    }

    public static function friendly(string $text): string
    {
        $lower = strtolower($text);
        return match (true) {
            str_contains($lower, 'invalid login credentials') => 'Wrong email or password.',
            str_contains($lower, 'email not confirmed') => 'Verify your email first — check the code we sent.',
            str_contains($lower, 'already registered'),
            str_contains($lower, 'already exists') => 'An account with this email already exists.',
            str_contains($lower, 'invalid or expired') => 'That code did not match or has expired.',
            str_contains($lower, 'too many') => 'Too many attempts. Wait a few minutes.',
            str_contains($lower, 'password should be') => 'Password must be at least 8 characters.',
            str_contains($lower, 'provider is not enabled') => 'Google sign-in is not enabled on the server yet.',
            str_contains($lower, 'failed to fetch'),
            str_contains($lower, 'timed out') => 'The server took too long to answer. Try again.',
            default => $text,
        };
    }
}
