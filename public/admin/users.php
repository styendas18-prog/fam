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
use App\Models\UserModel;

$admin = AdminPage::boot('users.view');

if (Request::isPost()) {
    AdminPage::boot('users.manage');
    try {
        Csrf::validateOrFail();
        $id = Request::int('id');
        $user = UserModel::find($id);
        if (!$user) {
            throw new HttpException(404, 'User not found.');
        }
        $action = Request::str('action');
        if ($action === 'status') {
            $status = Request::str('status');
            if (!UserModel::setStatus($id, $status)) {
                throw new HttpException(422, 'Select a valid account status.');
            }
            Audit::admin((int) $admin['id'], 'user_status_changed', 'user', $id, ['status' => $status, 'email' => $user['email']]);
            Audit::security('admin_user_status_changed', 'warning', ['status' => $status], $id, (int) $admin['id']);
            Flash::success('User status changed to ' . $status . '.');
        } elseif ($action === 'delete') {
            if (!UserModel::softDelete($id)) {
                throw new HttpException(422, 'User could not be deleted.');
            }
            Audit::admin((int) $admin['id'], 'user_soft_deleted', 'user', $id, ['email' => $user['email']]);
            Audit::security('admin_user_deleted', 'critical', ['email' => $user['email']], $id, (int) $admin['id']);
            Flash::success('User soft-deleted and all sessions invalidated on next request.');
        } else {
            throw new HttpException(422, 'Unknown action.');
        }
    } catch (HttpException $e) {
        Flash::error($e->getMessage());
    } catch (Throwable $e) {
        Flash::error('The user operation could not be completed.');
    }
    Response::redirect(url('/admin/users.php' . (Request::int('id') > 0 ? '?id=' . Request::int('id') : '')));
}

$filters = ['q' => Request::str('q'), 'status' => Request::str('status')];
$result = UserModel::adminList($filters, max(1, Request::int('page', 1)), 25);
$detail = null;
$summary = null;
if (Request::int('id') > 0) {
    $detail = UserModel::adminDetail(Request::int('id'));
    if ($detail && !empty($detail['merchant_id'])) {
        $summary = Database::fetch(
            'SELECT COUNT(*) AS orders, SUM(status = "success") AS success,
                    COALESCE((SELECT SUM(amount) FROM transactions WHERE merchant_id = ?),0) AS volume
             FROM payment_orders WHERE merchant_id = ?',
            [$detail['merchant_id'], $detail['merchant_id']]
        );
    }
}

View::render('admin.users', [
    'title' => 'Users',
    'active' => 'users',
    'pageTitle' => 'Users',
    'pageSubtitle' => 'Merchant account owners and access state',
    'result' => $result,
    'filters' => $filters,
    'detail' => $detail,
    'summary' => $summary,
], 'admin');
