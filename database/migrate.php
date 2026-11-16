<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

try {
    $pdo = App\Core\Database::pdo();
    $m = App\Core\Migrator::run($pdo, BASE_PATH . '/database/migrations');
    $s = App\Core\Migrator::run($pdo, BASE_PATH . '/database/seeds');
    echo "Migrations executed: {$m}\nSeed statements executed: {$s}\n";
    echo "Database: " . App\Core\Config::get('db.name') . "\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Migration failed: ' . $e->getMessage() . "\n");
    exit(1);
}
