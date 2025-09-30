<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
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
            $table->decimal('tax_rate', 5, 2)->default(0.05); // 5% default tax
            $table->decimal('commission_amount', 10, 2)->default(0);
            $table->decimal('commission_rate', 5, 2)->default(0.10); // 10% default commission
            
            // Status
            $table->enum('status', [
                'pending',
                'confirmed', 
                'processing',
                'shipped',
                'delivered',
                'cancelled',
                'refunded'
            ])->default('pending');
            
            // Payment
            $table->enum('payment_method', [
                'kbz_pay',
                'wave_pay', 
                'cb_pay',
                'cash_on_delivery'
            ])->default('cash_on_delivery');
            $table->enum('payment_status', [
                'pending',
                'paid',
                'failed',
                'refunded'
            ])->default('pending');
            
            // Addresses
            $table->json('shipping_address')->nullable();
            $table->json('billing_address')->nullable();
            
            // Additional info
            $table->text('order_notes')->nullable();
            
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
                'rejected'
            ])->default('none');
            $table->decimal('refund_amount', 10, 2)->default(0);
            $table->text('refund_reason')->nullable();
            
            // Timestamps
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['buyer_id', 'status']);
            $table->index(['seller_id', 'status']);
            $table->index('order_number');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};