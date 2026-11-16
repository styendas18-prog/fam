<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Audit;
use App\Core\Env;
use App\Core\Logger;
use App\Models\WebhookModel;
use App\Services\WebhookDispatcher;

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
$dueDeliveries = WebhookModel::dueDeliveries(50);
$successCount = 0;
$attemptedCount = 0;

foreach ($dueDeliveries as $delivery) {
    try {
        $attemptedCount++;
        $res = WebhookDispatcher::deliver((int) $delivery['id']);
        if (!empty($res['success'])) {
            $successCount++;
        }
    } catch (\Throwable $e) {
        Logger::error('Failed to dispatch webhook in cron', [
            'delivery_id' => $delivery['id'],
            'error' => $e->getMessage(),
        ]);
    }
}

$duration = round(microtime(true) - $startTime, 3);
Audit::admin(null, 'cron_retry_webhooks', 'cron', null, [
    'attempted_count' => $attemptedCount,
    'success_count' => $successCount,
    'duration_s' => $duration,
]);

$message = sprintf("[%s] Webhook retry cron finished. Attempted %d, succeeded %d in %.3fs\n", gmdate('Y-m-d H:i:s'), $attemptedCount, $successCount, $duration);
if (PHP_SAPI === 'cli') {
    echo $message;
} else {
    header('Content-Type: text/plain');
    echo $message;
}
