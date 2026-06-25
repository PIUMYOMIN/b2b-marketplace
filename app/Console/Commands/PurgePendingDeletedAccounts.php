<?php

namespace App\Console\Commands;

use App\Services\UserAccountDeletionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * php artisan users:purge-pending-deletions
 *
 * Permanently deletes user accounts whose deletion grace period has expired.
 */
class PurgePendingDeletedAccounts extends Command
{
    protected $signature = 'users:purge-pending-deletions
                            {--dry-run : Preview how many accounts would be purged, without deleting}';

    protected $description = 'Permanently delete user accounts whose pending deletion grace period has expired.';

    public function handle(UserAccountDeletionService $accountDeletion): int
    {
        if ($this->option('dry-run')) {
            $cutoff = now()->subDays($accountDeletion->graceDays());
            $count = \App\Models\User::query()
                ->whereNotNull('deletion_requested_at')
                ->where('deletion_requested_at', '<=', $cutoff)
                ->count();

            $this->info("{$count} account(s) would be permanently deleted.");
            return self::SUCCESS;
        }

        $purged = $accountDeletion->purgeExpiredPendingAccounts();

        if ($purged === 0) {
            $this->info('No expired pending deletions found.');
            return self::SUCCESS;
        }

        $this->info("Permanently deleted {$purged} account(s).");
        Log::info('users:purge-pending-deletions completed', ['purged' => $purged]);

        return self::SUCCESS;
    }
}
