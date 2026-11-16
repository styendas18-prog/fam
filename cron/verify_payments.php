<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Audit;
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
$pendingOrders = PaymentOrderModel::pendingDue(50);
$verifiedCount = 0;
$checkedCount = 0;

foreach ($pendingOrders as $order) {
    try {
        $checkedCount++;
        $updated = VerificationService::verify($order, 'cron');
        if (($updated['status'] ?? '') === 'success') {
            $verifiedCount++;
        }
    } catch (\Throwable $e) {
        Logger::error('Failed to verify pending order in cron', [
            'order' => $order['public_id'],
            'error' => $e->getMessage(),
        ]);
    }
}

$duration = round(microtime(true) - $startTime, 3);
Audit::admin(null, 'cron_verify_payments', 'cron', null, [
    'checked_count' => $checkedCount,
    'verified_count' => $verifiedCount,
    'duration_s' => $duration,
]);

$message = sprintf("[%s] Payment verification cron finished. Checked %d orders, verified %d in %.3fs\n", gmdate('Y-m-d H:i:s'), $checkedCount, $verifiedCount, $duration);
if (PHP_SAPI === 'cli') {
    echo $message;
} else {
    header('Content-Type: text/plain');
    echo $message;
}
