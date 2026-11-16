<?php
declare(strict_types=1);

namespace App\Core;

use Throwable;

final class Settings
{
    private static ?array $cache = null;

    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        try {
            $rows = Database::fetchAll('SELECT setting_key, setting_value, is_secret FROM system_settings');
        } catch (Throwable) {
            return self::$cache = [];
        }
        $result = [];
        foreach ($rows as $row) {
            $value = $row['setting_value'];
            if ((int) $row['is_secret'] === 1 && $value !== null && $value !== '') {
                try {
                    $value = Crypto::decrypt((string) $value);
                } catch (Throwable) {
                    $value = '';
                }
            }
            $result[$row['setting_key']] = $value;
        }
        return self::$cache = $result;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $all = self::all();
        return array_key_exists($key, $all) ? $all[$key] : $default;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $value = self::get($key);
        if ($value === null) {
            return $default;
        }
        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
    }

    public static function int(string $key, int $default = 0): int
    {
        $value = self::get($key);
        return is_numeric($value) ? (int) $value : $default;
    }

    public static function set(string $key, ?string $value, bool $secret = false, ?int $adminId = null): void
    {
        $stored = $value;
        if ($secret && $value !== null && $value !== '') {
            $stored = Crypto::encrypt($value);
        }
        Database::execute(
            'INSERT INTO system_settings (setting_key, setting_value, is_secret, updated_by)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), is_secret = VALUES(is_secret), updated_by = VALUES(updated_by)',
            [$key, $stored, $secret ? 1 : 0, $adminId]
        );
        self::$cache = null;
    }

    public static function forget(): void
    {
        self::$cache = null;
    }
}
