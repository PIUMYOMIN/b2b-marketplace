<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('commissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('seller_id');
            $table->decimal('amount', 12, 2);
            // Revenue breakdown
            $table->decimal('commission_rate', 5, 4)->default(0.05);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('tax_rate', 5, 4)->default(0.05);
            $table->decimal('platform_revenue', 12, 2)->default(0);
            $table->decimal('seller_payout', 12, 2)->default(0);
            $table->string('notes')->nullable();
            $table->unsignedBigInteger('commission_rule_id')->nullable();
            $table->enum('status', ['pending', 'collected', 'due', 'waived']);
            $table->timestamp('due_date')->nullable();
            $table->timestamp('collected_at')->nullable();
            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
            $table->foreign('seller_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commissions');
    }
};