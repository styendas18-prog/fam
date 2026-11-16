<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Services\PlanService;

final class PaymentLinkModel
{
    public static function create(int $merchantId, int $userId, array $data): int
    {
        PlanService::assertCan($merchantId, 'links');
        $base = strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', '-', $data['title'])));
        $base = trim($base, '-') ?: 'pay';
        $slug = substr($base, 0, 40) . '-' . strtolower(bin2hex(random_bytes(3)));
        return Database::insert(
            'INSERT INTO payment_links
             (merchant_id, slug, title, description, amount, min_amount, max_amount, currency, customer_reference,
              success_redirect_url, expires_at, status, created_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, "INR", ?, ?, ?, "active", ?, UTC_TIMESTAMP())',
            [
                $merchantId,
                $slug,
                $data['title'],
                $data['description'] ?? null,
                $data['amount'],
                $data['min_amount'] ?? 1,
                $data['max_amount'] ?? 100000,
                $data['customer_reference'] ?? null,
                $data['success_redirect_url'] ?? null,
                $data['expires_at'] ?? null,
                $userId,
            ]
        );
    }

    public static function find(int $id, int $merchantId): ?array
    {
        return Database::fetch('SELECT * FROM payment_links WHERE id = ? AND merchant_id = ? AND deleted_at IS NULL LIMIT 1', [$id, $merchantId]);
    }

    public static function findBySlug(string $slug): ?array
    {
        return Database::fetch(
            'SELECT l.*, m.business_name, m.status AS merchant_status, m.support_email
             FROM payment_links l JOIN merchants m ON m.id = l.merchant_id
             WHERE l.slug = ? AND l.deleted_at IS NULL LIMIT 1',
            [$slug]
        );
    }

    public static function listForMerchant(int $merchantId, array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $where = 'l.merchant_id = ? AND l.deleted_at IS NULL';
        $params = [$merchantId];
        if (!empty($filters['status'])) {
            $where .= ' AND l.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['q'])) {
            $where .= ' AND (l.title LIKE ? OR l.slug LIKE ?)';
            $like = '%' . $filters['q'] . '%';
            array_push($params, $like, $like);
        }
        $offset = max(0, ($page - 1) * $perPage);
        $total = (int) Database::scalar("SELECT COUNT(*) FROM payment_links l WHERE {$where}", $params);
        $rows = Database::fetchAll(
            "SELECT l.*,
                (SELECT COUNT(*) FROM payment_orders o WHERE o.link_id = l.id) AS order_count,
                (SELECT COALESCE(SUM(o.payable_amount),0) FROM payment_orders o WHERE o.link_id = l.id AND o.status = 'success') AS success_volume
             FROM payment_links l WHERE {$where} ORDER BY l.created_at DESC LIMIT {$perPage} OFFSET {$offset}",
            $params
        );
        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage, 'pages' => max(1, (int) ceil($total / $perPage))];
    }

    public static function setStatus(int $id, int $merchantId, string $status): bool
    {
        if (!in_array($status, ['active', 'inactive'], true)) {
            return false;
        }
        return Database::execute('UPDATE payment_links SET status = ? WHERE id = ? AND merchant_id = ? AND deleted_at IS NULL', [$status, $id, $merchantId]) > 0;
    }

    public static function delete(int $id, int $merchantId): bool
    {
        return Database::execute('UPDATE payment_links SET deleted_at = UTC_TIMESTAMP(), status = "inactive" WHERE id = ? AND merchant_id = ?', [$id, $merchantId]) > 0;
    }

    public static function orders(int $linkId, int $merchantId, int $limit = 25): array
    {
        $limit = max(1, min(100, $limit));
        return Database::fetchAll(
            "SELECT * FROM payment_orders WHERE link_id = ? AND merchant_id = ? ORDER BY created_at DESC LIMIT {$limit}",
            [$linkId, $merchantId]
        );
    }
}
