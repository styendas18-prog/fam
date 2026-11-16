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

$admin = AdminPage::boot('settings.payment');

if (Request::isPost()) {
    try {
        Csrf::validateOrFail();

        $minAmount = max(1.0, round((float) Request::input('min_payment_amount', 1), 2));
        $maxAmount = max($minAmount, round((float) Request::input('max_payment_amount', 100000), 2));
        $expirySeconds = max(60, min(3600, Request::int('payment_expiry_seconds', 300)));
        $linkExpiryDays = max(1, min(365, Request::int('default_link_expiry_days', 30)));
        $pollInterval = max(2, min(15, Request::int('provider_poll_interval_seconds', 4)));
        $allowPlatformCreds = Request::str('allow_platform_default_credentials') === '1' ? '1' : '0';
        $invoicePrefix = strtoupper(trim(preg_replace('/[^A-Za-z0-9\-]/', '', Request::str('invoice_prefix', 'MF'))));

        Settings::set('min_payment_amount', number_format($minAmount, 2, '.', ''));
        Settings::set('max_payment_amount', number_format($maxAmount, 2, '.', ''));
        Settings::set('payment_expiry_seconds', (string) $expirySeconds);
        Settings::set('default_link_expiry_days', (string) $linkExpiryDays);
        Settings::set('provider_poll_interval_seconds', (string) $pollInterval);
        Settings::set('allow_platform_default_credentials', $allowPlatformCreds);
        Settings::set('invoice_prefix', $invoicePrefix ?: 'MF');

        Audit::admin((int) $admin['id'], 'payment_settings_updated', 'system_settings', null, [
            'min' => $minAmount,
            'max' => $maxAmount,
            'expiry' => $expirySeconds,
            'allow_platform' => $allowPlatformCreds,
        ]);

        Flash::success('Payment gateway configuration saved.');
    } catch (HttpException $e) {
        Flash::error($e->getMessage());
    } catch (\Throwable $e) {
        Flash::error('Failed to save settings: ' . $e->getMessage());
    }
    Response::redirect(url('/admin/payment-settings.php'));
}

$settings = [
    'min_payment_amount' => Settings::get('min_payment_amount', '1.00'),
    'max_payment_amount' => Settings::get('max_payment_amount', '100000.00'),
    'payment_expiry_seconds' => Settings::get('payment_expiry_seconds', '300'),
    'default_link_expiry_days' => Settings::get('default_link_expiry_days', '30'),
    'provider_poll_interval_seconds' => Settings::get('provider_poll_interval_seconds', '4'),
    'allow_platform_default_credentials' => Settings::get('allow_platform_default_credentials', '0'),
    'invoice_prefix' => Settings::get('invoice_prefix', 'MF'),
];

View::render('admin.payment-settings', [
    'title' => 'Payment settings',
    'active' => 'payment-settings',
    'pageTitle' => 'Payment rules & limits',
    'pageSubtitle' => 'Configure transaction limits, session timeouts & platform fallback policy',
    'settings' => $settings,
], 'admin');
