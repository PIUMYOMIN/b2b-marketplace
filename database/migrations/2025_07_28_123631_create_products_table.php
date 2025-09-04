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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('name_mm')->nullable(); // Myanmar name
            $table->text('description');
            $table->decimal('price', 12, 2); // Max 999,999,999.99
            $table->integer('quantity')->default(0);
            $table->unsignedBigInteger('category_id');
            $table->unsignedBigInteger('seller_id');
            $table->decimal('average_rating', 3, 2)->default(0.00);
            $table->integer('review_count')->default(0);
            $table->json('specifications')->nullable(); // JSON for product specs
            $table->json('images')->nullable(); // JSON for product images
            $table->integer('min_order')->default(1);
            $table->string('lead_time')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            //foreign keys
            $table->foreign('category_id')->references('id')->on('categories')->onDelete('cascade');
            $table->foreign('seller_id')->references('id')->on('users')->onDelete('cascade');
            $table->softDeletes();

            // Indexes for better performance
            $table->index('category_id');
            $table->index('seller_id');
            $table->index('is_active');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};