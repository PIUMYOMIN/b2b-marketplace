<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payout_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('amount', 14, 2);
            $table->string('status', 32)->default('requested');
            $table->string('payment_method', 40);
            $table->string('bank_name', 120);
            $table->string('account_name', 160);
            $table->string('account_number', 255);
            $table->text('seller_note')->nullable();
            $table->text('admin_note')->nullable();
            $table->string('admin_withdrawal_reference', 120)->nullable();
            $table->string('seller_transfer_reference', 120)->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('admin_withdrawn_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamps();

            $table->index(['seller_id', 'status']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payout_requests');
    }
};
