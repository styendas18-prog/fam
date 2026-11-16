<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\Request;
use App\Core\Response;

if (!Request::isPost()) {
    Response::redirect(url('/user/dashboard.php'));
}

Csrf::validateOrFail();
Auth::logoutUser(false);
Flash::success('You have been signed out.');
Response::redirect(url('/login.php'));
