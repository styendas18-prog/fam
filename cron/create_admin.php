<?php
declare(strict_types=1);

/**
 * Secure CLI administrator provisioning / recovery.
 * Usage: php cron/create_admin.php "Name" admin@example.com [super_admin|admin|support]
 * The password is read from STDIN (never passed as an argument, never logged).
 */

require dirname(__DIR__) . '/app/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

$name = trim((string) ($argv[1] ?? ''));
$email = strtolower(trim((string) ($argv[2] ?? '')));
$role = (string) ($argv[3] ?? 'super_admin');
if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || !in_array($role, ['super_admin', 'admin', 'support'], true)) {
    fwrite(STDERR, "Usage: php cron/create_admin.php \"Name\" email@example.com [super_admin|admin|support]\n");
    exit(1);
}
if (!App\Core\Database::connection()) {
    fwrite(STDERR, "Database connection failed. Check .env and run database/migrate.php first.\n");
    exit(1);
}

fwrite(STDOUT, 'Password (min 12 chars, upper/lower/number): ');
$password = trim((string) fgets(STDIN));
$error = App\Core\Validator::passwordStrong($password);
if (strlen($password) < 12) {
    $error = 'Password must be at least 12 characters for administrators.';
}
if ($error) {
    fwrite(STDERR, $error . "\n");
    exit(1);
}

$existing = App\Core\Database::fetch('SELECT id FROM admins WHERE email = ?', [$email]);
if ($existing) {
    App\Core\Database::execute(
        'UPDATE admins SET name = ?, password_hash = ?, role = ?, status = "active", failed_attempts = 0, locked_until = NULL, totp_enabled = 0, totp_secret = NULL WHERE id = ?',
        [$name, App\Core\Auth::hashPassword($password), $role, $existing['id']]
    );
    echo "Administrator updated. 2FA was reset; it can be re-enabled from the admin menu.\n";
} else {
    App\Core\Database::insert(
        'INSERT INTO admins (name, email, password_hash, role, status, created_at) VALUES (?, ?, ?, ?, "active", UTC_TIMESTAMP())',
        [$name, $email, App\Core\Auth::hashPassword($password), $role]
    );
    echo "Administrator created. TOTP 2FA is optional and can be enabled from the admin menu.\n";
}
App\Core\Audit::security('admin_cli_provisioned', 'warning', ['email' => $email, 'role' => $role], null, null);
