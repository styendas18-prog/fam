<?php
declare(strict_types=1);

namespace App\Core;

final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_token" value="' . e(self::token()) . '">';
    }

    public static function verify(?string $token): bool
    {
        $known = $_SESSION['_csrf'] ?? '';
        return is_string($token) && $known !== '' && hash_equals($known, $token);
    }

    public static function validateOrFail(bool $json = false): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }
        $token = $_POST['_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
        if (!self::verify(is_string($token) ? $token : null)) {
            if ($json) {
                Security::json(['success' => false, 'error' => ['code' => 'csrf_failed', 'message' => 'The security token is invalid or expired. Refresh the page and try again.']], 419);
            }
            http_response_code(419);
            View::render('errors/419', ['title' => 'Session expired'], 'public');
            exit;
        }
    }
}
