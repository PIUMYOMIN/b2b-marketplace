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
            $table->unsignedBigInteger('buyer_id');
            $table->unsignedBigInteger('seller_id');
            $table->decimal('total_amount', 12, 2);
            $table->decimal('commission_amount', 12, 2);
            $table->enum('status', ['pending', 'confirmed', 'shipped', 'delivered', 'cancelled'])->default('pending');
            $table->enum('payment_method', ['mmqr', 'kbz_pay', 'wave_pay', 'bank_transfer', 'cod']);
            $table->text('shipping_address');
            $table->timestamps();

            $table->foreign('buyer_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('seller_id')->references('id')->on('users')->onDelete('cascade');
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