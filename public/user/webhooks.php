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
use App\Core\Security;
use App\Core\View;
use App\Http\UserPage;
use App\Models\WebhookModel;
use App\Services\WebhookDispatcher;
use Throwable;

[$user, $merchant] = UserPage::boot();
$merchantId = (int) $merchant['id'];

$validateUrl = static function (string $url): string {
    if (!filter_var($url, FILTER_VALIDATE_URL) || strlen($url) > 500) {
        throw new HttpException(422, 'Enter a valid webhook endpoint URL.');
    }
    if (!str_starts_with(strtolower($url), 'https://')) {
        throw new HttpException(422, 'Webhook endpoints must use HTTPS.');
    }
    Security::safeOutboundUrl($url);
    return $url;
};

if (Request::isPost()) {
    try {
        Csrf::validateOrFail();
        $action = Request::str('action', 'create');
        if ($action === 'create') {
            RateLimiter::enforce('webhook-create:' . $merchantId, 15, 3600, 'Too many webhook endpoints created.');
            $url = $validateUrl(Request::str('url'));
            $events = array_values(array_intersect(WebhookModel::EVENTS, array_map('strval', (array) Request::input('events', []))));
            if (!$events) {
                throw new HttpException(422, 'Select at least one event.');
            }
            $created = WebhookModel::create($merchantId, $url, $events);
            $_SESSION['_new_webhook_secret'] = ['id' => $created['id'], 'secret' => $created['secret'], 'url' => $url];
            Audit::security('webhook_created', 'warning', ['webhook_id' => $created['id'], 'url' => Security::redactUrl($url), 'events' => $events], (int) $user['id'], null);
            Flash::success('Webhook endpoint added. Copy the signing secret now.');
            Response::redirect(url('/user/webhooks.php'));
        }

        $id = Request::int('id');
        $hook = $id > 0 ? WebhookModel::find($id, $merchantId) : null;
        if (!$hook) {
            throw new HttpException(404, 'Webhook endpoint not found.');
        }
        if ($action === 'update') {
            $url = $validateUrl(Request::str('url'));
            $events = array_values(array_intersect(WebhookModel::EVENTS, array_map('strval', (array) Request::input('events', []))));
            if (!$events) {
                throw new HttpException(422, 'Select at least one event.');
            }
            WebhookModel::update($id, $merchantId, $url, $events, (int) $hook['is_active'] === 1);
            Audit::security('webhook_updated', 'info', ['webhook_id' => $id], (int) $user['id'], null);
            Flash::success('Webhook endpoint updated.');
        } elseif ($action === 'toggle') {
            $active = (int) $hook['is_active'] !== 1;
            WebhookModel::update($id, $merchantId, (string) $hook['url'], $hook['events_array'], $active);
            Audit::security('webhook_toggled', 'info', ['webhook_id' => $id, 'active' => $active], (int) $user['id'], null);
            Flash::success('Webhook endpoint is now ' . ($active ? 'active' : 'paused') . '.');
        } elseif ($action === 'rotate') {
            $secret = WebhookModel::rotate($id, $merchantId);
            $_SESSION['_new_webhook_secret'] = ['id' => $id, 'secret' => $secret, 'url' => $hook['url']];
            Audit::security('webhook_secret_rotated', 'warning', ['webhook_id' => $id], (int) $user['id'], null);
            Flash::success('Signing secret rotated. Update your endpoint verifier now.');
        } elseif ($action === 'delete') {
            WebhookModel::delete($id, $merchantId);
            Audit::security('webhook_deleted', 'warning', ['webhook_id' => $id], (int) $user['id'], null);
            Flash::success('Webhook endpoint deleted. Queued deliveries were removed.');
        } elseif ($action === 'test') {
            RateLimiter::enforce('webhook-test:' . $merchantId, 10, 300, 'Too many test deliveries.');
            $result = WebhookDispatcher::sendTest($hook);
            Audit::security('webhook_test_sent', 'info', ['webhook_id' => $id, 'success' => $result['success'], 'status' => $result['status']], (int) $user['id'], null);
            if ($result['success']) {
                Flash::success('Test delivered — endpoint returned HTTP ' . (int) $result['status'] . '.');
            } else {
                Flash::error('Test delivery failed: ' . $result['message']);
            }
        } else {
            throw new HttpException(422, 'Unknown action.');
        }
        Response::redirect(url('/user/webhooks.php'));
    } catch (HttpException $e) {
        Flash::error($e->getMessage());
    } catch (Throwable $e) {
        Flash::error('The webhook operation could not be completed.');
    }
    Response::redirect(url('/user/webhooks.php'));
}

$newSecret = $_SESSION['_new_webhook_secret'] ?? null;
unset($_SESSION['_new_webhook_secret']);
$hooks = WebhookModel::listForMerchant($merchantId);
$deliveryFilters = ['status' => Request::str('delivery_status'), 'event' => Request::str('delivery_event')];
$deliveries = WebhookModel::deliveries($merchantId, $deliveryFilters, max(1, Request::int('page', 1)), 15);

View::render('user.webhooks', [
    'title' => 'Webhooks',
    'active' => 'webhooks',
    'pageTitle' => 'Webhooks',
    'pageSubtitle' => 'Signed events with an honest retry queue',
    'merchant' => $merchant,
    'hooks' => $hooks,
    'deliveries' => $deliveries,
    'deliveryFilters' => $deliveryFilters,
    'newSecret' => is_array($newSecret) ? $newSecret : null,
    'events' => WebhookModel::EVENTS,
], 'dashboard');
