<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Env;

// Authorize execution: CLI or CRON_TOKEN
if (PHP_SAPI !== 'cli') {
    $providedToken = $_GET['token'] ?? $_SERVER['HTTP_X_CRON_TOKEN'] ?? '';
    $cronToken = Env::get('CRON_TOKEN', '');
    if ($cronToken === '' || !hash_equals($cronToken, (string) $providedToken)) {
        http_response_code(403);
        exit('Access denied: Invalid or missing cron token.');
    }
}

if (PHP_SAPI !== 'cli') {
    header('Content-Type: text/plain');
}

echo "=== MAXXfam Background Task Scheduler ===\n";
echo "Timestamp: " . gmdate('Y-m-d H:i:s') . " UTC\n\n";

$scripts = [
    'cron/expire_orders.php',
    'cron/verify_payments.php',
    'cron/retry_webhooks.php',
    'cron/process_email.php',
    'cron/process_email_verification.php',
];

foreach ($scripts as $file) {
    echo "Running " . $file . "...\n";
    $fullPath = dirname(__DIR__) . '/' . $file;
    if (is_file($fullPath)) {
        ob_start();
        include $fullPath;
        $out = ob_get_clean();
        echo $out . "\n";
    }
}

echo "=== All cron tasks completed successfully ===\n";
