<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')
                ->constrained()
                ->onDelete('cascade');

            $table->foreignId('product_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            // ---------------------------------------------------------------
            // VARIANT REFERENCE
            // Points to the specific variant the buyer selected (e.g. Red, M).
            // Nullable for simple products that have no variants.
            // Set to null on delete so the order history is never lost.
            // ---------------------------------------------------------------
            $table->foreignId('variant_id')
                ->nullable()
                ->constrained('product_variants')
                ->onDelete('set null');

            // ---------------------------------------------------------------
            // SNAPSHOTS AT TIME OF ORDER
            // Never rely on live product/variant data for order history —
            // products can be edited or deleted after an order is placed.
            // ---------------------------------------------------------------
            $table->string('product_name');
            $table->string('product_sku')->nullable();

            // The variant SKU at the time of order (e.g. "TSHIRT-RED-M")
            $table->string('variant_sku')->nullable();

            // The buyer's selected options at time of order.
            // Example: {"Color": "Red", "Size": "M", "Engraving Text": "John"}
            // Human-readable snapshot — survives option renames or deletions.
            $table->json('selected_options')->nullable();

            // Unit used for quantity at time of order (e.g. "piece", "kg", "meter")
            $table->string('quantity_unit')->default('piece');

            // Actual unit price charged (from variant.price at checkout)
            $table->decimal('price', 12, 2);

            $table->decimal('quantity', 14, 3);
            $table->decimal('subtotal', 12, 2);

            // Full product + variant snapshot for audit / support purposes
            $table->json('product_data')->nullable();

            $table->timestamps();

            // Indexes
            $table->index('order_id');
            $table->index('product_id');
            $table->index('variant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
