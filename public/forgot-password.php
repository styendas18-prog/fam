<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Csrf;
use App\Core\Database;
use App\Core\Flash;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Core\Security;
use App\Core\View;
use App\Services\Mailer;

if (Request::isPost()) {
    try {
        Csrf::validateOrFail();
        RateLimiter::enforce('forgot:' . Request::ip(), 5, 900, 'Too many reset requests. Try again later.');
        $email = strtolower(Request::str('email'));
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $user = Database::fetch('SELECT id, name, email FROM users WHERE email = ? AND status = "active" AND deleted_at IS NULL LIMIT 1', [$email]);
            if ($user) {
                $token = Security::randomToken(32);
                Database::execute('DELETE FROM password_resets WHERE email = ? AND user_type = "user"', [$email]);
                Database::execute(
                    'INSERT INTO password_resets (user_type, email, token_hash, expires_at, created_at) VALUES ("user", ?, ?, ?, UTC_TIMESTAMP())',
                    [$email, hash('sha256', $token), gmdate('Y-m-d H:i:s', time() + 1800)]
                );
                $resetUrl = url('/reset-password.php?token=' . $token);
                try {
                    Mailer::queuePasswordReset($user, $resetUrl);
                } catch (Throwable) {
                }
            }
        }
        Flash::success('If an account exists for that email, a reset link has been queued for delivery.');
        Response::redirect(url('/forgot-password.php'));
    } catch (Throwable $e) {
        Flash::error('We could not process the reset request right now.');
        Response::redirect(url('/forgot-password.php'));
    }
}

View::render('auth.forgot', ['title' => 'Forgot password', 'authHeading' => 'Reset your password', 'authCopy' => 'Enter the email on your account. If it exists, we will send a time-limited reset link.'], 'auth');
