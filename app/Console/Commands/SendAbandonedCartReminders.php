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
use Illuminate\Support\Facades\DB;
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
                            {--cooldown=7 : Minimum days before re-reminding the same buyer}
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
            ->with(['user', 'product', 'variant'])
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

            if (!$this->cartItemIsAvailable($cart, $product)) {
                $skipped++;
                continue;
            }

            if ($this->userPurchasedProductSinceAdded($user->id, $cart)) {
                $skipped++;
                continue;
            }

            $itemCount = (int) ($cartCounts[$user->id] ?? $cartCounts[(string) $user->id] ?? 1);

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
                $this->markBuyerCartsReminded($user->id);
                $usersNotifiedToday[$user->id] = true;
                $sent++;
                Log::info('cart:send-reminders sent', [
                    'cart_id' => $cart->id,
                    'user_id' => $user->id,
                    'product_id' => $product->id,
                    'cart_item_count' => $itemCount,
                ]);
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

    private function markBuyerCartsReminded(int $userId): void
    {
        // Query builder on the table so we do not bump updated_at (that would
        // reset the 3-day stale window). Stamp every line so leftover items
        // cannot fire another push tomorrow.
        DB::table('carts')
            ->where('user_id', $userId)
            ->update(['last_reminded_at' => now()]);
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

    private function cartItemIsAvailable(Cart $cart, Product $product): bool
    {
        if (!$product->is_active || $product->status !== 'approved') {
            return false;
        }

        if ($product->product_type !== 'physical') {
            return true;
        }

        if ($cart->variant_id) {
            $variant = $cart->variant;
            if ($variant === null) {
                return false;
            }

            return (float) $variant->quantity > 0;
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
