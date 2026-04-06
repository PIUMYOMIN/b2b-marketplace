<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('wallet_id')
                ->constrained('seller_wallets')
                ->onDelete('cascade');

            $table->foreignId('order_id')
                ->nullable()
                ->constrained()
                ->onDelete('set null');

            /**
             * Transaction types:
             *  escrow_hold        – buyer paid (digital), funds locked until delivery
             *  escrow_release     – delivery confirmed, commission deducted, payout credited
             *  escrow_reverse     – order cancelled before delivery, escrow reversed
             *  commission_deduct  – commission taken at delivery confirmation
             *  refund_hold        – refund approved, hold on available_balance
             *  withdrawal         – seller withdraws available funds to bank
             *  cod_invoice        – COD commission invoice raised (debt recorded)
             *  cod_payment        – seller paid COD commission invoice
             *  adjustment         – manual admin correction with notes
             */
            $table->enum('type', [
                'escrow_hold',
                'escrow_release',
                'escrow_reverse',
                'commission_deduct',
                'refund_hold',
                'withdrawal',
                'cod_invoice',
                'cod_payment',
                'adjustment',
            ]);

            // Positive = credit, Negative = debit
            $table->decimal('amount', 14, 2);

            // Snapshot of available_balance after this transaction
            $table->decimal('escrow_balance_after', 14, 2)->default(0);
            $table->decimal('available_balance_after', 14, 2)->default(0);

            $table->string('reference')->nullable();  // external ref (bank tx, invoice #)
            $table->text('notes')->nullable();

            // Who triggered it
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->onDelete('set null');

            $table->timestamps();

            $table->index(['wallet_id', 'created_at']);
            $table->index(['order_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
    }
};
