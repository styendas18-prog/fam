<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class NotificationModel
{
    public static function create(int $userId, string $type, string $title, string $message, ?string $link = null): int
    {
        return Database::insert(
            'INSERT INTO notifications (user_id, type, title, message, link, created_at) VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP())',
            [$userId, $type, $title, $message, $link]
        );
    }

    public static function listForUser(int $userId, int $limit = 50): array
    {
        $limit = max(1, min(100, $limit));
        return Database::fetchAll(
            "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT {$limit}",
            [$userId]
        );
    }

    public static function unreadCount(int $userId): int
    {
        return (int) Database::scalar('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL', [$userId]);
    }

    public static function markRead(int $id, int $userId): void
    {
        Database::execute('UPDATE notifications SET read_at = UTC_TIMESTAMP() WHERE id = ? AND user_id = ?', [$id, $userId]);
    }

    public static function markAllRead(int $userId): void
    {
        Database::execute('UPDATE notifications SET read_at = UTC_TIMESTAMP() WHERE user_id = ? AND read_at IS NULL', [$userId]);
    }
}
