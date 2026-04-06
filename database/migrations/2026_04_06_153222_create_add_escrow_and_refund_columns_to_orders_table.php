<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('refund_approved_by')->nullable()->after('refund_reason')
                ->constrained('users')->onDelete('set null');

            // Escrow status for this order
            $table->enum('escrow_status', [
                'not_applicable',   // COD order — no escrow
                'held',             // funds locked in seller wallet escrow
                'released',         // delivery confirmed, payout released
                'reversed',         // order cancelled, escrow reversed to buyer
                'refunded',         // refund processed
            ])->default('not_applicable')->after('payment_status');

            $table->index(['escrow_status']);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'refund_amount',
                'commission_forfeited',
                'refunded_at',
                'refund_reason',
                'refund_approved_by',
                'escrow_status',
            ]);
        });
    }
};
