<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('seller_delivery_areas', function (Blueprint $table) {
            $table->id();

            // Foreign keys
            $table->foreignId('seller_profile_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // Area type and location
            $table->enum('area_type', ['country', 'state', 'city', 'township', 'specific_address'])->default('city');
            $table->string('country', 80)->default('Myanmar');
            $table->string('state', 80)->nullable();
            $table->string('city', 80)->nullable();
            $table->string('township', 150)->nullable();
            $table->string('specific_location', 200)->nullable();
            $table->string('postal_code', 20)->nullable();

            // Delivery settings
            $table->boolean('is_deliverable')->default(true);
            $table->decimal('shipping_fee', 10, 2)->default(0);
            $table->decimal('free_shipping_threshold', 10, 2)->nullable();
            $table->integer('estimated_delivery_days_min')->nullable();
            $table->integer('estimated_delivery_days_max')->nullable();

            // Shipping methods availability
            $table->boolean('standard_shipping_available')->default(true);
            $table->boolean('express_shipping_available')->default(false);
            $table->boolean('pickup_available')->default(false);
            $table->string('pickup_location', 150)->nullable();

            // Restrictions
            $table->boolean('has_weight_limit')->default(false);
            $table->decimal('max_weight_kg', 8, 2)->nullable();
            $table->boolean('has_size_limit')->default(false);
            $table->json('size_restrictions')->nullable();
            $table->json('product_category_restrictions')->nullable();
            $table->json('excluded_dates')->nullable(); // For holidays, etc.

            // Status
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);

            // Timestamps
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['seller_profile_id', 'area_type']);
            $table->index(['country', 'state', 'city']);
            $table->index(['is_active', 'is_deliverable']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('seller_delivery_areas');
    }
};