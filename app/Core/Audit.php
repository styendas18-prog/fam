<?php
declare(strict_types=1);

namespace App\Core;

use Throwable;

final class Audit
{
    public static function security(string $event, string $severity, array $details = [], ?int $userId = null, ?int $adminId = null): void
    {
        try {
            Database::execute(
                'INSERT INTO security_logs (user_id, admin_id, event, severity, ip, user_agent, details, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())',
                [$userId, $adminId, $event, $severity, Security::clientIp(), Security::userAgent(), json_encode(Logger::redact($details), JSON_UNESCAPED_SLASHES)]
            );
        } catch (Throwable $e) {
            Logger::warning('Security audit write failed', ['event' => $event, 'error' => $e->getMessage()]);
        }
        Logger::log($severity === 'info' ? 'info' : 'warning', 'security.' . $event, $details);
    }

    public static function admin(?int $adminId, string $action, ?string $targetType = null, ?int $targetId = null, array $details = []): void
    {
        try {
            Database::execute(
                'INSERT INTO admin_activity_logs (admin_id, action, target_type, target_id, details, ip, user_agent, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())',
                [$adminId ?: null, $action, $targetType, $targetId, json_encode(Logger::redact($details), JSON_UNESCAPED_SLASHES), Security::clientIp(), Security::userAgent()]
            );
        } catch (Throwable $e) {
            Logger::warning('Admin audit write failed', ['action' => $action, 'error' => $e->getMessage()]);
        }
        Logger::info('admin.' . $action, ['admin_id' => $adminId, 'target' => $targetType . ':' . (string) $targetId] + $details);
    }

    public static function api(?int $merchantId, ?int $apiKeyId, string $requestId, string $method, string $path, int $status, int $latency, ?string $ip, ?string $ua, array $request, array $response, ?string $error = null): void
    {
        try {
            Database::execute(
                'INSERT INTO api_logs (merchant_id, api_key_id, request_id, method, path, status_code, ip, user_agent, request_body, response_body, latency_ms, error, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())',
                [
                    $merchantId,
                    $apiKeyId,
                    $requestId,
                    $method,
                    mb_substr($path, 0, 255),
                    $status,
                    $ip,
                    $ua,
                    json_encode(Logger::redact($request), JSON_UNESCAPED_SLASHES),
                    json_encode(Logger::redact($response), JSON_UNESCAPED_SLASHES),
                    $latency,
                    $error,
                ]
            );
        } catch (Throwable $e) {
            Logger::warning('API log write failed', ['error' => $e->getMessage()]);
        }
    }
}
