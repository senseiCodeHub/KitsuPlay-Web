<?php
/**
 * Announcements and the maintenance switch, read from the same tables and RPC
 * the Android app uses. Read state is per-device and lives in the session.
 */

declare(strict_types=1);

final class Notices
{
    private const READ_KEY = 'read_notices';
    private const CACHE_KEY = 'notices_cache';

    /** @return array<int,array{id:string,title:string,body:string,kind:string,createdAt:?string}> */
    public static function active(): array
    {
        $cached = $_SESSION[self::CACHE_KEY] ?? null;
        if (is_array($cached) && (int) ($cached['at'] ?? 0) > time() - 120) {
            return $cached['rows'];
        }

        $result = Supabase::call(
            '/rest/v1/app_announcements?select=id,title,body,kind,created_at,starts_at,ends_at'
            . '&is_active=eq.true&order=created_at.desc&limit=50',
            'GET',
        );
        $rows = [];
        if ($result['status'] < 400 && is_array($result['body'])) {
            $now = time();
            foreach ($result['body'] as $row) {
                if (!is_array($row) || empty($row['id'])) {
                    continue;
                }
                $starts = $row['starts_at'] ?? null;
                $ends = $row['ends_at'] ?? null;
                if (is_string($starts) && $starts !== '' && strtotime($starts) > $now) {
                    continue;
                }
                if (is_string($ends) && $ends !== '' && strtotime($ends) < $now) {
                    continue;
                }
                $rows[] = [
                    'id' => (string) $row['id'],
                    'title' => (string) ($row['title'] ?? ''),
                    'body' => (string) ($row['body'] ?? ''),
                    'kind' => (string) ($row['kind'] ?? 'info'),
                    'createdAt' => $row['created_at'] ?? null,
                ];
            }
        }
        $_SESSION[self::CACHE_KEY] = ['at' => time(), 'rows' => $rows];
        return $rows;
    }

    public static function maintenance(): array
    {
        $result = Supabase::rpc('app_maintenance');
        if (!is_array($result)) {
            return ['active' => false, 'message' => null];
        }
        return [
            'active' => ($result['active'] ?? false) === true,
            'message' => is_string($result['message'] ?? null) ? $result['message'] : null,
        ];
    }

    public static function isRead(string $id): bool
    {
        $read = $_SESSION[self::READ_KEY] ?? [];
        return is_array($read) && in_array($id, $read, true);
    }

    public static function markRead(string $id): void
    {
        $read = $_SESSION[self::READ_KEY] ?? [];
        if (!is_array($read)) {
            $read = [];
        }
        if (!in_array($id, $read, true)) {
            $read[] = $id;
        }
        $_SESSION[self::READ_KEY] = array_slice($read, -200);
    }

    public static function markAllRead(): void
    {
        foreach (self::active() as $notice) {
            self::markRead($notice['id']);
        }
    }

    public static function unreadCount(): int
    {
        return count(array_filter(
            self::active(),
            static fn ($notice) => !self::isRead($notice['id']),
        ));
    }
}
