<?php
// app/Console/Commands/ProcessOverdueCodInvoices.php

namespace App\Console\Commands;

use App\Models\CodCommissionInvoice;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Notifications\CodInvoiceWarning;
use App\Notifications\CodInvoiceSuspension;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessOverdueCodInvoices extends Command
{
    protected $signature   = 'cod:process-overdue {--dry-run : Preview without applying changes}';
    protected $description = 'Flag overdue COD invoices, send warnings at day 5, suspend listings at day 8.';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $today  = Carbon::today();

        if ($dryRun) {
            $this->info('[DRY RUN] No changes will be applied.');
        }

        $this->info("Processing COD invoices as of {$today->toDateString()}...");

        // ── Step 1: Mark outstanding invoices as overdue ──────────────────
        $overdueQuery = CodCommissionInvoice::where('status', 'outstanding')
            ->where('due_date', '<', $today);

        $overdueCount = $overdueQuery->count();
        $this->line("  Overdue (outstanding past due_date): {$overdueCount}");

        if (!$dryRun && $overdueCount > 0) {
            $overdueQuery->update(['status' => 'overdue']);
        }

        // ── Step 2: Day-5 warning — send notification to seller ──────────
        // Due date was 5+ days ago, warning not yet sent
        $warnCutoff  = $today->copy()->subDays(5);
        $warnInvoices = CodCommissionInvoice::where('status', 'overdue')
            ->where('due_date', '<=', $warnCutoff)
            ->whereNull('warning_sent_at')
            ->whereNull('suspended_at')
            ->with('seller')
            ->get();

        $this->line("  Sending day-5 warnings: {$warnInvoices->count()}");

        foreach ($warnInvoices as $invoice) {
            if ($dryRun) {
                $this->line("    [DRY RUN] Would warn seller #{$invoice->seller_id} for invoice {$invoice->invoice_number}");
                continue;
            }
            try {
                $invoice->seller?->notify(new CodInvoiceWarning($invoice));
                $invoice->update(['warning_sent_at' => now()]);
            } catch (\Exception $e) {
                Log::warning("COD warning notification failed for invoice {$invoice->id}: " . $e->getMessage());
            }
        }

        // ── Step 3: Day-8 suspension — suspend seller product listings ────
        $suspendCutoff  = $today->copy()->subDays(8);
        $suspendInvoices = CodCommissionInvoice::where('status', 'overdue')
            ->where('due_date', '<=', $suspendCutoff)
            ->whereNull('suspended_at')
            ->with('seller')
            ->get();

        $this->line("  Suspending sellers with 8+ day overdue invoices: {$suspendInvoices->count()}");

        foreach ($suspendInvoices as $invoice) {
            if ($dryRun) {
                $this->line("    [DRY RUN] Would suspend seller #{$invoice->seller_id} listings");
                continue;
            }
            try {
                DB::transaction(function () use ($invoice) {
                    // Deactivate all seller's products (sets is_active = false)
                    Product::where('seller_id', $invoice->seller_id)
                        ->where('is_active', true)
                        ->update(['is_active' => false]);

                    $invoice->update(['suspended_at' => now()]);

                    $invoice->seller?->notify(new CodInvoiceSuspension($invoice));
                });
            } catch (\Exception $e) {
                Log::error("COD suspension failed for invoice {$invoice->id}: " . $e->getMessage());
            }
        }

        // ── Step 4: Reinstate sellers whose invoices are now paid ─────────
        // A seller might have paid after suspension — restore their listings
        $reinstateSellerIds = CodCommissionInvoice::where('status', 'paid')
            ->whereNotNull('suspended_at')
            ->whereDoesntHave('seller', function ($q) {
                // Only reinstate if ALL invoices are paid (no outstanding/overdue left)
                $q->whereHas('codInvoices', fn($qi) =>
                    $qi->whereIn('status', ['outstanding', 'overdue'])
                );
            })
            ->pluck('seller_id')
            ->unique();

        $this->line("  Reinstating sellers with all invoices cleared: {$reinstateSellerIds->count()}");

        if (!$dryRun && $reinstateSellerIds->isNotEmpty()) {
            // Re-activate products (set back to active)
            Product::whereIn('seller_id', $reinstateSellerIds)
                ->where('is_active', false)
                ->update(['is_active' => true]);

            // Clear suspended_at on those invoices
            CodCommissionInvoice::whereIn('seller_id', $reinstateSellerIds)
                ->whereNotNull('suspended_at')
                ->update(['suspended_at' => null]);
        }

        $this->info('Done.');
        return Command::SUCCESS;
    }
}