<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seller_subscriptions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->unique()                          // one active subscription per seller
                ->constrained()
                ->onDelete('cascade');

            $table->foreignId('plan_id')
                ->constrained('subscription_plans')
                ->onDelete('restrict');

            $table->enum('status', ['active', 'expired', 'cancelled', 'pending_payment'])
                ->default('active');

            // Billing window
            $table->date('starts_at');
            $table->date('ends_at')->nullable();    // null = indefinite (free plan)
            $table->date('next_billing_at')->nullable();

            // Snapshot of what was charged (plan price may change later)
            $table->decimal('amount_paid_mmk', 12, 2)->default(0);

            // Payment reference (links to your existing payments table if used)
            $table->string('payment_reference')->nullable();

            // Who upgraded/changed the plan (null = self-service)
            $table->foreignId('changed_by')
                ->nullable()
                ->constrained('users')
                ->onDelete('set null');

            $table->text('notes')->nullable();      // admin notes on manual upgrades

            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('ends_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_subscriptions');
    }
};