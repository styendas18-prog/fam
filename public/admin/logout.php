<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/bootstrap.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\Request;
use App\Core\Response;

if (!Request::isPost()) {
    Response::redirect(url('/admin/login.php'));
}
Csrf::validateOrFail();
Auth::logoutAdmin(false);
Flash::success('Administrator session ended.');
Response::redirect(url('/admin/login.php'));
