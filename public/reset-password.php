<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Flash;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Core\View;

$token = Request::str('token');
$reset = null;
if ($token !== '') {
    $reset = Database::fetch(
        'SELECT * FROM password_resets WHERE token_hash = ? AND user_type = "user" AND used_at IS NULL AND expires_at > UTC_TIMESTAMP() LIMIT 1',
        [hash('sha256', $token)]
    );
}

if (!$reset) {
    Flash::error('This reset link is invalid or has expired. Request a new one.');
    Response::redirect(url('/forgot-password.php'));
}

if (Request::isPost()) {
    try {
        Csrf::validateOrFail();
        $password = (string) Request::input('password', '');
        $confirmation = (string) Request::input('password_confirmation', '');
        $errors = Validator::validate(['password' => $password, 'password_confirmation' => $confirmation], [
            'password' => 'required|min:10|max:200',
            'password_confirmation' => 'required|matches:password',
        ]);
        if (!$errors) {
            $weak = Validator::passwordStrong($password);
            if ($weak !== null) {
                $errors['password'] = $weak;
            }
        }
        if ($errors) {
            Flash::error(reset($errors));
            Response::redirect(url('/reset-password.php?token=' . urlencode($token)));
        }
        $user = Database::fetch('SELECT * FROM users WHERE email = ? AND status = "active" AND deleted_at IS NULL LIMIT 1', [$reset['email']]);
        if (!$user) {
            throw new RuntimeException('Account not available.');
        }
        Database::transaction(static function () use ($user, $password, $reset): void {
            Database::execute('UPDATE users SET password_hash = ?, failed_attempts = 0, locked_until = NULL WHERE id = ?', [Auth::hashPassword($password), $user['id']]);
            Database::execute('UPDATE password_resets SET used_at = UTC_TIMESTAMP() WHERE id = ?', [$reset['id']]);
            Database::execute('UPDATE login_sessions SET revoked_at = UTC_TIMESTAMP() WHERE user_type = "user" AND user_id = ? AND revoked_at IS NULL', [$user['id']]);
        });
        try {
            App\Core\Audit::security('password_reset_completed', 'info', ['email' => $user['email']], (int) $user['id'], null);
        } catch (Throwable) {
        }
        Flash::success('Your password has been updated. Sign in with the new password.');
        Response::redirect(url('/login.php'));
    } catch (Throwable $e) {
        Flash::error('We could not update the password. Request a new reset link.');
        Response::redirect(url('/forgot-password.php'));
    }
}

View::render('auth.reset', ['title' => 'Set a new password', 'token' => $token, 'authHeading' => 'Choose a new password', 'authCopy' => 'This link can be used once. After saving, all existing sessions for the account are revoked.'], 'auth');
