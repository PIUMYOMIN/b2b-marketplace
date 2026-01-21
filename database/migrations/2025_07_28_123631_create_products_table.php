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
            $table->json('specifications')->nullable();
            $table->json('images')->nullable();
            $table->json('dimensions')->nullable();
            $table->string('sku')->unique()->nullable();
            $table->string('barcode')->unique()->nullable();
            $table->string('brand')->nullable();
            $table->string('model')->nullable();
            $table->string('color')->nullable();
            $table->string('material')->nullable();
            $table->string('origin')->nullable();
            $table->decimal('discount_price', 12, 2)->nullable();
            $table->date('discount_start')->nullable();
            $table->date('discount_end')->nullable();
            $table->integer('views')->default(0);
            $table->integer('sales')->default(0);
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_new')->default(true);
            $table->enum('condition', [
                'new',
                'used_like_new',
                'used_good',
                'used_fair'
            ])->default('new');
            $table->boolean('is_on_sale')->default(false);
            $table->decimal('weight_kg', 10, 2)->nullable();
            $table->string('warranty')->nullable();
            $table->string('warranty_type')->nullable();
            $table->string('warranty_period')->nullable();
            $table->text('warranty_conditions')->nullable();
            $table->string('return_policy')->nullable();
            $table->text('return_conditions')->nullable();
            $table->text('shipping_details')->nullable();
            $table->decimal('shipping_cost', 12, 2)->nullable();
            $table->string('shipping_time')->nullable();
            $table->string('shipping_origin')->nullable();
            $table->string('customs_info')->nullable();
            $table->string('hs_code')->nullable();
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