<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Stores the types of buyer-selectable options a product has.
     *
     * Examples:
     *   product_id=5, name="Color",          type="color",  position=1
     *   product_id=5, name="Size",           type="size",   position=2
     *   product_id=8, name="Material",       type="text",   position=1
     *   product_id=9, name="Engraving Text", type="input",  position=1
     *
     * `type` drives the UI widget on the product page:
     *   "color"  → colour swatch circles
     *   "size"   → size buttons (S / M / L / XL)
     *   "text"   → plain text buttons or dropdown
     *   "image"  → thumbnail swatches (e.g. fabric patterns)
     *   "input"  → free-text field typed by the buyer (e.g. custom name,
     *              engraving, measurements). No predefined values exist in
     *              product_option_values — the buyer's typed value is captured
     *              directly in order_items.selected_options at checkout.
     */
    public function up(): void
    {
        Schema::create('product_options', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_id')
                ->constrained()
                ->onDelete('cascade');

            // Display name shown to the buyer
            $table->string('name');

            // UI widget type
            $table->enum('type', ['color', 'size', 'text', 'image', 'input'])->default('text');

            // Display order among this product's options (1-based)
            $table->unsignedTinyInteger('position')->default(1);

            // Whether the buyer must select/fill this option before adding to cart
            $table->boolean('is_required')->default(true);

            $table->timestamps();

            $table->unique(['product_id', 'name']);
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_options');
    }
};
