<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Security;

final class PaymentOrderModel
{
    public static function newPublicId(): string
    {
        return 'MF' . strtoupper(bin2hex(random_bytes(12)));
    }

    public static function create(array $data): int
    {
        $publicId = self::newPublicId();
        $token = Security::randomToken(32);
        return Database::insert(
            'INSERT INTO payment_orders
             (public_id, checkout_token, merchant_id, provider_id, link_id, customer_name, customer_email, customer_phone,
              customer_reference, description, amount, currency, status, success_redirect_url, webhook_url, expires_at, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "pending", ?, ?, ?, UTC_TIMESTAMP())',
            [
                $publicId,
                $token,
                $data['merchant_id'],
                $data['provider_id'],
                $data['link_id'] ?? null,
                $data['customer_name'] ?? null,
                $data['customer_email'] ?? null,
                $data['customer_phone'] ?? null,
                $data['customer_reference'] ?? null,
                $data['description'] ?? null,
                $data['amount'],
                $data['currency'] ?? 'INR',
                $data['success_redirect_url'] ?? null,
                $data['webhook_url'] ?? null,
                $data['expires_at'] ?? gmdate('Y-m-d H:i:s', time() + 300),
            ]
        );
    }

    public static function find(int $id): ?array
    {
        return Database::fetch(
            'SELECT o.*, m.business_name, m.merchant_code, m.support_email, p.name AS provider_name, p.code AS provider_code
             FROM payment_orders o
             JOIN merchants m ON m.id = o.merchant_id
             JOIN payment_providers p ON p.id = o.provider_id
             WHERE o.id = ? LIMIT 1',
            [$id]
        );
    }

    public static function findByPublicId(string $publicId, int $merchantId): ?array
    {
        return Database::fetch(
            'SELECT o.*, p.code AS provider_code, p.name AS provider_name, m.business_name
             FROM payment_orders o JOIN payment_providers p ON p.id = o.provider_id JOIN merchants m ON m.id = o.merchant_id
             WHERE o.public_id = ? AND o.merchant_id = ? LIMIT 1',
            [$publicId, $merchantId]
        );
    }

    public static function findByToken(string $token): ?array
    {
        return Database::fetch(
            'SELECT o.*, p.code AS provider_code, p.name AS provider_name, m.business_name, m.support_email, m.merchant_code
             FROM payment_orders o
             JOIN payment_providers p ON p.id = o.provider_id
             JOIN merchants m ON m.id = o.merchant_id
             WHERE o.checkout_token = ? LIMIT 1',
            [$token]
        );
    }

    public static function findByProviderOrder(int $providerId, string $providerOrderId): ?array
    {
        return Database::fetch(
            'SELECT o.*, p.code AS provider_code, m.support_email
             FROM payment_orders o JOIN payment_providers p ON p.id = o.provider_id
             JOIN merchants m ON m.id = o.merchant_id
             WHERE o.provider_id = ? AND o.provider_order_id = ? LIMIT 1',
            [$providerId, $providerOrderId]
        );
    }

    public static function providerData(int $id): ?array
    {
        return Database::fetch('SELECT provider_order_id, checkout_token FROM payment_orders WHERE id = ?', [$id]);
    }

    public static function markProviderCreated(int $id, array $provider): void
    {
        Database::execute(
            'UPDATE payment_orders SET provider_order_id = ?, payable_amount = ?, qr_url = ?, checkout_url = ?, provider_checkout_url = ?,
             upi_intent = ?, upi_id = ?, expires_at = ?, failure_code = NULL, failure_message = NULL, updated_at = UTC_TIMESTAMP()
             WHERE id = ?',
            [
                $provider['provider_order_id'],
                $provider['payable_amount'],
                $provider['qr_url'] ?? null,
                url('/checkout.php?token=' . self::tokenFor($id)),
                $provider['checkout_url'] ?? null,
                $provider['upi_intent'] ?? null,
                $provider['upi_id'] ?? null,
                $provider['expires_at'],
                $id,
            ]
        );
    }

    private static function tokenFor(int $id): string
    {
        $token = Database::scalar('SELECT checkout_token FROM payment_orders WHERE id = ?', [$id]);
        return (string) $token;
    }

    public static function setCheckoutUrl(int $id, string $url): void
    {
        Database::execute('UPDATE payment_orders SET checkout_url = ? WHERE id = ?', [$url, $id]);
    }

    public static function markFailed(int $id, string $code, string $message): void
    {
        Database::execute(
            'UPDATE payment_orders SET status = "failed", failure_code = ?, failure_message = ?, updated_at = UTC_TIMESTAMP() WHERE id = ? AND status != "success"',
            [$code, mb_substr($message, 0, 500), $id]
        );
    }

    public static function markCancelled(int $id): bool
    {
        return Database::execute(
            'UPDATE payment_orders SET status = "cancelled", cancelled_at = UTC_TIMESTAMP(), updated_at = UTC_TIMESTAMP()
             WHERE id = ? AND status = "pending"',
            [$id]
        ) > 0;
    }

    public static function listForMerchant(int $merchantId, array $filters = [], int $page = 1, int $perPage = 20): array
    {
        [$where, $params] = self::merchantFilters($merchantId, $filters);
        $offset = max(0, ($page - 1) * $perPage);
        $total = (int) Database::scalar("SELECT COUNT(*) FROM payment_orders o WHERE {$where}", $params);
        $rows = Database::fetchAll(
            "SELECT o.*, p.name AS provider_name, p.code AS provider_code, m.business_name
             FROM payment_orders o JOIN payment_providers p ON p.id = o.provider_id JOIN merchants m ON m.id = o.merchant_id
             WHERE {$where} ORDER BY o.created_at DESC LIMIT {$perPage} OFFSET {$offset}",
            $params
        );
        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage, 'pages' => max(1, (int) ceil($total / $perPage))];
    }

    public static function listAll(array $filters = [], int $page = 1, int $perPage = 25): array
    {
        $where = '1=1';
        $params = [];
        if (!empty($filters['status'])) {
            $where .= ' AND o.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['provider_id'])) {
            $where .= ' AND o.provider_id = ?';
            $params[] = (int) $filters['provider_id'];
        }
        if (!empty($filters['merchant_id'])) {
            $where .= ' AND o.merchant_id = ?';
            $params[] = (int) $filters['merchant_id'];
        }
        if (!empty($filters['q'])) {
            $where .= ' AND (o.public_id LIKE ? OR o.provider_order_id LIKE ? OR o.customer_email LIKE ? OR m.business_name LIKE ?)';
            $like = '%' . $filters['q'] . '%';
            array_push($params, $like, $like, $like, $like);
        }
        if (!empty($filters['from'])) {
            $where .= ' AND o.created_at >= ?';
            $params[] = $filters['from'] . ' 00:00:00';
        }
        if (!empty($filters['to'])) {
            $where .= ' AND o.created_at <= ?';
            $params[] = $filters['to'] . ' 23:59:59';
        }
        $offset = max(0, ($page - 1) * $perPage);
        $total = (int) Database::scalar("SELECT COUNT(*) FROM payment_orders o JOIN merchants m ON m.id = o.merchant_id WHERE {$where}", $params);
        $rows = Database::fetchAll(
            "SELECT o.*, p.name AS provider_name, p.code AS provider_code, m.business_name, m.merchant_code
             FROM payment_orders o JOIN payment_providers p ON p.id = o.provider_id JOIN merchants m ON m.id = o.merchant_id
             WHERE {$where} ORDER BY o.created_at DESC LIMIT {$perPage} OFFSET {$offset}",
            $params
        );
        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage, 'pages' => max(1, (int) ceil($total / $perPage))];
    }

    public static function recent(int $merchantId, int $limit = 6): array
    {
        $limit = max(1, min(50, $limit));
        return Database::fetchAll(
            "SELECT o.*, p.name AS provider_name FROM payment_orders o JOIN payment_providers p ON p.id = o.provider_id
             WHERE o.merchant_id = ? ORDER BY o.created_at DESC LIMIT {$limit}",
            [$merchantId]
        );
    }

    public static function pendingDue(int $limit = 100): array
    {
        $limit = max(1, min(500, $limit));
        return Database::fetchAll(
            "SELECT o.*, p.code AS provider_code FROM payment_orders o JOIN payment_providers p ON p.id = o.provider_id
             WHERE o.status = 'pending' AND o.provider_order_id IS NOT NULL
               AND (o.last_checked_at IS NULL OR o.last_checked_at < (UTC_TIMESTAMP() - INTERVAL 20 SECOND))
             ORDER BY o.last_checked_at IS NULL DESC, o.expires_at ASC LIMIT {$limit}"
        );
    }

    public static function expiredPending(int $limit = 200): array
    {
        $limit = max(1, min(500, $limit));
        return Database::fetchAll(
            "SELECT o.*, p.code AS provider_code FROM payment_orders o JOIN payment_providers p ON p.id = o.provider_id
             WHERE o.status = 'pending' AND o.expires_at < (UTC_TIMESTAMP() - INTERVAL 15 SECOND)
             ORDER BY o.expires_at ASC LIMIT {$limit}"
        );
    }

    public static function touchChecked(int $id): void
    {
        Database::execute('UPDATE payment_orders SET last_checked_at = UTC_TIMESTAMP(), verification_attempts = verification_attempts + 1 WHERE id = ?', [$id]);
    }

    public static function countByMerchant(int $merchantId): int
    {
        return (int) Database::scalar('SELECT COUNT(*) FROM payment_orders WHERE merchant_id = ?', [$merchantId]);
    }

    public static function exportForMerchant(int $merchantId, array $filters = [], int $limit = 10000): array
    {
        [$where, $params] = self::merchantFilters($merchantId, $filters);
        $limit = max(1, min(20000, $limit));
        return Database::fetchAll(
            "SELECT o.public_id, o.provider_order_id, o.customer_name, o.customer_email, o.customer_reference,
                    o.description, o.amount, o.payable_amount, o.currency, o.status, o.failure_code, o.created_at, o.paid_at,
                    p.name AS provider_name
             FROM payment_orders o JOIN payment_providers p ON p.id = o.provider_id
             WHERE {$where} ORDER BY o.created_at DESC LIMIT {$limit}",
            $params
        );
    }

    private static function merchantFilters(int $merchantId, array $filters): array
    {
        $where = 'o.merchant_id = ?';
        $params = [$merchantId];
        if (!empty($filters['status']) && in_array($filters['status'], ['pending', 'success', 'failed', 'expired', 'cancelled'], true)) {
            $where .= ' AND o.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['q'])) {
            $where .= ' AND (o.public_id LIKE ? OR o.provider_order_id LIKE ? OR o.customer_email LIKE ? OR o.customer_reference LIKE ?)';
            $like = '%' . $filters['q'] . '%';
            array_push($params, $like, $like, $like, $like);
        }
        if (!empty($filters['from'])) {
            $where .= ' AND o.created_at >= ?';
            $params[] = $filters['from'] . ' 00:00:00';
        }
        if (!empty($filters['to'])) {
            $where .= ' AND o.created_at <= ?';
            $params[] = $filters['to'] . ' 23:59:59';
        }
        if (!empty($filters['min_amount'])) {
            $where .= ' AND o.amount >= ?';
            $params[] = (float) $filters['min_amount'];
        }
        if (!empty($filters['max_amount'])) {
            $where .= ' AND o.amount <= ?';
            $params[] = (float) $filters['max_amount'];
        }
        return [$where, $params];
    }
}
