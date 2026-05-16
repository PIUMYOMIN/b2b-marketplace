<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();

            $table->string('slug')->unique();           // basic | professional | enterprise
            $table->string('name');                     // Basic | Professional | Enterprise
            $table->text('description')->nullable();

            // Pricing
            $table->decimal('price_mmk', 12, 2)->default(0);   // Monthly price in MMK; 0 = free
            $table->string('billing_cycle')->default('monthly'); // monthly | yearly (future)

            // Feature limits  (-1 means unlimited)
            $table->integer('product_limit')->default(20);       // Max active products
            $table->decimal('commission_rate', 6, 4)->default(0.05); // e.g. 0.05 = 5%

            // Feature flags
            $table->boolean('analytics_enabled')->default(false);
            $table->boolean('bulk_import_enabled')->default(false);
            $table->boolean('priority_support')->default(false);
            $table->boolean('custom_storefront')->default(false);
            $table->boolean('is_active')->default(true);

            // Display order
            $table->unsignedTinyInteger('sort_order')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_plans');
    }
};