<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class MerchantModel
{
    public static function findByUser(int $userId): ?array
    {
        return Database::fetch(
            'SELECT m.*, mp.kyc_status, mp.legal_name, mp.business_type, mp.address_line1, mp.city, mp.state, mp.postal_code, mp.gstin, mp.pan
             FROM merchants m LEFT JOIN merchant_profiles mp ON mp.merchant_id = m.id
             WHERE m.user_id = ? AND m.deleted_at IS NULL LIMIT 1',
            [$userId]
        );
    }

    public static function find(int $id): ?array
    {
        return Database::fetch('SELECT * FROM merchants WHERE id = ? AND deleted_at IS NULL LIMIT 1', [$id]);
    }

    public static function all(): array
    {
        return Database::fetchAll(
            'SELECT m.id, m.business_name, m.merchant_code, m.status, u.email AS owner_email
             FROM merchants m JOIN users u ON u.id = m.user_id
             WHERE m.deleted_at IS NULL ORDER BY m.business_name ASC'
        );
    }

    public static function create(int $userId, string $businessName): int
    {
        $code = 'MFM' . strtoupper(bin2hex(random_bytes(4)));
        return Database::transaction(static function () use ($userId, $businessName, $code): int {
            $id = Database::insert(
                'INSERT INTO merchants (user_id, business_name, merchant_code, status, country, created_at) VALUES (?, ?, ?, "active", "IN", UTC_TIMESTAMP())',
                [$userId, $businessName, $code]
            );
            Database::insert('INSERT INTO merchant_profiles (merchant_id, kyc_status) VALUES (?, "not_submitted")', [$id]);
            return $id;
        });
    }

    public static function updateBusiness(int $merchantId, array $data): void
    {
        Database::execute(
            'UPDATE merchants SET business_name = ?, website = ?, support_email = ?, support_phone = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?',
            [
                $data['business_name'],
                $data['website'] ?: null,
                $data['support_email'] ?: null,
                $data['support_phone'] ?: null,
                $merchantId,
            ]
        );
    }

    public static function updateProfile(int $merchantId, array $data): void
    {
        Database::execute(
            'INSERT INTO merchant_profiles (merchant_id, legal_name, business_type, address_line1, address_line2, city, state, postal_code, gstin, pan)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE legal_name = VALUES(legal_name), business_type = VALUES(business_type),
               address_line1 = VALUES(address_line1), address_line2 = VALUES(address_line2), city = VALUES(city),
               state = VALUES(state), postal_code = VALUES(postal_code), gstin = VALUES(gstin), pan = VALUES(pan),
               updated_at = UTC_TIMESTAMP()',
            [
                $merchantId,
                $data['legal_name'] ?: null,
                $data['business_type'] ?: null,
                $data['address_line1'] ?: null,
                $data['address_line2'] ?: null,
                $data['city'] ?: null,
                $data['state'] ?: null,
                $data['postal_code'] ?: null,
                $data['gstin'] ?: null,
                $data['pan'] ?: null,
            ]
        );
    }

    public static function adminList(array $filters = [], int $page = 1, int $perPage = 25): array
    {
        $where = 'm.deleted_at IS NULL';
        $params = [];
        if (!empty($filters['status'])) {
            $where .= ' AND m.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['kyc'])) {
            $where .= ' AND mp.kyc_status = ?';
            $params[] = $filters['kyc'];
        }
        if (!empty($filters['q'])) {
            $where .= ' AND (m.business_name LIKE ? OR m.merchant_code LIKE ? OR u.email LIKE ? OR u.name LIKE ?)';
            $like = '%' . $filters['q'] . '%';
            array_push($params, $like, $like, $like, $like);
        }
        $offset = max(0, ($page - 1) * $perPage);
        $total = (int) Database::scalar(
            "SELECT COUNT(*) FROM merchants m JOIN users u ON u.id = m.user_id LEFT JOIN merchant_profiles mp ON mp.merchant_id = m.id WHERE {$where}",
            $params
        );
        $rows = Database::fetchAll(
            "SELECT m.*, u.name AS owner_name, u.email AS owner_email, u.last_login_at,
                    mp.kyc_status, mp.legal_name,
                    (SELECT COUNT(*) FROM payment_orders o WHERE o.merchant_id = m.id) AS order_count,
                    (SELECT COUNT(*) FROM transactions t WHERE t.merchant_id = m.id) AS verified_count,
                    (SELECT COALESCE(SUM(t.amount),0) FROM transactions t WHERE t.merchant_id = m.id) AS verified_volume
             FROM merchants m JOIN users u ON u.id = m.user_id LEFT JOIN merchant_profiles mp ON mp.merchant_id = m.id
             WHERE {$where} ORDER BY m.created_at DESC LIMIT {$perPage} OFFSET {$offset}",
            $params
        );
        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage, 'pages' => max(1, (int) ceil($total / $perPage))];
    }

    public static function adminDetail(int $id): ?array
    {
        return Database::fetch(
            'SELECT m.*, u.name AS owner_name, u.email AS owner_email, u.status AS owner_status, u.last_login_at, u.last_login_ip,
                    mp.kyc_status, mp.legal_name, mp.business_type, mp.address_line1, mp.address_line2, mp.city, mp.state,
                    mp.postal_code, mp.gstin, mp.pan, mp.kyc_notes
             FROM merchants m JOIN users u ON u.id = m.user_id LEFT JOIN merchant_profiles mp ON mp.merchant_id = m.id
             WHERE m.id = ? AND m.deleted_at IS NULL LIMIT 1',
            [$id]
        );
    }

    public static function setStatus(int $id, string $status): bool
    {
        if (!in_array($status, ['active', 'pending', 'suspended'], true)) {
            return false;
        }
        return Database::execute('UPDATE merchants SET status = ?, updated_at = UTC_TIMESTAMP() WHERE id = ? AND deleted_at IS NULL', [$status, $id]) > 0;
    }

    public static function setKyc(int $merchantId, string $status, string $notes = ''): void
    {
        if (!in_array($status, ['not_submitted', 'pending', 'verified', 'rejected'], true)) {
            return;
        }
        Database::execute(
            'INSERT INTO merchant_profiles (merchant_id, kyc_status, kyc_notes) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE kyc_status = VALUES(kyc_status), kyc_notes = VALUES(kyc_notes), updated_at = UTC_TIMESTAMP()',
            [$merchantId, $status, mb_substr($notes, 0, 2000)]
        );
    }

    public static function stats(int $merchantId): array
    {
        $totals = Database::fetch(
            'SELECT
                COUNT(*) AS total_orders,
                SUM(status = "success") AS successful_orders,
                SUM(status = "pending") AS pending_orders,
                SUM(status = "failed") AS failed_orders,
                SUM(status = "expired") AS expired_orders,
                COALESCE(SUM(CASE WHEN status = "success" THEN payable_amount ELSE 0 END), 0) AS success_volume
             FROM payment_orders WHERE merchant_id = ?',
            [$merchantId]
        ) ?: [];
        $verified = Database::fetch(
            'SELECT COUNT(*) AS verified_count, COALESCE(SUM(amount), 0) AS verified_volume
             FROM transactions WHERE merchant_id = ?',
            [$merchantId]
        ) ?: [];
        $today = Database::fetch(
            'SELECT COUNT(*) AS today_count, COALESCE(SUM(payable_amount), 0) AS today_volume
             FROM payment_orders WHERE merchant_id = ? AND status = "success" AND paid_at >= UTC_DATE()',
            [$merchantId]
        ) ?: [];
        $api = Database::fetch(
            'SELECT COUNT(*) AS requests_today FROM api_logs WHERE merchant_id = ? AND created_at >= UTC_DATE()',
            [$merchantId]
        ) ?: [];
        $total = (int) ($totals['total_orders'] ?? 0);
        $success = (int) ($totals['successful_orders'] ?? 0);
        return [
            'total_orders' => $total,
            'successful_orders' => $success,
            'pending_orders' => (int) ($totals['pending_orders'] ?? 0),
            'failed_orders' => (int) ($totals['failed_orders'] ?? 0),
            'expired_orders' => (int) ($totals['expired_orders'] ?? 0),
            'success_volume' => (float) ($verified['verified_volume'] ?? 0),
            'verified_count' => (int) ($verified['verified_count'] ?? 0),
            'today_count' => (int) ($today['today_count'] ?? 0),
            'today_volume' => (float) ($today['today_volume'] ?? 0),
            'api_today' => (int) ($api['requests_today'] ?? 0),
            'success_rate' => $total > 0 ? round($success / $total * 100, 1) : 0.0,
        ];
    }

    public static function adminStats(): array
    {
        $users = Database::fetch(
            'SELECT COUNT(*) AS total, SUM(status = "active" AND deleted_at IS NULL) AS active,
                    SUM(created_at >= UTC_DATE()) AS today
             FROM users'
        ) ?: [];
        $merchants = Database::fetch('SELECT COUNT(*) AS total, SUM(status = "active" AND deleted_at IS NULL) AS active FROM merchants') ?: [];
        $orders = Database::fetch(
            'SELECT COUNT(*) AS total, SUM(status = "pending") AS pending, SUM(status = "failed") AS failed,
                    SUM(status = "success") AS success FROM payment_orders'
        ) ?: [];
        $volume = Database::fetch('SELECT COALESCE(SUM(amount), 0) AS total, COALESCE(SUM(amount), 0) AS today FROM transactions WHERE verified_at >= UTC_DATE()') ?: [];
        $allVolume = Database::scalar('SELECT COALESCE(SUM(amount), 0) FROM transactions') ?: 0;
        $api = (int) (Database::scalar('SELECT COUNT(*) FROM api_logs WHERE created_at >= UTC_DATE()') ?: 0);
        $providers = Database::fetchAll('SELECT code, name, is_enabled, connection_status, last_test_at, last_error FROM payment_providers ORDER BY id');
        return [
            'users_total' => (int) ($users['total'] ?? 0),
            'users_active' => (int) ($users['active'] ?? 0),
            'users_today' => (int) ($users['today'] ?? 0),
            'merchants_total' => (int) ($merchants['total'] ?? 0),
            'merchants_active' => (int) ($merchants['active'] ?? 0),
            'orders_total' => (int) ($orders['total'] ?? 0),
            'orders_pending' => (int) ($orders['pending'] ?? 0),
            'orders_failed' => (int) ($orders['failed'] ?? 0),
            'orders_success' => (int) ($orders['success'] ?? 0),
            'volume_total' => (float) $allVolume,
            'volume_today' => (float) ($volume['today'] ?? 0),
            'api_today' => $api,
            'providers' => $providers,
        ];
    }
}
