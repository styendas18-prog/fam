<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\HttpClient;
use App\Core\Logger;
use App\Core\Security;
use App\Models\WebhookModel;
use Throwable;

final class WebhookDispatcher
{
    /**
     * Deliver one queued webhook with SSRF protection and response tracking.
     * @return array{success:bool,status:int,message:string}
     */
    public static function deliver(int $deliveryId): array
    {
        $delivery = Database::fetch(
            'SELECT d.*, w.url, w.secret_encrypted, w.merchant_id, w.id AS hook_id
             FROM webhook_deliveries d JOIN webhooks w ON w.id = d.webhook_id WHERE d.id = ? LIMIT 1',
            [$deliveryId]
        );
        if (!$delivery || $delivery['status'] === 'success') {
            return ['success' => false, 'status' => 0, 'message' => 'Delivery not found or already succeeded.'];
        }
        $secret = WebhookModel::decryptSecret($delivery);
        if ($secret === null) {
            self::recordFailure($delivery, 0, 'Webhook secret could not be decrypted.', 'secret_error');
            return ['success' => false, 'status' => 0, 'message' => 'Webhook secret unavailable.'];
        }
        try {
            $safe = Security::safeOutboundUrl((string) $delivery['url']);
        } catch (Throwable $e) {
            self::recordFailure($delivery, 0, 'Blocked outbound URL: ' . $e->getMessage(), null, true);
            return ['success' => false, 'status' => 0, 'message' => $e->getMessage()];
        }

        $payload = (string) $delivery['payload'];
        $timestamp = (string) time();
        $response = HttpClient::request('POST', (string) $delivery['url'], [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'User-Agent' => 'MAXXfam-Webhooks/1.0',
            'X-MAXXfam-Event' => (string) $delivery['event'],
            'X-MAXXfam-Delivery' => (string) $delivery['id'],
            'X-MAXXfam-Timestamp' => $timestamp,
            'X-MAXXfam-Signature' => (string) $delivery['signature'],
        ], $payload, 8, $safe['ip']);

        if ($response['error'] !== null) {
            self::recordFailure($delivery, 0, 'Network error: ' . $response['error']);
            return ['success' => false, 'status' => 0, 'message' => $response['error']];
        }
        $ok = $response['status'] >= 200 && $response['status'] < 300;
        if ($ok) {
            Database::execute(
                'UPDATE webhook_deliveries SET status = "success", attempts = attempts + 1, response_code = ?, response_body = ?,
                 next_attempt_at = NULL, delivered_at = UTC_TIMESTAMP(), updated_at = UTC_TIMESTAMP() WHERE id = ?',
                [$response['status'], mb_substr($response['body'], 0, 1000), $delivery['id']]
            );
            Database::execute(
                'UPDATE webhooks SET last_delivery_at = UTC_TIMESTAMP(), last_status = "success", failure_count = 0 WHERE id = ?',
                [$delivery['hook_id']]
            );
            Logger::info('Webhook delivered', ['delivery' => $delivery['id'], 'status' => $response['status']]);
            return ['success' => true, 'status' => $response['status'], 'message' => 'Delivered successfully.'];
        }
        self::recordFailure($delivery, $response['status'], mb_substr($response['body'], 0, 1000));
        return ['success' => false, 'status' => $response['status'], 'message' => 'Endpoint returned HTTP ' . $response['status'] . '.'];
    }

    public static function sendTest(array $webhook): array
    {
        $secret = WebhookModel::decryptSecret($webhook);
        if ($secret === null) {
            return ['success' => false, 'status' => 0, 'message' => 'Secret unavailable.'];
        }
        $payload = [
            'id' => 'evt_test_' . bin2hex(random_bytes(8)),
            'event' => 'webhook.test',
            'created_at' => gmdate('c'),
            'data' => [
                'test' => true,
                'message' => 'This is a real signed test delivery from MAXXfam.',
                'merchant_id' => (int) $webhook['merchant_id'],
            ],
        ];
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $signature = hash_hmac('sha256', $json, $secret);
        $deliveryId = Database::insert(
            'INSERT INTO webhook_deliveries (webhook_id, merchant_id, order_id, event, payload, signature, status, attempts, next_attempt_at, created_at)
             VALUES (?, ?, NULL, "webhook.test", ?, ?, "pending", 0, UTC_TIMESTAMP(), UTC_TIMESTAMP())',
            [$webhook['id'], $webhook['merchant_id'], $json, $signature]
        );
        $result = self::deliver($deliveryId);
        $result['delivery_id'] = $deliveryId;
        return $result;
    }

    public static function processDue(int $limit = 50): array
    {
        $due = WebhookModel::dueDeliveries($limit);
        $results = [];
        foreach ($due as $delivery) {
            $results[] = ['id' => $delivery['id'], 'result' => self::deliver((int) $delivery['id'])];
        }
        return $results;
    }

    private static function recordFailure(array $delivery, int $status, string $message, ?string $type = null, bool $permanent = false): void
    {
        $attempts = (int) $delivery['attempts'] + 1;
        $schedule = WebhookModel::retrySchedule();
        $maxAttempts = count($schedule);
        $next = null;
        $newStatus = 'failed';
        if (!$permanent && $attempts < $maxAttempts) {
            $delay = $schedule[$attempts - 1] ?? end($schedule);
            $next = gmdate('Y-m-d H:i:s', time() + max(30, (int) $delay));
            $newStatus = 'pending';
        }
        Database::execute(
            'UPDATE webhook_deliveries SET status = ?, attempts = ?, response_code = ?, response_body = ?, next_attempt_at = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?',
            [$newStatus, $attempts, $status ?: null, mb_substr($message, 0, 1000), $next, $delivery['id']]
        );
        Database::execute(
            'UPDATE webhooks SET last_delivery_at = UTC_TIMESTAMP(), last_status = "failed", failure_count = failure_count + 1 WHERE id = ?',
            [$delivery['hook_id']]
        );
        if ($newStatus === 'failed') {
            try {
                Mailer::queueWebhookFailure($delivery, $message, $attempts);
            } catch (Throwable $e) {
                Logger::warning('Webhook failure email could not be queued', ['error' => $e->getMessage()]);
            }
        }
        Logger::warning('Webhook delivery failed', [
            'delivery' => $delivery['id'],
            'attempt' => $attempts,
            'status' => $status,
            'message' => mb_substr($message, 0, 200),
            'url' => Security::redactUrl((string) $delivery['url']),
        ]);
    }
}
