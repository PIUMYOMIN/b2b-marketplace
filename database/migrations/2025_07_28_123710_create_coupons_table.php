<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the `coupons` table only.
 *
 * Coupons are buyer-entered codes at checkout, created by sellers and scoped
 * to their own products. Separate from `discounts`, which are price reductions
 * applied directly to product listings (no buyer code entry required).
 *
 * Run order: this (123710) → orders (123711) → coupon_usages (123746)
 * coupon_usages is in its own migration to break the circular FK dependency:
 *   coupons → orders.coupon_id  →  coupon_usages.order_id → coupons
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();

            // Coupons belong to one seller — they can only target their own products
            $table->foreignId('seller_id')
                ->constrained('users')
                ->onDelete('cascade');

            $table->string('name');
            $table->string('code', 50)->unique();

            $table->enum('type', ['percentage', 'fixed']);
            $table->decimal('value', 10, 2);

            // Optional minimum spend threshold before coupon applies
            $table->decimal('min_order_amount', 12, 2)->nullable();

            // NULL = all of this seller's products; array of IDs = specific products only
            $table->json('applicable_product_ids')->nullable();

            // Usage limits
            $table->unsignedInteger('max_uses')->nullable();          // total across all buyers
            $table->unsignedInteger('used_count')->default(0);
            $table->unsignedInteger('max_uses_per_user')->nullable();  // per buyer

            $table->boolean('is_active')->default(true);
            $table->boolean('is_one_time_use')->default(false);

            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['seller_id', 'is_active']);
            $table->index(['code', 'is_active']);
            $table->index('expires_at');
        });

        // coupon_usages is intentionally NOT here.
        // It lives in 2025_07_28_123746_create_coupon_usages_table.php
        // so it can safely reference both `coupons` AND `orders` after both exist.
    }

    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};
