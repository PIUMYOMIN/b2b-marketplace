<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('shipping_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_profile_id')->constrained('seller_profiles')->onDelete('cascade');
            $table->boolean('enabled')->default(true);
            $table->enum('processing_time', ['same_day', '1_2_days', '3_5_days', '5_7_days', 'custom'])->default('3_5_days');
            $table->string('custom_processing_time')->nullable();
            $table->decimal('free_shipping_threshold', 10, 2)->nullable();
            $table->boolean('free_shipping_enabled')->default(false);
            $table->json('shipping_methods')->nullable(); // ['standard', 'express', 'next_day']
            $table->json('delivery_areas')->nullable(); // Cities/states where delivery is available
            $table->json('shipping_rates')->nullable(); // Rate structure
            $table->boolean('international_shipping')->default(false);
            $table->json('international_rates')->nullable();
            $table->string('package_weight_unit')->default('kg');
            $table->decimal('default_package_weight', 8, 2)->default(1.0);
            $table->text('shipping_policy')->nullable();
            $table->text('return_policy')->nullable();
            $table->timestamps();

            $table->unique('seller_profile_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('shipping_settings');
    }
};
