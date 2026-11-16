<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/bootstrap.php';

use App\Core\Audit;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Http\AdminPage;
use App\Models\WebhookModel;
use App\Services\WebhookDispatcher;

$admin = AdminPage::boot('webhooks.view');

if (Request::isPost()) {
    try {
        AdminPage::boot('webhooks.retry');
        Csrf::validateOrFail();

        $action = Request::str('action');
        $id = Request::int('id');

        if ($action === 'retry') {
            $res = WebhookDispatcher::deliver($id);
            Audit::admin((int) $admin['id'], 'admin_webhook_retried', 'webhook_delivery', $id, $res);
            if (!empty($res['success'])) {
                Flash::success("Webhook delivery #{$id} succeeded (HTTP {$res['status']}).");
            } else {
                Flash::error("Webhook delivery #{$id} failed: {$res['message']}");
            }
        }
    } catch (HttpException $e) {
        Flash::error($e->getMessage());
    } catch (\Throwable $e) {
        Flash::error('Webhook retry failed: ' . $e->getMessage());
    }
    Response::redirect(url('/admin/webhooks.php'));
}

$filters = [
    'q' => Request::str('q'),
    'status' => Request::str('status'),
    'event' => Request::str('event'),
];

$page = AdminPage::page();
$deliveries = WebhookModel::allDeliveries($filters, $page, 30);
$endpoints = WebhookModel::listAll([], 1, 15);

View::render('admin.webhooks', [
    'title' => 'Webhooks',
    'active' => 'webhooks',
    'pageTitle' => 'Webhook deliveries',
    'pageSubtitle' => 'Merchant event listeners, HMAC signature logs and retry queue',
    'deliveries' => $deliveries,
    'endpoints' => $endpoints,
    'filters' => $filters,
], 'admin');
