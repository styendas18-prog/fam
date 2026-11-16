<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/bootstrap.php';

use App\Core\Audit;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Http\UserPage;
use App\Models\PaymentOrderModel;
use App\Models\TransactionModel;
use App\Services\VerificationService;

[$user, $merchant] = UserPage::boot();
$merchantId = (int) $merchant['id'];
$filters = [
    'status' => Request::str('status'),
    'q' => Request::str('q'),
    'from' => Request::str('from'),
    'to' => Request::str('to'),
    'min_amount' => Request::str('min_amount'),
    'max_amount' => Request::str('max_amount'),
];

if (Request::str('export') === 'csv') {
    $rows = PaymentOrderModel::exportForMerchant($merchantId, $filters);
    $safe = static function (mixed $value): string {
        $text = (string) $value;
        return preg_match('/^[=\-+@]/', $text) ? "'" . $text : $text;
    };
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="maxxfam-transactions-' . gmdate('Ymd-His') . '.csv"');
    header('Cache-Control: no-store');
    $out = fopen('php://output', 'wb');
    fputcsv($out, ['Order', 'Provider order', 'Customer', 'Email', 'Reference', 'Description', 'Amount', 'Payable', 'Currency', 'Status', 'Failure code', 'Provider', 'Created (UTC)', 'Paid (UTC)']);
    foreach ($rows as $row) {
        fputcsv($out, array_map($safe, [
            $row['public_id'], $row['provider_order_id'], $row['customer_name'], $row['customer_email'],
            $row['customer_reference'], $row['description'], $row['amount'], $row['payable_amount'],
            $row['currency'], $row['status'], $row['failure_code'], $row['provider_name'],
            $row['created_at'], $row['paid_at'],
        ]));
    }
    fclose($out);
    exit;
}

if (Request::isPost()) {
    Csrf::validateOrFail();
    $action = Request::str('action');
    $ref = Request::str('order_id');
    $order = $ref !== '' ? PaymentOrderModel::findByPublicId($ref, $merchantId) : null;
    if (!$order) {
        Flash::error('Payment order not found.');
        Response::redirect(url('/user/transactions.php'));
    }
    try {
        if ($action === 'recheck') {
            if ($order['status'] === 'success') {
                Flash::info('This payment is already verified.');
            } elseif (empty($order['provider_order_id'])) {
                Flash::error('The provider never created this order, so there is nothing to verify.');
            } else {
                $fresh = PaymentOrderModel::find((int) $order['id']) ?? $order;
                $result = VerificationService::verify($fresh, 'admin_recheck', true);
                Audit::security('payment_manual_recheck', 'info', ['order' => $order['public_id'], 'result' => $result['status']], (int) $user['id'], null);
                if ($result['status'] === 'success') {
                    Flash::success('Provider confirmed payment. The order is now verified.');
                } elseif ($result['status'] === 'pending') {
                    Flash::info('Provider still reports this payment as pending. No success was recorded.');
                } else {
                    Flash::info('Provider status is now ' . $result['status'] . '.');
                }
            }
        } elseif ($action === 'cancel') {
            if (PaymentOrderModel::markCancelled((int) $order['id'])) {
                Audit::security('payment_cancelled', 'info', ['order' => $order['public_id']], (int) $user['id'], null);
                Flash::success('Order cancelled. Only cancel if the customer has not paid.');
            } else {
                Flash::error('Only pending orders can be cancelled.');
            }
        } else {
            throw new HttpException(422, 'Unknown action.');
        }
    } catch (Throwable $e) {
        Flash::error($e->getMessage());
    }
    Response::redirect(url('/user/transactions.php?order=' . urlencode($ref)));
}

$page = max(1, Request::int('page', 1));
$result = PaymentOrderModel::listForMerchant($merchantId, $filters, $page, 20);
$detail = null;
$transaction = null;
$detailRef = Request::str('order');
if ($detailRef !== '') {
    $detail = PaymentOrderModel::findByPublicId($detailRef, $merchantId);
    if ($detail) {
        $transaction = TransactionModel::findByOrder((int) $detail['id']);
    }
}

View::render('user.transactions', [
    'title' => 'Transactions',
    'active' => 'transactions',
    'pageTitle' => 'Transactions',
    'pageSubtitle' => 'Orders and provider-verified payments',
    'merchant' => $merchant,
    'result' => $result,
    'filters' => $filters,
    'detail' => $detail,
    'transaction' => $transaction,
], 'dashboard');
