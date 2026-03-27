<?php

namespace App\Console\Commands;

use App\Models\CommissionRule;
use App\Models\SellerProfile;
use App\Models\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Nightly job that promotes sellers between tiers based on
 * their lifetime completed order count.
 *
 * Thresholds (completed orders):
 *   bronze  →  0–49
 *   silver  →  50–499
 *   gold    →  500+
 *
 * Schedule: run nightly via routes/console.php
 * Manual:   php artisan sellers:promote-tiers
 */
class PromoteSellerTiers extends Command
{
    protected $signature   = 'sellers:promote-tiers {--dry-run : Preview changes without saving}';
    protected $description = 'Promote seller tiers based on completed order counts';

    private const THRESHOLDS = [
        'gold'   => 500,
        'silver' => 50,
        'bronze' => 0,
    ];

    public function handle(): int
    {
        $dryRun    = $this->option('dry-run');
        $promoted  = 0;
        $unchanged = 0;

        // Count completed orders per seller in one query
        $orderCounts = Order::where('status', Order::STATUS_DELIVERED)
            ->select('seller_id', DB::raw('COUNT(*) as completed'))
            ->groupBy('seller_id')
            ->pluck('completed', 'seller_id');

        $profiles = SellerProfile::all();

        foreach ($profiles as $profile) {
            $completed   = (int) ($orderCounts[$profile->user_id] ?? 0);
            $targetTier  = $this->resolveTier($completed);
            $currentTier = $profile->seller_tier ?? 'bronze';

            // Only promote, never demote
            if ($this->tierRank($targetTier) <= $this->tierRank($currentTier)) {
                $unchanged++;
                continue;
            }

            $this->line(sprintf(
                '  %s  %s → %s  (%d completed orders)',
                $profile->store_name,
                strtoupper($currentTier),
                strtoupper($targetTier),
                $completed
            ));

            if (!$dryRun) {
                $profile->update([
                    'seller_tier'            => $targetTier,
                    'completed_orders_count' => $completed,
                    'tier_promoted_at'       => now(),
                ]);
            }

            $promoted++;
        }

        $this->info(sprintf(
            '%s %d seller(s) promoted, %d unchanged.',
            $dryRun ? '[DRY RUN]' : '',
            $promoted,
            $unchanged
        ));

        Log::info('PromoteSellerTiers completed', [
            'promoted'  => $promoted,
            'unchanged' => $unchanged,
            'dry_run'   => $dryRun,
        ]);

        return self::SUCCESS;
    }

    private function resolveTier(int $completed): string
    {
        foreach (self::THRESHOLDS as $tier => $threshold) {
            if ($completed >= $threshold) return $tier;
        }
        return 'bronze';
    }

    private function tierRank(string $tier): int
    {
        return match ($tier) {
            'gold'   => 3,
            'silver' => 2,
            default  => 1,
        };
    }
}