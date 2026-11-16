<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use InvalidArgumentException;

final class PlanLimitException extends \RuntimeException
{
    public function __construct(public readonly string $feature, string $message)
    {
        parent::__construct($message);
    }
}

/**
 * Subscription plans, usage limits and the merchant request/approval flow.
 *
 * A merchant without an assigned plan has no plan limits (legacy behaviour).
 * A limit of 0 means unlimited. Usage is counted per calendar month (UTC).
 */
final class PlanService
{
    public const FEATURES = [
        'transactions' => 'payment_orders',
        'api' => 'api_logs',
        'links' => 'payment_links',
    ];

    /** @return array<int,array<string,mixed>> */
    public static function activePlans(): array
    {
        return Database::fetchAll('SELECT * FROM plans WHERE is_active = 1 ORDER BY sort_order ASC, price ASC, id ASC');
    }

    /** @return array<int,array<string,mixed>> */
    public static function allPlans(): array
    {
        return Database::fetchAll(
            'SELECT p.*, (SELECT COUNT(*) FROM merchants m WHERE m.plan_id = p.id) AS merchant_count
             FROM plans p ORDER BY p.sort_order ASC, p.price ASC, p.id ASC'
        );
    }

    public static function find(int $id): ?array
    {
        return Database::fetch('SELECT * FROM plans WHERE id = ? LIMIT 1', [$id]);
    }

    public static function findBySlug(string $slug): ?array
    {
        return Database::fetch('SELECT * FROM plans WHERE slug = ? LIMIT 1', [$slug]);
    }

    public static function assignedPlan(int $merchantId): ?array
    {
        $row = Database::fetch(
            'SELECT p.*, m.plan_started_at, m.plan_expires_at, m.plan_status
             FROM merchants m JOIN plans p ON p.id = m.plan_id
             WHERE m.id = ? AND m.deleted_at IS NULL LIMIT 1',
            [$merchantId]
        );
        return $row ?: null;
    }

    public static function planIsUsable(?array $plan): bool
    {
        if (!$plan) {
            return false;
        }
        if (($plan['plan_status'] ?? 'active') !== 'active') {
            return false;
        }
        if (!empty($plan['plan_expires_at']) && strtotime((string) $plan['plan_expires_at'] . ' UTC') < time()) {
            return false;
        }
        return true;
    }

    /** @return array<string,int|null> */
    public static function limits(?array $plan): array
    {
        if (!$plan) {
            return ['transactions' => null, 'api' => null, 'links' => null];
        }
        return [
            'transactions' => ((int) ($plan['transaction_limit'] ?? 0)) > 0 ? (int) $plan['transaction_limit'] : null,
            'api' => ((int) ($plan['api_limit'] ?? 0)) > 0 ? (int) $plan['api_limit'] : null,
            'links' => ((int) ($plan['link_limit'] ?? 0)) > 0 ? (int) $plan['link_limit'] : null,
        ];
    }

    /** @return array{transactions:int,api:int,links:int,period_start:string} */
    public static function usage(int $merchantId): array
    {
        $start = gmdate('Y-m-01 00:00:00');
        return [
            'transactions' => (int) Database::scalar('SELECT COUNT(*) FROM payment_orders WHERE merchant_id = ? AND created_at >= ?', [$merchantId, $start]),
            'api' => (int) Database::scalar('SELECT COUNT(*) FROM api_logs WHERE merchant_id = ? AND created_at >= ?', [$merchantId, $start]),
            'links' => (int) Database::scalar('SELECT COUNT(*) FROM payment_links WHERE merchant_id = ? AND created_at >= ?', [$merchantId, $start]),
            'period_start' => $start,
        ];
    }

    public static function limitForFeature(?array $plan, string $feature): ?int
    {
        $limits = self::limits($plan);
        return $limits[$feature] ?? null;
    }

    public static function assertCan(int $merchantId, string $feature): void
    {
        $labels = ['transactions' => 'payment orders', 'api' => 'API requests', 'links' => 'payment links'];
        $label = $labels[$feature] ?? $feature;

        $plan = self::assignedPlan($merchantId);
        if (!$plan) {
            return; // No plan assigned: no plan-level limit.
        }
        if (!self::planIsUsable($plan)) {
            throw new PlanLimitException($feature, 'Your plan has expired or is inactive. Request a new plan from Account → Plans.');
        }
        $limit = self::limitForFeature($plan, $feature);
        if ($limit === null) {
            return;
        }
        $usage = self::usage($merchantId);
        $used = (int) ($usage[$feature] ?? 0);
        if ($used >= $limit) {
            throw new PlanLimitException($feature, sprintf(
                'Plan limit reached: %d of %d %s used this month on the %s plan. Request a higher plan from Account → Plans.',
                $used,
                $limit,
                $label,
                (string) $plan['name']
            ));
        }
    }

    public static function pendingRequest(int $merchantId): ?array
    {
        $row = Database::fetch(
            "SELECT r.*, p.name AS plan_name, p.price, p.billing_interval
             FROM plan_requests r JOIN plans p ON p.id = r.plan_id
             WHERE r.merchant_id = ? AND r.status = 'pending'
             ORDER BY r.id DESC LIMIT 1",
            [$merchantId]
        );
        return $row ?: null;
    }

    /** @return array<int,array<string,mixed>> */
    public static function requestsForMerchant(int $merchantId, int $limit = 10): array
    {
        $limit = max(1, min(50, $limit));
        return Database::fetchAll(
            "SELECT r.*, p.name AS plan_name, p.price, p.billing_interval
             FROM plan_requests r JOIN plans p ON p.id = r.plan_id
             WHERE r.merchant_id = ? ORDER BY r.id DESC LIMIT {$limit}",
            [$merchantId]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public static function pendingRequests(int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        return Database::fetchAll(
            "SELECT r.*, p.name AS plan_name, p.price, p.billing_interval,
                    m.business_name, m.merchant_code, m.user_id, u.email AS owner_email
             FROM plan_requests r
             JOIN plans p ON p.id = r.plan_id
             JOIN merchants m ON m.id = r.merchant_id
             LEFT JOIN users u ON u.id = m.user_id
             WHERE r.status = 'pending'
             ORDER BY r.requested_at ASC, r.id ASC LIMIT {$limit}"
        );
    }

    /** @return array<int,array<string,mixed>> */
    public static function recentRequests(int $limit = 30): array
    {
        $limit = max(1, min(200, $limit));
        return Database::fetchAll(
            "SELECT r.*, p.name AS plan_name, m.business_name, m.merchant_code, a.name AS reviewer_name
             FROM plan_requests r
             JOIN plans p ON p.id = r.plan_id
             JOIN merchants m ON m.id = r.merchant_id
             LEFT JOIN admins a ON a.id = r.reviewed_by
             ORDER BY r.id DESC LIMIT {$limit}"
        );
    }

    public static function requestPlan(int $merchantId, int $planId, string $note = ''): int
    {
        $plan = self::find($planId);
        if (!$plan || (int) $plan['is_active'] !== 1) {
            throw new InvalidArgumentException('That plan is not available.');
        }
        $current = self::assignedPlan($merchantId);
        if ($current && (int) $current['id'] === $planId) {
            throw new InvalidArgumentException('You are already on this plan.');
        }
        if (self::pendingRequest($merchantId) !== null) {
            throw new InvalidArgumentException('You already have a pending plan request. Cancel it before requesting another plan.');
        }
        return Database::insert(
            "INSERT INTO plan_requests (merchant_id, plan_id, status, merchant_note, requested_at)
             VALUES (?, ?, 'pending', ?, UTC_TIMESTAMP())",
            [$merchantId, $planId, $note !== '' ? mb_substr($note, 0, 500) : null]
        );
    }

    public static function cancelRequest(int $requestId, int $merchantId): bool
    {
        return Database::execute(
            "UPDATE plan_requests SET status = 'cancelled', reviewed_at = UTC_TIMESTAMP()
             WHERE id = ? AND merchant_id = ? AND status = 'pending'",
            [$requestId, $merchantId]
        ) > 0;
    }

    public static function assignPlan(int $merchantId, array $plan): void
    {
        Database::execute(
            'UPDATE merchants SET plan_id = ?, plan_started_at = UTC_TIMESTAMP(), plan_expires_at = ?, plan_status = "active" WHERE id = ?',
            [(int) $plan['id'], self::expiryFor((string) $plan['billing_interval']), $merchantId]
        );
    }

    public static function approveRequest(int $requestId, int $adminId, string $adminNote = ''): bool
    {
        $request = Database::fetch("SELECT * FROM plan_requests WHERE id = ? AND status = 'pending' LIMIT 1", [$requestId]);
        if (!$request) {
            return false;
        }
        $plan = self::find((int) $request['plan_id']);
        if (!$plan) {
            return false;
        }
        Database::transaction(static function () use ($request, $plan, $adminId, $adminNote): void {
            self::assignPlan((int) $request['merchant_id'], $plan);
            Database::execute(
                "UPDATE plan_requests SET status = 'approved', admin_note = ?, reviewed_by = ?, reviewed_at = UTC_TIMESTAMP() WHERE id = ?",
                [$adminNote !== '' ? mb_substr($adminNote, 0, 500) : null, $adminId ?: null, (int) $request['id']]
            );
            $userId = (int) Database::scalar('SELECT user_id FROM merchants WHERE id = ?', [(int) $request['merchant_id']]);
            if ($userId > 0) {
                \App\Models\NotificationModel::create(
                    $userId,
                    'plan_approved',
                    'Plan activated',
                    'Your plan request was approved. ' . $plan['name'] . ' is now active.',
                    '/user/plans.php'
                );
            }
        });
        return true;
    }

    public static function rejectRequest(int $requestId, int $adminId, string $adminNote = ''): bool
    {
        $request = Database::fetch("SELECT * FROM plan_requests WHERE id = ? AND status = 'pending' LIMIT 1", [$requestId]);
        if (!$request) {
            return false;
        }
        $planName = (string) Database::scalar('SELECT name FROM plans WHERE id = ?', [(int) $request['plan_id']]);
        Database::execute(
            "UPDATE plan_requests SET status = 'rejected', admin_note = ?, reviewed_by = ?, reviewed_at = UTC_TIMESTAMP() WHERE id = ?",
            [$adminNote !== '' ? mb_substr($adminNote, 0, 500) : null, $adminId ?: null, (int) $request['id']]
        );
        $userId = (int) Database::scalar('SELECT user_id FROM merchants WHERE id = ?', [(int) $request['merchant_id']]);
        if ($userId > 0) {
            \App\Models\NotificationModel::create(
                $userId,
                'plan_rejected',
                'Plan request declined',
                'Your request for ' . $planName . ' was declined.' . ($adminNote !== '' ? ' Note: ' . $adminNote : ''),
                '/user/plans.php'
            );
        }
        return true;
    }

    public static function expiryFor(string $interval): ?string
    {
        return match ($interval) {
            'monthly' => gmdate('Y-m-d H:i:s', strtotime('+1 month')),
            'yearly' => gmdate('Y-m-d H:i:s', strtotime('+1 year')),
            default => null, // one_time / lifetime
        };
    }

    public static function slugify(string $value): string
    {
        $slug = strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', '-', $value)));
        $slug = trim($slug, '-');
        if ($slug === '') {
            $slug = 'plan-' . strtolower(bin2hex(random_bytes(3)));
        }
        return substr($slug, 0, 130);
    }

    /** @return string[] */
    public static function featureList(?string $features): array
    {
        $lines = preg_split('/\r?\n/', (string) $features) ?: [];
        return array_values(array_filter(array_map('trim', $lines), static fn (string $line): bool => $line !== ''));
    }
}
