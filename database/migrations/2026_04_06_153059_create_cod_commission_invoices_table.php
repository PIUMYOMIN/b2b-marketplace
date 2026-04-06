<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cod_commission_invoices', function (Blueprint $table) {
            $table->id();

            // Human-readable invoice reference: COD-INV-20260405-00001
            $table->string('invoice_number')->unique();

            $table->foreignId('order_id')
                ->constrained()
                ->onDelete('cascade');

            $table->foreignId('seller_id')
                ->constrained('users')
                ->onDelete('cascade');

            $table->decimal('order_subtotal', 14, 2);         // subtotal the commission was taken from
            $table->decimal('commission_rate', 5, 4);         // rate applied
            $table->decimal('commission_amount', 14, 2);      // amount seller owes platform

            /**
             * outstanding  – invoice raised, awaiting seller payment
             * paid         – seller has paid commission to platform
             * overdue      – past due_date, not paid
             * waived       – admin manually waived (e.g., dispute resolved)
             */
            $table->enum('status', ['outstanding', 'paid', 'overdue', 'waived'])
                ->default('outstanding');

            $table->date('due_date');                         // default: 7 days after delivery

            // Payment receipt fields (filled when seller pays)
            $table->timestamp('paid_at')->nullable();
            $table->string('payment_reference')->nullable();  // bank transfer ref / screenshot ref
            $table->string('payment_method')->nullable();     // kbz_pay, wave_pay, bank_transfer, etc.

            // Admin actions
            $table->foreignId('confirmed_by')
                ->nullable()
                ->constrained('users')
                ->onDelete('set null');
            $table->timestamp('confirmed_at')->nullable();
            $table->text('admin_notes')->nullable();

            $table->text('seller_notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['seller_id', 'status']);
            $table->index(['due_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cod_commission_invoices');
    }
};
