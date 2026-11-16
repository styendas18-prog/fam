<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Audit;
use App\Core\Env;
use App\Services\Mailer;

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
$results = Mailer::processQueue(25);
$sent = 0;
foreach ($results as $res) {
    if (!empty($res['sent'])) {
        $sent++;
    }
}

$duration = round(microtime(true) - $startTime, 3);
Audit::admin(null, 'cron_process_email', 'cron', null, [
    'processed' => count($results),
    'sent' => $sent,
    'duration_s' => $duration,
]);

$message = sprintf("[%s] Process email cron finished. Processed %d, sent %d in %.3fs\n", gmdate('Y-m-d H:i:s'), count($results), $sent, $duration);
if (PHP_SAPI === 'cli') {
    echo $message;
} else {
    header('Content-Type: text/plain');
    echo $message;
}
