<?php
/** POST /api/notices — read state for announcements (per device/session). */

declare(strict_types=1);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    json_out(['ok' => false, 'error' => 'Method not allowed'], 405);
}
csrf_verify();

$body = json_decode((string) file_get_contents('php://input'), true);
$action = is_array($body) ? (string) ($body['action'] ?? '') : '';

if ($action === 'read') {
    $id = (string) ($body['id'] ?? '');
    if ($id !== '' && preg_match('/^[0-9a-f\-]{8,40}$/i', $id)) {
        Notices::markRead($id);
    }
    json_out(['ok' => true, 'unread' => Notices::unreadCount()]);
}

if ($action === 'read-all') {
    Notices::markAllRead();
    json_out(['ok' => true, 'unread' => 0]);
}

json_out(['ok' => false, 'error' => 'Unknown action'], 400);
