<?php

namespace App\Console\Commands;

use App\Models\Cart;
use App\Models\OrderItem;
use App\Models\PushToken;
use App\Models\Wishlist;
use App\Notifications\WishlistReminder;
use App\Support\MarketingPushThrottle;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * php artisan wishlist:send-reminders
 *
 * Sends one mobile push per eligible buyer for stale wishlist items.
 */
class SendWishlistReminders extends Command
{
    protected $signature = 'wishlist:send-reminders
                            {--days=3 : Minimum days since the item was wishlisted}
                            {--cooldown=7 : Minimum days before re-reminding the same item}
                            {--dry-run : Preview recipients without sending}';

    protected $description = 'Send buy-later push reminders for wishlist items that have not been purchased.';

    public function handle(): int
    {
        $delayDays = max(1, (int) $this->option('days'));
        $cooldownDays = max(1, (int) $this->option('cooldown'));
        $dryRun = (bool) $this->option('dry-run');

        $addedBefore = Carbon::now()->subDays($delayDays);
        $remindedBefore = Carbon::now()->subDays($cooldownDays);

        $candidates = Wishlist::query()
            ->with(['user', 'product'])
            ->where('created_at', '<=', $addedBefore)
            ->where(function ($query) use ($remindedBefore) {
                $query->whereNull('last_reminded_at')
                    ->orWhere('last_reminded_at', '<=', $remindedBefore);
            })
            ->whereHas('product', function ($query) {
                $query->where('is_active', true)->where('status', 'approved');
            })
            ->orderBy('created_at')
            ->get();

        if ($candidates->isEmpty()) {
            $this->info('No eligible wishlist reminders found.');
            return self::SUCCESS;
        }

        $sent = 0;
        $skipped = 0;
        $usersNotifiedToday = [];

        foreach ($candidates as $wishlist) {
            $user = $wishlist->user;
            $product = $wishlist->product;

            if ($user === null || $product === null) {
                $skipped++;
                continue;
            }

            if (isset($usersNotifiedToday[$user->id])) {
                $skipped++;
                continue;
            }

            if (!$this->userAllowsWishlistReminder($user)) {
                $skipped++;
                continue;
            }

            if (!$this->userHasPushToken($user->id)) {
                $skipped++;
                continue;
            }

            if (MarketingPushThrottle::userReceivedMarketingPushRecently($user)) {
                $skipped++;
                continue;
            }

            if ($this->userPurchasedProductSinceWishlist($user->id, $product->id, $wishlist->created_at)) {
                $skipped++;
                continue;
            }

            if ($this->userHasProductInCart($user->id, $product->id)) {
                $skipped++;
                continue;
            }

            if ($dryRun) {
                $this->line("Would remind user {$user->id} about product {$product->id} ({$product->name_en})");
                $usersNotifiedToday[$user->id] = true;
                $sent++;
                continue;
            }

            try {
                Notification::send($user, new WishlistReminder($wishlist, $product));
                $wishlist->update(['last_reminded_at' => now()]);
                $usersNotifiedToday[$user->id] = true;
                $sent++;
            } catch (\Throwable $exception) {
                $skipped++;
                Log::error('wishlist:send-reminders failed for wishlist item', [
                    'wishlist_id' => $wishlist->id,
                    'user_id' => $user->id,
                    'product_id' => $product->id,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        $this->info(
            ($dryRun ? '[DRY RUN] ' : '')
            . "Wishlist reminders processed: {$sent} sent, {$skipped} skipped."
        );

        return self::SUCCESS;
    }

    private function userAllowsWishlistReminder(object $user): bool
    {
        $prefs = $user->notification_preferences ?? [];
        if (is_string($prefs)) {
            $prefs = json_decode($prefs, true) ?: [];
        }

        if (!is_array($prefs) || ($prefs['push_notifications'] ?? true) === false) {
            return false;
        }

        return (bool) ($prefs['wishlist_reminders'] ?? true);
    }

    private function userHasPushToken(int $userId): bool
    {
        return PushToken::query()
            ->where('user_id', $userId)
            ->where('provider', 'expo')
            ->where('token', 'like', 'ExponentPushToken[%')
            ->exists();
    }

    private function userPurchasedProductSinceWishlist(
        int $userId,
        int $productId,
        Carbon|string|null $wishlistedAt,
    ): bool {
        if ($wishlistedAt === null) {
            return false;
        }

        return OrderItem::query()
            ->where('product_id', $productId)
            ->whereHas('order', function ($query) use ($userId, $wishlistedAt) {
                $query->where('buyer_id', $userId)
                    ->where('created_at', '>=', $wishlistedAt);
            })
            ->exists();
    }

    private function userHasProductInCart(int $userId, int $productId): bool
    {
        return Cart::query()
            ->where('user_id', $userId)
            ->where('product_id', $productId)
            ->exists();
    }
}
