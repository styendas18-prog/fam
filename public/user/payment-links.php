<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/bootstrap.php';

use App\Core\Audit;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\HttpException;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Http\UserPage;
use App\Models\PaymentLinkModel;

[$user, $merchant] = UserPage::boot();
$merchantId = (int) $merchant['id'];

if (Request::isPost()) {
    try {
        Csrf::validateOrFail();
        $action = Request::str('action', 'create');
        if ($action === 'create') {
            RateLimiter::enforce('create-link:' . $merchantId, 20, 3600, 'Too many links created. Try again later.');
            $title = Request::str('title');
            if (mb_strlen($title) < 3 || mb_strlen($title) > 160) {
                throw new HttpException(422, 'Enter a title between 3 and 160 characters.');
            }
            $amountRaw = Request::str('amount');
            $amount = $amountRaw === '' ? null : (float) $amountRaw;
            $min = (float) (Request::str('min_amount') ?: 1);
            $max = (float) (Request::str('max_amount') ?: 100000);
            if ($amount !== null && ($amount < 1 || $amount > 100000)) {
                throw new HttpException(422, 'Fixed amount must be between ₹1 and ₹100,000.');
            }
            if ($min < 1 || $max > 100000 || $min > $max) {
                throw new HttpException(422, 'Check the minimum and maximum amount range.');
            }
            $expiryDays = Request::int('expiry_days', 30);
            if ($expiryDays < 1 || $expiryDays > 365) {
                throw new HttpException(422, 'Expiry must be between 1 and 365 days.');
            }
            $redirect = Request::str('success_redirect_url');
            if ($redirect !== '' && (!filter_var($redirect, FILTER_VALIDATE_URL) || !str_starts_with(strtolower($redirect), 'https://'))) {
                throw new HttpException(422, 'Success redirect must be a valid HTTPS URL.');
            }
            $id = PaymentLinkModel::create($merchantId, (int) $user['id'], [
                'title' => $title,
                'description' => Request::str('description') ?: null,
                'amount' => $amount,
                'min_amount' => $min,
                'max_amount' => $max,
                'customer_reference' => Request::str('customer_reference') ?: null,
                'success_redirect_url' => $redirect ?: null,
                'expires_at' => gmdate('Y-m-d H:i:s', time() + $expiryDays * 86400),
            ]);
            Audit::security('payment_link_created', 'info', ['link_id' => $id, 'title' => $title], (int) $user['id'], null);
            Flash::success('Payment link created. Share the URL or copy it into a button.');
            Response::redirect(url('/user/payment-links.php?highlight=' . $id));
        }

        $id = Request::int('id');
        $link = $id > 0 ? PaymentLinkModel::find($id, $merchantId) : null;
        if (!$link) {
            throw new HttpException(404, 'Payment link not found.');
        }
        if ($action === 'toggle') {
            $next = $link['status'] === 'active' ? 'inactive' : 'active';
            PaymentLinkModel::setStatus($id, $merchantId, $next);
            Audit::security('payment_link_status', 'info', ['link_id' => $id, 'status' => $next], (int) $user['id'], null);
            Flash::success('Payment link is now ' . $next . '.');
            Response::redirect(url('/user/payment-links.php'));
        }
        if ($action === 'delete') {
            PaymentLinkModel::delete($id, $merchantId);
            Audit::security('payment_link_deleted', 'warning', ['link_id' => $id], (int) $user['id'], null);
            Flash::success('Payment link deleted. Existing orders remain intact.');
            Response::redirect(url('/user/payment-links.php'));
        }
        throw new HttpException(422, 'Unknown action.');
    } catch (HttpException $e) {
        Flash::error($e->getMessage());
        Response::redirect(url('/user/payment-links.php'));
    } catch (Throwable $e) {
        Flash::error($e->getMessage());
        Response::redirect(url('/user/payment-links.php'));
    }
}

$filters = ['status' => Request::str('status'), 'q' => Request::str('q')];
$page = max(1, Request::int('page', 1));
$result = PaymentLinkModel::listForMerchant($merchantId, $filters, $page, 15);

View::render('user.payment-links', [
    'title' => 'Payment links',
    'active' => 'links',
    'pageTitle' => 'Payment links',
    'pageSubtitle' => 'Shareable payment pages backed by real provider orders',
    'merchant' => $merchant,
    'result' => $result,
    'filters' => $filters,
    'highlight' => Request::int('highlight'),
], 'dashboard');
