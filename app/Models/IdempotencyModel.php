<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDOException;
use Throwable;

final class IdempotencyModel
{
    /**
     * Reserve an idempotency key for this merchant/endpoint.
     * @return array{state:string,record:?array}
     *   state = fresh | replay | mismatch | in_progress
     */
    public static function reserve(int $merchantId, string $endpoint, string $key, string $requestHash): array
    {
        $keyHash = hash('sha256', $key);
        $existing = Database::fetch(
            'SELECT * FROM idempotency_keys WHERE merchant_id = ? AND endpoint = ? AND key_hash = ? LIMIT 1',
            [$merchantId, $endpoint, $keyHash]
        );
        if ($existing) {
            return self::classify($existing, $requestHash);
        }
        try {
            Database::insert(
                'INSERT INTO idempotency_keys (merchant_id, endpoint, key_hash, request_hash, status_code, created_at, expires_at)
                 VALUES (?, ?, ?, ?, 0, UTC_TIMESTAMP(), UTC_TIMESTAMP() + INTERVAL 24 HOUR)',
                [$merchantId, $endpoint, $keyHash, $requestHash]
            );
        } catch (PDOException $e) {
            if (!Database::isDuplicate($e)) {
                throw $e;
            }
            $existing = Database::fetch(
                'SELECT * FROM idempotency_keys WHERE merchant_id = ? AND endpoint = ? AND key_hash = ? LIMIT 1',
                [$merchantId, $endpoint, $keyHash]
            );
            if ($existing) {
                return self::classify($existing, $requestHash);
            }
        }
        return ['state' => 'fresh', 'record' => null];
    }

    public static function complete(int $merchantId, string $endpoint, string $key, int $status, string $body): void
    {
        Database::execute(
            'UPDATE idempotency_keys SET status_code = ?, response_body = ? WHERE merchant_id = ? AND endpoint = ? AND key_hash = ?',
            [$status, $body, $merchantId, $endpoint, hash('sha256', $key)]
        );
    }

    public static function cleanup(): int
    {
        return Database::execute('DELETE FROM idempotency_keys WHERE expires_at < UTC_TIMESTAMP()');
    }

    private static function classify(array $record, string $requestHash): array
    {
        if (!hash_equals((string) $record['request_hash'], $requestHash)) {
            return ['state' => 'mismatch', 'record' => $record];
        }
        if ((int) $record['status_code'] === 0) {
            return ['state' => 'in_progress', 'record' => $record];
        }
        return ['state' => 'replay', 'record' => $record];
    }

    public static function safeCleanup(): void
    {
        try {
            self::cleanup();
        } catch (Throwable) {
        }
    }
}
