<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class ApiLogModel
{
    public static function listForMerchant(int $merchantId, array $filters = [], int $page = 1, int $perPage = 25): array
    {
        $where = 'l.merchant_id = ?';
        $params = [$merchantId];
        self::applyFilters($where, $params, $filters);
        $offset = max(0, ($page - 1) * $perPage);
        $total = (int) Database::scalar("SELECT COUNT(*) FROM api_logs l WHERE {$where}", $params);
        $rows = Database::fetchAll(
            "SELECT l.*, k.name AS key_name, k.key_prefix FROM api_logs l LEFT JOIN api_keys k ON k.id = l.api_key_id
             WHERE {$where} ORDER BY l.created_at DESC LIMIT {$perPage} OFFSET {$offset}",
            $params
        );
        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage, 'pages' => max(1, (int) ceil($total / $perPage))];
    }

    public static function listAll(array $filters = [], int $page = 1, int $perPage = 25): array
    {
        $where = '1=1';
        $params = [];
        self::applyFilters($where, $params, $filters);
        if (!empty($filters['merchant_id'])) {
            $where .= ' AND l.merchant_id = ?';
            $params[] = (int) $filters['merchant_id'];
        }
        $offset = max(0, ($page - 1) * $perPage);
        $total = (int) Database::scalar("SELECT COUNT(*) FROM api_logs l WHERE {$where}", $params);
        $rows = Database::fetchAll(
            "SELECT l.*, k.name AS key_name, k.key_prefix, m.business_name, m.merchant_code
             FROM api_logs l LEFT JOIN api_keys k ON k.id = l.api_key_id LEFT JOIN merchants m ON m.id = l.merchant_id
             WHERE {$where} ORDER BY l.created_at DESC LIMIT {$perPage} OFFSET {$offset}",
            $params
        );
        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage, 'pages' => max(1, (int) ceil($total / $perPage))];
    }

    public static function recentErrors(int $limit = 8): array
    {
        $limit = max(1, min(50, $limit));
        return Database::fetchAll(
            "SELECT l.*, m.business_name FROM api_logs l LEFT JOIN merchants m ON m.id = l.merchant_id
             WHERE l.status_code >= 400 ORDER BY l.created_at DESC LIMIT {$limit}"
        );
    }

    private static function applyFilters(string &$where, array &$params, array $filters): void
    {
        if (!empty($filters['status'])) {
            if ($filters['status'] === 'error') {
                $where .= ' AND l.status_code >= 400';
            } elseif ($filters['status'] === 'success') {
                $where .= ' AND l.status_code < 400';
            }
        }
        if (!empty($filters['from'])) {
            $where .= ' AND l.created_at >= ?';
            $params[] = $filters['from'] . ' 00:00:00';
        }
        if (!empty($filters['to'])) {
            $where .= ' AND l.created_at <= ?';
            $params[] = $filters['to'] . ' 23:59:59';
        }
        if (!empty($filters['q'])) {
            $where .= ' AND (l.path LIKE ? OR l.request_id LIKE ? OR l.error LIKE ?)';
            $like = '%' . $filters['q'] . '%';
            array_push($params, $like, $like, $like);
        }
    }
}
