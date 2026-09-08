<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seller_delivery_areas', function (Blueprint $table) {
            $table->decimal('included_weight_kg', 8, 2)->default(2)->after('shipping_fee');
            $table->decimal('additional_weight_step_kg', 8, 2)->default(0.5)->after('included_weight_kg');
            $table->decimal('additional_weight_fee', 12, 2)->default(400)->after('additional_weight_step_kg');
        });
    }

    public function down(): void
    {
        Schema::table('seller_delivery_areas', function (Blueprint $table) {
            $table->dropColumn([
                'included_weight_kg',
                'additional_weight_step_kg',
                'additional_weight_fee',
            ]);
        });
    }
};
