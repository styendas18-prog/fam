<?php
declare(strict_types=1);

use App\Core\Config;
use App\Core\Env;
use App\Core\HttpException;
use App\Core\Logger;
use App\Core\Security;
use App\Core\Session;

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

require BASE_PATH . '/app/autoload.php';
require BASE_PATH . '/app/Helpers/functions.php';

Env::load(BASE_PATH . '/.env');
Config::load(BASE_PATH . '/config/app.php');

date_default_timezone_set((string) Config::get('timezone', 'UTC'));
mb_internal_encoding('UTF-8');
error_reporting(E_ALL);
ini_set('display_errors', Config::get('debug') ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', BASE_PATH . '/storage/logs/php-' . gmdate('Y-m-d') . '.log');

set_exception_handler(static function (Throwable $e): void {
    $status = $e instanceof HttpException ? $e->status : 500;
    $message = $e instanceof HttpException ? $e->getMessage() : 'Something went wrong on our side.';
    Logger::error('Unhandled exception', [
        'type' => get_class($e),
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => Config::get('debug') ? $e->getTraceAsString() : null,
    ]);
    if (headers_sent()) {
        echo '<h1>' . htmlspecialchars($message, ENT_QUOTES) . '</h1>';
        return;
    }
    $wantsJson = str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')
        || str_starts_with((string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH), '/api/');
    if ($wantsJson) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'error' => [
                'code' => $status === 404 ? 'not_found' : ($status === 403 ? 'forbidden' : ($status === 429 ? 'rate_limited' : 'server_error')),
                'message' => $message,
            ],
        ]);
        return;
    }
    http_response_code($status);
    Security::sendHeaders();
    $safe = htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $brand = htmlspecialchars((string) Config::get('name', 'MAXXfam'), ENT_QUOTES);
    $home = htmlspecialchars(url('/'), ENT_QUOTES);
    echo <<<HTML
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>{$status} · {$brand}</title><style>
body{margin:0;min-height:100vh;display:grid;place-items:center;background:#070d1c;color:#e8eefc;font-family:ui-sans-serif,system-ui,sans-serif}
.card{max-width:520px;padding:48px;border:1px solid rgba(148,163,184,.18);background:linear-gradient(160deg,rgba(30,58,138,.35),rgba(7,13,28,.9));border-radius:24px;box-shadow:0 30px 80px rgba(0,0,0,.45)}
.badge{color:#7dd3fc;font-size:12px;letter-spacing:.18em;text-transform:uppercase}.card h1{font-size:42px;margin:16px 0 10px}.card p{color:#9fb0cc;line-height:1.7}
a{display:inline-block;margin-top:22px;color:#fff;text-decoration:none;background:#2563eb;padding:12px 18px;border-radius:12px;font-weight:600}
</style></head><body><main class="card"><span class="badge">{$brand} · Error {$status}</span><h1>{$status}</h1><p>{$safe}</p><a href="{$home}">Back to MAXXfam</a></main></body></html>
HTML;
});

register_shutdown_function(static function (): void {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        Logger::error('Fatal error', $error);
    }
});

if (PHP_SAPI !== 'cli') {
    Session::start();
    Security::sendHeaders();
}
