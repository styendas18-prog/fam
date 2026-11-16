<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class InvoiceModel
{
    public static function nextNumber(): string
    {
        $year = gmdate('Y');
        $prefix = 'MF-' . $year . '-';
        $last = Database::scalar(
            'SELECT invoice_number FROM invoices WHERE invoice_number LIKE ? ORDER BY id DESC LIMIT 1',
            [$prefix . '%']
        );
        $next = 1;
        if (is_string($last) && preg_match('/(\d+)$/', $last, $m)) {
            $next = (int) $m[1] + 1;
        }
        return $prefix . str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }

    public static function create(array $data): int
    {
        return Database::insert(
            'INSERT INTO invoices (invoice_number, order_id, transaction_id, merchant_id, amount, currency, customer_name, customer_email, issued_at, pdf_path, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(), ?, UTC_TIMESTAMP())',
            [
                $data['invoice_number'],
                $data['order_id'],
                $data['transaction_id'] ?? null,
                $data['merchant_id'],
                $data['amount'],
                $data['currency'] ?? 'INR',
                $data['customer_name'] ?? null,
                $data['customer_email'] ?? null,
                $data['pdf_path'],
            ]
        );
    }

    public static function findByInvoiceNumber(string $number): ?array
    {
        return Database::fetch(
            'SELECT i.*, o.public_id, o.checkout_token, m.business_name, m.merchant_code, m.support_email
             FROM invoices i JOIN payment_orders o ON o.id = i.order_id JOIN merchants m ON m.id = i.merchant_id
             WHERE i.invoice_number = ? LIMIT 1',
            [$number]
        );
    }

    public static function listForMerchant(int $merchantId, int $page = 1, int $perPage = 20): array
    {
        $offset = max(0, ($page - 1) * $perPage);
        $total = (int) Database::scalar('SELECT COUNT(*) FROM invoices WHERE merchant_id = ?', [$merchantId]);
        $rows = Database::fetchAll(
            "SELECT i.*, o.public_id, o.checkout_token FROM invoices i JOIN payment_orders o ON o.id = i.order_id
             WHERE i.merchant_id = ? ORDER BY i.issued_at DESC LIMIT {$perPage} OFFSET {$offset}",
            [$merchantId]
        );
        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage, 'pages' => max(1, (int) ceil($total / $perPage))];
    }

    public static function listAll(int $page = 1, int $perPage = 25): array
    {
        $offset = max(0, ($page - 1) * $perPage);
        $total = (int) Database::scalar('SELECT COUNT(*) FROM invoices');
        $rows = Database::fetchAll(
            "SELECT i.*, o.public_id, m.business_name FROM invoices i JOIN payment_orders o ON o.id = i.order_id
             JOIN merchants m ON m.id = i.merchant_id ORDER BY i.issued_at DESC LIMIT {$perPage} OFFSET {$offset}"
        );
        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage, 'pages' => max(1, (int) ceil($total / $perPage))];
    }
}
