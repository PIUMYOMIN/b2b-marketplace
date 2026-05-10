<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Each row is one purchasable combination of option values for a product.
     * The actual option combination is stored in the pivot table
     * `product_variant_option_values` (proper FK integrity, queryable).
     *
     * Example — T-shirt (product_id=5) with Color + Size options:
     *
     *   id | product_id | sku          | price  | quantity | quantity_unit | moq
     *   ---|-----------|--------------|--------|----------|---------------|-----
     *   1  | 5         | TSHIRT-RED-S | 15.00  | 30.000   | piece         | null  ← inherits product moq
     *   2  | 5         | TSHIRT-RED-M | 15.00  | 25.000   | piece         | null
     *   3  | 5         | TSHIRT-BLU-M | 17.00  | 10.000   | piece         | 200   ← variant-level override
     *
     * Example — Fabric product (product_id=8) with Color option:
     *
     *   id | product_id | sku         | price  | quantity | quantity_unit | moq
     *   ---|-----------|-------------|--------|----------|---------------|-----
     *   4  | 8         | FABRIC-WHT  | 5.50   | 500.000  | meter         | 50
     *   5  | 8         | FABRIC-BLK  | 5.50   | 300.000  | meter         | 50
     *
     * PRODUCTS WITH NO VARIANTS:
     *   Create one default variant row with no rows in product_variant_option_values.
     *   This keeps stock and SKU management uniform across all products.
     */
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_id')
                ->constrained()
                ->onDelete('cascade');

            // ---------------------------------------------------------------
            // IDENTIFICATION
            // Child SKU for this specific variant (e.g. "TSHIRT-RED-M").
            // ---------------------------------------------------------------
            $table->string('sku')->unique()->nullable();

            // ---------------------------------------------------------------
            // PRICING
            // The actual price charged when this variant is ordered.
            // More specific than products.price (the base/display price).
            // ---------------------------------------------------------------
            $table->decimal('price', 12, 2);

            // ---------------------------------------------------------------
            // STOCK
            // Decimal to support weight/length-based products (kg, meter…).
            // Uses the same precision as rfqs.quantity for consistency.
            // `quantity_unit` overrides the product-level unit if set,
            // otherwise falls back to products.quantity_unit.
            // ---------------------------------------------------------------
            $table->decimal('quantity', 14, 3)->default(0);
            $table->string('quantity_unit')->nullable()
                ->comment('Overrides products.quantity_unit when set. e.g. piece, kg, meter, liter');

            // ---------------------------------------------------------------
            // B2B — MINIMUM ORDER QUANTITY (variant-level)
            // When set, overrides products.moq for this specific variant.
            // When null, the product-level moq applies.
            // Useful when e.g. "Blue shirts" have a higher minimum than "Red".
            // ---------------------------------------------------------------
            $table->integer('moq')->nullable()
                ->comment('Variant-level MOQ override. Falls back to products.moq when null.');

            $table->unsignedSmallInteger('quantity_step')
                ->nullable()
                ->comment('Variant-level step override. Falls back to products.quantity_step when null.');

            // ---------------------------------------------------------------
            // VARIANT IMAGE
            // When set, shown when the buyer selects this variant
            // (e.g. switching colour updates the product photo).
            // ---------------------------------------------------------------
            $table->string('image')->nullable();

            // ---------------------------------------------------------------
            // DISPLAY ORDER & STATUS
            // ---------------------------------------------------------------
            $table->unsignedSmallInteger('position')->default(1);
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('product_id');
            $table->index(['product_id', 'is_active']);
            $table->index('sku');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
