<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Csrf;
use App\Core\Flash;
use App\Core\HttpException;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\Settings;
use App\Core\View;
use App\Http\PaymentLinkController;
use App\Models\SupportModel;

$path = Request::path();
$rawPath = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/');

// Direct dispatch for API endpoints, hosted checkout, and receipts.
// The raw path also covers subdirectory installs and PHP's built-in dev router,
// where SCRIPT_NAME can equal the requested path.
if (str_starts_with($path, '/api/v1') || preg_match('#(?:^|/)api/v1(?:/|$)#', $rawPath)) {
    require __DIR__ . '/api/v1/index.php';
    exit;
}
if (str_starts_with($path, '/api/webhooks')) {
    $script = __DIR__ . $path;
    if (!str_ends_with($script, '.php')) {
        $script .= '.php';
    }
    if (is_file($script)) {
        require $script;
        exit;
    }
}
if ($path === '/checkout' || $path === '/checkout.php') {
    require __DIR__ . '/checkout.php';
    exit;
}
if ($path === '/receipt' || $path === '/receipt.php') {
    require __DIR__ . '/receipt.php';
    exit;
}

// Front controller handles clean public URLs. Authentication pages, dashboards,
// checkout and APIs are physical PHP entry points under public/.
$isPublicRoute = !str_starts_with($path, '/user') && !str_starts_with($path, '/admin')
    && !str_starts_with($path, '/api') && $path !== '/checkout.php';

if ($isPublicRoute && Settings::bool('maintenance_mode', false) && !in_array($path, ['/status', '/contact'], true)) {
    http_response_code(503);
    header('Retry-After: 3600');
    View::render('public.maintenance', ['title' => 'Maintenance', 'nav' => ''], 'public');
    exit;
}

Router::get('/', static function (): void {
    View::render('public.home', ['title' => 'Smart UPI payments with real verification', 'nav' => 'home'], 'public');
});

Router::get('/features', static fn (): mixed => View::render('public.features', [], 'public'));
Router::get('/how-it-works', static fn (): mixed => View::render('public.how-it-works', [], 'public'));
Router::get('/docs', static fn (): mixed => View::render('public.docs', [], 'public'));
Router::get('/api-reference', static fn (): mixed => View::render('public.api-reference', [], 'public'));
Router::get('/pricing', static fn (): mixed => View::render('public.pricing', [], 'public'));
Router::get('/status', static fn (): mixed => View::render('public.status', [], 'public'));
Router::get('/faq', static fn (): mixed => View::render('public.faq', [], 'public'));
Router::get('/privacy', static fn (): mixed => View::render('public.privacy', [], 'public'));
Router::get('/terms', static fn (): mixed => View::render('public.terms', [], 'public'));

Router::get('/contact', static function (): void {
    View::render('public.contact', ['title' => 'Contact support'], 'public');
});

Router::post('/contact', static function (): void {
    try {
        Csrf::validateOrFail();
        if (Request::str('website') !== '') {
            throw new HttpException(422, 'Your submission could not be accepted.');
        }
        RateLimiter::enforce('contact:' . Request::ip(), 5, 3600, 'Too many tickets from this network. Please email support directly.');
        $name = Request::str('name');
        $email = strtolower(Request::str('email'));
        $subject = Request::str('subject');
        $category = Request::str('category', 'general');
        $priority = Request::str('priority', 'normal');
        $message = trim((string) Request::input('message', ''));
        if ($name === '' || mb_strlen($name) < 2 || mb_strlen($name) > 120) {
            throw new HttpException(422, 'Enter your name (2–120 characters).');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new HttpException(422, 'Enter a valid email address.');
        }
        if (mb_strlen($subject) < 3 || mb_strlen($subject) > 190) {
            throw new HttpException(422, 'Enter a subject between 3 and 190 characters.');
        }
        if (mb_strlen($message) < 10 || mb_strlen($message) > 5000) {
            throw new HttpException(422, 'Enter a message between 10 and 5000 characters.');
        }
        if (!in_array($category, ['general', 'integration', 'payment', 'account', 'security', 'billing'], true)) {
            $category = 'general';
        }
        if (!in_array($priority, ['low', 'normal', 'high', 'urgent'], true)) {
            $priority = 'normal';
        }
        $ticket = SupportModel::create(null, null, $subject, $category, $priority, $message, $name, $email);
        Flash::success('Ticket ' . $ticket['number'] . ' created. Keep this number for reference.');
        Response::redirect(url('/contact'));
    } catch (HttpException $e) {
        Flash::error($e->getMessage());
        remember_old([
            'name' => Request::str('name'),
            'email' => Request::str('email'),
            'subject' => Request::str('subject'),
            'category' => Request::str('category'),
            'priority' => Request::str('priority'),
            'message' => (string) Request::input('message', ''),
        ]);
        Response::redirect(url('/contact'));
    } catch (Throwable $e) {
        Flash::error('We could not create the ticket. Please email support directly.');
        Response::redirect(url('/contact'));
    }
});

// Dynamic payment link URL: /pay/{slug}
if (preg_match('#^/pay/([A-Za-z0-9\-]{3,64})$#', $path, $match)) {
    PaymentLinkController::show($match[1]);
    exit;
}

Router::dispatch();
