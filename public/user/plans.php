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
use App\Services\PlanService;

[$user, $merchant] = UserPage::boot();
$merchantId = (int) $merchant['id'];

if (Request::isPost()) {
    try {
        Csrf::validateOrFail();
        RateLimiter::enforce('plan-request:' . $merchantId, 10, 3600, 'Too many plan requests.');
        $action = Request::str('action', '');
        if ($action === 'request') {
            $planId = Request::int('plan_id');
            PlanService::requestPlan($merchantId, $planId, Request::str('note', ''));
            Audit::security('plan_requested', 'info', ['merchant_id' => $merchantId, 'plan_id' => $planId], (int) $user['id'], null);
            Flash::success('Plan request submitted. An administrator will review it.');
        } elseif ($action === 'cancel') {
            $requestId = Request::int('request_id');
            if (!PlanService::cancelRequest($requestId, $merchantId)) {
                throw new HttpException(404, 'Pending plan request not found.');
            }
            Audit::security('plan_request_cancelled', 'info', ['merchant_id' => $merchantId, 'request_id' => $requestId], (int) $user['id'], null);
            Flash::success('Plan request cancelled.');
        } else {
            throw new HttpException(422, 'Unknown plan action.');
        }
    } catch (HttpException $e) {
        Flash::error($e->getMessage());
    } catch (Throwable $e) {
        Flash::error($e->getMessage());
    }
    Response::redirect(url('/user/plans.php'));
}

$currentPlan = PlanService::assignedPlan($merchantId);
$usage = PlanService::usage($merchantId);
$limits = PlanService::limits($currentPlan);
$pending = PlanService::pendingRequest($merchantId);
$plans = PlanService::activePlans();
$history = PlanService::requestsForMerchant($merchantId, 10);

View::render('user.plans', [
    'title' => 'Plans',
    'active' => 'plans',
    'pageTitle' => 'Plans',
    'pageSubtitle' => 'Subscription plan, monthly usage limits and plan requests',
    'merchant' => $merchant,
    'currentPlan' => $currentPlan,
    'usage' => $usage,
    'limits' => $limits,
    'pending' => $pending,
    'plans' => $plans,
    'history' => $history,
], 'dashboard');
