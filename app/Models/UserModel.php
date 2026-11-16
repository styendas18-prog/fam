<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class UserModel
{
    public static function find(int $id): ?array
    {
        return Database::fetch('SELECT * FROM users WHERE id = ? AND deleted_at IS NULL LIMIT 1', [$id]);
    }

    public static function updateName(int $id, string $name): void
    {
        Database::execute('UPDATE users SET name = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?', [$name, $id]);
    }

    public static function updatePassword(int $id, string $hash): void
    {
        Database::execute('UPDATE users SET password_hash = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?', [$hash, $id]);
    }

    public static function adminList(array $filters = [], int $page = 1, int $perPage = 25): array
    {
        $where = 'u.deleted_at IS NULL';
        $params = [];
        if (!empty($filters['status'])) {
            $where .= ' AND u.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['q'])) {
            $where .= ' AND (u.name LIKE ? OR u.email LIKE ? OR m.business_name LIKE ? OR m.merchant_code LIKE ?)';
            $like = '%' . $filters['q'] . '%';
            array_push($params, $like, $like, $like, $like);
        }
        $offset = max(0, ($page - 1) * $perPage);
        $total = (int) Database::scalar(
            "SELECT COUNT(*) FROM users u LEFT JOIN merchants m ON m.user_id = u.id AND m.deleted_at IS NULL WHERE {$where}",
            $params
        );
        $rows = Database::fetchAll(
            "SELECT u.id, u.name, u.email, u.status, u.last_login_at, u.last_login_ip, u.created_at,
                    m.id AS merchant_id, m.business_name, m.merchant_code, m.status AS merchant_status,
                    (SELECT COUNT(*) FROM payment_orders o WHERE o.merchant_id = m.id) AS order_count,
                    (SELECT COALESCE(SUM(t.amount),0) FROM transactions t WHERE t.merchant_id = m.id) AS verified_volume
             FROM users u LEFT JOIN merchants m ON m.user_id = u.id AND m.deleted_at IS NULL
             WHERE {$where} ORDER BY u.created_at DESC LIMIT {$perPage} OFFSET {$offset}",
            $params
        );
        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage, 'pages' => max(1, (int) ceil($total / $perPage))];
    }

    public static function adminDetail(int $id): ?array
    {
        return Database::fetch(
            'SELECT u.*, m.id AS merchant_id, m.business_name, m.merchant_code, m.status AS merchant_status,
                    m.website, m.support_email, m.support_phone, m.created_at AS merchant_created_at,
                    mp.kyc_status, mp.legal_name, mp.business_type, mp.city, mp.state, mp.gstin, mp.pan
             FROM users u LEFT JOIN merchants m ON m.user_id = u.id AND m.deleted_at IS NULL
             LEFT JOIN merchant_profiles mp ON mp.merchant_id = m.id
             WHERE u.id = ? AND u.deleted_at IS NULL LIMIT 1',
            [$id]
        );
    }

    public static function setStatus(int $id, string $status): bool
    {
        if (!in_array($status, ['active', 'suspended', 'pending'], true)) {
            return false;
        }
        return Database::execute('UPDATE users SET status = ?, updated_at = UTC_TIMESTAMP() WHERE id = ? AND deleted_at IS NULL', [$status, $id]) > 0;
    }

    public static function softDelete(int $id): bool
    {
        return Database::execute(
            'UPDATE users SET deleted_at = UTC_TIMESTAMP(), status = "suspended" WHERE id = ? AND deleted_at IS NULL',
            [$id]
        ) > 0;
    }

    public static function sessions(int $userId, int $limit = 20): array
    {
        $limit = max(1, min(100, $limit));
        return Database::fetchAll(
            "SELECT * FROM login_sessions WHERE user_type = 'user' AND user_id = ? ORDER BY created_at DESC LIMIT {$limit}",
            [$userId]
        );
    }

    public static function revokeOtherSessions(int $userId, string $currentHash): int
    {
        return Database::execute(
            'UPDATE login_sessions SET revoked_at = UTC_TIMESTAMP() WHERE user_type = "user" AND user_id = ? AND revoked_at IS NULL AND session_hash != ?',
            [$userId, $currentHash]
        );
    }

    public static function securityEvents(int $userId, int $limit = 12): array
    {
        $limit = max(1, min(50, $limit));
        return Database::fetchAll(
            "SELECT * FROM security_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT {$limit}",
            [$userId]
        );
    }

    public static function notificationPreferences(int $id): void
    {
        Database::execute('UPDATE users SET updated_at = UTC_TIMESTAMP() WHERE id = ?', [$id]);
    }
}
