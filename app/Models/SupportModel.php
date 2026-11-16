<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class SupportModel
{
    public static function create(?int $userId, ?int $merchantId, string $subject, string $category, string $priority, string $message, ?string $guestName = null, ?string $guestEmail = null): array
    {
        $number = 'TKT-' . gmdate('ymd') . '-' . strtoupper(bin2hex(random_bytes(2)));
        $ticketId = Database::transaction(static function () use ($userId, $merchantId, $subject, $category, $priority, $message, $guestName, $guestEmail, $number): int {
            $id = Database::insert(
                'INSERT INTO support_tickets (ticket_number, user_id, merchant_id, guest_name, guest_email, subject, category, priority, status, last_reply_at, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, "open", UTC_TIMESTAMP(), UTC_TIMESTAMP())',
                [$number, $userId, $merchantId, $guestName, $guestEmail, $subject, $category, $priority]
            );
            Database::insert(
                'INSERT INTO support_messages (ticket_id, sender_type, sender_id, message, created_at) VALUES (?, ?, ?, ?, UTC_TIMESTAMP())',
                [$id, $userId ? 'user' : 'guest', $userId, $message]
            );
            return $id;
        });
        return ['id' => $ticketId, 'number' => $number];
    }

    public static function forUser(int $userId): array
    {
        return Database::fetchAll(
            'SELECT t.*, (SELECT COUNT(*) FROM support_messages sm WHERE sm.ticket_id = t.id) AS message_count
             FROM support_tickets t WHERE t.user_id = ? ORDER BY t.updated_at DESC',
            [$userId]
        );
    }

    public static function findForUser(int $id, int $userId): ?array
    {
        return Database::fetch('SELECT * FROM support_tickets WHERE id = ? AND user_id = ? LIMIT 1', [$id, $userId]);
    }

    public static function find(int $id): ?array
    {
        return Database::fetch(
            'SELECT t.*, u.name AS user_name, u.email AS user_email, m.business_name
             FROM support_tickets t LEFT JOIN users u ON u.id = t.user_id LEFT JOIN merchants m ON m.id = t.merchant_id
             WHERE t.id = ? LIMIT 1',
            [$id]
        );
    }

    public static function messages(int $ticketId): array
    {
        return Database::fetchAll(
            'SELECT sm.*, u.name AS user_name, a.name AS admin_name
             FROM support_messages sm LEFT JOIN users u ON u.id = sm.sender_id AND sm.sender_type = "user"
             LEFT JOIN admins a ON a.id = sm.sender_id AND sm.sender_type = "admin"
             WHERE sm.ticket_id = ? ORDER BY sm.created_at ASC',
            [$ticketId]
        );
    }

    public static function reply(int $ticketId, string $senderType, int $senderId, string $message): void
    {
        Database::transaction(static function () use ($ticketId, $senderType, $senderId, $message): void {
            Database::insert(
                'INSERT INTO support_messages (ticket_id, sender_type, sender_id, message, created_at) VALUES (?, ?, ?, ?, UTC_TIMESTAMP())',
                [$ticketId, $senderType, $senderId, $message]
            );
            Database::execute(
                'UPDATE support_tickets SET last_reply_at = UTC_TIMESTAMP(), updated_at = UTC_TIMESTAMP(), status = ? WHERE id = ?',
                [$senderType === 'admin' ? 'pending' : 'open', $ticketId]
            );
        });
    }

    public static function listAll(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $where = '1=1';
        $params = [];
        if (!empty($filters['status'])) {
            $where .= ' AND t.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['q'])) {
            $where .= ' AND (t.subject LIKE ? OR t.ticket_number LIKE ? OR t.guest_email LIKE ? OR u.email LIKE ?)';
            $like = '%' . $filters['q'] . '%';
            array_push($params, $like, $like, $like, $like);
        }
        $offset = max(0, ($page - 1) * $perPage);
        $total = (int) Database::scalar(
            "SELECT COUNT(*) FROM support_tickets t LEFT JOIN users u ON u.id = t.user_id WHERE {$where}",
            $params
        );
        $rows = Database::fetchAll(
            "SELECT t.*, u.name AS user_name, u.email AS user_email, m.business_name,
                (SELECT COUNT(*) FROM support_messages sm WHERE sm.ticket_id = t.id) AS message_count
             FROM support_tickets t LEFT JOIN users u ON u.id = t.user_id LEFT JOIN merchants m ON m.id = t.merchant_id
             WHERE {$where} ORDER BY FIELD(t.status,'open','pending','resolved','closed'), t.updated_at DESC LIMIT {$perPage} OFFSET {$offset}",
            $params
        );
        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage, 'pages' => max(1, (int) ceil($total / $perPage))];
    }

    public static function setStatus(int $id, string $status): void
    {
        if (!in_array($status, ['open', 'pending', 'resolved', 'closed'], true)) {
            return;
        }
        Database::execute('UPDATE support_tickets SET status = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?', [$status, $id]);
    }
}
