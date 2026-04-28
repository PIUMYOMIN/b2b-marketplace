<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Pivot table linking each variant to its specific option value choices.
     * Replaces the previous JSON `option_value_ids` column on product_variants,
     * giving proper FK integrity and clean queryability.
     *
     * Example — T-shirt variant "Red, M" (variant_id=2):
     *
     *   variant_id | option_value_id | (resolved)
     *   -----------|-----------------|------------------
     *   2          | 1               | Color → Red
     *   2          | 5               | Size  → M
     *
     * Querying all "Red" variants across a product:
     *   SELECT variant_id FROM product_variant_option_values
     *   WHERE option_value_id = 1;  -- 1 = "Red"
     *
     * Querying the full option combination for a variant:
     *   SELECT pov.label, pov.value, po.name
     *   FROM product_variant_option_values pvov
     *   JOIN product_option_values pov ON pov.id = pvov.option_value_id
     *   JOIN product_options po ON po.id = pov.option_id
     *   WHERE pvov.variant_id = 2;
     */
    public function up(): void
    {
        Schema::create('product_variant_option_values', function (Blueprint $table) {
            $table->foreignId('variant_id')
                ->constrained('product_variants')
                ->onDelete('cascade');

            $table->foreignId('option_value_id')
                ->constrained('product_option_values')
                ->onDelete('cascade');

            // Composite PK — one variant cannot have the same value twice
            $table->primary(['variant_id', 'option_value_id']);

            // Allows querying "all variants that have Color = Red"
            $table->index('option_value_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variant_option_values');
    }
};
