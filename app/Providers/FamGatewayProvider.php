<?php
declare(strict_types=1);

namespace App\Providers;

use App\Core\Config;
use App\Core\HttpClient;
use App\Core\Logger;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

/**
 * Adapter for the documented FamGateway v2 REST contract.
 *
 * Documented surface used here:
 *   POST /api/create-order             (X-Api-Key bearer-style header)
 *   GET  /api/verify-order.php         (authoritative server-to-server status)
 *   GET  /api/get-transactions.php     (authenticated read-only connectivity test)
 *   Webhook HMAC-SHA256 over the raw body using the merchant API key
 *   Header: X-FamGateway-Signature
 *
 * The provider does not document a refund API. supportsRefunds() therefore
 * returns false and no refund success can be fabricated by this adapter.
 */
final class FamGatewayProvider implements PaymentProviderInterface
{
    private string $baseUrl;

    public function __construct(?string $baseUrl = null)
    {
        $this->baseUrl = rtrim($baseUrl ?: (string) Config::get('providers.famgateway.base_url', 'https://famgateway.in'), '/');
    }

    public function createPayment(array $credentials, array $payload): array
    {
        $this->assertCredentials($credentials);
        $response = HttpClient::postJson($this->baseUrl . '/api/create-order', [
            'amount' => round((float) $payload['amount'], 2),
            'customer_name' => $payload['customer_name'] ?? null,
            'customer_email' => $payload['customer_email'] ?? null,
            'customer_phone' => $payload['customer_phone'] ?? null,
            'redirect_url' => $payload['redirect_url'] ?? null,
            'webhook_url' => $payload['webhook_url'] ?? null,
        ], [
            'X-Api-Key' => $credentials['api_key'],
            'Accept' => 'application/json',
        ], 25);

        $body = $this->decode($response, 'order creation');
        $status = strtolower((string) ($body['status'] ?? ''));
        if ($status !== 'success') {
            throw new ProviderException(
                $this->safeMessage($body, 'The provider rejected the order creation request.'),
                $response['status'] >= 400 ? $response['status'] : 422,
                'create_rejected'
            );
        }
        $data = is_array($body['data'] ?? null) ? $body['data'] : [];
        $orderId = trim((string) ($data['order_id'] ?? ''));
        if ($orderId === '' || strlen($orderId) > 64 || !preg_match('/^[A-Za-z0-9_\-]+$/', $orderId)) {
            throw new ProviderException('Provider response did not contain a valid order identifier.', 502, 'invalid_order_id');
        }
        $payable = $data['payable_amount'] ?? $data['amount'] ?? null;
        if (!is_numeric($payable) || (float) $payable <= 0) {
            throw new ProviderException('Provider response did not contain a valid payable amount.', 502, 'invalid_amount');
        }
        $qrUrl = isset($data['qr_url']) ? (string) $data['qr_url'] : null;
        if ($qrUrl === null || !$this->isProviderUrl($qrUrl)) {
            throw new ProviderException('Provider response did not contain a trusted QR URL.', 502, 'invalid_qr_url');
        }
        $checkoutUrl = isset($data['checkout_url']) ? (string) $data['checkout_url'] : null;
        if ($checkoutUrl !== null && !$this->isProviderUrl($checkoutUrl)) {
            throw new ProviderException('Provider response contained an untrusted checkout URL.', 502, 'invalid_checkout_url');
        }
        $upiIntent = isset($data['upi_intent']) ? (string) $data['upi_intent'] : null;
        if ($upiIntent !== null && !str_starts_with($upiIntent, 'upi://pay?')) {
            $upiIntent = null;
        }
        $expires = $this->parseIst((string) ($data['expires_at_ist'] ?? '')) ?? gmdate('Y-m-d H:i:s', time() + 300);
        if (strtotime($expires . ' UTC') <= time()) {
            $expires = gmdate('Y-m-d H:i:s', time() + 300);
        }

        return [
            'provider_order_id' => $orderId,
            'amount' => (float) ($data['amount'] ?? $payload['amount']),
            'payable_amount' => round((float) $payable, 2),
            'qr_url' => $qrUrl,
            'checkout_url' => $checkoutUrl,
            'upi_intent' => $upiIntent,
            'upi_id' => isset($data['upi_id']) ? (string) $data['upi_id'] : null,
            'expires_at' => $expires,
            'raw' => $body,
        ];
    }

    public function getPaymentStatus(array $credentials, string $providerOrderId): array
    {
        $this->assertCredentials($credentials);
        $url = $this->baseUrl . '/api/verify-order.php?order_id=' . rawurlencode($providerOrderId);
        $response = HttpClient::get($url, [
            'X-Api-Key' => $credentials['api_key'],
            'Accept' => 'application/json',
        ], 20);
        $body = $this->decode($response, 'status verification');
        $status = strtolower((string) ($body['status'] ?? ''));
        $data = is_array($body['data'] ?? null) ? $body['data'] : $body;
        if ($status === '' && isset($data['status'])) {
            $status = strtolower((string) $data['status']);
        }
        if (!in_array($status, ['success', 'pending', 'expired', 'failed', 'cancelled'], true)) {
            throw new ProviderException('Provider returned an unrecognised payment status.', 502, 'unknown_status');
        }
        $utr = isset($data['utr']) && $data['utr'] !== '' ? (string) $data['utr'] : null;
        $amount = isset($data['amount']) && is_numeric($data['amount']) ? (float) $data['amount'] : null;
        $paidAt = null;
        foreach (['payment_time_ist', 'payment_time'] as $key) {
            if (!empty($data[$key])) {
                $paidAt = $this->parseIst((string) $data[$key]);
                if ($paidAt !== null) {
                    break;
                }
            }
        }
        if ($status === 'success') {
            if ($utr === null || !preg_match('/^\d{12}$/', $utr)) {
                throw new ProviderException('Provider reported success without a valid 12-digit UTR. Payment cannot be verified.', 502, 'missing_utr');
            }
            if ($amount === null) {
                throw new ProviderException('Provider reported success without an amount. Payment cannot be verified.', 502, 'missing_amount');
            }
        }
        return [
            'status' => $status,
            'provider_order_id' => (string) ($data['order_id'] ?? $body['order_id'] ?? $providerOrderId),
            'amount' => $amount,
            'payable_amount' => $amount,
            'utr' => $utr,
            'transaction_id' => isset($data['transaction_id']) ? (string) $data['transaction_id'] : null,
            'sender_name' => isset($data['sender_name']) ? (string) $data['sender_name'] : null,
            'paid_at' => $paidAt,
            'raw' => $body,
        ];
    }

    public function processWebhook(string $rawBody, ?string $signature, array $credentials): array
    {
        $this->assertCredentials($credentials);
        if ($signature === null || !preg_match('/^[a-f0-9]{64}$/i', $signature)) {
            throw new ProviderException('Missing or malformed webhook signature.', 401, 'invalid_signature');
        }
        $expected = hash_hmac('sha256', $rawBody, $credentials['api_key']);
        if (!hash_equals($expected, strtolower($signature))) {
            throw new ProviderException('Webhook signature verification failed.', 401, 'invalid_signature');
        }
        $body = json_decode($rawBody, true);
        if (!is_array($body)) {
            throw new ProviderException('Webhook body is not valid JSON.', 400, 'invalid_payload');
        }
        $orderId = (string) ($body['order_id'] ?? '');
        if ($orderId === '' || strlen($orderId) > 64 || !preg_match('/^[A-Za-z0-9_\-]+$/', $orderId)) {
            throw new ProviderException('Webhook payload has no valid order identifier.', 400, 'invalid_payload');
        }
        return [
            'event' => (string) ($body['event'] ?? ''),
            'order_id' => $orderId,
            'utr' => isset($body['utr']) ? (string) $body['utr'] : null,
            'amount' => isset($body['amount']) ? (string) $body['amount'] : null,
            'status' => strtolower((string) ($body['status'] ?? '')),
            'raw' => $body,
        ];
    }

    public function testConnection(array $credentials): array
    {
        $this->assertCredentials($credentials);
        $response = HttpClient::get(
            $this->baseUrl . '/api/get-transactions.php?filter=all&date=today&limit=1&page=1',
            ['X-Api-Key' => $credentials['api_key'], 'Accept' => 'application/json'],
            20
        );
        if ($response['error'] !== null) {
            return ['connected' => false, 'message' => 'Network error: ' . $this->sanitize($response['error'], $credentials), 'latency_ms' => $response['latency_ms']];
        }
        $body = json_decode($response['body'], true);
        $providerMessage = is_array($body) ? $this->safeMessage($body, '') : '';
        if ($response['status'] !== 200) {
            $message = $providerMessage !== ''
                ? $providerMessage
                : 'Provider returned HTTP ' . $response['status'] . '.';
            return ['connected' => false, 'message' => $this->sanitize($message, $credentials), 'latency_ms' => $response['latency_ms']];
        }
        if (!is_array($body)) {
            return ['connected' => false, 'message' => 'Provider returned a non-JSON response; check the base URL.', 'latency_ms' => $response['latency_ms']];
        }
        $status = strtolower((string) ($body['status'] ?? 'success'));
        if (in_array($status, ['error', 'failed', 'unauthorized'], true)) {
            return ['connected' => false, 'message' => $this->sanitize($providerMessage ?: 'Provider rejected the credential.', $credentials), 'latency_ms' => $response['latency_ms']];
        }
        return [
            'connected' => true,
            'message' => 'Authenticated read-only request succeeded (HTTP 200, ' . $response['latency_ms'] . ' ms).',
            'latency_ms' => $response['latency_ms'],
        ];
    }

    public function supportsRefunds(): bool
    {
        return false;
    }

    public function getRefundStatus(array $credentials, string $providerOrderId): array
    {
        throw new ProviderException('FamGateway does not document a refund API. Refunds must be issued manually from the merchant UPI app and recorded by the merchant.', 501, 'refunds_not_supported');
    }

    private function assertCredentials(array $credentials): void
    {
        if (empty($credentials['api_key']) || !is_string($credentials['api_key'])) {
            throw new IntegrationNotConfiguredException();
        }
    }

    private function decode(array $response, string $operation): array
    {
        if ($response['error'] !== null) {
            Logger::warning('Provider network error', ['operation' => $operation, 'error' => $response['error']]);
            throw new ProviderException('Could not reach the payment provider (' . $operation . ').', 502, 'network_error');
        }
        $body = json_decode($response['body'], true);
        if (!is_array($body)) {
            Logger::warning('Provider non-JSON response', ['operation' => $operation, 'status' => $response['status']]);
            throw new ProviderException('Payment provider returned a non-JSON response.', 502, 'bad_response');
        }
        if ($response['status'] >= 400) {
            throw new ProviderException(
                $this->safeMessage($body, 'Payment provider returned HTTP ' . $response['status'] . '.'),
                $response['status'] >= 500 ? 502 : 422,
                'provider_http_error'
            );
        }
        return $body;
    }

    private function safeMessage(array $body, string $fallback): string
    {
        foreach (['message', 'error', 'detail'] as $key) {
            $value = $body[$key] ?? null;
            if (is_string($value) && trim($value) !== '') {
                return mb_substr($value, 0, 300);
            }
            if (is_array($value) && isset($value['message']) && is_string($value['message'])) {
                return mb_substr($value['message'], 0, 300);
            }
        }
        return $fallback;
    }

    private function sanitize(string $message, array $credentials): string
    {
        $key = (string) ($credentials['api_key'] ?? '');
        if ($key !== '') {
            $message = str_replace($key, '[REDACTED]', $message);
        }
        return mb_substr($message, 0, 300);
    }

    private function isProviderUrl(string $url): bool
    {
        $parts = parse_url($url);
        if (!is_array($parts) || strtolower((string) ($parts['scheme'] ?? '')) !== 'https' || empty($parts['host'])) {
            return false;
        }
        $baseHost = strtolower((string) parse_url($this->baseUrl, PHP_URL_HOST));
        $host = strtolower($parts['host']);
        return $host === $baseHost || str_ends_with($host, '.' . $baseHost);
    }

    private function parseIst(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        try {
            $ist = new DateTimeZone('Asia/Kolkata');
            foreach (['d-m-Y H:i:s', 'd-m-Y H:i', 'Y-m-d H:i:s', 'Y-m-d\TH:i:sP', DateTimeImmutable::ATOM] as $format) {
                $date = DateTimeImmutable::createFromFormat($format, $value, $ist);
                if ($date instanceof DateTimeImmutable) {
                    return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
                }
            }
            return (new DateTimeImmutable($value, $ist))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        } catch (Throwable) {
            return null;
        }
    }
}
