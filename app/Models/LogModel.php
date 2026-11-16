<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class LogModel
{
    public static function securityLogs(array $filters = [], int $page = 1, int $perPage = 30): array
    {
        $where = '1=1';
        $params = [];
        if (!empty($filters['severity'])) {
            $where .= ' AND s.severity = ?';
            $params[] = $filters['severity'];
        }
        if (!empty($filters['q'])) {
            $where .= ' AND (s.event LIKE ? OR s.details LIKE ? OR u.email LIKE ?)';
            $like = '%' . $filters['q'] . '%';
            array_push($params, $like, $like, $like);
        }
        if (!empty($filters['from'])) {
            $where .= ' AND s.created_at >= ?';
            $params[] = $filters['from'] . ' 00:00:00';
        }
        $offset = max(0, ($page - 1) * $perPage);
        $total = (int) Database::scalar(
            "SELECT COUNT(*) FROM security_logs s LEFT JOIN users u ON u.id = s.user_id WHERE {$where}",
            $params
        );
        $rows = Database::fetchAll(
            "SELECT s.*, u.email AS user_email, a.email AS admin_email
             FROM security_logs s LEFT JOIN users u ON u.id = s.user_id LEFT JOIN admins a ON a.id = s.admin_id
             WHERE {$where} ORDER BY s.created_at DESC LIMIT {$perPage} OFFSET {$offset}",
            $params
        );
        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage, 'pages' => max(1, (int) ceil($total / $perPage))];
    }

    public static function adminActivity(array $filters = [], int $page = 1, int $perPage = 30): array
    {
        $where = '1=1';
        $params = [];
        if (!empty($filters['q'])) {
            $where .= ' AND (a.action LIKE ? OR a.target_type LIKE ? OR ad.email LIKE ?)';
            $like = '%' . $filters['q'] . '%';
            array_push($params, $like, $like, $like);
        }
        $offset = max(0, ($page - 1) * $perPage);
        $total = (int) Database::scalar(
            "SELECT COUNT(*) FROM admin_activity_logs a LEFT JOIN admins ad ON ad.id = a.admin_id WHERE {$where}",
            $params
        );
        $rows = Database::fetchAll(
            "SELECT a.*, ad.name AS admin_name, ad.email AS admin_email
             FROM admin_activity_logs a LEFT JOIN admins ad ON ad.id = a.admin_id
             WHERE {$where} ORDER BY a.created_at DESC LIMIT {$perPage} OFFSET {$offset}",
            $params
        );
        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage, 'pages' => max(1, (int) ceil($total / $perPage))];
    }

    public static function emailQueue(int $limit = 20): array
    {
        $limit = max(1, min(100, $limit));
        return Database::fetchAll("SELECT * FROM email_queue ORDER BY created_at DESC LIMIT {$limit}");
    }

    public static function emailStats(): array
    {
        $row = Database::fetch(
            "SELECT COUNT(*) AS total, SUM(status = 'pending') AS pending, SUM(status = 'sent') AS sent, SUM(status = 'failed') AS failed,
                    SUM(status = 'pending' AND scheduled_at <= UTC_TIMESTAMP()) AS due
             FROM email_queue"
        ) ?: [];
        return [
            'total' => (int) ($row['total'] ?? 0),
            'pending' => (int) ($row['pending'] ?? 0),
            'sent' => (int) ($row['sent'] ?? 0),
            'failed' => (int) ($row['failed'] ?? 0),
            'due' => (int) ($row['due'] ?? 0),
        ];
    }

    public static function securitySummary(): array
    {
        $row = Database::fetch(
            "SELECT SUM(severity = 'critical' AND created_at >= (UTC_TIMESTAMP() - INTERVAL 1 DAY)) AS critical_24h,
                    SUM(created_at >= (UTC_TIMESTAMP() - INTERVAL 1 DAY)) AS events_24h
             FROM security_logs"
        ) ?: [];
        return [
            'critical_24h' => (int) ($row['critical_24h'] ?? 0),
            'events_24h' => (int) ($row['events_24h'] ?? 0),
        ];
    }
}
