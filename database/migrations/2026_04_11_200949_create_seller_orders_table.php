<?php
// database/migrations/2026_04_11_create_seller_orders_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('seller_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->foreignId('seller_id')->constrained('users')->onDelete('cascade');
            $table->string('order_number')->unique(); // e.g. PY-2026-001-A
            $table->decimal('subtotal_amount', 12, 2)->default(0);
            $table->decimal('shipping_fee', 10, 2)->default(0);
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->decimal('commission_amount', 10, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->enum('delivery_method', ['platform', 'seller', 'pickup'])->default('seller');
            $table->enum('status', [
                'pending', 'confirmed', 'processing',
                'shipped', 'delivered', 'cancelled', 'refunded'
            ])->default('pending');
            $table->string('payment_method')->nullable();
            $table->boolean('zone_matched')->default(false);
            $table->string('zone_name')->nullable();
            $table->string('fee_source')->default('platform_default'); // zone|seller_default|platform_default
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('seller_notes')->nullable();
            $table->timestamps();

            $table->index(['seller_id', 'status']);
            $table->index(['order_id', 'seller_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_orders');
    }
};