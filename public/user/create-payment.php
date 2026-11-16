<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/bootstrap.php';

use App\Core\Csrf;
use App\Core\Flash;
use App\Core\HttpException;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Core\View;
use App\Http\UserPage;
use App\Models\ProviderModel;
use App\Services\PaymentService;

[$user, $merchant] = UserPage::boot();
$providers = ProviderModel::merchantStatus((int) $merchant['id']);
$configured = false;
foreach ($providers as $providerRow) {
    if (!empty($providerRow['key_last4'])) {
        $configured = true;
    }
}

if (Request::isPost()) {
    try {
        Csrf::validateOrFail();
        RateLimiter::enforce('create-payment:' . $merchant['id'], 30, 60, 'Too many payment attempts. Wait a minute.');
        $amount = Validator::amount(Request::input('amount'));
        if ($amount === null) {
            throw new HttpException(422, 'Enter a valid amount.');
        }
        $redirectUrl = Request::str('success_redirect_url');
        if ($redirectUrl !== '' && !filter_var($redirectUrl, FILTER_VALIDATE_URL)) {
            throw new HttpException(422, 'The success redirect URL is not valid.');
        }
        if ($redirectUrl !== '' && !str_starts_with(strtolower($redirectUrl), 'https://') && !str_starts_with(strtolower($redirectUrl), 'http://localhost')) {
            throw new HttpException(422, 'Success redirects must use HTTPS.');
        }
        $result = PaymentService::create([
            'merchant_id' => (int) $merchant['id'],
            'amount' => $amount,
            'customer_name' => Request::str('customer_name') ?: null,
            'customer_email' => Request::str('customer_email') ?: null,
            'customer_phone' => Request::str('customer_phone') ?: null,
            'customer_reference' => Request::str('customer_reference') ?: null,
            'description' => Request::str('description') ?: null,
            'success_redirect_url' => $redirectUrl ?: null,
            'source' => 'dashboard',
        ]);
        Flash::success('Payment order ' . $result['order']['public_id'] . ' created. Waiting for provider verification.');
        Response::redirect((string) $result['order']['checkout_url']);
    } catch (HttpException $e) {
        Flash::error($e->getMessage());
        remember_old([
            'amount' => Request::str('amount'),
            'customer_name' => Request::str('customer_name'),
            'customer_email' => Request::str('customer_email'),
            'customer_phone' => Request::str('customer_phone'),
            'customer_reference' => Request::str('customer_reference'),
            'description' => Request::str('description'),
            'success_redirect_url' => Request::str('success_redirect_url'),
        ]);
        Response::redirect(url('/user/create-payment.php'));
    } catch (Throwable $e) {
        Flash::error($e->getMessage());
        Response::redirect(url('/user/create-payment.php'));
    }
}

View::render('user.create-payment', [
    'title' => 'Create payment',
    'active' => 'create',
    'pageTitle' => 'Create payment',
    'pageSubtitle' => 'A genuine provider order — never a frontend success flag',
    'merchant' => $merchant,
    'providers' => $providers,
    'configured' => $configured,
], 'dashboard');
