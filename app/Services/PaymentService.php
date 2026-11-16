<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Audit;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Settings;
use App\Models\PaymentOrderModel;
use App\Models\ProviderModel;
use App\Models\WebhookModel;
use App\Providers\IntegrationNotConfiguredException;
use App\Providers\PaymentProviderInterface;
use App\Providers\ProviderException;
use App\Providers\ProviderManager;
use Throwable;

final class PaymentService
{
    /**
     * Create a real provider order. Nothing is marked successful here.
     * @return array{order:array,provider:array}
     */
    public static function create(array $options): array
    {
        $merchantId = (int) $options['merchant_id'];
        $amount = round((float) $options['amount'], 2);
        $min = (float) Settings::get('min_payment_amount', 1);
        $max = (float) Settings::get('max_payment_amount', 100000);
        if ($amount < $min || $amount > $max) {
            throw new ProviderException('Amount must be between ' . number_format($min, 2) . ' and ' . number_format($max, 2) . ' INR.', 422, 'amount_out_of_range');
        }

        $merchant = Database::fetch('SELECT * FROM merchants WHERE id = ? AND deleted_at IS NULL LIMIT 1', [$merchantId]);
        if (!$merchant || $merchant['status'] !== 'active') {
            throw new ProviderException('Merchant account is not active.', 403, 'merchant_inactive');
        }

        // Enforce the merchant's assigned plan before creating an order.
        PlanService::assertCan($merchantId, 'transactions');

        $providerCode = (string) ($options['provider_code'] ?? 'famgateway');
        $provider = ProviderModel::findByCode($providerCode);
        if (!$provider) {
            throw new IntegrationNotConfiguredException('The requested payment provider is not installed.');
        }
        if ((int) $provider['is_enabled'] !== 1) {
            throw new ProviderException('This payment provider is disabled by the platform administrator.', 409, 'provider_disabled');
        }
        $credentials = ProviderManager::mergedCredentialsFor($provider, $merchantId);
        if (!$credentials) {
            throw new IntegrationNotConfiguredException('Integration Not Configured. Connect your provider API key under Integration before creating payments.');
        }

        $orderId = PaymentOrderModel::create([
            'merchant_id' => $merchantId,
            'provider_id' => (int) $provider['id'],
            'link_id' => $options['link_id'] ?? null,
            'customer_name' => $options['customer_name'] ?? null,
            'customer_email' => $options['customer_email'] ?? null,
            'customer_phone' => $options['customer_phone'] ?? null,
            'customer_reference' => $options['customer_reference'] ?? null,
            'description' => $options['description'] ?? null,
            'amount' => $amount,
            'currency' => 'INR',
            'success_redirect_url' => $options['success_redirect_url'] ?? null,
            'webhook_url' => $options['webhook_url'] ?? null,
            'expires_at' => gmdate('Y-m-d H:i:s', time() + max(120, Settings::int('payment_expiry_seconds', 300))),
        ]);
        $order = PaymentOrderModel::find($orderId);
        if (!$order) {
            throw new ProviderException('Unable to create the local payment order.', 500, 'order_persist_failed');
        }

        $adapter = ProviderManager::resolveRow($provider);
        try {
            $providerOrder = $adapter->createPayment($credentials, [
                'amount' => $amount,
                'customer_name' => $options['customer_name'] ?? null,
                'customer_email' => $options['customer_email'] ?? null,
                'customer_phone' => $options['customer_phone'] ?? null,
                'redirect_url' => url('/checkout.php?token=' . $order['checkout_token']),
                'webhook_url' => url('/api/webhooks/famgateway.php'),
            ]);
            self::validateProviderOrder($providerOrder, $amount);
            PaymentOrderModel::markProviderCreated($orderId, $providerOrder);
            ProviderModel::markSuccess((int) $provider['id']);
            $fresh = PaymentOrderModel::find($orderId);
            if (!$fresh) {
                throw new ProviderException('Provider order created but the local order could not be reloaded.', 500, 'order_reload_failed');
            }

            $eventData = self::webhookData($fresh);
            WebhookModel::enqueue($merchantId, 'payment.created', $eventData, $orderId);
            WebhookModel::enqueue($merchantId, 'payment.pending', $eventData, $orderId);
            Audit::security('payment_order_created', 'info', [
                'order_id' => $fresh['public_id'],
                'provider' => $providerCode,
                'source' => $options['source'] ?? 'dashboard',
            ], (int) $merchant['user_id'], null);

            Logger::info('Provider order created', [
                'order' => $fresh['public_id'],
                'provider_order' => $providerOrder['provider_order_id'],
                'amount' => $amount,
                'payable' => $providerOrder['payable_amount'],
            ]);
            return ['order' => $fresh, 'provider' => $providerOrder];
        } catch (ProviderException $e) {
            PaymentOrderModel::markFailed($orderId, $e->providerCode ?? 'provider_error', $e->getMessage());
            Database::execute('UPDATE payment_providers SET last_error = ? WHERE id = ?', [mb_substr($e->getMessage(), 0, 500), $provider['id']]);
            Audit::security('payment_order_failed', 'warning', [
                'order_id' => $order['public_id'],
                'reason' => $e->providerCode,
                'message' => $e->getMessage(),
            ], (int) $merchant['user_id'], null);
            WebhookModel::enqueue($merchantId, 'payment.failed', [
                'order_id' => $order['public_id'],
                'status' => 'failed',
                'amount' => $amount,
                'failure_code' => $e->providerCode,
            ], $orderId);
            throw $e;
        } catch (Throwable $e) {
            PaymentOrderModel::markFailed($orderId, 'internal_error', 'Order creation failed unexpectedly.');
            Logger::error('Payment creation exception', ['order' => $order['public_id'], 'error' => $e->getMessage()]);
            throw new ProviderException('Payment order could not be created. No charge was made.', 500, 'internal_error', $e);
        }
    }

    private static function validateProviderOrder(array $provider, float $requested): void
    {
        $payable = (float) ($provider['payable_amount'] ?? 0);
        if ($payable <= 0) {
            throw new ProviderException('Provider returned an invalid payable amount.', 502, 'invalid_payable_amount');
        }
        if ($payable + 0.0001 < $requested) {
            throw new ProviderException('Provider returned a payable amount lower than requested.', 502, 'payable_below_requested');
        }
        if (($payable - $requested) > 0.05) {
            throw new ProviderException('Provider increased the payable amount beyond the accepted tolerance. Order aborted.', 502, 'payable_mismatch');
        }
        $orderId = (string) ($provider['provider_order_id'] ?? '');
        if ($orderId === '') {
            throw new ProviderException('Provider did not return an order ID.', 502, 'missing_order_id');
        }
        if (empty($provider['qr_url'])) {
            throw new ProviderException('Provider did not return a QR code URL.', 502, 'missing_qr');
        }
        if (empty($provider['expires_at']) || strtotime((string) $provider['expires_at'] . ' UTC') < time() - 5) {
            throw new ProviderException('Provider returned an already-expired payment session.', 502, 'invalid_expiry');
        }
    }

    public static function webhookData(array $order): array
    {
        return [
            'order_id' => $order['public_id'],
            'merchant_reference' => $order['customer_reference'],
            'amount' => number_format((float) ($order['payable_amount'] ?? $order['amount']), 2, '.', ''),
            'currency' => $order['currency'] ?? 'INR',
            'status' => $order['status'],
            'customer_name' => $order['customer_name'],
            'description' => $order['description'],
            'created_at' => isset($order['created_at']) ? gmdate('c', strtotime($order['created_at'] . ' UTC')) : gmdate('c'),
        ];
    }
}
