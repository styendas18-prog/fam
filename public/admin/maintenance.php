<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/bootstrap.php';

use App\Core\Audit;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Settings;
use App\Core\View;
use App\Http\AdminPage;

$admin = AdminPage::boot('maintenance.manage');

if (Request::isPost()) {
    try {
        Csrf::validateOrFail();

        $mode = Request::str('maintenance_mode') === '1' ? '1' : '0';
        $message = trim(Request::str('maintenance_message', 'MAXXfam is undergoing scheduled maintenance. Please try again shortly.'));

        Settings::set('maintenance_mode', $mode);
        Settings::set('maintenance_message', $message);

        Audit::admin((int) $admin['id'], 'maintenance_mode_toggled', 'system', null, [
            'maintenance_mode' => $mode,
            'message' => $message,
        ]);

        Flash::success('Maintenance mode ' . ($mode === '1' ? 'ENABLED' : 'DISABLED') . '.');
    } catch (HttpException $e) {
        Flash::error($e->getMessage());
    } catch (\Throwable $e) {
        Flash::error('Maintenance operation failed: ' . $e->getMessage());
    }
    Response::redirect(url('/admin/maintenance.php'));
}

$settings = [
    'maintenance_mode' => Settings::get('maintenance_mode', '0'),
    'maintenance_message' => Settings::get('maintenance_message', 'MAXXfam is undergoing scheduled maintenance. Please try again shortly.'),
];

View::render('admin.maintenance', [
    'title' => 'Maintenance mode',
    'active' => 'maintenance',
    'pageTitle' => 'System maintenance mode',
    'pageSubtitle' => 'Temporarily suspend public storefront & merchant transactions during platform upgrades',
    'settings' => $settings,
], 'admin');
