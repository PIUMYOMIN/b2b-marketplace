<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ORDERING NOTE: coupons (123710) must run before this migration because
 * coupon_id has a FK referencing coupons.
 *
 * NOTE: ->after() is silently ignored inside Schema::create(). It only has
 * effect inside Schema::table() (ALTER TABLE). Columns appear in the order
 * they are declared here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();

            // Buyer and Seller
            $table->foreignId('buyer_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('seller_id')->constrained('users')->onDelete('cascade');

            // Amounts
            $table->decimal('total_amount', 10, 2);
            $table->decimal('subtotal_amount', 10, 2);
            $table->decimal('shipping_fee', 10, 2)->default(0);
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0.05);
            $table->decimal('commission_amount', 10, 2)->default(0);
            $table->decimal('commission_rate', 5, 2)->default(0.10);

            // Coupon (nullable — coupon is optional at checkout)
            // coupons table (123710) is created before this migration so the FK is valid.
            $table->foreignId('coupon_id')
                ->nullable()
                ->constrained('coupons')
                ->onDelete('set null');
            $table->string('coupon_code', 50)->nullable();
            $table->decimal('coupon_discount_amount', 10, 2)->default(0);

            // Status
            $table->enum('status', [
                'pending',
                'confirmed',
                'processing',
                'shipped',
                'delivered',
                'cancelled',
                'refunded',
            ])->default('pending');

            // Payment
            $table->enum('payment_method', [
                'mmqr',
                'aya_pay',
                'kbz_pay',
                'wave_pay',
                'cb_pay',
                'cash_on_delivery',
            ])->default('cash_on_delivery');

            $table->enum('payment_status', [
                'pending',
                'paid',
                'failed',
                'refunded',
            ])->default('pending');

            $table->enum('escrow_status', [
                'not_applicable',
                'held',
                'released',
                'reversed',
                'refunded',
            ])->default('not_applicable');

            // Addresses
            $table->json('shipping_address')->nullable();
            $table->json('billing_address')->nullable();

            // Additional info
            $table->text('order_notes')->nullable();
            $table->string('order_otp', 6)->nullable();
            $table->timestamp('order_otp_expires_at')->nullable();
            $table->boolean('order_otp_verified')->default(false);

            // Shipping info
            $table->string('tracking_number')->nullable();
            $table->string('shipping_carrier')->nullable();
            $table->timestamp('estimated_delivery')->nullable();
            $table->timestamp('delivered_at')->nullable();

            // Cancellation & Refunds
            $table->timestamp('cancelled_at')->nullable();
            $table->enum('refund_status', [
                'none',
                'requested',
                'approved',
                'processed',
                'rejected',
            ])->default('none');
            $table->decimal('refund_amount', 10, 2)->default(0);
            $table->text('refund_reason')->nullable();
            $table->foreignId('refund_approved_by')->nullable()->constrained('users')->onDelete('set null');

            $table->timestamps();
            $table->softDeletes();

            $table->index(['escrow_status']);
            $table->index(['buyer_id', 'status']);
            $table->index(['seller_id', 'status']);
            $table->index('order_number');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
