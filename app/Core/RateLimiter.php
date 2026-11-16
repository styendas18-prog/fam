<?php
declare(strict_types=1);

namespace App\Core;

use Throwable;

final class RateLimiter
{
    /**
     * Returns [allowed, remaining, resetSeconds].
     */
    public static function hit(string $key, int $max, int $windowSeconds): array
    {
        $bucket = hash('sha256', $key) . ':' . intdiv(time(), max(1, $windowSeconds));
        try {
            Database::execute(
                'INSERT INTO rate_limits (bucket, hits, expires_at) VALUES (?, 1, ?)
                 ON DUPLICATE KEY UPDATE hits = hits + 1',
                [$bucket, gmdate('Y-m-d H:i:s', time() + $windowSeconds + 5)]
            );
            $hits = (int) Database::scalar('SELECT hits FROM rate_limits WHERE bucket = ?', [$bucket]);
        } catch (Throwable $e) {
            // Fail open only if the rate-limit store is unavailable; log it.
            Logger::error('Rate limiter unavailable', ['error' => $e->getMessage()]);
            return [true, $max, $windowSeconds];
        }
        $reset = $windowSeconds - (time() % max(1, $windowSeconds));
        return [$hits <= $max, max(0, $max - $hits), $reset];
    }

    public static function check(string $key, int $max, int $windowSeconds): bool
    {
        [$allowed] = self::hit($key, $max, $windowSeconds);
        return $allowed;
    }

    public static function cleanup(): int
    {
        return Database::execute('DELETE FROM rate_limits WHERE expires_at < UTC_TIMESTAMP()');
    }

    public static function enforce(string $key, int $max, int $windowSeconds, string $message = 'Too many requests. Please try again shortly.'): void
    {
        [$allowed, , $reset] = self::hit($key, $max, $windowSeconds);
        if (!$allowed) {
            header('Retry-After: ' . $reset);
            throw new HttpException(429, $message);
        }
    }
}
