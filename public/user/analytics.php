<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/bootstrap.php';

use App\Core\Request;
use App\Core\View;
use App\Http\UserPage;
use App\Models\AnalyticsModel;
use App\Models\MerchantModel;
use App\Models\TransactionModel;

[$user, $merchant] = UserPage::boot();
$merchantId = (int) $merchant['id'];
$days = (int) Request::str('days', '30');
if (!in_array($days, [7, 30, 90], true)) {
    $days = 30;
}

if (Request::str('export') === 'csv') {
    $rows = AnalyticsModel::reportRows(['merchant_id' => $merchantId, 'from' => Request::str('from'), 'to' => Request::str('to')]);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="maxxfam-verified-' . gmdate('Ymd-His') . '.csv"');
    header('Cache-Control: no-store');
    $out = fopen('php://output', 'wb');
    fputcsv($out, ['Order', 'UTR', 'Amount', 'Currency', 'Customer', 'Email', 'Provider', 'Verified at (UTC)', 'Source']);
    $safe = static fn (mixed $v): string => preg_match('/^[=\-+@]/', (string) $v) ? "'" . $v : (string) $v;
    foreach ($rows as $row) {
        fputcsv($out, array_map($safe, [
            $row['public_id'], $row['utr'], $row['amount'], $row['currency'], $row['customer_name'],
            $row['customer_email'], $row['provider_name'], $row['verified_at'], $row['verification_source'],
        ]));
    }
    fclose($out);
    exit;
}

$series = AnalyticsModel::merchantSeries($merchantId, $days);
$statuses = AnalyticsModel::statusBreakdown($merchantId);
$providers = AnalyticsModel::providerPerformance($merchantId);
$apiUsage = AnalyticsModel::apiUsage($merchantId, min(30, $days));
$stats = MerchantModel::stats($merchantId);
$verified = TransactionModel::listForMerchant($merchantId, [], 1, 1);
$avgOrder = (int) $stats['verified_count'] > 0 ? $stats['success_volume'] / (int) $stats['verified_count'] : 0.0;

View::render('user.analytics', [
    'title' => 'Analytics',
    'active' => 'analytics',
    'pageTitle' => 'Analytics',
    'pageSubtitle' => 'Verified revenue and operational performance',
    'merchant' => $merchant,
    'days' => $days,
    'series' => $series,
    'statuses' => $statuses,
    'providers' => $providers,
    'apiUsage' => $apiUsage,
    'stats' => $stats,
    'verifiedTotal' => (int) ($verified['total'] ?? 0),
    'avgOrder' => $avgOrder,
    'useCharts' => true,
], 'dashboard');
