<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use DateInterval;
use DatePeriod;
use DateTimeImmutable;
use DateTimeZone;

final class AnalyticsModel
{
    public static function merchantSeries(int $merchantId, int $days = 14): array
    {
        $days = max(1, min(365, $days));
        $start = (new DateTimeImmutable('-' . ($days - 1) . ' days', new DateTimeZone('UTC')))->format('Y-m-d');
        $rows = Database::fetchAll(
            'SELECT DATE(paid_at) AS d, COUNT(*) AS count, COALESCE(SUM(payable_amount),0) AS volume
             FROM payment_orders WHERE merchant_id = ? AND status = "success" AND paid_at >= ? GROUP BY DATE(paid_at)',
            [$merchantId, $start . ' 00:00:00']
        );
        $map = [];
        foreach ($rows as $row) {
            $map[$row['d']] = $row;
        }
        $labels = [];
        $volume = [];
        $counts = [];
        $period = new DatePeriod(new DateTimeImmutable($start, new DateTimeZone('UTC')), new DateInterval('P1D'), $days);
        foreach ($period as $day) {
            $key = $day->format('Y-m-d');
            $labels[] = $day->format('d M');
            $volume[] = round((float) ($map[$key]['volume'] ?? 0), 2);
            $counts[] = (int) ($map[$key]['count'] ?? 0);
        }
        return ['labels' => $labels, 'volume' => $volume, 'counts' => $counts];
    }

    public static function statusBreakdown(int $merchantId): array
    {
        $rows = Database::fetchAll('SELECT status, COUNT(*) AS count FROM payment_orders WHERE merchant_id = ? GROUP BY status', [$merchantId]);
        $out = ['pending' => 0, 'success' => 0, 'failed' => 0, 'expired' => 0, 'cancelled' => 0];
        foreach ($rows as $row) {
            $out[$row['status']] = (int) $row['count'];
        }
        return $out;
    }

    public static function providerPerformance(int $merchantId): array
    {
        return Database::fetchAll(
            'SELECT p.name, COUNT(o.id) AS total, SUM(o.status = "success") AS success,
                    COALESCE(SUM(CASE WHEN o.status = "success" THEN o.payable_amount ELSE 0 END),0) AS volume
             FROM payment_orders o JOIN payment_providers p ON p.id = o.provider_id
             WHERE o.merchant_id = ? GROUP BY p.id, p.name ORDER BY total DESC',
            [$merchantId]
        );
    }

    public static function apiUsage(int $merchantId, int $days = 7): array
    {
        $days = max(1, min(60, $days));
        $start = (new DateTimeImmutable('-' . ($days - 1) . ' days', new DateTimeZone('UTC')))->format('Y-m-d');
        $rows = Database::fetchAll(
            'SELECT DATE(created_at) AS d, COUNT(*) AS count, SUM(status_code >= 400) AS errors
             FROM api_logs WHERE merchant_id = ? AND created_at >= ? GROUP BY DATE(created_at)',
            [$merchantId, $start . ' 00:00:00']
        );
        $map = [];
        foreach ($rows as $row) {
            $map[$row['d']] = $row;
        }
        $labels = [];
        $counts = [];
        $errors = [];
        foreach (new DatePeriod(new DateTimeImmutable($start, new DateTimeZone('UTC')), new DateInterval('P1D'), $days) as $day) {
            $key = $day->format('Y-m-d');
            $labels[] = $day->format('d M');
            $counts[] = (int) ($map[$key]['count'] ?? 0);
            $errors[] = (int) ($map[$key]['errors'] ?? 0);
        }
        return ['labels' => $labels, 'counts' => $counts, 'errors' => $errors];
    }

    public static function reportRows(array $filters): array
    {
        $where = '1=1';
        $params = [];
        if (!empty($filters['from'])) {
            $where .= ' AND t.verified_at >= ?';
            $params[] = $filters['from'] . ' 00:00:00';
        }
        if (!empty($filters['to'])) {
            $where .= ' AND t.verified_at <= ?';
            $params[] = $filters['to'] . ' 23:59:59';
        }
        if (!empty($filters['merchant_id'])) {
            $where .= ' AND t.merchant_id = ?';
            $params[] = (int) $filters['merchant_id'];
        }
        if (!empty($filters['provider_id'])) {
            $where .= ' AND t.provider_id = ?';
            $params[] = (int) $filters['provider_id'];
        }
        if (!empty($filters['min_amount'])) {
            $where .= ' AND t.amount >= ?';
            $params[] = (float) $filters['min_amount'];
        }
        if (!empty($filters['max_amount'])) {
            $where .= ' AND t.amount <= ?';
            $params[] = (float) $filters['max_amount'];
        }
        return Database::fetchAll(
            "SELECT t.id, t.utr, t.amount, t.currency, t.sender_name, t.verified_at, t.verification_source,
                    o.public_id, o.customer_name, o.customer_email, m.business_name, m.merchant_code, p.name AS provider_name
             FROM transactions t JOIN payment_orders o ON o.id = t.order_id JOIN merchants m ON m.id = t.merchant_id
             JOIN payment_providers p ON p.id = t.provider_id
             WHERE {$where} ORDER BY t.verified_at DESC LIMIT 5000",
            $params
        );
    }

    public static function adminSeries(int $days = 14): array
    {
        $days = max(1, min(365, $days));
        $start = (new DateTimeImmutable('-' . ($days - 1) . ' days', new DateTimeZone('UTC')))->format('Y-m-d');
        $rows = Database::fetchAll(
            'SELECT DATE(verified_at) AS d, COUNT(*) AS count, COALESCE(SUM(amount),0) AS volume
             FROM transactions WHERE verified_at >= ? GROUP BY DATE(verified_at)',
            [$start . ' 00:00:00']
        );
        $map = [];
        foreach ($rows as $row) {
            $map[$row['d']] = $row;
        }
        $labels = [];
        $volume = [];
        $counts = [];
        foreach (new DatePeriod(new DateTimeImmutable($start, new DateTimeZone('UTC')), new DateInterval('P1D'), $days) as $day) {
            $key = $day->format('Y-m-d');
            $labels[] = $day->format('d M');
            $volume[] = round((float) ($map[$key]['volume'] ?? 0), 2);
            $counts[] = (int) ($map[$key]['count'] ?? 0);
        }
        return ['labels' => $labels, 'volume' => $volume, 'counts' => $counts];
    }
}
