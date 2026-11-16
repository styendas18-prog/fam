<?php
declare(strict_types=1);

namespace App\Http;

use App\Core\Auth;
use App\Core\Request;

final class AdminPage
{
    public static function boot(?string $permission = null): array
    {
        return Auth::requireAdmin($permission);
    }

    public static function page(): int
    {
        return max(1, Request::int('page', 1));
    }
}
