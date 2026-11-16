<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Crypto;
use App\Core\Database;
use App\Core\Logger;
use RuntimeException;
use Throwable;

final class ProviderModel
{
    public static function all(): array
    {
        return Database::fetchAll('SELECT * FROM payment_providers ORDER BY name');
    }

    public static function enabled(): array
    {
        return Database::fetchAll('SELECT * FROM payment_providers WHERE is_enabled = 1 ORDER BY name');
    }

    public static function findByCode(string $code): ?array
    {
        return Database::fetch('SELECT * FROM payment_providers WHERE code = ? LIMIT 1', [$code]);
    }

    public static function find(int $id): ?array
    {
        return Database::fetch('SELECT * FROM payment_providers WHERE id = ? LIMIT 1', [$id]);
    }

    public static function credential(int $providerId, ?int $merchantId): ?array
    {
        if ($merchantId !== null) {
            $row = Database::fetch(
                'SELECT * FROM provider_credentials WHERE provider_id = ? AND merchant_id = ? LIMIT 1',
                [$providerId, $merchantId]
            );
            if ($row) {
                return self::hydrate($row);
            }
        }
        $allowDefault = (int) Database::scalar("SELECT setting_value FROM system_settings WHERE setting_key = 'allow_platform_default_credentials'") === 1;
        if (!$allowDefault) {
            return null;
        }
        $row = Database::fetch('SELECT * FROM provider_credentials WHERE provider_id = ? AND merchant_id IS NULL LIMIT 1', [$providerId]);
        return $row ? self::hydrate($row) : null;
    }

    public static function platformCredential(int $providerId): ?array
    {
        $row = Database::fetch('SELECT * FROM provider_credentials WHERE provider_id = ? AND merchant_id IS NULL LIMIT 1', [$providerId]);
        return $row ? self::hydrate($row) : null;
    }

    public static function saveCredential(int $providerId, ?int $merchantId, string $apiKey, ?int $adminId = null): int
    {
        $payload = json_encode(['api_key' => trim($apiKey)], JSON_UNESCAPED_SLASHES);
        $encrypted = Crypto::encrypt($payload);
        $last4 = substr(trim($apiKey), -4);
        $existing = Database::fetch(
            'SELECT id FROM provider_credentials WHERE provider_id = ? AND ' . ($merchantId === null ? 'merchant_id IS NULL' : 'merchant_id = ?'),
            $merchantId === null ? [$providerId] : [$providerId, $merchantId]
        );
        if ($existing) {
            Database::execute(
                'UPDATE provider_credentials SET encrypted_credentials = ?, key_last4 = ?, connection_status = "not_configured",
                 last_test_message = "Credentials replaced — test required", updated_at = UTC_TIMESTAMP() WHERE id = ?',
                [$encrypted, $last4, $existing['id']]
            );
            return (int) $existing['id'];
        }
        return Database::insert(
            'INSERT INTO provider_credentials (provider_id, merchant_id, encrypted_credentials, key_last4, connection_status, created_at)
             VALUES (?, ?, ?, ?, "not_configured", UTC_TIMESTAMP())',
            [$providerId, $merchantId, $encrypted, $last4]
        );
    }

    public static function deleteCredential(int $providerId, ?int $merchantId): void
    {
        if ($merchantId === null) {
            Database::execute('DELETE FROM provider_credentials WHERE provider_id = ? AND merchant_id IS NULL', [$providerId]);
        } else {
            Database::execute('DELETE FROM provider_credentials WHERE provider_id = ? AND merchant_id = ?', [$providerId, $merchantId]);
        }
    }

    public static function setConnectionStatus(int $providerId, ?int $merchantId, string $status, string $message = '', bool $success = false): void
    {
        $message = mb_substr($message, 0, 500);
        if ($merchantId !== null) {
            Database::execute(
                'UPDATE provider_credentials SET connection_status = ?, last_test_at = UTC_TIMESTAMP(), last_test_message = ?, last_success_at = ' .
                ($success ? 'UTC_TIMESTAMP()' : 'last_success_at') . ' WHERE provider_id = ? AND merchant_id = ?',
                [$status, $message, $providerId, $merchantId]
            );
        }
        Database::execute(
            'UPDATE payment_providers SET connection_status = ?, last_test_at = UTC_TIMESTAMP(), last_error = ?, last_success_at = ' .
            ($success ? 'UTC_TIMESTAMP()' : 'last_success_at') . ', updated_at = UTC_TIMESTAMP() WHERE id = ?',
            [$status, $success ? null : $message, $providerId]
        );
    }

    public static function markSuccess(int $providerId): void
    {
        Database::execute('UPDATE payment_providers SET last_success_at = UTC_TIMESTAMP() WHERE id = ?', [$providerId]);
    }

    private static function hydrate(array $row): array
    {
        try {
            $decoded = json_decode(Crypto::decrypt((string) $row['encrypted_credentials']), true);
            if (!is_array($decoded) || empty($decoded['api_key'])) {
                throw new RuntimeException('Credential payload invalid.');
            }
            $row['credentials'] = $decoded;
        } catch (Throwable $e) {
            Logger::error('Provider credential decryption failed', ['credential_id' => $row['id'], 'error' => $e->getMessage()]);
            $row['credentials'] = null;
        }
        return $row;
    }

    public static function merchantStatus(int $merchantId): array
    {
        return Database::fetchAll(
            'SELECT p.*, pc.connection_status AS merchant_connection_status, pc.last_test_at AS merchant_last_test_at,
                    pc.last_test_message AS merchant_last_test_message, pc.key_last4,
                    (SELECT COUNT(*) FROM payment_orders o WHERE o.provider_id = p.id AND o.merchant_id = ?) AS order_count
             FROM payment_providers p
             LEFT JOIN provider_credentials pc ON pc.provider_id = p.id AND pc.merchant_id = ?
             ORDER BY p.name',
            [$merchantId, $merchantId]
        );
    }

    public static function credentialCounts(): array
    {
        return Database::fetchAll(
            'SELECT provider_id, COUNT(*) AS total,
                    SUM(merchant_id IS NOT NULL) AS merchant_scoped,
                    SUM(connection_status = "connected") AS connected
             FROM provider_credentials GROUP BY provider_id'
        );
    }
}
