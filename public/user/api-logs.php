<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/bootstrap.php';

use App\Core\Database;
use App\Core\Request;
use App\Core\View;
use App\Http\UserPage;
use App\Models\ApiLogModel;

[$user, $merchant] = UserPage::boot();
$merchantId = (int) $merchant['id'];
$filters = [
    'status' => Request::str('status'),
    'q' => Request::str('q'),
    'from' => Request::str('from'),
    'to' => Request::str('to'),
];
$page = max(1, Request::int('page', 1));
$result = ApiLogModel::listForMerchant($merchantId, $filters, $page, 25);
$stats = Database::fetch(
    "SELECT COUNT(*) AS total,
            SUM(created_at >= UTC_DATE()) AS today,
            SUM(status_code >= 400 AND created_at >= (UTC_TIMESTAMP() - INTERVAL 1 DAY)) AS errors_24h,
            ROUND(AVG(latency_ms)) AS avg_latency
     FROM api_logs WHERE merchant_id = ?",
    [$merchantId]
) ?: ['total' => 0, 'today' => 0, 'errors_24h' => 0, 'avg_latency' => 0];
$detail = null;
if (Request::int('id') > 0) {
    $detail = Database::fetch('SELECT * FROM api_logs WHERE id = ? AND merchant_id = ? LIMIT 1', [Request::int('id'), $merchantId]);
}

View::render('user.api-logs', [
    'title' => 'API logs',
    'active' => 'api-logs',
    'pageTitle' => 'API logs',
    'pageSubtitle' => 'Server-recorded requests with redacted bodies',
    'merchant' => $merchant,
    'result' => $result,
    'filters' => $filters,
    'stats' => $stats,
    'detail' => $detail,
], 'dashboard');
