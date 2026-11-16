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
use App\Models\ApiKeyModel;

$admin = AdminPage::boot('api.view');

if (Request::isPost()) {
    try {
        AdminPage::boot('api.manage');
        Csrf::validateOrFail();

        $action = Request::str('action');
        $id = Request::int('id');

        if ($action === 'revoke') {
            $key = Database::fetch('SELECT k.*, m.business_name FROM api_keys k JOIN merchants m ON m.id = k.merchant_id WHERE k.id = ? LIMIT 1', [$id]);
            if (!$key) {
                throw new HttpException(404, 'API key not found.');
            }
            Database::execute('UPDATE api_keys SET status = "revoked", revoked_at = UTC_TIMESTAMP() WHERE id = ?', [$id]);
            Audit::admin((int) $admin['id'], 'admin_api_key_revoked', 'api_key', $id, [
                'merchant_id' => $key['merchant_id'],
                'key_name' => $key['name'],
                'prefix' => $key['key_prefix'],
            ]);
            Flash::success("API key '{$key['name']}' has been revoked.");
        }
    } catch (HttpException $e) {
        Flash::error($e->getMessage());
    } catch (\Throwable $e) {
        Flash::error('API key action failed: ' . $e->getMessage());
    }
    Response::redirect(url('/admin/api-management.php'));
}

$filters = [
    'q' => Request::str('q'),
    'status' => Request::str('status'),
];

$page = AdminPage::page();
$result = ApiKeyModel::listAll($filters, $page, 25);

View::render('admin.api-management', [
    'title' => 'API management',
    'active' => 'api',
    'pageTitle' => 'Developer API keys',
    'pageSubtitle' => 'Merchant REST API keys, authentication scopes and usage status',
    'result' => $result,
    'filters' => $filters,
], 'admin');
