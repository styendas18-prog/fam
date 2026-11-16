<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Security;

final class ApiKeyModel
{
    public const SCOPES = [
        'payments:read' => 'Read payments',
        'payments:write' => 'Create payments',
        'links:read' => 'Read payment links',
        'links:write' => 'Create payment links',
        'webhooks:test' => 'Send webhook tests',
        'profile:read' => 'Read merchant profile',
    ];

    public static function create(int $merchantId, string $name, array $scopes, ?int $rotatedFrom = null): array
    {
        $raw = 'mf_live_' . bin2hex(random_bytes(24));
        $prefix = substr($raw, 0, 12);
        $last4 = substr($raw, -4);
        $scopes = array_values(array_intersect(array_keys(self::SCOPES), $scopes));
        if (!$scopes) {
            $scopes = array_keys(self::SCOPES);
        }
        $id = Database::insert(
            'INSERT INTO api_keys (merchant_id, name, key_prefix, key_hash, last4, scopes, status, rotated_from, created_at)
             VALUES (?, ?, ?, ?, ?, ?, "active", ?, UTC_TIMESTAMP())',
            [$merchantId, $name, $prefix, hash('sha256', $raw), $last4, json_encode($scopes), $rotatedFrom]
        );
        return ['id' => $id, 'key' => $raw, 'prefix' => $prefix, 'last4' => $last4, 'scopes' => $scopes];
    }

    public static function findActiveByToken(string $token): ?array
    {
        if (!str_starts_with($token, 'mf_live_') || strlen($token) < 20) {
            return null;
        }
        $key = Database::fetch(
            'SELECT k.*, m.status AS merchant_status, m.business_name, m.merchant_code
             FROM api_keys k JOIN merchants m ON m.id = k.merchant_id
             WHERE k.key_hash = ? LIMIT 1',
            [hash('sha256', $token)]
        );
        if (!$key || $key['status'] !== 'active' || $key['merchant_status'] !== 'active') {
            return null;
        }
        if (!empty($key['expires_at']) && strtotime((string) $key['expires_at'] . ' UTC') < time()) {
            return null;
        }
        Database::execute('UPDATE api_keys SET last_used_at = UTC_TIMESTAMP() WHERE id = ?', [$key['id']]);
        $key['scopes_array'] = json_decode((string) $key['scopes'], true) ?: [];
        return $key;
    }

    public static function listForMerchant(int $merchantId): array
    {
        $rows = Database::fetchAll(
            'SELECT * FROM api_keys WHERE merchant_id = ? ORDER BY CASE status WHEN "active" THEN 0 ELSE 1 END, created_at DESC',
            [$merchantId]
        );
        foreach ($rows as &$row) {
            $row['scopes_array'] = json_decode((string) $row['scopes'], true) ?: [];
        }
        return $rows;
    }

    public static function revealableCount(int $merchantId): int
    {
        return (int) Database::scalar('SELECT COUNT(*) FROM api_keys WHERE merchant_id = ? AND status = "active"', [$merchantId]);
    }

    public static function revoke(int $id, int $merchantId): bool
    {
        return Database::execute(
            'UPDATE api_keys SET status = "revoked", revoked_at = UTC_TIMESTAMP() WHERE id = ? AND merchant_id = ? AND status = "active"',
            [$id, $merchantId]
        ) > 0;
    }

    public static function rotate(int $id, int $merchantId): array
    {
        $old = Database::fetch('SELECT * FROM api_keys WHERE id = ? AND merchant_id = ? AND status = "active" LIMIT 1', [$id, $merchantId]);
        if (!$old) {
            throw new \RuntimeException('Active API key not found.');
        }
        return Database::transaction(static function () use ($old, $merchantId): array {
            Database::execute('UPDATE api_keys SET status = "revoked", revoked_at = UTC_TIMESTAMP() WHERE id = ?', [$old['id']]);
            $scopes = json_decode((string) $old['scopes'], true) ?: [];
            return self::create($merchantId, $old['name'] . ' (rotated)', $scopes, (int) $old['id']);
        });
    }

    public static function listAll(array $filters = [], int $page = 1, int $perPage = 25): array
    {
        $where = 'k.status = "active"';
        $params = [];
        if (!empty($filters['merchant_id'])) {
            $where .= ' AND k.merchant_id = ?';
            $params[] = (int) $filters['merchant_id'];
        }
        if (!empty($filters['q'])) {
            $where .= ' AND (k.name LIKE ? OR k.key_prefix LIKE ? OR m.business_name LIKE ? OR m.merchant_code LIKE ?)';
            $like = '%' . $filters['q'] . '%';
            array_push($params, $like, $like, $like, $like);
        }
        $offset = max(0, ($page - 1) * $perPage);
        $total = (int) Database::scalar("SELECT COUNT(*) FROM api_keys k JOIN merchants m ON m.id = k.merchant_id WHERE {$where}", $params);
        $rows = Database::fetchAll(
            "SELECT k.*, m.business_name, m.merchant_code, m.status AS merchant_status
             FROM api_keys k JOIN merchants m ON m.id = k.merchant_id
             WHERE {$where} ORDER BY k.created_at DESC LIMIT {$perPage} OFFSET {$offset}",
            $params
        );
        foreach ($rows as &$row) {
            $row['scopes_array'] = json_decode((string) $row['scopes'], true) ?: [];
        }
        unset($row);
        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage, 'pages' => max(1, (int) ceil($total / $perPage))];
    }

    public static function revokeAny(int $id): bool
    {
        return Database::execute('UPDATE api_keys SET status = "revoked", revoked_at = UTC_TIMESTAMP() WHERE id = ? AND status = "active"', [$id]) > 0;
    }

    public static function scopesFor(string $scope): bool
    {
        return in_array($scope, array_keys(self::SCOPES), true);
    }
}
