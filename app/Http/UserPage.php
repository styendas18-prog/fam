<?php
declare(strict_types=1);

namespace App\Http;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;
use App\Models\MerchantModel;

final class UserPage
{
    /** @return array{0:array,1:array} */
    public static function boot(): array
    {
        $user = Auth::requireUser();
        $merchant = MerchantModel::findByUser((int) $user['id']);
        if (!$merchant) {
            $id = MerchantModel::create((int) $user['id'], $user['name'] . ' Store');
            $merchant = MerchantModel::find($id);
        }
        if (!$merchant) {
            throw new HttpException(403, 'A merchant profile is required for this page.');
        }
        return [$user, $merchant];
    }

    public static function page(): int
    {
        return max(1, Request::int('page', 1));
    }

    public static function merchant(): array
    {
        [, $merchant] = self::boot();
        return $merchant;
    }
}
