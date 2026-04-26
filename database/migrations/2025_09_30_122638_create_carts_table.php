<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->onDelete('cascade');

            $table->foreignId('product_id')
                ->constrained()
                ->onDelete('cascade');

            // ---------------------------------------------------------------
            // VARIANT REFERENCE
            // The specific variant the buyer added to cart (e.g. Blue, L).
            // Nullable for simple products that have no variants.
            //
            // The same product can appear as multiple cart rows when the buyer
            // picks different variants (Red M + Blue L = 2 rows), so the
            // unique constraint covers (user_id, product_id, variant_id).
            // ---------------------------------------------------------------
            $table->foreignId('variant_id')
                ->nullable()
                ->constrained('product_variants')
                ->onDelete('cascade');

            // The buyer's selected options captured at add-to-cart time.
            // Includes free-text input values (type="input" options).
            // Example: {"Color": "Blue", "Size": "L", "Engraving Text": "Jane"}
            $table->json('selected_options')->nullable();

            // Decimal to support weight/length-based products
            $table->decimal('quantity', 14, 3)->default(1);

            // Unit at time of add-to-cart
            $table->string('quantity_unit')->default('piece');

            // Price locked at the time the item was added to cart
            $table->decimal('price', 12, 2);

            // Full product + variant snapshot (for display without extra queries)
            $table->json('product_data')->nullable();

            $table->timestamps();

            // A user cannot have duplicate (product + variant) in cart.
            // For products without variants (variant_id IS NULL), MySQL treats
            // NULLs as distinct, so duplicates are prevented at app level.
            $table->unique(['user_id', 'product_id', 'variant_id']);
            $table->index(['user_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carts');
    }
};
