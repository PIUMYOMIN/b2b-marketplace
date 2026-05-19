<?php

namespace App\Console\Commands;

use App\Models\SellerSubscription;
use App\Models\SubscriptionPlan;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * php artisan subscriptions:expire
 *
 * Marks every overdue active subscription as 'expired' and creates a
 * replacement Basic (free) plan record so the seller is never left with
 * no plan at all.
 *
 * Schedule this daily in app/Console/Kernel.php:
 *   $schedule->command('subscriptions:expire')->dailyAt('00:05');
 */
class ExpireSubscriptions extends Command
{
    protected $signature   = 'subscriptions:expire
                                {--dry-run : Preview which subscriptions would be expired, without writing}';

    protected $description = 'Expire overdue paid seller subscriptions and downgrade them to the Basic plan.';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        // All active subs whose ends_at has already passed.
        $overdue = SellerSubscription::with(['plan', 'user'])
            ->overdue()
            ->get();

        if ($overdue->isEmpty()) {
            $this->info('No overdue subscriptions found.');
            return self::SUCCESS;
        }

        $this->info("Found {$overdue->count()} overdue subscription(s)." . ($dryRun ? ' [DRY RUN]' : ''));

        $basic = SubscriptionPlan::where('slug', 'basic')->first();

        if (! $basic) {
            $this->error('Basic plan not found in database. Run the SubscriptionPlanSeeder first.');
            return self::FAILURE;
        }

        $expired  = 0;
        $failed   = 0;
        $table    = [];

        foreach ($overdue as $sub) {
            $table[] = [
                $sub->user_id,
                $sub->user?->email ?? '—',
                $sub->plan?->name  ?? '—',
                $sub->ends_at?->toDateString(),
            ];

            if ($dryRun) {
                continue;
            }

            DB::beginTransaction();
            try {
                // Mark the old subscription as expired.
                $sub->update(['status' => 'expired']);

                // Auto-assign the Basic plan so the seller retains access.
                SellerSubscription::create([
                    'user_id'         => $sub->user_id,
                    'plan_id'         => $basic->id,
                    'status'          => 'active',
                    'starts_at'       => Carbon::today(),
                    'ends_at'         => null,           // Basic never expires
                    'next_billing_at' => null,
                    'amount_paid_mmk' => 0,
                    'notes'           => "Auto-downgraded from {$sub->plan?->name} on " . Carbon::today()->toDateString(),
                ]);

                DB::commit();
                $expired++;

                Log::info('ExpireSubscriptions: downgraded seller to Basic.', [
                    'user_id'      => $sub->user_id,
                    'expired_plan' => $sub->plan?->slug,
                    'ended_at'     => $sub->ends_at?->toDateString(),
                ]);

            } catch (\Exception $e) {
                DB::rollBack();
                $failed++;
                Log::error('ExpireSubscriptions: failed to expire subscription.', [
                    'subscription_id' => $sub->id,
                    'user_id'         => $sub->user_id,
                    'error'           => $e->getMessage(),
                ]);
            }
        }

        // Display summary table.
        $this->table(
            ['User ID', 'Email', 'Plan', 'Ended At'],
            $table
        );

        if (! $dryRun) {
            $this->info("Expired: {$expired} | Failed: {$failed}");

            // Alert admin when any subscription failed to expire.
            if ($failed > 0 && config('subscription.expiry.alert_on_failure', true)) {
                $this->notifyAdmin($failed, $expired);
            }
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Send a plain-text alert email to the admin report address.
     */
    private function notifyAdmin(int $failed, int $expired): void
    {
        $adminEmail = config('mail.admin_report_email');
        if (! $adminEmail) return;

        try {
            Mail::raw(
                "⚠️ subscriptions:expire command completed with {$failed} failure(s).\n\n" .
                "Successfully expired: {$expired}\n" .
                "Failed: {$failed}\n\n" .
                "Please check the Laravel logs for details:\n" .
                "  storage/logs/laravel.log\n\n" .
                "Run manually to retry:\n" .
                "  php artisan subscriptions:expire",
                function ($message) use ($adminEmail, $failed) {
                    $message->to($adminEmail)
                        ->subject("[Pyonea] ⚠️ {$failed} subscription expiry failure(s) — " . now()->toDateString());
                }
            );

            Log::info('ExpireSubscriptions: admin failure alert sent.', [
                'to'     => $adminEmail,
                'failed' => $failed,
            ]);
        } catch (\Exception $e) {
            // Never let the alert crash the command itself.
            Log::error('ExpireSubscriptions: could not send admin alert.', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}