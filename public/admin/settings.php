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

$admin = AdminPage::boot('settings.system');

if (Request::isPost()) {
    try {
        Csrf::validateOrFail();

        $siteName = trim(Request::str('site_name', 'MAXXfam'));
        $siteTagline = trim(Request::str('site_tagline', 'Smart Payments. Real Verification. Complete Control.'));
        $siteSupportEmail = strtolower(trim(Request::str('site_support_email', 'support@example.com')));
        $registrationEnabled = Request::str('registration_enabled') === '1' ? '1' : '0';
        $termsVersion = trim(Request::str('terms_version', '1.0'));
        $privacyVersion = trim(Request::str('privacy_version', '1.0'));

        if ($siteName === '') {
            throw new HttpException(422, 'Platform name cannot be empty.');
        }
        if (!filter_var($siteSupportEmail, FILTER_VALIDATE_EMAIL)) {
            throw new HttpException(422, 'Enter a valid platform support email.');
        }

        Settings::set('site_name', $siteName);
        Settings::set('site_tagline', $siteTagline);
        Settings::set('site_support_email', $siteSupportEmail);
        Settings::set('registration_enabled', $registrationEnabled);
        Settings::set('terms_version', $termsVersion);
        Settings::set('privacy_version', $privacyVersion);

        Audit::admin((int) $admin['id'], 'system_branding_settings_updated', 'system_settings', null, [
            'site_name' => $siteName,
            'registration_enabled' => $registrationEnabled,
        ]);

        Flash::success('System configuration and branding updated.');
    } catch (HttpException $e) {
        Flash::error($e->getMessage());
    } catch (\Throwable $e) {
        Flash::error('Failed to update system settings: ' . $e->getMessage());
    }
    Response::redirect(url('/admin/settings.php'));
}

$settings = [
    'site_name' => Settings::get('site_name', 'MAXXfam'),
    'site_tagline' => Settings::get('site_tagline', 'Smart Payments. Real Verification. Complete Control.'),
    'site_support_email' => Settings::get('site_support_email', 'support@example.com'),
    'registration_enabled' => Settings::get('registration_enabled', '1'),
    'terms_version' => Settings::get('terms_version', '1.0'),
    'privacy_version' => Settings::get('privacy_version', '1.0'),
];

View::render('admin.settings', [
    'title' => 'System configuration',
    'active' => 'settings',
    'pageTitle' => 'System configuration',
    'pageSubtitle' => 'Platform branding, registration availability & compliance versions',
    'settings' => $settings,
], 'admin');
