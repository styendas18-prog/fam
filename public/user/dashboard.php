<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/bootstrap.php';

use App\Core\Settings;
use App\Core\View;
use App\Http\UserPage;
use App\Models\AnalyticsModel;
use App\Models\ApiKeyModel;
use App\Models\MerchantModel;
use App\Models\NotificationModel;
use App\Models\PaymentOrderModel;
use App\Models\ProviderModel;
use App\Models\WebhookModel;

[$user, $merchant] = UserPage::boot();
$merchantId = (int) $merchant['id'];
$stats = MerchantModel::stats($merchantId);
$series = AnalyticsModel::merchantSeries($merchantId, 14);
$statuses = AnalyticsModel::statusBreakdown($merchantId);
$recent = PaymentOrderModel::recent($merchantId, 6);
$providers = ProviderModel::merchantStatus($merchantId);
$keys = ApiKeyModel::listForMerchant($merchantId);
$hooks = WebhookModel::listForMerchant($merchantId);
$notifications = NotificationModel::listForUser((int) $user['id'], 5);
$activeKeys = count(array_filter($keys, static fn (array $k): bool => $k['status'] === 'active'));
$activeHooks = count(array_filter($hooks, static fn (array $h): bool => (int) $h['is_active'] === 1));
$providerConnected = false;
$providerConfigured = false;
foreach ($providers as $provider) {
    if (($provider['merchant_connection_status'] ?? '') === 'connected' || ($provider['connection_status'] ?? '') === 'connected') {
        $providerConnected = true;
    }
    if (!empty($provider['key_last4'])) {
        $providerConfigured = true;
    }
}
$smtpConfigured = trim((string) Settings::get('smtp_host', '')) !== '';
$lastVerified = $stats['verified_count'] > 0;

View::render('user.dashboard', [
    'title' => 'Overview',
    'active' => 'overview',
    'pageTitle' => 'Overview',
    'pageSubtitle' => 'Verified performance for ' . $merchant['business_name'],
    'useCharts' => true,
    'user' => $user,
    'merchant' => $merchant,
    'stats' => $stats,
    'series' => $series,
    'statuses' => $statuses,
    'recent' => $recent,
    'providers' => $providers,
    'notifications' => $notifications,
    'activeKeys' => $activeKeys,
    'activeHooks' => $activeHooks,
    'providerConnected' => $providerConnected,
    'providerConfigured' => $providerConfigured,
    'smtpConfigured' => $smtpConfigured,
    'lastVerified' => $lastVerified,
], 'dashboard');
