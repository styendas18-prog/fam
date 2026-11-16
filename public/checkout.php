<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Csrf;
use App\Core\Database;
use App\Core\Flash;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Models\PaymentOrderModel;
use App\Services\VerificationService;

$token = Request::str('token');
if ($token === '' || !preg_match('/^[A-Za-z0-9_\-]{16,64}$/', $token)) {
    http_response_code(400);
    exit('Invalid or missing checkout token.');
}

$order = PaymentOrderModel::findByToken($token);
if (!$order) {
    http_response_code(404);
    exit('Payment session not found or link has expired.');
}

// Handle POST actions (AJAX status polling or user cancellation)
if (Request::isPost()) {
    try {
        Csrf::validateOrFail();

        $action = Request::str('action', 'status');

        if ($action === 'cancel') {
            if ($order['status'] === 'pending') {
                PaymentOrderModel::markCancelled((int) $order['id']);
                Flash::info('Payment has been cancelled.');
            }
            Response::redirect(url('/checkout.php?token=' . urlencode($token)));
        }

        // Default action: AJAX status poll
        $updatedOrder = $order;
        if ($order['status'] === 'pending') {
            // Check if expired
            if (!empty($order['expires_at']) && strtotime((string) $order['expires_at'] . ' UTC') < time()) {
                $updatedOrder = VerificationService::markExpired($order);
            } else {
                $updatedOrder = VerificationService::verify($order, 'poll');
            }
        }

        // Fetch fresh order
        $fresh = PaymentOrderModel::find((int) $order['id']) ?? $updatedOrder;
        $state = match ($fresh['status']) {
            'success' => 'success',
            'expired' => 'expired',
            'failed' => 'failed',
            'cancelled' => 'cancelled',
            default => 'waiting',
        };

        $receiptUrl = $fresh['status'] === 'success'
            ? url('/receipt.php?token=' . urlencode($fresh['checkout_token']))
            : null;

        Response::json([
            'success' => true,
            'data' => [
                'state' => $state,
                'status' => $fresh['status'],
                'payable_amount' => $fresh['payable_amount'] ?? $fresh['amount'],
                'receipt_url' => $receiptUrl,
                'redirect_url' => $fresh['success_redirect_url'] ?: null,
                'failure_message' => $fresh['failure_message'] ?: null,
            ],
        ]);
    } catch (HttpException $e) {
        Response::json(['success' => false, 'error' => ['message' => $e->getMessage()]], $e->statusCode);
    } catch (Throwable $e) {
        Response::json(['success' => false, 'error' => ['message' => 'Status check encountered a temporary error. Retrying...']], 500);
    }
    exit;
}

// Handle GET: Initial page render
if ($order['status'] === 'pending') {
    if (!empty($order['expires_at']) && strtotime((string) $order['expires_at'] . ' UTC') < time()) {
        $order = VerificationService::markExpired($order);
    }
}

// Check for verified transaction details if order is already success
$transaction = null;
if ($order['status'] === 'success') {
    $transaction = Database::fetch('SELECT utr, verified_at FROM transactions WHERE order_id = ? LIMIT 1', [$order['id']]);
    if ($transaction) {
        $order['utr'] = $transaction['utr'];
        $order['verified_at'] = $transaction['verified_at'];
    }
}

View::render('public.checkout', [
    'title' => 'Pay ' . money((float) ($order['payable_amount'] ?? $order['amount'])),
    'order' => $order,
], 'checkout');
