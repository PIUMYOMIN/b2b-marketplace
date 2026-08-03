<?php

namespace App\Support;

use App\Models\User;

class MarketingPushThrottle
{
    /** @var array<int, string> */
    private const MARKETING_TYPES = [
        'wishlist_reminder',
        'abandoned_cart',
    ];

    public static function userReceivedMarketingPushRecently(User $user, int $hours = 24): bool
    {
        return $user->notifications()
            ->where('created_at', '>=', now()->subHours($hours))
            ->where(function ($query) {
                foreach (self::MARKETING_TYPES as $type) {
                    $query->orWhere('data->type', $type);
                }
            })
            ->exists();
    }
}
