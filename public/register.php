<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Flash;
use App\Core\HttpException;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Core\Settings;
use App\Core\Validator;
use App\Core\View;
use App\Models\MerchantModel;
use App\Services\Mailer;

if (Auth::user()) {
    Response::redirect(url('/user/dashboard.php'));
}

if (Request::isPost()) {
    try {
        Csrf::validateOrFail();
        if (!Settings::bool('registration_enabled', true)) {
            throw new HttpException(403, 'New registrations are temporarily closed.');
        }
        RateLimiter::enforce('register:' . Request::ip(), 5, 3600, 'Too many accounts created from this network. Try again later.');
        $data = [
            'name' => Request::str('name'),
            'business_name' => Request::str('business_name'),
            'email' => strtolower(Request::str('email')),
            'password' => (string) Request::input('password', ''),
            'password_confirmation' => (string) Request::input('password_confirmation', ''),
        ];
        $errors = Validator::validate($data, [
            'name' => 'required|min:2|max:120',
            'business_name' => 'required|min:2|max:160',
            'email' => 'required|email|max:190',
            'password' => 'required|min:10|max:200',
            'password_confirmation' => 'required|matches:password',
        ]);
        if (!$errors) {
            $weak = Validator::passwordStrong($data['password']);
            if ($weak !== null) {
                $errors['password'] = $weak;
            }
        }
        if (!Request::bool('terms')) {
            $errors['terms'] = 'You must accept the terms to create an account.';
        }
        if ($errors) {
            remember_old($data);
            throw new HttpException(422, reset($errors));
        }
        if (Database::fetch('SELECT id FROM users WHERE email = ? LIMIT 1', [$data['email']])) {
            throw new HttpException(422, 'An account with this email already exists.');
        }

        $user = Database::transaction(static function () use ($data): array {
            $id = Database::insert(
                'INSERT INTO users (name, email, password_hash, status, email_verified_at, created_at) VALUES (?, ?, ?, "active", NULL, UTC_TIMESTAMP())',
                [$data['name'], $data['email'], Auth::hashPassword($data['password'])]
            );
            MerchantModel::create($id, $data['business_name']);
            $row = Database::fetch('SELECT * FROM users WHERE id = ?', [$id]);
            if (!$row) {
                throw new RuntimeException('User creation failed.');
            }
            return $row;
        });

        try {
            Mailer::queueWelcome($user);
        } catch (Throwable) {
        }
        Auth::startUserSession($user);
        clear_old();
        Flash::success('Your merchant account is ready. Connect a provider to start creating payments.');
        Response::redirect(url('/user/dashboard.php'));
    } catch (HttpException $e) {
        Flash::error($e->getMessage());
        Response::redirect(url('/register.php'));
    } catch (Throwable $e) {
        if ($e instanceof PDOException && Database::isDuplicate($e)) {
            Flash::error('An account with this email already exists.');
        } else {
            Flash::error('We could not create your account. Please try again.');
        }
        Response::redirect(url('/register.php'));
    }
}

clear_old();
View::render('auth.register', ['title' => 'Create account', 'authHeading' => 'Create your merchant account', 'authCopy' => 'Start with honest payments. You can connect a provider after creating your account.'], 'auth');
