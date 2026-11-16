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
use App\Models\ProviderModel;
use App\Providers\ProviderManager;

$admin = AdminPage::boot('providers.view');

if (Request::isPost()) {
    try {
        AdminPage::boot('providers.test');
        Csrf::validateOrFail();

        $action = Request::str('action');
        $id = Request::int('id');
        $provider = ProviderModel::find($id);
        if (!$provider) {
            throw new HttpException(404, 'Payment provider not found.');
        }

        if ($action === 'toggle_enable') {
            $newStatus = (int) $provider['is_enabled'] === 1 ? 0 : 1;
            Database::execute('UPDATE payment_providers SET is_enabled = ? WHERE id = ?', [$newStatus, $id]);
            Audit::admin((int) $admin['id'], 'provider_toggle_enabled', 'payment_provider', $id, [
                'provider' => $provider['code'],
                'enabled' => $newStatus,
            ]);
            Flash::success($provider['name'] . ($newStatus ? ' enabled.' : ' disabled.'));
        } elseif ($action === 'save_credentials') {
            $apiKey = Request::str('api_key');
            if ($apiKey === '' || mb_strlen($apiKey) < 8) {
                throw new HttpException(422, 'Provide a valid provider API key.');
            }
            ProviderModel::saveCredential($id, null, $apiKey, (int) $admin['id']);
            Audit::admin((int) $admin['id'], 'platform_provider_credential_saved', 'payment_provider', $id, [
                'provider' => $provider['code'],
            ]);
            Flash::success('Platform credentials updated. Test connection to verify authorization.');
        } elseif ($action === 'delete_credentials') {
            ProviderModel::deleteCredential($id, null);
            ProviderModel::setConnectionStatus($id, null, 'not_configured', 'Credentials removed.');
            Audit::admin((int) $admin['id'], 'platform_provider_credential_deleted', 'payment_provider', $id, [
                'provider' => $provider['code'],
            ]);
            Flash::success('Platform credentials deleted.');
        } elseif ($action === 'test_connection') {
            $cred = ProviderModel::platformCredential($id);
            if (!$cred || empty($cred['credentials']['api_key'])) {
                throw new HttpException(422, 'No platform credentials configured to test. Enter an API key first.');
            }
            $adapter = ProviderManager::resolveRow($provider);
            $test = $adapter->testConnection($cred['credentials']);
            if ($test['connected']) {
                ProviderModel::setConnectionStatus($id, null, 'connected', $test['message'], true);
                Audit::admin((int) $admin['id'], 'provider_connection_test_success', 'payment_provider', $id, $test);
                Flash::success('Connection test succeeded: ' . $test['message']);
            } else {
                ProviderModel::setConnectionStatus($id, null, 'connection_failed', $test['message'], false);
                Audit::admin((int) $admin['id'], 'provider_connection_test_failed', 'payment_provider', $id, $test);
                Flash::error('Connection test failed: ' . $test['message']);
            }
        }
    } catch (HttpException $e) {
        Flash::error($e->getMessage());
    } catch (\Throwable $e) {
        Flash::error('Provider operation failed: ' . $e->getMessage());
    }
    Response::redirect(url('/admin/providers.php'));
}

$providers = ProviderModel::all();
$platformCredentials = [];
foreach ($providers as $p) {
    $platformCredentials[$p['id']] = ProviderModel::platformCredential((int) $p['id']);
}

View::render('admin.providers', [
    'title' => 'Payment providers',
    'active' => 'providers',
    'pageTitle' => 'Payment providers',
    'pageSubtitle' => 'Configure modular UPI payment gateways, test credentials & manage platform fallback',
    'providers' => $providers,
    'platformCredentials' => $platformCredentials,
], 'admin');
