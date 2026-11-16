<?php
declare(strict_types=1);

namespace App\Core;

final class Flash
{
    public static function add(string $type, string $message): void
    {
        $_SESSION['_flash'][$type][] = $message;
    }

    public static function success(string $message): void
    {
        self::add('success', $message);
    }

    public static function error(string $message): void
    {
        self::add('error', $message);
    }

    public static function info(string $message): void
    {
        self::add('info', $message);
    }

    public static function consume(): array
    {
        $flash = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return is_array($flash) ? $flash : [];
    }
}
