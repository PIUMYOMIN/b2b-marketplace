<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\Order;
use App\Models\Report;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UserAccountDeletionService
{
    public const GRACE_DAYS = 30;

    public function graceDays(): int
    {
        return (int) config('app.account_deletion_grace_days', self::GRACE_DAYS);
    }

    public function hasBlockingActiveOrders(User $user): bool
    {
        if ($user->hasRole('buyer')) {
            return Order::where('buyer_id', $user->id)
                ->whereNotIn('status', ['delivered', 'cancelled'])
                ->exists();
        }

        if ($user->hasRole('seller')) {
            return Order::where('seller_id', $user->id)
                ->whereNotIn('status', ['delivered', 'cancelled'])
                ->exists();
        }

        return false;
    }

    public function hasBlockingOpenReports(User $user): bool
    {
        return Report::where('reporter_id', $user->id)
            ->whereIn('status', [
                Report::STATUS_OPEN,
                Report::STATUS_IN_REVIEW,
                Report::STATUS_WAITING,
            ])
            ->exists();
    }

    public function scheduleDeletion(User $user): User
    {
        return DB::transaction(function () use ($user) {
            $user->update([
                'deletion_requested_at' => now(),
                'is_active' => false,
            ]);

            $user->tokens()->delete();

            return $user->fresh();
        });
    }

    public function cancelDeletion(User $user): User
    {
        return DB::transaction(function () use ($user) {
            $user->update([
                'deletion_requested_at' => null,
                'is_active' => true,
            ]);

            return $user->fresh();
        });
    }

    public function purgeAccount(User $user): void
    {
        DB::transaction(function () use ($user) {
            Cart::where('user_id', $user->id)->delete();
            $user->wishlist()->detach();
            $user->notifications()->delete();
            $user->tokens()->delete();
            $user->roles()->detach();
            $user->delete();
        });
    }

    public function purgeExpiredPendingAccounts(): int
    {
        $cutoff = now()->subDays($this->graceDays());
        $purged = 0;

        User::query()
            ->whereNotNull('deletion_requested_at')
            ->where('deletion_requested_at', '<=', $cutoff)
            ->orderBy('id')
            ->chunkById(100, function ($users) use (&$purged) {
                foreach ($users as $user) {
                    $this->purgeAccount($user);
                    $purged++;
                }
            });

        return $purged;
    }
}
