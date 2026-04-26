<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
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
            $table->string('slug_mm')->nullable();
            $table->text('description_en')->nullable();
            $table->text('description_mm')->nullable();

            // ---------------------------------------------------------------
            // PRODUCT TYPE
            // Drives stock tracking, shipping, and delivery behaviour:
            //   physical → has stock, requires shipping
            //   digital  → no stock limit, no shipping (file/link delivered)
            //   service  → no stock, no shipping (e.g. consulting, installation)
            // ---------------------------------------------------------------
            $table->enum('product_type', ['physical', 'digital', 'service'])->default('physical');

            // ---------------------------------------------------------------
            // PRICING
            // `price` is the base / display price shown on listing cards and
            // used for price-range filters. The actual per-variant price lives
            // in product_variants.price. When a product has no variants,
            // this IS the selling price.
            // ---------------------------------------------------------------
            $table->decimal('price', 12, 2);

            // NOTE: `quantity` is intentionally absent here.
            //   → For physical products: stock is tracked per-variant in product_variants.
            //   → For digital / service: no stock tracking needed.

            // NOTE: `color` (single string) is removed.
            //   → Colors, sizes, and any buyer-selectable options are now
            //     handled through product_options + product_option_values.

            $table->unsignedBigInteger('category_id');
            $table->unsignedBigInteger('seller_id');
            $table->decimal('average_rating', 3, 2)->default(0.00);
            $table->integer('review_count')->default(0);
            $table->json('specifications')->nullable();
            $table->json('images')->nullable();
            $table->json('dimensions')->nullable();

            // ---------------------------------------------------------------
            // IDENTIFICATION
            // `sku` here is the parent / product-level SKU (optional).
            // Each variant has its own child SKU in product_variants.sku.
            // ---------------------------------------------------------------
            $table->string('sku')->unique()->nullable();
            $table->string('barcode')->unique()->nullable();
            $table->string('brand')->nullable();
            $table->string('model')->nullable();
            $table->string('material')->nullable();
            $table->string('origin')->nullable();

            // ---------------------------------------------------------------
            // DISCOUNTS
            // ---------------------------------------------------------------
            $table->decimal('discount_price', 12, 2)->nullable()->default(null);
            $table->enum('discount_type', ['percentage', 'fixed', 'none'])->default('none');
            $table->decimal('discount_percentage', 5, 2)->nullable()->default(null);
            $table->string('sale_badge')->nullable()->default('Sale');
            $table->decimal('compare_at_price', 12, 2)->nullable()->default(null);
            $table->integer('sale_quantity')->nullable()->default(null);
            $table->integer('sale_sold')->default(0);
            $table->date('discount_start')->nullable();
            $table->date('discount_end')->nullable();
            $table->boolean('is_on_sale')->default(false);

            // ---------------------------------------------------------------
            // STATS & FLAGS
            // ---------------------------------------------------------------
            $table->integer('views')->default(0);
            $table->integer('sales')->default(0);
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_new')->default(true);
            $table->enum('condition', [
                'new',
                'used_like_new',
                'used_good',
                'used_fair',
            ])->default('new');

            // ---------------------------------------------------------------
            // PHYSICAL & SHIPPING
            // (only relevant when product_type = 'physical')
            // ---------------------------------------------------------------
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

            // ---------------------------------------------------------------
            // B2B SPECIFICS
            // `moq` here is the product-level fallback minimum order quantity.
            // Individual variants can override with their own moq (nullable).
            // `quantity_unit` is the base unit for this product — consistent
            // with rfqs.unit (e.g. "piece", "kg", "meter", "liter", "roll").
            // ---------------------------------------------------------------
            $table->string('quantity_unit')->default('piece');
            $table->integer('moq')->default(1);
            $table->string('min_order_unit')->default('piece');
            $table->string('lead_time')->nullable();
            $table->text('packaging_details')->nullable();
            $table->text('additional_info')->nullable();

            // ---------------------------------------------------------------
            // DIGITAL PRODUCT FIELDS
            // Only relevant when product_type = 'digital'.
            // ---------------------------------------------------------------
            $table->string('file_url')->nullable();
            $table->string('file_type')->nullable();            // e.g. PDF, ZIP, MP4
            $table->unsignedBigInteger('file_size')->nullable(); // in bytes

            // ---------------------------------------------------------------
            // APPROVAL WORKFLOW
            // ---------------------------------------------------------------
            $table->timestamp('listed_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->string('rejection_reason', 500)
                ->nullable()
                ->comment('Admin rejection reason shown to the seller');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            // Foreign keys
            $table->foreign('category_id')->references('id')->on('categories')->onDelete('cascade');
            $table->foreign('seller_id')->references('id')->on('users')->onDelete('cascade');

            // Indexes
            $table->index(['sku', 'is_active']);
            $table->index('is_active');
            $table->index('created_at');
            $table->index('is_on_sale');
            $table->index('discount_end');
            $table->index('product_type');

            $table->fullText(['name_en', 'description_en']);
            $table->fullText(['name_mm', 'description_mm']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
