<?php
/**
 * POST /api/push-token — stores this browser's FCM token for the account.
 * Same table the Android app writes to, with `platform = 'web'`.
 */

declare(strict_types=1);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    json_out(['ok' => false, 'error' => 'Method not allowed'], 405);
}
csrf_verify();

$userId = Auth::userId();
$token = Auth::accessToken();
if (!$userId || !$token) {
    json_out(['ok' => false, 'error' => 'Sign in to receive notifications.'], 401);
}

$body = json_decode((string) file_get_contents('php://input'), true);
$fcm = is_array($body) ? trim((string) ($body['token'] ?? '')) : '';
$device = is_array($body) ? mb_substr((string) ($body['device'] ?? 'web'), 0, 160) : 'web';

if (strlen($fcm) < 40 || strlen($fcm) > 4096) {
    json_out(['ok' => false, 'error' => 'Invalid token'], 400);
}

// The Android app owns the table shape, so try its full row first and fall
// back to the columns every version has.
$attempts = [
    ['on_conflict=user_id,token', ['user_id' => $userId, 'token' => $fcm, 'device' => $device, 'platform' => 'web', 'updated_at' => gmdate('c')]],
    ['on_conflict=user_id,token', ['user_id' => $userId, 'token' => $fcm, 'updated_at' => gmdate('c')]],
    ['', ['user_id' => $userId, 'token' => $fcm]],
];
$result = ['status' => 400, 'body' => null];
foreach ($attempts as [$query, $row]) {
    $result = Supabase::rest('fcm_tokens', 'POST', $query, [$row], $token);
    if ($result['status'] < 400) {
        break;
    }
}

if ($result['status'] >= 400) {
    log_error(new RuntimeException('fcm_tokens write failed: ' . json_encode($result['body'])));
    json_out(['ok' => false, 'error' => 'The token could not be saved.'], 502);
}

json_out(['ok' => true]);
