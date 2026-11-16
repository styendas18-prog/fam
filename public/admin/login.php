<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/bootstrap.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\HttpException;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Core\View;

$admin = Auth::admin();
if ($admin) {
    Response::redirect(url('/admin/dashboard.php'));
}

if (Request::isPost()) {
    try {
        Csrf::validateOrFail();
        $email = strtolower(Request::str('email'));
        $password = (string) Request::input('password', '');
        RateLimiter::enforce('admin-login:' . Request::ip(), 8, 600, 'Too many administrator sign-in attempts.');
        $errors = Validator::validate(['email' => $email, 'password' => $password], ['email' => 'required|email', 'password' => 'required']);
        if ($errors) {
            throw new HttpException(422, reset($errors));
        }
        $admin = Auth::attemptAdmin($email, $password);
        if ((int) $admin['totp_enabled'] === 1) {
            $_SESSION['admin_2fa_pending'] = (int) $admin['id'];
            unset($_SESSION['admin_2fa_secret']);
            Flash::info('Enter your authenticator code to continue.');
            Response::redirect(url('/admin/two-factor.php'));
        }
        Auth::completeAdminLogin($admin);
        Flash::success('Administrator session started.');
        Response::redirect(url('/admin/dashboard.php'));
    } catch (HttpException $e) {
        Flash::error($e->getMessage());
        Response::redirect(url('/admin/login.php'));
    } catch (Throwable $e) {
        Flash::error('Administrator sign-in is temporarily unavailable.');
        Response::redirect(url('/admin/login.php'));
    }
}

View::render('auth.admin-login', ['title' => 'Administrator sign-in', 'authHeading' => 'Administrator access', 'authCopy' => 'Sign in with your administrator email and password. Two-factor authentication can be enabled optionally after sign-in.'], 'auth');
