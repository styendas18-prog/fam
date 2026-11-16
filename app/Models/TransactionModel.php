<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class TransactionModel
{
    public static function find(int $id): ?array
    {
        return Database::fetch(
            'SELECT t.*, o.public_id, o.customer_name, o.customer_email, o.description, m.business_name, m.merchant_code, p.name AS provider_name
             FROM transactions t JOIN payment_orders o ON o.id = t.order_id JOIN merchants m ON m.id = t.merchant_id
             JOIN payment_providers p ON p.id = t.provider_id WHERE t.id = ? LIMIT 1',
            [$id]
        );
    }

    public static function findByOrder(int $orderId): ?array
    {
        return Database::fetch(
            'SELECT t.*, i.invoice_number, i.pdf_path FROM transactions t LEFT JOIN invoices i ON i.transaction_id = t.id WHERE t.order_id = ? LIMIT 1',
            [$orderId]
        );
    }

    public static function findByToken(string $checkoutToken): ?array
    {
        return Database::fetch(
            'SELECT t.*, o.public_id, o.checkout_token, o.customer_name, o.customer_email, o.description, m.business_name, p.name AS provider_name
             FROM transactions t JOIN payment_orders o ON o.id = t.order_id JOIN merchants m ON m.id = t.merchant_id
             JOIN payment_providers p ON p.id = t.provider_id WHERE o.checkout_token = ? LIMIT 1',
            [$checkoutToken]
        );
    }

    public static function utrExists(string $utr, ?int $excludeOrderId = null): bool
    {
        if ($excludeOrderId) {
            return (int) Database::scalar('SELECT COUNT(*) FROM transactions WHERE utr = ? AND order_id != ?', [$utr, $excludeOrderId]) > 0;
        }
        return (int) Database::scalar('SELECT COUNT(*) FROM transactions WHERE utr = ?', [$utr]) > 0;
    }

    public static function setRefund(int $id, string $status, string $note): bool
    {
        if (!in_array($status, ['none', 'manual_pending', 'manual_completed'], true)) {
            return false;
        }
        return Database::execute(
            'UPDATE transactions SET refund_status = ?, refund_note = ? WHERE id = ?',
            [$status, mb_substr($note, 0, 500), $id]
        ) > 0;
    }

    public static function listForMerchant(int $merchantId, array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $where = 't.merchant_id = ?';
        $params = [$merchantId];
        if (!empty($filters['q'])) {
            $where .= ' AND (o.public_id LIKE ? OR t.utr LIKE ? OR o.customer_email LIKE ?)';
            $like = '%' . $filters['q'] . '%';
            array_push($params, $like, $like, $like);
        }
        if (!empty($filters['from'])) {
            $where .= ' AND t.verified_at >= ?';
            $params[] = $filters['from'] . ' 00:00:00';
        }
        if (!empty($filters['to'])) {
            $where .= ' AND t.verified_at <= ?';
            $params[] = $filters['to'] . ' 23:59:59';
        }
        if (!empty($filters['min_amount'])) {
            $where .= ' AND t.amount >= ?';
            $params[] = (float) $filters['min_amount'];
        }
        if (!empty($filters['max_amount'])) {
            $where .= ' AND t.amount <= ?';
            $params[] = (float) $filters['max_amount'];
        }
        $offset = max(0, ($page - 1) * $perPage);
        $total = (int) Database::scalar("SELECT COUNT(*) FROM transactions t JOIN payment_orders o ON o.id = t.order_id WHERE {$where}", $params);
        $rows = Database::fetchAll(
            "SELECT t.*, o.public_id, o.customer_name, o.customer_email, o.description, m.business_name, p.name AS provider_name,
                    i.invoice_number, i.pdf_path
             FROM transactions t JOIN payment_orders o ON o.id = t.order_id JOIN merchants m ON m.id = t.merchant_id
             JOIN payment_providers p ON p.id = t.provider_id LEFT JOIN invoices i ON i.transaction_id = t.id
             WHERE {$where} ORDER BY t.verified_at DESC LIMIT {$perPage} OFFSET {$offset}",
            $params
        );
        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage, 'pages' => max(1, (int) ceil($total / $perPage))];
    }

    public static function listAll(array $filters = [], int $page = 1, int $perPage = 25): array
    {
        $where = '1=1';
        $params = [];
        if (!empty($filters['merchant_id'])) {
            $where .= ' AND t.merchant_id = ?';
            $params[] = (int) $filters['merchant_id'];
        }
        if (!empty($filters['provider_id'])) {
            $where .= ' AND t.provider_id = ?';
            $params[] = (int) $filters['provider_id'];
        }
        if (!empty($filters['q'])) {
            $where .= ' AND (o.public_id LIKE ? OR t.utr LIKE ? OR m.business_name LIKE ? OR o.customer_email LIKE ?)';
            $like = '%' . $filters['q'] . '%';
            array_push($params, $like, $like, $like, $like);
        }
        if (!empty($filters['from'])) {
            $where .= ' AND t.verified_at >= ?';
            $params[] = $filters['from'] . ' 00:00:00';
        }
        if (!empty($filters['to'])) {
            $where .= ' AND t.verified_at <= ?';
            $params[] = $filters['to'] . ' 23:59:59';
        }
        $offset = max(0, ($page - 1) * $perPage);
        $total = (int) Database::scalar(
            "SELECT COUNT(*) FROM transactions t JOIN payment_orders o ON o.id = t.order_id JOIN merchants m ON m.id = t.merchant_id WHERE {$where}",
            $params
        );
        $rows = Database::fetchAll(
            "SELECT t.*, o.public_id, o.customer_name, o.customer_email, m.business_name, m.merchant_code, p.name AS provider_name
             FROM transactions t JOIN payment_orders o ON o.id = t.order_id JOIN merchants m ON m.id = t.merchant_id
             JOIN payment_providers p ON p.id = t.provider_id
             WHERE {$where} ORDER BY t.verified_at DESC LIMIT {$perPage} OFFSET {$offset}",
            $params
        );
        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage, 'pages' => max(1, (int) ceil($total / $perPage))];
    }
}
