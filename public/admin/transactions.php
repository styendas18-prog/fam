<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/bootstrap.php';

use App\Core\Audit;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Flash;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Http\AdminPage;
use App\Models\MerchantModel;
use App\Models\TransactionModel;

$admin = AdminPage::boot('transactions.view');

// Handle Refund / Note Update
if (Request::isPost()) {
    try {
        AdminPage::boot('refunds.manage');
        Csrf::validateOrFail();

        $action = Request::str('action');
        $id = Request::int('id');
        $txn = TransactionModel::find($id);
        if (!$txn) {
            throw new HttpException(404, 'Transaction not found.');
        }

        if ($action === 'refund_status') {
            $status = Request::str('refund_status');
            $note = Request::str('refund_note');
            TransactionModel::setRefund($id, $status, $note);

            Audit::admin((int) $admin['id'], 'transaction_refund_status_updated', 'transaction', $id, [
                'refund_status' => $status,
                'refund_note' => $note,
                'utr' => $txn['utr'],
            ]);

            Flash::success('Refund status updated for UTR ' . $txn['utr']);
        }
    } catch (HttpException $e) {
        Flash::error($e->getMessage());
    } catch (\Throwable $e) {
        Flash::error('Failed to update transaction.');
    }
    Response::redirect(url('/admin/transactions.php'));
}

// CSV Export
if (Request::str('export') === 'csv') {
    $filters = [
        'q' => Request::str('q'),
        'merchant_id' => Request::int('merchant_id') ?: null,
        'from' => Request::str('from'),
        'to' => Request::str('to'),
    ];
    $result = TransactionModel::listAll($filters, 1, 10000);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="MAXXfam-Transactions-' . gmdate('Ymd_His') . '.csv"');
    $out = fopen('php://output', 'wb');
    fputcsv($out, ['ID', 'Order ID', 'Merchant', 'Provider', 'Amount', 'Currency', 'UTR', 'Status', 'Refund Status', 'Verified At UTC']);
    foreach ($result['rows'] as $row) {
        fputcsv($out, [
            $row['id'],
            $row['public_id'],
            $row['business_name'],
            $row['provider_name'],
            $row['amount'],
            $row['currency'],
            $row['utr'],
            $row['status'],
            $row['refund_status'],
            $row['verified_at'],
        ]);
    }
    fclose($out);
    exit;
}

$filters = [
    'q' => Request::str('q'),
    'merchant_id' => Request::int('merchant_id') ?: null,
    'from' => Request::str('from'),
    'to' => Request::str('to'),
];

$page = AdminPage::page();
$result = TransactionModel::listAll($filters, $page, 25);
$merchants = MerchantModel::all();

View::render('admin.transactions', [
    'title' => 'Transactions',
    'active' => 'transactions',
    'pageTitle' => 'Verified transactions',
    'pageSubtitle' => 'Authoritative records confirmed by payment provider status & 12-digit bank UTR',
    'result' => $result,
    'filters' => $filters,
    'merchants' => $merchants,
], 'admin');
