<?php
declare(strict_types=1);

// Development router for PHP's built-in server:
//   php -S 127.0.0.1:8080 -t public public/router.php
// Production should use Apache/Nginx with public/ as the document root.

$path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
$file = __DIR__ . str_replace('..', '', $path);

if ($path !== '/' && is_file($file)) {
    return false;
}

require __DIR__ . '/index.php';
