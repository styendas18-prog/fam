<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Services\Mailer;

final class AnnouncementModel
{
    public static function listAll(int $page = 1, int $perPage = 20): array
    {
        $offset = max(0, ($page - 1) * $perPage);
        $total = (int) Database::scalar('SELECT COUNT(*) FROM announcements');
        $rows = Database::fetchAll(
            "SELECT a.*, ad.name AS admin_name FROM announcements a LEFT JOIN admins ad ON ad.id = a.admin_id
             ORDER BY a.created_at DESC LIMIT {$perPage} OFFSET {$offset}"
        );
        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage, 'pages' => max(1, (int) ceil($total / $perPage))];
    }

    public static function find(int $id): ?array
    {
        return Database::fetch('SELECT * FROM announcements WHERE id = ? LIMIT 1', [$id]);
    }

    public static function create(int $adminId, string $title, string $body, string $audience): int
    {
        return Database::insert(
            'INSERT INTO announcements (admin_id, title, body, audience, is_published, created_at) VALUES (?, ?, ?, ?, 0, UTC_TIMESTAMP())',
            [$adminId, $title, $body, $audience]
        );
    }

    public static function update(int $id, string $title, string $body, string $audience): void
    {
        Database::execute(
            'UPDATE announcements SET title = ?, body = ?, audience = ? WHERE id = ? AND is_published = 0',
            [$title, $body, $audience, $id]
        );
    }

    /**
     * Publish once. Creates one real in-app notification per targeted user and
     * queues one real email per user. Returns the number of recipients.
     */
    public static function publish(int $id): int
    {
        return Database::transaction(static function () use ($id): int {
            $row = Database::fetch('SELECT * FROM announcements WHERE id = ? FOR UPDATE', [$id]);
            if (!$row || (int) $row['is_published'] === 1) {
                return 0;
            }
            $sql = 'SELECT u.id, u.name, u.email FROM users u';
            if ($row['audience'] === 'active_merchants') {
                $sql .= ' JOIN merchants m ON m.user_id = u.id AND m.deleted_at IS NULL AND m.status = "active"';
            }
            $sql .= ' WHERE u.status = "active" AND u.deleted_at IS NULL';
            $users = Database::fetchAll($sql);
            foreach ($users as $user) {
                NotificationModel::create((int) $user['id'], 'announcement', (string) $row['title'], (string) $row['body'], '/user/notifications.php');
                Mailer::queueAnnouncement($user, (string) $row['title'], (string) $row['body']);
            }
            Database::execute(
                'UPDATE announcements SET is_published = 1, published_at = UTC_TIMESTAMP() WHERE id = ?',
                [$id]
            );
            return count($users);
        });
    }

    public static function delete(int $id): void
    {
        Database::execute('DELETE FROM announcements WHERE id = ?', [$id]);
    }
}
