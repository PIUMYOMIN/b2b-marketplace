<?php

namespace App\Support;

use App\Models\Product;
use App\Models\User;

class ProductAccess
{
    public static function canCreate(?User $user): bool
    {
        return $user !== null
            && $user->hasRole('seller')
            && ! $user->hasRole('buyer')
            && ! $user->hasRole('admin');
    }

    public static function canManageCatalog(?User $user): bool
    {
        return $user !== null
            && ($user->hasRole('admin')
                || ($user->hasRole('seller') && ! $user->hasRole('buyer')));
    }

    public static function canManage(?User $user, ?Product $product): bool
    {
        if (! $product || ! self::canManageCatalog($user)) {
            return false;
        }

        if ($user->hasRole('admin')) {
            return true;
        }

        return $user->hasRole('seller')
            && ! $user->hasRole('buyer')
            && (int) $product->seller_id === (int) $user->id;
    }
}
