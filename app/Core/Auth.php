<?php
declare(strict_types=1);

namespace App\Core;

use Throwable;

final class Auth
{
    private static ?array $userCache = null;
    private static ?array $adminCache = null;

    public static function attemptUser(string $email, string $password): array
    {
        $email = strtolower(trim($email));
        $ip = Security::clientIp();
        $recentFailures = (int) Database::scalar(
            'SELECT COUNT(*) FROM login_attempts WHERE email = ? AND user_type = "user" AND success = 0 AND created_at > (UTC_TIMESTAMP() - INTERVAL 15 MINUTE)',
            [$email]
        );
        if ($recentFailures >= 8) {
            Audit::security('login_rate_limited', 'warning', ['email' => $email], null, null);
            throw new HttpException(429, 'Too many failed attempts. Try again in 15 minutes.');
        }

        $user = Database::fetch('SELECT * FROM users WHERE email = ? AND deleted_at IS NULL LIMIT 1', [$email]);
        $ok = $user && password_verify($password, (string) $user['password_hash']);
        self::recordAttempt($email, 'user', $ok, $ip);

        if (!$ok) {
            if ($user) {
                $fails = (int) $user['failed_attempts'] + 1;
                $locked = $fails >= 10 ? gmdate('Y-m-d H:i:s', time() + 900) : null;
                Database::execute('UPDATE users SET failed_attempts = ?, locked_until = ? WHERE id = ?', [$fails, $locked, $user['id']]);
                Audit::security('user_login_failed', 'warning', ['email' => $email], (int) $user['id'], null);
            }
            throw new HttpException(401, 'Invalid email or password.');
        }
        if ($user['status'] !== 'active') {
            Audit::security('user_login_blocked', 'warning', ['email' => $email, 'status' => $user['status']], (int) $user['id'], null);
            throw new HttpException(403, 'This account is not active. Contact support.');
        }
        if (!empty($user['locked_until']) && strtotime((string) $user['locked_until']) > time()) {
            throw new HttpException(423, 'This account is temporarily locked. Try again later.');
        }
        if (password_needs_rehash((string) $user['password_hash'], self::passwordAlgo())) {
            Database::execute('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, self::passwordAlgo()), $user['id']]);
        }

        self::startUserSession($user);
        return $user;
    }

    public static function startUserSession(array $user): void
    {
        Session::regenerate(true);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['auth_time'] = time();
        self::$userCache = null;
        Database::execute(
            'UPDATE users SET failed_attempts = 0, locked_until = NULL, last_login_at = UTC_TIMESTAMP(), last_login_ip = ? WHERE id = ?',
            [Security::clientIp(), $user['id']]
        );
        self::recordSession('user', (int) $user['id']);
        Audit::security('user_login_success', 'info', ['email' => $user['email']], (int) $user['id'], null);
    }

    public static function user(): ?array
    {
        if (self::$userCache !== null) {
            return self::$userCache;
        }
        $id = (int) ($_SESSION['user_id'] ?? 0);
        if ($id <= 0) {
            return null;
        }
        $user = Database::fetch(
            'SELECT u.*, m.id AS merchant_id, m.business_name, m.merchant_code, m.status AS merchant_status,
                    m.support_email AS merchant_support_email
             FROM users u
             LEFT JOIN merchants m ON m.user_id = u.id AND m.deleted_at IS NULL
             WHERE u.id = ? AND u.deleted_at IS NULL LIMIT 1',
            [$id]
        );
        if (!$user || $user['status'] !== 'active') {
            self::logoutUser(false);
            return null;
        }
        return self::$userCache = $user;
    }

    public static function requireUser(): array
    {
        $user = self::user();
        if (!$user) {
            $target = $_SERVER['REQUEST_URI'] ?? '/user/dashboard.php';
            $_SESSION['_intended'] = $target;
            Flash::error('Please sign in to continue.');
            Response::redirect(url('/login.php'));
        }
        return $user;
    }

    public static function logoutUser(bool $redirect = true): void
    {
        if (isset($_SESSION['user_id'])) {
            self::revokeSession();
            Audit::security('user_logout', 'info', [], (int) $_SESSION['user_id'], null);
        }
        unset($_SESSION['user_id'], $_SESSION['auth_time']);
        self::$userCache = null;
        if ($redirect) {
            Flash::info('You have been signed out.');
            Response::redirect(url('/login.php'));
        }
    }

    public static function attemptAdmin(string $email, string $password): array
    {
        $email = strtolower(trim($email));
        $ip = Security::clientIp();
        $recentFailures = (int) Database::scalar(
            'SELECT COUNT(*) FROM login_attempts WHERE email = ? AND user_type = "admin" AND success = 0 AND created_at > (UTC_TIMESTAMP() - INTERVAL 15 MINUTE)',
            [$email]
        );
        if ($recentFailures >= 6) {
            Audit::security('admin_login_rate_limited', 'critical', ['email' => $email], null, null);
            throw new HttpException(429, 'Too many failed attempts. Try again in 15 minutes.');
        }
        $admin = Database::fetch('SELECT * FROM admins WHERE email = ? LIMIT 1', [$email]);
        $ok = $admin && password_verify($password, (string) $admin['password_hash']);
        self::recordAttempt($email, 'admin', $ok, $ip);
        if (!$ok) {
            if ($admin) {
                $fails = (int) $admin['failed_attempts'] + 1;
                $locked = $fails >= 8 ? gmdate('Y-m-d H:i:s', time() + 1800) : null;
                Database::execute('UPDATE admins SET failed_attempts = ?, locked_until = ? WHERE id = ?', [$fails, $locked, $admin['id']]);
            }
            Audit::security('admin_login_failed', 'warning', ['email' => $email], null, $admin ? (int) $admin['id'] : null);
            throw new HttpException(401, 'Invalid administrator credentials.');
        }
        if ($admin['status'] !== 'active') {
            throw new HttpException(403, 'This administrator account is disabled.');
        }
        if (!empty($admin['locked_until']) && strtotime((string) $admin['locked_until']) > time()) {
            throw new HttpException(423, 'This administrator account is temporarily locked.');
        }
        if (password_needs_rehash((string) $admin['password_hash'], self::passwordAlgo())) {
            Database::execute('UPDATE admins SET password_hash = ? WHERE id = ?', [password_hash($password, self::passwordAlgo()), $admin['id']]);
        }
        Database::execute('UPDATE admins SET failed_attempts = 0, locked_until = NULL WHERE id = ?', [$admin['id']]);
        return $admin;
    }

    public static function pendingAdmin(): ?array
    {
        $id = (int) ($_SESSION['admin_2fa_pending'] ?? 0);
        if ($id <= 0) {
            return null;
        }
        $admin = Database::fetch('SELECT * FROM admins WHERE id = ? AND status = "active" LIMIT 1', [$id]);
        return $admin ?: null;
    }

    public static function completeAdminLogin(array $admin): void
    {
        Session::regenerate(true);
        $_SESSION['admin_id'] = (int) $admin['id'];
        $_SESSION['admin_auth_time'] = time();
        unset($_SESSION['admin_2fa_pending'], $_SESSION['admin_2fa_secret']);
        self::$adminCache = null;
        Database::execute(
            'UPDATE admins SET last_login_at = UTC_TIMESTAMP(), last_login_ip = ? WHERE id = ?',
            [Security::clientIp(), $admin['id']]
        );
        self::recordSession('admin', (int) $admin['id']);
        Audit::security('admin_login_success', 'info', ['role' => $admin['role']], null, (int) $admin['id']);
    }

    public static function admin(): ?array
    {
        if (self::$adminCache !== null) {
            return self::$adminCache;
        }
        $id = (int) ($_SESSION['admin_id'] ?? 0);
        if ($id <= 0) {
            return null;
        }
        $admin = Database::fetch('SELECT * FROM admins WHERE id = ? AND status = "active" LIMIT 1', [$id]);
        if (!$admin) {
            unset($_SESSION['admin_id'], $_SESSION['admin_auth_time']);
            return null;
        }
        return self::$adminCache = $admin;
    }

    public static function requireAdmin(?string $permission = null, bool $allowPasswordRotation = false): array
    {
        $admin = self::admin();
        if (!$admin) {
            if (self::pendingAdmin()) {
                Response::redirect(url('/admin/two-factor.php'));
            }
            Flash::error('Administrator sign-in required.');
            Response::redirect(url('/admin/login.php'));
        }
        if (!$allowPasswordRotation && (int) ($admin['must_change_password'] ?? 0) === 1) {
            Flash::error('Your temporary administrator password must be changed before continuing.');
            Response::redirect(url('/admin/change-password.php'));
        }
        if ($permission !== null && !self::adminCan($admin, $permission)) {
            throw new HttpException(403, 'You do not have permission to perform this action.');
        }
        return $admin;
    }

    public static function adminCan(array $admin, string $permission): bool
    {
        $role = (string) $admin['role'];
        if ($role === 'super_admin') {
            return true;
        }
        $matrix = [
            'admin' => [
                'dashboard', 'users.view', 'users.manage', 'merchants.view', 'transactions.view', 'refunds.manage',
                'orders.view', 'orders.manage', 'providers.view', 'providers.test', 'api.view', 'api.manage',
                'webhooks.view', 'webhooks.retry', 'logs.view', 'support.manage', 'reports.view',
                'announcements.manage', 'settings.payment', 'settings.system', 'settings.smtp',
                'activity.view', 'maintenance.manage', 'emailverify.view', 'emailverify.manage',
                'plans.view', 'plans.manage',
            ],
            'support' => [
                'dashboard', 'users.view', 'merchants.view', 'transactions.view', 'orders.view',
                'support.manage', 'logs.view', 'emailverify.view', 'plans.view',
            ],
        ];
        return in_array($permission, $matrix[$role] ?? [], true);
    }

    public static function logoutAdmin(bool $redirect = true): void
    {
        if (isset($_SESSION['admin_id'])) {
            self::revokeSession();
            Audit::security('admin_logout', 'info', [], null, (int) $_SESSION['admin_id']);
        }
        unset($_SESSION['admin_id'], $_SESSION['admin_auth_time'], $_SESSION['admin_2fa_pending'], $_SESSION['admin_2fa_secret']);
        self::$adminCache = null;
        if ($redirect) {
            Flash::info('Administrator session ended.');
            Response::redirect(url('/admin/login.php'));
        }
    }

    public static function passwordAlgo(): string|int
    {
        return defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
    }

    public static function hashPassword(string $password): string
    {
        return password_hash($password, self::passwordAlgo(), PASSWORD_BCRYPT === self::passwordAlgo() ? ['cost' => 12] : []);
    }

    private static function recordAttempt(string $email, string $type, bool $success, string $ip): void
    {
        Database::execute(
            'INSERT INTO login_attempts (email, user_type, ip, success, created_at) VALUES (?, ?, ?, ?, UTC_TIMESTAMP())',
            [$email, $type, $ip, $success ? 1 : 0]
        );
        // Retain a bounded history.
        if (random_int(1, 20) === 1) {
            Database::execute('DELETE FROM login_attempts WHERE created_at < (UTC_TIMESTAMP() - INTERVAL 30 DAY)');
        }
    }

    private static function recordSession(string $type, int $id): void
    {
        try {
            Database::execute(
                'INSERT INTO login_sessions (session_hash, user_type, user_id, ip, user_agent, last_activity_at, created_at)
                 VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())',
                [Security::hashToken(session_id()), $type, $id, Security::clientIp(), Security::userAgent()]
            );
        } catch (Throwable $e) {
            Logger::warning('Unable to record login session', ['error' => $e->getMessage()]);
        }
    }

    private static function revokeSession(): void
    {
        try {
            Database::execute('UPDATE login_sessions SET revoked_at = UTC_TIMESTAMP() WHERE session_hash = ?', [Security::hashToken(session_id())]);
        } catch (Throwable) {
        }
    }
}
