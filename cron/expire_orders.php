<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Audit;
use App\Core\Database;
use App\Core\Env;
use App\Core\Logger;
use App\Models\PaymentOrderModel;
use App\Services\VerificationService;

// Authorize execution: CLI or CRON_TOKEN
if (PHP_SAPI !== 'cli') {
    $providedToken = $_GET['token'] ?? $_SERVER['HTTP_X_CRON_TOKEN'] ?? '';
    $cronToken = Env::get('CRON_TOKEN', '');
    if ($cronToken === '' || !hash_equals($cronToken, (string) $providedToken)) {
        http_response_code(403);
        exit('Access denied: Invalid or missing cron token.');
    }
}

$startTime = microtime(true);
$expiredOrders = PaymentOrderModel::expiredPending(100);
$count = 0;

foreach ($expiredOrders as $order) {
    try {
        VerificationService::markExpired($order);
        $count++;
    } catch (\Throwable $e) {
        Logger::error('Failed to expire order in cron', [
            'order' => $order['public_id'],
            'error' => $e->getMessage(),
        ]);
    }
}

$duration = round(microtime(true) - $startTime, 3);
Audit::admin(null, 'cron_expire_orders', 'cron', null, [
    'expired_count' => $count,
    'scanned' => count($expiredOrders),
    'duration_s' => $duration,
]);

$message = sprintf("[%s] Expired orders cron finished. Expired %d of %d orders in %.3fs\n", gmdate('Y-m-d H:i:s'), $count, count($expiredOrders), $duration);
if (PHP_SAPI === 'cli') {
    echo $message;
} else {
    header('Content-Type: text/plain');
    echo $message;
}
