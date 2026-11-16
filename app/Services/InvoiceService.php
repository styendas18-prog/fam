<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Core\Security;
use App\Models\InvoiceModel;
use RuntimeException;

final class InvoiceService
{
    public static function issue(array $order, array $transaction): string
    {
        $existing = Database::fetch('SELECT pdf_path FROM invoices WHERE order_id = ? LIMIT 1', [$order['id']]);
        if ($existing) {
            return (string) $existing['pdf_path'];
        }
        $number = InvoiceModel::nextNumber();
        $providerName = (string) (Database::scalar('SELECT name FROM payment_providers WHERE id = ?', [$order['provider_id']]) ?: 'UPI Provider');
        $issuedAt = gmdate('Y-m-d H:i:s');
        $amount = (float) ($transaction['amount'] ?? $order['payable_amount'] ?? $order['amount'] ?? 0);
        $data = [
            'invoice_number' => $number,
            'amount' => $amount,
            'business_name' => $order['business_name'] ?? 'Merchant',
            'merchant_code' => $order['merchant_code'] ?? '—',
            'support_email' => $order['support_email'] ?? '',
            'order_id' => $order['public_id'] ?? '—',
            'provider_order_id' => $order['provider_order_id'] ?? '',
            'provider_name' => $providerName,
            'utr' => $transaction['utr'],
            'sender_name' => $transaction['sender_name'] ?? '',
            'verified_at_ist' => ist($transaction['verified_at']),
            'issued_at_ist' => ist($issuedAt),
            'customer_name' => $order['customer_name'] ?? '',
            'customer_email' => $order['customer_email'] ?? '',
            'description' => $order['description'] ?? '',
        ];
        $pdf = PdfService::invoice($data);
        $relative = 'invoices/' . $number . '.pdf';
        $absolute = BASE_PATH . '/storage/' . $relative;
        if (!is_dir(dirname($absolute)) && !mkdir(dirname($absolute), 0775, true) && !is_dir(dirname($absolute))) {
            throw new RuntimeException('Unable to create invoice directory.');
        }
        if (file_put_contents($absolute, $pdf, LOCK_EX) === false) {
            throw new RuntimeException('Unable to write invoice PDF.');
        }
        InvoiceModel::create([
            'invoice_number' => $number,
            'order_id' => (int) $order['id'],
            'transaction_id' => $transaction['id'] ?? null,
            'merchant_id' => (int) $order['merchant_id'],
            'amount' => $amount,
            'currency' => 'INR',
            'customer_name' => $order['customer_name'] ?? null,
            'customer_email' => $order['customer_email'] ?? null,
            'pdf_path' => $relative,
        ]);
        Logger::info('Invoice generated', ['order' => $order['public_id'], 'invoice' => $number]);
        return $relative;
    }

    public static function absolutePath(string $relative): ?string
    {
        $clean = str_replace(['..', '\\'], ['', '/'], $relative);
        $absolute = BASE_PATH . '/storage/' . ltrim($clean, '/');
        return is_file($absolute) ? $absolute : null;
    }

    public static function stream(string $relative, bool $download = true): never
    {
        $path = self::absolutePath($relative);
        if (!$path) {
            abort(404, 'Receipt not found.');
        }
        $name = basename($path);
        header('Content-Type: application/pdf');
        header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="' . $name . '"');
        header('Content-Length: ' . (string) filesize($path));
        header('Cache-Control: private, no-store');
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        exit;
    }

    public static function reportPdf(array $filters): array
    {
        $rows = \App\Models\AnalyticsModel::reportRows($filters);
        $summary = ['Generated: ' . gmdate('d M Y H:i') . ' UTC', 'Records: ' . count($rows) . ' (maximum 5,000)'];
        $total = array_sum(array_map(static fn ($r) => (float) $r['amount'], $rows));
        $summary[] = 'Verified volume: INR ' . number_format($total, 2);
        if (!empty($filters['from']) || !empty($filters['to'])) {
            $summary[] = 'Range: ' . ($filters['from'] ?: 'beginning') . ' to ' . ($filters['to'] ?: 'now') . ' (UTC)';
        }
        $tableRows = [];
        foreach ($rows as $row) {
            $tableRows[] = [
                ist($row['verified_at'], 'd M y H:i'),
                $row['public_id'],
                $row['business_name'],
                'INR ' . number_format((float) $row['amount'], 2),
                $row['utr'],
            ];
        }
        $pdf = PdfService::report([
            'title' => 'Verified Payments Report',
            'subtitle' => 'Source: transactions table. Only provider-verified payments are included.',
            'summary' => $summary,
            'headers' => ['Verified (IST)', 'Order', 'Merchant', 'Amount', 'UTR'],
            'rows' => $tableRows,
        ]);
        $name = 'maxxfam-report-' . gmdate('Ymd-His') . '.pdf';
        $relative = 'reports/' . $name;
        $absolute = BASE_PATH . '/storage/' . $relative;
        if (!is_dir(dirname($absolute))) {
            mkdir(dirname($absolute), 0775, true);
        }
        file_put_contents($absolute, $pdf, LOCK_EX);
        return [$absolute, $name, $pdf];
    }
}
