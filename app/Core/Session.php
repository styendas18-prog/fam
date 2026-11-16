<?php
declare(strict_types=1);

namespace App\Core;

final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        $secure = (bool) Config::get('session.secure', true);
        if (str_starts_with((string) Config::get('url'), 'http://')) {
            $secure = false;
        }
        session_name((string) Config::get('session.name', 'MAXXFAMSESS'));
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => (string) Config::get('session.samesite', 'Lax'),
        ]);
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.sid_length', '48');
        ini_set('session.sid_bits_per_character', '6');
        session_start();

        $lifetime = (int) Config::get('session.lifetime', 3600);
        $now = time();
        if (isset($_SESSION['_last_activity']) && ($now - (int) $_SESSION['_last_activity']) > $lifetime) {
            self::destroy();
            session_start();
            $_SESSION['_flash']['error'][] = 'Your session expired. Please sign in again.';
        }
        $_SESSION['_last_activity'] = $now;
    }

    public static function regenerate(bool $deleteOld = true): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id($deleteOld);
            $_SESSION['_last_activity'] = time();
        }
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $params['path'],
                'domain' => $params['domain'],
                'secure' => (bool) $params['secure'],
                'httponly' => (bool) $params['httponly'],
                'samesite' => $params['samesite'] ?? 'Lax',
            ]);
        }
        session_destroy();
    }
}
