<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/bootstrap.php';

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Flash;
use App\Core\HttpException;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Http\AdminPage;
use App\Services\PlanService;

$admin = AdminPage::boot('plans.view');
$canManage = Auth::adminCan($admin, 'plans.manage');

function planInput(): array
{
    $name = trim(Request::str('name', ''));
    if ($name === '' || mb_strlen($name) < 2 || mb_strlen($name) > 120) {
        throw new HttpException(422, 'Plan name must be between 2 and 120 characters.');
    }
    $price = round((float) Request::input('price', 0), 2);
    if ($price < 0 || $price > 10_000_000) {
        throw new HttpException(422, 'Enter a valid plan price.');
    }
    $interval = Request::str('billing_interval', 'monthly');
    if (!in_array($interval, ['monthly', 'yearly', 'one_time'], true)) {
        throw new HttpException(422, 'Choose a valid billing interval.');
    }
    $limit = static function (string $key) {
        $value = Request::int($key, 0);
        if ($value < 0 || $value > 100_000_000) {
            throw new HttpException(422, 'Limits must be zero (unlimited) or a positive number.');
        }
        return $value;
    };
    return [
        'name' => $name,
        'description' => Request::str('description', ''),
        'price' => $price,
        'currency' => 'INR',
        'billing_interval' => $interval,
        'transaction_limit' => $limit('transaction_limit'),
        'api_limit' => $limit('api_limit'),
        'link_limit' => $limit('link_limit'),
        'features' => trim((string) Request::input('features', '')),
        'is_active' => Request::int('is_active', 0) === 1 ? 1 : 0,
        'is_featured' => Request::int('is_featured', 0) === 1 ? 1 : 0,
        'sort_order' => max(0, min(10000, Request::int('sort_order', 0))),
    ];
}

function uniqueSlug(string $name, ?int $ignoreId = null): string
{
    $base = PlanService::slugify($name);
    $slug = $base;
    for ($i = 0; $i < 25; $i++) {
        $existing = Database::fetch('SELECT id FROM plans WHERE slug = ? LIMIT 1', [$slug]);
        if (!$existing || ($ignoreId !== null && (int) $existing['id'] === $ignoreId)) {
            return $slug;
        }
        $slug = substr($base, 0, 120) . '-' . strtolower(bin2hex(random_bytes(2)));
    }
    return $base . '-' . time();
}

if (Request::isPost()) {
    try {
        Csrf::validateOrFail();
        if (!$canManage) {
            throw new HttpException(403, 'You do not have permission to manage plans.');
        }
        $action = Request::str('action', '');

        if ($action === 'create' || $action === 'update') {
            RateLimiter::enforce('admin-plan-save:' . (int) $admin['id'], 30, 3600, 'Too many plan updates.');
            $data = planInput();
            if ($action === 'create') {
                $slug = uniqueSlug($data['name']);
                Database::insert(
                    'INSERT INTO plans (name, slug, description, price, currency, billing_interval,
                        transaction_limit, api_limit, link_limit, features, is_active, is_featured, sort_order, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())',
                    [
                        $data['name'], $slug, $data['description'] ?: null, $data['price'], $data['currency'],
                        $data['billing_interval'], $data['transaction_limit'], $data['api_limit'], $data['link_limit'],
                        $data['features'] ?: null, $data['is_active'], $data['is_featured'], $data['sort_order'],
                    ]
                );
                Audit::admin((int) $admin['id'], 'plan_created', 'plan', null, ['name' => $data['name'], 'slug' => $slug]);
                Flash::success('Plan created.');
            } else {
                $id = Request::int('id');
                $existing = PlanService::find($id);
                if (!$existing) {
                    throw new HttpException(404, 'Plan not found.');
                }
                $slug = uniqueSlug($data['name'], $id);
                Database::execute(
                    'UPDATE plans SET name = ?, slug = ?, description = ?, price = ?, currency = ?, billing_interval = ?,
                        transaction_limit = ?, api_limit = ?, link_limit = ?, features = ?, is_active = ?, is_featured = ?,
                        sort_order = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?',
                    [
                        $data['name'], $slug, $data['description'] ?: null, $data['price'], $data['currency'],
                        $data['billing_interval'], $data['transaction_limit'], $data['api_limit'], $data['link_limit'],
                        $data['features'] ?: null, $data['is_active'], $data['is_featured'], $data['sort_order'], $id,
                    ]
                );
                Audit::admin((int) $admin['id'], 'plan_updated', 'plan', $id, ['name' => $data['name']]);
                Flash::success('Plan updated.');
            }
        } elseif ($action === 'toggle') {
            $id = Request::int('id');
            $plan = PlanService::find($id);
            if (!$plan) {
                throw new HttpException(404, 'Plan not found.');
            }
            Database::execute('UPDATE plans SET is_active = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?', [(int) $plan['is_active'] === 1 ? 0 : 1, $id]);
            Audit::admin((int) $admin['id'], 'plan_toggled', 'plan', $id, ['active' => (int) $plan['is_active'] === 1 ? 0 : 1]);
            Flash::success('Plan status updated.');
        } elseif ($action === 'delete') {
            $id = Request::int('id');
            $plan = PlanService::find($id);
            if (!$plan) {
                throw new HttpException(404, 'Plan not found.');
            }
            $assigned = (int) Database::scalar('SELECT COUNT(*) FROM merchants WHERE plan_id = ?', [$id]);
            if ($assigned > 0) {
                throw new HttpException(409, 'This plan is assigned to ' . $assigned . ' merchant(s). Move them to another plan before deleting it.');
            }
            Database::execute('DELETE FROM plan_requests WHERE plan_id = ?', [$id]);
            Database::execute('DELETE FROM plans WHERE id = ?', [$id]);
            Audit::admin((int) $admin['id'], 'plan_deleted', 'plan', $id, ['name' => $plan['name']]);
            Flash::success('Plan deleted.');
        } elseif ($action === 'approve_request' || $action === 'reject_request') {
            $requestId = Request::int('request_id');
            $note = Request::str('admin_note', '');
            $ok = $action === 'approve_request'
                ? PlanService::approveRequest($requestId, (int) $admin['id'], $note)
                : PlanService::rejectRequest($requestId, (int) $admin['id'], $note);
            if (!$ok) {
                throw new HttpException(404, 'Pending plan request not found.');
            }
            Audit::admin((int) $admin['id'], $action === 'approve_request' ? 'plan_request_approved' : 'plan_request_rejected', 'plan_request', $requestId, ['note' => mb_substr($note, 0, 200)]);
            Flash::success($action === 'approve_request' ? 'Plan request approved and assigned.' : 'Plan request rejected.');
        } else {
            throw new HttpException(422, 'Unknown plan action.');
        }
    } catch (HttpException $e) {
        Flash::error($e->getMessage());
    } catch (Throwable $e) {
        Flash::error('Plan operation failed: ' . $e->getMessage());
    }
    Response::redirect(url('/admin/plans.php' . (Request::str('action') === 'update' ? '?edit=' . Request::int('id') : '')));
}

$editId = Request::int('edit');
$editing = $editId > 0 ? PlanService::find($editId) : null;
$plans = PlanService::allPlans();
$pendingRequests = PlanService::pendingRequests();
$recentRequests = PlanService::recentRequests(20);
$stats = [
    'plans' => count($plans),
    'active' => count(array_filter($plans, static fn (array $p): bool => (int) $p['is_active'] === 1)),
    'assigned' => (int) Database::scalar('SELECT COUNT(*) FROM merchants WHERE plan_id IS NOT NULL'),
    'pending' => count($pendingRequests),
];

View::render('admin.plans', [
    'title' => 'Plans',
    'active' => 'plans',
    'pageTitle' => 'Subscription plans',
    'pageSubtitle' => 'Create plans, set usage limits, and approve merchant plan requests',
    'plans' => $plans,
    'editing' => $editing,
    'pendingRequests' => $pendingRequests,
    'recentRequests' => $recentRequests,
    'stats' => $stats,
    'canManage' => $canManage,
], 'admin');
