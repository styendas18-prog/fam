<?php
declare(strict_types=1);

namespace App\Core;

final class Logger
{
    public static function log(string $level, string $message, array $context = []): void
    {
        $dir = BASE_PATH . '/storage/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $line = json_encode([
            'ts' => gmdate('c'),
            'level' => $level,
            'message' => $message,
            'context' => self::redact($context),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        @file_put_contents($dir . '/app-' . gmdate('Y-m-d') . '.log', $line . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    public static function error(string $message, array $context = []): void
    {
        self::log('error', $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::log('warning', $message, $context);
    }

    public static function info(string $message, array $context = []): void
    {
        self::log('info', $message, $context);
    }

    public static function redact(array $data): array
    {
        $sensitive = ['password', 'api_key', 'apikey', 'secret', 'token', 'authorization', 'signature', 'totp', 'cvv', 'card', 'mail_password'];
        foreach ($data as $key => $value) {
            $lower = strtolower((string) $key);
            foreach ($sensitive as $needle) {
                if (str_contains($lower, $needle)) {
                    $data[$key] = '[REDACTED]';
                    continue 2;
                }
            }
            if (is_array($value)) {
                $data[$key] = self::redact($value);
            } elseif (is_string($value) && strlen($value) > 4000) {
                $data[$key] = substr($value, 0, 4000) . '…';
            }
        }
        return $data;
    }
}
