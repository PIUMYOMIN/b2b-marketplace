<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MOQ SYSTEM — Step 2: wholesale tier pricing.
 *
 * Allows sellers to define volume-based discounts that apply automatically
 * when a buyer's quantity meets a threshold.
 *
 * Example for a product with price = 11,500 Ks / piece:
 *
 *   min_qty | price_per_unit | discount_pct | label
 *   --------|----------------|--------------|------
 *   1       | 11500.00       | 0            | Retail
 *   5       | 10500.00       | 8.70         | Wholesale
 *   20      |  9500.00       | 17.39        | Bulk
 *   100     |  8000.00       | 30.43        | Factory
 *
 * The effective price is resolved at add-to-cart and recalculated at checkout.
 * Tiers are returned in the ProductResource and used by ProductDetail.jsx to
 * render a pricing table.
 *
 * VARIANT SUPPORT:
 * A tier can be scoped to a specific variant (variant_id != null) or apply to
 * the whole product (variant_id = null). Variant-scoped tiers take precedence.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_wholesale_tiers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_id')
                ->constrained()
                ->onDelete('cascade');

            // When set, this tier applies only to this specific variant.
            // When null, applies to all variants of the product (or the product itself).
            $table->foreignId('variant_id')
                ->nullable()
                ->constrained('product_variants')
                ->onDelete('cascade');

            // The minimum quantity the buyer must order to unlock this tier.
            // Should be >= the product's MOQ.
            $table->unsignedInteger('min_qty');

            // The per-unit price at this tier. Stored explicitly so the display
            // price is always clear — no runtime formula required.
            $table->decimal('price_per_unit', 12, 2);

            // Precomputed discount % relative to products.price — for display only.
            // Recalculated whenever price_per_unit is saved.
            $table->decimal('discount_pct', 5, 2)->default(0);

            // Optional human-readable label shown in the pricing table.
            // e.g. "Wholesale", "Bulk", "Factory Price"
            $table->string('label')->nullable();

            // Sort order (ascending min_qty is the natural order, but explicit
            // sort_order lets sellers rearrange without changing min_qty).
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Composite unique: one tier per min_qty per product (+ optional variant).
            $table->unique(['product_id', 'variant_id', 'min_qty'], 'unique_tier_per_product_variant_qty');

            $table->index(['product_id', 'is_active']);
            $table->index(['variant_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_wholesale_tiers');
    }
};