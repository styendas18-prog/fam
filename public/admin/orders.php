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
use App\Models\PaymentOrderModel;
use App\Services\VerificationService;

$admin = AdminPage::boot('orders.view');

if (Request::isPost()) {
    try {
        AdminPage::boot('orders.manage');
        Csrf::validateOrFail();

        $action = Request::str('action');
        $id = Request::int('id');
        $order = PaymentOrderModel::find($id);
        if (!$order) {
            throw new HttpException(404, 'Payment order not found.');
        }

        if ($action === 'verify') {
            $updated = VerificationService::verify($order, 'admin_manual', true);
            Audit::admin((int) $admin['id'], 'admin_order_rechecked', 'payment_order', $id, [
                'order_id' => $order['public_id'],
                'new_status' => $updated['status'],
            ]);
            Flash::success("Order {$order['public_id']} rechecked. Current status: {$updated['status']}");
        } elseif ($action === 'expire') {
            if ($order['status'] === 'pending') {
                VerificationService::markExpired($order);
                Audit::admin((int) $admin['id'], 'admin_order_expired', 'payment_order', $id, [
                    'order_id' => $order['public_id'],
                ]);
                Flash::success("Order {$order['public_id']} marked as expired.");
            } else {
                Flash::error('Only pending orders can be marked expired.');
            }
        }
    } catch (HttpException $e) {
        Flash::error($e->getMessage());
    } catch (\Throwable $e) {
        Flash::error('Order operation failed: ' . $e->getMessage());
    }
    Response::redirect(url('/admin/orders.php'));
}

$filters = [
    'q' => Request::str('q'),
    'status' => Request::str('status'),
    'merchant_id' => Request::int('merchant_id') ?: null,
    'from' => Request::str('from'),
    'to' => Request::str('to'),
];

$page = AdminPage::page();
$result = PaymentOrderModel::listAll($filters, $page, 25);
$merchants = MerchantModel::all();

View::render('admin.orders', [
    'title' => 'Payment orders',
    'active' => 'orders',
    'pageTitle' => 'Payment orders',
    'pageSubtitle' => 'All local payment sessions and third-party provider orders',
    'result' => $result,
    'filters' => $filters,
    'merchants' => $merchants,
], 'admin');
