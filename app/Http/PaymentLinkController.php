<?php
declare(strict_types=1);

namespace App\Http;

use App\Core\Csrf;
use App\Core\Flash;
use App\Core\HttpException;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Models\PaymentLinkModel;
use App\Services\PaymentService;
use Throwable;

final class PaymentLinkController
{
    public static function show(string $slug): void
    {
        $link = PaymentLinkModel::findBySlug($slug);
        if (!$link || $link['status'] !== 'active' || $link['merchant_status'] !== 'active') {
            throw new HttpException(404, 'This payment link is unavailable.');
        }
        if (!empty($link['expires_at']) && strtotime((string) $link['expires_at'] . ' UTC') < time()) {
            throw new HttpException(410, 'This payment link has expired.');
        }

        if (Request::isPost()) {
            try {
                Csrf::validateOrFail();
                RateLimiter::enforce('pay-link:' . Request::ip(), 20, 300, 'Too many attempts. Please wait a moment.');
                $amount = $link['amount'] !== null ? (float) $link['amount'] : round((float) Request::input('amount', 0), 2);
                if ($amount <= 0) {
                    throw new HttpException(422, 'Enter a valid amount.');
                }
                if ($amount < (float) $link['min_amount'] || $amount > (float) $link['max_amount']) {
                    throw new HttpException(422, 'Amount must be between ' . money((float) $link['min_amount']) . ' and ' . money((float) $link['max_amount']) . '.');
                }
                $result = PaymentService::create([
                    'merchant_id' => (int) $link['merchant_id'],
                    'amount' => $amount,
                    'link_id' => (int) $link['id'],
                    'customer_name' => Request::str('customer_name') ?: null,
                    'customer_email' => Request::str('customer_email') ?: null,
                    'customer_phone' => Request::str('customer_phone') ?: null,
                    'customer_reference' => $link['customer_reference'] ?: null,
                    'description' => $link['title'],
                    'success_redirect_url' => $link['success_redirect_url'],
                    'source' => 'payment_link',
                ]);
                Response::redirect((string) $result['order']['checkout_url']);
            } catch (HttpException $e) {
                Flash::error($e->getMessage());
                Response::redirect(url('/pay/' . rawurlencode($slug)));
            } catch (Throwable $e) {
                Flash::error($e->getMessage());
                Response::redirect(url('/pay/' . rawurlencode($slug)));
            }
        }

        View::render('public.pay', [
            'title' => $link['title'],
            'link' => $link,
        ], 'checkout');
    }
}
