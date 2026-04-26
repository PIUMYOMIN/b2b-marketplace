<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Stores the predefined choices for each product option.
     * Not used for options of type "input" — those are free-text from the buyer.
     *
     * Examples (for a clothing product with Color + Size options):
     *
     *   option_id=1 (Color) → label="Red",   value="red",   meta={"hex":"#EF4444"}
     *   option_id=1 (Color) → label="Blue",  value="blue",  meta={"hex":"#3B82F6"}
     *   option_id=1 (Color) → label="Black", value="black", meta={"hex":"#000000"}
     *   option_id=2 (Size)  → label="S",     value="s",     meta=null
     *   option_id=2 (Size)  → label="M",     value="m",     meta=null
     *   option_id=2 (Size)  → label="L",     value="l",     meta=null
     *   option_id=2 (Size)  → label="XL",    value="xl",    meta=null
     *
     * `label`  — what the buyer sees in the UI ("Red", "Extra Large")
     * `value`  — normalized/slug version used internally ("red", "xl")
     * `meta`   — optional extra data per type:
     *              color → { "hex": "#EF4444" }
     *              image → { "image_url": "https://..." }
     *              size  → { "size_chart_note": "EU 42" }  (optional)
     */
    public function up(): void
    {
        Schema::create('product_option_values', function (Blueprint $table) {
            $table->id();

            $table->foreignId('option_id')
                ->constrained('product_options')
                ->onDelete('cascade');

            // Human-readable label shown to the buyer
            $table->string('label');

            // Normalized slug-friendly value used internally
            $table->string('value');

            // Extra metadata depending on option type
            $table->json('meta')->nullable();

            // Display order within this option's value list
            $table->unsignedTinyInteger('position')->default(1);

            $table->timestamps();

            // The same value cannot appear twice within the same option
            $table->unique(['option_id', 'value']);
            $table->index('option_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_option_values');
    }
};
