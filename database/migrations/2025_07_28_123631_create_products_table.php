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
            $table->string('name_en');
            $table->string('name_mm')->nullable();
            $table->string('slug_en')->unique();
            $table->string('slug_mm')->unique()->nullable();
            $table->text('description_en')->nullable();
            $table->text('description_mm')->nullable();
            $table->decimal('price', 12, 2);
            $table->integer('quantity')->default(0);
            $table->unsignedBigInteger('category_id');
            $table->unsignedBigInteger('seller_id');
            $table->decimal('average_rating', 3, 2)->default(0.00);
            $table->integer('review_count')->default(0);
            $table->json('specifications')->nullable(); // JSON for product specs
            $table->json('images')->nullable(); // JSON for product images
            $table->decimal('weight_kg', 10, 2)->nullable();
            $table->json('dimensions')->nullable(); // LxWxH format
            $table->string('sku')->unique()->nullable(); // Stock Keeping Unit
            $table->string('barcode')->unique()->nullable();
            $table->string('brand')->nullable();
            $table->string('model')->nullable();
            $table->string('color')->nullable();
            $table->string('material')->nullable();
            $table->string('origin')->nullable(); // Country of origin
            $table->decimal('discount_price', 12, 2)->nullable();
            $table->date('discount_start')->nullable();
            $table->date('discount_end')->nullable();
            $table->integer('views')->default(0);
            $table->integer('sales')->default(0);
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_new')->default(true);
            $table->boolean('is_on_sale')->default(false);
            $table->string('warranty')->nullable(); // e.g., "1 year"
            $table->string('warranty_type')->nullable(); // e.g., "manufacturer", "seller"
            $table->string('warranty_period')->nullable(); // e.g., "12 months"
            $table->text('warranty_conditions')->nullable(); // e.g., "terms and conditions"
            $table->string('return_policy')->nullable(); // e.g., "30 days return"
            $table->text('return_conditions')->nullable(); // e.g., "unused, original packaging"
            $table->text('shipping_details')->nullable(); // e.g., "Free shipping over $50"
            $table->decimal('shipping_cost', 12, 2)->nullable();
            $table->string('shipping_time')->nullable(); // e.g., "3-5 business days"
            $table->string('shipping_origin')->nullable(); // e.g., "Yangon, Myanmar"
            $table->string('customs_info')->nullable(); // e.g., "Buyer responsible for duties"
            $table->string('hs_code')->nullable(); // Harmonized System Code
            $table->string('min_order_unit')->default('piece');
            $table->integer('moq')->default(1);
            $table->string('lead_time')->nullable();
            $table->text('packaging_details')->nullable();
            $table->text('additional_info')->nullable();
            $table->timestamp('listed_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            //foreign keys
            $table->foreign('category_id')->references('id')->on('categories')->onDelete('cascade');
            $table->foreign('seller_id')->references('id')->on('users')->onDelete('cascade');
            $table->softDeletes();

            // Indexes for better performance
            $table->index(['sku', 'is_active']);
            $table->index('is_active');
            $table->index('created_at');

            //Orders table indexes
            $table->index(['seller_id','status']);

            $table->fullText(['name_en', 'description_en']);
            $table->fullText(['name_mm', 'description_mm']);
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