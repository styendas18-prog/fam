<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Audit;
use App\Core\Database;
use App\Core\Logger;
use App\Models\InvoiceModel;
use App\Models\NotificationModel;
use App\Models\PaymentOrderModel;
use App\Models\ProviderModel;
use App\Models\TransactionModel;
use App\Models\WebhookModel;
use App\Providers\IntegrationNotConfiguredException;
use App\Providers\ProviderException;
use App\Providers\ProviderManager;
use App\Providers\VerificationException;
use PDOException;
use Throwable;

final class VerificationService
{
    /**
     * Ask the provider for the authoritative status and reconcile.
     * A payment can only become SUCCESS inside this method after:
     *  - the provider returns status=success
     *  - order id, amount, currency and UTR are validated
     *  - the UTR is unique across every verified transaction
     *  - a row lock serialises concurrent callbacks
     */
    public static function verify(array $order, string $source = 'poll', bool $force = false): array
    {
        if ($order['status'] === 'success') {
            return $order;
        }
        if (!$force && !empty($order['last_checked_at'])) {
            $age = time() - strtotime((string) $order['last_checked_at'] . ' UTC');
            if ($age < 3) {
                return $order;
            }
        }
        $provider = ProviderModel::find((int) $order['provider_id']);
        if (!$provider) {
            return $order;
        }
        try {
            $credentials = ProviderManager::mergedCredentialsFor($provider, (int) $order['merchant_id']);
            if (!$credentials) {
                Logger::warning('Verification skipped: integration not configured', ['order' => $order['public_id']]);
                return $order;
            }
            $adapter = ProviderManager::resolveRow($provider);
            $status = $adapter->getPaymentStatus($credentials, (string) $order['provider_order_id']);
            PaymentOrderModel::touchChecked((int) $order['id']);
            Logger::info('Provider status checked', [
                'order' => $order['public_id'],
                'source' => $source,
                'provider_status' => $status['status'],
            ]);
            if ($status['status'] === 'success') {
                return self::processVerified($order, $status, $source);
            }
            if ($status['status'] === 'expired') {
                return self::markExpired($order);
            }
            if ($status['status'] === 'failed') {
                return self::markFailed($order, 'provider_failed', 'The provider reported the payment as failed.');
            }
            return PaymentOrderModel::find((int) $order['id']) ?? $order;
        } catch (IntegrationNotConfiguredException $e) {
            Logger::warning('Verification credential missing', ['order' => $order['public_id']]);
            return $order;
        } catch (ProviderException $e) {
            Logger::warning('Provider verification error', [
                'order' => $order['public_id'],
                'code' => $e->providerCode,
                'message' => $e->getMessage(),
            ]);
            return $order;
        } catch (Throwable $e) {
            Logger::error('Verification exception', ['order' => $order['public_id'], 'error' => $e->getMessage()]);
            return $order;
        }
    }

    /**
     * Verify a payment from a trusted FamPay email alert.
     *
     * Email alerts have no provider order id, so that single assertion is
     * skipped. Every other guarantee still applies: exact amount, 12-digit
     * UTR, unique UTR/provider reference, and row-locked idempotency.
     */
    public static function processEmailVerified(array $order, array $status, string $source = 'email_imap'): array
    {
        return self::processVerified($order, $status, $source, false);
    }

    public static function processVerified(array $order, array $status, string $source, bool $requireProviderOrderId = true): array
    {
        $providerAmount = $status['amount'];
        $expected = (float) ($order['payable_amount'] ?? $order['amount']);
        if ($providerAmount === null || bccomp(number_format((float) $providerAmount, 2, '.', ''), number_format($expected, 2, '.', ''), 2) !== 0) {
            self::flagMismatch($order, 'amount_mismatch', 'Provider-confirmed amount does not match the payable amount.', [
                'provider_amount' => $providerAmount,
                'expected' => $expected,
            ]);
            throw new VerificationException('amount_mismatch', 'Amount mismatch — payment requires manual review.');
        }
        $utr = (string) ($status['utr'] ?? '');
        if (!preg_match('/^\d{12}$/', $utr)) {
            self::flagMismatch($order, 'utr_invalid', 'Provider confirmation did not include a valid 12-digit UTR.');
            throw new VerificationException('utr_invalid', 'UTR validation failed.');
        }
        if ($requireProviderOrderId && $status['provider_order_id'] !== $order['provider_order_id']) {
            self::flagMismatch($order, 'order_mismatch', 'Provider order ID does not match the stored order.');
            throw new VerificationException('order_mismatch', 'Order identifier mismatch.');
        }
        $existing = TransactionModel::findByOrder((int) $order['id']);
        if ($existing) {
            return PaymentOrderModel::find((int) $order['id']) ?? $order;
        }

        try {
            $result = Database::deadlockRetry(static function () use ($order, $status, $source, $utr, $requireProviderOrderId): array {
            $locked = Database::fetch(
                'SELECT o.*, m.user_id, m.business_name, m.merchant_code
                 FROM payment_orders o JOIN merchants m ON m.id = o.merchant_id
                 WHERE o.id = ? FOR UPDATE',
                [$order['id']]
            );
            if (!$locked) {
                throw new VerificationException('order_missing', 'Local order no longer exists.');
            }
            if ($locked['status'] === 'success') {
                return ['order' => $locked, 'already' => true, 'transaction_id' => null];
            }
            if ($requireProviderOrderId && $locked['provider_order_id'] !== $order['provider_order_id']) {
                throw new VerificationException('order_mismatch', 'Provider order ID mismatch under lock.');
            }
            $duplicate = Database::fetch('SELECT id, order_id FROM transactions WHERE utr = ? LIMIT 1', [$utr]);
            if ($duplicate && (int) $duplicate['order_id'] !== (int) $locked['id']) {
                Audit::security('duplicate_utr_blocked', 'critical', [
                    'utr' => $utr,
                    'incoming_order' => $locked['public_id'],
                    'existing_order_id' => $duplicate['order_id'],
                ], (int) $locked['user_id'], null);
                throw new VerificationException('duplicate_utr', 'This UTR has already been verified for another order.');
            }
            $providerTxnId = (string) ($status['transaction_id'] ?? '');
            if ($providerTxnId !== '') {
                $duplicateTxn = Database::fetch(
                    'SELECT id, order_id FROM transactions WHERE provider_id = ? AND provider_transaction_id = ? LIMIT 1',
                    [(int) $locked['provider_id'], $providerTxnId]
                );
                if ($duplicateTxn && (int) $duplicateTxn['order_id'] !== (int) $locked['id']) {
                    Audit::security('duplicate_provider_transaction_blocked', 'critical', [
                        'provider_transaction_id' => $providerTxnId,
                        'incoming_order' => $locked['public_id'],
                        'existing_order_id' => $duplicateTxn['order_id'],
                    ], (int) $locked['user_id'], null);
                    throw new VerificationException('duplicate_transaction', 'This provider transaction reference has already been verified for another order.');
                }
            }

            $verifiedAt = $status['paid_at'] ?: gmdate('Y-m-d H:i:s');
            $transactionId = Database::insert(
                'INSERT INTO transactions
                 (order_id, merchant_id, provider_id, provider_transaction_id, utr, amount, currency, sender_name, status, verified_at, verification_source, provider_payload, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, "INR", ?, "success", ?, ?, ?, UTC_TIMESTAMP())',
                [
                    $locked['id'],
                    $locked['merchant_id'],
                    $locked['provider_id'],
                    $status['transaction_id'] ?: null,
                    $utr,
                    number_format((float) $status['amount'], 2, '.', ''),
                    $status['sender_name'] ?? null,
                    $verifiedAt,
                    $source,
                    json_encode(Logger::redact($status['raw']), JSON_UNESCAPED_SLASHES),
                ]
            );
            Database::execute(
                'UPDATE payment_orders SET status = "success", paid_at = ?, failure_code = NULL, failure_message = NULL,
                 payable_amount = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?',
                [$verifiedAt, number_format((float) $status['amount'], 2, '.', ''), $locked['id']]
            );
            $locked['status'] = 'success';
            $locked['paid_at'] = $verifiedAt;
            $locked['payable_amount'] = number_format((float) $status['amount'], 2, '.', '');

            $invoicePath = InvoiceService::issue($locked, [
                'id' => $transactionId,
                'utr' => $utr,
                'sender_name' => $status['sender_name'] ?? null,
                'verified_at' => $verifiedAt,
                'provider_name' => null,
            ]);
            NotificationModel::create(
                (int) $locked['user_id'],
                'payment_success',
                'Payment verified',
                'Payment ' . $locked['public_id'] . ' for ' . money((float) $status['amount']) . ' was verified. UTR ' . $utr . '.',
                '/user/transactions.php?order=' . urlencode($locked['public_id'])
            );
            Mailer::queuePaymentSuccess($locked, $utr, (float) $status['amount'], $invoicePath);

            $eventData = [
                'order_id' => $locked['public_id'],
                'merchant_reference' => $locked['customer_reference'],
                'amount' => number_format((float) $status['amount'], 2, '.', ''),
                'currency' => 'INR',
                'status' => 'success',
                'utr' => $utr,
                'transaction_reference' => $status['transaction_id'],
                'payment_method' => 'upi',
                'verified_at' => gmdate('c', strtotime($verifiedAt . ' UTC')),
            ];
            WebhookModel::enqueue((int) $locked['merchant_id'], 'payment.success', $eventData, (int) $locked['id']);

            return ['order' => $locked, 'already' => false, 'transaction_id' => $transactionId];
            });
        } catch (PDOException $e) {
            if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
                Audit::security('duplicate_payment_reference_blocked', 'critical', [
                    'order' => $order['public_id'],
                    'utr' => $utr,
                    'provider_transaction_id' => $status['transaction_id'] ?? null,
                ], null, null);
                throw new VerificationException('duplicate_transaction', 'This payment reference or UTR has already been verified.');
            }
            throw $e;
        }

        Audit::security('payment_verified', 'info', [
            'order' => $order['public_id'],
            'utr' => $utr,
            'amount' => (float) $status['amount'],
            'source' => $source,
            'idempotent' => $result['already'] ?? false,
        ], null, null);
        Logger::info('Payment verified', [
            'order' => $order['public_id'],
            'utr' => $utr,
            'amount' => (float) $status['amount'],
            'source' => $source,
        ]);
        return PaymentOrderModel::find((int) $order['id']) ?? $order;
    }

    public static function markExpired(array $order): array
    {
        $changed = Database::transaction(static function () use ($order): bool {
            $locked = Database::fetch('SELECT * FROM payment_orders WHERE id = ? FOR UPDATE', [$order['id']]);
            if (!$locked || $locked['status'] !== 'pending') {
                return false;
            }
            Database::execute(
                'UPDATE payment_orders SET status = "expired", failure_code = "expired", failure_message = "Payment session expired without verified payment.", updated_at = UTC_TIMESTAMP() WHERE id = ?',
                [$locked['id']]
            );
            $user = Database::scalar('SELECT user_id FROM merchants WHERE id = ?', [$locked['merchant_id']]);
            if ($user) {
                NotificationModel::create((int) $user, 'payment_expired', 'Payment expired', 'Payment ' . $locked['public_id'] . ' expired without a verified payment.', '/user/transactions.php?status=expired');
            }
            WebhookModel::enqueue((int) $locked['merchant_id'], 'payment.expired', [
                'order_id' => $locked['public_id'],
                'status' => 'expired',
                'amount' => number_format((float) $locked['amount'], 2, '.', ''),
                'expired_at' => gmdate('c'),
            ], (int) $locked['id']);
            return true;
        });
        if ($changed) {
            Logger::info('Order expired', ['order' => $order['public_id']]);
        }
        return PaymentOrderModel::find((int) $order['id']) ?? $order;
    }

    public static function markFailed(array $order, string $code, string $message): array
    {
        $changed = Database::transaction(static function () use ($order, $code, $message): bool {
            $locked = Database::fetch('SELECT * FROM payment_orders WHERE id = ? FOR UPDATE', [$order['id']]);
            if (!$locked || $locked['status'] !== 'pending') {
                return false;
            }
            Database::execute(
                'UPDATE payment_orders SET status = "failed", failure_code = ?, failure_message = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?',
                [$code, mb_substr($message, 0, 500), $locked['id']]
            );
            $user = Database::scalar('SELECT user_id FROM merchants WHERE id = ?', [$locked['merchant_id']]);
            if ($user) {
                NotificationModel::create((int) $user, 'payment_failed', 'Payment failed', 'Payment ' . $locked['public_id'] . ' failed: ' . $message, '/user/transactions.php?status=failed');
            }
            Mailer::queuePaymentFailed($locked, $message);
            WebhookModel::enqueue((int) $locked['merchant_id'], 'payment.failed', [
                'order_id' => $locked['public_id'],
                'status' => 'failed',
                'amount' => number_format((float) $locked['amount'], 2, '.', ''),
                'failure_code' => $code,
                'failed_at' => gmdate('c'),
            ], (int) $locked['id']);
            return true;
        });
        return PaymentOrderModel::find((int) $order['id']) ?? $order;
    }

    private static function flagMismatch(array $order, string $code, string $message, array $details = []): void
    {
        Database::execute(
            'UPDATE payment_orders SET failure_code = ?, failure_message = ?, updated_at = UTC_TIMESTAMP() WHERE id = ? AND status != "success"',
            [$code, $message, $order['id']]
        );
        Audit::security('payment_verification_mismatch', 'critical', [
            'order' => $order['public_id'],
            'code' => $code,
            'message' => $message,
        ] + $details, null, null);
        Logger::error('Payment verification mismatch', ['order' => $order['public_id'], 'code' => $code] + $details);
    }
}
