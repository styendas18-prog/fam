<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Audit;
use App\Core\Env;
use App\Core\Logger;
use App\Services\EmailVerificationService;

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
$totals = [
    'accounts' => 0,
    'checked' => 0,
    'verified' => 0,
    'unmatched' => 0,
    'ignored' => 0,
    'duplicate' => 0,
    'failed' => 0,
    'errors' => 0,
];

try {
    $run = EmailVerificationService::scanAll();
    $totals['accounts'] = (int) ($run['accounts'] ?? 0);
    foreach (($run['results'] ?? []) as $result) {
        $totals['checked'] += (int) ($result['checked'] ?? 0);
        $totals['verified'] += (int) ($result['verified'] ?? 0);
        $totals['unmatched'] += (int) ($result['unmatched'] ?? 0);
        $totals['ignored'] += (int) ($result['ignored'] ?? 0);
        $totals['duplicate'] += (int) ($result['duplicate'] ?? 0);
        $totals['failed'] += (int) ($result['failed'] ?? 0);
        if (!empty($result['error'])) {
            $totals['errors']++;
        }
    }
} catch (Throwable $e) {
    Logger::error('Email verification cron failed', ['error' => $e->getMessage()]);
    $totals['errors']++;
}

$duration = round(microtime(true) - $startTime, 3);
Audit::admin(null, 'cron_process_email_verification', 'cron', null, $totals + ['duration_s' => $duration]);

$message = sprintf(
    "[%s] Email verification cron finished. Accounts %d, checked %d, verified %d, unmatched %d, ignored %d, duplicate %d, failed %d, errors %d in %.3fs\n",
    gmdate('Y-m-d H:i:s'),
    $totals['accounts'],
    $totals['checked'],
    $totals['verified'],
    $totals['unmatched'],
    $totals['ignored'],
    $totals['duplicate'],
    $totals['failed'],
    $totals['errors'],
    $duration
);
if (PHP_SAPI === 'cli') {
    echo $message;
} else {
    header('Content-Type: text/plain');
    echo $message;
}
