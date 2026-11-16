<?php
declare(strict_types=1);

/**
 * FamGateway provider webhook receiver.
 *
 * Security model:
 *  1. The raw request body is authenticated with HMAC-SHA256 before any state changes.
 *  2. The webhook body is never trusted as proof of payment.
 *  3. Payment state is reconciled by calling the provider's authoritative
 *     status API (VerificationService::verify) after signature validation.
 *  4. Identical payloads are recorded by hash and safely de-duplicated.
 */

require dirname(__DIR__, 3) . '/app/bootstrap.php';

use App\Core\Database;
use App\Core\Logger;
use App\Models\PaymentOrderModel;
use App\Models\ProviderModel;
use App\Providers\IntegrationNotConfiguredException;
use App\Providers\ProviderException;
use App\Providers\ProviderManager;
use App\Services\VerificationService;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$rawBody = file_get_contents('php://input') ?: '';
$signature = (string) ($_SERVER['HTTP_X_FAMGATEWAY_SIGNATURE'] ?? $_SERVER['REDIRECT_HTTP_X_FAMGATEWAY_SIGNATURE'] ?? '');
$payloadHash = hash('sha256', $rawBody);

$respond = static function (int $status, array $body): void {
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_SLASHES);
    exit;
};

$provider = ProviderModel::findByCode('famgateway');
if (!$provider) {
    $respond(500, ['status' => 'error', 'message' => 'Provider not configured on this platform.']);
}

$payload = json_decode($rawBody, true);
if (!is_array($payload)) {
    $respond(400, ['status' => 'error', 'message' => 'Invalid JSON payload.']);
}

$providerOrderId = (string) ($payload['order_id'] ?? $payload['data']['order_id'] ?? '');
if ($providerOrderId === '' || strlen($providerOrderId) > 64 || !preg_match('/^[A-Za-z0-9_\-]+$/', $providerOrderId)) {
    $respond(400, ['status' => 'error', 'message' => 'Missing or invalid order identifier in webhook payload.']);
}

$order = PaymentOrderModel::findByProviderOrder((int) $provider['id'], $providerOrderId);
if (!$order) {
    // Also allow the platform public ID as a defensive fallback.
    $order = Database::fetch(
        'SELECT o.*, p.code AS provider_code, m.support_email
         FROM payment_orders o JOIN payment_providers p ON p.id = o.provider_id
         JOIN merchants m ON m.id = o.merchant_id
         WHERE o.public_id = ? LIMIT 1',
        [$providerOrderId]
    );
}

if (!$order) {
    $respond(404, ['status' => 'error', 'message' => 'Order not found for the supplied provider order id.']);
}

$credentials = null;
try {
    $credentials = ProviderManager::mergedCredentialsFor($provider, (int) $order['merchant_id']);
} catch (Throwable $e) {
    Logger::error('Webhook credential lookup failed', ['order' => $order['public_id'], 'error' => $e->getMessage()]);
}

if (!$credentials) {
    $respond(409, ['status' => 'error', 'message' => 'Merchant provider credentials are not configured.']);
}

$adapter = ProviderManager::resolveRow($provider);

// Signature verification is mandatory. A webhook without a valid HMAC must never
// be able to move an order into a paid state.
try {
    $event = $adapter->processWebhook($rawBody, $signature !== '' ? $signature : null, $credentials);
} catch (IntegrationNotConfiguredException $e) {
    $respond(409, ['status' => 'error', 'message' => 'Provider integration is not configured.']);
} catch (ProviderException $e) {
    $logStatus = $e->providerCode === 'invalid_signature' ? 'failed_signature' : 'received';
    try {
        Database::execute(
            'INSERT INTO provider_webhook_events (provider_id, provider_order_id, event, utr, payload_hash, status, note, received_at)
             VALUES (?, ?, ?, NULL, ?, ?, ?, UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE status = VALUES(status), note = VALUES(note)',
            [
                (int) $provider['id'],
                $providerOrderId,
                mb_substr((string) ($payload['event'] ?? 'payment.webhook'), 0, 60),
                $payloadHash,
                $logStatus,
                mb_substr($e->getMessage(), 0, 500),
            ]
        );
    } catch (Throwable) {
    }
    Logger::warning('Provider webhook rejected before reconciliation', [
        'order' => $order['public_id'],
        'code' => $e->providerCode,
    ]);
    $respond($e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 401, [
        'status' => 'error',
        'message' => $e->getMessage(),
    ]);
}

$eventType = mb_substr((string) ($event['event'] ?? 'payment.webhook'), 0, 60) ?: 'payment.webhook';
$utr = isset($event['utr']) && is_string($event['utr']) && $event['utr'] !== '' ? mb_substr($event['utr'], 0, 32) : null;

// Record the authenticated event. The unique hash provides replay visibility.
$eventRow = Database::fetch(
    'SELECT * FROM provider_webhook_events WHERE provider_id = ? AND payload_hash = ? LIMIT 1',
    [(int) $provider['id'], $payloadHash]
);
if ($eventRow && $eventRow['status'] === 'processed') {
    $respond(200, ['status' => 'ok', 'message' => 'Duplicate webhook event already processed.', 'order_id' => $order['public_id']]);
}

if (!$eventRow) {
    try {
        $eventId = Database::insert(
            'INSERT INTO provider_webhook_events (provider_id, provider_order_id, event, utr, payload_hash, status, received_at)
             VALUES (?, ?, ?, ?, ?, "received", UTC_TIMESTAMP())',
            [(int) $provider['id'], $providerOrderId, $eventType, $utr, $payloadHash]
        );
    } catch (Throwable $e) {
        // A concurrent request may have inserted the same hash first.
        $eventId = (int) (Database::scalar(
            'SELECT id FROM provider_webhook_events WHERE provider_id = ? AND payload_hash = ? LIMIT 1',
            [(int) $provider['id'], $payloadHash]
        ) ?: 0);
    }
    $eventRow = Database::fetch('SELECT * FROM provider_webhook_events WHERE id = ? LIMIT 1', [$eventId]);
}

$eventId = (int) ($eventRow['id'] ?? 0);

try {
    // The webhook payload is only a trigger. Always ask the provider for the
    // authoritative status; only that response may mark the order successful.
    $fresh = VerificationService::verify($order, 'webhook', true);
    $note = 'Reconciled with provider status API. Local status: ' . $fresh['status'];

    if ($eventId > 0) {
        Database::execute(
            'UPDATE provider_webhook_events SET status = ?, utr = ?, note = ?, processed_at = UTC_TIMESTAMP() WHERE id = ?',
            [$fresh['status'] === 'success' ? 'processed' : 'ignored', $utr, mb_substr($note, 0, 500), $eventId]
        );
    }

    $respond(200, [
        'status' => 'ok',
        'message' => $fresh['status'] === 'success'
            ? 'Payment verified against the provider status API.'
            : 'Webhook accepted and reconciled with the provider status API.',
        'order_id' => $order['public_id'],
        'current_status' => $fresh['status'],
    ]);
} catch (Throwable $e) {
    if ($eventId > 0) {
        try {
            Database::execute(
                'UPDATE provider_webhook_events SET status = "retry", note = ? WHERE id = ?',
                [mb_substr($e->getMessage(), 0, 500), $eventId]
            );
        } catch (Throwable) {
        }
    }
    Logger::error('Webhook reconciliation failed', ['order' => $order['public_id'], 'error' => $e->getMessage()]);
    $respond(500, ['status' => 'error', 'message' => 'Webhook accepted but reconciliation is temporarily unavailable. Provider should retry.']);
}
