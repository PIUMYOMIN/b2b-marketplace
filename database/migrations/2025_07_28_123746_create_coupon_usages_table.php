<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * coupon_usages tracks every time a buyer uses a coupon code.
 *
 * ORDERING NOTE: This migration MUST run after both create_coupons_table (123710)
 * and create_orders_table (123711) because it references both tables.
 * It was split out of create_coupons_table to break the circular dependency:
 *   coupons → orders (coupon_id FK) → coupon_usages (order_id FK) → coupons
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupon_usages', function (Blueprint $table) {
            $table->id();

            $table->foreignId('coupon_id')
                ->constrained('coupons')
                ->onDelete('cascade');

            $table->foreignId('user_id')
                ->constrained('users')
                ->onDelete('cascade');

            // Nullable so a usage record can be created before an order ID is known
            $table->foreignId('order_id')
                ->nullable()
                ->constrained('orders')
                ->onDelete('set null');

            $table->decimal('discount_amount', 10, 2)->default(0);

            $table->timestamps();

            $table->unique(['coupon_id', 'user_id', 'order_id']);
            $table->index(['coupon_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_usages');
    }
};
