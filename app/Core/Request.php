<?php
declare(strict_types=1);

namespace App\Core;

final class Request
{
    public static function method(): string
    {
        return strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    }

    public static function isPost(): bool
    {
        return self::method() === 'POST';
    }

    public static function path(): string
    {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $script = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
        $base = rtrim(str_replace('\\', '/', dirname($script)), '/');
        if ($base !== '' && $base !== '/' && str_starts_with($path, $base)) {
            $path = substr($path, strlen($base)) ?: '/';
        }
        return '/' . ltrim($path, '/');
    }

    public static function input(string $key, mixed $default = null): mixed
    {
        return $_POST[$key] ?? $_GET[$key] ?? $default;
    }

    public static function str(string $key, string $default = ''): string
    {
        $value = self::input($key, $default);
        return is_string($value) ? trim($value) : $default;
    }

    public static function int(string $key, int $default = 0): int
    {
        $value = self::input($key, $default);
        return is_numeric($value) ? (int) $value : $default;
    }

    public static function bool(string $key): bool
    {
        return in_array(strtolower((string) self::input($key, '')), ['1', 'true', 'yes', 'on'], true);
    }

    public static function query(string $key, mixed $default = null): mixed
    {
        return $_GET[$key] ?? $default;
    }

    public static function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        $value = $_SERVER[$key] ?? null;
        if ($value === null && strcasecmp($name, 'Content-Type') === 0) {
            $value = $_SERVER['CONTENT_TYPE'] ?? null;
        }
        return is_string($value) ? $value : null;
    }

    public static function bearerToken(): ?string
    {
        $header = self::header('Authorization');
        if ($header && preg_match('/^Bearer\s+(\S+)$/i', $header, $m)) {
            return $m[1];
        }
        $alt = self::header('X-Api-Key');
        return $alt !== null && $alt !== '' ? trim($alt) : null;
    }

    public static function json(): array
    {
        $raw = file_get_contents('php://input');
        if ($raw === false || trim($raw) === '') {
            return [];
        }
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            throw new HttpException(400, 'Request body must be valid JSON.');
        }
        return $data;
    }

    public static function rawBody(): string
    {
        $raw = file_get_contents('php://input');
        return $raw === false ? '' : $raw;
    }

    public static function ip(): string
    {
        return Security::clientIp();
    }
}
