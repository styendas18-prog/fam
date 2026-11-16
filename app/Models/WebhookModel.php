<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Crypto;
use App\Core\Database;
use App\Core\Security;
use RuntimeException;
use Throwable;

final class WebhookModel
{
    public const EVENTS = [
        'payment.created',
        'payment.pending',
        'payment.success',
        'payment.failed',
        'payment.expired',
        'webhook.test',
    ];

    public static function listForMerchant(int $merchantId): array
    {
        $rows = Database::fetchAll('SELECT * FROM webhooks WHERE merchant_id = ? ORDER BY created_at DESC', [$merchantId]);
        foreach ($rows as &$row) {
            $row['events_array'] = json_decode((string) $row['events'], true) ?: [];
        }
        return $rows;
    }

    public static function forMerchant(int $merchantId): ?array
    {
        $row = Database::fetch(
            'SELECT * FROM webhooks WHERE merchant_id = ? AND is_active = 1 ORDER BY created_at DESC LIMIT 1',
            [$merchantId]
        );
        if ($row) {
            $row['events_array'] = json_decode((string) $row['events'], true) ?: [];
        }
        return $row;
    }

    public static function create(int $merchantId, string $url, array $events): array
    {
        $secret = 'whsec_' . bin2hex(random_bytes(24));
        $events = array_values(array_intersect(self::EVENTS, $events));
        if (!$events) {
            $events = ['payment.success'];
        }
        $id = Database::insert(
            'INSERT INTO webhooks (merchant_id, url, url_hash, secret_encrypted, events, is_active, created_at)
             VALUES (?, ?, ?, ?, ?, 1, UTC_TIMESTAMP())',
            [$merchantId, $url, hash('sha256', $url), Crypto::encrypt($secret), json_encode($events)]
        );
        return ['id' => $id, 'secret' => $secret];
    }

    public static function find(int $id, int $merchantId): ?array
    {
        $row = Database::fetch('SELECT * FROM webhooks WHERE id = ? AND merchant_id = ? LIMIT 1', [$id, $merchantId]);
        if ($row) {
            $row['events_array'] = json_decode((string) $row['events'], true) ?: [];
        }
        return $row;
    }

    public static function findAny(int $id): ?array
    {
        return Database::fetch('SELECT * FROM webhooks WHERE id = ? LIMIT 1', [$id]);
    }

    public static function decryptSecret(array $webhook): ?string
    {
        try {
            return Crypto::decrypt((string) $webhook['secret_encrypted']);
        } catch (Throwable) {
            return null;
        }
    }

    public static function update(int $id, int $merchantId, string $url, array $events, bool $active): void
    {
        $events = array_values(array_intersect(self::EVENTS, $events)) ?: ['payment.success'];
        Database::execute(
            'UPDATE webhooks SET url = ?, url_hash = ?, events = ?, is_active = ? WHERE id = ? AND merchant_id = ?',
            [$url, hash('sha256', $url), json_encode($events), $active ? 1 : 0, $id, $merchantId]
        );
    }

    public static function rotate(int $id, int $merchantId): string
    {
        $secret = 'whsec_' . bin2hex(random_bytes(24));
        Database::execute(
            'UPDATE webhooks SET secret_encrypted = ? WHERE id = ? AND merchant_id = ?',
            [Crypto::encrypt($secret), $id, $merchantId]
        );
        return $secret;
    }

    public static function delete(int $id, int $merchantId): bool
    {
        return Database::execute('DELETE FROM webhooks WHERE id = ? AND merchant_id = ?', [$id, $merchantId]) > 0;
    }

    /**
     * Queue a signed event for every active webhook subscribed to it.
     * Returns the number of deliveries queued.
     */
    public static function listAll(array $filters = [], int $page = 1, int $perPage = 25): array
    {
        $where = '1=1';
        $params = [];
        if (!empty($filters['q'])) {
            $where .= ' AND (w.url LIKE ? OR m.business_name LIKE ? OR m.merchant_code LIKE ?)';
            $like = '%' . $filters['q'] . '%';
            array_push($params, $like, $like, $like);
        }
        if (isset($filters['active']) && $filters['active'] !== '') {
            $where .= ' AND w.is_active = ?';
            $params[] = (int) $filters['active'];
        }
        $offset = max(0, ($page - 1) * $perPage);
        $total = (int) Database::scalar("SELECT COUNT(*) FROM webhooks w JOIN merchants m ON m.id = w.merchant_id WHERE {$where}", $params);
        $rows = Database::fetchAll(
            "SELECT w.*, m.business_name, m.merchant_code,
                    (SELECT COUNT(*) FROM webhook_deliveries d WHERE d.webhook_id = w.id) AS delivery_count,
                    (SELECT COUNT(*) FROM webhook_deliveries d WHERE d.webhook_id = w.id AND d.status = 'failed') AS failed_count
             FROM webhooks w JOIN merchants m ON m.id = w.merchant_id
             WHERE {$where} ORDER BY w.created_at DESC LIMIT {$perPage} OFFSET {$offset}",
            $params
        );
        foreach ($rows as &$row) {
            $row['events_array'] = json_decode((string) $row['events'], true) ?: [];
        }
        unset($row);
        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage, 'pages' => max(1, (int) ceil($total / $perPage))];
    }

    public static function allDeliveries(array $filters = [], int $page = 1, int $perPage = 30): array
    {
        $where = '1=1';
        $params = [];
        foreach (['status', 'event'] as $key) {
            if (!empty($filters[$key])) {
                $where .= " AND d.{$key} = ?";
                $params[] = $filters[$key];
            }
        }
        if (!empty($filters['q'])) {
            $where .= ' AND (d.payload LIKE ? OR w.url LIKE ? OR m.business_name LIKE ?)';
            $like = '%' . $filters['q'] . '%';
            array_push($params, $like, $like, $like);
        }
        $offset = max(0, ($page - 1) * $perPage);
        $total = (int) Database::scalar(
            "SELECT COUNT(*) FROM webhook_deliveries d JOIN webhooks w ON w.id = d.webhook_id JOIN merchants m ON m.id = d.merchant_id WHERE {$where}",
            $params
        );
        $rows = Database::fetchAll(
            "SELECT d.*, w.url, m.business_name FROM webhook_deliveries d
             JOIN webhooks w ON w.id = d.webhook_id JOIN merchants m ON m.id = d.merchant_id
             WHERE {$where} ORDER BY d.created_at DESC LIMIT {$perPage} OFFSET {$offset}",
            $params
        );
        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage, 'pages' => max(1, (int) ceil($total / $perPage))];
    }

    public static function requeue(int $deliveryId): bool
    {
        return Database::execute(
            'UPDATE webhook_deliveries SET status = "pending", next_attempt_at = UTC_TIMESTAMP(), updated_at = UTC_TIMESTAMP() WHERE id = ? AND status != "success"',
            [$deliveryId]
        ) > 0;
    }

    /**
     * Queue an event for every active endpoint subscribed to it.
     *
     * @return int Number of delivery rows created (not a delivery id).
     */
    public static function enqueue(int $merchantId, string $event, array $data, ?int $orderId = null): int
    {
        $hooks = Database::fetchAll('SELECT * FROM webhooks WHERE merchant_id = ? AND is_active = 1', [$merchantId]);
        $queued = 0;
        foreach ($hooks as $hook) {
            $events = json_decode((string) $hook['events'], true) ?: [];
            if (!in_array($event, $events, true)) {
                continue;
            }
            $payload = [
                'id' => 'evt_' . bin2hex(random_bytes(12)),
                'event' => $event,
                'created_at' => gmdate('c'),
                'data' => $data,
            ];
            $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $secret = self::decryptSecret($hook);
            if ($secret === null) {
                continue;
            }
            $signature = hash_hmac('sha256', $json, $secret);
            Database::insert(
                'INSERT INTO webhook_deliveries (webhook_id, merchant_id, order_id, event, payload, signature, status, attempts, next_attempt_at, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, "pending", 0, UTC_TIMESTAMP(), UTC_TIMESTAMP())',
                [$hook['id'], $merchantId, $orderId, $event, $json, $signature]
            );
            $queued++;
        }
        return $queued;
    }

    public static function deliveries(int $merchantId, array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $where = 'd.merchant_id = ?';
        $params = [$merchantId];
        if (!empty($filters['status'])) {
            $where .= ' AND d.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['event'])) {
            $where .= ' AND d.event = ?';
            $params[] = $filters['event'];
        }
        if (!empty($filters['webhook_id'])) {
            $where .= ' AND d.webhook_id = ?';
            $params[] = (int) $filters['webhook_id'];
        }
        $offset = max(0, ($page - 1) * $perPage);
        $total = (int) Database::scalar("SELECT COUNT(*) FROM webhook_deliveries d WHERE {$where}", $params);
        $rows = Database::fetchAll(
            "SELECT d.*, w.url FROM webhook_deliveries d JOIN webhooks w ON w.id = d.webhook_id
             WHERE {$where} ORDER BY d.created_at DESC LIMIT {$perPage} OFFSET {$offset}",
            $params
        );
        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage, 'pages' => max(1, (int) ceil($total / $perPage))];
    }

    public static function dueDeliveries(int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        return Database::fetchAll(
            "SELECT d.*, w.url, w.secret_encrypted FROM webhook_deliveries d JOIN webhooks w ON w.id = d.webhook_id
             WHERE d.status IN ('pending','failed') AND d.next_attempt_at IS NOT NULL AND d.next_attempt_at <= UTC_TIMESTAMP()
             ORDER BY d.next_attempt_at ASC LIMIT {$limit}"
        );
    }

    public static function retrySchedule(): array
    {
        $raw = Database::scalar("SELECT setting_value FROM system_settings WHERE setting_key = 'webhook_retry_schedule'");
        $schedule = json_decode((string) $raw, true);
        return is_array($schedule) && $schedule ? array_map('intval', $schedule) : [60, 300, 900, 3600, 21600];
    }
}
