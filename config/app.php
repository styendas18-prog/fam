<?php
declare(strict_types=1);

use App\Core\Env;

return [
    'name' => Env::get('APP_NAME', 'MAXXfam'),
    'tagline' => 'Smart Payments. Real Verification. Complete Control.',
    'env' => Env::get('APP_ENV', 'production'),
    'debug' => Env::bool('APP_DEBUG', false),
    'url' => rtrim((string) Env::get('APP_URL', 'http://localhost:8080'), '/'),
    'key' => Env::get('APP_KEY', ''),
    'timezone' => Env::get('APP_TIMEZONE', 'UTC'),
    'display_timezone' => Env::get('DISPLAY_TIMEZONE', 'Asia/Kolkata'),
    'session' => [
        'name' => Env::get('SESSION_NAME', 'MAXXFAMSESS'),
        'secure' => Env::bool('SESSION_SECURE', true),
        'samesite' => Env::get('SESSION_SAMESITE', 'Lax'),
        'lifetime' => Env::int('SESSION_LIFETIME', 3600),
    ],
    'mail' => [
        'transport' => Env::get('MAIL_TRANSPORT', 'smtp'),
        'host' => Env::get('MAIL_HOST', ''),
        'port' => Env::int('MAIL_PORT', 587),
        'encryption' => Env::get('MAIL_ENCRYPTION', 'tls'),
        'username' => Env::get('MAIL_USERNAME', ''),
        'password' => Env::get('MAIL_PASSWORD', ''),
        'from_address' => Env::get('MAIL_FROM_ADDRESS', ''),
        'from_name' => Env::get('MAIL_FROM_NAME', 'MAXXfam'),
        'timeout' => Env::int('MAIL_TIMEOUT', 12),
    ],
    'db' => [
        'host' => Env::get('DB_HOST', 'localhost'),
        'port' => Env::int('DB_PORT', 3306),
        'name' => Env::get('DB_NAME', 'maxxfam'),
        'user' => Env::get('DB_USER', 'root'),
        'pass' => Env::get('DB_PASS', ''),
        'charset' => Env::get('DB_CHARSET', 'utf8mb4'),
    ],
    'providers' => [
        'famgateway' => [
            'base_url' => Env::get('FAMGATEWAY_BASE_URL', 'https://famgateway.in'),
            'docs_url' => 'https://famgateway.in/docs.php',
            'create_path' => '/api/create-order',
            'verify_path' => '/api/verify-order.php',
            'history_path' => '/api/get-transactions.php',
        ],
    ],
    'cron_token' => Env::get('CRON_TOKEN', ''),
    'log_level' => Env::get('LOG_LEVEL', 'warning'),
];
