<?php
// database/migrations/2026_04_11_create_cod_commission_invoices_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('cod_commission_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->string('invoice_number')->unique();
            $table->decimal('order_subtotal', 12, 2);
            $table->decimal('commission_rate', 5, 4);  // e.g. 0.0500 = 5%
            $table->decimal('commission_amount', 12, 2);
            $table->enum('status', ['outstanding', 'overdue', 'paid', 'waived'])
                  ->default('outstanding');
            $table->date('due_date');
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('warning_sent_at')->nullable();  // day-5 warning
            $table->timestamp('suspended_at')->nullable();     // day-8 suspension
            $table->timestamp('admin_confirmed_at')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users');
            $table->string('payment_reference')->nullable();
            $table->string('payment_method')->nullable();
            $table->text('seller_notes')->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamps();

            $table->index(['seller_id', 'status']);
            $table->index(['status', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cod_commission_invoices');
    }
};