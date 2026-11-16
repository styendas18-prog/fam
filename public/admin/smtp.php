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
use App\Services\Mailer;

$admin = AdminPage::boot('settings.smtp');

if (Request::isPost()) {
    try {
        Csrf::validateOrFail();

        $action = Request::str('action');

        if ($action === 'save') {
            $host = trim(Request::str('smtp_host'));
            $port = (string) max(1, min(65535, Request::int('smtp_port', 587)));
            $encryption = strtolower(Request::str('smtp_encryption', 'tls'));
            $username = trim(Request::str('smtp_username'));
            $password = (string) Request::input('smtp_password', '');
            $fromAddress = strtolower(trim(Request::str('mail_from_address')));
            $fromName = trim(Request::str('mail_from_name', 'MAXXfam'));

            Settings::set('smtp_host', $host);
            Settings::set('smtp_port', $port);
            Settings::set('smtp_encryption', $encryption);
            Settings::set('smtp_username', $username);
            if ($password !== '') {
                Settings::set('smtp_password', $password, true);
            }
            Settings::set('mail_from_address', $fromAddress);
            Settings::set('mail_from_name', $fromName);

            Audit::admin((int) $admin['id'], 'smtp_settings_updated', 'system_settings', null, [
                'host' => $host,
                'port' => $port,
                'from' => $fromAddress,
            ]);

            Flash::success('SMTP configuration saved.');
        } elseif ($action === 'test') {
            $testTo = strtolower(trim(Request::str('test_email', (string) $admin['email'])));
            if (!filter_var($testTo, FILTER_VALIDATE_EMAIL)) {
                throw new HttpException(422, 'Enter a valid test email address.');
            }

            $res = Mailer::test($testTo);
            Settings::set('smtp_last_test_at', gmdate('Y-m-d H:i:s'));
            Settings::set('smtp_last_test_result', $res['message']);

            Audit::admin((int) $admin['id'], 'smtp_connection_tested', 'smtp', null, $res);

            if ($res['success']) {
                Flash::success('Test email delivered successfully to ' . $testTo);
            } else {
                Flash::error('SMTP connection failed: ' . $res['message']);
            }
        }
    } catch (HttpException $e) {
        Flash::error($e->getMessage());
    } catch (\Throwable $e) {
        Flash::error('SMTP operation failed: ' . $e->getMessage());
    }
    Response::redirect(url('/admin/smtp.php'));
}

$settings = [
    'smtp_host' => Settings::get('smtp_host', ''),
    'smtp_port' => Settings::get('smtp_port', '587'),
    'smtp_encryption' => Settings::get('smtp_encryption', 'tls'),
    'smtp_username' => Settings::get('smtp_username', ''),
    'mail_from_address' => Settings::get('mail_from_address', ''),
    'mail_from_name' => Settings::get('mail_from_name', 'MAXXfam'),
    'smtp_last_test_at' => Settings::get('smtp_last_test_at', ''),
    'smtp_last_test_result' => Settings::get('smtp_last_test_result', ''),
];

View::render('admin.smtp', [
    'title' => 'SMTP settings',
    'active' => 'smtp',
    'pageTitle' => 'SMTP & email delivery',
    'pageSubtitle' => 'Configure transactional email notifications, login alerts & payment receipts',
    'settings' => $settings,
    'adminEmail' => $admin['email'],
], 'admin');
