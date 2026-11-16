<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\HttpException;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Core\View;
use App\Services\Mailer;

if (Auth::user()) {
    Response::redirect(url('/user/dashboard.php'));
}

if (Request::isPost()) {
    try {
        Csrf::validateOrFail();
        $email = strtolower(Request::str('email'));
        $password = (string) Request::input('password', '');
        RateLimiter::enforce('login:' . Request::ip() . ':' . $email, 15, 300, 'Too many sign-in attempts. Wait a few minutes and try again.');
        $errors = Validator::validate(['email' => $email, 'password' => $password], [
            'email' => 'required|email',
            'password' => 'required',
        ]);
        if ($errors) {
            remember_old(['email' => $email]);
            throw new HttpException(422, reset($errors));
        }
        $user = Auth::attemptUser($email, $password);
        try {
            Mailer::queueLoginAlert($user);
        } catch (Throwable) {
        }
        clear_old();
        $intended = $_SESSION['_intended'] ?? '/user/dashboard.php';
        unset($_SESSION['_intended']);
        if (!is_string($intended) || !str_starts_with($intended, '/') || str_starts_with($intended, '//')) {
            $intended = '/user/dashboard.php';
        }
        Flash::success('Welcome back, ' . $user['name'] . '.');
        Response::redirect($intended);
    } catch (HttpException $e) {
        Flash::error($e->getMessage());
        if ($e->status !== 429) {
            remember_old(['email' => Request::str('email')]);
        }
        Response::redirect(url('/login.php'));
    } catch (Throwable $e) {
        Flash::error('Sign-in is temporarily unavailable. Please try again.');
        Response::redirect(url('/login.php'));
    }
}

clear_old();
View::render('auth.login', ['title' => 'Sign in', 'authHeading' => 'Sign in to MAXXfam', 'authCopy' => 'Use the email and password for your merchant account.'], 'auth');
