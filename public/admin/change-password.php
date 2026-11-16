<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/bootstrap.php';

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\HttpException;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Core\View;

$admin = Auth::requireAdmin(null, true);

if (Request::isPost()) {
    try {
        Csrf::validateOrFail();
        RateLimiter::enforce('admin-password-change:' . (int) $admin['id'], 6, 900, 'Too many password change attempts. Try again later.');

        $current = (string) Request::input('current_password', '');
        $new = (string) Request::input('new_password', '');
        $confirm = (string) Request::input('new_password_confirmation', '');

        if (!password_verify($current, (string) $admin['password_hash'])) {
            Audit::security('admin_password_change_failed', 'warning', ['reason' => 'current_password'], null, (int) $admin['id']);
            throw new HttpException(422, 'The current password is incorrect.');
        }
        if (strlen($new) < 12) {
            throw new HttpException(422, 'Administrator passwords must be at least 12 characters.');
        }
        $error = Validator::passwordStrong($new);
        if ($error !== null) {
            throw new HttpException(422, $error);
        }
        if (!hash_equals($new, $confirm)) {
            throw new HttpException(422, 'The new password confirmation does not match.');
        }
        if (hash_equals($current, $new)) {
            throw new HttpException(422, 'Choose a password different from the temporary one.');
        }

        \App\Core\Database::execute(
            'UPDATE admins SET password_hash = ?, must_change_password = 0, failed_attempts = 0, locked_until = NULL WHERE id = ?',
            [Auth::hashPassword($new), (int) $admin['id']]
        );
        Session::regenerate(true);
        Audit::security('admin_password_changed', 'info', ['forced_rotation' => (int) ($admin['must_change_password'] ?? 0) === 1], null, (int) $admin['id']);
        Flash::success('Administrator password updated. You can now use the admin panel.');
        Response::redirect(url('/admin/dashboard.php'));
    } catch (HttpException $e) {
        Flash::error($e->getMessage());
        Response::redirect(url('/admin/change-password.php'));
    } catch (\Throwable $e) {
        Flash::error('Password change failed: ' . $e->getMessage());
        Response::redirect(url('/admin/change-password.php'));
    }
}

View::render('admin.change-password', [
    'title' => 'Change password',
    'active' => 'change-password',
    'pageTitle' => 'Change administrator password',
    'pageSubtitle' => 'Set a permanent password before using the control panel',
    'forced' => (int) ($admin['must_change_password'] ?? 0) === 1,
    'adminEmail' => (string) $admin['email'],
], 'admin');
