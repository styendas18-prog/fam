<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/bootstrap.php';

use App\Core\Database;
use App\Core\Request;
use App\Core\View;
use App\Http\AdminPage;
use App\Models\MerchantModel;
use App\Models\ProviderModel;
use App\Services\PdfService;

$admin = AdminPage::boot('reports.view');

$from = Request::str('from');
$to = Request::str('to');
$merchantId = Request::int('merchant_id') ?: null;
$providerId = Request::int('provider_id') ?: null;

$where = '1=1';
$params = [];

if ($from !== '') {
    $where .= ' AND t.verified_at >= ?';
    $params[] = $from . ' 00:00:00';
}
if ($to !== '') {
    $where .= ' AND t.verified_at <= ?';
    $params[] = $to . ' 23:59:59';
}
if ($merchantId !== null) {
    $where .= ' AND t.merchant_id = ?';
    $params[] = $merchantId;
}
if ($providerId !== null) {
    $where .= ' AND t.provider_id = ?';
    $params[] = $providerId;
}

$summary = Database::fetch(
    "SELECT COUNT(*) AS total_count, COALESCE(SUM(t.amount), 0) AS total_volume,
            COALESCE(AVG(t.amount), 0) AS avg_amount
     FROM transactions t WHERE {$where}",
    $params
) ?: ['total_count' => 0, 'total_volume' => 0, 'avg_amount' => 0];

// Order states overview
$orderSummary = Database::fetch(
    "SELECT COUNT(*) AS total_orders,
            SUM(CASE WHEN o.status = 'success' THEN 1 ELSE 0 END) AS success_orders,
            SUM(CASE WHEN o.status = 'failed' THEN 1 ELSE 0 END) AS failed_orders,
            SUM(CASE WHEN o.status = 'expired' THEN 1 ELSE 0 END) AS expired_orders
     FROM payment_orders o"
) ?: ['total_orders' => 0, 'success_orders' => 0, 'failed_orders' => 0, 'expired_orders' => 0];

$rows = Database::fetchAll(
    "SELECT t.id, o.public_id, t.utr, t.amount, t.currency, t.verified_at, m.business_name, p.name AS provider_name
     FROM transactions t
     JOIN payment_orders o ON o.id = t.order_id
     JOIN merchants m ON m.id = t.merchant_id
     JOIN payment_providers p ON p.id = t.provider_id
     WHERE {$where} ORDER BY t.verified_at DESC LIMIT 500",
    $params
);

// CSV Export
if (Request::str('export') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="MAXXfam-Financial-Report-' . gmdate('Ymd') . '.csv"');
    $out = fopen('php://output', 'wb');
    $safe = static fn (mixed $v): string => preg_match('/^[=\-+@]/', (string) $v) ? "'" . $v : (string) $v;
    fputcsv($out, ['ID', 'Order ID', 'UTR', 'Merchant', 'Provider', 'Amount', 'Currency', 'Verified At']);
    foreach ($rows as $r) {
        fputcsv($out, array_map($safe, [$r['id'], $r['public_id'], $r['utr'], $r['business_name'], $r['provider_name'], $r['amount'], $r['currency'], $r['verified_at']]));
    }
    fclose($out);
    exit;
}

// PDF Export
if (Request::str('export') === 'pdf') {
    $reportData = [
        'title' => 'MAXXfam Gateway Financial Report',
        'subtitle' => 'Period: ' . ($from ?: 'Earliest') . ' to ' . ($to ?: 'Present') . ' · Generated ' . gmdate('Y-m-d H:i') . ' UTC',
        'summary' => [
            'Total verified payments: ' . (int) $summary['total_count'],
            'Total volume: INR ' . number_format((float) $summary['total_volume'], 2),
            'Average ticket size: INR ' . number_format((float) $summary['avg_amount'], 2),
        ],
        'headers' => ['Order ID', 'UTR', 'Merchant', 'Amount (INR)', 'Date (UTC)'],
        'rows' => array_slice(array_map(static fn (array $r) => [
            $r['public_id'],
            $r['utr'],
            mb_substr((string) $r['business_name'], 0, 20),
            number_format((float) $r['amount'], 2),
            substr((string) $r['verified_at'], 0, 16),
        ], $rows), 0, 80),
    ];

    $pdf = PdfService::report($reportData);
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="MAXXfam-Report.pdf"');
    echo $pdf;
    exit;
}

$merchants = MerchantModel::all();
$providers = ProviderModel::all();

View::render('admin.reports', [
    'title' => 'Reports',
    'active' => 'reports',
    'pageTitle' => 'Financial & operational reports',
    'pageSubtitle' => 'Authoritative ledger analytics computed from real database transactions',
    'summary' => $summary,
    'orderSummary' => $orderSummary,
    'rows' => $rows,
    'merchants' => $merchants,
    'providers' => $providers,
    'filters' => [
        'from' => $from,
        'to' => $to,
        'merchant_id' => $merchantId,
        'provider_id' => $providerId,
    ],
], 'admin');
