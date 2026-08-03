<?php

namespace App\Console\Commands;

use App\Models\Cart;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\PushToken;
use App\Notifications\AbandonedCartReminder;
use App\Support\MarketingPushThrottle;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * php artisan cart:send-reminders
 *
 * Sends one mobile push per eligible buyer for stale cart items.
 */
class SendAbandonedCartReminders extends Command
{
    protected $signature = 'cart:send-reminders
                            {--days=3 : Minimum days since the cart item was last updated}
                            {--cooldown=7 : Minimum days before re-reminding the same cart item}
                            {--dry-run : Preview recipients without sending}';

    protected $description = 'Send checkout reminder pushes for cart items that have not been purchased.';

    public function handle(): int
    {
        $delayDays = max(1, (int) $this->option('days'));
        $cooldownDays = max(1, (int) $this->option('cooldown'));
        $dryRun = (bool) $this->option('dry-run');

        $staleBefore = Carbon::now()->subDays($delayDays);
        $remindedBefore = Carbon::now()->subDays($cooldownDays);

        $candidates = Cart::query()
            ->with(['user', 'product'])
            ->where('updated_at', '<=', $staleBefore)
            ->where(function ($query) use ($remindedBefore) {
                $query->whereNull('last_reminded_at')
                    ->orWhere('last_reminded_at', '<=', $remindedBefore);
            })
            ->whereHas('product', function ($query) {
                $query->where('is_active', true)->where('status', 'approved');
            })
            ->orderBy('updated_at')
            ->get();

        if ($candidates->isEmpty()) {
            $this->info('No eligible abandoned cart reminders found.');
            return self::SUCCESS;
        }

        $sent = 0;
        $skipped = 0;
        $usersNotifiedToday = [];
        $cartCounts = Cart::query()
            ->selectRaw('user_id, COUNT(*) as item_count')
            ->groupBy('user_id')
            ->pluck('item_count', 'user_id');

        foreach ($candidates as $cart) {
            $user = $cart->user;
            $product = $cart->product;

            if ($user === null || $product === null) {
                $skipped++;
                continue;
            }

            if (isset($usersNotifiedToday[$user->id])) {
                $skipped++;
                continue;
            }

            if (!$this->userAllowsCartReminder($user)) {
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

            if (!$this->cartItemIsAvailable($product)) {
                $skipped++;
                continue;
            }

            if ($this->userPurchasedProductSinceAdded($user->id, $cart)) {
                $skipped++;
                continue;
            }

            $itemCount = (int) ($cartCounts[$user->id] ?? 1);

            if ($dryRun) {
                $this->line(
                    "Would remind user {$user->id} about cart item {$cart->id} "
                    . "({$product->name_en}, {$itemCount} item(s) in cart)"
                );
                $usersNotifiedToday[$user->id] = true;
                $sent++;
                continue;
            }

            try {
                Notification::send($user, new AbandonedCartReminder($cart, $product, $itemCount));
                $cart->update(['last_reminded_at' => now()]);
                $usersNotifiedToday[$user->id] = true;
                $sent++;
            } catch (\Throwable $exception) {
                $skipped++;
                Log::error('cart:send-reminders failed for cart item', [
                    'cart_id' => $cart->id,
                    'user_id' => $user->id,
                    'product_id' => $product->id,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        $this->info(
            ($dryRun ? '[DRY RUN] ' : '')
            . "Abandoned cart reminders processed: {$sent} sent, {$skipped} skipped."
        );

        return self::SUCCESS;
    }

    private function userAllowsCartReminder(object $user): bool
    {
        $prefs = $user->notification_preferences ?? [];
        if (is_string($prefs)) {
            $prefs = json_decode($prefs, true) ?: [];
        }

        if (!is_array($prefs) || ($prefs['push_notifications'] ?? true) === false) {
            return false;
        }

        return (bool) ($prefs['cart_reminders'] ?? true);
    }

    private function userHasPushToken(int $userId): bool
    {
        return PushToken::query()
            ->where('user_id', $userId)
            ->where('provider', 'expo')
            ->where('token', 'like', 'ExponentPushToken[%')
            ->exists();
    }

    private function cartItemIsAvailable(Product $product): bool
    {
        if (!$product->is_active || $product->status !== 'approved') {
            return false;
        }

        if ($product->product_type !== 'physical') {
            return true;
        }

        return $product->totalStock() > 0;
    }

    private function userPurchasedProductSinceAdded(int $userId, Cart $cart): bool
    {
        $since = $cart->created_at ?? $cart->updated_at;
        if ($since === null) {
            return false;
        }

        return OrderItem::query()
            ->where('product_id', $cart->product_id)
            ->when(
                $cart->variant_id,
                fn ($query) => $query->where('variant_id', $cart->variant_id),
            )
            ->whereHas('order', function ($query) use ($userId, $since) {
                $query->where('buyer_id', $userId)
                    ->where('created_at', '>=', $since);
            })
            ->exists();
    }
}
