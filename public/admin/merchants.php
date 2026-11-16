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

$admin = AdminPage::boot('merchants.view');

if (Request::isPost()) {
    AdminPage::boot('users.manage');
    try {
        Csrf::validateOrFail();
        $id = Request::int('id');
        $merchant = MerchantModel::adminDetail($id);
        if (!$merchant) {
            throw new HttpException(404, 'Merchant not found.');
        }
        $action = Request::str('action');
        if ($action === 'status') {
            $status = Request::str('status');
            if (!MerchantModel::setStatus($id, $status)) {
                throw new HttpException(422, 'Select a valid merchant status.');
            }
            Audit::admin((int) $admin['id'], 'merchant_status_changed', 'merchant', $id, ['status' => $status, 'merchant' => $merchant['merchant_code']]);
            Flash::success('Merchant status changed to ' . $status . '.');
        } elseif ($action === 'kyc') {
            $status = Request::str('kyc_status');
            if (!in_array($status, ['not_submitted', 'pending', 'verified', 'rejected'], true)) {
                throw new HttpException(422, 'Select a valid KYC status.');
            }
            MerchantModel::setKyc($id, $status, Request::str('kyc_notes'));
            Audit::admin((int) $admin['id'], 'merchant_kyc_' . $status, 'merchant', $id, ['notes' => Request::str('kyc_notes')]);
            Audit::security('merchant_kyc_reviewed', 'warning', ['merchant' => $merchant['merchant_code'], 'status' => $status], null, (int) $admin['id']);
            Flash::success('KYC status updated to ' . str_replace('_', ' ', $status) . '.');
        } else {
            throw new HttpException(422, 'Unknown action.');
        }
    } catch (HttpException $e) {
        Flash::error($e->getMessage());
    } catch (Throwable $e) {
        Flash::error('The merchant operation could not be completed.');
    }
    Response::redirect(url('/admin/merchants.php' . (Request::int('id') > 0 ? '?id=' . Request::int('id') : '')));
}

$filters = ['q' => Request::str('q'), 'status' => Request::str('status'), 'kyc' => Request::str('kyc')];
$result = MerchantModel::adminList($filters, max(1, Request::int('page', 1)), 25);
$detail = Request::int('id') > 0 ? MerchantModel::adminDetail(Request::int('id')) : null;
$stats = $detail ? MerchantModel::stats((int) $detail['id']) : null;
$providerState = $detail ? Database::fetchAll(
    'SELECT p.name, p.code, pc.key_last4, pc.connection_status, pc.last_test_at, pc.last_test_message
     FROM payment_providers p LEFT JOIN provider_credentials pc ON pc.provider_id = p.id AND pc.merchant_id = ? ORDER BY p.name',
    [$detail['id']]
) : [];

View::render('admin.merchants', [
    'title' => 'Merchants',
    'active' => 'merchants',
    'pageTitle' => 'Merchants',
    'pageSubtitle' => 'KYC, status and provider readiness',
    'result' => $result,
    'filters' => $filters,
    'detail' => $detail,
    'stats' => $stats,
    'providerState' => $providerState,
], 'admin');
